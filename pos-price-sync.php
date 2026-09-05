<?php
// admin/pos-price-sync.php
$page_title = 'POS Price Sync';

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

// POS Database Connection Helper with multi-environment fallbacks
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
            // Verify phppos_items exists
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
// AJAX API HANDLER: Load Products by Category & Cross-reference with POS
// -------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'load_category_products') {
    header('Content-Type: application/json');

    $posPdo = get_pos_pdo();
    if (!$posPdo) {
        echo json_encode([
            'success' => false,
            'message' => 'Could not connect to POS database (u464193275_srishringarr). Please check server database credentials.'
        ]);
        exit;
    }

    $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
    $filterType = $_GET['filter_type'] ?? 'all';
    $search = trim($_GET['search'] ?? '');

    try {
        // 1. Fetch YN Products
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
        $ynProducts = $stmt->fetchAll();

        if (empty($ynProducts)) {
            echo json_encode([
                'success' => true,
                'summary' => ['total' => 0, 'matched' => 0, 'mismatch' => 0, 'synced' => 0, 'not_found' => 0],
                'products' => []
            ]);
            exit;
        }

        // 2. Fetch Matching POS Items in Fast Chunks
        $skus = array_filter(array_map('trim', array_column($ynProducts, 'sku')));
        $posMap = [];

        if (!empty($skus)) {
            foreach (array_chunk($skus, 400) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $posStmt = $posPdo->prepare("SELECT item_id, name, item_number, unit_price, cost_price, quantity FROM phppos_items WHERE name IN ($placeholders) OR item_number IN ($placeholders)");
                $posStmt->execute(array_merge($chunk, $chunk));
                while ($row = $posStmt->fetch()) {
                    $keyName = strtolower(trim($row['name']));
                    $keyItem = strtolower(trim($row['item_number']));
                    if (!empty($keyName)) $posMap[$keyName] = $row;
                    if (!empty($keyItem)) $posMap[$keyItem] = $row;
                }
            }
        }

        // 3. Compare & Classify Products
        $classifiedProducts = [];
        $cntTotal = count($ynProducts);
        $cntMatched = 0;
        $cntMismatch = 0;
        $cntSynced = 0;
        $cntNotFound = 0;

        foreach ($ynProducts as $p) {
            $skuKey = strtolower(trim($p['sku']));
            $posItem = $posMap[$skuKey] ?? null;

            // Try loose matching (without hyphens or spaces) if not found directly
            if (!$posItem) {
                $cleanKey = preg_replace('/[^a-zA-Z0-9]/', '', $skuKey);
                foreach ($posMap as $mapKey => $mapItem) {
                    if (preg_replace('/[^a-zA-Z0-9]/', '', $mapKey) === $cleanKey) {
                        $posItem = $mapItem;
                        break;
                    }
                }
            }

            $currentPrice = (float)$p['price'];
            $posPrice = $posItem ? (float)$posItem['unit_price'] : null;

            $status = 'not_found';
            $diff = 0;

            if ($posItem) {
                $cntMatched++;
                $diff = $posPrice - $currentPrice;

                // Price is considered mismatch if difference is more than 0.01 (or price is 1 rs)
                if (abs($diff) > 0.01 || $currentPrice <= 1.0) {
                    $status = 'mismatch';
                    $cntMismatch++;
                } else {
                    $status = 'synced';
                    $cntSynced++;
                }
            } else {
                $cntNotFound++;
            }

            $pData = [
                'id' => (int)$p['id'],
                'sku' => $p['sku'],
                'name' => $p['name'],
                'category_name' => $p['category_name'] ?: 'Uncategorized',
                'main_image' => $p['main_image'],
                'current_price' => $currentPrice,
                'pos_price' => $posPrice,
                'pos_item_id' => $posItem ? $posItem['item_id'] : null,
                'pos_stock' => $posItem ? (int)$posItem['quantity'] : null,
                'status' => $status,
                'price_diff' => $diff
            ];

            // Apply filter
            if ($filterType === 'all' || 
                ($filterType === 'mismatch' && $status === 'mismatch') || 
                ($filterType === 'matched' && ($status === 'mismatch' || $status === 'synced')) || 
                ($filterType === 'synced' && $status === 'synced') || 
                ($filterType === 'not_found' && $status === 'not_found')) {
                $classifiedProducts[] = $pData;
            }
        }

        echo json_encode([
            'success' => true,
            'summary' => [
                'total' => $cntTotal,
                'matched' => $cntMatched,
                'mismatch' => $cntMismatch,
                'synced' => $cntSynced,
                'not_found' => $cntNotFound
            ],
            'products' => $classifiedProducts
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

        echo json_encode(['success' => true, 'message' => 'Price updated successfully.']);
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
            'message' => "Successfully updated $updatedCount product prices to match POS!"
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
?>

<div class="wrap" style="max-width: 1300px; margin: 20px auto; padding: 0 15px;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-tags" style="color: #4f46e5;"></i>
                POS Product Price Sync
                <?php if ($posConnected): ?>
                    <span style="font-size: 11px; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 3px 9px; border-radius: 20px; font-weight: 700;">
                        <i class="fa-solid fa-circle" style="font-size: 8px;"></i> POS Connected
                    </span>
                <?php else: ?>
                    <span style="font-size: 11px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 3px 9px; border-radius: 20px; font-weight: 700;">
                        <i class="fa-solid fa-circle-xmark"></i> POS DB Offline
                    </span>
                <?php endif; ?>
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
                Select a category to compare current product prices against POS unit prices and update them in 1-click.
            </p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="products.php" class="button" style="background: #f1f5f9; color: #475569; border-color: #cbd5e1; font-weight: 600;">
                <i class="fa-solid fa-arrow-left"></i> Products List
            </a>
        </div>
    </div>

    <?php if (!$posConnected): ?>
        <div style="background: #fef2f2; border: 1px solid #f87171; color: #991b1b; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
            <div style="font-weight: 700; margin-bottom: 5px;"><i class="fa-solid fa-triangle-exclamation"></i> Cannot Connect to POS Database</div>
            Could not find or connect to database <code>u464193275_srishringarr</code>. Please ensure the POS database is imported or accessible.
        </div>
    <?php endif; ?>

    <!-- Filter Control Card -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; align-items: end;">
            <!-- Category Selector -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <i class="fa-solid fa-folder-tree" style="color: #4f46e5;"></i> Select Category
                </label>
                <select id="category_select" class="form-control" style="width: 100%; height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; font-weight: 600;">
                    <option value="">-- All Categories --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo str_repeat('&nbsp;&nbsp;&nbsp;', isset($cat['depth']) ? $cat['depth'] : 0) . htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Match Status Filter -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <i class="fa-solid fa-filter" style="color: #4f46e5;"></i> Match Status Filter
                </label>
                <select id="filter_type_select" class="form-control" style="width: 100%; height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px;">
                    <option value="mismatch" selected>⚠️ Price Mismatch Only (e.g. ₹1 vs POS)</option>
                    <option value="matched">🟢 All Matched in POS (Mismatch + Synced)</option>
                    <option value="synced">✅ Already Synced to POS</option>
                    <option value="not_found">❓ Missing in POS</option>
                    <option value="all">📋 Show All Products</option>
                </select>
            </div>

            <!-- Search SKU / Name -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <i class="fa-solid fa-magnifying-glass" style="color: #4f46e5;"></i> Search SKU / Name
                </label>
                <input type="text" id="search_input" placeholder="e.g. YNIT21, SET824..." style="width: 100%; height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 0 10px; font-size: 13px;">
            </div>

            <!-- Fetch / Refresh Button -->
            <div>
                <button type="button" id="fetch_btn" class="button button-primary" style="width: 100%; height: 38px; border-radius: 8px; background: #4f46e5; border-color: #4338ca; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fa-solid fa-rotate"></i> Load &amp; Compare
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Metrics Cards -->
    <div id="metrics_container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Total in Selection</div>
            <div id="stat_total" style="font-size: 24px; font-weight: 800; color: #1e293b; margin-top: 4px;">0</div>
        </div>

        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 15px;">
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #dc2626; letter-spacing: 0.5px; display: flex; justify-content: space-between;">
                <span>Price Mismatch</span>
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div id="stat_mismatch" style="font-size: 24px; font-weight: 800; color: #dc2626; margin-top: 4px;">0</div>
            <div style="font-size: 10px; color: #ef4444; margin-top: 2px;">Differs from POS price (e.g. ₹1)</div>
        </div>

        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 15px;">
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #059669; letter-spacing: 0.5px; display: flex; justify-content: space-between;">
                <span>Already In Sync</span>
                <i class="fa-solid fa-check-double"></i>
            </div>
            <div id="stat_synced" style="font-size: 24px; font-weight: 800; color: #059669; margin-top: 4px;">0</div>
            <div style="font-size: 10px; color: #10b981; margin-top: 2px;">YN Price matches POS</div>
        </div>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px;">
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; display: flex; justify-content: space-between;">
                <span>Missing in POS</span>
                <i class="fa-solid fa-circle-question"></i>
            </div>
            <div id="stat_not_found" style="font-size: 24px; font-weight: 800; color: #64748b; margin-top: 4px;">0</div>
            <div style="font-size: 10px; color: #94a3b8; margin-top: 2px;">No matching SKU in POS DB</div>
        </div>
    </div>

    <!-- Master Action Controls -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: gap; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: #334155; cursor: pointer;">
                <input type="checkbox" id="select_all_checkbox" style="width: 17px; height: 17px; cursor: pointer;">
                <span>Select All Visible</span>
            </label>
            <span id="selected_count_badge" style="font-size: 11px; background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 12px; font-weight: 700;">0 selected</span>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <button type="button" id="sync_selected_btn" disabled class="button" style="background: #4f46e5; color: #ffffff; border-color: #4338ca; font-weight: 700; font-size: 13px; padding: 6px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; opacity: 0.5; cursor: not-allowed;">
                <i class="fa-solid fa-bolt"></i> Sync Selected (<span id="btn_sel_count">0</span>)
            </button>

            <button type="button" id="sync_all_matched_btn" disabled class="button" style="background: #16a34a; color: #ffffff; border-color: #15803d; font-weight: 700; font-size: 13px; padding: 6px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; opacity: 0.5; cursor: not-allowed;">
                <i class="fa-solid fa-rocket"></i> Sync ALL Matched (<span id="btn_matched_count">0</span>)
            </button>
        </div>
    </div>

    <!-- Product Table Card -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="max-height: 600px; overflow-y: auto;">
            <table class="wp-list-table widefat fixed striped" style="border: none;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <th style="width: 40px; text-align: center;"></th>
                        <th style="width: 70px;">Image</th>
                        <th style="width: 140px;">SKU</th>
                        <th>Product Name &amp; Category</th>
                        <th style="width: 130px; text-align: right;">Current YN Price</th>
                        <th style="width: 140px; text-align: right;">POS Unit Price</th>
                        <th style="width: 160px; text-align: center;">Status / Diff</th>
                        <th style="width: 120px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="product_tbody">
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 50px 20px; color: #64748b;">
                            <i class="fa-solid fa-hand-pointer" style="font-size: 28px; color: #94a3b8; display: block; margin-bottom: 10px;"></i>
                            Select a category or click <b>"Load &amp; Compare"</b> to scan products against POS.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let loadedProducts = [];

const categorySelect = document.getElementById('category_select');
const filterTypeSelect = document.getElementById('filter_type_select');
const searchInput = document.getElementById('search_input');
const fetchBtn = document.getElementById('fetch_btn');
const tbody = document.getElementById('product_tbody');

const statTotal = document.getElementById('stat_total');
const statMismatch = document.getElementById('stat_mismatch');
const statSynced = document.getElementById('stat_synced');
const statNotFound = document.getElementById('stat_not_found');

const selectAllCheckbox = document.getElementById('select_all_checkbox');
const selectedCountBadge = document.getElementById('selected_count_badge');
const syncSelectedBtn = document.getElementById('sync_selected_btn');
const btnSelCount = document.getElementById('btn_sel_count');
const syncAllMatchedBtn = document.getElementById('sync_all_matched_btn');
const btnMatchedCount = document.getElementById('btn_matched_count');

async function loadProducts() {
    const catId = categorySelect.value;
    const filter = filterTypeSelect.value;
    const search = searchInput.value;

    fetchBtn.disabled = true;
    fetchBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
    tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 40px; color: #4f46e5;"><i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block"></i> Comparing products against POS database...</td></tr>`;

    try {
        const url = `pos-price-sync.php?action=load_category_products&category_id=${encodeURIComponent(catId)}&filter_type=${encodeURIComponent(filter)}&search=${encodeURIComponent(search)}`;
        const res = await fetch(url);
        const data = await res.json();

        fetchBtn.disabled = false;
        fetchBtn.innerHTML = '<i class="fa-solid fa-rotate"></i> Load &amp; Compare';

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 30px; color: #dc2626;"><i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.message}</td></tr>`;
            return;
        }

        statTotal.textContent = data.summary.total;
        statMismatch.textContent = data.summary.mismatch;
        statSynced.textContent = data.summary.synced;
        statNotFound.textContent = data.summary.not_found;

        loadedProducts = data.products || [];
        renderTable(loadedProducts);

        // Update All Matched Button
        const totalMatched = data.summary.mismatch;
        btnMatchedCount.textContent = totalMatched;
        if (totalMatched > 0) {
            syncAllMatchedBtn.disabled = false;
            syncAllMatchedBtn.style.opacity = '1';
            syncAllMatchedBtn.style.cursor = 'pointer';
        } else {
            syncAllMatchedBtn.disabled = true;
            syncAllMatchedBtn.style.opacity = '0.5';
            syncAllMatchedBtn.style.cursor = 'not-allowed';
        }

    } catch (err) {
        fetchBtn.disabled = false;
        fetchBtn.innerHTML = '<i class="fa-solid fa-rotate"></i> Load &amp; Compare';
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 30px; color: #dc2626;">Network error: ${err.message}</td></tr>`;
    }
}

function renderTable(products) {
    if (products.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 40px; color: #64748b;"><i class="fa-solid fa-box-open text-2xl mb-2 block text-slate-300"></i> No products match the selected filters.</td></tr>`;
        updateSelectionUI();
        return;
    }

    let html = '';
    products.forEach((p, idx) => {
        const canSync = (p.pos_price !== null && p.status === 'mismatch');
        const imgUrl = p.main_image ? p.main_image : 'assets/images/placeholder.png';

        let statusBadge = '';
        if (p.status === 'mismatch') {
            statusBadge = `<span style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">⚠️ Differs by ₹${Math.abs(p.price_diff).toLocaleString('en-IN')}</span>`;
        } else if (p.status === 'synced') {
            statusBadge = `<span style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">✅ In Sync</span>`;
        } else {
            statusBadge = `<span style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">❓ Not in POS</span>`;
        }

        html += `
            <tr id="row-${p.id}" data-id="${p.id}" data-posprice="${p.pos_price || 0}" style="vertical-align: middle;">
                <td style="text-align: center;">
                    ${canSync ? `<input type="checkbox" class="row-checkbox" value="${p.id}" style="width: 16px; height: 16px; cursor: pointer;">` : `<input type="checkbox" disabled style="opacity: 0.3;">`}
                </td>
                <td>
                    <div style="width: 40px; height: 40px; border-radius: 6px; overflow: hidden; border: 1px solid #e4e4e7; background: #f4f4f5; display: flex; align-items: center; justify-content: center;">
                        <img src="${imgUrl}" alt="" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: #a1a1aa;">
                            <i class="fa-solid fa-gem" style="font-size: 13px;"></i>
                        </div>
                    </div>
                </td>
                <td>
                    <strong style="color: #4f46e5; font-family: monospace; font-size: 12.5px; font-weight: 500;">${p.sku}</strong>
                </td>
                <td>
                    <div style="font-weight: 500; color: #09090b; font-size: 13px;">${p.name}</div>
                    <div style="font-size: 11px; color: #71717a; margin-top: 2px;"><i class="fa-solid fa-folder" style="color: #a1a1aa;"></i> ${p.category_name}</div>
                </td>
                <td style="text-align: right; font-family: monospace; font-size: 13px; font-weight: 600; color: ${p.current_price <= 1.0 ? '#dc2626' : '#09090b'};">
                    ₹${p.current_price.toLocaleString('en-IN', {minimumFractionDigits: 2})}
                </td>
                <td style="text-align: right; font-family: monospace; font-size: 13px; font-weight: 600; color: #059669;">
                    ${p.pos_price !== null ? '₹' + p.pos_price.toLocaleString('en-IN', {minimumFractionDigits: 2}) : '<span style="color:#94a3b8; font-weight:normal;">N/A</span>'}
                </td>
                <td style="text-align: center;">
                    ${statusBadge}
                </td>
                <td style="text-align: center;" id="action-cell-${p.id}">
                    ${canSync ? `
                        <button type="button" onclick="syncSingle(${p.id}, ${p.pos_price})" class="button button-small" style="background: #4f46e5; color: #fff; border-color: #4338ca; font-weight: 600; border-radius: 6px;">
                            <i class="fa-solid fa-check"></i> Sync
                        </button>
                    ` : `<span style="color:#cbd5e1; font-size: 11px;">-</span>`}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    updateSelectionUI();

    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectionUI);
    });
}

function updateSelectionUI() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const count = checkboxes.length;
    selectedCountBadge.textContent = `${count} selected`;
    btnSelCount.textContent = count;

    if (count > 0) {
        syncSelectedBtn.disabled = false;
        syncSelectedBtn.style.opacity = '1';
        syncSelectedBtn.style.cursor = 'pointer';
    } else {
        syncSelectedBtn.disabled = true;
        syncSelectedBtn.style.opacity = '0.5';
        syncSelectedBtn.style.cursor = 'not-allowed';
    }
}

selectAllCheckbox.addEventListener('change', function() {
    const isChecked = this.checked;
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = isChecked;
    });
    updateSelectionUI();
});

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
            if (actionCell) actionCell.innerHTML = `<span style="color: #059669; font-weight: 700; font-size: 12px;"><i class="fa-solid fa-circle-check"></i> Synced</span>`;
            // Update row styling
            const row = document.getElementById(`row-${productId}`);
            if (row) {
                const priceCell = row.children[4];
                if (priceCell) priceCell.textContent = '₹' + Number(newPrice).toLocaleString('en-IN', {minimumFractionDigits: 2});
                const statusCell = row.children[6];
                if (statusCell) statusCell.innerHTML = `<span style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">✅ In Sync</span>`;
                const cb = row.querySelector('.row-checkbox');
                if (cb) cb.remove();
            }
        } else {
            alert('Failed: ' + data.message);
            if (actionCell) actionCell.innerHTML = `<button type="button" onclick="syncSingle(${productId}, ${newPrice})" class="button button-small">Retry</button>`;
        }
    } catch (err) {
        alert('Network error: ' + err.message);
    }
}

// Batch Sync Selected
syncSelectedBtn.addEventListener('click', async function() {
    const checked = Array.from(document.querySelectorAll('.row-checkbox:checked'));
    if (checked.length === 0) return;

    if (!confirm(`Are you sure you want to update ${checked.length} product(s) to their POS Unit Price?`)) {
        return;
    }

    const items = checked.map(cb => {
        const row = document.getElementById(`row-${cb.value}`);
        const posPrice = parseFloat(row.getAttribute('data-posprice'));
        return { id: cb.value, price: posPrice };
    });

    syncSelectedBtn.disabled = true;
    syncSelectedBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Syncing ${items.length}...`;

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
            loadProducts(); // Refresh list
        } else {
            alert('Error: ' + data.message);
            syncSelectedBtn.disabled = false;
            syncSelectedBtn.innerHTML = `<i class="fa-solid fa-bolt"></i> Sync Selected (<span id="btn_sel_count">${checked.length}</span>)`;
        }
    } catch (err) {
        alert('Network Error: ' + err.message);
        syncSelectedBtn.disabled = false;
        syncSelectedBtn.innerHTML = `<i class="fa-solid fa-bolt"></i> Sync Selected (<span id="btn_sel_count">${checked.length}</span>)`;
    }
});

// Batch Sync ALL Matched in Selection
syncAllMatchedBtn.addEventListener('click', async function() {
    const mismatchItems = loadedProducts.filter(p => p.status === 'mismatch' && p.pos_price > 0);
    if (mismatchItems.length === 0) {
        alert('No mismatched items found to sync.');
        return;
    }

    if (!confirm(`Are you sure you want to update all ${mismatchItems.length} matched products in this category to their POS Unit Price?`)) {
        return;
    }

    syncAllMatchedBtn.disabled = true;
    syncAllMatchedBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Syncing All ${mismatchItems.length}...`;

    const items = mismatchItems.map(p => ({ id: p.id, price: p.pos_price }));

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
            loadProducts(); // Refresh list
        } else {
            alert('Error: ' + data.message);
            syncAllMatchedBtn.disabled = false;
            syncAllMatchedBtn.innerHTML = `<i class="fa-solid fa-rocket"></i> Sync ALL Matched (<span id="btn_matched_count">${mismatchItems.length}</span>)`;
        }
    } catch (err) {
        alert('Network Error: ' + err.message);
        syncAllMatchedBtn.disabled = false;
        syncAllMatchedBtn.innerHTML = `<i class="fa-solid fa-rocket"></i> Sync ALL Matched (<span id="btn_matched_count">${mismatchItems.length}</span>)`;
    }
});

fetchBtn.addEventListener('click', loadProducts);
categorySelect.addEventListener('change', loadProducts);
filterTypeSelect.addEventListener('change', loadProducts);

// Auto-load on page start
document.addEventListener('DOMContentLoaded', loadProducts);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
