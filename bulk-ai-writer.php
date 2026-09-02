<?php
// admin/bulk-ai-writer.php
$page_title = 'AI Bulk Product Content Writer';

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cache.php';

if (!current_user_can('manage_products')) {
    die("You do not have permission to manage product content.");
}

$secretsFile = __DIR__ . '/config/secrets.php';
$hasApiKey = false;
if (file_exists($secretsFile)) {
    $sec = include($secretsFile);
    $hasApiKey = !empty($sec['GEMINI_API_KEY']);
}

// -------------------------------------------------------------------------
// AJAX Handler: Fetch Products for Bulk AI Processing
// -------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'load_bulk_products') {
    header('Content-Type: application/json');

    $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
    $filterType = $_GET['filter_type'] ?? 'needs_content';
    $search = trim($_GET['search'] ?? '');
    $nameFilter = trim($_GET['name_filter'] ?? '');
    $descFilter = trim($_GET['desc_filter'] ?? '');
    $skuFilter = trim($_GET['sku_filter'] ?? '');
    $limit = isset($_GET['limit']) ? min(200, max(10, (int)$_GET['limit'])) : 50;

    try {
        $where = ["p.deleted_at IS NULL"];
        $params = [];

        if ($categoryId !== null) {
            $where[] = "(p.category_id = ? OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?))";
            $params[] = $categoryId;
            $params[] = $categoryId;
        }

        // Dedicated Name Filter
        if ($nameFilter !== '') {
            if ($nameFilter === '1' || $nameFilter === '0') {
                $where[] = "(TRIM(p.name) = ? OR p.name = ?)";
                $params[] = $nameFilter;
                $params[] = $nameFilter;
            } else {
                $where[] = "p.name LIKE ?";
                $params[] = "%$nameFilter%";
            }
        }

        // Dedicated Description Filter
        if ($descFilter !== '') {
            if ($descFilter === '1' || $descFilter === '0') {
                $where[] = "(TRIM(p.description) = ? OR p.description = ? OR TRIM(p.short_description) = ?)";
                $params[] = $descFilter;
                $params[] = $descFilter;
                $params[] = $descFilter;
            } else {
                $where[] = "(p.description LIKE ? OR p.short_description LIKE ?)";
                $params[] = "%$descFilter%";
                $params[] = "%$descFilter%";
            }
        }

        // Dedicated SKU Filter
        if ($skuFilter !== '') {
            $where[] = "p.sku LIKE ?";
            $params[] = "%$skuFilter%";
        }

        // Presets
        if ($filterType === 'name_is_1') {
            $where[] = "(TRIM(p.name) = '1' OR p.name = '1')";
        } elseif ($filterType === 'desc_is_1') {
            $where[] = "(TRIM(p.description) = '1' OR p.description = '1' OR TRIM(p.short_description) = '1')";
        } elseif ($filterType === 'name_or_desc_is_1') {
            $where[] = "(TRIM(p.name) = '1' OR p.name = '1' OR TRIM(p.description) = '1' OR p.description = '1')";
        } elseif ($filterType === 'needs_content') {
            $where[] = "(TRIM(p.name) = '1' OR p.name = '1' OR p.name = p.sku OR p.name LIKE 'YN%' OR p.short_description IS NULL OR p.short_description = '' OR TRIM(p.short_description) = '1' OR p.description IS NULL OR p.description = '' OR TRIM(p.description) = '1' OR p.description LIKE '%Srisringarr%' OR p.description LIKE '%Premium Quality Collection%')";
        } elseif ($filterType === 'missing_desc') {
            $where[] = "(p.description IS NULL OR p.description = '' OR TRIM(p.description) = '1' OR p.description LIKE '%Srisringarr%' OR p.description LIKE '%Premium Quality Collection%')";
        } elseif ($filterType === 'missing_short_desc') {
            $where[] = "(p.short_description IS NULL OR p.short_description = '' OR TRIM(p.short_description) = '1' OR p.short_description = p.name)";
        }

        if (!empty($search)) {
            $where[] = "(p.sku LIKE ? OR p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $whereClause = implode(" AND ", $where);
        $sql = "
            SELECT p.id, p.sku, p.name, p.slug, p.short_description, p.description, p.main_image, p.category_id,
            (SELECT GROUP_CONCAT(c.name ORDER BY c.parent_id ASC SEPARATOR ' > ') 
             FROM product_categories pc 
             JOIN categories c ON pc.category_id = c.id 
             WHERE pc.product_id = p.id) as full_category_name
            FROM products p
            WHERE $whereClause
            ORDER BY p.id DESC
            LIMIT $limit
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch total count for summary
        $countSql = "SELECT COUNT(*) FROM products p WHERE $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'total_count' => $totalCount,
            'products' => $products
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

try {
    $categories_raw = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
    $categories = get_category_tree($categories_raw);
} catch (Exception $e) {
    $categories = [];
}
?>

<div class="wrap" style="max-width: 1400px; margin: 20px auto; padding: 0 15px;">
    
    <!-- Hero Banner -->
    <div style="background: linear-gradient(135deg, #3b0764 0%, #6b21a8 50%, #4f46e5 100%); border-radius: 16px; padding: 24px 30px; color: #ffffff; margin-bottom: 24px; box-shadow: 0 10px 25px -5px rgba(107, 33, 168, 0.35); position: relative; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-size: 24px; font-weight: 800; color: #ffffff; margin: 0; display: flex; align-items: center; gap: 12px;">
                    <i class="fa-solid fa-wand-magic-sparkles" style="color: #facc15;"></i>
                    AI Bulk Product Content Writer
                    <span style="font-size: 11px; background: rgba(255, 255, 255, 0.2); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); padding: 3px 10px; border-radius: 20px; font-weight: 700;">
                        Gemini Vision Multimodal
                    </span>
                </h1>
                <p style="font-size: 13px; color: #e9d5ff; margin: 6px 0 0 0; max-width: 750px; line-height: 1.5;">
                    Automatically examine high-resolution product photos and category context to generate SEO-rich Product Titles, Short Highlight Summaries, and Detailed Descriptions in bulk.
                </p>
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <?php if ($hasApiKey): ?>
                    <span style="background: rgba(16, 185, 129, 0.25); border: 1px solid #10b981; color: #a7f3d0; font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 8px;">
                        <i class="fa-solid fa-circle-check"></i> Gemini API Connected
                    </span>
                <?php else: ?>
                    <span style="background: rgba(239, 68, 68, 0.25); border: 1px solid #ef4444; color: #fecaca; font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 8px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> API Key Missing in secrets.php
                    </span>
                <?php endif; ?>
                <a href="products.php" class="button" style="background: rgba(255, 255, 255, 0.15); color: #ffffff; border-color: rgba(255, 255, 255, 0.3); font-weight: 600;">
                    <i class="fa-solid fa-arrow-left"></i> Products
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Control Card -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; align-items: end; margin-bottom: 15px;">
            <!-- Category Selector -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <i class="fa-solid fa-folder-tree" style="color: #7c3aed;"></i> Category Selection
                </label>
                <select id="cat_filter" class="form-control" style="width: 100%; height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; font-weight: 600;">
                    <option value="">-- All Categories --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo str_repeat('&nbsp;&nbsp;&nbsp;', isset($cat['depth']) ? $cat['depth'] : 0) . htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Content Status Filter -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <i class="fa-solid fa-filter" style="color: #7c3aed;"></i> Quality Preset
                </label>
                <select id="status_filter" class="form-control" style="width: 100%; height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px;">
                    <option value="name_or_desc_is_1">⚠️ Name or Description is '1' (Raw Imports)</option>
                    <option value="name_is_1">🎯 Exact Name is '1'</option>
                    <option value="desc_is_1">🎯 Exact Description is '1'</option>
                    <option value="needs_content" selected>⚠️ Needs AI Content (Name '1'/SKU/Missing Desc)</option>
                    <option value="missing_desc">📝 Missing Detailed Description</option>
                    <option value="missing_short_desc">📄 Missing Short Summary</option>
                    <option value="all">📋 All Products in Category</option>
                </select>
            </div>

            <!-- Batch Size -->
            <div style="max-width: 130px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    Batch Limit
                </label>
                <select id="limit_filter" class="form-control" style="width: 100%; height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px;">
                    <option value="25">25 items</option>
                    <option value="50" selected>50 items</option>
                    <option value="100">100 items</option>
                    <option value="200">200 items</option>
                </select>
            </div>

            <!-- Fetch Button -->
            <div>
                <button type="button" id="load_products_btn" class="button button-primary" style="width: 100%; height: 38px; border-radius: 8px; background: #7c3aed; border-color: #6d28d9; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fa-solid fa-arrows-rotate"></i> Load Products
                </button>
            </div>
        </div>

        <!-- Dedicated Specific Search Inputs Row -->
        <div style="padding-top: 15px; border-top: 1px solid #f1f5f9; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
            <!-- Dedicated Product Name Filter -->
            <div>
                <label style="display: block; font-size: 11px; font-weight: 700; color: #6b21a8; margin-bottom: 4px;">
                    <i class="fa-solid fa-tag"></i> Filter by Product Name / Title:
                </label>
                <div style="position: relative; display: flex;">
                    <input type="text" id="name_filter" placeholder="e.g. 1 (exact '1') or keyword..." style="width: 100%; height: 34px; border-radius: 6px; border: 1px solid #c084fc; padding: 0 50px 0 8px; font-size: 12px;">
                    <button type="button" onclick="setNameFilter('1')" style="position: absolute; right: 3px; top: 3px; bottom: 3px; padding: 0 8px; font-size: 10px; font-weight: 700; background: #f3e8ff; color: #7c3aed; border: 1px solid #d8b4fe; border-radius: 4px; cursor: pointer;">
                        = '1'
                    </button>
                </div>
            </div>

            <!-- Dedicated Description Filter -->
            <div>
                <label style="display: block; font-size: 11px; font-weight: 700; color: #6b21a8; margin-bottom: 4px;">
                    <i class="fa-solid fa-align-left"></i> Filter by Description:
                </label>
                <div style="position: relative; display: flex;">
                    <input type="text" id="desc_filter" placeholder="e.g. 1 or keyword..." style="width: 100%; height: 34px; border-radius: 6px; border: 1px solid #c084fc; padding: 0 50px 0 8px; font-size: 12px;">
                    <button type="button" onclick="setDescFilter('1')" style="position: absolute; right: 3px; top: 3px; bottom: 3px; padding: 0 8px; font-size: 10px; font-weight: 700; background: #f3e8ff; color: #7c3aed; border: 1px solid #d8b4fe; border-radius: 4px; cursor: pointer;">
                        = '1'
                    </button>
                </div>
            </div>

            <!-- Dedicated SKU Filter -->
            <div>
                <label style="display: block; font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 4px;">
                    <i class="fa-solid fa-barcode"></i> Filter by SKU Code:
                </label>
                <input type="text" id="sku_filter" placeholder="e.g. YNIT21, SET824..." style="width: 100%; height: 34px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 8px; font-size: 12px;">
            </div>
        </div>
    </div>

    <!-- Live Progress Bar Card (Hidden by default) -->
    <div id="progress_card" style="display: none; background: #ffffff; border: 1px solid #c084fc; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1); margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <div style="font-size: 14px; font-weight: 800; color: #581c87; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-spinner fa-spin" style="color: #7c3aed;"></i>
                <span id="progress_title">Gemini Vision is analyzing products...</span>
            </div>
            <div style="font-size: 12px; font-weight: 700; color: #6b21a8;" id="progress_counter">0 / 0</div>
        </div>

        <div style="width: 100%; height: 10px; background: #f3e8ff; border-radius: 10px; overflow: hidden; margin-bottom: 10px;">
            <div id="progress_bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #7c3aed, #ec4899); border-radius: 10px; transition: width 0.3s ease;"></div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748b;">
            <span id="current_task_status">Starting queue...</span>
            <button type="button" id="stop_queue_btn" class="button button-small" style="background: #fef2f2; color: #dc2626; border-color: #fca5a5; font-weight: 700;">
                <i class="fa-solid fa-circle-stop"></i> Stop Queue
            </button>
        </div>
    </div>

    <!-- Master Action Bar -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: #334155; cursor: pointer;">
                <input type="checkbox" id="select_all_cb" style="width: 17px; height: 17px; cursor: pointer;">
                <span>Select All Visible</span>
            </label>
            <span id="selected_count_badge" style="font-size: 11px; background: #f3e8ff; color: #6b21a8; padding: 2px 8px; border-radius: 12px; font-weight: 700;">0 selected</span>
            <span id="matched_total_badge" style="font-size: 11px; color: #64748b;">Found 0 products</span>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <!-- Generate for selected -->
            <button type="button" id="generate_selected_btn" disabled class="button" style="background: #7c3aed; color: #ffffff; border-color: #6d28d9; font-weight: 700; font-size: 13px; padding: 6px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; opacity: 0.5; cursor: not-allowed;">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate AI Content (<span id="btn_gen_count">0</span>)
            </button>

            <!-- Auto-generate & save directly -->
            <button type="button" id="auto_generate_save_btn" disabled class="button" style="background: #059669; color: #ffffff; border-color: #047857; font-weight: 700; font-size: 13px; padding: 6px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; opacity: 0.5; cursor: not-allowed;">
                <i class="fa-solid fa-bolt"></i> 1-Click Generate &amp; Save (<span id="btn_auto_count">0</span>)
            </button>

            <!-- Save all generated -->
            <button type="button" id="save_all_btn" disabled class="button" style="background: #1e293b; color: #ffffff; border-color: #0f172a; font-weight: 700; font-size: 13px; padding: 6px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; opacity: 0.5; cursor: not-allowed;">
                <i class="fa-solid fa-floppy-disk"></i> Save All to DB (<span id="btn_save_count">0</span>)
            </button>
        </div>
    </div>

    <!-- Product Queue Table -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="max-height: 650px; overflow-y: auto;">
            <table class="wp-list-table widefat fixed striped" style="border: none;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <th style="width: 35px; text-align: center;"></th>
                        <th style="width: 60px;">Image</th>
                        <th style="width: 110px;">SKU</th>
                        <th style="width: 280px;">Product Name (Title)</th>
                        <th style="width: 250px;">Short Description</th>
                        <th>Detailed Description &amp; Features</th>
                        <th style="width: 140px; text-align: center;">Status</th>
                        <th style="width: 90px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="product_tbody">
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 50px 20px; color: #64748b;">
                            <i class="fa-solid fa-hand-pointer" style="font-size: 28px; color: #cbd5e1; display: block; margin-bottom: 10px;"></i>
                            Select a category and click <b>"Load Products"</b> to start generating AI titles and descriptions.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
let loadedProducts = [];
let isQueueRunning = false;
let stopRequested = false;

const catFilter = document.getElementById('cat_filter');
const statusFilter = document.getElementById('status_filter');
const nameFilter = document.getElementById('name_filter');
const descFilter = document.getElementById('desc_filter');
const skuFilter = document.getElementById('sku_filter');
const limitFilter = document.getElementById('limit_filter');
const loadProductsBtn = document.getElementById('load_products_btn');
const tbody = document.getElementById('product_tbody');

const selectAllCb = document.getElementById('select_all_cb');
const selectedCountBadge = document.getElementById('selected_count_badge');
const matchedTotalBadge = document.getElementById('matched_total_badge');
const generateSelectedBtn = document.getElementById('generate_selected_btn');
const btnGenCount = document.getElementById('btn_gen_count');
const autoGenerateSaveBtn = document.getElementById('auto_generate_save_btn');
const btnAutoCount = document.getElementById('btn_auto_count');
const saveAllBtn = document.getElementById('save_all_btn');
const btnSaveCount = document.getElementById('btn_save_count');

const progressCard = document.getElementById('progress_card');
const progressTitle = document.getElementById('progress_title');
const progressCounter = document.getElementById('progress_counter');
const progressBar = document.getElementById('progress_bar');
const currentTaskStatus = document.getElementById('current_task_status');
const stopQueueBtn = document.getElementById('stop_queue_btn');

function setNameFilter(val) {
    if (nameFilter) nameFilter.value = val;
    loadProducts();
}

function setDescFilter(val) {
    if (descFilter) descFilter.value = val;
    loadProducts();
}

async function loadProducts() {
    const catId = catFilter.value;
    const filter = statusFilter.value;
    const nameVal = nameFilter ? nameFilter.value.trim() : '';
    const descVal = descFilter ? descFilter.value.trim() : '';
    const skuVal = skuFilter ? skuFilter.value.trim() : '';
    const limit = limitFilter.value;

    loadProductsBtn.disabled = true;
    loadProductsBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
    tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 40px; color: #7c3aed;"><i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block"></i> Fetching products...</td></tr>`;

    try {
        const url = `bulk-ai-writer.php?action=load_bulk_products&category_id=${encodeURIComponent(catId)}&filter_type=${encodeURIComponent(filter)}&name_filter=${encodeURIComponent(nameVal)}&desc_filter=${encodeURIComponent(descVal)}&sku_filter=${encodeURIComponent(skuVal)}&limit=${encodeURIComponent(limit)}`;
        const res = await fetch(url);
        const data = await res.json();

        loadProductsBtn.disabled = false;
        loadProductsBtn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Load Products';

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 30px; color: #dc2626;"><i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error || 'Failed to load products.'}</td></tr>`;
            return;
        }

        loadedProducts = data.products || [];
        matchedTotalBadge.textContent = `Found ${data.total_count} products (Showing ${loadedProducts.length})`;
        renderTable(loadedProducts);
    } catch (err) {
        loadProductsBtn.disabled = false;
        loadProductsBtn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Load Products';
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 30px; color: #dc2626;">Network error: ${err.message}</td></tr>`;
    }
}

function renderTable(products) {
    if (products.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 40px; color: #64748b;"><i class="fa-solid fa-box-open text-2xl mb-2 block text-slate-300"></i> No products match the selected criteria.</td></tr>`;
        updateSelectionUI();
        return;
    }

    let html = '';
    products.forEach((p) => {
        const imgUrl = p.main_image ? p.main_image : 'assets/images/placeholder.png';
        const catName = p.full_category_name || 'Uncategorized';

        html += `
            <tr id="row-${p.id}" data-id="${p.id}" style="vertical-align: top;">
                <td style="text-align: center; padding-top: 15px;">
                    <input type="checkbox" class="row-checkbox" value="${p.id}" checked style="width: 16px; height: 16px; cursor: pointer;">
                </td>
                <td style="padding-top: 12px;">
                    <img src="${imgUrl}" alt="${p.sku}" style="width: 44px; height: 55px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0;">
                </td>
                <td style="padding-top: 12px;">
                    <strong style="color: #7c3aed; font-family: monospace; font-size: 13px;">${p.sku}</strong>
                    <div style="font-size: 10px; color: #64748b; margin-top: 2px;">${catName}</div>
                </td>
                <td style="padding-top: 10px;">
                    <textarea id="name-${p.id}" class="form-control" rows="2" style="width: 100%; font-size: 12px; font-weight: 600; border-radius: 6px; border: 1px solid #cbd5e1; padding: 6px;">${p.name}</textarea>
                </td>
                <td style="padding-top: 10px;">
                    <textarea id="short-desc-${p.id}" class="form-control" rows="2" placeholder="AI short summary..." style="width: 100%; font-size: 11px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 6px;">${p.short_description || ''}</textarea>
                </td>
                <td style="padding-top: 10px;">
                    <textarea id="desc-${p.id}" class="form-control" rows="2" placeholder="AI detailed description & features..." style="width: 100%; font-size: 11px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 6px;">${p.description || ''}</textarea>
                </td>
                <td style="text-align: center; padding-top: 15px;" id="status-cell-${p.id}">
                    <span class="status-badge" style="background: #f1f5f9; color: #64748b; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                        ⏳ Pending
                    </span>
                </td>
                <td style="text-align: center; padding-top: 12px;" id="action-cell-${p.id}">
                    <button type="button" onclick="generateSingle(${p.id})" class="button button-small" style="background: #f3e8ff; color: #7c3aed; border-color: #d8b4fe; font-weight: 700; border-radius: 6px; margin-bottom: 4px; width: 100%;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> AI
                    </button>
                    <button type="button" onclick="saveSingle(${p.id})" class="button button-small" style="background: #ffffff; color: #334155; border-color: #cbd5e1; font-weight: 600; border-radius: 6px; width: 100%;">
                        <i class="fa-solid fa-floppy-disk"></i> Save
                    </button>
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
    const checked = document.querySelectorAll('.row-checkbox:checked');
    const count = checked.length;
    selectedCountBadge.textContent = `${count} selected`;
    btnGenCount.textContent = count;
    btnAutoCount.textContent = count;

    if (count > 0 && !isQueueRunning) {
        generateSelectedBtn.disabled = false;
        generateSelectedBtn.style.opacity = '1';
        generateSelectedBtn.style.cursor = 'pointer';

        autoGenerateSaveBtn.disabled = false;
        autoGenerateSaveBtn.style.opacity = '1';
        autoGenerateSaveBtn.style.cursor = 'pointer';
    } else {
        generateSelectedBtn.disabled = true;
        generateSelectedBtn.style.opacity = '0.5';
        generateSelectedBtn.style.cursor = 'not-allowed';

        autoGenerateSaveBtn.disabled = true;
        autoGenerateSaveBtn.style.opacity = '0.5';
        autoGenerateSaveBtn.style.cursor = 'not-allowed';
    }
}

selectAllCb.addEventListener('change', function() {
    const isChecked = this.checked;
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = isChecked;
    });
    updateSelectionUI();
});

// Single Product Generation
async function generateSingle(productId) {
    const statusCell = document.getElementById(`status-cell-${productId}`);
    const nameInput = document.getElementById(`name-${productId}`);
    const shortDescInput = document.getElementById(`short-desc-${productId}`);
    const descInput = document.getElementById(`desc-${productId}`);

    if (statusCell) {
        statusCell.innerHTML = `<span style="background: #fdf4ff; color: #a855f7; border: 1px solid #f0abfc; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;"><i class="fa-solid fa-spinner fa-spin"></i> Vision AI...</span>`;
    }

    try {
        const res = await fetch(`api/ai_product_api.php?action=ai_generate_full_content&product_id=${productId}`);
        const data = await res.json();

        if (data.success) {
            if (nameInput && data.name) nameInput.value = data.name;
            if (shortDescInput && data.short_description) shortDescInput.value = data.short_description;
            if (descInput && data.description) descInput.value = data.description;

            if (statusCell) {
                statusCell.innerHTML = `<span style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">🟢 Generated</span>`;
            }
            updateSaveButtonCount();
            return true;
        } else {
            if (statusCell) {
                statusCell.innerHTML = `<span style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;" title="${data.error || 'Failed'}">❌ Error</span>`;
            }
            return false;
        }
    } catch (err) {
        if (statusCell) {
            statusCell.innerHTML = `<span style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">❌ Network Error</span>`;
        }
        return false;
    }
}

