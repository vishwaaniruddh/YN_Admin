<?php
// admin/analytics.php
// Live Traffic & Visitor Analytics Monitor — Enhanced with Sessions, Bounce Rate, Device Breakdown
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
} elseif ($range === '90days') {
    $interval_sql = "INTERVAL 90 DAY";
}

// Bot & Crawler exclusion filter (Googlebot, search crawlers, automated bots)
$bot_filter = "AND (v.traffic_source != 'Googlebot / Crawler' AND v.device_type != 'Search Bot' AND v.traffic_source NOT LIKE '%bot%' AND v.traffic_source NOT LIKE '%crawler%')";

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

// 1. Total Pageviews, Unique Visitors & Sessions (excluding crawlers)
try {
    $statsStmt = $pdo->query("SELECT 
        COUNT(*) as total_pageviews, 
        COUNT(DISTINCT v.ip_address) as unique_visitors,
        COUNT(DISTINCT v.session_id) as total_sessions
        FROM visitor_logs v 
        WHERE v.created_at >= DATE_SUB(NOW(), $interval_sql) $bot_filter");
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
        SELECT v.session_id, COUNT(*) as hits FROM visitor_logs v 
        WHERE v.session_id IS NOT NULL AND v.session_id != '' AND v.created_at >= DATE_SUB(NOW(), $interval_sql) $bot_filter
        GROUP BY v.session_id HAVING hits = 1
    ) sub");
    $bounceRow = $bounceStmt->fetch(PDO::FETCH_ASSOC);
    $bounce_rate = round(((int)($bounceRow['bounced'] ?? 0) / $total_sessions) * 100, 1);
} catch (Exception $e) {}

// 2. Traffic Sources Breakdown (human visitors only)
try {
    $sourcesStmt = $pdo->query("SELECT v.traffic_source, COUNT(*) as cnt 
        FROM visitor_logs v 
        WHERE v.created_at >= DATE_SUB(NOW(), $interval_sql) $bot_filter
        GROUP BY v.traffic_source 
        ORDER BY cnt DESC");
    $traffic_sources = $sourcesStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($traffic_sources)) {
        $top_source_name = $traffic_sources[0]['traffic_source'];
    }
} catch (Exception $e) {
}

// 3. Top Viewed Products (Fast index aggregation on visitor_logs)
try {
    $topProductsStmt = $pdo->query("SELECT v.product_id, COUNT(*) as log_views
        FROM visitor_logs v
        WHERE v.product_id IS NOT NULL 
          AND v.product_id > 0 
          AND v.created_at >= DATE_SUB(NOW(), $interval_sql)
          $bot_filter
        GROUP BY v.product_id
        ORDER BY log_views DESC
        LIMIT 6");
    $topRows = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($topRows)) {
        $pids = array_column($topRows, 'product_id');
        $placeholders = implode(',', array_fill(0, count($pids), '?'));
        $pStmt = $pdo->prepare("SELECT id, name, sku, main_image, price, view_count FROM products WHERE id IN ($placeholders)");
        $pStmt->execute($pids);
        $pMap = [];
        foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
            $pMap[$pr['id']] = $pr;
        }
        foreach ($topRows as $tr) {
            $pid = $tr['product_id'];
            if (isset($pMap[$pid])) {
                $item = $pMap[$pid];
                $item['log_views'] = (int)$tr['log_views'];
                $top_products[] = $item;
            }
        }
    }

    // Fallback if not enough log views: fill from catalog view_count
    if (count($top_products) < 6) {
        $needed = 6 - count($top_products);
        $excludeIds = !empty($top_products) ? array_column($top_products, 'id') : [0];
        $exPlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $fbStmt = $pdo->prepare("SELECT id, name, sku, main_image, price, view_count 
            FROM products 
            WHERE deleted_at IS NULL AND (status = 'published' OR status IS NULL) AND id NOT IN ($exPlaceholders)
            ORDER BY view_count DESC, id DESC 
            LIMIT $needed");
        $fbStmt->execute($excludeIds);
        foreach ($fbStmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
            $pr['log_views'] = (int)($pr['view_count'] ?? 0);
            $top_products[] = $pr;
        }
    }
} catch (Exception $e) {
}

