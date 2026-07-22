<?php
// admin/api/track_visitor.php
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

// Parse Traffic Source
$traffic_source = 'Direct';
if (!empty($referrer)) {
    $ref_lower = strtolower($referrer);
    if (str_contains($ref_lower, 'instagram.com') || str_contains($ref_lower, 'l.instagram.com')) {
        $traffic_source = 'Instagram';
    } elseif (str_contains($ref_lower, 'facebook.com') || str_contains($ref_lower, 'fb.me')) {
        $traffic_source = 'Facebook';
    } elseif (str_contains($ref_lower, 'google.com') || str_contains($ref_lower, 'google.co.in')) {
        $traffic_source = 'Google Search';
    } elseif (str_contains($ref_lower, 'youtube.com') || str_contains($ref_lower, 't.co') || str_contains($ref_lower, 'twitter.com')) {
        $traffic_source = 'Social Media';
    } elseif (!str_contains($ref_lower, $_SERVER['HTTP_HOST'] ?? 'localhost')) {
        $traffic_source = 'Referral';
    }
}

// Parse Device Type
$device_type = 'Desktop';
$ua_lower = strtolower($user_agent);
if (preg_match('/(ipad|tablet|(android(?!.*mobile)))/i', $user_agent)) {
    $device_type = 'Tablet';
} elseif (preg_match('/(mobile|iphone|ipod|android|blackberry|opera mini|windows phone)/i', $user_agent)) {
    $device_type = 'Mobile';
}

// Parse Page Type & Product/Category IDs if present
$page_type = 'other';
$product_id = null;
$category_id = null;

if ($page_url === '/' || str_ends_with($page_url, '/') || str_contains($page_url, '/home')) {
    $page_type = 'home';
} elseif (str_contains($page_url, '/product/')) {
    $page_type = 'product';
    if (!empty($input['product_id'])) {
        $product_id = (int)$input['product_id'];
    }
} elseif (str_contains($page_url, '/category/')) {
    $page_type = 'category';
    if (!empty($input['category_id'])) {
        $category_id = (int)$input['category_id'];
    }
} elseif (str_contains($page_url, '/cart')) {
    $page_type = 'cart';
} elseif (str_contains($page_url, '/checkout')) {
    $page_type = 'checkout';
}

try {
    // Avoid spamming duplicate logs from exact same IP and URL within 10 seconds
    $checkStmt = $pdo->prepare("SELECT id FROM visitor_logs WHERE ip_address = ? AND page_url = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND) LIMIT 1");
    $checkStmt->execute([$ip, substr($page_url, 0, 255)]);
    
    if (!$checkStmt->fetch()) {
        $insStmt = $pdo->prepare("INSERT INTO visitor_logs (ip_address, page_url, page_type, product_id, category_id, referrer, traffic_source, user_agent, device_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insStmt->execute([
            $ip,
            substr($page_url, 0, 255),
            $page_type,
            $product_id,
            $category_id,
            substr($referrer, 0, 255),
            $traffic_source,
            substr($user_agent, 0, 255),
            $device_type
        ]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
