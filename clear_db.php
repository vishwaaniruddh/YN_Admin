<?php
// admin/clear_db.php
require_once __DIR__ . '/config/db.php';

try {
    echo "Disabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $tables_to_truncate = [
        'product_images',
        'products',
        'categories',
        'blog_images',
        'blogs',
        'order_items',
        'orders',
        'cart_items',
        'wishlists',
        'activity_logs',
        'search_logs',
        'newsletters'
    ];

    foreach ($tables_to_truncate as $table) {
        echo "Truncating table: $table...\n";
        // Check if table exists before truncating to avoid errors if some tables aren't created yet
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $pdo->exec("TRUNCATE TABLE `$table`");
            echo "  - Truncated.\n";
        } else {
            echo "  - Table does not exist, skipping.\n";
        }
    }

    echo "Re-enabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "Database cleared successfully (Admins and Users tables were preserved).\n";
} catch (Exception $e) {
    echo "Error clearing database: " . $e->getMessage() . "\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); // Ensure it's re-enabled even on error
}
