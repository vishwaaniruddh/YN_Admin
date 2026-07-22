<?php
// admin/api/desc_corrector.php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
$description = isset($data['description']) ? trim($data['description']) : '';

if (!$id || empty($description)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Product ID and non-empty description are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE products SET description = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$description, $id]);

    log_activity($pdo, 'update_product_description', 'product', $id, "Updated product #{$id} description via Description Corrector tool");

    echo json_encode(['success' => true, 'message' => 'Product description updated successfully!']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
