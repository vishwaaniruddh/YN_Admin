<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['product_id'] = 1800;
$_POST['category_ids'] = [8];
$_POST['name'] = 'Test';
$_POST['slug'] = 'test';
$_POST['sku'] = 'YNL170';
$_POST['description'] = 'test';
$_POST['short_description'] = 'test';
$_POST['price'] = 100;
$_POST['sale_price'] = '';
$_POST['stock_qty'] = 10;
$_POST['is_featured'] = 0;
$_POST['status'] = 'published';
$_FILES['main_image'] = ['name' => '', 'error' => 4];

// simulate product-edit.php execution
require_once 'c:\xampp\htdocs\yn\admin\config\db.php';

$product_id = 1800;
$category_ids = [8];
$name = 'test';
$slug = 'test';
$sku = 'YNL170';
$description = 'test';
$short_description = 'test';
$price = 100;
$sale_price = null;
$stock_qty = 10;
$is_featured = 0;
$status = 'published';
$main_image = '';

$pdo->beginTransaction();
try {
    $primary_category_id = !empty($category_ids) ? (int)$category_ids[0] : null;

    $sql = "UPDATE products SET 
        category_id = ?, name = ?, slug = ?, sku = ?, description = ?, 
        short_description = ?, price = ?, sale_price = ?, stock_qty = ?, 
        is_featured = ?, status = ?, main_image = ? 
        WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $primary_category_id,
        $name,
        $slug,
        $sku,
        $description,
        $short_description,
        $price,
        $sale_price,
        $stock_qty,
        $is_featured,
        $status,
        $main_image,
        $product_id
    ]);

    $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$product_id]);
    if (!empty($category_ids)) {
        $cat_stmt = $pdo->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
        foreach ($category_ids as $cat_id) {
            $cat_stmt->execute([$product_id, (int)$cat_id]);
        }
    }
    
    $pdo->commit();
    echo "Edit Committed successfully.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
