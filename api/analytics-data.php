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

try {
    switch ($action) {

        // ===================== OVERVIEW KPIs =====================
        case 'overview':
            // Total pageviews & unique visitors
            $stats = $pdo->query("SELECT COUNT(*) as pageviews, COUNT(DISTINCT ip_address) as unique_visitors, COUNT(DISTINCT session_id) as sessions FROM visitor_logs v WHERE 1=1 $date_filter")->fetch(PDO::FETCH_ASSOC);

            // Bounce rate: sessions with only 1 hit
            $bounceData = $pdo->query("SELECT COUNT(*) as bounced FROM (SELECT session_id, COUNT(*) as hits FROM visitor_logs v WHERE session_id IS NOT NULL AND session_id != '' $date_filter GROUP BY session_id HAVING hits = 1) sub")->fetch(PDO::FETCH_ASSOC);
            $totalSessions = max(1, (int)$stats['sessions']);
            $bounceRate = round(((int)($bounceData['bounced'] ?? 0) / $totalSessions) * 100, 1);

            // Avg pages per session
            $avgPages = round((int)$stats['pageviews'] / $totalSessions, 1);

            // Page type breakdown
            $pageTypes = $pdo->query("SELECT page_type, COUNT(*) as views, COUNT(DISTINCT ip_address) as unique_ips FROM visitor_logs v WHERE 1=1 $date_filter GROUP BY page_type ORDER BY views DESC")->fetchAll(PDO::FETCH_ASSOC);

            // Traffic sources
            $sources = $pdo->query("SELECT traffic_source, COUNT(*) as views FROM visitor_logs v WHERE 1=1 $date_filter GROUP BY traffic_source ORDER BY views DESC")->fetchAll(PDO::FETCH_ASSOC);

            // Device breakdown
            $devices = $pdo->query("SELECT device_type, COUNT(*) as views FROM visitor_logs v WHERE 1=1 $date_filter GROUP BY device_type ORDER BY views DESC")->fetchAll(PDO::FETCH_ASSOC);

            // Daily trend (last N days)
            $trend = $pdo->query("SELECT DATE(created_at) as day, COUNT(*) as views, COUNT(DISTINCT ip_address) as visitors FROM visitor_logs v WHERE 1=1 $date_filter GROUP BY DATE(created_at) ORDER BY day ASC")->fetchAll(PDO::FETCH_ASSOC);

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
                (SELECT COUNT(*) FROM visitor_logs v WHERE v.product_id = p.id $date_filter) as log_views,
                (SELECT COUNT(DISTINCT v.ip_address) FROM visitor_logs v WHERE v.product_id = p.id $date_filter) as unique_visitors,
                (SELECT COUNT(DISTINCT v.session_id) FROM visitor_logs v WHERE v.product_id = p.id AND v.session_id IS NOT NULL $date_filter) as sessions,
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
                (SELECT COUNT(*) FROM visitor_logs v WHERE v.category_id = c.id $date_filter) as log_views,
                (SELECT COUNT(DISTINCT v.ip_address) FROM visitor_logs v WHERE v.category_id = c.id $date_filter) as unique_visitors,
                (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) as product_count,
                (SELECT p2.name FROM products p2 JOIN visitor_logs v2 ON v2.product_id = p2.id WHERE p2.category_id = c.id AND p2.deleted_at IS NULL $date_filter GROUP BY p2.id ORDER BY COUNT(*) DESC LIMIT 1) as top_product
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
                (SELECT v2.traffic_source FROM visitor_logs v2 WHERE v2.page_url = v.page_url GROUP BY v2.traffic_source ORDER BY COUNT(*) DESC LIMIT 1) as top_source,
                MAX(v.created_at) as last_visit
                FROM visitor_logs v
                WHERE 1=1 $date_filter $type_where
                GROUP BY v.page_url, v.page_type
                ORDER BY views DESC
                LIMIT $per_page OFFSET $offset";
            $pages = $pdo->query($dataSql)->fetchAll(PDO::FETCH_ASSOC);

            $countSql = "SELECT COUNT(DISTINCT page_url) FROM visitor_logs v WHERE 1=1 $date_filter $type_where";
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

        // ===================== LIVE STREAM =====================
        case 'live_stream':
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
            $logs = $pdo->query("SELECT * FROM visitor_logs ORDER BY id DESC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
