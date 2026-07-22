<?php
// admin/api/cart.php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

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
        // Fetch cart items
        $stmt = $pdo->prepare("
            SELECT c.id as cart_item_id, c.quantity, p.* 
            FROM cart_items c
            JOIN products p ON c.product_id = p.id
            WHERE c.session_token = ?
        ");
        $stmt->execute([$session_token]);
        $items = $stmt->fetchAll();
        
        $total = 0;
        foreach($items as &$item) {
            $discount = get_product_discount_info($pdo, $item['id'], $item['price']);
            if ($discount) {
                $item['original_price'] = (float)$item['price'];
                $item['discount_info'] = $discount;
                $item['sale_price'] = $discount['discounted_price'];
                $item['has_discount'] = true;
                $effectivePrice = $discount['discounted_price'];
            } else {
                $item['original_price'] = (float)$item['price'];
                $item['has_discount'] = false;
                $effectivePrice = (float)($item['sale_price'] > 0 ? $item['sale_price'] : $item['price']);
            }
            $total += $effectivePrice * $item['quantity'];
        }

        echo json_encode(["success" => true, "data" => ["items" => $items, "total" => $total]]);
    } 
    elseif ($method === 'POST') {
        // Add to cart
        $data = json_decode(file_get_contents("php://input"), true);
        $product_id = $data['product_id'] ?? null;
        $quantity = (int)($data['quantity'] ?? 1);

        if (!$product_id) {
            throw new Exception("Product ID is required");
        }

        // Check stock quantity from products table
        $pStmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
        $pStmt->execute([$product_id]);
        $prod = $pStmt->fetch();
        $availableStock = $prod && $prod['stock_qty'] !== null ? (int)$prod['stock_qty'] : 99;

        // Check if exists
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE session_token = ? AND product_id = ?");
        $stmt->execute([$session_token, $product_id]);
        $existing = $stmt->fetch();

        $new_qty = $existing ? ($existing['quantity'] + $quantity) : $quantity;

        if ($availableStock > 0 && $new_qty > $availableStock) {
            echo json_encode(["success" => false, "message" => "Cannot add more items than available stock ({$availableStock} available)."]);
            exit();
        }

        if ($existing) {
            // Update quantity
            $update = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            $update->execute([$new_qty, $existing['id']]);
        } else {
            // Insert
            $insert = $pdo->prepare("INSERT INTO cart_items (session_token, product_id, quantity) VALUES (?, ?, ?)");
            $insert->execute([$session_token, $product_id, $quantity]);
        }
        
        echo json_encode(["success" => true, "message" => "Added to cart"]);
    } 
    elseif ($method === 'PUT') {
        // Update quantity
        $data = json_decode(file_get_contents("php://input"), true);
        $product_id = $data['product_id'] ?? null;
        $quantity = isset($data['quantity']) ? (int)$data['quantity'] : null;

        if (!$product_id || $quantity === null) {
            throw new Exception("Product ID and quantity are required");
        }

        $pStmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
        $pStmt->execute([$product_id]);
        $prod = $pStmt->fetch();
        $availableStock = $prod && $prod['stock_qty'] !== null ? (int)$prod['stock_qty'] : 99;

        if ($availableStock > 0 && $quantity > $availableStock) {
            echo json_encode(["success" => false, "message" => "Cannot set quantity higher than available stock ({$availableStock} available)."]);
            exit();
        }

        if ($quantity <= 0) {
            // Remove item if quantity is 0 or less
            $del = $pdo->prepare("DELETE FROM cart_items WHERE session_token = ? AND product_id = ?");
            $del->execute([$session_token, $product_id]);
        } else {
            $update = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE session_token = ? AND product_id = ?");
            $update->execute([$quantity, $session_token, $product_id]);
        }

        echo json_encode(["success" => true, "message" => "Cart updated"]);
    } 
    elseif ($method === 'DELETE') {
        // Remove single item or clear whole cart
        $product_id = $_GET['product_id'] ?? null;
        if ($product_id) {
            $del = $pdo->prepare("DELETE FROM cart_items WHERE session_token = ? AND product_id = ?");
            $del->execute([$session_token, $product_id]);
            echo json_encode(["success" => true, "message" => "Item removed"]);
        } else {
            $del = $pdo->prepare("DELETE FROM cart_items WHERE session_token = ?");
            $del->execute([$session_token]);
            echo json_encode(["success" => true, "message" => "Cart cleared"]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
