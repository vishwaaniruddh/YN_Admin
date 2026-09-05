<?php
// admin/products.php
$page_title = "Products";
require_once __DIR__ . '/config/db.php';

// 1. Handle AJAX Featured Toggle Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_featured') {
    require_once __DIR__ . '/includes/auth.php'; // Ensure authenticated
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    $is_featured = (int)($_POST['is_featured'] ?? 0);

    header('Content-Type: application/json');
    if ($product_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET is_featured = ? WHERE id = ?");
            $stmt->execute([$is_featured, $product_id]);
            echo json_encode(['success' => true]);
            exit();
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$message = '';
$message_type = 'success';

// 2. Handle Delete Product Request (Soft Delete)
if (isset($_GET['delete'])) {
    if (!current_user_can('delete_products')) {
        $message = "You do not have permission to delete products.";
        $message_type = "error";
    } else {
        $delete_id = (int)$_GET['delete'];
        try {
        // Start Transaction
        $pdo->beginTransaction();

        // Soft delete from DB
        $del_stmt = $pdo->prepare("UPDATE products SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
        $del_stmt->execute([$delete_id]);
        
        // Log activity
        log_activity($pdo, 'delete_product', 'product', $delete_id, "Soft deleted product ID $delete_id");

        $pdo->commit();

        $message = "Product successfully soft-deleted.";
        $message_type = "success";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Error deleting product: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// 3. Filters and Search Queries
$search = isset($_GET['s']) ? trim($_GET['s']) : '';
$cat_filter = isset($_GET['cat_id']) ? $_GET['cat_id'] : '';
$stock_filter = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
$featured_filter = isset($_GET['featured']) ? $_GET['featured'] : '';

// Build Query
$query_parts = [];
$params = [];

if (!empty($search)) {
    $query_parts[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($cat_filter)) {
    if ($cat_filter === 'uncategorized') {
        $query_parts[] = "(p.category_id IS NULL OR p.category_id = 0 OR NOT EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id))";
    } else {
        $query_parts[] = "(p.category_id = ? OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?))";
        $params[] = (int)$cat_filter;
        $params[] = (int)$cat_filter;
    }
}

if (!empty($stock_filter)) {
    if ($stock_filter === 'instock') {
        $query_parts[] = "p.stock_qty > 5";
    } elseif ($stock_filter === 'lowstock') {
        $query_parts[] = "p.stock_qty > 0 AND p.stock_qty <= 5";
    } elseif ($stock_filter === 'outofstock') {
        $query_parts[] = "p.stock_qty = 0";
    }
}

if ($featured_filter === '1') {
    $query_parts[] = "p.is_featured = 1";
} elseif ($featured_filter === '0') {
    $query_parts[] = "p.is_featured = 0";
}

$where_clause = 'WHERE p.deleted_at IS NULL';
if (!empty($query_parts)) {
    $where_clause .= " AND " . implode(" AND ", $query_parts);
}

try {
    // Get Categories for filter drop-down
    $categories_raw = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
    $categories = get_category_tree($categories_raw);

    // Pagination logic
    $items_per_page = 20;
    $current_page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    
    // Get total counts
    $count_sql = "SELECT COUNT(*) FROM products p $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);
    
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }
    
    $offset = ($current_page - 1) * $items_per_page;

    // Get Products
    $sql = "
        SELECT p.*, 
        c.name as category_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        $where_clause 
        ORDER BY p.created_at DESC
        LIMIT $items_per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = "error";
}
?>

<div class="dashboard-header-banner" style="margin-bottom: 20px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting">
            <h1>Products Catalog</h1>
            <span class="shadcn-badge shadcn-badge-sky" style="font-size: 11px; padding: 3px 8px;">
                <i class="fa-solid fa-boxes-stacked" style="margin-right: 4px;"></i> <?php echo number_format($total_items); ?> Items
            </span>
        </div>
        <p class="dashboard-subtitle">
            Manage your store inventory, pricing, SKUs, and category bindings.
        </p>
    </div>
    <div class="dashboard-actions">
        <a href="product-add.php" class="shadcn-btn shadcn-btn-primary">
            <i class="fa-solid fa-plus"></i> Add Product
        </a>
        <a href="import_archive.php" class="shadcn-btn shadcn-btn-outline">
            <i class="fa-solid fa-folder-tree"></i> Archive Import
        </a>
        <a href="product-import.php" class="shadcn-btn shadcn-btn-outline">
            <i class="fa-solid fa-file-csv"></i> CSV Import
        </a>

        <!-- Export Dropdown -->
        <div style="position: relative; display: inline-block;" id="export-dropdown-wrap">
            <button type="button" onclick="document.getElementById('export-menu').classList.toggle('open')" class="shadcn-btn shadcn-btn-outline">
                <i class="fa-solid fa-download"></i> Export
                <i class="fa-solid fa-chevron-down" style="font-size: 10px; margin-left: 2px;"></i>
            </button>
            <ul id="export-menu" style="display:none; position: absolute; right: 0; top: calc(100% + 6px); background: #ffffff; border: 1px solid #e4e4e7; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); list-style: none; margin: 0; padding: 6px 0; z-index: 999; min-width: 240px;">
                <li>
                    <a href="generate-yn-products-excel.php?download=1" style="display: flex; align-items: center; gap: 10px; padding: 9px 16px; text-decoration: none; color: #09090b; font-size: 13px; font-weight: 500;">
                        <i class="fa-solid fa-file-excel" style="color: #16a34a; width: 16px;"></i> YN Products Excel (Full)
                    </a>
                </li>
                <li style="border-top: 1px solid #f4f4f5;">
                    <a href="export-products.php?format=excel<?php echo !empty($cat_filter) ? '&category_id=' . (int)$cat_filter : ''; ?>" style="display: flex; align-items: center; gap: 10px; padding: 9px 16px; text-decoration: none; color: #09090b; font-size: 13px; font-weight: 500;">
                        <i class="fa-solid fa-file-excel" style="color: #059669; width: 16px;"></i> Filtered Excel (.xlsx)
                    </a>
                </li>
                <li style="border-top: 1px solid #f4f4f5;">
                    <a href="export-products.php?format=csv<?php echo !empty($cat_filter) ? '&category_id=' . (int)$cat_filter : ''; ?>" style="display: flex; align-items: center; gap: 10px; padding: 9px 16px; text-decoration: none; color: #09090b; font-size: 13px; font-weight: 500;">
                        <i class="fa-solid fa-file-csv" style="color: #0284c7; width: 16px;"></i> Filtered CSV (.csv)
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<style>
#export-menu.open { display: block !important; }
#export-menu a:hover { background-color: #f4f4f5; }
</style>
<script>
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('export-dropdown-wrap');
    if (wrap && !wrap.contains(e.target)) {
        var menu = document.getElementById('export-menu');
        if (menu) menu.classList.remove('open');
    }
});
</script>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <i class="fa-solid fa-circle-info"></i>
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<!-- Filters Card -->
<div class="shadcn-card" style="margin-bottom: 16px;">
    <div class="shadcn-card-padded" style="padding: 12px 16px;">
        <form action="products.php" method="GET" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 0;">
            <!-- Search Box -->
            <div style="position: relative; flex: 1; min-width: 220px;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #a1a1aa; font-size: 12px;"></i>
                <input type="text" name="s" value="<?php echo sanitize_html($search); ?>" placeholder="Search name, SKU, description..." class="form-control" style="padding-left: 28px; width: 100%;">
            </div>

            <!-- Categories Dropdown -->
            <div style="min-width: 160px;">
                <select name="cat_id" class="form-control" style="width: 100%;">
                    <option value="">All Categories</option>
                    <option value="uncategorized" <?php echo ($cat_filter === 'uncategorized') ? 'selected' : ''; ?>>Uncategorized</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($cat_filter == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo str_repeat('&nbsp;&nbsp;&nbsp;', isset($cat['depth']) ? $cat['depth'] : 0) . sanitize_html($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Stock Status Dropdown -->
            <div style="min-width: 140px;">
                <select name="stock_status" class="form-control" style="width: 100%;">
                    <option value="">All Stock Status</option>
                    <option value="instock" <?php echo ($stock_filter == 'instock') ? 'selected' : ''; ?>>In Stock (&gt; 5)</option>
                    <option value="lowstock" <?php echo ($stock_filter == 'lowstock') ? 'selected' : ''; ?>>Low Stock (1–5)</option>
                    <option value="outofstock" <?php echo ($stock_filter == 'outofstock') ? 'selected' : ''; ?>>Out of Stock (0)</option>
                </select>
            </div>

            <!-- Featured Status Dropdown -->
            <div style="min-width: 120px;">
                <select name="featured" class="form-control" style="width: 100%;">
                    <option value="">All Items</option>
                    <option value="1" <?php echo ($featured_filter === '1') ? 'selected' : ''; ?>>Starred Only</option>
                    <option value="0" <?php echo ($featured_filter === '0') ? 'selected' : ''; ?>>Unstarred Only</option>
                </select>
            </div>

            <button type="submit" class="shadcn-btn shadcn-btn-primary">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <?php if (!empty($search) || !empty($cat_filter) || !empty($stock_filter) || $featured_filter !== ''): ?>
                <a href="products.php" class="shadcn-btn shadcn-btn-ghost" title="Clear Filters">
                    <i class="fa-solid fa-xmark"></i> Reset
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Products Table Card -->
<div class="shadcn-card">
    <div class="shadcn-card-header">
        <div style="font-size: 12.5px; color: #52525b;">
            <?php
            $range_start = min(($current_page - 1) * $items_per_page + 1, $total_items);
            $range_end   = min($current_page * $items_per_page, $total_items);
            if ($total_items === 0):
            ?>
                <span>No products found matching query.</span>
            <?php else: ?>
                <span>
                    Showing <strong><?php echo number_format($range_start); ?>–<?php echo number_format($range_end); ?></strong> of <strong><?php echo number_format($total_items); ?></strong> products
                    <?php if (!empty($search)): ?>
                        &nbsp;<span class="shadcn-badge shadcn-badge-sky">"<?php echo sanitize_html($search); ?>"</span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </div>
        <div style="font-size: 11.5px; color: #71717a;">
            Page <?php echo $current_page; ?> of <?php echo max(1, $total_pages); ?>
        </div>
    </div>

    <div class="shadcn-card-body" style="overflow-x: auto;">
        <table class="shadcn-table">
            <thead>
                <tr>
                    <th style="width: 50px;">Image</th>
                    <th>Product Title & SKU</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Inventory</th>
                    <th style="width: 60px; text-align: center;"><i class="fa-solid fa-star" title="Featured"></i></th>
                    <th style="width: 110px;">Added On</th>
                    <th style="width: 70px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #71717a; padding: 40px;">
                            <i class="fa-solid fa-boxes-stacked" style="font-size: 24px; display: block; margin-bottom: 8px; opacity: 0.4;"></i>
                            No products found matching filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $prod): ?>
                        <tr>
                            <td>
                                <div style="width: 36px; height: 46px; border-radius: 4px; overflow: hidden; position: relative; background: #f4f4f5; border: 1px solid #e4e4e7;">
                                    <?php if ($prod['main_image']): ?>
                                        <img src="<?php echo sanitize_html($prod['main_image']); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; display: block;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: #a1a1aa;">
                                            <i class="fa-solid fa-gem" style="font-size: 12px;"></i>
                                        </div>
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #a1a1aa;">
                                            <i class="fa-solid fa-gem" style="font-size: 12px;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <a href="product-edit.php?id=<?php echo $prod['id']; ?>" style="font-weight: 500; color: #09090b; text-decoration: none; font-size: 13px; line-height: 1.35; display: inline-block;">
                                        <?php echo sanitize_html($prod['name']); ?>
                                    </a>
                                    <?php if ($prod['status'] === 'draft'): ?>
                                        <span class="shadcn-badge" style="background: #f4f4f5; color: #71717a; margin-left: 6px;">Draft</span>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-top: 3px;">
                                    <span style="font-size: 11px; font-family: monospace; color: #71717a; background: #f4f4f5; padding: 1px 5px; border-radius: 3px; border: 1px solid #e4e4e7;">
                                        <?php echo sanitize_html($prod['sku'] ?: 'N/A'); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 11px; color: #52525b; background: #f4f4f5; padding: 2px 7px; border-radius: 4px; border: 1px solid #e4e4e7; white-space: nowrap; display: inline-block;">
                                    <?php echo sanitize_html($prod['category_name'] ?: 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($prod['sale_price']): ?>
                                    <span style="text-decoration: line-through; color: #a1a1aa; font-size: 11px;">₹<?php echo number_format($prod['price'], 2); ?></span>
                                    <div style="color: #dc2626; font-weight: 600; font-size: 13px;">₹<?php echo number_format($prod['sale_price'], 2); ?></div>
                                <?php else: ?>
                                    <span style="color: #09090b; font-weight: 600; font-size: 13px;">₹<?php echo number_format($prod['price'], 2); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($prod['stock_qty'] <= 0): ?>
                                    <span class="stock-pill-out"><i class="fa-solid fa-circle-xmark"></i> Out of stock</span>
                                <?php elseif ($prod['stock_qty'] <= 5): ?>
                                    <span class="stock-pill-low"><i class="fa-solid fa-triangle-exclamation"></i> Low: <?php echo $prod['stock_qty']; ?></span>
                                <?php else: ?>
                                    <span class="stock-pill-in"><i class="fa-solid fa-check"></i> In Stock (<?php echo $prod['stock_qty']; ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($prod['is_featured']): ?>
                                    <span class="star-icon featured ajax-toggle" data-product-id="<?php echo $prod['id']; ?>" style="color: #f59e0b; cursor: pointer; font-size: 15px;" title="Click to unstar">&#9733;</span>
                                <?php else: ?>
                                    <span class="star-icon not-featured ajax-toggle" data-product-id="<?php echo $prod['id']; ?>" style="color: #d4d4d8; cursor: pointer; font-size: 15px;" title="Click to star">&#9734;</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 11.5px; color: #71717a; white-space: nowrap;">
                                <?php echo date('M d, Y', strtotime($prod['created_at'])); ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <div style="display: inline-flex; align-items: center; gap: 3px;">
                                    <a href="product-edit.php?id=<?php echo $prod['id']; ?>" class="shadcn-btn-ghost" style="width: 26px; height: 26px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; color: #52525b;" title="Edit Product">
                                        <i class="fa-solid fa-pen-to-square" style="font-size: 11px;"></i>
                                    </a>
                                    <?php if (current_user_can('delete_products')): ?>
                                        <a href="products.php?delete=<?php echo $prod['id']; ?>" class="shadcn-btn-ghost delete-confirm" data-name="<?php echo sanitize_html($prod['name']); ?>" style="width: 26px; height: 26px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; color: #ef4444;" title="Delete Product">
                                            <i class="fa-solid fa-trash-can" style="font-size: 11px;"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination Controls -->
<?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; display: flex; justify-content: center; gap: 6px;">
        <?php
        $query_params = $_GET;
        unset($query_params['p']);
        $q = http_build_query($query_params);
        $base_url = 'products.php?' . ($q ? $q . '&' : '') . 'p=';
        
        // Previous button
        if ($current_page > 1) {
            echo '<a href="' . $base_url . ($current_page - 1) . '" class="shadcn-btn shadcn-btn-outline" style="height: 34px; padding: 0 12px;"><i class="fa-solid fa-chevron-left" style="font-size: 11px; margin-right: 4px;"></i> Prev</a>';
        }

        // Page numbers
        for ($i = max(1, $current_page - 3); $i <= min($total_pages, $current_page + 3); $i++): 
            $btnClass = ($i === $current_page) ? 'shadcn-btn-primary' : 'shadcn-btn-outline';
        ?>
            <a href="<?php echo $base_url . $i; ?>" class="shadcn-btn <?php echo $btnClass; ?>" style="height: 34px; width: 34px; padding: 0;">
                <?php echo $i; ?>
            </a>
        <?php 
        endfor; 

        // Next button
        if ($current_page < $total_pages) {
            echo '<a href="' . $base_url . ($current_page + 1) . '" class="shadcn-btn shadcn-btn-outline" style="height: 34px; padding: 0 12px;">Next <i class="fa-solid fa-chevron-right" style="font-size: 11px; margin-left: 4px;"></i></a>';
        }
        ?>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
