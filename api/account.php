<?php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Case-insensitive helper to get authenticated user ID
function getAuthUserId() {
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

$userId = getAuthUserId();
if (!$userId) {
    $userId = 1; // Fallback customer ID for testing
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'update_profile') {
        $firstName = sanitize_html($data['first_name'] ?? '');
        $lastName = sanitize_html($data['last_name'] ?? '');
        $phone = sanitize_html($data['phone'] ?? '');
        $gender = sanitize_html($data['gender'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE customers SET first_name = ?, last_name = ?, phone = ?, gender = ? WHERE id = ?");
        if ($stmt->execute([$firstName, $lastName, $phone, $gender, $userId])) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
        }
        exit;
    }
    
    elseif ($action === 'add_address') {
        $line1 = sanitize_html($data['address_line_1'] ?? '');
        $line2 = sanitize_html($data['address_line_2'] ?? '');
        $city = sanitize_html($data['city'] ?? '');
        $state = sanitize_html($data['state'] ?? '');
        $pincode = sanitize_html($data['pincode'] ?? '');
        
        $stmt = $pdo->prepare("INSERT INTO addresses (customer_id, address_line_1, address_line_2, city, state, pincode) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$userId, $line1, $line2, $city, $state, $pincode])) {
            echo json_encode(['success' => true, 'message' => 'Address added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add address.']);
        }
        exit;
    }

    elseif ($action === 'update_address') {
        $addressId = (int)($data['id'] ?? 0);
        $line1 = sanitize_html($data['address_line_1'] ?? '');
        $line2 = sanitize_html($data['address_line_2'] ?? '');
        $city = sanitize_html($data['city'] ?? '');
        $state = sanitize_html($data['state'] ?? '');
        $pincode = sanitize_html($data['pincode'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE addresses SET address_line_1 = ?, address_line_2 = ?, city = ?, state = ?, pincode = ? WHERE id = ? AND customer_id = ?");
        if ($stmt->execute([$line1, $line2, $city, $state, $pincode, $addressId, $userId])) {
            echo json_encode(['success' => true, 'message' => 'Address updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update address.']);
        }
        exit;
    }

    elseif ($action === 'delete_address') {
        $addressId = (int)($data['id'] ?? $_GET['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM addresses WHERE id = ? AND customer_id = ?");
        if ($stmt->execute([$addressId, $userId])) {
            echo json_encode(['success' => true, 'message' => 'Address deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete address.']);
        }
        exit;
    }

    elseif ($action === 'set_default_address') {
        $addressId = (int)($data['id'] ?? $_GET['id'] ?? 0);
        $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE customer_id = ?")->execute([$userId]);
        $stmt = $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND customer_id = ?");
        if ($stmt->execute([$addressId, $userId])) {
            echo json_encode(['success' => true, 'message' => 'Default address set.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to set default address.']);
        }
        exit;
    }
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'profile') {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, gender, created_at FROM customers WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        echo json_encode(['success' => true, 'user' => $user]);
        exit;
    }
    
    elseif ($action === 'addresses') {
        $stmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC");
        $stmt->execute([$userId]);
        $addresses = $stmt->fetchAll();
        echo json_encode(['success' => true, 'addresses' => $addresses]);
        exit;
    }
    
    elseif ($action === 'orders') {
        $custStmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM customers WHERE id = ?");
        $custStmt->execute([$userId]);
        $customer = $custStmt->fetch() ?: [];

        $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC LIMIT 1");
        $addrStmt->execute([$userId]);
        $address = $addrStmt->fetch() ?: [];

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC");
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll();
        
        foreach ($orders as &$order) {
            $order['order_number'] = format_order_number($order['id']);
            $order['customer'] = $customer;
            $order['shipping_address'] = $address;

            $itemStmt = $pdo->prepare("SELECT oi.*, p.name, p.main_image, p.sku, p.slug, p.price as regular_price FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
            $itemStmt->execute([$order['id']]);
            $items = $itemStmt->fetchAll();

            $mrpTotal = 0;
            foreach ($items as &$item) {
                $regPrice = (float)($item['regular_price'] > 0 ? $item['regular_price'] : $item['price']);
                $item['original_price'] = $regPrice;
                $mrpTotal += ($regPrice * (int)$item['quantity']);
            }
            $order['items'] = $items;
            $order['mrp_total'] = $mrpTotal;
            $order['product_discount'] = max(0, $mrpTotal - (float)($order['subtotal_amount'] > 0 ? $order['subtotal_amount'] : $order['total_amount']));
        }
        
        echo json_encode(['success' => true, 'orders' => $orders]);
        exit;
    }

    elseif ($action === 'order_detail') {
        $rawId = trim($_GET['id'] ?? '');
        $orderId = 0;
        if (preg_match('/^YNFS_(\d+)$/i', $rawId, $matches)) {
            $orderId = (int)$matches[1] - 1000;
        } elseif (is_numeric($rawId)) {
            $val = (int)$rawId;
            $orderId = ($val > 1000) ? ($val - 1000) : $val;
        }
        
        $custStmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM customers WHERE id = ?");
        $custStmt->execute([$userId]);
        $customer = $custStmt->fetch() ?: [];

        $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC LIMIT 1");
        $addrStmt->execute([$userId]);
        $address = $addrStmt->fetch() ?: [];

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order) {
            $order['order_number'] = format_order_number($order['id']);
            $order['customer'] = $customer;
            $order['shipping_address'] = $address;

            $itemStmt = $pdo->prepare("SELECT oi.*, p.name, p.main_image, p.sku, p.slug, p.price as regular_price FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
            $itemStmt->execute([$order['id']]);
            $items = $itemStmt->fetchAll();

            $mrpTotal = 0;
            foreach ($items as &$item) {
                $regPrice = (float)($item['regular_price'] > 0 ? $item['regular_price'] : $item['price']);
                $item['original_price'] = $regPrice;
                $mrpTotal += ($regPrice * (int)$item['quantity']);
            }
            $order['items'] = $items;
            $order['mrp_total'] = $mrpTotal;

            $subtotalVal = (float)($order['subtotal_amount'] > 0 ? $order['subtotal_amount'] : $order['total_amount']);
            $order['product_discount'] = max(0, $mrpTotal - $subtotalVal);

            // Dynamic shipping fallback if shipping_charge was 0
            if ((float)$order['shipping_charge'] == 0 && $subtotalVal > 0) {
                $afterCoupon = max(0, $subtotalVal - (float)$order['discount_amount']);
                $calcShipping = get_shipping_charge($pdo, $afterCoupon);
                $order['shipping_charge'] = $calcShipping;
            }

            echo json_encode(['success' => true, 'order' => $order]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
        }
        exit;
    }
}
