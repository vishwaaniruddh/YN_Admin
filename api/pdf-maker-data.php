<?php
// admin/api/pdf-maker-data.php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function format_live_image_url($path) {
    if (empty($path)) return '';
    $clean = trim((string)$path);
    // Strip localhost
    $clean = preg_replace('#^https?://localhost(:[0-9]+)?(/yn)?/admin/?#i', '', $clean);
    $clean = preg_replace('#^https?://localhost(:[0-9]+)?/?#i', '', $clean);
    
    if (str_starts_with($clean, 'https://yosshitaneha.com')) {
        return $clean;
    }
    if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
        return $clean;
    }
    
    $clean = ltrim($clean, '/');
    if (str_starts_with($clean, 'admin/')) {
        return 'https://yosshitaneha.com/' . $clean;
    }
    return 'https://yosshitaneha.com/admin/' . $clean;
}

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

try {
    if ($action === 'get_meta') {
        // Fetch all non-deleted categories
        $stmt = $pdo->query("
            SELECT id, name, slug, parent_id,
                (SELECT COUNT(*) FROM products p WHERE (p.category_id = c.id OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = c.id)) AND p.deleted_at IS NULL) as product_count
            FROM categories c
            WHERE c.deleted_at IS NULL
            ORDER BY c.parent_id ASC, c.name ASC
        ");
        $allCats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch price bounds
        $priceStmt = $pdo->query("SELECT MIN(price) as min_price, MAX(price) as max_price, COUNT(*) as total_products FROM products WHERE deleted_at IS NULL");
        $priceStats = $priceStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'categories' => $allCats,
            'stats' => [
                'min_price' => (float)($priceStats['min_price'] ?? 0),
                'max_price' => (float)($priceStats['max_price'] ?? 50000),
                'total_products' => (int)($priceStats['total_products'] ?? 0)
            ]
        ]);
        exit;
    }

    if ($action === 'search_products') {
        $department = trim($_GET['department'] ?? 'all');
        $categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $minPrice = (isset($_GET['min_price']) && $_GET['min_price'] !== '') ? (float)$_GET['min_price'] : null;
        $maxPrice = (isset($_GET['max_price']) && $_GET['max_price'] !== '') ? (float)$_GET['max_price'] : null;
        $search = trim($_GET['search'] ?? '');
        $sort = trim($_GET['sort'] ?? 'newest');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(6, min(100, (int)($_GET['limit'] ?? 24)));
        $offset = ($page - 1) * $limit;

        $categoryIdsIn = [];

        // 1. Department resolution (Outfit vs Jewellery)
        if ($department === 'jewellery') {
            // Find root Jewellery (usually slug 'jewellery' or id 1)
            $jStmt = $pdo->prepare("SELECT id FROM categories WHERE (slug = 'jewellery' OR LOWER(name) LIKE '%jewellery%') AND parent_id IS NULL AND deleted_at IS NULL LIMIT 1");
            $jStmt->execute();
            $jId = $jStmt->fetchColumn();
            if ($jId) {
                $categoryIdsIn = get_all_child_category_ids($pdo, (int)$jId);
            }
        } elseif ($department === 'outfit') {
            // Find root Outfit (usually slug 'outfit' or id 26)
            $oStmt = $pdo->prepare("SELECT id FROM categories WHERE (slug = 'outfit' OR LOWER(name) LIKE '%outfit%') AND parent_id IS NULL AND deleted_at IS NULL LIMIT 1");
            $oStmt->execute();
            $oId = $oStmt->fetchColumn();
            if ($oId) {
                $categoryIdsIn = get_all_child_category_ids($pdo, (int)$oId);
            }
        }

        // If specific category is selected, narrow down to that category and its descendants
        if ($categoryId && $categoryId > 0) {
            $selectedChildIds = get_all_child_category_ids($pdo, $categoryId);
            if (!empty($categoryIdsIn)) {
                $categoryIdsIn = array_values(array_intersect($categoryIdsIn, $selectedChildIds));
                if (empty($categoryIdsIn)) {
                    $categoryIdsIn = [-999]; // no overlap
                }
            } else {
                $categoryIdsIn = $selectedChildIds;
            }
        }

        // Base where clause
        $whereClauses = ["p.deleted_at IS NULL"];
        $params = [];

        if (!empty($categoryIdsIn)) {
            $inPlaceholders = implode(',', array_fill(0, count($categoryIdsIn), '?'));
            $whereClauses[] = "(p.category_id IN ($inPlaceholders) OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($inPlaceholders)))";
            $params = array_merge($params, $categoryIdsIn, $categoryIdsIn);
        }

        if ($minPrice !== null && $minPrice >= 0) {
            $whereClauses[] = "COALESCE(NULLIF(p.sale_price, 0), p.price) >= ?";
            $params[] = $minPrice;
        }

        if ($maxPrice !== null && $maxPrice > 0) {
            $whereClauses[] = "COALESCE(NULLIF(p.sale_price, 0), p.price) <= ?";
            $params[] = $maxPrice;
        }

        if (!empty($search)) {
            $whereClauses[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $sParam = "%$search%";
            $params[] = $sParam;
            $params[] = $sParam;
            $params[] = $sParam;
        }

        $whereSql = "WHERE " . implode(" AND ", $whereClauses);

        // Sorting
        $orderBy = "p.id DESC";
        switch ($sort) {
            case 'price_asc':
                $orderBy = "COALESCE(NULLIF(p.sale_price, 0), p.price) ASC, p.id DESC";
                break;
            case 'price_desc':
                $orderBy = "COALESCE(NULLIF(p.sale_price, 0), p.price) DESC, p.id DESC";
                break;
            case 'sku_asc':
                $orderBy = "p.sku ASC";
                break;
            case 'name_asc':
                $orderBy = "p.name ASC";
                break;
            case 'newest':
            default:
                $orderBy = "p.id DESC";
                break;
        }

        // Count Query
        $countSql = "SELECT COUNT(*) FROM products p $whereSql";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        // Data Query
        $dataSql = "
            SELECT 
                p.id, 
                p.name, 
                p.sku, 
                p.price, 
                p.sale_price, 
                p.stock_qty, 
                p.main_image, 
                p.status,
                c.name as primary_category_name,
                (SELECT COUNT(*) FROM product_images pi WHERE pi.product_id = p.id) as gallery_count
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            $whereSql
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ";

        $dataStmt = $pdo->prepare($dataSql);
        $execParams = $params;
        $execParams[] = $limit;
        $execParams[] = $offset;
        $dataStmt->execute($execParams);
        $products = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        // Process images & formatting
        foreach ($products as &$prod) {
            $prod['price_formatted'] = '₹' . number_format($prod['price'], 0);
            $prod['sale_price_formatted'] = $prod['sale_price'] ? '₹' . number_format($prod['sale_price'], 0) : null;
            $prod['effective_price'] = (float)($prod['sale_price'] > 0 ? $prod['sale_price'] : $prod['price']);
            $prod['effective_price_formatted'] = '₹' . number_format($prod['effective_price'], 0);
            
            // Format image url/path using live production media server
            $prod['display_image'] = format_live_image_url($prod['main_image']);
        }
        unset($prod);

        echo json_encode([
            'success' => true,
            'products' => $products,
            'pagination' => [
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalCount / $limit)
            ]
        ]);
        exit;
    }

    if ($action === 'get_product_images' || $action === 'get_selected_details') {
        $rawIds = $_POST['product_ids'] ?? $_GET['product_ids'] ?? '';
        if (is_array($rawIds)) {
            $ids = array_filter(array_map('intval', $rawIds));
        } else {
            $ids = array_filter(array_map('intval', explode(',', (string)$rawIds)));
        }

        if (empty($ids)) {
            echo json_encode(['success' => true, 'products' => []]);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Fetch products
        $stmt = $pdo->prepare("
            SELECT 
                p.id, 
                p.name, 
                p.slug,
                p.sku, 
                p.price, 
                p.sale_price, 
                p.stock_qty, 
                p.description,
                p.short_description,
                p.main_image, 
                c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id IN ($placeholders) AND p.deleted_at IS NULL
        ");
        $stmt->execute($ids);
        $productsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch gallery images
        $imgStmt = $pdo->prepare("
            SELECT id, product_id, image_path, thumb_path, sort_order 
            FROM product_images 
            WHERE product_id IN ($placeholders) 
            ORDER BY sort_order ASC, id ASC
        ");
        $imgStmt->execute($ids);
        $galleryImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

        // Group gallery by product_id
        $galleryMap = [];
        foreach ($galleryImages as $img) {
            $pId = (int)$img['product_id'];
            if (!isset($galleryMap[$pId])) {
                $galleryMap[$pId] = [];
            }
            $galleryMap[$pId][] = $img;
        }

        // Build structured product items
        $resultProducts = [];
        $lookup = [];
        foreach ($productsRaw as $p) {
            $pId = (int)$p['id'];
            
            // Build all available images list
            $imagesList = [];
            $seenUrls = [];

            // 1. Main image
            if (!empty($p['main_image'])) {
                $liveMain = format_live_image_url($p['main_image']);
                if ($liveMain) {
                    $imagesList[] = [
                        'id' => 'main_' . $pId,
                        'path' => $liveMain,
                        'is_main' => true,
                        'thumb' => $liveMain
                    ];
                    $seenUrls[$liveMain] = true;
                }
            }

            // 2. Gallery images
            if (isset($galleryMap[$pId])) {
                foreach ($galleryMap[$pId] as $gImg) {
                    $liveG = format_live_image_url($gImg['image_path']);
                    $liveThumb = !empty($gImg['thumb_path']) ? format_live_image_url($gImg['thumb_path']) : $liveG;
                    if ($liveG && !isset($seenUrls[$liveG])) {
                        $imagesList[] = [
                            'id' => 'gal_' . $gImg['id'],
                            'path' => $liveG,
                            'is_main' => false,
                            'thumb' => $liveThumb
                        ];
                        $seenUrls[$liveG] = true;
                    }
                }
            }

            // Fallback placeholder if no images
            if (empty($imagesList)) {
                $fallbackUrl = 'https://yosshitaneha.com/admin/assets/images/placeholder.png';
                $imagesList[] = [
                    'id' => 'placeholder_' . $pId,
                    'path' => $fallbackUrl,
                    'is_main' => true,
                    'thumb' => $fallbackUrl
                ];
            }

            $p['all_images'] = $imagesList;
            // Default selected images: first image (main)
            $p['selected_images'] = [$imagesList[0]['path']];
            $p['price_formatted'] = '₹' . number_format($p['price'], 0);
            $p['sale_price_formatted'] = $p['sale_price'] ? '₹' . number_format($p['sale_price'], 0) : null;
            $p['effective_price'] = (float)($p['sale_price'] > 0 ? $p['sale_price'] : $p['price']);
            $p['effective_price_formatted'] = '₹' . number_format($p['effective_price'], 0);
            $prodSlug = !empty($p['slug']) ? $p['slug'] : $p['sku'];
            $p['product_url'] = 'https://yosshitaneha.com/product/' . urlencode($prodSlug);

            $lookup[$pId] = $p;
        }

        foreach ($ids as $id) {
            if (isset($lookup[$id])) {
                $resultProducts[] = $lookup[$id];
            }
        }

        echo json_encode([
            'success' => true,
            'products' => $resultProducts
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