// Single Product Save
async function saveSingle(productId) {
    const statusCell = document.getElementById(`status-cell-${productId}`);
    const nameInput = document.getElementById(`name-${productId}`);
    const shortDescInput = document.getElementById(`short-desc-${productId}`);
    const descInput = document.getElementById(`desc-${productId}`);

    const name = nameInput ? nameInput.value.trim() : '';
    const shortDesc = shortDescInput ? shortDescInput.value.trim() : '';
    const desc = descInput ? descInput.value.trim() : '';

    if (!name) {
        alert('Product name cannot be empty.');
        return false;
    }

    if (statusCell) {
        statusCell.innerHTML = `<span style="background: #f1f5f9; color: #64748b; padding: 3px 8px; border-radius: 12px; font-size: 11px;"><i class="fa-solid fa-spinner fa-spin"></i> Saving...</span>`;
    }

    try {
        const res = await fetch('api/ai_product_api.php?action=save_ai_product_content', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: productId,
                name: name,
                short_description: shortDesc,
                description: desc
            })
        });
        const data = await res.json();

        if (data.success) {
            if (statusCell) {
                statusCell.innerHTML = `<span style="background: #ecfdf5; color: #059669; border: 1px solid #6ee7b7; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;"><i class="fa-solid fa-check"></i> Saved to DB</span>`;
            }
            return true;
        } else {
            alert('Save failed: ' + (data.error || 'Unknown error'));
            if (statusCell) {
                statusCell.innerHTML = `<span style="background: #fef2f2; color: #dc2626; padding: 3px 8px; border-radius: 12px; font-size: 11px;">❌ Save Error</span>`;
            }
            return false;
        }
    } catch (err) {
        alert('Network error: ' + err.message);
        return false;
    }
}

