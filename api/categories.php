<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/db.php';

require_once '../includes/functions.php'; // For log_activity

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$page = $page > 0 ? $page : 1;
$limit = $limit > 0 ? $limit : 20;
$offset = ($page - 1) * $limit;

$is_tree = isset($_GET['tree']) && $_GET['tree'] === 'true';

try {
    if ($is_tree) {
        $stmt = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC");
        $categories = build_nested_category_tree($stmt->fetchAll());
        
        echo json_encode([
            'success' => true,
            'data' => $categories
        ]);
        exit;
    }

    // Count total categories
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL");
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    // Fetch categories
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // Log activity
    log_activity($pdo, 'api_fetch_categories', 'category', null, "Fetched categories list (page $page)", null, 'guest');
    
    echo json_encode([
        'success' => true,
        'data' => $categories,
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
        'message' => 'Failed to fetch categories: ' . $e->getMessage()
    ]);
}
?>
