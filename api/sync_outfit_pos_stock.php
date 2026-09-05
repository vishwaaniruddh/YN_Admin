<?php
// admin/api/sync_outfit_pos_stock.php
// Synchronize outfit products stock with POS phppos_items
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $result = sync_outfit_stock_from_pos($pdo);
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sync failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
