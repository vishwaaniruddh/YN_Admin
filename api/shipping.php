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

$subtotal = isset($_GET['subtotal']) ? (float)$_GET['subtotal'] : 0;
if (isset($_POST['subtotal'])) {
    $subtotal = (float)$_POST['subtotal'];
}

$shippingCharge = get_shipping_charge($pdo, $subtotal);

echo json_encode([
    'success' => true,
    'subtotal' => $subtotal,
    'shipping_charge' => $shippingCharge,
    'is_free' => ($shippingCharge == 0),
    'text' => ($shippingCharge == 0) ? 'FREE' : ('₹' . number_format($shippingCharge, 2))
]);
