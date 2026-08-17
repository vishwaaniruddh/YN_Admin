<?php
// admin/api/cart.php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Get Session Token
$session_token = get_session_token();

if (!$session_token) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Session token is required"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Fetch cart items explicitly selecting p.id as product_id
        $stmt = $pdo->prepare("
            SELECT c.id as cart_item_id, c.quantity, p.id as product_id, p.* 
            FROM cart_items c
            JOIN products p ON c.product_id = p.id
            WHERE c.session_token = ?
            ORDER BY c.id DESC
        ");
        $stmt->execute([$session_token]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pre-fetch discount rules ONCE and batch-fetch category mappings
        $rules = get_cached_discount_rules($pdo);
        $categoryMap = [];
        if (!empty($items) && !empty($rules)) {
            $itemProductIds = array_column($items, 'product_id');
            $placeholders = implode(',', array_fill(0, count($itemProductIds), '?'));
            try {
                $catStmt = $pdo->prepare("SELECT product_id, category_id FROM product_categories WHERE product_id IN ($placeholders)");
                $catStmt->execute($itemProductIds);
                $catRows = $catStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($catRows as $row) {
                    $categoryMap[$row['product_id']][] = $row['category_id'];
                }
            } catch (Exception $e) {}
        }

        $total = 0;
        foreach($items as &$item) {
            // Ensure product_id is explicitly set
            $item['product_id'] = (int)$item['product_id'];
            $item['stock_qty'] = ($item['stock_qty'] !== null && $item['stock_qty'] !== '') ? (int)$item['stock_qty'] : 99;

            $catIds = $categoryMap[$item['product_id']] ?? [];
            $discount = get_product_discount_from_rules($rules, $item['product_id'], $item['price'], $catIds);
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
            echo json_encode(["success" => false, "message" => "Product ID is required"]);
            exit();
        }

        // Check stock quantity from products table
        $pStmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
        $pStmt->execute([$product_id]);
        $prod = $pStmt->fetch();
        $availableStock = ($prod && $prod['stock_qty'] !== null && $prod['stock_qty'] !== '') ? (int)$prod['stock_qty'] : 99;

        // Check if exists in cart
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE session_token = ? AND product_id = ?");
        $stmt->execute([$session_token, $product_id]);
        $existing = $stmt->fetch();

        $new_qty = $existing ? ($existing['quantity'] + $quantity) : $quantity;

        if ($availableStock > 0 && $new_qty > $availableStock) {
            echo json_encode(["success" => false, "message" => "Cannot add more items than available stock ({$availableStock} available).", "stock_qty" => $availableStock]);
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
            echo json_encode(["success" => false, "message" => "Product ID and quantity are required"]);
            exit();
        }

        $pStmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
        $pStmt->execute([$product_id]);
        $prod = $pStmt->fetch();
        $availableStock = ($prod && $prod['stock_qty'] !== null && $prod['stock_qty'] !== '') ? (int)$prod['stock_qty'] : 99;

        if ($quantity > 0 && $availableStock > 0 && $quantity > $availableStock) {
            echo json_encode(["success" => false, "message" => "Cannot set quantity higher than available stock ({$availableStock} available).", "stock_qty" => $availableStock]);
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
        if (!$product_id) {
            $input = json_decode(file_get_contents("php://input"), true);
            $product_id = $input['product_id'] ?? null;
        }

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
