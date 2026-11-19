<?php
// admin/includes/refund-service.php
// Manages order cancellations, returns inspection holds, and Razorpay gateway refunds

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/razorpay.php';
require_once __DIR__ . '/pos-sync.php';

/**
 * Places an order's refund on hold pending return package receipt & inspection.
 */
function hold_order_refund($pdo, $orderId, $reason = '') {
    $orderId = (int)$orderId;
    $reason = trim($reason) ?: 'Awaiting return package receipt and physical inspection';

    $stmt = $pdo->prepare("
        UPDATE orders 
        SET refund_status = 'on_hold', 
            refund_reason = ?, 
            return_received = 0 
        WHERE id = ?
    ");
    $stmt->execute([$reason, $orderId]);

    if (function_exists('log_activity')) {
        log_activity($pdo, "Refund put ON HOLD for Order #{$orderId}. Reason: {$reason}", 'orders', $orderId);
    }

    return [
        'success' => true,
        'message' => 'Refund has been put ON HOLD. Funds will not be reversed until the returned package is received and verified.'
    ];
}

/**
 * Restores product quantities in central POS inventory (phppos_items) and local YN products table.
 */
function restore_order_inventory($pdo, $orderId) {
    $orderId = (int)$orderId;
    
    // Fetch items
    $itemsStmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.sku 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $posPdo = get_pos_db_connection();
    $restocked = [];

    foreach ($items as $item) {
        $sku = trim($item['sku'] ?? '');
        $qty = (int)($item['quantity'] ?? 1);
        $prodId = (int)($item['product_id'] ?? 0);

        // 1. Restock in POS phppos_items
        if ($posPdo && (!empty($sku) || !empty($item['product_name']))) {
            try {
                $posItemStmt = $posPdo->prepare("
                    SELECT item_id, name FROM phppos_items 
                    WHERE (name = ? OR item_number = ?) AND (is_deleted = 0 OR is_deleted IS NULL)
                    LIMIT 1
                ");
                $posItemStmt->execute([$sku, $sku]);
                $posItem = $posItemStmt->fetch(PDO::FETCH_ASSOC);

                if (!$posItem && !empty($item['product_name'])) {
                    $posItemStmt = $posPdo->prepare("
                        SELECT item_id, name FROM phppos_items 
                        WHERE name = ? AND (is_deleted = 0 OR is_deleted IS NULL)
                        LIMIT 1
                    ");
                    $posItemStmt->execute([$item['product_name']]);
                    $posItem = $posItemStmt->fetch(PDO::FETCH_ASSOC);
                }

                if ($posItem && isset($posItem['item_id'])) {
                    $posPdo->prepare("UPDATE phppos_items SET quantity = quantity + ? WHERE item_id = ?")
                        ->execute([$qty, $posItem['item_id']]);
                    $restocked[] = "{$posItem['name']} (+{$qty} in POS)";
                }
            } catch (Exception $e) {
                error_log("Failed to restock POS item: " . $e->getMessage());
            }
        }

        // 2. Restock in local YN products table
        if ($prodId > 0) {
            try {
                $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")
                    ->execute([$qty, $prodId]);
            } catch (Exception $e) {}
        }
    }

    return $restocked;
}

/**
 * Executes a refund via Razorpay Payment Gateway (Full or Custom/Partial)
 * and updates order and inventory records accordingly.
 *
 * @param PDO $pdo
 * @param int $orderId
 * @param float $refundAmount Amount in INR to refund
 * @param string $reason Cancellation / Refund reason
 * @param bool $returnConfirmed True if return parcel was received & inspected
 * @param bool $restockInventory True if items should be restored to POS inventory
 * @return array
 */
function process_razorpay_refund($pdo, $orderId, $refundAmount, $reason = '', $returnConfirmed = false, $restockInventory = false) {
    $orderId = (int)$orderId;
    $refundAmount = round((float)$refundAmount, 2);

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    $totalPaid = (float)($order['total_amount'] ?? 0);
    if ($refundAmount <= 0) {
        return ['success' => false, 'message' => 'Refund amount must be greater than zero.'];
    }
    if ($refundAmount > $totalPaid) {
        return ['success' => false, 'message' => "Refund amount (₹{$refundAmount}) cannot exceed original order total (₹{$totalPaid})."];
    }

    // Guard: If order was Shipped or Delivered, require return confirmation
    $isShippedOrDelivered = in_array(strtolower($order['status'] ?? ''), ['shipped', 'delivered']);
    if ($isShippedOrDelivered && !$returnConfirmed) {
        return [
            'success' => false,
            'message' => 'Return Verification Alert: This order was already shipped/delivered. You must confirm that the returned physical package has been received and inspected before issuing a refund.'
        ];
    }

    $txnId = trim($order['transaction_id'] ?? '');
    if (empty($txnId)) {
        return ['success' => false, 'message' => 'Cannot issue refund: No Razorpay Payment / Transaction ID found on this order.'];
    }

    $isFullRefund = ($refundAmount >= $totalPaid);
    $refundStatus = $isFullRefund ? 'refunded' : 'partially_refunded';
    $reasonText = trim($reason) ?: ($isFullRefund ? 'Full order cancellation' : 'Partial cancellation / shipping deduction');

    // 1. Call Razorpay Refund API
    $isDummy = (
        strpos($txnId, 'dummy') !== false || 
        strpos($txnId, 'txn_') === 0 || 
        RAZORPAY_KEY_ID === 'YOUR_KEY_ID' || 
        empty(RAZORPAY_KEY_ID)
    );

    $refundId = '';

    if ($isDummy) {
        // Dummy/offline testing response
        $refundId = 'dummy_rfnd_' . time();
    } else {
        // Real Razorpay API call
        $amountInPaise = round($refundAmount * 100);
        $payload = [
            'amount' => $amountInPaise,
            'reverse_all' => 1,
            'notes' => [
                'order_id' => (string)$orderId,
                'order_number' => format_order_number($orderId),
                'reason' => substr($reasonText, 0, 100)
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/payments/' . urlencode($txnId) . '/refund');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $rzpData = json_decode($response, true);

        if ($httpCode !== 200 || empty($rzpData['id'])) {
            $errDesc = $rzpData['error']['description'] ?? 'Razorpay API rejected the refund request.';
            return [
                'success' => false,
                'message' => 'Razorpay Gateway Error: ' . $errDesc
            ];
        }

        $refundId = $rzpData['id'];
    }

    // 2. Restock Inventory if requested
    $restockedItems = [];
    if ($restockInventory) {
        $restockedItems = restore_order_inventory($pdo, $orderId);
    }

    // 3. Update Order record
    $upStmt = $pdo->prepare("
        UPDATE orders 
        SET status = 'Cancelled',
            refund_status = ?,
            refund_amount = ?,
            refund_id = ?,
            refund_reason = ?,
            return_received = ?,
            refunded_at = NOW()
        WHERE id = ?
    ");
    $upStmt->execute([
        $refundStatus,
        $refundAmount,
        $refundId,
        $reasonText,
        $returnConfirmed ? 1 : 0,
        $orderId
    ]);

    // 4. Log Administrative Activity
    if (function_exists('log_activity')) {
        $orderNum = format_order_number($orderId);
        $restockNote = !empty($restockedItems) ? ' | Inventory Restocked: ' . implode(', ', $restockedItems) : '';
        log_activity(
            $pdo,
            "Refund of ₹{$refundAmount} processed via Razorpay ({$refundId}) for Order #{$orderNum}. Status: {$refundStatus}{$restockNote}",
            'orders',
            $orderId
        );
    }

    // 5. Send Order Cancellation & Refund Email
    if (function_exists('send_order_email')) {
        send_order_email($pdo, $orderId, 'Cancelled');
    }

    return [
        'success' => true,
        'refund_id' => $refundId,
        'amount' => $refundAmount,
        'refund_status' => $refundStatus,
        'restocked_items' => $restockedItems,
        'message' => "Refund of ₹" . number_format($refundAmount, 2) . " processed successfully via Razorpay (Refund ID: {$refundId})."
    ];
}
