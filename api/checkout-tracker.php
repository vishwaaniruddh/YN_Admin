<?php
// admin/api/checkout-tracker.php
// Tracks visitors arriving at checkout, abandoned carts, and conversion lifecycle

require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Helper to extract authenticated customer ID
function getAuthCustomerId() {
    $authHeader = '';
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }
    }
    if (empty($authHeader)) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }
    if (preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
        $token = $matches[1];
        $decoded = json_decode(base64_decode($token), true);
        if ($decoded && isset($decoded['id'])) {
            return (int)$decoded['id'];
        }
    }
    return null;
}

// Helper to detect device type
function detectDeviceType($userAgent) {
    if (empty($userAgent)) return 'desktop';
    $ua = strtolower($userAgent);
    if (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
        return 'tablet';
    }
    if (strpos($ua, 'mobile') !== false || strpos($ua, 'iphone') !== false || strpos($ua, 'android') !== false) {
        return 'mobile';
    }
    return 'desktop';
}

$action = $_GET['action'] ?? 'track_visit';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? [];

    $sessionToken = trim($data['session_token'] ?? '');
    if (empty($sessionToken)) {
        $sessionToken = 'chk_' . bin2hex(random_bytes(16));
    }

    $customerId = getAuthCustomerId() ?: (int)($data['customer_id'] ?? 0);
    $customerName = trim($data['customer_name'] ?? '');
    $customerEmail = trim($data['customer_email'] ?? '');
    $customerPhone = trim($data['customer_phone'] ?? '');

    // If customer details are missing but we have customerId, fetch from DB
    if ($customerId > 0 && (empty($customerEmail) || empty($customerName))) {
        try {
            $cStmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM customers WHERE id = ?");
            $cStmt->execute([$customerId]);
            $cust = $cStmt->fetch(PDO::FETCH_ASSOC);
            if ($cust) {
                if (empty($customerName)) {
                    $customerName = trim(($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? ''));
                }
                if (empty($customerEmail)) {
                    $customerEmail = $cust['email'] ?? '';
                }
                if (empty($customerPhone)) {
                    $customerPhone = $cust['phone'] ?? '';
                }
            }
        } catch (Exception $e) {}
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ipAddress = trim($parts[0]);
    }
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $deviceType = detectDeviceType($userAgent);

    $cartItems = $data['cart_items'] ?? [];
    $cartItemsCount = (int)($data['cart_items_count'] ?? count($cartItems));
    $cartTotal = (float)($data['cart_total'] ?? 0.00);
    $cartDataJson = !empty($cartItems) ? json_encode($cartItems, JSON_UNESCAPED_UNICODE) : null;
    $shippingAddress = trim($data['shipping_address'] ?? '');
    $couponCode = trim($data['coupon_code'] ?? '');
    $step = strtolower(trim($data['step'] ?? 'visited'));
    $orderId = !empty($data['order_id']) ? (int)$data['order_id'] : null;

    // Validate step enum
    $allowedSteps = ['visited', 'payment_initiated', 'completed', 'cancelled'];
    if (!in_array($step, $allowedSteps)) {
        $step = 'visited';
    }

    try {
        // Check if session token exists
        $checkStmt = $pdo->prepare("SELECT id, status, order_id FROM checkout_visitors WHERE session_token = ? LIMIT 1");
        $checkStmt->execute([$sessionToken]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $id = (int)$existing['id'];
            $currentStatus = $existing['status'];

            // Do not downgrade a 'completed' session to 'visited' or 'payment_initiated'
            $newStatus = ($currentStatus === 'completed') ? 'completed' : $step;
            $finalOrderId = $orderId ?: ($existing['order_id'] ?? null);

            $updateSql = "UPDATE checkout_visitors SET 
                customer_id = COALESCE(NULLIF(?, 0), customer_id),
                customer_name = COALESCE(NULLIF(?, ''), customer_name),
                customer_email = COALESCE(NULLIF(?, ''), customer_email),
                customer_phone = COALESCE(NULLIF(?, ''), customer_phone),
                cart_items_count = GREATEST(cart_items_count, ?),
                cart_total = CASE WHEN ? > 0 THEN ? ELSE cart_total END,
                cart_data = COALESCE(?, cart_data),
                shipping_address = COALESCE(NULLIF(?, ''), shipping_address),
                coupon_code = COALESCE(NULLIF(?, ''), coupon_code),
                status = ?,
                order_id = ?,
                ip_address = COALESCE(NULLIF(?, ''), ip_address),
                user_agent = COALESCE(NULLIF(?, ''), user_agent),
                device_type = COALESCE(NULLIF(?, ''), device_type),
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";

            $pdo->prepare($updateSql)->execute([
                $customerId ?: null,
                $customerName,
                $customerEmail,
                $customerPhone,
                $cartItemsCount,
                $cartTotal,
                $cartTotal,
                $cartDataJson,
                $shippingAddress,
                $couponCode,
                $newStatus,
                $finalOrderId,
                $ipAddress,
                $userAgent,
                $deviceType,
                $id
            ]);

            echo json_encode([
                'success' => true,
                'session_token' => $sessionToken,
                'id' => $id,
                'status' => $newStatus,
                'action' => 'updated'
            ]);
            exit;
        } else {
            // Insert new checkout visitor session
            $insertSql = "INSERT INTO checkout_visitors 
                (session_token, customer_id, customer_name, customer_email, customer_phone, cart_items_count, cart_total, cart_data, shipping_address, coupon_code, status, order_id, ip_address, user_agent, device_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $pdo->prepare($insertSql)->execute([
                $sessionToken,
                $customerId ?: null,
                $customerName ?: null,
                $customerEmail ?: null,
                $customerPhone ?: null,
                $cartItemsCount,
                $cartTotal,
                $cartDataJson,
                $shippingAddress ?: null,
                $couponCode ?: null,
                $step,
                $orderId ?: null,
                $ipAddress ?: null,
                $userAgent ?: null,
                $deviceType
            ]);

            $newId = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'session_token' => $sessionToken,
                'id' => $newId,
                'status' => $step,
                'action' => 'created'
            ]);
            exit;
        }
    } catch (Exception $ex) {
        error_log("Checkout tracker error: " . $ex->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Tracking failed: ' . $ex->getMessage(),
            'session_token' => $sessionToken
        ]);
        exit;
    }
}

// Fallback for GET request (e.g. quick status or stats)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'summary') {
    try {
        $totalVisits = (int)$pdo->query("SELECT COUNT(*) FROM checkout_visitors")->fetchColumn();
        $completedVisits = (int)$pdo->query("SELECT COUNT(*) FROM checkout_visitors WHERE status = 'completed'")->fetchColumn();
        $paymentInitiated = (int)$pdo->query("SELECT COUNT(*) FROM checkout_visitors WHERE status = 'payment_initiated'")->fetchColumn();
        $abandonedVisits = (int)$pdo->query("SELECT COUNT(*) FROM checkout_visitors WHERE status = 'visited' AND updated_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->fetchColumn();
        $activeNow = (int)$pdo->query("SELECT COUNT(*) FROM checkout_visitors WHERE status != 'completed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->fetchColumn();

        echo json_encode([
            'success' => true,
            'total_checkout_visits' => $totalVisits,
            'completed' => $completedVisits,
            'payment_initiated' => $paymentInitiated,
            'abandoned' => $abandonedVisits,
            'active_in_last_30m' => $activeNow
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
