<?php
// admin/includes/pos-sync.php
// Centralized synchronization between Website Orders and POS Quotation Bills (phppos)

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

if (!function_exists('normalize_pos_phone')) {
    /**
     * Normalizes a customer phone number to a clean comparable 10-digit suffix when available.
     */
    function normalize_pos_phone($phone) {
        $digits = preg_replace('/[^0-9]/', '', (string)$phone);
        if (strlen($digits) > 10 && substr($digits, 0, 2) === '91') {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && substr($digits, 0, 1) === '0') {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }
        return $digits;
    }
}

if (!function_exists('find_or_create_pos_customer')) {
    /**
     * Looks up an existing customer in POS phppos_people by phone number (then email fallback).
     * Creates a new customer (phppos_people + phppos_customers) if none exists, matching people.php?mode=app.
     */
    function find_or_create_pos_customer($posPdo, array $customer, array $address) {
        $firstName = trim((string)($customer['first_name'] ?? '')) ?: 'Website';
        $lastName = trim((string)($customer['last_name'] ?? '')) ?: 'Shopper';
        $email = trim((string)($customer['email'] ?? ''));
        $rawPhone = trim((string)($customer['phone'] ?? ($address['phone'] ?? '')));
        $cleanPhone = normalize_pos_phone($rawPhone);

        $addr1 = trim((string)($address['address_line_1'] ?? ''));
        $addr2 = trim((string)($address['address_line_2'] ?? ''));
        $city = trim((string)($address['city'] ?? ''));
        $state = trim((string)($address['state'] ?? ''));
        $zip = trim((string)($address['pincode'] ?? ''));
        $country = trim((string)($address['country'] ?? 'India')) ?: 'India';

        $posPersonId = null;

        // 1. Check existing POS customer by phone number first
        $normalizedColSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone_number, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '')";
        if (strlen($cleanPhone) === 10) {
            $searchStmt = $posPdo->prepare("
                SELECT person_id FROM phppos_people
                WHERE (RIGHT($normalizedColSql, 10) = ? OR phone_number = ?)
                ORDER BY person_id DESC LIMIT 1
            ");
            $searchStmt->execute([$cleanPhone, $rawPhone]);
            $posPersonId = $searchStmt->fetchColumn();
        } elseif (strlen($cleanPhone) >= 7) {
            $searchStmt = $posPdo->prepare("
                SELECT person_id FROM phppos_people
                WHERE ($normalizedColSql = ? OR phone_number = ?)
                ORDER BY person_id DESC LIMIT 1
            ");
            $searchStmt->execute([$cleanPhone, $rawPhone]);
            $posPersonId = $searchStmt->fetchColumn();
        } elseif ($rawPhone !== '') {
            $searchStmt = $posPdo->prepare("
                SELECT person_id FROM phppos_people
                WHERE phone_number = ?
                ORDER BY person_id DESC LIMIT 1
            ");
            $searchStmt->execute([$rawPhone]);
            $posPersonId = $searchStmt->fetchColumn();
        }

        // 2. Fallback to email lookup only if no match by phone number
        if (!$posPersonId && $email !== '') {
            $searchStmt = $posPdo->prepare("
                SELECT person_id FROM phppos_people
                WHERE email = ? AND email != ''
                ORDER BY person_id DESC LIMIT 1
            ");
            $searchStmt->execute([$email]);
            $posPersonId = $searchStmt->fetchColumn();
        }

        $isNewCustomer = false;
        if (!$posPersonId) {
            $isNewCustomer = true;
            $phoneToStore = $cleanPhone !== '' ? $cleanPhone : $rawPhone;

            $insPerson = $posPdo->prepare("
                INSERT INTO phppos_people
                (first_name, last_name, phone_number, email, address_1, address_2, city, state, zip, country, comments, dob, acc_type, add_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Website Online Customer', '1970-01-01', 0, 0)
            ");
            $insPerson->execute([
                $firstName,
                $lastName,
                $phoneToStore,
                $email,
                $addr1,
                $addr2,
                $city,
                $state,
                $zip,
                $country
            ]);
            $posPersonId = (int)$posPdo->lastInsertId();
        } else {
            $posPersonId = (int)$posPersonId;
        }

        // Ensure customer record exists in phppos_customers (for POS reports/dropdowns)
        $insCust = $posPdo->prepare("
            INSERT INTO phppos_customers (person_id, account_number, taxable)
            VALUES (?, '', 1)
            ON DUPLICATE KEY UPDATE taxable = 1
        ");
        $insCust->execute([$posPersonId]);

        return ['person_id' => $posPersonId, 'is_new' => $isNewCustomer];
    }
}

if (!function_exists('find_pos_item_for_order_item')) {
    /**
     * Resolves matching inventory row in POS phppos_items using SKU variations or product name.
     */
    function find_pos_item_for_order_item($posPdo, array $item) {
        $sku = trim((string)($item['sku'] ?? ''));
        $productName = trim((string)($item['product_name'] ?? ''));

        $candidates = [];
        if ($sku !== '') {
            $candidates[] = $sku;
            $stripped = trim(preg_replace('/\s*\(\d+\)$/', '', $sku));
            if ($stripped !== '' && $stripped !== $sku) {
                $candidates[] = $stripped;
            }
            if (preg_match('/^([A-Za-z]+)0+(\d+)$/', $sku, $m)) {
                $candidates[] = $m[1] . $m[2];
            }
        }
        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $cand) {
            $stmt = $posPdo->prepare("
                SELECT item_id, name, item_number, quantity, unit_price
                FROM phppos_items
                WHERE (name = ? OR item_number = ?) AND (is_deleted = 0 OR is_deleted IS NULL)
                LIMIT 1
            ");
            $stmt->execute([$cand, $cand]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        if ($productName !== '') {
            $stmt = $posPdo->prepare("
                SELECT item_id, name, item_number, quantity, unit_price
                FROM phppos_items
                WHERE name = ? AND (is_deleted = 0 OR is_deleted IS NULL)
                LIMIT 1
            ");
            $stmt->execute([$productName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        return null;
    }
}

/**
 * Creates a QUOTATION Bill in the POS system (company: Sri Shringarr, status: S, bill_by: QUOTATION)
 * against a phone-verified or newly created POS customer, and deducts centralized POS & YN inventory.
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

    // Prevent double billing and double stock deduction if already synced
    if (!empty($order['pos_bill_number'])) {
        return [
            'success' => true,
            'already_synced' => true,
            'bill_number' => $order['pos_bill_number'],
            'message' => 'Order was already synced to POS as Quotation Bill #' . $order['pos_bill_number']
        ];
    }

    // 2. Connect to POS Database
    $posPdo = get_pos_db_connection();
    if (!$posPdo) {
        return ['success' => false, 'message' => 'Failed to connect to central POS database (u464193275_srishringarr).'];
    }

    $posStartedTxn = false;
    try {
        // Fetch Customer & Shipping Address
        $custStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $custStmt->execute([$order['customer_id']]);
        $customer = $custStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC LIMIT 1");
        $addrStmt->execute([$order['customer_id']]);
        $address = $addrStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $itemsStmt = $pdo->prepare("
            SELECT oi.*, p.name as product_name, p.sku, p.category_id, p.price as product_price
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($posPdo->beginTransaction()) {
            $posStartedTxn = true;
        }

        // =========================================================================
        // STEP A: Check Existing Customer by Phone Number or Create New in POS
        // =========================================================================
        $custRes = find_or_create_pos_customer($posPdo, $customer, $address);
        $posPersonId = (int)$custRes['person_id'];

        // =========================================================================
        // STEP B: Resolve Line Items & Flat-Rate (Rs) Discounts from POS Catalog MRP
        // =========================================================================
        $orderCouponDiscount = max(0.0, (float)($order['discount_amount'] ?? 0));
        $shippingCharge = max(0.0, (float)($order['shipping_charge'] ?? 0));
        $preparedLines = [];
        $itemsSoldSum = 0.0;
        $hasOutfitItem = false;

        foreach ($orderItems as $item) {
            $sku = trim((string)($item['sku'] ?? ''));
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $soldUnitPrice = (float)($item['price'] ?? 0);
            $lineSoldSubtotal = round($soldUnitPrice * $qty, 2);
            $itemsSoldSum += $lineSoldSubtotal;

            if (function_exists('is_outfit_category_or_product') && is_outfit_category_or_product($item, $pdo)) {
                $hasOutfitItem = true;
            }

            $posItem = find_pos_item_for_order_item($posPdo, $item);
            $itemIdToRecord = $posItem ? $posItem['name'] : ($sku ?: $item['product_name']);

            // Product base price in POS: prefer live phppos_items.unit_price, fallback to YN catalog price
            $posUnitPrice = ($posItem && (float)($posItem['unit_price'] ?? 0) > 0)
                ? (float)$posItem['unit_price']
                : (((float)($item['product_price'] ?? 0) > 0) ? (float)$item['product_price'] : $soldUnitPrice);

            $preparedLines[] = [
                'product_id' => (int)($item['product_id'] ?? 0),
                'pos_item' => $posItem,
                'item_id_record' => $itemIdToRecord,
                'qty' => $qty,
                'pos_unit_price' => $posUnitPrice,
                'sold_unit_price' => $soldUnitPrice,
                'line_sold_subtotal' => $lineSoldSubtotal,
            ];
        }

        // Allocate any coupon discount cleanly across line items so flat Rs discount includes product + coupon savings
        $remainingCoupon = min($orderCouponDiscount, $itemsSoldSum);
        $totalDiscountGiven = 0.0;
        $lastIdx = count($preparedLines) - 1;

        foreach ($preparedLines as $idx => &$line) {
            $couponShare = 0.0;
            if ($remainingCoupon > 0 && $itemsSoldSum > 0) {
                $couponShare = ($idx === $lastIdx)
                    ? round($remainingCoupon, 2)
                    : min($line['line_sold_subtotal'], round($orderCouponDiscount * ($line['line_sold_subtotal'] / $itemsSoldSum), 2));
                $remainingCoupon = max(0.0, round($remainingCoupon - $couponShare, 2));
            }

            $linePaidAmount = max(0.0, round($line['line_sold_subtotal'] - $couponShare, 2));
            $lineGross = round($line['pos_unit_price'] * $line['qty'], 2);
            $lineDisAmount = ($lineGross > $linePaidAmount) ? round($lineGross - $linePaidAmount, 2) : 0.0;

            $line['paid_amount'] = $linePaidAmount;
            $line['dis_amount'] = $lineDisAmount;
            $totalDiscountGiven += $lineDisAmount;
        }
        unset($line);

        // =========================================================================
        // STEP C: Generate Next QUOTATION (Sell) Invoice Number for Sri Shringarr
        // =========================================================================
        $year = date("Y");
        $month = (int)date("m");
        $financialYear = ($month >= 4) ? substr($year, 2) : substr((string)($year - 1), 2);
        $companyName = "Sri Shringarr";
        $billBy = "QUOTATION";
        $billStatus = "S";
        $invoiceType = "sell";
        $prefix = "SSFS-" . $financialYear;
        $suffix = "/S-";

        $invStmt = $posPdo->prepare("
            SELECT invoice_number FROM invoice_tracker
            WHERE company = ? AND invoice_type = ? AND invoice LIKE ?
            ORDER BY CAST(invoice_number AS UNSIGNED) DESC, id DESC LIMIT 1
        ");
        $invStmt->execute([$companyName, $invoiceType, "{$prefix}{$suffix}%"]);
        $lastRow = $invStmt->fetch(PDO::FETCH_ASSOC);

        $nextInvNum = $lastRow ? ((int)$lastRow['invoice_number'] + 1) : 1;
        $formattedNum = str_pad($nextInvNum, 5, "0", STR_PAD_LEFT);
        $fullInvoice = $prefix . $suffix . $formattedNum;

        $trackerStmt = $posPdo->prepare("
            INSERT INTO invoice_tracker
            (company, invoice_type, invoice_number, invoice, created_at, created_by, status)
            VALUES (?, ?, ?, ?, NOW(), 1, 1)
        ");
        $trackerStmt->execute([$companyName, $invoiceType, $nextInvNum, $fullInvoice]);

        // =========================================================================
        // STEP D: Create QUOTATION Bill in approval Table (status = 'S')
        // =========================================================================
        $today = date('Y-m-d');
        $totalAmount = (float)($order['total_amount'] ?? 0);
        $payMethod = !empty($order['payment_method']) ? $order['payment_method'] : 'Online Prepaid';
        $txnId = !empty($order['transaction_id']) ? $order['transaction_id'] : '';
        $orderNumFormatted = format_order_number($orderId);
        $billNote = "Website Order #{$orderNumFormatted}"
            . ($txnId ? " (Txn: {$txnId})" : "")
            . ($totalDiscountGiven > 0 ? " | Flat Discount: Rs." . (round($totalDiscountGiven, 2) + 0) : "")
            . ($shippingCharge > 0 ? " | Shipping: Rs." . (round($shippingCharge, 2) + 0) : "");

        $hasAnyDiscount = ($totalDiscountGiven > 0 || $orderCouponDiscount > 0);
        $disStatus = $hasAnyDiscount ? 'yes' : 'no';
        $disWise = $hasAnyDiscount ? 'Item Wise' : '';
        $disPer = '';

        $appStmt = $posPdo->prepare("
            INSERT INTO approval
            (cust_id, bill_date, status, paid_amount, transaction_id, discount_status, discount_wise, discount_per, bill_by, pay_by, note, card_perc, card_amt, amountTotal, new_bill_number, company_name, bill_prepare_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, 'Website YN Online')
        ");
        $appStmt->execute([
            $posPersonId,
            $today,
            $billStatus,
            (int)round($totalAmount),
            $txnId,
            $disStatus,
            $disWise,
            $disPer,
            $billBy,
            $payMethod,
            $billNote,
            (string)round($totalAmount),
            $fullInvoice,
            $companyName
        ]);
        $billId = (int)$posPdo->lastInsertId();

        // =========================================================================
        // STEP E: Create Entry in paid_amount (Payment Receipt)
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
        // STEP F: Insert Line Items in approval_detail & Deduct POS + YN Stock
        // =========================================================================
        $deductedItems = [];

        foreach ($preparedLines as $line) {
            $posItem = $line['pos_item'];
            $itemIdToRecord = $line['item_id_record'];
            $qty = $line['qty'];
            $posUnitPriceStr = (string)(round($line['pos_unit_price'], 2) + 0);
            $linePaidStr = (string)(round($line['paid_amount'], 2) + 0);
            $lineDisAmount = $line['dis_amount'];
            $discountCol = $lineDisAmount > 0 ? (string)(round($lineDisAmount, 2) + 0) : '0';
            $itemPerCol = $lineDisAmount > 0 ? 'Rs' : '';

            $detailStmt = $posPdo->prepare("
                INSERT INTO approval_detail
                (bill_id, item_id, qty, discount, dis_amount, amount, item_per, final_amount, new_bill_number, status_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')
            ");
            $detailStmt->execute([
                $billId,
                $itemIdToRecord,
                $qty,
                $discountCol,
                $lineDisAmount,
                $linePaidStr,
                $itemPerCol,
                $posUnitPriceStr,
                $fullInvoice
            ]);

            if ($posItem && isset($posItem['item_id'])) {
                $decStmt = $posPdo->prepare("
                    UPDATE phppos_items
                    SET quantity = quantity - ?
                    WHERE item_id = ?
                ");
                $decStmt->execute([$qty, $posItem['item_id']]);
                $deductedItems[] = "{$itemIdToRecord} (-{$qty})";
            }

            if ($line['product_id'] > 0) {
                $pdo->prepare("UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?")
                    ->execute([$qty, $line['product_id']]);
            }
        }

        if ($posStartedTxn && $posPdo->inTransaction()) {
            $posPdo->commit();
        }

        // =========================================================================
        // STEP F: Update Local YN Order, Clear Cache, & Update Lookbook if Needed
        // =========================================================================
        $upOrder = $pdo->prepare("UPDATE orders SET pos_bill_number = ?, pos_synced_at = NOW() WHERE id = ?");
        $upOrder->execute([$fullInvoice, $orderId]);

        if (function_exists('purge_cache')) {
            purge_cache();
        }
        if ($hasOutfitItem && function_exists('sync_sold_outfits_to_lookbook')) {
            sync_sold_outfits_to_lookbook($pdo);
        }

        if (function_exists('log_activity')) {
            $deductSummary = !empty($deductedItems) ? ' | Items deducted in POS: ' . implode(', ', $deductedItems) : '';
            $custTag = $custRes['is_new'] ? "New POS Customer #{$posPersonId}" : "Existing POS Customer #{$posPersonId}";
            log_activity(
                $pdo,
                "Order #{$orderNumFormatted} synced to POS Quotation Bill {$fullInvoice} ({$custTag}, Company: {$companyName}){$deductSummary}",
                'orders',
                $orderId
            );
        }

        return [
            'success' => true,
            'bill_number' => $fullInvoice,
            'bill_id' => $billId,
            'bill_by' => $billBy,
            'company_name' => $companyName,
            'pos_customer_id' => $posPersonId,
            'is_new_customer' => $custRes['is_new'],
            'deducted_items' => $deductedItems,
            'message' => "Successfully generated POS Quotation Bill {$fullInvoice} ({$companyName}) and updated central inventory."
        ];

    } catch (Exception $ex) {
        if ($posStartedTxn && $posPdo->inTransaction()) {
            try {
                $posPdo->rollBack();
            } catch (Exception $rbEx) {}
        }
        error_log("POS Sync Error for Order #{$orderId}: " . $ex->getMessage());
        return [
            'success' => false,
            'message' => 'POS Sync Exception: ' . $ex->getMessage()
        ];
    }
}
