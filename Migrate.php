<?php
$migration = new Migration();
$migration->Migrate("Admins");
$migration->Migrate("Carts");
$migration->Migrate("Logs");
$migration->Migrate("OrderRecords");
$migration->Migrate("Orders");
$migration->Migrate("ProductImages");
$migration->Migrate("ProductInventory");
$migration->Migrate("Products");
$migration->Migrate("ProductTagImages");
$migration->Migrate("ProductTags");
$migration->Migrate("ProductViews");
$migration->Migrate("Store");

$admin1 = new stdClass();
$admin1->id = 1;
$admin1->str_username = "admin";
$admin1->str_password = "admin";
$admin1->str_last_name = "Administrator";
$admin1->str_first_name = "Tenderfoot";
$migration->Seed("Admins", $admin1);

$admin1 = new stdClass();
$admin1->id = 2;
$admin1->str_username = "admin2";
$admin1->str_password = "admin2";
$admin1->str_last_name = "Administrator2";
$admin1->str_first_name = "Tenderfoot2";
$migration->Seed("Admins", $admin1);
?>