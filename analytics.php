<?php
// admin/analytics.php
$page_title = "Traffic & Visitor Analytics";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Time filter range (today, 7days, 30days)
$range = $_GET['range'] ?? '7days';
$interval_sql = "INTERVAL 7 DAY";
if ($range === 'today') {
    $interval_sql = "INTERVAL 1 DAY";
} elseif ($range === '30days') {
    $interval_sql = "INTERVAL 30 DAY";
}

try {
    // 1. Total Pageviews & Unique Visitors
    $statsStmt = $pdo->query("SELECT 
        COUNT(*) as total_pageviews, 
        COUNT(DISTINCT ip_address) as unique_visitors 
        FROM visitor_logs 
        WHERE created_at >= DATE_SUB(NOW(), $interval_sql)");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    $total_pageviews = $stats['total_pageviews'] ?? 0;
    $unique_visitors = $stats['unique_visitors'] ?? 0;

    // 2. Traffic Sources Breakdown
    $sourcesStmt = $pdo->query("SELECT traffic_source, COUNT(*) as cnt 
        FROM visitor_logs 
        WHERE created_at >= DATE_SUB(NOW(), $interval_sql) 
        GROUP BY traffic_source 
        ORDER BY cnt DESC");
    $traffic_sources = $sourcesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Top Source Name
    $top_source_name = !empty($traffic_sources) ? $traffic_sources[0]['traffic_source'] : 'Direct';

    // 3. Top Viewed Products
    $topProductsStmt = $pdo->query("SELECT p.id, p.name, p.sku, p.main_image, p.price, p.view_count, COUNT(v.id) as log_views 
        FROM products p 
        LEFT JOIN visitor_logs v ON (v.product_id = p.id OR v.page_url LIKE CONCAT('%', p.slug, '%')) 
        WHERE p.deleted_at IS NULL 
        GROUP BY p.id 
        ORDER BY log_views DESC, p.view_count DESC 
        LIMIT 6");
    $top_products = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Top Viewed Categories
    $topCatsStmt = $pdo->query("SELECT c.id, c.name, c.slug, COUNT(v.id) as cat_views 
        FROM categories c 
        JOIN visitor_logs v ON (v.category_id = c.id OR v.page_url LIKE CONCAT('%', c.slug, '%')) 
        WHERE c.deleted_at IS NULL 
        GROUP BY c.id 
        ORDER BY cat_views DESC 
        LIMIT 5");
    $top_categories = $topCatsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Live Visitor Log Stream
    $logsStmt = $pdo->query("SELECT * FROM visitor_logs ORDER BY id DESC LIMIT 25");
    $recent_logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = $e->getMessage();
}
?>

<div class="wrap-header">
    <h1><i class="fa-solid fa-chart-line" style="color: var(--wp-blue);"></i> Traffic &amp; Visitor Analytics Monitor</h1>
    <div style="display: flex; gap: 10px; align-items: center;">
        <form method="GET" action="analytics.php">
            <select name="range" onchange="this.form.submit()" class="form-control" style="padding: 6px 12px; font-size: 13px; font-weight: 600;">
                <option value="today" <?php echo $range === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="7days" <?php echo $range === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30days" <?php echo $range === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
            </select>
        </form>
    </div>
</div>

<!-- Stats Overview Grid -->
<div class="dashboard-grid" style="margin-bottom: 25px;">
    <div class="dash-card">
        <div class="dash-card-icon" style="background-color: rgba(52, 152, 219, 0.12); color: #3498db;">
            <i class="fa-solid fa-eye"></i>
        </div>
        <div class="dash-card-info">
            <h3>Total Pageviews</h3>
            <p><?php echo number_format($total_pageviews); ?></p>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-icon" style="background-color: rgba(46, 204, 113, 0.12); color: #2ecc71;">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="dash-card-info">
            <h3>Unique Visitors</h3>
            <p><?php echo number_format($unique_visitors); ?></p>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-icon" style="background-color: rgba(155, 89, 182, 0.12); color: #9b59b6;">
            <i class="fa-solid fa-compass"></i>
        </div>
        <div class="dash-card-info">
            <h3>Top Traffic Source</h3>
            <p style="font-size: 18px; color: #9b59b6;"><?php echo htmlspecialchars($top_source_name); ?></p>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-icon" style="background-color: rgba(241, 196, 15, 0.12); color: #f1c40f;">
            <i class="fa-solid fa-fire"></i>
        </div>
        <div class="dash-card-info">
            <h3>Top Viewed Product</h3>
            <p style="font-size: 14px; font-weight: 600; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; max-width: 180px;">
                <?php echo !empty($top_products) ? htmlspecialchars($top_products[0]['name']) : 'N/A'; ?>
            </p>
        </div>
    </div>
</div>

<!-- Main Two-Column Layout -->
<div class="wp-editor-columns">

    <!-- Left Main Column: Live Traffic Stream & Products -->
    <div class="main-column">
        
        <!-- Live Visitor Stream -->
        <div class="postbox" style="margin-bottom: 24px;">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-tower-cell" style="color: #2ecc71;"></i> Real-Time Visitor Activity Stream</h2>
            </div>
            <div class="postbox-body" style="padding: 0; max-height: 480px; overflow-y: auto;">
                <?php if (empty($recent_logs)): ?>
                    <div style="text-align: center; padding: 40px; color: #646970;">
                        <i class="fa-solid fa-user-clock" style="font-size: 36px; margin-bottom: 12px; color: #a7aaad;"></i>
                        <p style="margin: 0; font-size: 14px;">No visitor traffic logs recorded yet. Visit the storefront to generate live logs!</p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead style="position: sticky; top: 0; z-index: 10; background: #fff;">
                            <tr>
                                <th style="width: 15%;">IP Address</th>
                                <th style="width: 15%;">Source</th>
                                <th style="width: 35%;">Visited Page</th>
                                <th style="width: 15%;">Device</th>
                                <th style="width: 20%; text-align: right;">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><code style="font-family: monospace; font-weight: 600; color: #334155;"><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                    <td>
                                        <?php 
                                        $src = $log['traffic_source'];
                                        $badgeBg = '#64748b';
                                        if ($src === 'Instagram') $badgeBg = '#e1306c';
                                        elseif ($src === 'Facebook') $badgeBg = '#1877f2';
                                        elseif ($src === 'Google Search') $badgeBg = '#ea4335';
                                        elseif ($src === 'Direct') $badgeBg = '#059669';
                                        ?>
                                        <span style="background: <?php echo $badgeBg; ?>; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                            <?php echo htmlspecialchars($src); ?>
                                        </span>
                                    </td>
                                    <td style="word-break: break-all;">
                                        <span style="font-size: 12px; color: #0f172a; font-weight: 500;">
                                            <?php echo htmlspecialchars($log['page_url']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fa-solid fa-<?php echo strtolower($log['device_type']) === 'mobile' ? 'mobile-screen' : 'desktop'; ?>"></i>
                                        <?php echo htmlspecialchars($log['device_type']); ?>
                                    </td>
                                    <td style="text-align: right; color: #64748b; font-size: 12px; white-space: nowrap;">
                                        <?php echo date('d M, h:i A', strtotime($log['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Most Viewed Products -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-fire-flame-curved" style="color: #f59e0b;"></i> Top Viewed Products</h2>
            </div>
            <div class="postbox-body" style="padding: 0;">
                <?php if (empty($top_products)): ?>
                    <p style="padding: 20px; color: #646970;">No product view metrics recorded yet.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Image</th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Price</th>
                                <th style="text-align: right;">Total Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $p): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($p['main_image'] ?: 'https://placehold.co/40x50/1A1A1A/D4AF37?text=No+Img'); ?>" style="width: 36px; height: 44px; object-fit: cover; border-radius: 4px;">
                                    </td>
                                    <td>
                                        <a href="product-edit.php?id=<?php echo $p['id']; ?>" style="font-weight: 600; color: var(--wp-blue); text-decoration: none;">
                                            <?php echo htmlspecialchars($p['name']); ?>
                                        </a>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($p['sku'] ?: 'N/A'); ?></code></td>
                                    <td style="color: #059669; font-weight: 600;">₹<?php echo number_format($p['price'], 2); ?></td>
                                    <td style="text-align: right; font-weight: 700; color: #d97706; font-size: 14px;">
                                        <i class="fa-solid fa-eye" style="margin-right: 4px;"></i> <?php echo max((int)$p['log_views'], (int)$p['view_count']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Right Column: Traffic Sources & Category Trends -->
    <div class="side-column">

        <!-- Traffic Sources Breakdown -->
        <div class="postbox" style="margin-bottom: 24px;">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-chart-pie" style="color: var(--wp-blue);"></i> Traffic Sources</h2>
            </div>
            <div class="postbox-body">
                <?php if (empty($traffic_sources)): ?>
                    <p style="color: #646970; margin: 0;">No traffic source data available for selected period.</p>
                <?php else: ?>
                    <?php 
                    $sumVisits = array_sum(array_column($traffic_sources, 'cnt'));
                    foreach ($traffic_sources as $srcItem):
                        $percent = $sumVisits > 0 ? round(($srcItem['cnt'] / $sumVisits) * 100, 1) : 0;
                        $sName = $srcItem['traffic_source'];
                        $barColor = '#3b82f6';
                        if ($sName === 'Instagram') $barColor = '#e1306c';
                        elseif ($sName === 'Facebook') $barColor = '#1877f2';
                        elseif ($sName === 'Google Search') $barColor = '#ea4335';
                        elseif ($sName === 'Direct') $barColor = '#10b981';
                    ?>
                        <div style="margin-bottom: 14px;">
                            <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #1e293b;">
                                <span><?php echo htmlspecialchars($sName); ?></span>
                                <span><?php echo $srcItem['cnt']; ?> (<?php echo $percent; ?>%)</span>
                            </div>
                            <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?php echo $percent; ?>%; height: 100%; background: <?php echo $barColor; ?>; border-radius: 4px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Category Views -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-layer-group" style="color: var(--wp-blue);"></i> Top Categories</h2>
            </div>
            <div class="postbox-body" style="padding: 0;">
                <?php if (empty($top_categories)): ?>
                    <p style="padding: 16px; color: #646970; margin: 0;">No category traffic recorded yet.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th style="text-align: right;">Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_categories as $cat): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td style="text-align: right; font-weight: 700; color: #2563eb;"><?php echo $cat['cat_views']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