// 4. Top Viewed Categories (Fast index aggregation on visitor_logs)
try {
    $topCatsStmt = $pdo->query("SELECT v.category_id, COUNT(*) as cat_views
        FROM visitor_logs v
        WHERE v.category_id IS NOT NULL 
          AND v.category_id > 0 
          AND v.created_at >= DATE_SUB(NOW(), $interval_sql)
          $bot_filter
        GROUP BY v.category_id
        ORDER BY cat_views DESC
        LIMIT 5");
    $topCatRows = $topCatsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($topCatRows)) {
        $cids = array_column($topCatRows, 'category_id');
        $cPlaceholders = implode(',', array_fill(0, count($cids), '?'));
        $cStmt = $pdo->prepare("SELECT id, name, slug FROM categories WHERE id IN ($cPlaceholders)");
        $cStmt->execute($cids);
        $cMap = [];
        foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $cr) {
            $cMap[$cr['id']] = $cr;
        }
        foreach ($topCatRows as $tr) {
            $cid = $tr['category_id'];
            if (isset($cMap[$cid])) {
                $item = $cMap[$cid];
                $item['cat_views'] = (int)$tr['cat_views'];
                $top_categories[] = $item;
            }
        }
    }

    // Fallback if needed: fill from catalog view_count
    if (count($top_categories) < 5) {
        $needed = 5 - count($top_categories);
        $excludeCids = !empty($top_categories) ? array_column($top_categories, 'id') : [0];
        $exCPlaceholders = implode(',', array_fill(0, count($excludeCids), '?'));
        $fbCStmt = $pdo->prepare("SELECT id, name, slug, view_count 
            FROM categories 
            WHERE deleted_at IS NULL AND id NOT IN ($exCPlaceholders)
            ORDER BY view_count DESC, id ASC 
            LIMIT $needed");
        $fbCStmt->execute($excludeCids);
        foreach ($fbCStmt->fetchAll(PDO::FETCH_ASSOC) as $cr) {
            $cr['cat_views'] = (int)($cr['view_count'] ?? 0);
            $top_categories[] = $cr;
        }
    }
} catch (Exception $e) {
}

