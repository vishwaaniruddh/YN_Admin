<?php
// admin/pos-price-sync.php
$page_title = 'POS Price Synchronization';

if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/functions.php';
    if (!current_user_can('manage_products')) {
        die("You do not have permission to manage product prices.");
    }
} else {
    require_once __DIR__ . '/includes/functions.php';
}
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cache.php';

// -------------------------------------------------------------------------
// POS Database Connection Helper (Multi-environment fallbacks)
// -------------------------------------------------------------------------
if (!function_exists('get_pos_pdo')) {
    function get_pos_pdo() {
        static $posPdo = null;
        if ($posPdo !== null) return $posPdo;

        $isLocal = (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST']) || in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0);

        $configs = [];
        if ($isLocal) {
            $configs[] = ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'db' => 'u464193275_srishringarr'];
            $configs[] = ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'db' => 'u464193275_srishrinjewels'];
        } else {
            $configs[] = ['host' => 'localhost', 'user' => 'u464193275_sarmicropos', 'pass' => 'Mypos1234', 'db' => 'u464193275_srishringarr'];
            $configs[] = ['host' => 'localhost', 'user' => 'u464193275_yosshitanehafs', 'pass' => 'AVav@@2026', 'db' => 'u464193275_srishringarr'];
            $configs[] = ['host' => 'localhost', 'user' => 'u464193275_srishrinjuser', 'pass' => '9b@hMgk!=zI', 'db' => 'u464193275_srishrinjewels'];
        }

        foreach ($configs as $cfg) {
            try {
                $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['db']};charset=utf8mb4", $cfg['user'], $cfg['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                $stmt = $pdo->query("SHOW TABLES LIKE 'phppos_items'");
                if ($stmt->rowCount() > 0) {
                    $posPdo = $pdo;
                    return $posPdo;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }
}

// -------------------------------------------------------------------------
// Helper: Load indexed POS items hash map in memory (~45ms)
// -------------------------------------------------------------------------
function get_pos_items_map($posPdo) {
    static $posMap = null;
    if ($posMap !== null) return $posMap;

    $posMap = [
        'by_key' => [],
        'clean_key' => [],
        'total_items' => 0
    ];

    if (!$posPdo) return $posMap;

    $stmt = $posPdo->query("SELECT item_id, name, item_number, unit_price, cost_price, quantity FROM phppos_items WHERE (is_deleted = 0 OR is_deleted IS NULL)");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $posMap['total_items'] = count($items);

    foreach ($items as $row) {
        $uPrice = (float)($row['unit_price'] ?? 0);
        $name = trim($row['name'] ?? '');
        $itemNum = trim($row['item_number'] ?? '');

        if ($name !== '') {
            $kName = strtolower($name);
            $posMap['by_key'][$kName] = $row;
            $cName = preg_replace('/[^a-z0-9]/', '', $kName);
            if ($cName !== '') {
                $posMap['clean_key'][$cName] = $row;
            }
        }

        if ($itemNum !== '') {
            $kNum = strtolower($itemNum);
            $posMap['by_key'][$kNum] = $row;
            $cNum = preg_replace('/[^a-z0-9]/', '', $kNum);
            if ($cNum !== '') {
                $posMap['clean_key'][$cNum] = $row;
            }
        }
    }

    return $posMap;
}

// -------------------------------------------------------------------------
// AJAX API HANDLER: Load & Compare Products with POS
// -------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'load_category_products') {
    header('Content-Type: application/json');

    $posPdo = get_pos_pdo();
    if (!$posPdo) {
        echo json_encode([
            'success' => false,
            'message' => 'Could not connect to POS database (u464193275_srishringarr). Please verify database credentials.'
        ]);
        exit;
    }

    $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
    $filterType = $_GET['filter_type'] ?? 'mismatch';
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = isset($_GET['limit']) ? ($_GET['limit'] === 'all' ? 999999 : max(10, min(500, (int)$_GET['limit']))) : 50;

    try {
        // 1. Build Query for Products
        $where = ["p.deleted_at IS NULL"];
        $params = [];

        if ($categoryId !== null) {
            $where[] = "(p.category_id = ? OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?))";
            $params[] = $categoryId;
            $params[] = $categoryId;
        }

        if (!empty($search)) {
            $where[] = "(p.sku LIKE ? OR p.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $whereClause = implode(" AND ", $where);
        $sql = "SELECT p.id, p.sku, p.name, p.price, p.sale_price, p.main_image, p.category_id, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE $whereClause 
                ORDER BY p.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ynProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $posData = get_pos_items_map($posPdo);
        $byKey = $posData['by_key'];
        $cleanKey = $posData['clean_key'];

        $cntTotal = count($ynProducts);
        $cntMatched = 0;
        $cntMismatch = 0;
        $cntSynced = 0;
        $cntNotFound = 0;

        $filteredProducts = [];

        foreach ($ynProducts as $p) {
            $sku = trim($p['sku'] ?? '');
            $skuKey = strtolower($sku);
            $posItem = null;

            if (isset($byKey[$skuKey])) {
                $posItem = $byKey[$skuKey];
            } else {
                $cKey = preg_replace('/[^a-z0-9]/', '', $skuKey);
                if ($cKey !== '' && isset($cleanKey[$cKey])) {
                    $posItem = $cleanKey[$cKey];
                }
            }

            $currentPrice = (float)$p['price'];
            $posPrice = $posItem ? (float)$posItem['unit_price'] : null;

            $status = 'not_found';
            $diff = 0;

            if ($posItem !== null) {
                $cntMatched++;
                $diff = $posPrice - $currentPrice;

                // Price is considered mismatch if difference is > 0.01 OR current price is <= 1.00
                if (abs($diff) > 0.01 || $currentPrice <= 1.00) {
                    $status = 'mismatch';
                    $cntMismatch++;
                } else {
                    $status = 'synced';
                    $cntSynced++;
                }
            } else {
                $cntNotFound++;
            }

            // Check if matches the active filter tab
            $include = false;
            if ($filterType === 'all') {
                $include = true;
            } elseif ($filterType === 'mismatch' && $status === 'mismatch') {
                $include = true;
            } elseif ($filterType === 'matched' && ($status === 'mismatch' || $status === 'synced')) {
                $include = true;
            } elseif ($filterType === 'synced' && $status === 'synced') {
                $include = true;
            } elseif ($filterType === 'not_found' && $status === 'not_found') {
                $include = true;
            }

            if ($include) {
                $filteredProducts[] = [
                    'id' => (int)$p['id'],
                    'sku' => $sku,
                    'name' => $p['name'],
                    'category_name' => $p['category_name'] ?: 'Uncategorized',
                    'main_image' => $p['main_image'],
                    'current_price' => $currentPrice,
                    'pos_price' => $posPrice,
                    'pos_item_id' => $posItem ? (int)$posItem['item_id'] : null,
                    'pos_stock' => $posItem ? (float)$posItem['quantity'] : null,
                    'status' => $status,
                    'price_diff' => $diff
                ];
            }
        }

        // Pagination slicing
        $totalFiltered = count($filteredProducts);
        $totalPages = $limit > 0 ? (int)ceil($totalFiltered / $limit) : 1;
        if ($totalPages < 1) $totalPages = 1;
        if ($page > $totalPages) $page = $totalPages;

        $offset = ($page - 1) * $limit;
        $pageProducts = array_slice($filteredProducts, $offset, $limit);

        echo json_encode([
            'success' => true,
            'summary' => [
                'total' => $cntTotal,
                'matched' => $cntMatched,
                'mismatch' => $cntMismatch,
                'synced' => $cntSynced,
                'not_found' => $cntNotFound,
                'pos_total_inventory' => $posData['total_items']
            ],
            'pagination' => [
                'total_items' => $totalFiltered,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages
            ],
            'products' => $pageProducts
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// AJAX API HANDLER: Sync Single Product Price
// -------------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'sync_single_price') {
    header('Content-Type: application/json');

    $productId = (int)($_POST['product_id'] ?? 0);
    $newPrice = (float)($_POST['new_price'] ?? 0);

    if ($productId <= 0 || $newPrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID or price.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE products SET price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newPrice, $productId]);

        log_activity($pdo, 'pos_price_sync', 'product', $productId, "Synced price to ₹" . number_format($newPrice, 2));

        if (function_exists('purge_cache')) {
            purge_cache();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Price updated successfully.',
            'new_price' => $newPrice
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// AJAX API HANDLER: Batch Sync Product Prices
// -------------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'sync_batch_prices') {
    header('Content-Type: application/json');

    $items = json_decode($_POST['items'] ?? '[]', true);

    if (empty($items) || !is_array($items)) {
        echo json_encode(['success' => false, 'message' => 'No items provided for batch sync.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE products SET price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updatedCount = 0;

        foreach ($items as $it) {
            $pId = (int)($it['id'] ?? 0);
            $pPrice = (float)($it['price'] ?? 0);

            if ($pId > 0 && $pPrice > 0) {
                $stmt->execute([$pPrice, $pId]);
                $updatedCount++;
            }
        }

        $pdo->commit();

        log_activity($pdo, 'pos_price_sync_batch', 'product', null, "Batch synced $updatedCount product prices from POS");

        if (function_exists('purge_cache')) {
            purge_cache();
        }

        echo json_encode([
            'success' => true,
            'updated_count' => $updatedCount,
            'message' => "Successfully updated $updatedCount product prices to match POS unit_price!"
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// AJAX API HANDLER: Full Sync ALL Mismatched Prices (One-Click Store Sync)
// -------------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'sync_all_mismatched') {
    header('Content-Type: application/json');

    $posPdo = get_pos_pdo();
    if (!$posPdo) {
        echo json_encode(['success' => false, 'message' => 'Cannot connect to POS database.']);
        exit;
    }

    $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;

    try {
        $where = ["p.deleted_at IS NULL"];
        $params = [];

        if ($categoryId !== null) {
            $where[] = "(p.category_id = ? OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?))";
            $params[] = $categoryId;
            $params[] = $categoryId;
        }

        $whereClause = implode(" AND ", $where);
        $stmt = $pdo->prepare("SELECT p.id, p.sku, p.price FROM products p WHERE $whereClause");
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $posData = get_pos_items_map($posPdo);
        $byKey = $posData['by_key'];
        $cleanKey = $posData['clean_key'];

        $toUpdate = [];
        foreach ($products as $p) {
            $sku = trim($p['sku'] ?? '');
            $skuKey = strtolower($sku);
            $posItem = null;

            if (isset($byKey[$skuKey])) {
                $posItem = $byKey[$skuKey];
            } else {
                $cKey = preg_replace('/[^a-z0-9]/', '', $skuKey);
                if ($cKey !== '' && isset($cleanKey[$cKey])) {
                    $posItem = $cleanKey[$cKey];
                }
            }

            if ($posItem) {
                $posPrice = (float)$posItem['unit_price'];
                $curPrice = (float)$p['price'];
                if ($posPrice > 0 && (abs($posPrice - $curPrice) > 0.01 || $curPrice <= 1.00)) {
                    $toUpdate[] = ['id' => (int)$p['id'], 'price' => $posPrice];
                }
            }
        }

        $totalMismatch = count($toUpdate);
        if ($totalMismatch === 0) {
            echo json_encode([
                'success' => true,
                'updated_count' => 0,
                'message' => 'All matching products are already in sync with POS unit prices!'
            ]);
            exit;
        }

        $pdo->beginTransaction();
        $upStmt = $pdo->prepare("UPDATE products SET price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        foreach ($toUpdate as $item) {
            $upStmt->execute([$item['price'], $item['id']]);
        }
        $pdo->commit();

        log_activity($pdo, 'pos_price_sync_all', 'product', null, "Full synced $totalMismatch product prices from POS unit_price");

        if (function_exists('purge_cache')) {
            purge_cache();
        }

        echo json_encode([
            'success' => true,
            'updated_count' => $totalMismatch,
            'message' => "Successfully synchronized $totalMismatch products with POS unit_price!"
        ]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// Page View Rendering
// -------------------------------------------------------------------------
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch categories for tree dropdown
try {
    $categories_raw = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
    $categories = get_category_tree($categories_raw);
} catch (Exception $e) {
    $categories = [];
}

$posPdo = get_pos_pdo();
$posConnected = ($posPdo !== null);
$posItemCount = 0;
if ($posConnected) {
    try {
        $posItemCount = (int)$posPdo->query("SELECT COUNT(*) FROM phppos_items WHERE (is_deleted = 0 OR is_deleted IS NULL)")->fetchColumn();
    } catch (Exception $e) {}
}
?>

<div class="wrap" style="max-width: 1400px; margin: 20px auto; padding: 0 15px;">
    
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <h1 style="font-size: 22px; font-weight: 700; color: #09090b; margin: 0; display: flex; align-items: center; gap: 9px; letter-spacing: -0.02em;">
                    <i class="fa-solid fa-tags" style="color: #4f46e5;"></i>
                    POS Price Synchronization
                </h1>
                <?php if ($posConnected): ?>
                    <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 3px 10px; border-radius: 20px; font-weight: 600;">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
                        POS Online (<?php echo number_format($posItemCount); ?> items)
                    </span>
                <?php else: ?>
                    <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 3px 10px; border-radius: 20px; font-weight: 600;">
                        <i class="fa-solid fa-circle-exclamation"></i> POS Offline
                    </span>
                <?php endif; ?>
            </div>
            <p style="font-size: 13px; color: #71717a; margin: 4px 0 0 0;">
                Compare web product selling prices against POS <code>unit_price</code> and synchronize them in real-time.
            </p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="products.php" class="button" style="background: #ffffff; color: #09090b; border: 1px solid #e4e4e7; font-weight: 500; border-radius: 6px; padding: 0 14px; height: 34px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                <i class="fa-solid fa-arrow-left" style="font-size: 11px;"></i> All Products
            </a>
            <button type="button" id="refresh_scan_btn" class="button" style="background: #ffffff; color: #4f46e5; border: 1px solid #c7d2fe; font-weight: 600; border-radius: 6px; padding: 0 14px; height: 34px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                <i class="fa-solid fa-rotate"></i> Refresh Scan
            </button>
        </div>
    </div>

    <?php if (!$posConnected): ?>
        <div class="shadcn-card" style="background: #fff1f2; border: 1px solid #fecdd3; padding: 16px 20px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: flex; gap: 12px; align-items: flex-start;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #e11d48; font-size: 18px; margin-top: 2px;"></i>
                <div>
                    <h3 style="margin: 0 0 4px 0; color: #9f1239; font-size: 14px; font-weight: 600;">POS Database Connection Failed</h3>
                    <p style="margin: 0; color: #be123c; font-size: 12.5px;">
                        Could not connect to the POS database (<code>u464193275_srishringarr</code>). Please ensure MySQL service is running and the database is imported.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Summary Metrics Cards -->
    <div class="shadcn-stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 20px;">
        
        <!-- Total Products Card -->
        <div class="shadcn-stat-card">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title">Total Products</span>
                <span class="shadcn-stat-icon-wrap" style="background: #f4f4f5; color: #71717a;">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </span>
            </div>
            <div class="shadcn-stat-value" id="stat_total">--</div>
            <div class="shadcn-stat-note" id="stat_total_note">Total in catalog</div>
        </div>

        <!-- Price Mismatches Card -->
        <div class="shadcn-stat-card" style="border-color: #fecaca; background: #fffafb;">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title" style="color: #dc2626; font-weight: 600;">Price Mismatch</span>
                <span class="shadcn-stat-icon-wrap" style="background: #fee2e2; color: #dc2626;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </span>
            </div>
            <div class="shadcn-stat-value" id="stat_mismatch" style="color: #dc2626;">--</div>
            <div class="shadcn-stat-note" style="color: #ef4444;">Differs from POS unit_price</div>
        </div>

        <!-- In Sync Card -->
        <div class="shadcn-stat-card" style="border-color: #a7f3d0; background: #f7fdfa;">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title" style="color: #059669; font-weight: 600;">Already In Sync</span>
                <span class="shadcn-stat-icon-wrap" style="background: #d1fae5; color: #059669;">
                    <i class="fa-solid fa-circle-check"></i>
                </span>
            </div>
            <div class="shadcn-stat-value" id="stat_synced" style="color: #059669;">--</div>
            <div class="shadcn-stat-note" style="color: #10b981;">Matches POS unit_price exactly</div>
        </div>

        <!-- Missing in POS Card -->
        <div class="shadcn-stat-card">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title">Not in POS</span>
                <span class="shadcn-stat-icon-wrap" style="background: #f4f4f5; color: #71717a;">
                    <i class="fa-solid fa-circle-question"></i>
                </span>
            </div>
            <div class="shadcn-stat-value" id="stat_not_found">--</div>
            <div class="shadcn-stat-note">SKU not found in POS database</div>
        </div>

    </div>

    <!-- Master Action Banner / Sync Controls -->
    <div class="shadcn-card" id="master_action_card" style="background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border: 1px solid #e2e8f0; margin-bottom: 20px;">
        <div class="shadcn-card-padded" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: #e0e7ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 20px; shrink-0;">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div>
                    <h3 style="font-size: 14.5px; font-weight: 700; color: #0f172a; margin: 0 0 3px 0;">
                        1-Click Synchronization
                    </h3>
                    <p style="font-size: 12.5px; color: #64748b; margin: 0;" id="sync_banner_subtext">
                        Ready to scan and update prices directly to match POS unit_price.
                    </p>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <button type="button" id="sync_selected_btn" disabled class="button" style="height: 38px; border-radius: 6px; background: #ffffff; color: #4f46e5; border: 1px solid #c7d2fe; font-weight: 600; font-size: 12.5px; padding: 0 16px; display: inline-flex; align-items: center; gap: 7px; opacity: 0.5; cursor: not-allowed; transition: all 0.15s ease;">
                    <i class="fa-solid fa-list-check"></i>
                    Sync Selected (<span id="btn_sel_count">0</span>)
                </button>

                <button type="button" id="sync_all_btn" disabled class="button button-primary" style="height: 38px; border-radius: 6px; background: #4f46e5; border-color: #4338ca; color: #ffffff; font-weight: 600; font-size: 12.5px; padding: 0 18px; display: inline-flex; align-items: center; gap: 8px; opacity: 0.5; cursor: not-allowed; box-shadow: 0 1px 2px rgba(79, 70, 229, 0.2); transition: all 0.15s ease;">
                    <i class="fa-solid fa-rocket"></i>
                    Sync ALL Mismatched Prices (<span id="btn_mismatch_count">0</span>)
                </button>
            </div>
        </div>
    </div>

    <!-- Filter & Toolbar Card -->
    <div class="shadcn-card" style="margin-bottom: 20px;">
        <div class="shadcn-card-padded" style="padding: 14px 16px;">
            
            <!-- Filter Tabs -->
            <div class="shadcn-filter-tabs" id="status_tabs" style="margin-bottom: 14px;">
                <a href="javascript:void(0);" data-filter="mismatch" class="shadcn-tab-item active">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #dc2626;"></i>
                    Price Mismatch
                    <span class="shadcn-tab-count" id="tab_count_mismatch">0</span>
                </a>
                <a href="javascript:void(0);" data-filter="matched" class="shadcn-tab-item">
                    <i class="fa-solid fa-circle-check" style="color: #059669;"></i>
                    All Matched in POS
                    <span class="shadcn-tab-count" id="tab_count_matched">0</span>
                </a>
                <a href="javascript:void(0);" data-filter="synced" class="shadcn-tab-item">
                    <i class="fa-solid fa-check-double" style="color: #10b981;"></i>
                    Already In Sync
                    <span class="shadcn-tab-count" id="tab_count_synced">0</span>
                </a>
                <a href="javascript:void(0);" data-filter="not_found" class="shadcn-tab-item">
                    <i class="fa-solid fa-circle-question" style="color: #71717a;"></i>
                    Missing in POS
                    <span class="shadcn-tab-count" id="tab_count_not_found">0</span>
                </a>
                <a href="javascript:void(0);" data-filter="all" class="shadcn-tab-item">
                    <i class="fa-solid fa-table-list" style="color: #6366f1;"></i>
                    All Products
                    <span class="shadcn-tab-count" id="tab_count_all">0</span>
                </a>
            </div>

            <!-- Controls Row: Category, Search, Limit -->
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                
                <!-- Category Select -->
                <div style="min-width: 220px; flex: 1;">
                    <select id="category_select" class="form-control" style="width: 100%; height: 34px; font-size: 12.5px;">
                        <option value="">-- All Categories --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo str_repeat('&nbsp;&nbsp;&nbsp;', isset($cat['depth']) ? $cat['depth'] : 0) . htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Instant Search -->
                <div style="min-width: 240px; flex: 1.5; position: relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #a1a1aa; font-size: 11px;"></i>
                    <input type="text" id="search_input" placeholder="Search SKU or Product Name..." class="form-control" style="width: 100%; height: 34px; padding-left: 28px; padding-right: 26px; font-size: 12.5px;">
                    <button type="button" id="search_clear_btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: none; background: transparent; color: #a1a1aa; cursor: pointer; display: none; font-size: 14px;">&times;</button>
                </div>

                <!-- Page Limit Select -->
                <div style="min-width: 120px;">
                    <select id="limit_select" class="form-control" style="width: 100%; height: 34px; font-size: 12.5px;">
                        <option value="25">25 per page</option>
                        <option value="50" selected>50 per page</option>
                        <option value="100">100 per page</option>
                        <option value="200">200 per page</option>
                        <option value="all">Show All</option>
                    </select>
                </div>

                <!-- Filter Submit / Trigger -->
                <button type="button" id="apply_filter_btn" class="button" style="height: 34px; background: #09090b; color: #ffffff; border-color: #09090b; font-weight: 600; font-size: 12.5px; padding: 0 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-filter" style="font-size: 11px;"></i> Filter
                </button>
            </div>

        </div>
    </div>

    <!-- Product Comparison Table Card -->
    <div class="shadcn-card" style="margin-bottom: 20px;">
        <div class="shadcn-card-header" style="background: #fafafa; border-bottom: 1px solid #e4e4e7; padding: 10px 16px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="select_all_checkbox" style="width: 16px; height: 16px; cursor: pointer;" title="Select All Visible">
                <span style="font-size: 12.5px; font-weight: 600; color: #09090b;">Product Price Comparison</span>
                <span id="selected_pill" style="font-size: 11px; background: #e0e7ff; color: #4338ca; padding: 1px 7px; border-radius: 10px; font-weight: 600; display: none;">0 selected</span>
            </div>

            <div style="font-size: 12px; color: #71717a;" id="table_meta_count">
                Loading products...
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="shadcn-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"></th>
                        <th style="width: 55px; text-align: center;">Image</th>
                        <th style="width: 130px;">SKU</th>
                        <th>Product Details</th>
                        <th style="width: 140px; text-align: right;">Current Web Price</th>
                        <th style="width: 150px; text-align: right; background: #f0fdf4; color: #166534; font-weight: 600;">POS Unit Price</th>
                        <th style="width: 160px; text-align: center;">Difference</th>
                        <th style="width: 120px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="product_tbody">
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 60px 20px; color: #71717a;">
                            <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px; color: #4f46e5; margin-bottom: 10px; display: block;"></i>
                            Loading product prices from POS database...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Table Footer / Pagination -->
        <div class="shadcn-card-padded" id="pagination_container" style="padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f4f4f5; flex-wrap: wrap; gap: 10px;">
            <div style="font-size: 12px; color: #71717a;" id="pagination_info">
                Showing 0 of 0 entries
            </div>

            <div style="display: flex; align-items: center; gap: 6px;" id="pagination_buttons">
                <!-- Dynamically rendered -->
            </div>
        </div>
    </div>

</div>

<!-- ----------------------------------------------------------------------- -->
<!-- Progress Modal for 1-Click Sync ALL -->
<!-- ----------------------------------------------------------------------- -->
<div id="sync_modal_backdrop" style="display: none; position: fixed; inset: 0; background: rgba(9, 9, 11, 0.5); backdrop-filter: blur(2px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; width: 100%; max-width: 480px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); border: 1px solid #e4e4e7; overflow: hidden; animation: popIn 0.15s ease-out;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #f4f4f5; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #e0e7ff; color: #4f46e5; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-arrows-rotate fa-spin" id="modal_spinner_icon"></i>
                </div>
                <h3 style="font-size: 15px; font-weight: 700; color: #09090b; margin: 0;" id="modal_title">
                    Syncing Prices from POS
                </h3>
            </div>
            <button type="button" id="modal_close_btn" style="border: none; background: transparent; font-size: 18px; color: #a1a1aa; cursor: pointer; display: none;" onclick="closeSyncModal()">&times;</button>
        </div>

        <div style="padding: 24px;">
            <p style="font-size: 13px; color: #52525b; margin: 0 0 16px 0;" id="modal_status_text">
                Updating mismatched products to match POS unit_price...
            </p>

            <!-- Progress Bar -->
            <div style="background: #f4f4f5; height: 10px; border-radius: 5px; overflow: hidden; margin-bottom: 14px;">
                <div id="modal_progress_bar" style="background: #4f46e5; height: 100%; width: 0%; transition: width 0.2s ease; border-radius: 5px;"></div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #71717a;">
                <span id="modal_counter_text">Processing...</span>
                <span id="modal_percentage_text" style="font-weight: 700; color: #09090b;">0%</span>
            </div>

            <div id="modal_success_box" style="display: none; margin-top: 16px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 14px; border-radius: 8px; font-size: 12.5px;">
                <i class="fa-solid fa-circle-check" style="color: #10b981; margin-right: 6px;"></i>
                <span id="modal_success_msg">Sync completed successfully!</span>
            </div>
        </div>

        <div style="padding: 14px 24px; background: #fafafa; border-top: 1px solid #f4f4f5; display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" id="modal_done_btn" onclick="closeSyncModal()" class="button button-primary" style="display: none; background: #09090b; border-color: #09090b; font-weight: 600; border-radius: 6px; padding: 0 18px; height: 34px;">
                Done
            </button>
        </div>
    </div>
</div>

<style>
@keyframes popIn {
    from { transform: scale(0.96); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.row-highlight-synced {
    background-color: #f0fdf4 !important;
    transition: background-color 0.4s ease;
}
.copy-sku-btn {
    opacity: 0.6;
    transition: opacity 0.12s ease;
    cursor: pointer;
    font-size: 10px;
    margin-left: 4px;
    color: #6366f1;
}
.copy-sku-btn:hover {
    opacity: 1;
}
</style>

<script>
let currentFilter = 'mismatch';
let currentPage = 1;
let currentLimit = 50;
let loadedData = null;

const categorySelect = document.getElementById('category_select');
const searchInput = document.getElementById('search_input');
const searchClearBtn = document.getElementById('search_clear_btn');
const limitSelect = document.getElementById('limit_select');
const applyFilterBtn = document.getElementById('apply_filter_btn');
const refreshScanBtn = document.getElementById('refresh_scan_btn');
const tbody = document.getElementById('product_tbody');

const statTotal = document.getElementById('stat_total');
const statMismatch = document.getElementById('stat_mismatch');
const statSynced = document.getElementById('stat_synced');
const statNotFound = document.getElementById('stat_not_found');

const selectAllCheckbox = document.getElementById('select_all_checkbox');
const selectedPill = document.getElementById('selected_pill');
const syncSelectedBtn = document.getElementById('sync_selected_btn');
const btnSelCount = document.getElementById('btn_sel_count');
const syncAllBtn = document.getElementById('sync_all_btn');
const btnMismatchCount = document.getElementById('btn_mismatch_count');
const syncBannerSubtext = document.getElementById('sync_banner_subtext');

const paginationInfo = document.getElementById('pagination_info');
const paginationButtons = document.getElementById('pagination_buttons');
const tableMetaCount = document.getElementById('table_meta_count');

// Load Products via AJAX
async function loadProducts(page = 1) {
    currentPage = page;
    currentLimit = limitSelect.value;
    const catId = categorySelect.value;
    const search = searchInput.value.trim();

    tbody.innerHTML = `
        <tr>
            <td colspan="8" style="text-align: center; padding: 60px 20px; color: #71717a;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px; color: #4f46e5; margin-bottom: 10px; display: block;"></i>
                Scanning and cross-referencing products with POS unit_price...
            </td>
        </tr>
    `;

    try {
        const url = `pos-price-sync.php?action=load_category_products&category_id=${encodeURIComponent(catId)}&filter_type=${encodeURIComponent(currentFilter)}&search=${encodeURIComponent(search)}&page=${currentPage}&limit=${encodeURIComponent(currentLimit)}`;
        const res = await fetch(url);
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px 20px; color: #dc2626;">
                        <i class="fa-solid fa-circle-exclamation" style="font-size: 20px; margin-bottom: 6px; display: block;"></i>
                        ${data.message || 'Error loading products.'}
                    </td>
                </tr>
            `;
            return;
        }

        loadedData = data;

        // 1. Update Metrics
        statTotal.textContent = Number(data.summary.total).toLocaleString('en-IN');
        statMismatch.textContent = Number(data.summary.mismatch).toLocaleString('en-IN');
        statSynced.textContent = Number(data.summary.synced).toLocaleString('en-IN');
        statNotFound.textContent = Number(data.summary.not_found).toLocaleString('en-IN');

        document.getElementById('tab_count_mismatch').textContent = data.summary.mismatch;
        document.getElementById('tab_count_matched').textContent = data.summary.matched;
        document.getElementById('tab_count_synced').textContent = data.summary.synced;
        document.getElementById('tab_count_not_found').textContent = data.summary.not_found;
        document.getElementById('tab_count_all').textContent = data.summary.total;

        // 2. Update Master Action Button
        btnMismatchCount.textContent = Number(data.summary.mismatch).toLocaleString('en-IN');
        if (data.summary.mismatch > 0) {
            syncAllBtn.disabled = false;
            syncAllBtn.style.opacity = '1';
            syncAllBtn.style.cursor = 'pointer';
            syncBannerSubtext.innerHTML = `Found <strong style="color: #dc2626;">${Number(data.summary.mismatch).toLocaleString('en-IN')} products</strong> whose website price differs from POS unit_price.`;
        } else {
            syncAllBtn.disabled = true;
            syncAllBtn.style.opacity = '0.5';
            syncAllBtn.style.cursor = 'not-allowed';
            syncBannerSubtext.innerHTML = `All matched products in this selection are <strong style="color: #059669;">100% in sync</strong> with POS unit_price.`;
        }

        // 3. Render Table Rows
        renderTable(data.products || []);

        // 4. Render Pagination
        renderPagination(data.pagination);

    } catch (err) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px 20px; color: #dc2626;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 20px; margin-bottom: 6px; display: block;"></i>
                    Network error: ${err.message}
                </td>
            </tr>
        `;
    }
}

function renderTable(products) {
    if (products.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 50px 20px; color: #71717a;">
                    <i class="fa-solid fa-box-open" style="font-size: 26px; color: #d4d4d8; margin-bottom: 8px; display: block;"></i>
                    No products found matching the current filter.
                </td>
            </tr>
        `;
        selectAllCheckbox.checked = false;
        updateSelectionUI();
        return;
    }

    let html = '';
    products.forEach(p => {
        const canSync = (p.pos_price !== null && p.status === 'mismatch');
        const imgUrl = p.main_image ? p.main_image : 'assets/images/placeholder.png';

        let diffBadge = '';
        if (p.status === 'mismatch') {
            const diffSign = p.price_diff > 0 ? '+' : '';
            diffBadge = `<span style="display: inline-flex; align-items: center; gap: 4px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                <i class="fa-solid fa-arrow-trend-${p.price_diff > 0 ? 'up' : 'down'}" style="font-size: 9px;"></i>
                ${diffSign}₹${Math.abs(p.price_diff).toLocaleString('en-IN', {minimumFractionDigits: 2})}
            </span>`;
        } else if (p.status === 'synced') {
            diffBadge = `<span style="display: inline-flex; align-items: center; gap: 4px; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                <i class="fa-solid fa-check" style="font-size: 9px;"></i> In Sync
            </span>`;
        } else {
            diffBadge = `<span style="display: inline-flex; align-items: center; gap: 4px; background: #f4f4f5; color: #71717a; border: 1px solid #e4e4e7; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                Not in POS
            </span>`;
        }

        const currentPriceFmt = '₹' + Number(p.current_price).toLocaleString('en-IN', {minimumFractionDigits: 2});
        const posPriceFmt = p.pos_price !== null ? '₹' + Number(p.pos_price).toLocaleString('en-IN', {minimumFractionDigits: 2}) : '<span style="color: #a1a1aa; font-weight: 400;">N/A</span>';

        html += `
            <tr id="row-${p.id}" data-id="${p.id}" data-posprice="${p.pos_price || 0}">
                <td style="text-align: center;">
                    ${canSync ? `<input type="checkbox" class="row-checkbox" value="${p.id}" style="width: 15px; height: 15px; cursor: pointer;">` : `<input type="checkbox" disabled style="opacity: 0.25;">`}
                </td>
                <td style="text-align: center;">
                    <div style="width: 40px; height: 40px; border-radius: 6px; overflow: hidden; border: 1px solid #e4e4e7; background: #f4f4f5; display: inline-flex; align-items: center; justify-content: center;">
                        <img src="${imgUrl}" alt="" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: #a1a1aa;">
                            <i class="fa-solid fa-gem" style="font-size: 12px;"></i>
                        </div>
                    </div>
                </td>
                <td>
                    <div style="display: flex; align-items: center;">
                        <span style="font-family: monospace; font-size: 12.5px; font-weight: 600; color: #4338ca;">${p.sku}</span>
                        <i class="fa-regular fa-copy copy-sku-btn" title="Copy SKU" onclick="copySku('${p.sku}')"></i>
                    </div>
                    ${p.pos_item_id ? `<div style="font-size: 10px; color: #a1a1aa; margin-top: 1px;">POS Item #${p.pos_item_id}</div>` : ''}
                </td>
                <td>
                    <div style="font-weight: 500; color: #09090b; font-size: 13px; max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${p.name}">
                        ${p.name}
                    </div>
                    <div style="font-size: 11px; color: #71717a; margin-top: 2px;">
                        <i class="fa-solid fa-folder" style="color: #cbd5e1; font-size: 10px;"></i> ${p.category_name}
                    </div>
                </td>
                <td style="text-align: right; font-family: monospace; font-size: 13px; font-weight: 600; ${p.status === 'mismatch' ? 'color: #dc2626; text-decoration: line-through;' : 'color: #09090b;'}">
                    ${currentPriceFmt}
                </td>
                <td style="text-align: right; font-family: monospace; font-size: 13px; font-weight: 700; color: #059669; background: #f0fdf4;">
                    ${posPriceFmt}
                </td>
                <td style="text-align: center;">
                    ${diffBadge}
                </td>
                <td style="text-align: center;" id="action-cell-${p.id}">
                    ${canSync ? `
                        <button type="button" onclick="syncSingle(${p.id}, ${p.pos_price})" class="button" style="background: #ffffff; color: #4f46e5; border: 1px solid #c7d2fe; font-weight: 600; font-size: 11.5px; border-radius: 6px; padding: 3px 10px; height: 26px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
                            <i class="fa-solid fa-bolt" style="font-size: 10px;"></i> Sync
                        </button>
                    ` : `<span style="color: #d4d4d8; font-size: 11px;">—</span>`}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    selectAllCheckbox.checked = false;
    updateSelectionUI();

    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectionUI);
    });
}

function renderPagination(pg) {
    if (!pg || pg.total_items === 0) {
        paginationInfo.textContent = 'Showing 0 of 0 entries';
        paginationButtons.innerHTML = '';
        tableMetaCount.textContent = '0 items';
        return;
    }

    const start = (pg.page - 1) * pg.limit + 1;
    const end = Math.min(pg.page * pg.limit, pg.total_items);
    paginationInfo.textContent = `Showing ${start.toLocaleString('en-IN')} to ${end.toLocaleString('en-IN')} of ${pg.total_items.toLocaleString('en-IN')} products`;
    tableMetaCount.textContent = `${pg.total_items.toLocaleString('en-IN')} products total`;

    let html = '';
    // Prev Button
    html += `<button type="button" class="button" style="height: 30px; padding: 0 10px; font-size: 12px; border-radius: 5px; ${pg.page <= 1 ? 'opacity: 0.4; cursor: not-allowed;' : 'cursor: pointer;'}" ${pg.page <= 1 ? 'disabled' : `onclick="loadProducts(${pg.page - 1})"`}>
        <i class="fa-solid fa-chevron-left" style="font-size: 10px;"></i> Prev
    </button>`;

    // Page Numbers (sliding window)
    const totalP = pg.total_pages;
    const curP = pg.page;
    let startP = Math.max(1, curP - 2);
    let endP = Math.min(totalP, curP + 2);

    if (startP > 1) {
        html += `<button type="button" class="button" onclick="loadProducts(1)" style="height: 30px; width: 30px; padding: 0; font-size: 12px; border-radius: 5px;">1</button>`;
        if (startP > 2) html += `<span style="color: #a1a1aa; padding: 0 3px;">...</span>`;
    }

    for (let i = startP; i <= endP; i++) {
        const isActive = (i === curP);
        html += `<button type="button" class="button ${isActive ? 'button-primary' : ''}" style="height: 30px; width: 30px; padding: 0; font-size: 12px; border-radius: 5px; ${isActive ? 'background: #09090b; border-color: #09090b;' : ''}" onclick="loadProducts(${i})">${i}</button>`;
    }

    if (endP < totalP) {
        if (endP < totalP - 1) html += `<span style="color: #a1a1aa; padding: 0 3px;">...</span>`;
        html += `<button type="button" class="button" onclick="loadProducts(${totalP})" style="height: 30px; width: 30px; padding: 0; font-size: 12px; border-radius: 5px;">${totalP}</button>`;
    }

    // Next Button
    html += `<button type="button" class="button" style="height: 30px; padding: 0 10px; font-size: 12px; border-radius: 5px; ${pg.page >= totalP ? 'opacity: 0.4; cursor: not-allowed;' : 'cursor: pointer;'}" ${pg.page >= totalP ? 'disabled' : `onclick="loadProducts(${pg.page + 1})"`}>
        Next <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
    </button>`;

    paginationButtons.innerHTML = html;
}

function updateSelectionUI() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const count = checkboxes.length;
    btnSelCount.textContent = count;

    if (count > 0) {
        selectedPill.style.display = 'inline-block';
        selectedPill.textContent = `${count} selected`;
        syncSelectedBtn.disabled = false;
        syncSelectedBtn.style.opacity = '1';
        syncSelectedBtn.style.cursor = 'pointer';
        syncSelectedBtn.style.background = '#4f46e5';
        syncSelectedBtn.style.color = '#ffffff';
    } else {
        selectedPill.style.display = 'none';
        syncSelectedBtn.disabled = true;
        syncSelectedBtn.style.opacity = '0.5';
        syncSelectedBtn.style.cursor = 'not-allowed';
        syncSelectedBtn.style.background = '#ffffff';
        syncSelectedBtn.style.color = '#4f46e5';
    }
}

selectAllCheckbox.addEventListener('change', function() {
    const isChecked = this.checked;
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = isChecked;
    });
    updateSelectionUI();
});

// Single Product Sync
async function syncSingle(productId, newPrice) {
    const actionCell = document.getElementById(`action-cell-${productId}`);
    if (actionCell) actionCell.innerHTML = `<i class="fa-solid fa-spinner fa-spin" style="color: #4f46e5;"></i>`;

    const formData = new FormData();
    formData.append('action', 'sync_single_price');
    formData.append('product_id', productId);
    formData.append('new_price', newPrice);

    try {
        const res = await fetch('pos-price-sync.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            if (actionCell) actionCell.innerHTML = `<span style="color: #059669; font-weight: 600; font-size: 11.5px; display: inline-flex; align-items: center; gap: 3px;"><i class="fa-solid fa-circle-check"></i> Synced</span>`;
            
            const row = document.getElementById(`row-${productId}`);
            if (row) {
                row.classList.add('row-highlight-synced');
                // Update current price cell
                const priceCell = row.children[4];
                if (priceCell) {
                    priceCell.style.color = '#09090b';
                    priceCell.style.textDecoration = 'none';
                    priceCell.textContent = '₹' + Number(newPrice).toLocaleString('en-IN', {minimumFractionDigits: 2});
                }
                // Update difference cell
                const diffCell = row.children[6];
                if (diffCell) {
                    diffCell.innerHTML = `<span style="display: inline-flex; align-items: center; gap: 4px; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;"><i class="fa-solid fa-check" style="font-size: 9px;"></i> In Sync</span>`;
                }
                // Remove checkbox
                const cbCell = row.children[0];
                if (cbCell) cbCell.innerHTML = `<input type="checkbox" disabled style="opacity: 0.25;">`;
            }

            // Decrement mismatch metric
            const curMismatch = parseInt(statMismatch.textContent.replace(/,/g, '')) || 0;
            if (curMismatch > 0) {
                statMismatch.textContent = (curMismatch - 1).toLocaleString('en-IN');
                document.getElementById('tab_count_mismatch').textContent = (curMismatch - 1);
                btnMismatchCount.textContent = (curMismatch - 1).toLocaleString('en-IN');
            }
            const curSynced = parseInt(statSynced.textContent.replace(/,/g, '')) || 0;
            statSynced.textContent = (curSynced + 1).toLocaleString('en-IN');
            document.getElementById('tab_count_synced').textContent = (curSynced + 1);

            updateSelectionUI();
        } else {
            alert('Sync failed: ' + data.message);
            if (actionCell) actionCell.innerHTML = `<button type="button" onclick="syncSingle(${productId}, ${newPrice})" class="button" style="color: #dc2626;">Retry</button>`;
        }
    } catch (err) {
        alert('Network error: ' + err.message);
        if (actionCell) actionCell.innerHTML = `<button type="button" onclick="syncSingle(${productId}, ${newPrice})" class="button" style="color: #dc2626;">Retry</button>`;
    }
}

// Batch Sync Selected
syncSelectedBtn.addEventListener('click', async function() {
    const checked = Array.from(document.querySelectorAll('.row-checkbox:checked'));
    if (checked.length === 0) return;

    if (!confirm(`Are you sure you want to update ${checked.length} selected product(s) to match POS unit_price?`)) {
        return;
    }

    const items = checked.map(cb => {
        const row = document.getElementById(`row-${cb.value}`);
        const posPrice = parseFloat(row.getAttribute('data-posprice'));
        return { id: parseInt(cb.value), price: posPrice };
    });

    syncSelectedBtn.disabled = true;
    syncSelectedBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Updating ${items.length}...`;

    const formData = new FormData();
    formData.append('action', 'sync_batch_prices');
    formData.append('items', JSON.stringify(items));

    try {
        const res = await fetch('pos-price-sync.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            loadProducts(currentPage);
        } else {
            alert('Batch sync failed: ' + data.message);
            syncSelectedBtn.disabled = false;
            updateSelectionUI();
        }
    } catch (err) {
        alert('Network error: ' + err.message);
        syncSelectedBtn.disabled = false;
        updateSelectionUI();
    }
});

// 1-Click Sync ALL Mismatched Prices
syncAllBtn.addEventListener('click', async function() {
    const mismatchCount = loadedData ? loadedData.summary.mismatch : 0;
    if (mismatchCount === 0) {
        alert('No mismatched products found to sync.');
        return;
    }

    if (!confirm(`Are you sure you want to update all ${mismatchCount.toLocaleString('en-IN')} mismatched product prices across the catalog to match POS unit_price?`)) {
        return;
    }

    openSyncModal(mismatchCount);

    const formData = new FormData();
    formData.append('action', 'sync_all_mismatched');
    if (categorySelect.value) {
        formData.append('category_id', categorySelect.value);
    }

    try {
        const res = await fetch('pos-price-sync.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            updateModalProgress(100, data.updated_count, data.updated_count);
            document.getElementById('modal_spinner_icon').className = 'fa-solid fa-circle-check';
            document.getElementById('modal_spinner_icon').style.color = '#10b981';
            document.getElementById('modal_success_box').style.display = 'block';
            document.getElementById('modal_success_msg').textContent = data.message;
            document.getElementById('modal_done_btn').style.display = 'inline-flex';
            document.getElementById('modal_close_btn').style.display = 'inline-block';
        } else {
            alert('Sync failed: ' + data.message);
            closeSyncModal();
        }
    } catch (err) {
        alert('Network error during sync: ' + err.message);
        closeSyncModal();
    }
});

// Modal helpers
function openSyncModal(total) {
    const backdrop = document.getElementById('sync_modal_backdrop');
    backdrop.style.display = 'flex';
    document.getElementById('modal_spinner_icon').className = 'fa-solid fa-arrows-rotate fa-spin';
    document.getElementById('modal_spinner_icon').style.color = '#4f46e5';
    document.getElementById('modal_success_box').style.display = 'none';
    document.getElementById('modal_done_btn').style.display = 'none';
    document.getElementById('modal_close_btn').style.display = 'none';
    document.getElementById('modal_progress_bar').style.width = '35%';
    document.getElementById('modal_percentage_text').textContent = 'Processing...';
    document.getElementById('modal_counter_text').textContent = `Synchronizing ${total.toLocaleString('en-IN')} products...`;
}

function updateModalProgress(pct, updated, total) {
    document.getElementById('modal_progress_bar').style.width = pct + '%';
    document.getElementById('modal_percentage_text').textContent = pct + '%';
    document.getElementById('modal_counter_text').textContent = `Updated ${updated.toLocaleString('en-IN')} of ${total.toLocaleString('en-IN')} products`;
}

function closeSyncModal() {
    document.getElementById('sync_modal_backdrop').style.display = 'none';
    loadProducts(currentPage);
}

// Filter Tabs Handling
document.querySelectorAll('#status_tabs .shadcn-tab-item').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('#status_tabs .shadcn-tab-item').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currentFilter = this.getAttribute('data-filter');
        loadProducts(1);
    });
});

// Search input handling
searchInput.addEventListener('input', function() {
    searchClearBtn.style.display = this.value.length > 0 ? 'inline-block' : 'none';
});

searchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        loadProducts(1);
    }
});

searchClearBtn.addEventListener('click', function() {
    searchInput.value = '';
    searchClearBtn.style.display = 'none';
    loadProducts(1);
});

categorySelect.addEventListener('change', () => loadProducts(1));
limitSelect.addEventListener('change', () => loadProducts(1));
applyFilterBtn.addEventListener('click', () => loadProducts(1));
refreshScanBtn.addEventListener('click', () => loadProducts(currentPage));

// Copy SKU helper
function copySku(sku) {
    navigator.clipboard.writeText(sku).then(() => {
        // Subtle toast or visual hint
        const el = event.target;
        el.className = 'fa-solid fa-check copy-sku-btn';
        el.style.color = '#10b981';
        setTimeout(() => {
            el.className = 'fa-regular fa-copy copy-sku-btn';
            el.style.color = '#6366f1';
        }, 1200);
    });
}

// Auto-start on load
document.addEventListener('DOMContentLoaded', () => loadProducts(1));
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
