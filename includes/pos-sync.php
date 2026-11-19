<?php
// admin/includes/pos-sync.php
// Centralized synchronization between Website Orders and POS Approval Bills (phppos)

if (!function_exists('get_pos_db_connection')) {
    function get_pos_db_connection() {
        static $posPdo = null;
        if ($posPdo !== null) return $posPdo;

        $isLocal = (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST']) || in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0);

        $configs = [];
        if ($isLocal) {
            $configs[] = ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'db' => 'u464193275_srishringarr'];
            $configs[] = ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'db' => 'u464193275_srishrinjewels'];
        } else {
            $configs[] = ['host' => 'localhost', 'user' => 'u464193275_sarmicropos', 'pass' => 'Mypos1234', 'db' => 'u464193275_srishringarr'];
            $configs[] = ['host' => 'localhost', 'user' => 'u464193275_yosshitanehafs', 'pass' => 'AVav@@2026', 'db' => 'u464193275_srishringarr'];
            $configs[] = ['host' => 'localhost', 'user' => 'u464193275_srishrinjuser', 'pass' => '9b@hMgk!=zI', 'db' => 'u464193275_srishrinjewels'];
        }

        foreach ($configs as $cfg) {
            try {
                $p = new PDO("mysql:host={$cfg['host']};dbname={$cfg['db']};charset=utf8mb4", $cfg['user'], $cfg['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                $stmt = $p->query("SHOW TABLES LIKE 'approval'");
                if ($stmt->rowCount() > 0) {
                    $posPdo = $p;
                    return $posPdo;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }
}

/**
 * Creates an Approval Bill in the POS system (localhost/ss/pos/reports/approval.php format)
 * and deducts purchased quantities from centralized POS inventory (phppos_items).
 *
 * @param PDO $pdo YN Local Database Connection
 * @param int $orderId Order ID to sync
 * @return array ['success' => bool, 'message' => string, 'bill_number' => string]
 */
function sync_order_to_pos_approval_bill($pdo, $orderId) {
    $orderId = (int)$orderId;
    if ($orderId <= 0) {
        return ['success' => false, 'message' => 'Invalid order ID.'];
    }

    // 1. Fetch Order details
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        return ['success' => false, 'message' => 'Order not found in database.'];
    }

    // If already synced, prevent double billing and double stock deduction
    if (!empty($order['pos_bill_number'])) {
        return [
            'success' => true,
            'already_synced' => true,
            'bill_number' => $order['pos_bill_number'],
            'message' => 'Order was already synced to POS as Bill #' . $order['pos_bill_number']
        ];
    }

    // 2. Connect to POS Database
    $posPdo = get_pos_db_connection();
    if (!$posPdo) {
        return ['success' => false, 'message' => 'Failed to connect to central POS database (u464193275_srishringarr).'];
    }

    try {
        // Fetch Customer & Shipping Address
        $custStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $custStmt->execute([$order['customer_id']]);
        $customer = $custStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC LIMIT 1");
        $addrStmt->execute([$order['customer_id']]);
        $address = $addrStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $firstName = trim($customer['first_name'] ?? 'Website');
        $lastName = trim($customer['last_name'] ?? 'Shopper');
        $email = trim($customer['email'] ?? '');
        $phone = trim($customer['phone'] ?? '');

        $addr1 = trim($address['address_line_1'] ?? '');
        $addr2 = trim($address['address_line_2'] ?? '');
        $city = trim($address['city'] ?? '');
        $state = trim($address['state'] ?? '');
        $zip = trim($address['pincode'] ?? '');
        $country = trim($address['country'] ?? 'India');

        // Clean phone for searching (e.g. 9820198201)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) > 10 && substr($cleanPhone, 0, 2) === '91') {
            $cleanPhone = substr($cleanPhone, 2);
        }

        // =========================================================================
        // STEP A: Locate or Create Customer in POS phppos_people & phppos_customers
        // =========================================================================
        $posPersonId = null;

        if (!empty($cleanPhone)) {
            $searchStmt = $posPdo->prepare("SELECT person_id FROM phppos_people WHERE phone_number LIKE ? LIMIT 1");
            $searchStmt->execute(["%{$cleanPhone}%"]);
            $posPersonId = $searchStmt->fetchColumn();
        }

        if (!$posPersonId && !empty($email)) {
            $searchStmt = $posPdo->prepare("SELECT person_id FROM phppos_people WHERE email = ? AND email != '' LIMIT 1");
            $searchStmt->execute([$email]);
            $posPersonId = $searchStmt->fetchColumn();
        }

        if (!$posPersonId) {
            // Customer does not exist in POS -> Create new customer
            $insPerson = $posPdo->prepare("
                INSERT INTO phppos_people 
                (first_name, last_name, phone_number, email, address_1, address_2, city, state, zip, country, comments, dob, acc_type, add_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Website Online Customer', '1970-01-01', 0, 0)
            ");
            $insPerson->execute([
                $firstName,
                $lastName,
                $phone ?: $cleanPhone,
                $email,
                $addr1,
                $addr2,
                $city,
                $state,
                $zip,
                $country
            ]);
            $posPersonId = (int)$posPdo->lastInsertId();

            // Insert into phppos_customers
            $insCust = $posPdo->prepare("
                INSERT INTO phppos_customers (person_id, account_number, taxable)
                VALUES (?, '', 1)
                ON DUPLICATE KEY UPDATE taxable = 1
            ");
            $insCust->execute([$posPersonId]);
        } else {
            $posPersonId = (int)$posPersonId;
        }

        // =========================================================================
        // STEP B: Generate Next Invoice Number via invoice_tracker
        // =========================================================================
        $year = date("Y");
        $month = (int)date("m");
        $financialYear = ($month >= 4) ? substr($year, 2) : substr((string)($year - 1), 2);
        $companyName = "Sri Shringarr";
        $prefix = "APP-" . $financialYear;
        $suffix = "/A-";

        // Query last approval invoice number
        $invStmt = $posPdo->prepare("
            SELECT invoice_number FROM invoice_tracker 
            WHERE company = ? AND invoice LIKE ? 
            ORDER BY id DESC LIMIT 1
        ");
        $invStmt->execute([$companyName, "{$prefix}%"]);
        $lastRow = $invStmt->fetch(PDO::FETCH_ASSOC);

        $nextInvNum = $lastRow ? ((int)$lastRow['invoice_number'] + 1) : 1;
        $formattedNum = str_pad($nextInvNum, 5, "0", STR_PAD_LEFT);
        $fullInvoice = $prefix . $suffix . $formattedNum;

        // Reserve in invoice_tracker
        $trackerStmt = $posPdo->prepare("
            INSERT INTO invoice_tracker 
            (company, invoice_type, invoice_number, invoice, created_at, created_by, status)
            VALUES (?, 'sell', ?, ?, NOW(), 1, 1)
        ");
        $trackerStmt->execute([$companyName, $nextInvNum, $fullInvoice]);

        // =========================================================================
        // STEP C: Create Approval Bill in approval Table
        // =========================================================================
        $today = date('Y-m-d');
        $totalAmount = (float)($order['total_amount'] ?? 0);
        $payMethod = !empty($order['payment_method']) ? $order['payment_method'] : 'Online Prepaid';
        $txnId = !empty($order['transaction_id']) ? $order['transaction_id'] : '';
        $orderNumFormatted = format_order_number($orderId);
        $billNote = "Website Order #{$orderNumFormatted}" . ($txnId ? " (Txn: {$txnId})" : "");

        $hasDiscount = (!empty($order['discount_amount']) && (float)$order['discount_amount'] > 0);
        $disStatus = $hasDiscount ? 'yes' : 'no';
        $disWise = $hasDiscount ? 'Total Wise' : '';
        $disPer = $hasDiscount ? 'Rs' : '';

        $appStmt = $posPdo->prepare("
            INSERT INTO approval 
            (cust_id, bill_date, status, paid_amount, transaction_id, discount_status, discount_wise, discount_per, bill_by, pay_by, note, card_perc, card_amt, amountTotal, new_bill_number, company_name, bill_prepare_by)
            VALUES (?, ?, 'A', ?, ?, ?, ?, ?, 'APPROVAL RECEIPT', ?, ?, 0, 0, ?, ?, ?, 'Website YN Online')
        ");
        $appStmt->execute([
            $posPersonId,
            $today,
            (int)round($totalAmount),
            $txnId,
            $disStatus,
            $disWise,
            $disPer,
            $payMethod,
            $billNote,
            (string)round($totalAmount),
            $fullInvoice,
            $companyName
        ]);
        $billId = (int)$posPdo->lastInsertId();

        // =========================================================================
        // STEP D: Create Entry in paid_amount (Payment Receipt)
        // =========================================================================
        if ($totalAmount > 0) {
            $paidStmt = $posPdo->prepare("
                INSERT INTO paid_amount 
                (bill_id, amount, return_date, payment_by, bid, new_bill_number)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $paidStmt->execute([
                $posPersonId,
                $totalAmount,
                $today,
                $payMethod,
                $billId,
                $fullInvoice
            ]);
        }

        // =========================================================================
        // STEP E: Insert Line Items in approval_detail & Deduct POS phppos_items Stock
        // =========================================================================
        $itemsStmt = $pdo->prepare("
            SELECT oi.*, p.name as product_name, p.sku 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $deductedItems = [];

        foreach ($orderItems as $item) {
            $sku = trim($item['sku'] ?? '');
            $qty = (int)($item['quantity'] ?? 1);
            $unitPrice = (float)($item['price'] ?? 0);
            $lineAmount = $unitPrice * $qty;

            // Search for item in POS database
            $posItem = null;
            if (!empty($sku)) {
                $posItemStmt = $posPdo->prepare("
                    SELECT item_id, name, item_number, quantity, unit_price 
                    FROM phppos_items 
                    WHERE (name = ? OR item_number = ?) AND (is_deleted = 0 OR is_deleted IS NULL)
                    LIMIT 1
                ");
                $posItemStmt->execute([$sku, $sku]);
                $posItem = $posItemStmt->fetch(PDO::FETCH_ASSOC);
            }

            // Fallback: search by product name if SKU not found
            if (!$posItem && !empty($item['product_name'])) {
                $posItemStmt = $posPdo->prepare("
                    SELECT item_id, name, item_number, quantity, unit_price 
                    FROM phppos_items 
                    WHERE name = ? AND (is_deleted = 0 OR is_deleted IS NULL)
                    LIMIT 1
                ");
                $posItemStmt->execute([$item['product_name']]);
                $posItem = $posItemStmt->fetch(PDO::FETCH_ASSOC);
            }

            $itemIdToRecord = $posItem ? $posItem['name'] : ($sku ?: $item['product_name']);

            // Insert into approval_detail
            $detailStmt = $posPdo->prepare("
                INSERT INTO approval_detail 
                (bill_id, item_id, qty, discount, dis_amount, amount, item_per, final_amount, new_bill_number, status_type)
                VALUES (?, ?, ?, '0', 0, ?, '', ?, ?, 'new')
            ");
            $detailStmt->execute([
                $billId,
                $itemIdToRecord,
                $qty,
                (string)$lineAmount,
                (string)$unitPrice,
                $fullInvoice
            ]);

            // DEDUCT QUANTITY FROM CENTRALIZED POS DATABASE
            if ($posItem && isset($posItem['item_id'])) {
                $decStmt = $posPdo->prepare("
                    UPDATE phppos_items 
                    SET quantity = quantity - ? 
                    WHERE item_id = ?
                ");
                $decStmt->execute([$qty, $posItem['item_id']]);
                $deductedItems[] = "{$itemIdToRecord} (-{$qty})";
            }

            // Also synchronize local YN products stock
            if (!empty($item['product_id'])) {
                $pdo->prepare("UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?")
                    ->execute([$qty, (int)$item['product_id']]);
            }
        }

        // =========================================================================
        // STEP F: Update Local YN Order with POS Bill Reference
        // =========================================================================
        $upOrder = $pdo->prepare("UPDATE orders SET pos_bill_number = ?, pos_synced_at = NOW() WHERE id = ?");
        $upOrder->execute([$fullInvoice, $orderId]);

        // Log administrative activity
        if (function_exists('log_activity')) {
            $deductSummary = !empty($deductedItems) ? ' | Items deducted in POS: ' . implode(', ', $deductedItems) : '';
            log_activity(
                $pdo, 
                "Order #{$orderNumFormatted} synced to POS Approval Bill {$fullInvoice} (POS Customer #{$posPersonId}){$deductSummary}",
                'orders',
                $orderId
            );
        }

        return [
            'success' => true,
            'bill_number' => $fullInvoice,
            'bill_id' => $billId,
            'pos_customer_id' => $posPersonId,
            'deducted_items' => $deductedItems,
            'message' => "Successfully generated POS Approval Bill {$fullInvoice} and updated central inventory."
        ];

    } catch (Exception $ex) {
        error_log("POS Sync Error for Order #{$orderId}: " . $ex->getMessage());
        return [
            'success' => false,
            'message' => 'POS Sync Exception: ' . $ex->getMessage()
        ];
    }
}