function updateSaveButtonCount() {
    const generatedRows = document.querySelectorAll('#product_tbody tr');
    let count = 0;
    generatedRows.forEach(row => {
        const status = row.querySelector('.status-badge, span');
        if (status && (status.textContent.includes('Generated') || status.textContent.includes('Saved'))) {
            count++;
        }
    });
    btnSaveCount.textContent = count;
    if (count > 0 && !isQueueRunning) {
        saveAllBtn.disabled = false;
        saveAllBtn.style.opacity = '1';
        saveAllBtn.style.cursor = 'pointer';
    }
}

// Batch Queue Runner
async function runBatchQueue(autoSave = false) {
    const checked = Array.from(document.querySelectorAll('.row-checkbox:checked'));
    if (checked.length === 0) return;

    isQueueRunning = true;
    stopRequested = false;
    generateSelectedBtn.disabled = true;
    autoGenerateSaveBtn.disabled = true;

    progressCard.style.display = 'block';
    progressTitle.textContent = autoSave ? 'Gemini Vision is Generating & Auto-Saving...' : 'Gemini Vision is Generating AI Content...';

    const total = checked.length;
    let completed = 0;
    let successCount = 0;

    for (let i = 0; i < total; i++) {
        if (stopRequested) {
            currentTaskStatus.textContent = 'Queue stopped by user.';
            break;
        }

        const pId = checked[i].value;
        const row = document.getElementById(`row-${pId}`);
        const sku = row ? row.querySelector('strong').textContent : pId;

        progressCounter.textContent = `${i + 1} / ${total}`;
        progressBar.style.width = `${Math.round(((i + 1) / total) * 100)}%`;
        currentTaskStatus.textContent = `Analyzing image & category for SKU: ${sku}...`;

        const success = await generateSingle(pId);
        if (success) {
            successCount++;
            if (autoSave) {
                currentTaskStatus.textContent = `Saving SKU: ${sku} to database...`;
                await saveSingle(pId);
            }
        }

        completed++;
        // Small rate-limit delay between Gemini calls (500ms)
        await new Promise(r => setTimeout(r, 500));
    }

    isQueueRunning = false;
    currentTaskStatus.textContent = `Completed ${completed} items (${successCount} successful).`;
    updateSelectionUI();
    updateSaveButtonCount();
}

