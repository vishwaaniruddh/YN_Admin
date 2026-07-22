<?php
require_once 'config/db.php';

echo "Running migrations...\n";

try {
    // Add deleted_at and view_count to categories
    $pdo->exec("ALTER TABLE categories 
                ADD COLUMN IF NOT EXISTS view_count INT DEFAULT 0 AFTER parent_id,
                ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL AFTER view_count,
                ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL");
    echo "Added view_count, image_path, and deleted_at to categories.\n";
} catch (Exception $e) {
    echo "Error updating categories: " . $e->getMessage() . "\n";
}

try {
    // Add deleted_at and view_count to products
    $pdo->exec("ALTER TABLE products 
                ADD COLUMN IF NOT EXISTS view_count INT DEFAULT 0 AFTER main_image,
                ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL");
    echo "Added view_count and deleted_at to products.\n";
} catch (Exception $e) {
    echo "Error updating products: " . $e->getMessage() . "\n";
}

try {
    // Add role to admins
    $pdo->exec("ALTER TABLE admins ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'administrator' AFTER full_name");
    
    // Update existing users to be administrators if role is empty (since IF NOT EXISTS might not update existing nulls if we didn't specify DEFAULT properly in some SQL dialects, though DEFAULT handles it here)
    $pdo->exec("UPDATE admins SET role = 'administrator' WHERE role IS NULL OR role = ''");
    echo "Added role column to admins.\n";
} catch (Exception $e) {
    echo "Error updating admins: " . $e->getMessage() . "\n";
}

// Activity Logs and Search Logs are handled by config/db.php since they didn't exist

echo "Migrations completed.\n";
?>
