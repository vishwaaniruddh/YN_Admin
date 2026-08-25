<?php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cache.php';

// Handle Review Submission (POST method or action=add_review)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_GET['action']) && $_GET['action'] === 'add_review')) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $productId = (int)($input['product_id'] ?? $_GET['id'] ?? 0);
    $reviewerName = trim($input['reviewer_name'] ?? '');
    $reviewerEmail = trim($input['reviewer_email'] ?? '');
    $rating = (int)($input['rating'] ?? 5);
    $title = trim($input['title'] ?? '');
    $comment = trim($input['comment'] ?? '');

    if ($productId <= 0 || empty($reviewerName) || empty($comment)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product ID, Name, and Review Comment are required.']);
        exit;
    }

    if ($rating < 1) $rating = 1;
    if ($rating > 5) $rating = 5;

    try {
        $stmtIns = $pdo->prepare("INSERT INTO product_reviews (product_id, reviewer_name, reviewer_email, rating, title, comment, status) VALUES (?, ?, ?, ?, ?, ?, 'approved')");
        $stmtIns->execute([$productId, $reviewerName, $reviewerEmail, $rating, $title, $comment]);

        purge_cache();

        echo json_encode(['success' => true, 'message' => 'Thank you! Your review has been published.']);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save review: ' . $e->getMessage()]);
        exit;
    }
}

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$slug && !$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product slug or id is required']);
    exit;
}

$cache_key = "product_detail_" . ($slug ? "slug_" . md5($slug) : "id_" . $id);

$cached_product = get_cache($cache_key, 3600, $pdo);
if ($cached_product !== false) {
    echo json_encode($cached_product);
    if (isset($cached_product['data']['id'])) {
        try {
            $pdo->exec("UPDATE products SET view_count = view_count + 1 WHERE id = " . (int)$cached_product['data']['id']);
        } catch (Exception $e) {}
    }
    exit;
}

try {
    $sql = "SELECT p.*, 
            (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM product_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.product_id = p.id) as category_name 
            FROM products p 
            WHERE p.status = 'published' AND p.deleted_at IS NULL";
            
    $params = [];
    if ($slug) {
        if (is_numeric($slug)) {
            $sql .= " AND (p.slug = ? OR p.id = ?)";
            $params[] = $slug;
            $params[] = (int)$slug;
        } else {
            $sql .= " AND p.slug = ?";
            $params[] = $slug;
        }
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
        $product['view_count']++;
        
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

        // Fetch approved Customer Reviews (Only real DB entries)
        $revStmt = $pdo->prepare("SELECT id, reviewer_name, rating, title, comment, created_at FROM product_reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC");
        $revStmt->execute([$product['id']]);
        $reviewsList = $revStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reviewsList as &$rev) {
            $rev['verified'] = true;
        }
        unset($rev);

        $totalReviews = count($reviewsList);
        $totalRatingSum = array_sum(array_column($reviewsList, 'rating'));
        $avgRating = $totalReviews > 0 ? round($totalRatingSum / $totalReviews, 1) : 0;

        $product['reviews'] = $reviewsList;
        $product['average_rating'] = $avgRating;
        $product['total_reviews'] = $totalReviews;

        // Fetch Related Products (same category or latest published products)
        $relSql = "SELECT p.*, 
                   (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM product_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.product_id = p.id) as category_name 
                   FROM products p 
                   WHERE p.status = 'published' AND p.deleted_at IS NULL AND p.id != ?";
        
        $relParams = [$product['id']];
        if (!empty($product['category_id'])) {
            $relSql .= " AND p.category_id = ?";
            $relParams[] = $product['category_id'];
        }
        $relSql .= " ORDER BY p.id DESC LIMIT 4";

        $relStmt = $pdo->prepare($relSql);
        $relStmt->execute($relParams);
        $relatedProducts = $relStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($relatedProducts) < 4) {
            $existingIds = array_merge([$product['id']], array_column($relatedProducts, 'id'));
            $inPlaceholders = implode(',', array_fill(0, count($existingIds), '?'));
            
            $fallbackStmt = $pdo->prepare("SELECT p.*, (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM product_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.product_id = p.id) as category_name FROM products p WHERE p.status = 'published' AND p.deleted_at IS NULL AND p.id NOT IN ($inPlaceholders) ORDER BY p.id DESC LIMIT " . (4 - count($relatedProducts)));
            $fallbackStmt->execute($existingIds);
            $moreProducts = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
            $relatedProducts = array_merge($relatedProducts, $moreProducts);
        }

        // Batch-fetch images and discounts for related products (2 queries instead of 2N)
        $relProductIds = array_column($relatedProducts, 'id');
        $relImageMap = get_bulk_product_images($pdo, $relProductIds, 1);
        $relDiscountMap = get_bulk_product_discounts($pdo, $relatedProducts);

        foreach ($relatedProducts as &$relProd) {
            $relProd['images'] = $relImageMap[$relProd['id']] ?? [];

            $relDiscount = $relDiscountMap[$relProd['id']] ?? false;
            if ($relDiscount) {
                $relProd['original_price'] = (float)$relProd['price'];
                $relProd['discount_info'] = $relDiscount;
                $relProd['sale_price'] = $relDiscount['discounted_price'];
                $relProd['has_discount'] = true;
            } else {
                $relProd['original_price'] = (float)$relProd['price'];
                $relProd['has_discount'] = false;
            }
        }
        unset($relProd);

        $product['related_products'] = $relatedProducts;

        $response_data = [
            'success' => true,
            'data' => $product
        ];
        set_cache($cache_key, $response_data, $pdo);
        echo json_encode($response_data);
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
