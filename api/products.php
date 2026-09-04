<?php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cache.php';

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$category_slug = isset($_GET['category_slug']) ? trim($_GET['category_slug']) : null;
$category_slugs_raw = isset($_GET['category_slugs']) ? trim($_GET['category_slugs']) : null;
$featured = isset($_GET['featured']) ? (bool)$_GET['featured'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'sku_desc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$include_out_of_stock = isset($_GET['include_out_of_stock']) ? (bool)$_GET['include_out_of_stock'] : null;
$in_stock = isset($_GET['in_stock']) ? (bool)$_GET['in_stock'] : null;

$valid_sorts = ['default', 'sku_desc', 'sku_asc', 'newest', 'created_desc', 'oldest', 'price_asc', 'price_low', 'price_desc', 'price_high', 'name_asc', 'name_desc'];
if (!in_array($sort, $valid_sorts, true)) {
    $sort = 'default';
}

$page = $page > 0 ? $page : 1;
$limit = $limit > 0 ? $limit : 12;
$offset = ($page - 1) * $limit;

$cache_key = "products_c" . ($category_id ?: '0') . "_cs" . ($category_slug ?: 'none') . "_css" . ($category_slugs_raw ?: 'none') . "_f" . ($featured ? '1' : '0') . "_sq" . ($search ?: 'none') . "_s" . $sort . "_p" . $page . "_l" . $limit . "_min" . ($min_price !== null ? $min_price : 'none') . "_max" . ($max_price !== null ? $max_price : 'none') . "_ioos" . ($include_out_of_stock ? '1' : '0') . "_is" . ($in_stock ? '1' : '0');

$cached_res = get_cache($cache_key, 3600, $pdo);
if ($cached_res !== false) {
    echo json_encode($cached_res);
    exit;
}

try {
    $category_info = null;
    $category_slugs_array = [];

    $slug_aliases = [
        'kalamkari-collection-ittar' => 'kalamkari-collection',
        'kalamkari' => 'kalamkari-collection',
        'ittar' => 'kalamkari-collection',
        'neclace-sets' => 'necklace-sets',
        'outfits' => 'outfit',
        'apparel' => 'outfit',
        'jewellery' => 'jewellery',
        'jewelry' => 'jewellery',
    ];

    $has_explicit_cat_filter = false;

    if ($category_slugs_raw) {
        $has_explicit_cat_filter = true;
        $raw_arr = array_filter(array_map('trim', explode(',', $category_slugs_raw)));
        foreach ($raw_arr as $s) {
            $category_slugs_array[] = $slug_aliases[$s] ?? $s;
        }
    } elseif ($category_slug) {
        $has_explicit_cat_filter = true;
        $norm_slug = $slug_aliases[$category_slug] ?? $category_slug;
        $category_slugs_array = [$norm_slug];
    }

    $category_ids_in = [];
    if (!empty($category_slugs_array)) {
        $slugs_placeholders = str_repeat('?,', count($category_slugs_array) - 1) . '?';
        $stmtCats = $pdo->prepare("SELECT * FROM categories WHERE slug IN ($slugs_placeholders) AND deleted_at IS NULL");
        $stmtCats->execute($category_slugs_array);
        $cats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($cats) > 0) {
            $category_info = $cats[0];
            $category_id = $category_info['id'];
            
            foreach ($cats as $cat) {
                $category_ids_in = array_merge($category_ids_in, get_all_child_category_ids($pdo, $cat['id']));
            }
            $category_ids_in = array_values(array_unique($category_ids_in));

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
    } elseif ($category_id) {
        $has_explicit_cat_filter = true;
        $category_ids_in = get_all_child_category_ids($pdo, $category_id);
    }

    // Determine if out-of-stock products should be included:
    // 1. Explicit parameter: include_out_of_stock=1
    // 2. Outfit category listing (outfit root ID 26 and all its subcategories like designer-blouses, kalamkari, etc.)
    // 3. BUT if in_stock=1 is explicitly requested (e.g. user toggled 'In Stock Only'), then force in-stock only.
    $outfit_category_ids = get_all_child_category_ids($pdo, 26);
    $is_outfit_category = false;
    if (!empty($category_ids_in)) {
        if (!empty(array_intersect($category_ids_in, $outfit_category_ids))) {
            $is_outfit_category = true;
        }
    }
    if (!$is_outfit_category && !empty($category_slugs_array)) {
        $outfit_slugs = ['outfit', 'outfits', 'apparel', 'designer-blouses', 'kalamkari-collection', 'kalamkari-collection-ittar', 'trail-gowns-infinity-gowns'];
        if (!empty(array_intersect($category_slugs_array, $outfit_slugs))) {
            $is_outfit_category = true;
        }
    }

    $show_out_of_stock = ($include_out_of_stock || $is_outfit_category) && !$in_stock;

    // Build WHERE clause (shared between count and data queries)
    if ($show_out_of_stock) {
        $where_sql = "WHERE p.status = 'published' AND p.deleted_at IS NULL";
    } else {
        $where_sql = "WHERE p.status = 'published' AND p.deleted_at IS NULL AND p.stock_qty > 0";
    }
    $params = [];
    
    if ($has_explicit_cat_filter) {
        if (!empty($category_ids_in)) {
            $in_placeholders = str_repeat('?,', count($category_ids_in) - 1) . '?';
            $where_sql .= " AND (p.category_id IN ($in_placeholders) OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($in_placeholders)))";
            $params = array_merge($params, $category_ids_in, $category_ids_in);
        } else {
            // Explicit category requested but no matching category found -> return 0 products
            $where_sql .= " AND 1 = 0";
        }
    }
    
    if ($featured !== null) {
        $where_sql .= " AND p.is_featured = ?";
        $params[] = $featured ? 1 : 0;
    }

    if ($search) {
        $where_sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($min_price !== null) {
        $where_sql .= " AND p.price >= ?";
        $params[] = $min_price;
    }
    
    if ($max_price !== null) {
        $where_sql .= " AND p.price <= ?";
        $params[] = $max_price;
    }

    // Determine if sort requires computed discounts (price/name sorts need all products)
    $needs_php_sort = in_array($sort, ['price_asc', 'price_low', 'price_desc', 'price_high', 'name_asc', 'name_desc']);

    if ($needs_php_sort) {
        // --- PRICE/NAME SORT PATH: fetch all products, compute discounts, sort in PHP, then paginate ---
        $sql = "SELECT p.*, 
                (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM product_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.product_id = p.id) as category_name 
                FROM products p $where_sql ORDER BY p.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Batch-fetch images and discounts (2 queries instead of 2N)
        $productIds = array_column($products, 'id');
        $imageMap = get_bulk_product_images($pdo, $productIds);
        $discountMap = get_bulk_product_discounts($pdo, $products);

        foreach ($products as &$product) {
            $product['images'] = $imageMap[$product['id']] ?? [];
            $discount = $discountMap[$product['id']] ?? false;
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
        unset($product);

        // PHP in-memory sort
        if ($sort === 'price_asc' || $sort === 'price_low') {
            usort($products, function($a, $b) {
                $priceA = (isset($a['sale_price']) && (float)$a['sale_price'] > 0) ? (float)$a['sale_price'] : (float)($a['price'] ?? 0);
                $priceB = (isset($b['sale_price']) && (float)$b['sale_price'] > 0) ? (float)$b['sale_price'] : (float)($b['price'] ?? 0);
                if ($priceA == $priceB) return 0;
                return ($priceA < $priceB) ? -1 : 1;
            });
        } elseif ($sort === 'price_desc' || $sort === 'price_high') {
            usort($products, function($a, $b) {
                $priceA = (isset($a['sale_price']) && (float)$a['sale_price'] > 0) ? (float)$a['sale_price'] : (float)($a['price'] ?? 0);
                $priceB = (isset($b['sale_price']) && (float)$b['sale_price'] > 0) ? (float)$b['sale_price'] : (float)($b['price'] ?? 0);
                if ($priceA == $priceB) return 0;
                return ($priceA > $priceB) ? -1 : 1;
            });
        } elseif ($sort === 'name_asc') {
            usort($products, function($a, $b) { return strcasecmp($a['name'] ?? '', $b['name'] ?? ''); });
        } elseif ($sort === 'name_desc') {
            usort($products, function($a, $b) { return strcasecmp($b['name'] ?? '', $a['name'] ?? ''); });
        }

        $total_items = count($products);
        $total_pages = $limit > 0 ? (int)ceil($total_items / $limit) : 1;
        $paginated_products = array_slice($products, $offset, $limit);

    } else {
        // --- DEFAULT/SKU/NEWEST SORT PATH: Use SQL LIMIT/OFFSET (fast path) ---
        
        // 1. Get total count
        $count_sql = "SELECT COUNT(*) FROM products p $where_sql";
        $countStmt = $pdo->prepare($count_sql);
        $countStmt->execute($params);
        $total_items = (int)$countStmt->fetchColumn();
        $total_pages = $limit > 0 ? (int)ceil($total_items / $limit) : 1;

        // 2. Fetch only the page we need
        $order_clause = "";
        if ($sort === 'newest' || $sort === 'created_desc') {
            $order_clause = "ORDER BY p.id DESC, p.created_at DESC";
        } elseif ($sort === 'sku_asc' || $sort === 'oldest') {
            $order_clause = "ORDER BY CAST(REGEXP_REPLACE(p.sku, '[^0-9]', '') AS UNSIGNED) ASC, p.id ASC";
        } else {
            $order_clause = "ORDER BY CAST(REGEXP_REPLACE(p.sku, '[^0-9]', '') AS UNSIGNED) DESC, p.id DESC";
        }

        $sql = "SELECT p.*, 
                (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM product_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.product_id = p.id) as category_name 
                FROM products p $where_sql $order_clause LIMIT ? OFFSET ?";
        
        $page_params = array_merge($params, [$limit, $offset]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($page_params);
        $paginated_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Batch-fetch images and discounts for ONLY the page (2 queries instead of 2N)
        $pageProductIds = array_column($paginated_products, 'id');
        $imageMap = get_bulk_product_images($pdo, $pageProductIds);
        $discountMap = get_bulk_product_discounts($pdo, $paginated_products);

        foreach ($paginated_products as &$product) {
            $product['images'] = $imageMap[$product['id']] ?? [];
            $discount = $discountMap[$product['id']] ?? false;
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
        unset($product);
    }
    
    // Log activity
    log_activity($pdo, 'api_fetch_products', 'product', null, "Fetched products list (page $page)", null, 'guest');
    
    $response_data = [
        'success' => true,
        'category' => $category_info,
        'data' => $paginated_products,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total_items,
            'limit' => $limit
        ]
    ];
    set_cache($cache_key, $response_data, $pdo);
    echo json_encode($response_data);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch products: ' . $e->getMessage()
    ]);
}

