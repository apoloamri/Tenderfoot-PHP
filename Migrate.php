<?php
$migration = new Migration();
$migration->Migrate("Admins");
$migration->Migrate("Members");

$admin1 = new stdClass();
$admin1->id = 1;
$admin1->username = "admin";
$admin1->password = "admin";
$admin1->last_name = "Administrator";
$admin1->first_name = "Tenderfoot";
$migration->Seed("Admins", $admin1);

$admin1 = new stdClass();
$admin1->id = 2;
$admin1->username = "admin2";
$admin1->password = "admin2";
$admin1->last_name = "Administrator2";
$admin1->first_name = "Tenderfoot2";
$migration->Seed("Admins", $admin1);
?>