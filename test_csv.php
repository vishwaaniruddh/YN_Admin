<?php
$handle = fopen('c:\xampp\htdocs\yn\admin\products_export_20260716_142944.csv', 'r');
$header = fgetcsv($handle, 10000, ",");
$header_map = array_flip($header);
while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
    if (trim($data[$header_map['sku']]) === 'YNL170') {
        print_r($data);
        break;
    }
}
fclose($handle);
