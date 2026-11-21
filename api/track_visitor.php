<?php
// admin/api/track_visitor.php
// Live Visitor & Analytics Ingestion API
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';

// Accept JSON payload or standard POST/GET parameters
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_REQUEST;
}

$page_url = trim($input['page_url'] ?? $_SERVER['HTTP_REFERER'] ?? '/');
if (empty($page_url)) {
    $page_url = '/';
}

// Get Client IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip_parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($ip_parts[0]);
}

$referrer = trim($input['referrer'] ?? $_SERVER['HTTP_REFERER'] ?? '');
$user_agent = trim($_SERVER['HTTP_USER_AGENT'] ?? $input['user_agent'] ?? '');
$ua_lower = strtolower($user_agent);
$session_id = trim($input['session_id'] ?? '');

// Detect Search Engine Crawlers & Bots (e.g. Googlebot 66.249.xx.xx)
$is_bot = false;
if (preg_match('/(googlebot|bingbot|yandexbot|baiduspider|duckduckbot|slurp|facebookexternalhit|twitterbot|ahrefsbot|semrushbot|dotbot|rogerbot|curl|wget|python|php)/i', $user_agent) || str_starts_with($ip, '66.249.')) {
    $is_bot = true;
}

// Check for legacy Japanese hack query patterns (e.g., /?w=23413602053, /?x=7029441740)
$queryString = parse_url($page_url, PHP_URL_QUERY) ?? '';
if (preg_match('/^[a-z]=\d+$/i', $queryString)) {
    $is_bot = true;
}

// Parse query string for campaign parameters if present
$queryParts = [];
if (!empty($queryString)) {
    parse_str($queryString, $queryParts);
}

// Extract UTM and ad click tracking identifiers
$utm_source = trim($input['utm_source'] ?? $queryParts['utm_source'] ?? '');
$utm_medium = trim($input['utm_medium'] ?? $queryParts['utm_medium'] ?? '');
$utm_campaign = trim($input['utm_campaign'] ?? $queryParts['utm_campaign'] ?? '');
$utm_content = trim($input['utm_content'] ?? $queryParts['utm_content'] ?? '');
$utm_term = trim($input['utm_term'] ?? $queryParts['utm_term'] ?? '');
$fbclid = trim($input['fbclid'] ?? $queryParts['fbclid'] ?? '');
$gclid = trim($input['gclid'] ?? $queryParts['gclid'] ?? '');
$landing_page = trim($input['landing_page'] ?? $page_url);

$source_lower = strtolower($utm_source);
$medium_lower = strtolower($utm_medium);
$ref_lower = strtolower($referrer);

// Determine Smart Traffic Source
$traffic_source = 'Direct';

if ($is_bot) {
    $traffic_source = 'Googlebot / Crawler';
} elseif (!empty($fbclid) || ($source_lower === 'facebook' && (!empty($utm_campaign) || in_array($medium_lower, ['cpc', 'paid', 'ads', 'ad', 'feed', 'story', 'reels', 'boost'])))) {
    $traffic_source = 'Facebook Ads';
} elseif ($source_lower === 'instagram' && (!empty($utm_campaign) || in_array($medium_lower, ['cpc', 'paid', 'ads', 'ad', 'feed', 'story', 'reels']))) {
    $traffic_source = 'Instagram Ads';
} elseif (str_contains($ref_lower, 'facebook.com') || str_contains($ref_lower, 'fb.me') || $source_lower === 'facebook' || $source_lower === 'fb') {
    $traffic_source = 'Facebook (Organic)';
} elseif (str_contains($ref_lower, 'instagram.com') || str_contains($ref_lower, 'l.instagram.com') || $source_lower === 'instagram') {
    $traffic_source = 'Instagram (Organic)';
} elseif (!empty($gclid) || ($source_lower === 'google' && in_array($medium_lower, ['cpc', 'paid', 'ads', 'adwords']))) {
    $traffic_source = 'Google Ads';
} elseif (str_contains($ref_lower, 'google.com') || str_contains($ref_lower, 'google.co.in') || $source_lower === 'google') {
    $traffic_source = 'Google Search';
} elseif (str_contains($ref_lower, 'youtube.com') || $source_lower === 'youtube') {
    $traffic_source = 'YouTube';
} elseif (str_contains($ref_lower, 'wa.me') || str_contains($ref_lower, 'whatsapp.com') || $source_lower === 'whatsapp') {
    $traffic_source = 'WhatsApp';
} elseif (str_contains($ref_lower, 'pinterest.com') || $source_lower === 'pinterest') {
    $traffic_source = 'Pinterest';
} elseif (str_contains($ref_lower, 'twitter.com') || str_contains($ref_lower, 't.co') || $source_lower === 'twitter') {
    $traffic_source = 'Twitter / X';
} elseif (!empty($utm_source)) {
    $traffic_source = ucfirst($utm_source) . (!empty($utm_medium) ? ' (' . ucfirst($utm_medium) . ')' : '');
} elseif (!empty($referrer) && !str_contains($ref_lower, $_SERVER['HTTP_HOST'] ?? 'localhost') && !str_contains($ref_lower, 'yosshitaneha.com')) {
    $traffic_source = 'Referral';
}

// Parse Device Type
$device_type = 'Desktop';
if ($is_bot) {
    $device_type = 'Search Bot';
} elseif (preg_match('/(ipad|tablet|(android(?!.*mobile)))/i', $user_agent)) {
    $device_type = 'Tablet';
} elseif (preg_match('/(mobile|iphone|ipod|android|blackberry|opera mini|windows phone)/i', $user_agent)) {
    $device_type = 'Mobile';
}

