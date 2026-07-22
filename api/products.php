<?php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$category_slug = isset($_GET['category_slug']) ? trim($_GET['category_slug']) : null;
$featured = isset($_GET['featured']) ? (bool)$_GET['featured'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;

$page = $page > 0 ? $page : 1;
$limit = $limit > 0 ? $limit : 12;
$offset = ($page - 1) * $limit;

try {
    $category_info = null;
    if ($category_slug) {
        $stmtCat = $pdo->prepare("SELECT * FROM categories WHERE slug = ? AND deleted_at IS NULL");
        $stmtCat->execute([$category_slug]);
        $category_info = $stmtCat->fetch(PDO::FETCH_ASSOC);
        if ($category_info) {
            $category_id = $category_info['id'];
            
            // Fetch related categories (children, or siblings if no children)
            $stmtChildren = $pdo->prepare("SELECT id, name, slug FROM categories WHERE parent_id = ? AND deleted_at IS NULL ORDER BY name ASC");
            $stmtChildren->execute([$category_id]);
            $children = $stmtChildren->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($children) > 0) {
                $category_info['related_categories'] = $children;
            } else {
                $parent_id = $category_info['parent_id'] ? $category_info['parent_id'] : 0;
                $stmtSiblings = $pdo->prepare("SELECT id, name, slug FROM categories WHERE parent_id = ? AND deleted_at IS NULL ORDER BY name ASC");
                $stmtSiblings->execute([$parent_id]);
                $category_info['related_categories'] = $stmtSiblings->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

    $category_ids_in = [];
    if ($category_id) {
        $category_ids_in = get_all_child_category_ids($pdo, $category_id);
    }

    $sql = "SELECT p.*, 
            (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM product_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.product_id = p.id) as category_name 
            FROM products p 
            WHERE p.status = 'published' AND p.deleted_at IS NULL";
    
    $params = [];
    
    if (!empty($category_ids_in)) {
        $in_placeholders = str_repeat('?,', count($category_ids_in) - 1) . '?';
        $sql .= " AND (p.category_id IN ($in_placeholders) OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($in_placeholders)))";
        $params = array_merge($params, $category_ids_in, $category_ids_in);
    }
    
    if ($featured !== null) {
        $sql .= " AND p.is_featured = ?";
        $params[] = $featured ? 1 : 0;
    }

    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // First count total for pagination
    $count_sql = "SELECT COUNT(*) FROM products p WHERE p.status = 'published' AND p.deleted_at IS NULL";
    $count_params = [];
    if (!empty($category_ids_in)) {
        $in_placeholders = str_repeat('?,', count($category_ids_in) - 1) . '?';
        $count_sql .= " AND (p.category_id IN ($in_placeholders) OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($in_placeholders)))";
        $count_params = array_merge($count_params, $category_ids_in, $category_ids_in);
    }
    if ($featured !== null) {
        $count_sql .= " AND p.is_featured = ?";
        $count_params[] = $featured ? 1 : 0;
    }
    if ($search) {
        $count_sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
        $count_params[] = $searchTerm;
        $count_params[] = $searchTerm;
        $count_params[] = $searchTerm;
    }
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit);
    
    if ($sort === 'price_asc') {
        $sql .= " ORDER BY p.price ASC";
    } elseif ($sort === 'price_desc') {
        $sql .= " ORDER BY p.price DESC";
    } else {
        $sql .= " ORDER BY p.created_at DESC";
    }
    
    $sql .= " LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Fetch images and calculate discounts for each product
    foreach ($products as &$product) {
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
    }
    
    // Log activity
    log_activity($pdo, 'api_fetch_products', 'product', null, "Fetched products list (page $page)", null, 'guest');
    
    echo json_encode([
        'success' => true,
        'category' => $category_info,
        'data' => $products,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total_items,
            'limit' => $limit
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch products: ' . $e->getMessage()
    ]);
}
