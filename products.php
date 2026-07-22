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

<div class="wrap-header">
    <h1>Products</h1>
    <div style="display: flex; gap: 10px;">
        <a href="product-add.php" class="button button-primary"><i class="fa-solid fa-plus"></i> Add New</a>
        <a href="product-import.php" class="button button-secondary"><i class="fa-solid fa-file-csv"></i> Bulk Import</a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<!-- Filters Form -->
<div class="tablenav">
    <form action="products.php" method="GET" class="alignleft">
        <!-- Search Box -->
        <input type="text" name="s" value="<?php echo sanitize_html($search); ?>" placeholder="Search products..." class="form-control" style="width: 180px; padding: 4px 8px;">

        <!-- Categories Dropdown -->
        <select name="cat_id" class="form-control" style="width: 150px; padding: 4px 8px;">
            <option value="">All Categories</option>
            <option value="uncategorized" <?php echo ($cat_filter === 'uncategorized') ? 'selected' : ''; ?>>Uncategorized</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo ($cat_filter == $cat['id']) ? 'selected' : ''; ?>>
                    <?php echo str_repeat('&nbsp;&nbsp;&nbsp;', isset($cat['depth']) ? $cat['depth'] : 0) . sanitize_html($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Stock Status Dropdown -->
        <select name="stock_status" class="form-control" style="width: 140px; padding: 4px 8px;">
            <option value="">All Stock Status</option>
            <option value="instock" <?php echo ($stock_filter == 'instock') ? 'selected' : ''; ?>>In Stock</option>
            <option value="lowstock" <?php echo ($stock_filter == 'lowstock') ? 'selected' : ''; ?>>Low Stock</option>
            <option value="outofstock" <?php echo ($stock_filter == 'outofstock') ? 'selected' : ''; ?>>Out of Stock</option>
        </select>

        <!-- Featured Status Dropdown -->
        <select name="featured" class="form-control" style="width: 120px; padding: 4px 8px;">
            <option value="">All Items</option>
            <option value="1" <?php echo ($featured_filter === '1') ? 'selected' : ''; ?>>Featured</option>
            <option value="0" <?php echo ($featured_filter === '0') ? 'selected' : ''; ?>>Not Featured</option>
        </select>

        <button type="submit" class="button">Filter</button>
        <?php if (!empty($search) || !empty($cat_filter) || !empty($stock_filter) || $featured_filter !== ''): ?>
            <a href="products.php" class="button" title="Clear Filters"><i class="fa-solid fa-xmark"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Products Table -->
<table class="wp-list-table">
    <thead>
        <tr>
            <th class="column-thumbnail">Image</th>
            <th>Name</th>
            <th>SKU</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th style="width: 80px; text-align: center;"><i class="fa-solid fa-star"></i></th>
            <th>Date Added</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($products)): ?>
            <tr>
                <td colspan="8" style="text-align: center; color: #646970; padding: 25px;">No products found matching filters.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($products as $prod): ?>
                <tr>
                    <td class="column-thumbnail">
                        <?php if ($prod['main_image']): ?>
                            <!-- Path points relative to server root since we stored it relative -->
                            <img src="<?php echo sanitize_html($prod['main_image']); ?>" alt="Product">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; background: #f0f0f1; display: flex; align-items: center; justify-content: center; border-radius: 4px; border: 1px solid var(--wp-border); color: #8c8f94;">
                                <i class="fa-solid fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><a href="product-edit.php?id=<?php echo $prod['id']; ?>" style="font-size: 14px;"><?php echo sanitize_html($prod['name']); ?></a></strong>
                        <?php if ($prod['status'] === 'draft'): ?>
                            <span style="font-style: italic; color: #8c8f94; font-size: 12px; margin-left: 5px;">— Draft</span>
                        <?php endif; ?>
                        
                        <div class="column-actions">
                            <a href="product-edit.php?id=<?php echo $prod['id']; ?>">Edit Details</a> 
                            <?php if (current_user_can('delete_products')): ?>
                            | <a href="products.php?delete=<?php echo $prod['id']; ?>" class="delete delete-confirm" data-name="<?php echo sanitize_html($prod['name']); ?>">Delete</a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo sanitize_html($prod['sku']); ?></td>
                    <td><?php echo sanitize_html($prod['category_name'] ?: '—'); ?></td>
                    <td>
                        <?php if ($prod['sale_price']): ?>
                            <span style="text-decoration: line-through; color: #8c8f94; font-size: 12px;">₹<?php echo number_format($prod['price'], 2); ?></span><br>
                            <strong style="color: var(--wp-error-red);">₹<?php echo number_format($prod['sale_price'], 2); ?></strong>
                        <?php else: ?>
                            <strong>₹<?php echo number_format($prod['price'], 2); ?></strong>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($prod['stock_qty'] <= 0): ?>
                            <span class="badge badge-danger">Out of stock</span>
                        <?php elseif ($prod['stock_qty'] <= 5): ?>
                            <span class="badge badge-warning">Low Stock (<?php echo $prod['stock_qty']; ?>)</span>
                        <?php else: ?>
                            <span class="badge badge-success">In Stock (<?php echo $prod['stock_qty']; ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if ($prod['is_featured']): ?>
                            <span class="star-icon featured ajax-toggle" data-product-id="<?php echo $prod['id']; ?>">&#9733;</span>
                        <?php else: ?>
                            <span class="star-icon not-featured ajax-toggle" data-product-id="<?php echo $prod['id']; ?>">&#9734;</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 12px; color: #646970;">
                        <?php echo date('Y/m/d H:i', strtotime($prod['created_at'])); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($total_pages > 1): ?>
    <div class="pagination" style="margin-top: 20px; display: flex; gap: 5px;">
        <?php
        $query_params = $_GET;
        unset($query_params['p']);
        $q = http_build_query($query_params);
        $base_url = 'products.php?' . ($q ? $q . '&' : '') . 'p=';
        
        // Show previous button
        if ($current_page > 1) {
            echo '<a href="' . $base_url . ($current_page - 1) . '" class="button button-secondary">&laquo; Prev</a>';
        }

        // Show page numbers
        for ($i = max(1, $current_page - 3); $i <= min($total_pages, $current_page + 3); $i++): 
        ?>
            <a href="<?php echo $base_url . $i; ?>" class="button <?php echo ($i === $current_page) ? 'button-primary' : 'button-secondary'; ?>">
                <?php echo $i; ?>
            </a>
        <?php 
        endfor; 

        // Show next button
        if ($current_page < $total_pages) {
            echo '<a href="' . $base_url . ($current_page + 1) . '" class="button button-secondary">Next &raquo;</a>';
        }
        ?>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
