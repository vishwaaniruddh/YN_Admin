<?php
require_once '../config/db.php';
require_once '../config/razorpay.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

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
    // Fallback to customer ID 1 if not present for checkout flow
    $userId = 1;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? [];

    if ($action === 'create_order') {
        $items = $data['items'] ?? [];
        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
            exit;
        }

        // Calculate total securely on backend
        $subtotal = 0;
        foreach ($items as $item) {
            $stmt = $pdo->prepare("SELECT id, price, sale_price FROM products WHERE id = ?");
            $stmt->execute([(int)$item['id']]);
            $product = $stmt->fetch();
            if ($product) {
                $discount = get_product_discount_info($pdo, $product['id'], $product['price']);
                $priceToUse = $discount ? $discount['discounted_price'] : (float)($product['sale_price'] > 0 ? $product['sale_price'] : $product['price']);
                $subtotal += ($priceToUse * (int)$item['quantity']);
            }
        }
        
        $couponCode = trim($data['coupon_code'] ?? '');
        $couponDiscount = 0;
        if (!empty($couponCode)) {
            $cpRes = validate_and_apply_coupon($pdo, $couponCode, $subtotal);
            if ($cpRes['valid']) {
                $couponDiscount = (float)$cpRes['discount_calculated'];
                // Increment coupon usage
                try {
                    $pdo->prepare("UPDATE coupons SET usage_count = usage_count + 1 WHERE UPPER(code) = ?")->execute([strtoupper($couponCode)]);
                } catch (Exception $ex) {}
            }
        }

        $afterCouponSubtotal = max(0, $subtotal - $couponDiscount);
        $shipping = get_shipping_charge($pdo, $afterCouponSubtotal);
        $totalAmount = max(0, $afterCouponSubtotal + $shipping);

        // Clean up any stale unpaid pending orders for this customer to prevent duplicates
        try {
            $pdo->prepare("DELETE FROM orders WHERE customer_id = ? AND status = 'Pending'")->execute([$userId]);
        } catch (Exception $ex) {}

        // Create local DB Order first as Pending
        $stmt = $pdo->prepare("INSERT INTO orders (customer_id, subtotal_amount, shipping_charge, coupon_code, discount_amount, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        if (!$stmt->execute([$userId, $subtotal, $shipping, $couponCode, $couponDiscount, $totalAmount])) {
            echo json_encode(['success' => false, 'message' => 'Failed to create order in database.']);
            exit;
        }
        $orderId = $pdo->lastInsertId();

        // Insert order items
        foreach ($items as $item) {
            $stmt = $pdo->prepare("SELECT id, price, sale_price FROM products WHERE id = ?");
            $stmt->execute([(int)$item['id']]);
            $product = $stmt->fetch();
            if ($product) {
                $discount = get_product_discount_info($pdo, $product['id'], $product['price']);
                $priceToUse = $discount ? $discount['discounted_price'] : (float)($product['sale_price'] > 0 ? $product['sale_price'] : $product['price']);
                $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $itemStmt->execute([$orderId, (int)$item['id'], (int)$item['quantity'], $priceToUse]);
            }
        }

        // Save or update shipping address for the customer
        $shippingData = $data['shipping'] ?? [];
        $addrSummary = '';
        if (!empty($shippingData) && is_array($shippingData)) {
            $line1 = sanitize_html(trim($shippingData['address_line_1'] ?? ''));
            $line2 = sanitize_html(trim($shippingData['address_line_2'] ?? ''));
            $city = sanitize_html(trim($shippingData['city'] ?? ''));
            $state = sanitize_html(trim($shippingData['state'] ?? ''));
            $pincode = sanitize_html(trim($shippingData['pincode'] ?? ''));
            $country = sanitize_html(trim($shippingData['country'] ?? 'India')) ?: 'India';

            if (!empty($line1) && !empty($city) && !empty($state) && !empty($pincode)) {
                $addrSummary = $line1 . ($line2 ? ', ' . $line2 : '') . ', ' . $city . ', ' . $state . ' - ' . $pincode;
                try {
                    // Check if an identical address already exists for this customer
                    $checkStmt = $pdo->prepare("
                        SELECT id FROM addresses 
                        WHERE customer_id = ? AND address_line_1 = ? AND city = ? AND state = ? AND pincode = ?
                        LIMIT 1
                    ");
                    $checkStmt->execute([$userId, $line1, $city, $state, $pincode]);
                    $existingAddrId = $checkStmt->fetchColumn();

                    // Mark prior addresses for this customer as non-default
                    $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE customer_id = ?")->execute([$userId]);

                    if ($existingAddrId) {
                        $upStmt = $pdo->prepare("
                            UPDATE addresses 
                            SET address_line_2 = ?, is_default = 1, created_at = CURRENT_TIMESTAMP 
                            WHERE id = ?
                        ");
                        $upStmt->execute([$line2, $existingAddrId]);
                    } else {
                        $insStmt = $pdo->prepare("
                            INSERT INTO addresses (customer_id, address_line_1, address_line_2, city, state, pincode, country, is_default)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                        ");
                        $insStmt->execute([$userId, $line1, $line2, $city, $state, $pincode, $country]);
                    }
                } catch (Exception $addrEx) {
                    error_log("Failed to save shipping address: " . $addrEx->getMessage());
                }
            }
        }

        // Call Razorpay API to generate Razorpay Order ID
        $rpOrderPayload = [
            'amount' => round($totalAmount * 100), // paise
            'currency' => 'INR',
            'receipt' => 'yn_rcpt_' . $orderId,
            'notes' => [
                'order_id' => (string)$orderId,
                'customer_id' => (string)$userId
            ]
        ];
        if (!empty($addrSummary)) {
            $rpOrderPayload['notes']['shipping_address'] = substr($addrSummary, 0, 120);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($rpOrderPayload));
        curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $rpData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($rpData['id'])) {
            echo json_encode([
                'success' => true, 
                'order_id' => (string)$orderId,
                'order_number' => format_order_number($orderId),
                'razorpay_order_id' => $rpData['id'],
                'amount' => $totalAmount,
                'key' => RAZORPAY_KEY_ID
            ]);
            exit;
        } else {
            // Fallback for dummy keys or offline testing
            if (RAZORPAY_KEY_ID === 'YOUR_KEY_ID' || empty(RAZORPAY_KEY_ID)) {
                echo json_encode([
                    'success' => true,
                    'order_id' => (string)$orderId,
                    'order_number' => format_order_number($orderId),
                    'razorpay_order_id' => 'dummy_order_' . time(),
                    'amount' => $totalAmount,
                    'key' => RAZORPAY_KEY_ID,
                    'is_dummy' => true
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Razorpay order creation failed: ' . ($rpData['error']['description'] ?? 'Check API Keys in Admin Settings')
                ]);
                exit;
            }
        }
    } 
    elseif ($action === 'verify_payment') {
        $razorpayPaymentId = $data['razorpay_payment_id'] ?? '';
        $razorpayOrderId = $data['razorpay_order_id'] ?? '';
        $razorpaySignature = $data['razorpay_signature'] ?? '';
        $localOrderId = (int)($data['order_id'] ?? 0);
        
        $paymentMethodLabel = 'Prepaid Online';
        
        if (!empty($razorpayPaymentId) && $razorpayPaymentId !== 'dummy_pay_id') {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/payments/' . $razorpayPaymentId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
            $payResp = curl_exec($ch);
            curl_close($ch);
            
            $payData = json_decode($payResp, true);
            if (isset($payData['method'])) {
                $methodType = strtolower($payData['method']);
                if ($methodType === 'card') {
                    $cardType = ucfirst($payData['card']['type'] ?? 'Card');
                    $network = $payData['card']['network'] ?? 'Card';
                    $paymentMethodLabel = "Online (" . $cardType . " - " . $network . ")";
                } elseif ($methodType === 'upi') {
                    $vpa = $payData['vpa'] ?? '';
                    $paymentMethodLabel = "UPI" . ($vpa ? " (" . $vpa . ")" : "");
                } elseif ($methodType === 'netbanking') {
                    $bank = $payData['bank'] ?? '';
                    $paymentMethodLabel = "NetBanking" . ($bank ? " (" . $bank . ")" : "");
                } elseif ($methodType === 'wallet') {
                    $wallet = $payData['wallet'] ?? '';
                    $paymentMethodLabel = "Wallet" . ($wallet ? " (" . $wallet . ")" : "");
                } else {
                    $paymentMethodLabel = ucfirst($methodType);
                }
            }
        } elseif (!empty($data['payment_method'])) {
            $paymentMethodLabel = trim($data['payment_method']);
        }

        if (!empty($data['is_dummy'])) {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'Processing', payment_method = ?, transaction_id = ? WHERE id = ?");
            $stmt->execute([$paymentMethodLabel, $razorpayPaymentId ?: ('txn_' . time()), $localOrderId]);
            send_order_email($pdo, $localOrderId, 'success');
            echo json_encode(['success' => true, 'message' => 'Dummy payment verified.']);
            exit;
        }

        // Standard Signature Verification
        $expectedSignature = hash_hmac('sha256', $razorpayOrderId . "|" . $razorpayPaymentId, RAZORPAY_KEY_SECRET);
        
        if (hash_equals($expectedSignature, $razorpaySignature)) {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'Processing', payment_method = ?, transaction_id = ? WHERE id = ?");
            if ($stmt->execute([$paymentMethodLabel, $razorpayPaymentId, $localOrderId])) {
                send_order_email($pdo, $localOrderId, 'success');
                echo json_encode(['success' => true, 'message' => 'Payment successful and order updated.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Payment successful, but failed to update order in DB.']);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'Cancelled', payment_method = ?, transaction_id = ? WHERE id = ?");
            $stmt->execute([$paymentMethodLabel, $razorpayPaymentId, $localOrderId]);
            send_order_email($pdo, $localOrderId, 'failure');
            echo json_encode(['success' => false, 'message' => 'Payment signature verification failed.']);
        }
        exit;
    }
}
