<?php
require_once 'c:\xampp\htdocs\yn\admin\config\db.php';

$valid_cat_stmt = $pdo->query("SELECT id FROM categories");
$valid_category_ids = $valid_cat_stmt->fetchAll(PDO::FETCH_COLUMN);

$sku = 'YNL170';
$name = 'Test Name';
$category_id = 8;
$product_id = 1800;
$description = 'test';
$short_description = 'test';
$price = 100;
$stock_qty = 10;
$main_image = '';

$pdo->beginTransaction();

try {
    $check_stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
    $check_stmt->execute([$sku]);
    $existing = $check_stmt->fetch();
    
    if ($existing) {
        $product_id = $existing['id'];
        $update_stmt = $pdo->prepare("
            UPDATE products 
            SET name = ?, description = ?, short_description = ?, category_id = ?, price = ?, stock_qty = ?, main_image = ?, status = 'published'
            WHERE sku = ?
        ");
        $update_stmt->execute([
            $name, $description, $short_description, $category_id, $price, $stock_qty, $main_image, $sku
        ]);
        echo "Updated product_id: $product_id\n";
    }

    if ($category_id) {
        echo "Category is truthy ($category_id). Deleting and inserting...\n";
        $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$product_id]);
        $cat_stmt = $pdo->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
        $cat_stmt->execute([$product_id, $category_id]);
        echo "Inserted into product_categories for product $product_id and category $category_id\n";
    }

    $pdo->commit();
    echo "Committed successfully.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
