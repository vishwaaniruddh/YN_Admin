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
        // Fetch all non-deleted product categories formatted via get_category_tree (matching products.php)
        $stmt = $pdo->query("
            SELECT id, name, slug, parent_id,
                (SELECT COUNT(*) FROM products p WHERE (p.category_id = c.id OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = c.id)) AND p.deleted_at IS NULL) as product_count
            FROM categories c
            WHERE c.deleted_at IS NULL
            ORDER BY c.name ASC
        ");
        $allCatsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $allCats = get_category_tree($allCatsRaw);

        // Fetch collection categories from collections table
        $collCatsStmt = $pdo->query("
            SELECT category, category as name, COUNT(*) as count 
            FROM collections 
            WHERE category IS NOT NULL AND category != '' 
            GROUP BY category 
            ORDER BY category ASC
        ");
        $collectionCats = $collCatsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch total collections count
        $totalCollections = (int)$pdo->query("SELECT COUNT(*) FROM collections")->fetchColumn();

        // Fetch price bounds
        $priceStmt = $pdo->query("SELECT MIN(price) as min_price, MAX(price) as max_price, COUNT(*) as total_products FROM products WHERE deleted_at IS NULL");
        $priceStats = $priceStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'categories' => $allCats,
            'collection_categories' => $collectionCats,
            'total_collections' => $totalCollections,
            'stats' => [
                'min_price' => (float)($priceStats['min_price'] ?? 0),
                'max_price' => (float)($priceStats['max_price'] ?? 50000),
                'total_products' => (int)($priceStats['total_products'] ?? 0),
                'total_collections' => $totalCollections
            ]
        ]);
        exit;
    }

    if ($action === 'search_products') {
        $department = trim($_GET['department'] ?? 'all');
        $categoryId = !empty($_GET['category_id']) ? $_GET['category_id'] : null;
        $search = trim($_GET['search'] ?? '');
        $sort = trim($_GET['sort'] ?? 'newest');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(6, min(100, (int)($_GET['limit'] ?? 24)));
        $offset = ($page - 1) * $limit;

        // ---------------------------------------------------------------------
        // CASE 1: SEARCH COLLECTIONS (Lookbook from collections.php)
        // ---------------------------------------------------------------------
        if ($department === 'collections') {
            $whereClauses = ["1=1"];
            $params = [];

            // Category filter for collections (either category name or ID)
            $catFilter = trim($_GET['collection_category'] ?? ($categoryId ?? ''));
            if (!empty($catFilter) && $catFilter !== 'all') {
                $whereClauses[] = "c.category = ?";
                $params[] = $catFilter;
            }

            if (!empty($search)) {
                $whereClauses[] = "(c.title LIKE ? OR c.sku LIKE ? OR c.subtitle LIKE ? OR c.description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            $whereSql = "WHERE " . implode(" AND ", $whereClauses);

            // Sorting
            $orderBy = "c.sort_order ASC, c.id DESC";
            if ($sort === 'sku_asc') {
                $orderBy = "c.sku ASC, c.id DESC";
            } elseif ($sort === 'name_asc') {
                $orderBy = "c.title ASC, c.id DESC";
            }

            // Count Query
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM collections c $whereSql");
            $countStmt->execute($params);
            $totalCount = (int)$countStmt->fetchColumn();

            // Data Query
            $dataSql = "
                SELECT 
                    c.id, 
                    c.title as name, 
                    c.sku, 
                    c.subtitle,
                    c.category as primary_category_name,
                    c.cover_image as main_image,
                    c.slug,
                    c.description,
                    c.status,
                    (SELECT COUNT(*) FROM collection_images ci WHERE ci.collection_id = c.id) as gallery_count
                FROM collections c
                $whereSql
                ORDER BY $orderBy
                LIMIT ? OFFSET ?
            ";

            $dataStmt = $pdo->prepare($dataSql);
            $execParams = $params;
            $execParams[] = $limit;
            $execParams[] = $offset;
            $dataStmt->execute($execParams);
            $collections = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($collections as &$col) {
                $col['item_key'] = 'c_' . $col['id'];
                $col['item_type'] = 'collection';
                $col['sku'] = !empty($col['sku']) ? $col['sku'] : ('COL-' . $col['id']);
                $col['price'] = 0;
                $col['sale_price'] = null;
                $col['effective_price'] = 0;
                $col['price_formatted'] = 'Couture';
                $col['sale_price_formatted'] = null;
                $col['effective_price_formatted'] = 'Lookbook';
                $col['display_image'] = format_live_image_url($col['main_image']);
            }
            unset($col);

            echo json_encode([
                'success' => true,
                'products' => $collections,
                'pagination' => [
                    'total' => $totalCount,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($totalCount / $limit)
                ]
            ]);
            exit;
        }

        // ---------------------------------------------------------------------
        // CASE 2: SEARCH PRODUCTS (Catalog Products: Outfits & Jewellery)
        // ---------------------------------------------------------------------
        $minPrice = (isset($_GET['min_price']) && $_GET['min_price'] !== '') ? (float)$_GET['min_price'] : null;
        $maxPrice = (isset($_GET['max_price']) && $_GET['max_price'] !== '') ? (float)$_GET['max_price'] : null;
        $numericCatId = (is_numeric($categoryId) && (int)$categoryId > 0) ? (int)$categoryId : null;

        $categoryIdsIn = [];

        // Department resolution (Outfit vs Jewellery)
        if ($department === 'jewellery') {
            $jStmt = $pdo->prepare("SELECT id FROM categories WHERE (slug = 'jewellery' OR LOWER(name) LIKE '%jewellery%') AND parent_id IS NULL AND deleted_at IS NULL LIMIT 1");
            $jStmt->execute();
            $jId = $jStmt->fetchColumn();
            if ($jId) {
                $categoryIdsIn = get_all_child_category_ids($pdo, (int)$jId);
            }
        } elseif ($department === 'outfit') {
            $oStmt = $pdo->prepare("SELECT id FROM categories WHERE (slug = 'outfit' OR LOWER(name) LIKE '%outfit%') AND parent_id IS NULL AND deleted_at IS NULL LIMIT 1");
            $oStmt->execute();
            $oId = $oStmt->fetchColumn();
            if ($oId) {
                $categoryIdsIn = get_all_child_category_ids($pdo, (int)$oId);
            }
        }

        // Specific category filter
        if ($numericCatId && $numericCatId > 0) {
            $selectedChildIds = get_all_child_category_ids($pdo, $numericCatId);
            if (!empty($categoryIdsIn)) {
                $categoryIdsIn = array_values(array_intersect($categoryIdsIn, $selectedChildIds));
                if (empty($categoryIdsIn)) {
                    $categoryIdsIn = [-999];
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

        foreach ($products as &$prod) {
            $prod['item_key'] = 'p_' . $prod['id'];
            $prod['item_type'] = 'product';
            $prod['price_formatted'] = '₹' . number_format($prod['price'], 0);
            $prod['sale_price_formatted'] = $prod['sale_price'] ? '₹' . number_format($prod['sale_price'], 0) : null;
            $prod['effective_price'] = (float)($prod['sale_price'] > 0 ? $prod['sale_price'] : $prod['price']);
            $prod['effective_price_formatted'] = '₹' . number_format($prod['effective_price'], 0);
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

    // -------------------------------------------------------------------------
    // ACTION: GET DETAILS & ALL ANGLES FOR SELECTED ITEMS (PRODUCTS & COLLECTIONS)
    // -------------------------------------------------------------------------
    if ($action === 'get_product_images' || $action === 'get_selected_details') {
        $rawIds = $_POST['product_ids'] ?? $_GET['product_ids'] ?? '';
        if (is_array($rawIds)) {
            $rawList = $rawIds;
        } else {
            $rawList = explode(',', (string)$rawIds);
        }

        $productIds = [];
        $collectionIds = [];
        $orderList = [];

        foreach ($rawList as $raw) {
            $raw = trim((string)$raw);
            if ($raw === '') continue;

            if (str_starts_with($raw, 'c_') || str_starts_with($raw, 'col_')) {
                $cid = (int)preg_replace('/[^0-9]/', '', $raw);
                if ($cid > 0) {
                    $collectionIds[] = $cid;
                    $orderList[] = 'c_' . $cid;
                }
            } elseif (str_starts_with($raw, 'p_')) {
                $pid = (int)substr($raw, 2);
                if ($pid > 0) {
                    $productIds[] = $pid;
                    $orderList[] = 'p_' . $pid;
                }
            } else {
                // Numeric identifier: default to product
                $pid = (int)$raw;
                if ($pid > 0) {
                    $productIds[] = $pid;
                    $orderList[] = 'p_' . $pid;
                }
            }
        }

        $lookup = [];

        // 1. Fetch Selected Products
        if (!empty($productIds)) {
            $uniquePids = array_unique($productIds);
            $pPlaceholders = implode(',', array_fill(0, count($uniquePids), '?'));

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
                WHERE p.id IN ($pPlaceholders) AND p.deleted_at IS NULL
            ");
            $stmt->execute($uniquePids);
            $productsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $imgStmt = $pdo->prepare("
                SELECT id, product_id, image_path, thumb_path, sort_order 
                FROM product_images 
                WHERE product_id IN ($pPlaceholders) 
                ORDER BY sort_order ASC, id ASC
            ");
            $imgStmt->execute($uniquePids);
            $galleryImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

            $galleryMap = [];
            foreach ($galleryImages as $img) {
                $pId = (int)$img['product_id'];
                if (!isset($galleryMap[$pId])) $galleryMap[$pId] = [];
                $galleryMap[$pId][] = $img;
            }

            foreach ($productsRaw as $p) {
                $pId = (int)$p['id'];
                $itemKey = 'p_' . $pId;
                $imagesList = [];
                $seenUrls = [];

                if (!empty($p['main_image'])) {
                    $liveMain = format_live_image_url($p['main_image']);
                    if ($liveMain) {
                        $imagesList[] = [
                            'id' => 'main_' . $pId,
                            'path' => $liveMain,
                            'is_main' => true,
                            'thumb' => $liveMain,
                            'angle_label' => 'Angle 1 (Main Image)',
                            'label' => 'Angle 1 (Main Image)'
                        ];
                        $seenUrls[$liveMain] = true;
                    }
                }

                if (isset($galleryMap[$pId])) {
                    foreach ($galleryMap[$pId] as $idx => $gImg) {
                        $liveG = format_live_image_url($gImg['image_path']);
                        $liveThumb = !empty($gImg['thumb_path']) ? format_live_image_url($gImg['thumb_path']) : $liveG;
                        if ($liveG && !isset($seenUrls[$liveG])) {
                            $angLabel = 'Angle ' . (count($imagesList) + 1);
                            $imagesList[] = [
                                'id' => 'gal_' . $gImg['id'],
                                'path' => $liveG,
                                'is_main' => false,
                                'thumb' => $liveThumb,
                                'angle_label' => $angLabel,
                                'label' => $angLabel
                            ];
                            $seenUrls[$liveG] = true;
                        }
                    }
                }

                if (empty($imagesList)) {
                    $fallbackUrl = 'https://yosshitaneha.com/admin/assets/images/placeholder.png';
                    $imagesList[] = [
                        'id' => 'placeholder_' . $pId,
                        'path' => $fallbackUrl,
                        'is_main' => true,
                        'thumb' => $fallbackUrl,
                        'angle_label' => 'Main Image',
                        'label' => 'Main Image'
                    ];
                }

                if (!empty($imagesList)) {
                    $imagesList[0]['is_main'] = true;
                }

                $p['item_key'] = $itemKey;
                $p['item_type'] = 'product';
                $p['all_images'] = $imagesList;
                $p['selected_images'] = !empty($imagesList) ? [$imagesList[0]['path']] : [];
                $p['price_formatted'] = '₹' . number_format($p['price'], 0);
                $p['sale_price_formatted'] = $p['sale_price'] ? '₹' . number_format($p['sale_price'], 0) : null;
                $p['effective_price'] = (float)($p['sale_price'] > 0 ? $p['sale_price'] : $p['price']);
                $p['effective_price_formatted'] = '₹' . number_format($p['effective_price'], 0);
                $prodSlug = !empty($p['slug']) ? $p['slug'] : $p['sku'];
                $p['product_url'] = 'https://yosshitaneha.com/product/' . urlencode($prodSlug);

                $lookup[$itemKey] = $p;
            }
        }

        // 2. Fetch Selected Lookbook Collections (collections.php)
        if (!empty($collectionIds)) {
            $uniqueCids = array_unique($collectionIds);
            $cPlaceholders = implode(',', array_fill(0, count($uniqueCids), '?'));

            $cStmt = $pdo->prepare("
                SELECT id, title, slug, sku, subtitle, category, description, cover_image, status 
                FROM collections 
                WHERE id IN ($cPlaceholders)
            ");
            $cStmt->execute($uniqueCids);
            $collectionsRaw = $cStmt->fetchAll(PDO::FETCH_ASSOC);

            $ciStmt = $pdo->prepare("
                SELECT id, collection_id, image_path, thumb_path, caption, angle_type, is_cover, sort_order 
                FROM collection_images 
                WHERE collection_id IN ($cPlaceholders) 
                ORDER BY is_cover DESC, sort_order ASC, id ASC
            ");
            $ciStmt->execute($uniqueCids);
            $collImages = $ciStmt->fetchAll(PDO::FETCH_ASSOC);

            $cImgMap = [];
            foreach ($collImages as $ci) {
                $cid = (int)$ci['collection_id'];
                if (!isset($cImgMap[$cid])) $cImgMap[$cid] = [];
                $cImgMap[$cid][] = $ci;
            }

            foreach ($collectionsRaw as $c) {
                $cid = (int)$c['id'];
                $itemKey = 'c_' . $cid;
                $imagesList = [];
                $seenUrls = [];

                // 1. Cover Image
                if (!empty($c['cover_image'])) {
                    $liveCover = format_live_image_url($c['cover_image']);
                    if ($liveCover) {
                        $imagesList[] = [
                            'id' => 'cover_' . $cid,
                            'path' => $liveCover,
                            'is_main' => true,
                            'thumb' => $liveCover,
                            'angle_label' => 'Angle 1 (Cover View)',
                            'label' => 'Angle 1 (Cover View)'
                        ];
                        $seenUrls[$liveCover] = true;
                    }
                }

                // 2. Additional Lookbook Photos
                if (isset($cImgMap[$cid])) {
                    foreach ($cImgMap[$cid] as $ci) {
                        $liveG = format_live_image_url($ci['image_path']);
                        $liveThumb = !empty($ci['thumb_path']) ? format_live_image_url($ci['thumb_path']) : $liveG;
                        if ($liveG && !isset($seenUrls[$liveG])) {
                            $angleLabel = !empty($ci['angle_type']) ? $ci['angle_type'] : (!empty($ci['caption']) ? $ci['caption'] : ('Angle ' . (count($imagesList) + 1)));
                            $imagesList[] = [
                                'id' => 'cimg_' . $ci['id'],
                                'path' => $liveG,
                                'is_main' => (empty($imagesList) || $ci['is_cover'] == 1),
                                'thumb' => $liveThumb,
                                'angle_label' => $angleLabel,
                                'label' => $angleLabel
                            ];
                            $seenUrls[$liveG] = true;
                        }
                    }
                }

                if (empty($imagesList)) {
                    $fallbackUrl = 'https://yosshitaneha.com/admin/assets/images/placeholder.png';
                    $imagesList[] = [
                        'id' => 'placeholder_c_' . $cid,
                        'path' => $fallbackUrl,
                        'is_main' => true,
                        'thumb' => $fallbackUrl,
                        'angle_label' => 'Main Image'
                    ];
                }

                if (!empty($imagesList)) {
                    $imagesList[0]['is_main'] = true;
                }

                $sku = !empty($c['sku']) ? $c['sku'] : ('COL-' . $cid);
                $collSlug = !empty($c['slug']) ? $c['slug'] : $sku;

                $item = [
                    'id' => $cid,
                    'item_key' => $itemKey,
                    'item_type' => 'collection',
                    'name' => $c['title'],
                    'slug' => $c['slug'],
                    'sku' => $sku,
                    'price' => 0,
                    'sale_price' => null,
                    'effective_price' => 0,
                    'category_name' => $c['category'] ?: 'Lookbook Collection',
                    'description' => $c['description'],
                    'main_image' => $c['cover_image'],
                    'all_images' => $imagesList,
                    'selected_images' => !empty($imagesList) ? [$imagesList[0]['path']] : [],
                    'price_formatted' => 'Price on Request',
                    'sale_price_formatted' => null,
                    'effective_price_formatted' => 'Couture',
                    'product_url' => 'https://yosshitaneha.com/collections/' . urlencode($collSlug)
                ];

                $lookup[$itemKey] = $item;
            }
        }

        // Return items in user-selected order
        $resultProducts = [];
        foreach ($orderList as $key) {
            if (isset($lookup[$key])) {
                $resultProducts[] = $lookup[$key];
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
