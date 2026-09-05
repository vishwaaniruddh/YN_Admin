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

<div class="dashboard-header-banner">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting">
            <h1>Dashboard</h1>
            <span class="shadcn-tag-live"><span class="pulse-dot"></span> Live Store</span>
        </div>
        <p class="dashboard-subtitle">
            Welcome back, <strong><?php echo sanitize_html($_SESSION['admin_name'] ?? $_SESSION['username'] ?? 'Admin'); ?></strong>. Here is the operational performance of YosshitaNeha Fashion Studio.
        </p>
    </div>
    <div class="dashboard-actions">
        <a href="orders.php" class="shadcn-btn shadcn-btn-primary"><i class="fa-solid fa-box-open"></i> Manage Orders</a>
        <a href="product-add.php" class="shadcn-btn shadcn-btn-outline"><i class="fa-solid fa-plus"></i> Add Product</a>
        <a href="export-products.php" class="shadcn-btn shadcn-btn-ghost"><i class="fa-solid fa-file-excel"></i> Export Catalog</a>
    </div>
</div>

<!-- ShadCN Stat Cards Grid -->
<div class="shadcn-stat-grid">
    <!-- Revenue Card -->
    <a href="orders.php" class="shadcn-stat-card">
        <div class="shadcn-stat-top">
            <span class="shadcn-stat-title">Total Revenue</span>
            <div class="shadcn-stat-icon-wrap" style="background: rgba(16, 185, 129, 0.12); color: #059669;">
                <i class="fa-solid fa-indian-rupee-sign"></i>
            </div>
        </div>
        <div>
            <div class="shadcn-stat-value">₹<?php echo number_format($total_revenue, 2); ?></div>
            <div class="shadcn-stat-note" style="color: #059669; font-weight: 500;">
                <i class="fa-solid fa-arrow-trend-up"></i> Confirmed store orders
            </div>
        </div>
    </a>

    <!-- Orders Card -->
    <a href="orders.php" class="shadcn-stat-card">
        <div class="shadcn-stat-top">
            <span class="shadcn-stat-title">Customer Orders</span>
            <div class="shadcn-stat-icon-wrap" style="background: rgba(168, 85, 247, 0.12); color: #9333ea;">
                <i class="fa-solid fa-cart-shopping"></i>
            </div>
        </div>
        <div>
            <div class="shadcn-stat-value"><?php echo number_format((int) $total_orders); ?></div>
            <div class="shadcn-stat-note">Lifetime customer transactions</div>
        </div>
    </a>

    <!-- Products Card -->
    <a href="products.php" class="shadcn-stat-card">
        <div class="shadcn-stat-top">
            <span class="shadcn-stat-title">Catalog Inventory</span>
            <div class="shadcn-stat-icon-wrap" style="background: rgba(14, 165, 233, 0.12); color: #0284c7;">
                <i class="fa-solid fa-shirt"></i>
            </div>
        </div>
        <div>
            <div class="shadcn-stat-value"><?php echo number_format((int) $total_products); ?></div>
            <div class="shadcn-stat-note">Active outfits & jewelry SKUs</div>
        </div>
    </a>

    <!-- Categories & Stock Card -->
    <a href="categories.php" class="shadcn-stat-card">
        <div class="shadcn-stat-top">
            <span class="shadcn-stat-title">Categories & Stock</span>
            <div class="shadcn-stat-icon-wrap" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">
                <i class="fa-solid fa-folder-tree"></i>
            </div>
        </div>
        <div>
            <div class="shadcn-stat-value"><?php echo number_format((int) $total_categories); ?></div>
            <div class="shadcn-stat-note">
                <?php if ($low_stock > 0): ?>
                    <span style="color: #d97706; font-weight: 600;">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $low_stock; ?> items low in stock
                    </span>
                <?php else: ?>
                    <span style="color: #059669; font-weight: 500;">
                        <i class="fa-solid fa-check"></i> Stock levels healthy
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </a>
</div>

