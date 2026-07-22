<?php
// admin/analytics.php
// Live Traffic & Visitor Analytics Monitor
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

$total_pageviews = 0;
$unique_visitors = 0;
$traffic_sources = [];
$top_source_name = 'Direct';
$top_products = [];
$top_categories = [];
$recent_logs = [];

// 1. Total Pageviews & Unique Visitors
try {
    $statsStmt = $pdo->query("SELECT 
        COUNT(*) as total_pageviews, 
        COUNT(DISTINCT ip_address) as unique_visitors 
        FROM visitor_logs 
        WHERE created_at >= DATE_SUB(NOW(), $interval_sql)");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    $total_pageviews = $stats['total_pageviews'] ?? 0;
    $unique_visitors = $stats['unique_visitors'] ?? 0;
} catch (Exception $e) {}

// 2. Traffic Sources Breakdown
try {
    $sourcesStmt = $pdo->query("SELECT traffic_source, COUNT(*) as cnt 
        FROM visitor_logs 
        WHERE created_at >= DATE_SUB(NOW(), $interval_sql) 
        GROUP BY traffic_source 
        ORDER BY cnt DESC");
    $traffic_sources = $sourcesStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($traffic_sources)) {
        $top_source_name = $traffic_sources[0]['traffic_source'];
    }
} catch (Exception $e) {}

// 3. Top Viewed Products (using subquery to bypass strict MySQL GROUP BY rules)
try {
    $topProductsStmt = $pdo->query("SELECT p.id, p.name, p.sku, p.main_image, p.price, p.view_count, 
        (SELECT COUNT(*) FROM visitor_logs v WHERE (v.product_id = p.id OR v.page_url LIKE CONCAT('%', p.slug, '%')) AND v.created_at >= DATE_SUB(NOW(), $interval_sql)) as log_views 
        FROM products p 
        WHERE p.deleted_at IS NULL 
        ORDER BY log_views DESC, p.view_count DESC 
        LIMIT 6");
    $top_products = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 4. Top Viewed Categories (using subquery to bypass strict MySQL GROUP BY rules)
try {
    $topCatsStmt = $pdo->query("SELECT c.id, c.name, c.slug, 
        (SELECT COUNT(*) FROM visitor_logs v WHERE (v.category_id = c.id OR v.page_url LIKE CONCAT('%', c.slug, '%')) AND v.created_at >= DATE_SUB(NOW(), $interval_sql)) as cat_views 
        FROM categories c 
        WHERE c.deleted_at IS NULL 
        ORDER BY cat_views DESC, c.id DESC 
        LIMIT 5");
    $top_categories = $topCatsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 5. Live Visitor Log Stream
try {
    $logsStmt = $pdo->query("SELECT * FROM visitor_logs ORDER BY id DESC LIMIT 30");
    $recent_logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
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
                <?php echo !empty($top_products) && (($top_products[0]['log_views'] ?? 0) > 0 || ($top_products[0]['view_count'] ?? 0) > 0) ? htmlspecialchars($top_products[0]['name']) : 'N/A'; ?>
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
                                        elseif ($src === 'Googlebot / Crawler') $badgeBg = '#6366f1';
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
                                    <td style="text-align: right; color: #64748b; font-size: 12px;">
                                        <?php echo date('M d, g:i a', strtotime($log['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Viewed Products Table -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-fire" style="color: #f1c40f;"></i> Top Viewed Products</h2>
            </div>
            <div class="postbox-body" style="padding: 0;">
                <?php 
                $hasProductMetrics = false;
                foreach ($top_products as $tp) {
                    if (($tp['log_views'] ?? 0) > 0 || ($tp['view_count'] ?? 0) > 0) {
                        $hasProductMetrics = true;
                        break;
                    }
                }
                ?>
                <?php if (!$hasProductMetrics): ?>
                    <div style="text-align: center; padding: 30px; color: #646970;">
                        <p style="margin: 0; font-size: 14px;">No product view metrics recorded yet.</p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Image</th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Price</th>
                                <th style="text-align: right;">Total Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $p): ?>
                                <?php if (($p['log_views'] ?? 0) > 0 || ($p['view_count'] ?? 0) > 0): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($p['main_image'])): ?>
                                                <img src="<?php echo sanitize_html($p['main_image']); ?>" style="width: 36px; height: 36px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <div style="width: 36px; height: 36px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                                    <i class="fa-solid fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="product-edit.php?id=<?php echo $p['id']; ?>" style="font-weight: 600; color: #1e293b; text-decoration: none;">
                                                <?php echo sanitize_html($p['name']); ?>
                                            </a>
                                        </td>
                                        <td><code><?php echo sanitize_html($p['sku'] ?: 'N/A'); ?></code></td>
                                        <td style="font-weight: 600; color: #16a34a;">₹<?php echo number_format($p['price'], 2); ?></td>
                                        <td style="text-align: right; font-weight: 700; color: #0284c7;">
                                            <?php echo number_format(max($p['log_views'], $p['view_count'])); ?> views
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Right Sidebar Column: Traffic Sources & Top Categories -->
    <div class="sidebar-column" style="width: 320px; flex-shrink: 0;">
        
        <!-- Traffic Sources Breakdown -->
        <div class="postbox" style="margin-bottom: 24px;">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-chart-pie" style="color: #9b59b6;"></i> Traffic Sources</h2>
            </div>
            <div class="postbox-body" style="padding: 16px;">
                <?php if (empty($traffic_sources)): ?>
                    <p style="text-align: center; color: #646970; font-size: 13px; margin: 10px 0;">No traffic source data available.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <?php foreach ($traffic_sources as $src): ?>
                            <?php 
                            $pct = $total_pageviews > 0 ? round(($src['cnt'] / $total_pageviews) * 100) : 0;
                            $barBg = '#059669';
                            if ($src['traffic_source'] === 'Instagram') $barBg = '#e1306c';
                            elseif ($src['traffic_source'] === 'Facebook') $barBg = '#1877f2';
                            elseif ($src['traffic_source'] === 'Google Search') $barBg = '#ea4335';
                            ?>
                            <div>
                                <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #334155;">
                                    <span><?php echo htmlspecialchars($src['traffic_source']); ?></span>
                                    <span><?php echo number_format($src['cnt']); ?> (<?php echo $pct; ?>%)</span>
                                </div>
                                <div style="width: 100%; background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="width: <?php echo $pct; ?>%; background: <?php echo $barBg; ?>; height: 100%; border-radius: 4px;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Categories Breakdown -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-layer-group" style="color: #3b82f6;"></i> Top Categories</h2>
            </div>
            <div class="postbox-body" style="padding: 16px;">
                <?php 
                $hasCategoryMetrics = false;
                foreach ($top_categories as $cat) {
                    if (($cat['cat_views'] ?? 0) > 0) {
                        $hasCategoryMetrics = true;
                        break;
                    }
                }
                ?>
                <?php if (!$hasCategoryMetrics): ?>
                    <p style="text-align: center; color: #646970; font-size: 13px; margin: 10px 0;">No category traffic recorded yet.</p>
                <?php else: ?>
                    <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($top_categories as $cat): ?>
                            <?php if (($cat['cat_views'] ?? 0) > 0): ?>
                                <li style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
                                    <span style="font-size: 13px; font-weight: 600; color: #1e293b;">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </span>
                                    <span style="font-size: 12px; font-weight: 700; color: #3b82f6; background: #eff6ff; padding: 2px 8px; border-radius: 10px;">
                                        <?php echo number_format($cat['cat_views']); ?> views
                                    </span>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