generateSelectedBtn.addEventListener('click', () => runBatchQueue(false));
autoGenerateSaveBtn.addEventListener('click', () => {
    if (confirm(`Run Gemini Vision and DIRECTLY save updated titles & descriptions to the database for ${document.querySelectorAll('.row-checkbox:checked').length} products?`)) {
        runBatchQueue(true);
    }
});

stopQueueBtn.addEventListener('click', () => {
    stopRequested = true;
    stopQueueBtn.textContent = 'Stopping...';
});

// Save All Generated
saveAllBtn.addEventListener('click', async () => {
    const rows = Array.from(document.querySelectorAll('#product_tbody tr'));
    const toSave = [];

    rows.forEach(r => {
        const pId = r.getAttribute('data-id');
        const nameInput = document.getElementById(`name-${pId}`);
        const shortDescInput = document.getElementById(`short-desc-${pId}`);
        const descInput = document.getElementById(`desc-${pId}`);

        if (pId && nameInput && nameInput.value.trim()) {
            toSave.push({
                id: pId,
                name: nameInput.value.trim(),
                short_description: shortDescInput ? shortDescInput.value.trim() : '',
                description: descInput ? descInput.value.trim() : ''
            });
        }
    });

    if (toSave.length === 0) {
        alert('No generated products found to save.');
        return;
    }

    if (!confirm(`Save all ${toSave.length} product titles and descriptions to the database?`)) {
        return;
    }

    saveAllBtn.disabled = true;
    saveAllBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Saving ${toSave.length}...`;

    let saved = 0;
    for (let item of toSave) {
        const ok = await saveSingle(item.id);
        if (ok) saved++;
    }

    saveAllBtn.disabled = false;
    saveAllBtn.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> Save All to DB (<span id="btn_save_count">${saved}</span>)`;
    alert(`Successfully saved ${saved} products to the database!`);
});

loadProductsBtn.addEventListener('click', loadProducts);
catFilter.addEventListener('change', loadProducts);
statusFilter.addEventListener('change', loadProducts);

document.addEventListener('DOMContentLoaded', loadProducts);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
