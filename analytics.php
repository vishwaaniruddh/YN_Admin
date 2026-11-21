<?php
// admin/analytics.php
// Live Traffic & Visitor Analytics Monitor — Enhanced with Sessions, Bounce Rate, Device Breakdown
$page_title = "Traffic & Visitor Analytics";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fix collation on visitor_logs table if needed
try {
    $pdo->exec("ALTER TABLE visitor_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Exception $e) {
}

// Time filter range (today, 7days, 30days)
$range = $_GET['range'] ?? '7days';
$interval_sql = "INTERVAL 7 DAY";
if ($range === 'today') {
    $interval_sql = "INTERVAL 1 DAY";
} elseif ($range === '30days') {
    $interval_sql = "INTERVAL 30 DAY";
} elseif ($range === '90days') {
    $interval_sql = "INTERVAL 90 DAY";
}

$total_pageviews = 0;
$unique_visitors = 0;
$total_sessions = 0;
$bounce_rate = 0;
$avg_pages_per_session = 0;
$traffic_sources = [];
$top_source_name = 'Direct';
$top_products = [];
$top_categories = [];
$recent_logs = [];
$checkout_visits_count = 0;
$checkout_abandoned_count = 0;
$device_breakdown = [];
$page_type_breakdown = [];
$daily_trend = [];
$top_searches = [];

// Fetch Checkout Visitors KPI for current range
try {
    $cStmt = $pdo->query("SELECT 
        COUNT(*) as total_v, 
        SUM(CASE WHEN status IN ('visited', 'payment_initiated') AND (order_id IS NULL OR order_id = 0) THEN 1 ELSE 0 END) as ab_cnt 
        FROM checkout_visitors WHERE created_at >= DATE_SUB(NOW(), $interval_sql)");
    $cRow = $cStmt->fetch(PDO::FETCH_ASSOC);
    $checkout_visits_count = (int)($cRow['total_v'] ?? 0);
    $checkout_abandoned_count = (int)($cRow['ab_cnt'] ?? 0);
} catch (Exception $e) {}

// 1. Total Pageviews, Unique Visitors & Sessions
try {
    $statsStmt = $pdo->query("SELECT 
        COUNT(*) as total_pageviews, 
        COUNT(DISTINCT ip_address) as unique_visitors,
        COUNT(DISTINCT session_id) as total_sessions
        FROM visitor_logs 
        WHERE created_at >= DATE_SUB(NOW(), $interval_sql)");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    $total_pageviews = $stats['total_pageviews'] ?? 0;
    $unique_visitors = $stats['unique_visitors'] ?? 0;
    $total_sessions = max(1, (int)($stats['total_sessions'] ?? 1));
    $avg_pages_per_session = round($total_pageviews / $total_sessions, 1);
} catch (Exception $e) {
}

// 1b. Bounce Rate
try {
    $bounceStmt = $pdo->query("SELECT COUNT(*) as bounced FROM (
        SELECT session_id, COUNT(*) as hits FROM visitor_logs 
        WHERE session_id IS NOT NULL AND session_id != '' AND created_at >= DATE_SUB(NOW(), $interval_sql) 
        GROUP BY session_id HAVING hits = 1
    ) sub");
    $bounceRow = $bounceStmt->fetch(PDO::FETCH_ASSOC);
    $bounce_rate = round(((int)($bounceRow['bounced'] ?? 0) / $total_sessions) * 100, 1);
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
} catch (Exception $e) {
}

// 3. Top Viewed Products (split pid + slug matching with explicit COLLATE)
try {
    $topProductsStmt = $pdo->query("SELECT p.id, p.name, p.sku, p.main_image, p.price, p.view_count, 
        (SELECT COUNT(*) FROM visitor_logs v WHERE v.product_id = p.id AND v.created_at >= DATE_SUB(NOW(), $interval_sql)) as pid_views,
        (SELECT COUNT(*) FROM visitor_logs v WHERE p.slug IS NOT NULL AND p.slug != '' AND v.page_url COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', p.slug COLLATE utf8mb4_unicode_ci, '%') AND v.created_at >= DATE_SUB(NOW(), $interval_sql)) as slug_views
        FROM products p 
        WHERE p.deleted_at IS NULL AND (p.status = 'published' OR p.status IS NULL)
        ORDER BY pid_views DESC, slug_views DESC, p.view_count DESC, p.id DESC 
        LIMIT 6");
    $top_products = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($top_products as &$tp) {
        $tp['log_views'] = max(($tp['pid_views'] ?? 0), ($tp['slug_views'] ?? 0));
    }
    unset($tp);
} catch (Exception $e) {
}

// 4. Top Viewed Categories
try {
    $topCatsStmt = $pdo->query("SELECT c.id, c.name, c.slug, 
        (SELECT COUNT(*) FROM visitor_logs v WHERE v.category_id = c.id AND v.created_at >= DATE_SUB(NOW(), $interval_sql)) as cid_views,
        (SELECT COUNT(*) FROM visitor_logs v WHERE c.slug IS NOT NULL AND c.slug != '' AND v.page_url COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', c.slug COLLATE utf8mb4_unicode_ci, '%') AND v.created_at >= DATE_SUB(NOW(), $interval_sql)) as slug_views
        FROM categories c 
        WHERE c.deleted_at IS NULL 
        ORDER BY cid_views DESC, slug_views DESC, c.id ASC 
        LIMIT 5");
    $top_categories = $topCatsStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($top_categories as &$tc) {
        $tc['cat_views'] = max(($tc['cid_views'] ?? 0), ($tc['slug_views'] ?? 0));
    }
    unset($tc);
} catch (Exception $e) {
}

// 5. Live Visitor Log Stream
try {
    $logsStmt = $pdo->query("SELECT * FROM visitor_logs ORDER BY id DESC LIMIT 30");
    $recent_logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// 6. Device Breakdown
try {
    $devStmt = $pdo->query("SELECT device_type, COUNT(*) as cnt FROM visitor_logs WHERE created_at >= DATE_SUB(NOW(), $interval_sql) GROUP BY device_type ORDER BY cnt DESC");
    $device_breakdown = $devStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 7. Page Type Breakdown
try {
    $ptStmt = $pdo->query("SELECT page_type, COUNT(*) as views, COUNT(DISTINCT ip_address) as unique_ips FROM visitor_logs WHERE created_at >= DATE_SUB(NOW(), $interval_sql) GROUP BY page_type ORDER BY views DESC");
    $page_type_breakdown = $ptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 8. Daily Trend
try {
    $trendStmt = $pdo->query("SELECT DATE(created_at) as day, COUNT(*) as views, COUNT(DISTINCT ip_address) as visitors FROM visitor_logs WHERE created_at >= DATE_SUB(NOW(), $interval_sql) GROUP BY DATE(created_at) ORDER BY day ASC");
    $daily_trend = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 9. Top Search Terms
try {
    $searchStmt = $pdo->query("SELECT search_term, COUNT(*) as cnt, AVG(results_count) as avg_res FROM search_logs WHERE created_at >= DATE_SUB(NOW(), $interval_sql) GROUP BY search_term ORDER BY cnt DESC LIMIT 10");
    $top_searches = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<div class="wrap-header">
    <h1><i class="fa-solid fa-chart-line" style="color: var(--wp-blue);"></i> Traffic & Visitor Analytics
    </h1>
    <div style="display: flex; gap: 10px; align-items: center;">
        <form method="GET" action="analytics.php">
            <select name="range" onchange="this.form.submit()" class="form-control"
                style="padding: 6px 12px; font-size: 13px; font-weight: 600;">
                <option value="today" <?php echo $range === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="7days" <?php echo $range === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30days" <?php echo $range === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="90days" <?php echo $range === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
            </select>
        </form>
    </div>
</div>

<!-- Stats Overview Grid — 6 KPI Cards -->
<div class="dashboard-grid" style="margin-bottom: 25px; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));">
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
        <div class="dash-card-icon" style="background-color: rgba(14, 165, 233, 0.12); color: #0ea5e9;">
            <i class="fa-solid fa-window-restore"></i>
        </div>
        <div class="dash-card-info">
            <h3>Sessions</h3>
            <p><?php echo number_format($total_sessions); ?></p>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-icon" style="background-color: rgba(239, 68, 68, 0.12); color: #ef4444;">
            <i class="fa-solid fa-person-walking-arrow-right"></i>
        </div>
        <div class="dash-card-info">
            <h3>Bounce Rate</h3>
            <p style="font-size: 22px; color: <?php echo $bounce_rate > 70 ? '#ef4444' : ($bounce_rate > 50 ? '#f59e0b' : '#059669'); ?>;"><?php echo $bounce_rate; ?>%</p>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-icon" style="background-color: rgba(155, 89, 182, 0.12); color: #9b59b6;">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="dash-card-info">
            <h3>Pages / Session</h3>
            <p style="font-size: 22px;"><?php echo $avg_pages_per_session; ?></p>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-icon" style="background-color: rgba(241, 196, 15, 0.12); color: #f1c40f;">
            <i class="fa-solid fa-compass"></i>
        </div>
        <div class="dash-card-info">
            <h3>Top Source</h3>
            <p style="font-size: 16px; color: #9b59b6;"><?php echo htmlspecialchars($top_source_name); ?></p>
        </div>
    </div>
</div>

<!-- Checkout Traffic & Abandoned Carts Banner -->
<div style="background: linear-gradient(135deg, #18181b 0%, #27272a 100%); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; color: #fff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
    <div style="display: flex; align-items: center; gap: 14px;">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(176, 141, 87, 0.2); border: 1px solid rgba(176, 141, 87, 0.4); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fbbf24;">
            <i class="fa-solid fa-cart-shopping"></i>
        </div>
        <div>
            <div style="font-size: 15px; font-weight: 700; color: #fff;">
                Checkout Visitors: <?php echo number_format($checkout_visits_count); ?> Visitors &bull; <?php echo number_format($checkout_abandoned_count); ?> Abandoned Carts
            </div>
            <div style="font-size: 12px; color: #a1a1aa; margin-top: 2px;">
                Monitor shoppers who reached checkout, track cart details, and recover lost orders in real-time.
            </div>
        </div>
    </div>
    <a href="checkout-visitors.php" class="button" style="background: #B08D57; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
        View Checkout Visitors <i class="fa-solid fa-arrow-right"></i>
    </a>
</div>

<!-- Quick Links to Sub-Pages -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px;">
    <a href="analytics-campaigns.php?range=<?php echo $range; ?>" style="display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; text-decoration: none; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.04);" onmouseover="this.style.borderColor='#1877f2';this.style.boxShadow='0 2px 8px rgba(24,119,242,0.15)'" onmouseout="this.style.borderColor='#e2e8f0';this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04)'">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(24, 119, 242, 0.1); display: flex; align-items: center; justify-content: center; color: #1877f2; font-size: 16px;">
            <i class="fa-solid fa-bullhorn"></i>
        </div>
        <div>
            <div style="font-size: 13px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                <span>Facebook &amp; Campaigns</span>
                <span style="font-size: 9.5px; background: #dbeafe; color: #1d4ed8; padding: 1px 5px; border-radius: 4px; font-weight: 700;">ADS</span>
            </div>
            <div style="font-size: 11px; color: #64748b;">Facebook, Google &amp; UTM traffic</div>
        </div>
    </a>
    <a href="analytics-products.php?range=<?php echo $range; ?>" style="display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; text-decoration: none; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.04);" onmouseover="this.style.borderColor='#3b82f6';this.style.boxShadow='0 2px 8px rgba(59,130,246,0.1)'" onmouseout="this.style.borderColor='#e2e8f0';this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04)'">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 16px;">
            <i class="fa-solid fa-chart-bar"></i>
        </div>
        <div>
            <div style="font-size: 13px; font-weight: 700; color: #1e293b;">Product Analytics</div>
            <div style="font-size: 11px; color: #64748b;">Views, conversions per product</div>
        </div>
    </a>
    <a href="analytics-categories.php?range=<?php echo $range; ?>" style="display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; text-decoration: none; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.04);" onmouseover="this.style.borderColor='#7c3aed';this.style.boxShadow='0 2px 8px rgba(124,58,237,0.1)'" onmouseout="this.style.borderColor='#e2e8f0';this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04)'">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(124, 58, 237, 0.1); display: flex; align-items: center; justify-content: center; color: #7c3aed; font-size: 16px;">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div>
            <div style="font-size: 13px; font-weight: 700; color: #1e293b;">Category Analytics</div>
            <div style="font-size: 11px; color: #64748b;">Traffic per collection</div>
        </div>
    </a>
    <a href="analytics-pages.php?range=<?php echo $range; ?>" style="display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; text-decoration: none; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.04);" onmouseover="this.style.borderColor='#059669';this.style.boxShadow='0 2px 8px rgba(5,150,105,0.1)'" onmouseout="this.style.borderColor='#e2e8f0';this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04)'">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(5, 150, 105, 0.1); display: flex; align-items: center; justify-content: center; color: #059669; font-size: 16px;">
            <i class="fa-solid fa-globe"></i>
        </div>
        <div>
            <div style="font-size: 13px; font-weight: 700; color: #1e293b;">Pages & Search</div>
            <div style="font-size: 11px; color: #64748b;">URL-level traffic, search terms</div>
        </div>
    </a>
</div>

<!-- Page Type Breakdown Table -->
<?php if (!empty($page_type_breakdown)): ?>
<div class="postbox" style="margin-bottom: 24px;">
    <div class="postbox-header">
        <h2><i class="fa-solid fa-sitemap" style="color: #0ea5e9;"></i> Page Type Breakdown</h2>
    </div>
    <div class="postbox-body" style="padding: 0;">
        <table class="wp-list-table widefat fixed striped" style="margin: 0;">
            <thead>
                <tr>
                    <th>Page Type</th>
                    <th style="text-align: right;">Pageviews</th>
                    <th style="text-align: right;">Unique Visitors</th>
                    <th style="text-align: right;">% of Traffic</th>
                    <th style="width: 30%;">Distribution</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $type_colors = [
                    'home' => '#059669', 'product' => '#0284c7', 'category' => '#7c3aed',
                    'cart' => '#d97706', 'checkout' => '#dc2626', 'auth' => '#64748b',
                    'search' => '#0891b2', 'studio' => '#db2777', 'portfolio' => '#8b5cf6',
                    'account' => '#6366f1', 'order' => '#ea580c', 'other' => '#94a3b8'
                ];
                foreach ($page_type_breakdown as $pt): 
                    $pct = $total_pageviews > 0 ? round(($pt['views'] / $total_pageviews) * 100, 1) : 0;
                    $color = $type_colors[$pt['page_type']] ?? '#94a3b8';
                ?>
                <tr>
                    <td>
                        <span style="display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; color: #fff; background: <?php echo $color; ?>; text-transform: capitalize;">
                            <?php echo htmlspecialchars($pt['page_type']); ?>
                        </span>
                    </td>
                    <td style="text-align: right; font-weight: 700; color: #1e293b;"><?php echo number_format($pt['views']); ?></td>
                    <td style="text-align: right; color: #0284c7; font-weight: 600;"><?php echo number_format($pt['unique_ips']); ?></td>
                    <td style="text-align: right; font-weight: 600; color: #64748b;"><?php echo $pct; ?>%</td>
                    <td>
                        <div style="width: 100%; background: #f1f5f9; height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo $pct; ?>%; background: <?php echo $color; ?>; height: 100%; border-radius: 4px; transition: width 0.3s;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Main Two-Column Layout -->
<div class="wp-editor-columns">

    <!-- Left Main Column: Live Traffic Stream & Products -->
    <div class="main-column">

        <!-- Live Visitor Stream -->
        <div class="postbox" style="margin-bottom: 24px;">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-tower-cell" style="color: #2ecc71;"></i> Real-Time Visitor Activity Stream
                </h2>
            </div>
            <div class="postbox-body" style="padding: 0; max-height: 480px; overflow-y: auto;">
                <?php if (empty($recent_logs)): ?>
                    <div style="text-align: center; padding: 40px; color: #646970;">
                        <i class="fa-solid fa-user-clock" style="font-size: 36px; margin-bottom: 12px; color: #a7aaad;"></i>
                        <p style="margin: 0; font-size: 14px;">No visitor traffic logs recorded yet. Visit the storefront to
                            generate live logs!</p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead style="position: sticky; top: 0; z-index: 10; background: #fff;">
                            <tr>
                                <th style="width: 12%;">IP Address</th>
                                <th style="width: 12%;">Source</th>
                                <th style="width: 10%;">Type</th>
                                <th style="width: 30%;">Visited Page</th>
                                <th style="width: 10%;">Device</th>
                                <th style="width: 12%;">Session</th>
                                <th style="width: 14%; text-align: right;">Time (IST)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><code
                                            style="font-family: monospace; font-weight: 600; color: #334155; font-size: 11px;"><?php echo htmlspecialchars($log['ip_address']); ?></code>
                                    </td>
                                    <td>
                                        <?php
                                        $src = $log['traffic_source'];
                                        $badgeBg = '#64748b';
                                        if ($src === 'Instagram') $badgeBg = '#e1306c';
                                        elseif ($src === 'Facebook') $badgeBg = '#1877f2';
                                        elseif ($src === 'Google Search') $badgeBg = '#ea4335';
                                        elseif ($src === 'Direct') $badgeBg = '#059669';
                                        elseif ($src === 'Googlebot / Crawler') $badgeBg = '#6366f1';
                                        elseif ($src === 'WhatsApp') $badgeBg = '#25d366';
                                        elseif ($src === 'Pinterest') $badgeBg = '#e60023';
                                        ?>
                                        <span
                                            style="background: <?php echo $badgeBg; ?>; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600;white-space:nowrap;">
                                            <?php echo htmlspecialchars($src); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $ptColor = $type_colors[$log['page_type']] ?? '#94a3b8';
                                        ?>
                                        <span style="font-size: 10px; font-weight: 600; color: <?php echo $ptColor; ?>; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($log['page_type'] ?? 'other'); ?>
                                        </span>
                                    </td>
                                    <td style="word-break: break-all;">
                                        <span style="font-size: 11px; color: #0f172a; font-weight: 500;">
                                            <?php echo htmlspecialchars($log['page_url']); ?>
                                        </span>
                                    </td>
                                    <td style="white-space: nowrap; font-size: 12px;">
                                        <i
                                            class="fa-solid fa-<?php echo strtolower($log['device_type']) === 'mobile' ? 'mobile-screen' : (strtolower($log['device_type']) === 'tablet' ? 'tablet-screen-button' : 'desktop'); ?>"></i>
                                        <?php echo htmlspecialchars($log['device_type']); ?>
                                    </td>
                                    <td>
                                        <code style="font-size: 10px; color: #64748b;"><?php echo htmlspecialchars(substr($log['session_id'] ?? '-', 0, 12)); ?></code>
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
            <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2><i class="fa-solid fa-fire" style="color: #f1c40f;"></i> Top Viewed Products</h2>
                <a href="analytics-products.php?range=<?php echo $range; ?>" style="font-size: 12px; font-weight: 600; color: #3b82f6; text-decoration: none; padding-right: 12px;">View All Products →</a>
            </div>
            <div class="postbox-body" style="padding: 0;">
                <?php if (empty($top_products)): ?>
                    <div style="text-align: center; padding: 30px; color: #646970;">
                        <p style="margin: 0; font-size: 14px;">No products found in database.</p>
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
                                <tr>
                                    <td>
                                        <?php if (!empty($p['main_image'])): ?>
                                            <img src="<?php echo sanitize_html($p['main_image']); ?>"
                                                style="width: 36px; height: 36px; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <div
                                                style="width: 36px; height: 36px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                                <i class="fa-solid fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="product-edit.php?id=<?php echo $p['id']; ?>"
                                            style="font-weight: 600; color: #1e293b; text-decoration: none;">
                                            <?php echo sanitize_html($p['name']); ?>
                                        </a>
                                    </td>
                                    <td><code><?php echo sanitize_html($p['sku'] ?: 'N/A'); ?></code></td>
                                    <td style="font-weight: 600; color: #16a34a;">₹<?php echo number_format($p['price'], 2); ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: #0284c7;">
                                        <?php echo number_format(max(($p['log_views'] ?? 0), ($p['view_count'] ?? 0))); ?> views
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Right Sidebar Column: Traffic Sources, Devices & Top Categories -->
    <div class="sidebar-column" style="width: 320px; flex-shrink: 0;">

        <!-- Traffic Sources Breakdown -->
        <div class="postbox" style="margin-bottom: 24px;">
            <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2><i class="fa-solid fa-chart-pie" style="color: #9b59b6;"></i> Traffic Sources</h2>
                <a href="analytics-campaigns.php?range=<?php echo $range; ?>" style="font-size: 11.5px; color: #1877f2; text-decoration: none; font-weight: 600;">
                    Campaigns <i class="fa-solid fa-arrow-right"></i>
                </a>
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
                            $sourceName = $src['traffic_source'];
                            $isAd = false;

                            if ($sourceName === 'Facebook Ads') { $barBg = '#1877f2'; $isAd = true; }
                            elseif ($sourceName === 'Facebook (Organic)' || $sourceName === 'Facebook') { $barBg = '#3b82f6'; }
                            elseif ($sourceName === 'Instagram Ads') { $barBg = '#e1306c'; $isAd = true; }
                            elseif ($sourceName === 'Instagram (Organic)' || $sourceName === 'Instagram') { $barBg = '#ec4899'; }
                            elseif ($sourceName === 'Google Ads') { $barBg = '#ea4335'; $isAd = true; }
                            elseif ($sourceName === 'Google Search') { $barBg = '#f59e0b'; }
                            elseif ($sourceName === 'Googlebot / Crawler') { $barBg = '#6366f1'; }
                            elseif ($sourceName === 'WhatsApp') { $barBg = '#25d366'; }
                            elseif ($sourceName === 'Pinterest') { $barBg = '#e60023'; }
                            ?>
                            <div>
                                <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #334155;">
                                    <span style="display: flex; align-items: center; gap: 5px;">
                                        <?php echo htmlspecialchars($sourceName); ?>
                                        <?php if ($isAd): ?>
                                            <span style="background: #e0f2fe; color: #0369a1; font-size: 9.5px; padding: 1px 4px; border-radius: 3px; font-weight: 700;">PAID</span>
                                        <?php endif; ?>
                                    </span>
                                    <span><?php echo number_format($src['cnt']); ?> (<?php echo $pct; ?>%)</span>
                                </div>
                                <div style="width: 100%; background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="width: <?php echo $pct; ?>%; background: <?php echo $barBg; ?>; height: 100%; border-radius: 4px;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid #f1f5f9; text-align: center;">
                        <a href="analytics-campaigns.php?range=<?php echo $range; ?>" style="color: #1877f2; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-bullhorn"></i> View Facebook &amp; Campaign Details &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Device Breakdown -->
        <div class="postbox" style="margin-bottom: 24px;">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-mobile-screen" style="color: #0ea5e9;"></i> Device Breakdown</h2>
            </div>
            <div class="postbox-body" style="padding: 16px;">
                <?php if (empty($device_breakdown)): ?>
                    <p style="text-align: center; color: #646970; font-size: 13px;">No device data.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php 
                        $deviceIcons = ['Mobile' => 'fa-mobile-screen', 'Desktop' => 'fa-desktop', 'Tablet' => 'fa-tablet-screen-button', 'Search Bot' => 'fa-robot'];
                        $deviceColors = ['Mobile' => '#0ea5e9', 'Desktop' => '#8b5cf6', 'Tablet' => '#f59e0b', 'Search Bot' => '#6366f1'];
                        foreach ($device_breakdown as $dev):
                            $devPct = $total_pageviews > 0 ? round(($dev['cnt'] / $total_pageviews) * 100, 1) : 0;
                            $dColor = $deviceColors[$dev['device_type']] ?? '#64748b';
                            $dIcon = $deviceIcons[$dev['device_type']] ?? 'fa-question';
                        ?>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: <?php echo $dColor; ?>15; display: flex; align-items: center; justify-content: center; color: <?php echo $dColor; ?>; font-size: 14px;">
                                <i class="fa-solid <?php echo $dIcon; ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 3px;">
                                    <span><?php echo htmlspecialchars($dev['device_type']); ?></span>
                                    <span><?php echo number_format($dev['cnt']); ?> (<?php echo $devPct; ?>%)</span>
                                </div>
                                <div style="width: 100%; background: #f1f5f9; height: 6px; border-radius: 3px; overflow: hidden;">
                                    <div style="width: <?php echo $devPct; ?>%; background: <?php echo $dColor; ?>; height: 100%; border-radius: 3px;"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Categories Breakdown -->
        <div class="postbox" style="margin-bottom: 24px;">
            <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2><i class="fa-solid fa-layer-group" style="color: #3b82f6;"></i> Top Categories</h2>
                <a href="analytics-categories.php?range=<?php echo $range; ?>" style="font-size: 11px; font-weight: 600; color: #3b82f6; text-decoration: none; padding-right: 12px;">View All →</a>
            </div>
            <div class="postbox-body" style="padding: 16px;">
                <?php if (empty($top_categories)): ?>
                    <p style="text-align: center; color: #646970; font-size: 13px; margin: 10px 0;">No categories found in
                        database.</p>
                <?php else: ?>
                    <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($top_categories as $cat): ?>
                            <li style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
                                <span style="font-size: 13px; font-weight: 600; color: #1e293b;">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </span>
                                <span style="font-size: 12px; font-weight: 700; color: #3b82f6; background: #eff6ff; padding: 2px 8px; border-radius: 10px;">
                                    <?php echo number_format($cat['cat_views'] ?? 0); ?> views
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Search Terms -->
        <?php if (!empty($top_searches)): ?>
        <div class="postbox">
            <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2><i class="fa-solid fa-magnifying-glass" style="color: #0891b2;"></i> Top Searches</h2>
                <a href="analytics-pages.php?range=<?php echo $range; ?>" style="font-size: 11px; font-weight: 600; color: #3b82f6; text-decoration: none; padding-right: 12px;">All Searches →</a>
            </div>
            <div class="postbox-body" style="padding: 0;">
                <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                    <tbody>
                        <?php foreach ($top_searches as $st): ?>
                        <tr>
                            <td style="font-weight: 600; color: #1e293b; font-size: 13px;">
                                "<?php echo htmlspecialchars($st['search_term']); ?>"
                            </td>
                            <td style="text-align: right; font-size: 12px;">
                                <span style="font-weight: 700; color: #0891b2;"><?php echo number_format($st['cnt']); ?></span>
                                <span style="color: #94a3b8;"> searches</span>
                            </td>
                            <td style="text-align: right; font-size: 11px; color: <?php echo round($st['avg_res']) == 0 ? '#ef4444' : '#64748b'; ?>;">
                                <?php echo round($st['avg_res']); ?> avg results
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
