<?php
Model::AddSchema("Products");
Model::AddSchema("ProductImages");
Model::AddSchema("ProductInventory");
Model::AddSchema("ProductTags");
Model::AddSchema("Logs");
class ProductsModel extends Model
{   
    //GET
    public $Search;
    public $SearchTag;
    public $Page;
    public $Count;
    public $Result;
    public $PageCount;
    //POST, PUT
    public $Id;
    public $Code;
    public $OldCode;
    public $Brand;
    public $Name;
    public $Description;
    public $Tags;
    public $Price;
    public $SalePrice;
    public $ImagePaths;
    function Validate() : iterable
    {
        if ($this->Get())
        {
            yield "Search" => $this->CheckInput("Search", false, Type::All);
            yield "SearchTag" => $this->CheckInput("SearchTag", false, Type::All);
            yield "Page" => $this->CheckInput("Page", false, Type::Numeric);
            yield "Count" => $this->CheckInput("Count", false, Type::Numeric);
        }
        if ($this->Post() || $this->Put())
        {
            if ($this->Put())
            {
                yield "Id" => $this->CheckInput("Id", true, Type::Numeric, 25);
                $this->OldCode = $this->Code;
            }
            yield "Code" => $this->CheckInput("Code", true, Type::AlphaNumeric, 25);
            yield "Brand" => $this->CheckInput("Brand", true, Type::All, 100);
            yield "Name" => $this->CheckInput("Name", true, Type::All, 100);
            yield "Description" => $this->CheckInput("Description", false, Type::All, 1000);
            yield "Tags" => $this->CheckInput("Tags", false, Type::All, 255);
            yield "Price" => $this->CheckInput("Price", true, Type::Currency, 25);
            yield "SalePrice" => $this->CheckInput("SalePrice", true, Type::Currency, 25);
            if ($this->IsValid("Code"))
            {
                $products = new Products();
                if ($products->CodeExists($this->Code, $this->OldCode))
                {
                    yield "Code" => GetMessage("CodeExists");
                }
            }
        }
        if ($this->Delete())
        {
            yield "Id" => $this->CheckInput("Id", true, Type::Numeric, 25);
        }
    }
    function Map() : void
    {
        $products = new Products();
        $inventory = new ProductInventory();
        $images = new ProductImages();
        $tags = new ProductTags();
        if (_::HasValue($this->Id))
        {
            $products->id = $this->Id;
            $products->Join($inventory, "int_product_id", "id");
            $this->Result = $products->SelectSingle();
            $images->int_product_id = $products->id;
            $this->Result->ImagePaths = $images->GetImages();
            $tags->int_product_id = $products->id;
            $this->Result->Tags = $tags->GetTags();
        }
        else
        {
            $products->Join($images, "int_product_id", "id");
            $products->Join($inventory, "int_product_id", "id");
            $products->Join($tags, "int_product_id", "id");
            if (_::HasValue($this->SearchTag))
            {
                $products->Where("product_tags.str_tag", DB::Equal, $this->SearchTag);
            }
            else
            {
                $products->Where("str_code", DB::Like, "%".$this->Search."%", DB::OR);
                $products->Where("str_name", DB::Like, "%".$this->Search."%", DB::OR);
                $products->Where("str_brand", DB::Like, "%".$this->Search."%");
            }
            $products->GroupBy("id");
            $products->OrderBy("id", DB::DESC);
            $products->Page((int)$this->Page, (int)$this->Count);
            $this->Result = $products->Select();
            $this->PageCount = $products->PageCount((int)$this->Count);
        }
    }
    function Handle() : void
    {
        $logs = new Logs();
        $products = new Products();
        if ($this->Post() || $this->Put())
        {
            $products->str_code = $this->Code;
            $products->str_brand = $this->Brand;
            $products->str_name = $this->Name;
            $products->txt_description = $this->Description;
            $products->dbl_price = $this->Price;
            $products->dbl_sale_price = $this->SalePrice;
            if ($this->Post())
            {
                $products->Insert();
                $this->Id = $products->id;
                $this->UpdateImages($products->id);
                $this->UpdateTags($products->id);
                $logs->str_action = Action::Created;
            }
            else if ($this->Put())
            {
                $products->Where("id", DB::Equal, $this->Id);
                $products->Update();
                $this->UpdateImages($this->Id);
                $this->UpdateTags($this->Id);
                $logs->str_action = Action::Updated;
            }
        }
        else if ($this->Delete())
        {
            $products->id = $this->Id;
            $products->SelectSingle();
            $products->Delete();
            $productImages = new ProductImages();
            $productImages->int_product_id = $this->Id;
            $productImages->Delete();
            $productInventory = new ProductInventory();
            $productInventory->int_product_id = $this->Id;
            $productInventory->Delete();
            $logs->str_action = Action::Deleted;
        }
        $logs->str_code = $products->str_code;
        $logs->LogAction();
    }
    function UpdateImages($id) : void
    {
        $productImages = new ProductImages();
        $productImages->int_product_id = $id;
        $productImages->Delete();
        foreach ($this->ImagePaths as $key => $value)
        {
            $productImages->str_path = $this->SaveFile($value, $this->Code."-".$key);
            $productImages->Insert();
        }
    }
    function UpdateTags($id) : void
    {
        $productTags = new ProductTags();
        $productTags->int_product_id = $id;
        $productTags->Delete();
        $tags = explode(",", $this->Tags);
        $tags = array_map("trim", $tags);
        foreach ($tags as $tag)
        {
            if (_::HasValue($tag))
            {
                $productTags->str_tag = $tag;
                $productTags->Insert();
            }
        }
    }
}
?>