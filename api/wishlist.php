<?php
// admin/api/wishlist.php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Auto-create wishlist_items table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `wishlist_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `session_token` VARCHAR(255) NOT NULL,
            `product_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user_product` (`session_token`, `product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {
    // ignore if table creation fails or exists
}

$session_token = get_session_token();

if (!$session_token) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Session token is required"]);
    exit();
}

function fetch_wishlist_with_discounts($pdo, $session_token) {
    $stmt = $pdo->prepare("
        SELECT w.id as wishlist_item_id, p.id as product_id, p.* 
        FROM wishlist_items w
        JOIN products p ON w.product_id = p.id
        WHERE w.session_token = ?
        ORDER BY w.id DESC
    ");
    $stmt->execute([$session_token]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    foreach($items as &$item) {
        $item['product_id'] = (int)$item['product_id'];
        $catIds = $categoryMap[$item['product_id']] ?? [];
        $discount = get_product_discount_from_rules($rules, $item['product_id'], $item['price'], $catIds);
        if ($discount) {
            $item['original_price'] = (float)$item['price'];
            $item['discount_info'] = $discount;
            $item['sale_price'] = $discount['discounted_price'];
            $item['has_discount'] = true;
            $item['discount_percent'] = $discount['value'] ?? ($discount['discount_value'] ?? 0);
        } else {
            $item['original_price'] = (float)$item['price'];
            $item['sale_price'] = (float)$item['price'];
            $item['has_discount'] = false;
            $item['discount_percent'] = 0;
        }
    }
    return $items;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $items = fetch_wishlist_with_discounts($pdo, $session_token);
        echo json_encode(["success" => true, "data" => $items]);
    } 
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $product_id = $data['product_id'] ?? null;

        if (!$product_id) {
            throw new Exception("Product ID is required");
        }

        $stmt = $pdo->prepare("SELECT id FROM wishlist_items WHERE session_token = ? AND product_id = ?");
        $stmt->execute([$session_token, $product_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            $del = $pdo->prepare("DELETE FROM wishlist_items WHERE id = ?");
            $del->execute([$existing['id']]);
            $action = "removed";
        } else {
            $insert = $pdo->prepare("INSERT INTO wishlist_items (session_token, product_id) VALUES (?, ?)");
            $insert->execute([$session_token, $product_id]);
            $action = "added";
        }
        
        $items = fetch_wishlist_with_discounts($pdo, $session_token);
        echo json_encode(["success" => true, "message" => "Wishlist updated", "action" => $action, "data" => $items]);
    } 
    elseif ($method === 'DELETE') {
        $product_id = $_GET['product_id'] ?? null;
        if ($product_id) {
            $stmt = $pdo->prepare("DELETE FROM wishlist_items WHERE session_token = ? AND product_id = ?");
            $stmt->execute([$session_token, $product_id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM wishlist_items WHERE session_token = ?");
            $stmt->execute([$session_token]);
        }
        
        $items = fetch_wishlist_with_discounts($pdo, $session_token);
        echo json_encode(["success" => true, "message" => "Item removed", "data" => $items]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
