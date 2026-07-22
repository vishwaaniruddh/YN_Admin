<?php
// admin/index.php
$page_title = "Dashboard";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch statistics
try {
    // 1. Total products
    $total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();

    // 2. Total categories
    $total_categories = $pdo->query("SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL")->fetchColumn();

    // 3. Total Orders & Revenue
    $total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $total_revenue = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status != 'Cancelled'")->fetchColumn();

    // 4. Low stock products (e.g., <= 5)
    $low_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= 5 AND deleted_at IS NULL")->fetchColumn();

    // 5. Recent Orders
    $recent_orders_stmt = $pdo->query("
        SELECT o.*, c.first_name, c.last_name, c.email 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        ORDER BY o.id DESC 
        LIMIT 5
    ");
    $recent_orders = $recent_orders_stmt->fetchAll();

    // Fetch Items for each recent order
    foreach ($recent_orders as &$ord) {
        $item_stmt = $pdo->prepare("
            SELECT oi.*, p.name, p.main_image, p.sku 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $item_stmt->execute([$ord['id']]);
        $ord['items'] = $item_stmt->fetchAll();
    }

    // 6. Recent Products
    $recent_stmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.deleted_at IS NULL
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $recent_products = $recent_stmt->fetchAll();

    // 7. Featured products list
    $featured_stmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_featured = 1 AND p.deleted_at IS NULL
        ORDER BY p.updated_at DESC 
        LIMIT 5
    ");
    $featured_products = $featured_stmt->fetchAll();

} catch (PDOException $e) {
    echo "<div class='notice notice-error'><p>Database error: " . $e->getMessage() . "</p></div>";
}
?>

<div class="wrap-header">
    <h1>Dashboard Overview</h1>
    <div style="display: flex; gap: 10px;">
        <a href="orders.php" class="button button-primary"><i class="fa-solid fa-box-open"></i> Manage Orders</a>
        <a href="product-add.php" class="button"><i class="fa-solid fa-plus"></i> Add New Product</a>
        <a href="export-products.php" class="button"><i class="fa-solid fa-file-excel"></i> Export Catalog</a>
    </div>
</div>

<!-- Stats Grid -->
<div class="dashboard-grid">
    <!-- Orders Card -->
    <a href="orders.php" class="dash-card" style="text-decoration: none; color: inherit; display: flex;">
        <div class="dash-card-icon" style="background-color: rgba(155, 89, 182, 0.12); color: #9b59b6;">
            <i class="fa-solid fa-box-open"></i>
        </div>
        <div class="dash-card-info">
            <h3>Customer Orders</h3>
            <p><?php echo (int) $total_orders; ?></p>
        </div>
    </a>

    <!-- Revenue Card -->
    <a href="orders.php" class="dash-card" style="text-decoration: none; color: inherit; display: flex;">
        <div class="dash-card-icon" style="background-color: rgba(46, 204, 113, 0.12); color: #2ecc71;">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
        <div class="dash-card-info">
            <h3>Total Sales</h3>
            <p style="font-size: 20px;">₹<?php echo number_format($total_revenue, 2); ?></p>
        </div>
    </a>

    <!-- Products Card -->
    <a href="products.php" class="dash-card" style="text-decoration: none; color: inherit; display: flex;">
        <div class="dash-card-icon">
            <i class="fa-solid fa-shirt"></i>
        </div>
        <div class="dash-card-info">
            <h3>Total Products</h3>
            <p><?php echo (int) $total_products; ?></p>
        </div>
    </a>

    <!-- Categories Card -->
    <a href="categories.php" class="dash-card" style="text-decoration: none; color: inherit; display: flex;">
        <div class="dash-card-icon" style="background-color: rgba(0, 163, 42, 0.1); color: var(--wp-success-green);">
            <i class="fa-solid fa-folder-tree"></i>
        </div>
        <div class="dash-card-info">
            <h3>Categories</h3>
            <p><?php echo (int) $total_categories; ?></p>
        </div>
    </a>
</div>

<div class="wp-editor-columns">
    <!-- Left Column - Recent Orders & Products -->
    <div class="main-column">
        
        <!-- RECENT ORDERS POSTBOX -->
        <div class="postbox" style="margin-bottom: 25px;">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-cart-shopping" style="color: var(--wp-blue);"></i> Recent Orders Overview</h2>
                <a href="orders.php" class="button button-primary">View All Orders</a>
            </div>
            <div class="postbox-body" style="padding: 0;">
                <?php if (empty($recent_orders)): ?>
                    <p style="padding: 20px; color: #646970; text-align: center;">No orders received yet.</p>
                <?php else: ?>
                    <table class="wp-list-table" style="border: none; box-shadow: none;">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Order</th>
                                <th>Customer Name</th>
                                <th>Purchased Items</th>
                                <th style="width: 110px;">Amount</th>
                                <th style="width: 120px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $ord): ?>
                                <tr>
                                    <td>
                                        <a href="order-detail.php?id=<?php echo $ord['id']; ?>" style="font-weight: 700; color: var(--wp-blue);"><?php echo format_order_number($ord['id']); ?></a>
                                    </td>
                                    <td>
                                        <strong><?php echo sanitize_html(trim(($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? '')) ?: 'Guest Customer'); ?></strong>
                                        <div style="font-size: 11px; color: #646970;"><?php echo sanitize_html($ord['email']); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($ord['items'])): ?>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <?php if (!empty($ord['items'][0]['main_image'])): ?>
                                                    <img src="<?php echo sanitize_html($ord['items'][0]['main_image']); ?>" style="width: 30px; height: 38px; object-fit: cover; border-radius: 4px;">
                                                <?php endif; ?>
                                                <span style="font-size: 13px;">
                                                    <?php echo sanitize_html($ord['items'][0]['name']); ?>
                                                    <?php if (count($ord['items']) > 1): ?>
                                                        <span style="color: #646970; font-size: 11px;">(+<?php echo count($ord['items']) - 1; ?> more)</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #888; font-style: italic; font-size: 12px;">Order details</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color: var(--wp-success-green);">₹<?php echo number_format($ord['total_amount'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span style="
                                            font-size: 11px; 
                                            font-weight: 700; 
                                            padding: 3px 8px; 
                                            border-radius: 10px;
                                            display: inline-block;
                                            <?php 
                                                switch($ord['status']) {
                                                    case 'Delivered': echo 'background: #e6f4ea; color: #137333;'; break;
                                                    case 'Shipped': echo 'background: #e8f0fe; color: #1a73e8;'; break;
                                                    case 'Processing': echo 'background: #fef7e0; color: #b06000;'; break;
                                                    case 'Cancelled': echo 'background: #fce8e6; color: #c5221f;'; break;
                                                    default: echo 'background: #f1f3f4; color: #3c4043;';
                                                }
                                            ?>
                                        ">
                                            <?php echo sanitize_html($ord['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- RECENTLY ADDED PRODUCTS POSTBOX -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-clock-rotate-left"></i> Recently Added Products</h2>
                <a href="products.php" class="button">View All Products</a>
            </div>
            <div class="postbox-body" style="padding: 0;">
                <?php if (empty($recent_products)): ?>
                    <p style="padding: 20px; color: #646970; text-align: center;">No products found. Start by adding one!</p>
                <?php else: ?>
                    <table class="wp-list-table" style="border: none; box-shadow: none;">
                        <thead>
                            <tr>
                                <th class="column-thumbnail">Image</th>
                                <th>Name / SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_products as $prod): ?>
                                <tr>
                                    <td class="column-thumbnail">
                                        <?php if ($prod['main_image']): ?>
                                            <img src="<?php echo sanitize_html($prod['main_image']); ?>" alt="product">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background: #f0f0f1; display: flex; align-items: center; justify-content: center; border-radius: 4px; border: 1px solid var(--wp-border); color: #8c8f94;">
                                                <i class="fa-solid fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><a href="product-edit.php?id=<?php echo $prod['id']; ?>"><?php echo sanitize_html($prod['name']); ?></a></strong>
                                        <div style="font-size: 12px; color: #646970;">SKU: <?php echo sanitize_html($prod['sku']); ?></div>
                                    </td>
                                    <td><?php echo sanitize_html($prod['category_name'] ?: 'Uncategorized'); ?></td>
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Right Column - Quick Tools & Featured -->
    <div class="side-column">
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-wand-magic-sparkles"></i> Store Identity</h2>
            </div>
            <div class="postbox-body" style="line-height: 1.6;">
                <p><strong>YosshitaNeha Fashion Studio</strong> specializes in curated premium collections:</p>
                <ul style="margin: 10px 0 0 20px;">
                    <li>Bridal Wears</li>
                    <li>Bridal Jewellery</li>
                </ul>
                <div style="margin-top: 15px; border-top: 1px solid var(--wp-border); padding-top: 15px; font-size: 13px;">
                    <span style="color: #646970;"><i class="fa-solid fa-user-shield"></i> Role:</span> Admin Console
                </div>
            </div>
        </div>

        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-star" style="color: #ffb900;"></i> Featured Items</h2>
            </div>
            <div class="postbox-body" style="padding: 0;">
                <?php if (empty($featured_products)): ?>
                    <p style="padding: 15px; color: #646970; text-align: center;">No featured items. Click the star on the products list to feature one.</p>
                <?php else: ?>
                    <ul style="list-style: none;">
                        <?php foreach ($featured_products as $fprod): ?>
                            <li style="display: flex; align-items: center; padding: 10px 15px; border-bottom: 1px solid var(--wp-border);">
                                <div style="width: 40px; height: 40px; margin-right: 12px; border-radius: 4px; overflow: hidden; border: 1px solid var(--wp-border); flex-shrink: 0;">
                                    <?php if ($fprod['main_image']): ?>
                                        <img src="<?php echo sanitize_html($fprod['main_image']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: #f0f0f1; display: flex; align-items: center; justify-content: center; color: #8c8f94;">
                                            <i class="fa-solid fa-image" style="font-size: 12px;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="min-width: 0; flex: 1;">
                                    <h4 style="font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0;">
                                        <a href="product-edit.php?id=<?php echo $fprod['id']; ?>"><?php echo sanitize_html($fprod['name']); ?></a>
                                    </h4>
                                    <span style="font-size: 11px; color: #646970;"><?php echo sanitize_html($fprod['category_name'] ?: 'Uncategorized'); ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>