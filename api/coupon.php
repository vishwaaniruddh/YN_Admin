<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

$action = $_GET['action'] ?? ($data['action'] ?? 'validate');

if ($action === 'validate') {
    $code = trim($data['code'] ?? ($_GET['code'] ?? ''));
    $subtotal = (float)($data['subtotal'] ?? ($_GET['subtotal'] ?? 0));

    $res = validate_and_apply_coupon($pdo, $code, $subtotal);
    if ($res['valid']) {
        echo json_encode([
            'success' => true,
            'valid' => true,
            'coupon_id' => $res['coupon_id'],
            'code' => $res['code'],
            'description' => $res['description'],
            'discount_type' => $res['discount_type'],
            'coupon_amount' => $res['coupon_amount'],
            'discount_amount' => $res['discount_calculated'],
            'new_subtotal' => $res['new_subtotal'],
            'message' => $res['message']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'valid' => false,
            'message' => $res['message']
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
