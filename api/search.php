<?php
// admin/api/search.php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

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
    $cleanQuery = trim($query);
    // Remove punctuation & spaces for SKU matching (e.g. "k 506", "k-506" -> "k506")
    $alphanumericQuery = preg_replace('/[^a-zA-Z0-9]/', '', $cleanQuery);

    // Split query into keywords/tokens (length >= 2) for multi-term matching
    $tokens = array_values(array_filter(preg_split('/\s+/', $cleanQuery), fn($t) => mb_strlen($t) >= 2));
    if (empty($tokens)) {
        $tokens = [$cleanQuery];
    }

    // Base condition for active products
    $whereBase = "p.status = 'published' AND p.deleted_at IS NULL";

    // 1. Direct SKU match conditions (exact SKU, SKU contains query, or normalized SKU match)
    $skuCondition = "(p.sku LIKE ? OR REPLACE(REPLACE(p.sku, '-', ''), ' ', '') LIKE ?)";
    $skuParam1 = "%{$cleanQuery}%";
    $skuParam2 = "%{$alphanumericQuery}%";

    // 2. Token conditions: Each keyword should match somewhere in (name, sku, description, or category name)
    $tokenConditions = [];
    $tokenParams = [];
    foreach ($tokens as $token) {
        $tokenConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
        $tWild = "%{$token}%";
        $tokenParams[] = $tWild;
        $tokenParams[] = $tWild;
        $tokenParams[] = $tWild;
        $tokenParams[] = $tWild;
    }
    $allTokensSql = implode(' AND ', $tokenConditions);

    // Matches if SKU matches directly OR all tokens match
    $searchWhere = "({$skuCondition} OR ({$allTokensSql}))";
    $allParams = array_merge([$skuParam1, $skuParam2], $tokenParams);

    // Count total search results
    $countSql = "SELECT COUNT(DISTINCT p.id) 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE {$whereBase} AND {$searchWhere}";
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($allParams);
    $total_items = (int)$countStmt->fetchColumn();
    $total_pages = $limit > 0 ? (int)ceil($total_items / $limit) : 0;

    // Log the search (safe try/catch so missing search_logs table never breaks search)
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $logStmt = $pdo->prepare("INSERT INTO search_logs (search_term, results_count, ip_address) VALUES (?, ?, ?)");
        $logStmt->execute([$cleanQuery, $total_items, $ip]);
    } catch (Exception $e) {}

    // Fetch search results with intelligent relevance ranking:
    // 1. Exact SKU match (e.g. searching 'K506' puts K506 at #1)
    // 2. Normalized SKU match (e.g. searching 'k 506' matches K506)
    // 3. SKU starts with query
    // 4. Name starts with query
    // 5. In-stock products prioritized over out-of-stock
    $dataSql = "SELECT p.*, c.name as category_name,
                CASE 
                    WHEN LOWER(p.sku) = LOWER(?) THEN 1
                    WHEN LOWER(REPLACE(REPLACE(p.sku, '-', ''), ' ', '')) = LOWER(?) THEN 2
                    WHEN LOWER(p.sku) LIKE LOWER(?) THEN 3
                    WHEN LOWER(p.name) LIKE LOWER(?) THEN 4
                    ELSE 5
                END as search_relevance
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE {$whereBase} AND {$searchWhere}
                ORDER BY search_relevance ASC, (p.stock_qty > 0) DESC, p.id DESC 
                LIMIT {$limit} OFFSET {$offset}";

    $dataParams = array_merge(
        [$cleanQuery, $alphanumericQuery, "{$cleanQuery}%", "{$cleanQuery}%"],
        $allParams
    );

    $stmt = $pdo->prepare($dataSql);
    $stmt->execute($dataParams);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch images for products
    foreach ($products as &$product) {
        $imgStmt = $pdo->prepare("SELECT image_path, thumb_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
        $imgStmt->execute([$product['id']]);
        $product['images'] = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($product);

    // Batch-resolve live POS inventory for outfit products if applicable
    $outfitSkus = [];
    foreach ($products as $p) {
        if (function_exists('is_outfit_category_or_product') && is_outfit_category_or_product($p, $pdo)) {
            $outfitSkus[] = $p['sku'];
        }
    }
    if (!empty($outfitSkus) && function_exists('get_pos_stock_for_skus')) {
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
