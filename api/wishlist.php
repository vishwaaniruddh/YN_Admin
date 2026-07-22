<?php
// admin/api/wishlist.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Session-Token");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/db.php';

// Get Session Token
$headers = getallheaders();
$session_token = isset($headers['X-Session-Token']) ? $headers['X-Session-Token'] : (isset($_GET['session_token']) ? $_GET['session_token'] : null);

if (!$session_token) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Session token is required"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Fetch wishlist items
        $stmt = $pdo->prepare("
            SELECT w.id as wishlist_item_id, p.* 
            FROM wishlist_items w
            JOIN products p ON w.product_id = p.id
            WHERE w.session_token = ?
        ");
        $stmt->execute([$session_token]);
        $items = $stmt->fetchAll();

        echo json_encode(["success" => true, "data" => $items]);
    } 
    elseif ($method === 'POST') {
        // Toggle wishlist item (Add or Remove)
        $data = json_decode(file_get_contents("php://input"), true);
        $product_id = $data['product_id'] ?? null;

        if (!$product_id) {
            throw new Exception("Product ID is required");
        }

        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM wishlist_items WHERE session_token = ? AND product_id = ?");
        $stmt->execute([$session_token, $product_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Remove
            $del = $pdo->prepare("DELETE FROM wishlist_items WHERE id = ?");
            $del->execute([$existing['id']]);
            $action = "removed";
        } else {
            // Insert
            $insert = $pdo->prepare("INSERT INTO wishlist_items (session_token, product_id) VALUES (?, ?)");
            $insert->execute([$session_token, $product_id]);
            $action = "added";
        }
        
        echo json_encode(["success" => true, "message" => "Wishlist updated", "action" => $action]);
    } 
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