<div class="wp-editor-columns">
    <!-- Main Column: Recent Orders & Products -->
    <div class="main-column">
        
        <!-- RECENT ORDERS SHADCN CARD -->
        <div class="shadcn-card">
            <div class="shadcn-card-header">
                <div>
                    <div class="shadcn-card-title">
                        <i class="fa-solid fa-cart-shopping" style="color: #3b82f6;"></i> Recent Orders
                    </div>
                    <div class="shadcn-card-desc">Latest storefront orders and fulfillment status</div>
                </div>
                <a href="orders.php" class="shadcn-btn shadcn-btn-outline" style="height: 32px; font-size: 12px; padding: 0 10px;">
                    View All Orders &rarr;
                </a>
            </div>
            <div class="shadcn-card-body">
                <?php if (empty($recent_orders)): ?>
                    <p style="padding: 32px; color: #71717a; text-align: center; font-size: 13.5px;">
                        <i class="fa-solid fa-inbox" style="font-size: 24px; display: block; margin-bottom: 8px; opacity: 0.5;"></i>
                        No customer orders received yet.
                    </p>
                <?php else: ?>
                    <table class="shadcn-table">
                        <thead>
                            <tr>
                                <th style="width: 100px;">Order</th>
                                <th>Customer</th>
                                <th>Purchased Items</th>
                                <th style="width: 110px;">Amount</th>
                                <th style="width: 120px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $ord): ?>
                                <tr>
                                    <td>
                                        <a href="order-detail.php?id=<?php echo $ord['id']; ?>" style="font-weight: 600; font-family: monospace; color: #09090b; text-decoration: none; padding: 2px 6px; background: #f4f4f5; border: 1px solid #e4e4e7; border-radius: 4px; font-size: 12px;">
                                            <?php echo format_order_number($ord['id']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500; font-size: 13px; color: #09090b;">
                                            <?php echo sanitize_html(trim(($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? '')) ?: 'Guest Customer'); ?>
                                        </div>
                                        <div style="font-size: 11.5px; color: #71717a;"><?php echo sanitize_html($ord['email']); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($ord['items'])): ?>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="width: 28px; height: 34px; border-radius: 4px; overflow: hidden; position: relative; background: #f4f4f5; border: 1px solid #e4e4e7; flex-shrink: 0;">
                                                    <?php if (!empty($ord['items'][0]['main_image'])): ?>
                                                        <img src="<?php echo sanitize_html($ord['items'][0]['main_image']); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; display: block;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                        <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: #a1a1aa;">
                                                            <i class="fa-solid fa-gem" style="font-size: 10px;"></i>
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #a1a1aa;">
                                                            <i class="fa-solid fa-gem" style="font-size: 10px;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <div style="font-size: 12.5px; font-weight: 500; color: #09090b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 190px;">
                                                        <?php echo sanitize_html($ord['items'][0]['name']); ?>
                                                    </div>
                                                    <?php if (count($ord['items']) > 1): ?>
                                                        <span style="display: inline-block; padding: 1px 5px; font-size: 10.5px; font-weight: 500; color: #71717a; background: #f4f4f5; border-radius: 4px; border: 1px solid #e4e4e7;">
                                                            +<?php echo count($ord['items']) - 1; ?> more item<?php echo count($ord['items']) > 2 ? 's' : ''; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #a1a1aa; font-style: italic; font-size: 12px;">Order details</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="font-size: 13px; font-weight: 600; color: #09090b;">₹<?php echo number_format($ord['total_amount'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                            $stClass = 'status-pill-default';
                                            $stIcon = 'fa-circle-dot';
                                            switch($ord['status']) {
                                                case 'Delivered':
                                                    $stClass = 'status-pill-delivered';
                                                    $stIcon = 'fa-circle-check';
                                                    break;
                                                case 'Shipped':
                                                    $stClass = 'status-pill-shipped';
                                                    $stIcon = 'fa-truck-fast';
                                                    break;
                                                case 'Processing':
                                                    $stClass = 'status-pill-processing';
                                                    $stIcon = 'fa-gears';
                                                    break;
                                                case 'Cancelled':
                                                    $stClass = 'status-pill-cancelled';
                                                    $stIcon = 'fa-circle-xmark';
                                                    break;
                                                default:
                                                    $stClass = 'status-pill-pending';
                                                    $stIcon = 'fa-clock';
                                            }
                                        ?>
                                        <span class="status-pill <?php echo $stClass; ?>">
                                            <i class="fa-solid <?php echo $stIcon; ?>" style="font-size: 10px;"></i>
                                            <?php echo htmlspecialchars($ord['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Products Card -->
        <div class="shadcn-card">
            <div class="shadcn-card-header">
                <div>
                    <div class="shadcn-card-title">
                        <i class="fa-solid fa-clock-rotate-left" style="color: #a855f7;"></i> Recently Added Products
                    </div>
                    <div class="shadcn-card-desc">Newest additions to your bridal and fashion catalog</div>
                </div>
                <a href="products.php" class="shadcn-btn shadcn-btn-outline" style="height: 28px; font-size: 11.5px; padding: 0 9px;">
                    All Products &rarr;
                </a>
            </div>
            <div class="shadcn-card-body">
                <?php if (empty($recent_products)): ?>
                    <p style="padding: 32px; color: #71717a; text-align: center; font-size: 13px;">
                        No products registered in the database yet.
                    </p>
                <?php else: ?>
                    <table class="shadcn-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Image</th>
                                <th>Product Name & SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th style="width: 50px; text-align: center;">Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_products as $prod): ?>
                                <tr>
                                    <td>
                                        <div style="width: 32px; height: 40px; border-radius: 4px; overflow: hidden; position: relative; background: #f4f4f5; border: 1px solid #e4e4e7;">
                                            <?php if ($prod['main_image']): ?>
                                                <img src="<?php echo sanitize_html($prod['main_image']); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; display: block;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: #a1a1aa;">
                                                    <i class="fa-solid fa-gem" style="font-size: 11px;"></i>
                                                </div>
                                            <?php else: ?>
                                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #a1a1aa;">
                                                    <i class="fa-solid fa-gem" style="font-size: 11px;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <a href="product-edit.php?id=<?php echo $prod['id']; ?>" style="font-weight: 500; color: #09090b; text-decoration: none; font-size: 13px; line-height: 1.35; display: inline-block;">
                                                <?php echo sanitize_html($prod['name']); ?>
                                            </a>
                                        </div>
                                        <div style="margin-top: 2px;">
                                            <span style="font-size: 10.5px; font-family: monospace; color: #71717a; background: #f4f4f5; padding: 1px 5px; border-radius: 3px; border: 1px solid #e4e4e7;">
                                                <?php echo sanitize_html($prod['sku'] ?: 'N/A'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-size: 11px; color: #52525b; background: #f4f4f5; padding: 2px 6px; border-radius: 4px; border: 1px solid #e4e4e7; white-space: nowrap; display: inline-block;">
                                            <?php echo sanitize_html($prod['category_name'] ?: 'Uncategorized'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($prod['sale_price']): ?>
                                            <span style="text-decoration: line-through; color: #a1a1aa; font-size: 10.5px;">₹<?php echo number_format($prod['price'], 2); ?></span>
                                            <div style="color: #dc2626; font-weight: 600; font-size: 13px;">₹<?php echo number_format($prod['sale_price'], 2); ?></div>
                                        <?php else: ?>
                                            <span style="color: #09090b; font-weight: 600; font-size: 13px;">₹<?php echo number_format($prod['price'], 2); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($prod['stock_qty'] <= 0): ?>
                                            <span class="stock-pill-out">Out of stock</span>
                                        <?php elseif ($prod['stock_qty'] <= 5): ?>
                                            <span class="stock-pill-low">Low: <?php echo $prod['stock_qty']; ?></span>
                                        <?php else: ?>
                                            <span class="stock-pill-in">In Stock (<?php echo $prod['stock_qty']; ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="product-edit.php?id=<?php echo $prod['id']; ?>" class="shadcn-btn-ghost" style="width: 30px; height: 30px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; color: #71717a;" title="Edit Product">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Side Column: Store Identity & Featured Showcase -->
    <div class="side-column">
        
        <!-- STORE IDENTITY CARD -->
        <div class="shadcn-card">
            <div class="shadcn-card-header">
                <div class="shadcn-card-title">
                    <i class="fa-solid fa-gem" style="color: #f59e0b;"></i> Store Overview
                </div>
            </div>
            <div class="shadcn-card-padded">
                <p style="font-size: 13px; color: #52525b; line-height: 1.5; margin-bottom: 14px;">
                    <strong>YosshitaNeha Fashion Studio</strong> specializes in curated bridal collections:
                </p>
                
                <div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #27272a; padding: 6px 10px; background: #f4f4f5; border-radius: 6px;">
                        <i class="fa-solid fa-vest" style="color: #71717a; width: 14px;"></i>
                        <span>Bridal Wears & Couture</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #27272a; padding: 6px 10px; background: #f4f4f5; border-radius: 6px;">
                        <i class="fa-solid fa-ring" style="color: #71717a; width: 14px;"></i>
                        <span>Bridal Jewellery & Ornaments</span>
                    </div>
                </div>

                <div style="border-top: 1px solid #f4f4f5; padding-top: 12px; display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
                        <span style="color: #71717a;">Inventory Source:</span>
                        <span class="shadcn-badge shadcn-badge-amber">POS Synced</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
                        <span style="color: #71717a;">AI SEO Engine:</span>
                        <span class="shadcn-badge shadcn-badge-gemini">Gemini Ready</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FEATURED ITEMS CARD -->
        <div class="shadcn-card">
            <div class="shadcn-card-header">
                <div class="shadcn-card-title">
                    <i class="fa-solid fa-star" style="color: #f59e0b;"></i> Featured Showcase
                </div>
            </div>
            <div class="shadcn-card-body">
                <?php if (empty($featured_products)): ?>
                    <p style="padding: 20px; color: #71717a; text-align: center; font-size: 12.5px;">
                        No items marked as featured yet. Star an item in the products list to feature it here.
                    </p>
                <?php else: ?>
                    <ul style="list-style: none; margin: 0; padding: 0;">
                        <?php foreach ($featured_products as $fprod): ?>
                            <li style="display: flex; align-items: center; padding: 12px 16px; border-bottom: 1px solid #f4f4f5; gap: 10px;">
                                <div style="width: 36px; height: 46px; border-radius: 6px; overflow: hidden; border: 1px solid #e4e4e7; flex-shrink: 0; background: #f4f4f5;">
                                    <?php if ($fprod['main_image']): ?>
                                        <img src="<?php echo sanitize_html($fprod['main_image']); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: #a1a1aa;">
                                            <i class="fa-solid fa-gem" style="font-size: 12px;"></i>
                                        </div>
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #a1a1aa;">
                                            <i class="fa-solid fa-gem" style="font-size: 12px;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="min-width: 0; flex: 1;">
                                    <div style="font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <a href="product-edit.php?id=<?php echo $fprod['id']; ?>" style="color: #09090b; text-decoration: none;">
                                            <?php echo sanitize_html($fprod['name']); ?>
                                        </a>
                                    </div>
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 3px;">
                                        <span style="font-size: 11px; color: #71717a;"><?php echo sanitize_html($fprod['category_name'] ?: 'Curated'); ?></span>
                                        <strong style="font-size: 12px; color: #09090b;">
                                            ₹<?php echo number_format($fprod['sale_price'] ?: $fprod['price'], 2); ?>
                                        </strong>
                                    </div>
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