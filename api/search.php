<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/db.php';
require_once '../includes/functions.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;

if (empty($query)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Search query (q) is required']);
    exit;
}

$page = $page > 0 ? $page : 1;
$limit = $limit > 0 ? $limit : 12;
$offset = ($page - 1) * $limit;

try {
    // Count total search results
    $count_sql = "SELECT COUNT(*) FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.status = 'published' AND p.deleted_at IS NULL AND p.stock_qty > 0 
                  AND (p.name LIKE :q1 OR p.description LIKE :q2 OR c.name LIKE :q3)";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->bindValue(':q1', "%$query%");
    $count_stmt->bindValue(':q2', "%$query%");
    $count_stmt->bindValue(':q3', "%$query%");
    $count_stmt->execute();
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    // Log the search
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $log_stmt = $pdo->prepare("INSERT INTO search_logs (search_term, results_count, ip_address) VALUES (?, ?, ?)");
    $log_stmt->execute([$query, $total_items, $ip]);

    // Fetch search results
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'published' AND p.deleted_at IS NULL AND p.stock_qty > 0 
            AND (p.name LIKE :q1 OR p.description LIKE :q2 OR c.name LIKE :q3)
            ORDER BY p.created_at DESC 
            LIMIT :limit OFFSET :offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':q1', "%$query%");
    $stmt->bindValue(':q2', "%$query%");
    $stmt->bindValue(':q3', "%$query%");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    // Fetch images for products
    foreach ($products as &$product) {
        $imgStmt = $pdo->prepare("SELECT image_path, thumb_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
        $imgStmt->execute([$product['id']]);
        $product['images'] = $imgStmt->fetchAll();
    }
    unset($product);

    // Batch-resolve live POS inventory for outfit products
    $outfitSkus = [];
    foreach ($products as $p) {
        if (is_outfit_category_or_product($p, $pdo)) {
            $outfitSkus[] = $p['sku'];
        }
    }
    if (!empty($outfitSkus)) {
        $posStockMap = get_pos_stock_for_skus($outfitSkus);
        foreach ($products as &$product) {
            if (is_outfit_category_or_product($product, $pdo)) {
                $skuKey = strtolower(trim($product['sku']));
                if (isset($posStockMap[$skuKey])) {
                    $posQty = max(0, (int)$posStockMap[$skuKey]);
                    $product['stock_qty'] = $posQty;
                    $product['stock_quantity'] = $posQty;
                    $product['is_in_stock'] = ($posQty > 0);
                    $product['is_out_of_stock'] = ($posQty <= 0);
                }
            }
        }
        unset($product);
    }
    
    echo json_encode([
        'success' => true,
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
        'message' => 'Search failed: ' . $e->getMessage()
    ]);
}
?>
