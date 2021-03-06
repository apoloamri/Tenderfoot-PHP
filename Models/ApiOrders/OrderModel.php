<?php
Model::AddSchema("Carts");
Model::AddSchema("Orders");
Model::AddSchema("OrderRecords");
Model::AddSchema("Products");
Model::AddSchema("ProductInventory");
Model::AddSchema("ProductViews");
Model::AddSchema("Logs");
class OrderModel extends Model
{   
    //GET
    public $Search;
    public $OrderStatus;
    public $Page;
    public $Count;
    public $Result;
    public $PageCount;
    //POST
    public $Id;
    public $OrderNumber;
    public $PhoneNumber;
    public $Email;
    public $LastName;
    public $FirstName;
    public $Address;
    public $Barangay;
    public $City;
    public $PostalCode;
    public $CartItems;
    public $Total;
    function Validate() : iterable
    {
        if ($this->Get())
        {
            yield "Search" => $this->CheckInput("Search", false, Type::All);
            yield "Page" => $this->CheckInput("Page", false, Type::Numeric);
            yield "Count" => $this->CheckInput("Count", false, Type::Numeric);
        }
        else if ($this->Post())
        {
            $sessionId = GetSession()->SessionId;
            if (_::HasValue($sessionId))
            {
                $carts = new Carts();
                $carts->str_session_id = $sessionId;
                if (!$carts->Exists())
                {
                    yield "sessionId" => GetMessage("InvalidAccess");
                }
            }
            yield "PhoneNumber" => $this->CheckInput("PhoneNumber", true, Type::PhoneNumber, 255);
            yield "Email" => $this->CheckInput("Email", true, Type::Email, 255);
            yield "LastName" => $this->CheckInput("LastName", true, Type::AlphaNumeric, 255);
            yield "FirstName" => $this->CheckInput("FirstName", true, Type::AlphaNumeric, 255);
            yield "Address" => $this->CheckInput("Address", true, Type::All, 255);
            yield "Barangay" => $this->CheckInput("Barangay", false, Type::AlphaNumeric, 255);
            yield "City" => $this->CheckInput("City", true, Type::AlphaNumeric, 255);
            yield "PostalCode" => $this->CheckInput("PostalCode", false, Type::All, 255);
        }
        else if ($this->Put() || $this->Delete())
        {
            yield "Id" => $this->CheckInput("Id", false, Type::All);
            if ($this->IsValid("Id"))
            {
                $orders = new Orders();
                $orders->id = $this->Id;
                if (!$orders->Exists())
                {
                    yield "Id" => GetMessage("IdDoesNotExist");
                }
                else
                {
                    if ($this->Delete())
                    {
                        $orders->SelectSingle();
                        if ($orders->str_order_status == OrderStatus::Cancelled)
                        {
                            yield "Id" => GetMessage("InvalidOperation");
                        }
                    }
                }
            }
        }
    }
    function Map() : void
    {
        $orders = new Orders();
        if (_::HasValue($this->Id))
        {
            $orders->id = $this->Id;
            $this->Result = $orders->SelectSingle();
            $orderRecords = new OrderRecords();
            $orderRecords->int_order_id = $this->Id;
            $this->CartItems = $orderRecords->Select();
        }
        else
        {
            $orders->Where("str_order_number", DB::Like, "%".$this->Search."%", DB::OR);
            $orders->Where("str_last_name", DB::Like, "%".$this->Search."%", DB::OR);
            $orders->Where("str_first_name", DB::Like, "%".$this->Search."%");
            $orders->Combine(DB::AND);
            if (_::HasValue($this->OrderStatus))
            {
                $orders->Where("str_order_status", DB::Equal, $this->OrderStatus, DB::AND);
            }
            else if (!_::HasValue($this->Search))
            {
                $orders->Where("str_order_status", DB::NotEqual, OrderStatus::Fulfilled, DB::AND);
                $orders->Where("str_order_status", DB::NotEqual, OrderStatus::Cancelled, DB::AND);
                $orders->Combine(DB::AND);
            }
            $orders->OrderBy("dat_insert_time", DB::DESC);
            $orders->Page((int)$this->Page, (int)$this->Count);
            $this->Result = $orders->Select();    
            $this->PageCount = $orders->PageCount((int)$this->Count);
        }
    }
    function Handle() : void
    {
        $now = _::Now();
        $orders = new Orders();
        if ($this->Post())
        {
            $orders->CreateOrderNumber();
            $orders->str_phonenumber = $this->PhoneNumber;
            $orders->str_email = $this->Email;
            $orders->str_last_name = $this->LastName;
            $orders->str_first_name = $this->FirstName;
            $orders->str_address = $this->Address;
            $orders->str_barangay = $this->Barangay;
            $orders->str_city = $this->City;
            $orders->str_postal = $this->PostalCode;
            $orders->str_order_status = OrderStatus::NewOrder;
            $carts = new Carts();
            $carts->Join(new Products(), "str_code", "str_code");
            $carts->str_session_id = GetSession()->SessionId;
            $cartItems = $carts->Select();
            $orders->dbl_total = $this->GetTotal($cartItems);
            $orders->Insert();
            foreach ($cartItems as $cartItem)
            {
                $orderRecords = new OrderRecords();
                $orderRecords->OverwriteWithModel($cartItem);
                $orderRecords->dbl_price = 
                    $cartItem->dbl_sale_price != 0 ? 
                    $cartItem->dbl_sale_price : 
                    $cartItem->dbl_price;
                $orderRecords->int_order_id = $orders->id;
                $orderRecords->int_product_id = $cartItem->{'products->id'};
                $orderRecords->dbl_total_price = (int)$orderRecords->int_amount * (int)$orderRecords->dbl_price;
                $orderRecords->Insert();
                $this->UpdateInventory((int)$orderRecords->int_product_id, -(int)$orderRecords->int_amount);
                $this->AddPurchase((int)$cartItem->{'products->id'});
                
            }
            $this->OrderNumber = $orders->str_order_number;
            $this->CartItems = $cartItems;
            $this->SendEmail();
            $carts->Delete();
        }
        else if ($this->Put() || $this->Delete())
        {
            $logs = new Logs();
            $orders->Where("id", DB::Equal, $this->Id);
            $orders->SelectSingle();
            if ($this->Put())
            {
                switch ($orders->str_order_status)
                {
                    case OrderStatus::NewOrder:
                    $orders->str_order_status = OrderStatus::Processed;
                    $logs->str_action = OrderStatus::Processed;
                    break;
                    case OrderStatus::Processed:
                    $orders->str_order_status = OrderStatus::OnDelivery;
                    $logs->str_action = OrderStatus::OnDelivery;
                    break;
                    case OrderStatus::OnDelivery:
                    $orders->str_order_status = OrderStatus::Delivered;
                    $logs->str_action = OrderStatus::Delivered;
                    break;
                    case OrderStatus::Delivered:
                    $orders->str_order_status = OrderStatus::Fulfilled;
                    $logs->str_action = OrderStatus::Fulfilled;
                    break;
                }
            }
            else if ($this->Delete())
            {
                $orders->str_order_status = OrderStatus::Cancelled;
                $logs->str_action = OrderStatus::Cancelled;
            }
            $orders->Update();
            $logs->str_code = $orders->str_order_number;
            $logs->LogAction();
            $orderRecords = new OrderRecords();
            $orderRecords->int_order_id = $this->Id;
            $result = $orderRecords->Select();
            foreach ($result as $orderRecord)
            {
                $this->UpdateInventory((int)$orderRecord->int_product_id, (int)$orderRecord->int_amount);
            }
        }
    }
    function GetTotal($cartItems) : int
    {
        $price = 0;
        foreach ($cartItems as $item)
        {
            $price += $item->int_amount * (
                $item->dbl_sale_price != 0 ? 
                $item->dbl_sale_price : 
                $item->dbl_price);
        }
        return $price;
    }
    function UpdateInventory(int $productId, int $amount) : void
    {
        $inventory = new ProductInventory();
        $inventory->int_product_id = $productId;
        $inventory->SelectSingle();
        $inventory->int_amount = $inventory->int_amount + $amount;
        $inventory->Where("int_product_id", DB::Equal, $productId);
        $inventory->Update(); 
    }
    function AddPurchase(int $productId) : void
    {
        $views = new ProductViews();
        $views->int_product_id = $productId;
        $views->AddPurchase();
    }
    public $Url;
    function SendEmail() : void
    {
        $this->Url = Settings::SiteUrlSSL();
        $email = new Email($this, "OrderComplete");
        $email->SendEmail();
        $email = new Email($this, "OrderCompleteCustomer");
        $email->AddEmailTo($this->Email);
        $email->SendEmail();
    }
}
?>