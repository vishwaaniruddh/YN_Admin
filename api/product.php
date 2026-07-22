<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/db.php';
require_once '../includes/functions.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$slug && !$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product slug or id is required']);
    exit;
}

try {
    $sql = "SELECT p.*, 
            (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM product_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.product_id = p.id) as category_name 
            FROM products p 
            WHERE p.status = 'published' AND p.deleted_at IS NULL";
            
    $params = [];
    if ($slug) {
        $sql .= " AND p.slug = ?";
        $params[] = $slug;
    } else {
        $sql .= " AND p.id = ?";
        $params[] = $id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $product = $stmt->fetch();
    
    if ($product) {
        // Increment view count
        $updateStmt = $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?");
        $updateStmt->execute([$product['id']]);
        $product['view_count']++; // Update the fetched data to reflect the increment
        
        // Log view activity
        log_activity($pdo, 'view_product', 'product', $product['id'], "Viewed product: " . $product['name'], null, 'guest');

        // Fetch product images
        $imgStmt = $pdo->prepare("SELECT image_path, thumb_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
        $imgStmt->execute([$product['id']]);
        $product['images'] = $imgStmt->fetchAll();
        
        // Evaluate dynamic Discount Architect rules
        $discount = get_product_discount_info($pdo, $product['id'], $product['price']);
        if ($discount) {
            $product['original_price'] = (float)$product['price'];
            $product['discount_info'] = $discount;
            $product['sale_price'] = $discount['discounted_price'];
            $product['has_discount'] = true;
        } else {
            $product['original_price'] = (float)$product['price'];
            $product['has_discount'] = false;
        }

        echo json_encode([
            'success' => true,
            'data' => $product
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Product not found'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch product: ' . $e->getMessage()
    ]);
}
?>