// 5. Live Visitor Log Stream (excluding bots)
try {
    $logsStmt = $pdo->query("SELECT * FROM visitor_logs v WHERE 1=1 $bot_filter ORDER BY v.id DESC LIMIT 30");
    $recent_logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// 6. Device Breakdown (excluding Search Bot)
try {
    $devStmt = $pdo->query("SELECT v.device_type, COUNT(*) as cnt FROM visitor_logs v WHERE v.created_at >= DATE_SUB(NOW(), $interval_sql) $bot_filter GROUP BY v.device_type ORDER BY cnt DESC");
    $device_breakdown = $devStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 7. Page Type Breakdown
try {
    $ptStmt = $pdo->query("SELECT v.page_type, COUNT(*) as views, COUNT(DISTINCT v.ip_address) as unique_ips FROM visitor_logs v WHERE v.created_at >= DATE_SUB(NOW(), $interval_sql) $bot_filter GROUP BY v.page_type ORDER BY views DESC");
    $page_type_breakdown = $ptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 8. Daily Trend
try {
    $trendStmt = $pdo->query("SELECT DATE(v.created_at) as day, COUNT(*) as views, COUNT(DISTINCT v.ip_address) as visitors FROM visitor_logs v WHERE v.created_at >= DATE_SUB(NOW(), $interval_sql) $bot_filter GROUP BY DATE(v.created_at) ORDER BY day ASC");
    $daily_trend = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 9. Top Search Terms
try {
    $searchStmt = $pdo->query("SELECT search_term, COUNT(*) as cnt, AVG(results_count) as avg_res FROM search_logs WHERE created_at >= DATE_SUB(NOW(), $interval_sql) GROUP BY search_term ORDER BY cnt DESC LIMIT 10");
    $top_searches = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<div class="dashboard-header-banner" style="margin-bottom: 16px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting">
            <h1>Traffic &amp; Visitor Analytics</h1>
            <span class="shadcn-badge shadcn-badge-sky" style="font-size: 11px; padding: 2px 8px;">
                Live Tracker
            </span>
        </div>
        <p class="dashboard-subtitle">
            Monitor real-time visitors, pageviews, session engagement, and traffic channels.
        </p>
    </div>
    <div class="dashboard-actions">
        <!-- Date Range Filter Selector (Products Style) -->
        <div style="display: flex; background: #f4f4f5; border-radius: 6px; padding: 2px; border: 1px solid #e4e4e7;">
            <a href="analytics.php?range=today" class="shadcn-btn <?php echo ($range === 'today') ? 'shadcn-btn-primary' : 'shadcn-btn-ghost'; ?>" style="height: 28px; font-size: 12px; padding: 0 10px; text-decoration: none;">Today</a>
            <a href="analytics.php?range=7days" class="shadcn-btn <?php echo ($range === '7days') ? 'shadcn-btn-primary' : 'shadcn-btn-ghost'; ?>" style="height: 28px; font-size: 12px; padding: 0 10px; text-decoration: none;">7 Days</a>
            <a href="analytics.php?range=30days" class="shadcn-btn <?php echo ($range === '30days') ? 'shadcn-btn-primary' : 'shadcn-btn-ghost'; ?>" style="height: 28px; font-size: 12px; padding: 0 10px; text-decoration: none;">30 Days</a>
            <a href="analytics.php?range=90days" class="shadcn-btn <?php echo ($range === '90days') ? 'shadcn-btn-primary' : 'shadcn-btn-ghost'; ?>" style="height: 28px; font-size: 12px; padding: 0 10px; text-decoration: none;">90 Days</a>
        </div>
    </div>
</div>

<!-- Navigation Tabs (Aligned with products.php tab bar) -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
        <a href="analytics.php?range=<?php echo $range; ?>" class="shadcn-btn shadcn-btn-primary" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Overview
        </a>
        <a href="analytics-campaigns.php?range=<?php echo $range; ?>" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Facebook &amp; Campaigns
        </a>
        <a href="analytics-products.php?range=<?php echo $range; ?>" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Products
        </a>
        <a href="analytics-categories.php?range=<?php echo $range; ?>" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Categories
        </a>
        <a href="analytics-pages.php?range=<?php echo $range; ?>" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Pages &amp; Search
        </a>
    </div>
    <div style="display: flex; align-items: center; gap: 8px;">
        <a href="checkout-visitors.php" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; text-decoration: none; color: #71717a;" title="View shoppers who reached checkout">
            <i class="fa-solid fa-cart-shopping" style="margin-right: 5px; font-size: 11px;"></i> Checkout Visitors
            <span style="background: #f4f4f5; color: #09090b; font-size: 10.5px; padding: 1px 6px; border-radius: 999px; font-weight: 600; margin-left: 4px;">
                <?php echo number_format($checkout_visits_count); ?>
            </span>
        </a>
    </div>
</div>

<!-- Compact Neutral KPI Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-bottom: 16px;">
    <!-- Pageviews -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Total Views</span>
            <i class="fa-solid fa-eye" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;"><?php echo number_format($total_pageviews); ?></div>
            <div style="font-size: 11px; color: #71717a; margin-top: 2px;">All visitor hits</div>
        </div>
    </div>

    <!-- Unique Visitors -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Unique Visitors</span>
            <i class="fa-solid fa-users" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;"><?php echo number_format($unique_visitors); ?></div>
            <div style="font-size: 11px; color: #71717a; margin-top: 2px;">Distinct IPs</div>
        </div>
    </div>

    <!-- Sessions -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Sessions</span>
            <i class="fa-solid fa-window-restore" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;"><?php echo number_format($total_sessions); ?></div>
            <div style="font-size: 11px; color: #71717a; margin-top: 2px;">30-min browsing sessions</div>
        </div>
    </div>

    <!-- Bounce Rate -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Bounce Rate</span>
            <i class="fa-solid fa-person-walking-arrow-right" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;"><?php echo $bounce_rate; ?>%</div>
            <div style="font-size: 11px; color: #71717a; margin-top: 2px;">Single-page visits</div>
        </div>
    </div>

    <!-- Pages / Session -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Pages / Session</span>
            <i class="fa-solid fa-layer-group" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;"><?php echo $avg_pages_per_session; ?></div>
            <div style="font-size: 11px; color: #71717a; margin-top: 2px;">Avg depth per visit</div>
        </div>
    </div>

    <!-- Top Source -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Top Source</span>
            <i class="fa-solid fa-compass" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div style="font-size: 15px; font-weight: 700; color: #09090b; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($top_source_name); ?></div>
            <div style="font-size: 11px; color: #71717a; margin-top: 2px;">Leading channel</div>
        </div>
    </div>
</div>

<!-- Page Type Breakdown Table -->
<?php if (!empty($page_type_breakdown)): ?>
<div class="shadcn-card" style="margin-bottom: 16px;">
    <div class="shadcn-card-header">
        <h2 class="shadcn-card-title"><i class="fa-solid fa-sitemap" style="color: #71717a; margin-right: 6px;"></i> Page Type Breakdown</h2>
    </div>
    <div class="shadcn-card-body">
        <table class="wp-list-table widefat fixed striped" style="margin: 0;">
            <thead>
                <tr>
                    <th style="font-size: 11.5px; font-weight: 600; color: #71717a;">Page Type</th>
                    <th style="text-align: right; font-size: 11.5px; font-weight: 600; color: #71717a;">Views</th>
                    <th style="text-align: right; font-size: 11.5px; font-weight: 600; color: #71717a;">Unique Visitors</th>
                    <th style="text-align: right; font-size: 11.5px; font-weight: 600; color: #71717a;">% of Traffic</th>
                    <th style="width: 30%; font-size: 11.5px; font-weight: 600; color: #71717a;">Distribution</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($page_type_breakdown as $pt): 
                    $pct = $total_pageviews > 0 ? round(($pt['views'] / $total_pageviews) * 100, 1) : 0;
                ?>
                <tr>
                    <td>
                        <span style="display: inline-block; padding: 1px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; color: #09090b; background: #f4f4f5; text-transform: uppercase;">
                            <?php echo htmlspecialchars($pt['page_type']); ?>
                        </span>
                    </td>
                    <td style="text-align: right; font-weight: 600; color: #09090b; font-size: 12px;"><?php echo number_format($pt['views']); ?></td>
                    <td style="text-align: right; color: #71717a; font-size: 12px;"><?php echo number_format($pt['unique_ips']); ?></td>
                    <td style="text-align: right; font-weight: 600; color: #71717a; font-size: 12px;"><?php echo $pct; ?>%</td>
                    <td>
                        <div style="width: 100%; background: #f4f4f5; height: 6px; border-radius: 3px; overflow: hidden;">
                            <div style="width: <?php echo $pct; ?>%; background: #09090b; height: 100%; border-radius: 3px; transition: width 0.3s;"></div>
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
<div class="wp-editor-columns" style="gap: 16px;">

    <!-- Left Main Column: Live Traffic Stream & Products -->
    <div class="main-column">

        <!-- Live Visitor Stream -->
        <div class="shadcn-card" style="margin-bottom: 16px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title">
                    <i class="fa-solid fa-tower-cell" style="color: #71717a; margin-right: 6px;"></i> Live Visitor Activity Stream
                </h2>
                <span style="font-size: 11px; color: #71717a;">Auto-recorded</span>
            </div>
            <div class="shadcn-card-body" style="max-height: 440px; overflow-y: auto;">
                <?php if (empty($recent_logs)): ?>
                    <div style="text-align: center; padding: 40px; color: #71717a;">
                        <i class="fa-solid fa-user-clock" style="font-size: 32px; margin-bottom: 8px; color: #d4d4d8;"></i>
                        <p style="margin: 0; font-size: 13px;">No visitor traffic logs recorded yet. Real-time visits will stream here.</p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" style="margin: 0; font-size: 12px;">
                        <thead style="position: sticky; top: 0; z-index: 10; background: #fff;">
                            <tr>
                                <th style="width: 14%; font-size: 11.5px; color: #71717a;">IP Address</th>
                                <th style="width: 14%; font-size: 11.5px; color: #71717a;">Source</th>
                                <th style="width: 10%; font-size: 11.5px; color: #71717a;">Type</th>
                                <th style="width: 30%; font-size: 11.5px; color: #71717a;">Visited Page</th>
                                <th style="width: 10%; font-size: 11.5px; color: #71717a;">Device</th>
                                <th style="width: 10%; font-size: 11.5px; color: #71717a;">Session</th>
                                <th style="width: 12%; text-align: right; font-size: 11.5px; color: #71717a;">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><code style="font-family: monospace; font-size: 11px; color: #09090b;"><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                    <td>
                                        <span style="background: #f4f4f5; color: #09090b; border: 1px solid #e4e4e7; padding: 1px 6px; border-radius: 4px; font-size: 10.5px; font-weight: 500; white-space: nowrap;">
                                            <?php echo htmlspecialchars($log['traffic_source']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size: 10.5px; font-weight: 600; color: #71717a; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($log['page_type'] ?? 'other'); ?>
                                        </span>
                                    </td>
                                    <td style="word-break: break-all;">
                                        <span style="font-size: 11.5px; color: #09090b; font-weight: 500;">
                                            <?php echo htmlspecialchars($log['page_url']); ?>
                                        </span>
                                    </td>
                                    <td style="white-space: nowrap; font-size: 11.5px; color: #71717a;">
                                        <i class="fa-solid fa-<?php echo strtolower($log['device_type']) === 'mobile' ? 'mobile-screen' : (strtolower($log['device_type']) === 'tablet' ? 'tablet-screen-button' : 'desktop'); ?>"></i>
                                        <?php echo htmlspecialchars($log['device_type']); ?>
                                    </td>
                                    <td>
                                        <code style="font-size: 10px; color: #71717a;"><?php echo htmlspecialchars(substr($log['session_id'] ?? '-', 0, 8)); ?></code>
                                    </td>
                                    <td style="text-align: right; color: #71717a; font-size: 11.5px;">
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
        <div class="shadcn-card" style="margin-bottom: 16px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title"><i class="fa-solid fa-fire" style="color: #71717a; margin-right: 6px;"></i> Top Viewed Products</h2>
                <a href="analytics-products.php?range=<?php echo $range; ?>" style="font-size: 12px; font-weight: 500; color: #09090b; text-decoration: none;">View All Products &rarr;</a>
            </div>
            <div class="postbox-body" style="padding: 0;">
                <?php 
                $has_product_views = false;
                foreach ($top_products as $tp_check) {
                    if (max(($tp_check['log_views'] ?? 0), ($tp_check['view_count'] ?? 0)) > 0) {
                        $has_product_views = true;
                        break;
                    }
                }
                ?>
                <?php if (empty($top_products) || !$has_product_views): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                        <div style="font-size: 28px; color: #cbd5e1; margin-bottom: 8px;"><i class="fa-solid fa-eye-slash"></i></div>
                        <div style="font-size: 14px; font-weight: 600; color: #1e293b; margin-bottom: 4px;">No Product Views Recorded Yet</div>
                        <p style="margin: 0; font-size: 12.5px; color: #64748b;">When shoppers browse your products, the most popular items will appear here.</p>
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
        <div class="shadcn-card" style="margin-bottom: 16px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title"><i class="fa-solid fa-chart-pie" style="color: #71717a; margin-right: 6px;"></i> Traffic Sources</h2>
                <a href="analytics-campaigns.php?range=<?php echo $range; ?>" style="font-size: 11.5px; color: #09090b; text-decoration: none; font-weight: 500;">
                    Campaigns &rarr;
                </a>
            </div>
            <div class="shadcn-card-body" style="padding: 14px;">
                <?php if (empty($traffic_sources)): ?>
                    <p style="text-align: center; color: #71717a; font-size: 12.5px; margin: 6px 0;">No traffic source data available.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($traffic_sources as $src): ?>
                            <?php
                            $pct = $total_pageviews > 0 ? round(($src['cnt'] / $total_pageviews) * 100) : 0;
                            $sourceName = $src['traffic_source'];
                            $isAd = str_contains($sourceName, 'Ads');
                            ?>
                            <div>
                                <div style="display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 500; margin-bottom: 3px; color: #09090b;">
                                    <span style="display: flex; align-items: center; gap: 5px;">
                                        <?php echo htmlspecialchars($sourceName); ?>
                                        <?php if ($isAd): ?>
                                            <span style="background: #f4f4f5; color: #09090b; border: 1px solid #e4e4e7; font-size: 9px; padding: 1px 4px; border-radius: 3px; font-weight: 600;">PAID</span>
                                        <?php endif; ?>
                                    </span>
                                    <span style="color: #71717a;"><?php echo number_format($src['cnt']); ?> (<?php echo $pct; ?>%)</span>
                                </div>
                                <div style="width: 100%; background: #f4f4f5; height: 5px; border-radius: 3px; overflow: hidden;">
                                    <div style="width: <?php echo $pct; ?>%; background: #09090b; height: 100%; border-radius: 3px;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Device Breakdown -->
        <div class="shadcn-card" style="margin-bottom: 16px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title"><i class="fa-solid fa-mobile-screen" style="color: #71717a; margin-right: 6px;"></i> Devices</h2>
            </div>
            <div class="shadcn-card-body" style="padding: 14px;">
                <?php if (empty($device_breakdown)): ?>
                    <p style="text-align: center; color: #71717a; font-size: 12.5px; margin: 6px 0;">No device data.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php 
                        $deviceIcons = ['Mobile' => 'fa-mobile-screen', 'Desktop' => 'fa-desktop', 'Tablet' => 'fa-tablet-screen-button', 'Search Bot' => 'fa-robot'];
                        foreach ($device_breakdown as $dev):
                            $devPct = $total_pageviews > 0 ? round(($dev['cnt'] / $total_pageviews) * 100, 1) : 0;
                            $dIcon = $deviceIcons[$dev['device_type']] ?? 'fa-question';
                        ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 500; color: #09090b; margin-bottom: 3px;">
                                <span style="display: flex; align-items: center; gap: 6px;">
                                    <i class="fa-solid <?php echo $dIcon; ?>" style="color: #71717a; font-size: 11px;"></i>
                                    <?php echo htmlspecialchars($dev['device_type']); ?>
                                </span>
                                <span style="color: #71717a;"><?php echo number_format($dev['cnt']); ?> (<?php echo $devPct; ?>%)</span>
                            </div>
                            <div style="width: 100%; background: #f4f4f5; height: 5px; border-radius: 3px; overflow: hidden;">
                                <div style="width: <?php echo $devPct; ?>%; background: #71717a; height: 100%; border-radius: 3px;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Categories Breakdown -->
        <div class="shadcn-card" style="margin-bottom: 16px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title"><i class="fa-solid fa-layer-group" style="color: #71717a; margin-right: 6px;"></i> Categories</h2>
                <a href="analytics-categories.php?range=<?php echo $range; ?>" style="font-size: 11.5px; font-weight: 500; color: #09090b; text-decoration: none;">View All &rarr;</a>
            </div>
            <div class="shadcn-card-body" style="padding: 10px 14px;">
                <?php if (empty($top_categories)): ?>
                    <p style="text-align: center; color: #71717a; font-size: 12.5px; margin: 6px 0;">No categories found.</p>
                <?php else: ?>
                    <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px;">
                        <?php foreach ($top_categories as $cat): ?>
                            <li style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 6px; border-bottom: 1px solid #f4f4f5; font-size: 12px;">
                                <span style="font-weight: 500; color: #09090b;">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </span>
                                <span style="font-size: 11px; font-weight: 600; color: #71717a;">
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
        <div class="shadcn-card" style="margin-bottom: 16px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title"><i class="fa-solid fa-magnifying-glass" style="color: #71717a; margin-right: 6px;"></i> Top Searches</h2>
                <a href="analytics-pages.php?range=<?php echo $range; ?>" style="font-size: 11.5px; font-weight: 500; color: #09090b; text-decoration: none;">All &rarr;</a>
            </div>
            <div class="shadcn-card-body">
                <table class="wp-list-table widefat fixed striped" style="margin: 0; font-size: 11.5px;">
                    <tbody>
                        <?php foreach ($top_searches as $st): ?>
                        <tr>
                            <td style="font-weight: 500; color: #09090b;">
                                "<?php echo htmlspecialchars($st['search_term']); ?>"
                            </td>
                            <td style="text-align: right; color: #71717a;">
                                <span style="font-weight: 600; color: #09090b;"><?php echo number_format($st['cnt']); ?></span> searches
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