// Parse Page Type, Product ID, Category ID
$page_type = 'other';
$product_id = null;
$category_id = null;

$path = parse_url($page_url, PHP_URL_PATH) ?? $page_url;

if ($path === '/' || str_ends_with($path, '/') || str_contains($path, '/home')) {
    $page_type = 'home';
} elseif (str_contains($path, '/product/')) {
    $page_type = 'product';
    if (!empty($input['product_id'])) {
        $product_id = (int)$input['product_id'];
    } else {
        preg_match('~/product/([^/?#]+)~i', $path, $m);
        if (!empty($m[1])) {
            $slug = trim($m[1]);
            try {
                $stP = $pdo->prepare("SELECT id FROM products WHERE slug = ? OR sku = ? LIMIT 1");
                $stP->execute([$slug, $slug]);
                $pRow = $stP->fetch(PDO::FETCH_ASSOC);
                if ($pRow) {
                    $product_id = (int)$pRow['id'];
                }
            } catch (Exception $e) {}
        }
    }
} elseif (str_contains($path, '/category/') || str_contains($path, '/collections')) {
    $page_type = 'category';
    if (!empty($input['category_id'])) {
        $category_id = (int)$input['category_id'];
    } else {
        // Try slug from URL or from input
        $catSlug = $input['slug'] ?? '';
        if (empty($catSlug)) {
            $parts = explode('/', trim($path, '/'));
            $catSlug = end($parts);
        }
        if (!empty($catSlug) && $catSlug !== 'category' && $catSlug !== 'collections') {
            try {
                $stC = $pdo->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
                $stC->execute([$catSlug]);
                $cRow = $stC->fetch(PDO::FETCH_ASSOC);
                if ($cRow) {
                    $category_id = (int)$cRow['id'];
                }
            } catch (Exception $e) {}
        }
    }
} elseif (str_contains($path, '/cart')) {
    $page_type = 'cart';
} elseif (str_contains($path, '/checkout')) {
    $page_type = 'checkout';
} elseif (str_contains($path, '/auth') || str_contains($path, '/login')) {
    $page_type = 'auth';
} elseif (str_contains($path, '/search')) {
    $page_type = 'search';
} elseif (str_contains($path, '/consultation') || str_contains($path, '/blouse-studio') || str_contains($path, '/ai-studio') || str_contains($path, '/try-on') || str_contains($path, '/tryon')) {
    $page_type = 'studio';
} elseif (str_contains($path, '/our-work') || str_contains($path, '/portfolio') || str_contains($path, '/lookbook') || str_contains($path, '/client-diaries')) {
    $page_type = 'portfolio';
} elseif (str_contains($path, '/account')) {
    $page_type = 'account';
} elseif (str_contains($path, '/order/')) {
    $page_type = 'order';
}

// Also handle page_hint from frontend for better slug matching
if (!empty($input['page_hint']) && !empty($input['slug'])) {
    $hintSlug = trim($input['slug']);
    if ($input['page_hint'] === 'product' && !$product_id) {
        $page_type = 'product';
        try {
            $stP = $pdo->prepare("SELECT id FROM products WHERE slug = ? OR sku = ? LIMIT 1");
            $stP->execute([$hintSlug, $hintSlug]);
            $pRow = $stP->fetch(PDO::FETCH_ASSOC);
            if ($pRow) $product_id = (int)$pRow['id'];
        } catch (Exception $e) {}
    } elseif ($input['page_hint'] === 'category' && !$category_id) {
        $page_type = 'category';
        try {
            $stC = $pdo->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
            $stC->execute([$hintSlug]);
            $cRow = $stC->fetch(PDO::FETCH_ASSOC);
            if ($cRow) $category_id = (int)$cRow['id'];
        } catch (Exception $e) {}
    }
}

try {
    // Avoid duplicate logging within 5 seconds for same IP and path
    $checkStmt = $pdo->prepare("SELECT id FROM visitor_logs WHERE ip_address = ? AND page_url = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND) LIMIT 1");
    $checkStmt->execute([$ip, substr($page_url, 0, 255)]);
    
    if (!$checkStmt->fetch()) {
        $insStmt = $pdo->prepare("INSERT INTO visitor_logs (
            ip_address, page_url, page_type, product_id, category_id, referrer, traffic_source,
            utm_source, utm_medium, utm_campaign, utm_content, utm_term, fbclid, gclid, landing_page,
            user_agent, device_type, session_id, customer_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insStmt->execute([
            $ip,
            substr($page_url, 0, 255),
            $page_type,
            $product_id,
            $category_id,
            substr($referrer, 0, 255),
            $traffic_source,
            $utm_source ?: null,
            $utm_medium ?: null,
            $utm_campaign ?: null,
            $utm_content ?: null,
            $utm_term ?: null,
            $fbclid ?: null,
            $gclid ?: null,
            substr($landing_page, 0, 255) ?: null,
            substr($user_agent, 0, 255),
            $device_type,
            $session_id ?: null,
            null // customer_id — could be set from JWT in future
        ]);

        // Increment product view count if product_id is resolved and not a bot
        if ($product_id && !$is_bot) {
            $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?")->execute([$product_id]);
        }

        // Increment category view count if category_id is resolved and not a bot
        if ($category_id && !$is_bot) {
            $pdo->prepare("UPDATE categories SET view_count = view_count + 1 WHERE id = ?")->execute([$category_id]);
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
