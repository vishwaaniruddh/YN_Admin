<?php
$handle = fopen('c:\xampp\htdocs\yn\admin\products_export_20260716_142944.csv', 'r');
$header = fgetcsv($handle, 10000, ",");
print_r($header);
fclose($handle);
