<?php
// admin/api/analytics-data.php
// AJAX data endpoint for admin analytics sub-pages
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$range = $_GET['range'] ?? '7days';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
$sort = $_GET['sort'] ?? 'views';
$order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$offset = ($page - 1) * $per_page;

// Date range
$interval_sql = "INTERVAL 7 DAY";
if ($range === 'today') $interval_sql = "INTERVAL 1 DAY";
elseif ($range === '30days') $interval_sql = "INTERVAL 30 DAY";
elseif ($range === '90days') $interval_sql = "INTERVAL 90 DAY";
elseif ($range === 'all') $interval_sql = "INTERVAL 10 YEAR";

$date_filter = "AND v.created_at >= DATE_SUB(NOW(), $interval_sql)";
$bot_filter = "AND (v.traffic_source != 'Googlebot / Crawler' AND v.device_type != 'Search Bot' AND v.traffic_source NOT LIKE '%bot%' AND v.traffic_source NOT LIKE '%crawler%')";

try {
    switch ($action) {

        // ===================== OVERVIEW KPIs =====================
        case 'overview':
            // Total pageviews & unique visitors
            $stats = $pdo->query("SELECT COUNT(*) as pageviews, COUNT(DISTINCT ip_address) as unique_visitors, COUNT(DISTINCT session_id) as sessions FROM visitor_logs v WHERE 1=1 $date_filter $bot_filter")->fetch(PDO::FETCH_ASSOC);

            // Bounce rate: sessions with only 1 hit
            $bounceData = $pdo->query("SELECT COUNT(*) as bounced FROM (SELECT session_id, COUNT(*) as hits FROM visitor_logs v WHERE session_id IS NOT NULL AND session_id != '' $date_filter $bot_filter GROUP BY session_id HAVING hits = 1) sub")->fetch(PDO::FETCH_ASSOC);
            $totalSessions = max(1, (int)$stats['sessions']);
            $bounceRate = round(((int)($bounceData['bounced'] ?? 0) / $totalSessions) * 100, 1);

            // Avg pages per session
            $avgPages = round((int)$stats['pageviews'] / $totalSessions, 1);

            // Page type breakdown
            $pageTypes = $pdo->query("SELECT page_type, COUNT(*) as views, COUNT(DISTINCT ip_address) as unique_ips FROM visitor_logs v WHERE 1=1 $date_filter $bot_filter GROUP BY page_type ORDER BY views DESC")->fetchAll(PDO::FETCH_ASSOC);

            // Traffic sources
            $sources = $pdo->query("SELECT traffic_source, COUNT(*) as views FROM visitor_logs v WHERE 1=1 $date_filter $bot_filter GROUP BY traffic_source ORDER BY views DESC")->fetchAll(PDO::FETCH_ASSOC);

            // Device breakdown
            $devices = $pdo->query("SELECT device_type, COUNT(*) as views FROM visitor_logs v WHERE 1=1 $date_filter $bot_filter GROUP BY device_type ORDER BY views DESC")->fetchAll(PDO::FETCH_ASSOC);

            // Daily trend (last N days)
            $trend = $pdo->query("SELECT DATE(created_at) as day, COUNT(*) as views, COUNT(DISTINCT ip_address) as visitors FROM visitor_logs v WHERE 1=1 $date_filter $bot_filter GROUP BY DATE(created_at) ORDER BY day ASC")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'pageviews' => (int)$stats['pageviews'],
                'unique_visitors' => (int)$stats['unique_visitors'],
                'sessions' => $totalSessions,
                'bounce_rate' => $bounceRate,
                'avg_pages_per_session' => $avgPages,
                'page_types' => $pageTypes,
                'traffic_sources' => $sources,
                'devices' => $devices,
                'daily_trend' => $trend,
            ]);
            break;

        // ===================== PRODUCT ANALYTICS =====================
        case 'products':
            $search = trim($_GET['search'] ?? '');
            $where_search = '';
            $params = [];
            if ($search) {
                $where_search = "AND (p.name LIKE ? OR p.sku LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            // Count
            $countSql = "SELECT COUNT(*) FROM products p WHERE p.deleted_at IS NULL AND (p.status = 'published' OR p.status IS NULL) $where_search";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Data
            $dataSql = "SELECT p.id, p.name, p.slug, p.sku, p.main_image, p.price, p.sale_price, p.view_count,
                (SELECT COUNT(*) FROM visitor_logs v WHERE v.product_id = p.id $date_filter $bot_filter) as log_views,
                (SELECT COUNT(DISTINCT v.ip_address) FROM visitor_logs v WHERE v.product_id = p.id $date_filter $bot_filter) as unique_visitors,
                (SELECT COUNT(DISTINCT v.session_id) FROM visitor_logs v WHERE v.product_id = p.id AND v.session_id IS NOT NULL $date_filter $bot_filter) as sessions,
                (SELECT COUNT(*) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.product_id = p.id AND o.status != 'Cancelled' AND o.created_at >= DATE_SUB(NOW(), $interval_sql)) as orders_count
                FROM products p
                WHERE p.deleted_at IS NULL AND (p.status = 'published' OR p.status IS NULL) $where_search
                ORDER BY log_views DESC, p.view_count DESC
                LIMIT $per_page OFFSET $offset";
            $dataStmt = $pdo->prepare($dataSql);
            $dataStmt->execute($params);
            $products = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

            // Compute conversion rate
            foreach ($products as &$pr) {
                $views = max(1, (int)$pr['log_views'] ?: (int)$pr['view_count']);
                $pr['conversion_rate'] = round(((int)$pr['orders_count'] / $views) * 100, 2);
            }
            unset($pr);

            echo json_encode([
                'success' => true,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'products' => $products,
            ]);
            break;

        // ===================== CATEGORY ANALYTICS =====================
        case 'categories':
            $dataSql = "SELECT c.id, c.name, c.slug, c.image_path, c.view_count,
                (SELECT COUNT(*) FROM visitor_logs v WHERE v.category_id = c.id $date_filter $bot_filter) as log_views,
                (SELECT COUNT(DISTINCT v.ip_address) FROM visitor_logs v WHERE v.category_id = c.id $date_filter $bot_filter) as unique_visitors,
                (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) as product_count,
                (SELECT p2.name FROM products p2 JOIN visitor_logs v2 ON v2.product_id = p2.id WHERE p2.category_id = c.id AND p2.deleted_at IS NULL $date_filter AND (v2.traffic_source != 'Googlebot / Crawler' AND v2.device_type != 'Search Bot' AND v2.traffic_source NOT LIKE '%bot%' AND v2.traffic_source NOT LIKE '%crawler%') GROUP BY p2.id ORDER BY COUNT(*) DESC LIMIT 1) as top_product
                FROM categories c
                WHERE c.deleted_at IS NULL
                ORDER BY log_views DESC, c.view_count DESC";
            $categories = $pdo->query($dataSql)->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'categories' => $categories,
            ]);
            break;

        // ===================== PAGES / URL ANALYTICS =====================
        case 'pages':
            $filter_type = $_GET['page_type'] ?? '';
            $type_where = '';
            if ($filter_type) {
                $type_where = "AND v.page_type = " . $pdo->quote($filter_type);
            }

            $dataSql = "SELECT v.page_url, v.page_type,
                COUNT(*) as views,
                COUNT(DISTINCT v.ip_address) as unique_ips,
                (SELECT v2.traffic_source FROM visitor_logs v2 WHERE v2.page_url = v.page_url AND (v2.traffic_source != 'Googlebot / Crawler' AND v2.device_type != 'Search Bot' AND v2.traffic_source NOT LIKE '%bot%' AND v2.traffic_source NOT LIKE '%crawler%') GROUP BY v2.traffic_source ORDER BY COUNT(*) DESC LIMIT 1) as top_source,
                MAX(v.created_at) as last_visit
                FROM visitor_logs v
                WHERE 1=1 $date_filter $type_where $bot_filter
                GROUP BY v.page_url, v.page_type
                ORDER BY views DESC
                LIMIT $per_page OFFSET $offset";
            $pages = $pdo->query($dataSql)->fetchAll(PDO::FETCH_ASSOC);

            $countSql = "SELECT COUNT(DISTINCT page_url) FROM visitor_logs v WHERE 1=1 $date_filter $type_where $bot_filter";
            $total = (int)$pdo->query($countSql)->fetchColumn();

            echo json_encode([
                'success' => true,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'pages' => $pages,
            ]);
            break;

        // ===================== SEARCH ANALYTICS =====================
        case 'searches':
            $dataSql = "SELECT search_term, COUNT(*) as search_count, AVG(results_count) as avg_results, MAX(created_at) as last_searched
                FROM search_logs
                WHERE created_at >= DATE_SUB(NOW(), $interval_sql)
                GROUP BY search_term
                ORDER BY search_count DESC
                LIMIT 50";
            $searches = $pdo->query($dataSql)->fetchAll(PDO::FETCH_ASSOC);

            // Zero-result searches
            $zeroSql = "SELECT search_term, COUNT(*) as search_count, MAX(created_at) as last_searched
                FROM search_logs
                WHERE results_count = 0 AND created_at >= DATE_SUB(NOW(), $interval_sql)
                GROUP BY search_term
                ORDER BY search_count DESC
                LIMIT 20";
            $zeroResults = $pdo->query($zeroSql)->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'searches' => $searches,
                'zero_results' => $zeroResults,
            ]);
            break;

        // ===================== CAMPAIGN & TRAFFIC SOURCES =====================
        case 'campaigns':
            // 1. Overall stats for date range (human visitors only)
            $totStats = $pdo->query("SELECT 
                COUNT(*) as total_views, 
                COUNT(DISTINCT ip_address) as total_visitors,
                COUNT(DISTINCT session_id) as total_sessions
                FROM visitor_logs v 
                WHERE 1=1 $date_filter $bot_filter")->fetch(PDO::FETCH_ASSOC);

            $totalViews = max(1, (int)$totStats['total_views']);
            $totalVisitors = (int)$totStats['total_visitors'];
            $totalSessions = max(1, (int)$totStats['total_sessions']);

            // 2. Facebook Traffic (Ads + Organic + Meta Audience Network)
            $fbStats = $pdo->query("SELECT 
                COUNT(*) as total_fb_views,
                COUNT(DISTINCT ip_address) as total_fb_visitors,
                COUNT(DISTINCT session_id) as total_fb_sessions,
                SUM(CASE WHEN traffic_source = 'Facebook Ads' OR utm_source IN ('fb', 'facebook', 'an') OR (utm_source LIKE '%facebook%' AND (utm_campaign IS NOT NULL AND utm_campaign != '' OR fbclid IS NOT NULL)) OR fbclid IS NOT NULL THEN 1 ELSE 0 END) as fb_ads_views,
                COUNT(DISTINCT CASE WHEN traffic_source = 'Facebook Ads' OR utm_source IN ('fb', 'facebook', 'an') OR (utm_source LIKE '%facebook%' AND (utm_campaign IS NOT NULL AND utm_campaign != '' OR fbclid IS NOT NULL)) OR fbclid IS NOT NULL THEN ip_address END) as fb_ads_visitors
                FROM visitor_logs v
                WHERE (traffic_source LIKE '%Facebook%' OR utm_source IN ('facebook', 'fb', 'an') OR utm_source LIKE '%facebook%' OR fbclid IS NOT NULL) $date_filter $bot_filter")->fetch(PDO::FETCH_ASSOC);

            // 3. Instagram Traffic (recognizing 'ig' and 'instagram')
            $igStats = $pdo->query("SELECT 
                COUNT(*) as total_ig_views,
                COUNT(DISTINCT ip_address) as total_ig_visitors,
                COUNT(DISTINCT session_id) as total_ig_sessions,
                SUM(CASE WHEN traffic_source = 'Instagram Ads' OR utm_source IN ('ig', 'instagram') THEN 1 ELSE 0 END) as ig_ads_views,
                COUNT(DISTINCT CASE WHEN traffic_source = 'Instagram Ads' OR utm_source IN ('ig', 'instagram') THEN ip_address END) as ig_ads_visitors
                FROM visitor_logs v
                WHERE (traffic_source LIKE '%Instagram%' OR utm_source IN ('instagram', 'ig')) $date_filter $bot_filter")->fetch(PDO::FETCH_ASSOC);

            // 4. Google Traffic (Ads + Search — crawlers strictly excluded)
            $googStats = $pdo->query("SELECT 
                COUNT(*) as total_goog_views,
                COUNT(DISTINCT ip_address) as total_goog_visitors,
                SUM(CASE WHEN traffic_source = 'Google Ads' OR gclid IS NOT NULL THEN 1 ELSE 0 END) as goog_ads_views,
                SUM(CASE WHEN traffic_source = 'Google Search' THEN 1 ELSE 0 END) as goog_organic_views
                FROM visitor_logs v
                WHERE (traffic_source IN ('Google Ads', 'Google Search') OR (utm_source LIKE '%google%' AND traffic_source != 'Googlebot / Crawler') OR gclid IS NOT NULL) $date_filter $bot_filter")->fetch(PDO::FETCH_ASSOC);

            // 5. Campaigns Breakdown (UTM Campaign or Ad Clicks)
            $campSql = "SELECT 
                COALESCE(NULLIF(v.utm_campaign, ''), CASE WHEN v.fbclid IS NOT NULL AND v.fbclid != '' THEN 'Facebook Ad (Untagged Campaign)' WHEN v.gclid IS NOT NULL AND v.gclid != '' THEN 'Google Ad (Untagged Campaign)' ELSE 'Direct / Other' END) as campaign_name,
                COALESCE(NULLIF(v.utm_source, ''), CASE WHEN v.traffic_source LIKE '%Facebook%' THEN 'facebook' WHEN v.traffic_source LIKE '%Instagram%' THEN 'instagram' WHEN v.traffic_source LIKE '%Google%' THEN 'google' ELSE 'direct' END) as source,
                COALESCE(NULLIF(v.utm_medium, ''), CASE WHEN v.fbclid IS NOT NULL THEN 'paid' WHEN v.gclid IS NOT NULL THEN 'cpc' ELSE 'referral' END) as medium,
                COUNT(*) as views,
                COUNT(DISTINCT v.ip_address) as visitors,
                COUNT(DISTINCT v.session_id) as sessions,
                COUNT(DISTINCT CASE WHEN v.page_type = 'product' THEN v.session_id END) as product_visits,
                COUNT(DISTINCT CASE WHEN v.page_type IN ('cart', 'checkout') THEN v.session_id END) as cart_or_checkout_visits,
                MAX(COALESCE(v.landing_page, v.page_url)) as landing_page,
                MAX(v.created_at) as last_activity
                FROM visitor_logs v
                WHERE ((v.utm_campaign IS NOT NULL AND v.utm_campaign != '') 
                    OR (v.fbclid IS NOT NULL AND v.fbclid != '') 
                    OR (v.gclid IS NOT NULL AND v.gclid != '')
                    OR v.traffic_source IN ('Facebook Ads', 'Instagram Ads', 'Google Ads')
                    OR v.utm_source IN ('fb', 'ig', 'an', 'wa', 'facebook', 'instagram', 'google'))
                    $date_filter $bot_filter
                GROUP BY campaign_name, source, medium
                ORDER BY visitors DESC, views DESC
                LIMIT 50";
            $campaigns = $pdo->query($campSql)->fetchAll(PDO::FETCH_ASSOC);

            // 6. All Traffic Sources Distribution (Excluding bots)
            $sourcesSql = "SELECT 
                v.traffic_source,
                COUNT(*) as views,
                COUNT(DISTINCT v.ip_address) as visitors,
                COUNT(DISTINCT v.session_id) as sessions,
                ROUND((COUNT(*) / $totalViews) * 100, 1) as percentage
                FROM visitor_logs v
                WHERE 1=1 $date_filter $bot_filter
                GROUP BY v.traffic_source
                ORDER BY views DESC";
            $sources = $pdo->query($sourcesSql)->fetchAll(PDO::FETCH_ASSOC);

            // 7. Recent Campaign Visits Stream (last 30 human visits)
            $recentSql = "SELECT v.id, v.ip_address, v.page_url, v.traffic_source, v.utm_source, v.utm_medium, v.utm_campaign, v.utm_content, v.fbclid, v.device_type, v.created_at
                FROM visitor_logs v
                WHERE (v.traffic_source LIKE '%Facebook%' 
                    OR v.traffic_source LIKE '%Instagram%' 
                    OR v.traffic_source IN ('Google Ads', 'Google Search')
                    OR (v.utm_source LIKE '%google%' AND v.traffic_source != 'Googlebot / Crawler')
                    OR (v.utm_campaign IS NOT NULL AND v.utm_campaign != '') 
                    OR v.fbclid IS NOT NULL 
                    OR v.gclid IS NOT NULL)
                    $bot_filter
                ORDER BY v.id DESC
                LIMIT 30";
            $recentCampaignLogs = $pdo->query($recentSql)->fetchAll(PDO::FETCH_ASSOC);

            // 8. Top Campaign Name
            $topCampaignName = 'None';
            if (!empty($campaigns)) {
                $topCampaignName = $campaigns[0]['campaign_name'];
            }

            echo json_encode([
                'success' => true,
                'summary' => [
                    'total_views' => $totalViews,
                    'total_visitors' => $totalVisitors,
                    'total_sessions' => $totalSessions,
                    'fb_total_views' => (int)($fbStats['total_fb_views'] ?? 0),
                    'fb_total_visitors' => (int)($fbStats['total_fb_visitors'] ?? 0),
                    'fb_ads_views' => (int)($fbStats['fb_ads_views'] ?? 0),
                    'fb_ads_visitors' => (int)($fbStats['fb_ads_visitors'] ?? 0),
                    'ig_total_views' => (int)($igStats['total_ig_views'] ?? 0),
                    'ig_total_visitors' => (int)($igStats['total_ig_visitors'] ?? 0),
                    'goog_total_views' => (int)($googStats['total_goog_views'] ?? 0),
                    'goog_total_visitors' => (int)($googStats['total_goog_visitors'] ?? 0),
                    'goog_ads_views' => (int)($googStats['goog_ads_views'] ?? 0),
                    'top_campaign' => $topCampaignName
                ],
                'campaigns' => $campaigns,
                'sources' => $sources,
                'recent_logs' => $recentCampaignLogs
            ]);
            break;

        // ===================== LIVE STREAM =====================
        case 'live_stream':
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
            $logs = $pdo->query("SELECT * FROM visitor_logs v WHERE 1=1 $bot_filter ORDER BY v.id DESC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
