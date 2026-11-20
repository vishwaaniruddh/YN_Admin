<?php
// admin/order-detail.php
$page_title = "Order Details";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$rawId = trim($_GET['id'] ?? $_POST['order_id'] ?? '');
$orderId = 0;
if (preg_match('/^YNFS_(\d+)$/i', $rawId, $matches)) {
    $orderId = (int)$matches[1] - 1000;
} elseif (is_numeric($rawId)) {
    $val = (int)$rawId;
    $orderId = ($val > 1000) ? ($val - 1000) : $val;
}

if ($orderId <= 0) {
    header("Location: orders.php");
    exit();
}

$message = '';
$message_type = 'success';

if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Handle Actions (Resend Email, Update Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'resend_confirmation_email') {
        $result = send_order_email($pdo, $orderId, 'success');
        if ($result['success']) {
            $_SESSION['flash_message'] = "Order confirmation email resent successfully to the customer!";
            $_SESSION['flash_type'] = "success";
            log_activity($pdo, 'resend_order_email', 'order', $orderId, "Resent order confirmation email for order #$orderId");
        } else {
            $_SESSION['flash_message'] = "Failed to send email: " . ($result['error'] ?? 'Unknown error');
            $_SESSION['flash_type'] = "error";
        }
        header("Location: order-detail.php?id=" . urlencode($rawId));
        exit();
    } elseif ($action === 'update_status') {
        $new_status = trim($_POST['status'] ?? '');
        $courier_name = trim($_POST['courier_name'] ?? '');
        $tracking_number = trim($_POST['tracking_number'] ?? '');
        $allowed_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

        if (in_array($new_status, $allowed_statuses)) {
            try {
                $stmt = $pdo->prepare("UPDATE orders SET status = ?, courier_name = ?, tracking_number = ? WHERE id = ?");
                $stmt->execute([$new_status, $courier_name, $tracking_number, $orderId]);
                send_order_email($pdo, $orderId, $new_status);
                log_activity($pdo, 'update_order_status', 'order', $orderId, "Updated order #$orderId status to $new_status (Courier: $courier_name, POD: $tracking_number)");
                
                $_SESSION['flash_message'] = "Fulfillment status updated to <strong>$new_status</strong> and tracking details saved.";
                $_SESSION['flash_type'] = "success";
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = "Database Error: " . $e->getMessage();
                $_SESSION['flash_type'] = "error";
            }
        }
        header("Location: order-detail.php?id=" . urlencode($rawId));
        exit();
    } elseif ($action === 'sync_pos_approval_bill') {
        $result = sync_order_to_pos_approval_bill($pdo, $orderId);
        if ($result['success']) {
            $_SESSION['flash_message'] = "POS Approval Bill <strong>" . htmlspecialchars($result['bill_number']) . "</strong> generated and central stock updated!";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "POS Sync Failed: " . htmlspecialchars($result['message']);
            $_SESSION['flash_type'] = "error";
        }
        header("Location: order-detail.php?id=" . urlencode($rawId));
        exit();
    } elseif ($action === 'hold_refund') {
        $holdReason = sanitize_html(trim($_POST['hold_reason'] ?? ''));
        $res = hold_order_refund($pdo, $orderId, $holdReason);
        $_SESSION['flash_message'] = $res['message'];
        $_SESSION['flash_type'] = "success";
        header("Location: order-detail.php?id=" . urlencode($rawId));
        exit();
    } elseif ($action === 'process_refund') {
        $refundType = $_POST['refund_type'] ?? 'full';
        $orderTotal = (float)($_POST['order_total'] ?? 0);
        $refundAmount = ($refundType === 'partial') ? (float)($_POST['custom_refund_amount'] ?? 0) : $orderTotal;
        $reason = sanitize_html(trim($_POST['refund_reason'] ?? ''));
        $returnConfirmed = !empty($_POST['return_confirmed']);
        $restockInventory = !empty($_POST['restock_inventory']);

        $res = process_razorpay_refund($pdo, $orderId, $refundAmount, $reason, $returnConfirmed, $restockInventory);
        if ($res['success']) {
            $_SESSION['flash_message'] = $res['message'];
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Refund Request Notice: " . htmlspecialchars($res['message']);
            $_SESSION['flash_type'] = "error";
        }
        header("Location: order-detail.php?id=" . urlencode($rawId));
        exit();
    } elseif ($action === 'ajax_add_courier') {
        header('Content-Type: application/json');
        $courierName = sanitize_html(trim($_POST['courier_name'] ?? ''));
        $trackingUrl = trim($_POST['tracking_url'] ?? '');
        $contactNumber = sanitize_html(trim($_POST['contact_number'] ?? ''));

        if (empty($courierName)) {
            echo json_encode(['success' => false, 'message' => 'Courier partner name is required.']);
            exit();
        }

        try {
            $chk = $pdo->prepare("SELECT id, name, tracking_url, contact_number FROM logistics WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $chk->execute([$courierName]);
            $existing = $chk->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Reactivate if inactive
                $pdo->prepare("UPDATE logistics SET status = 'active' WHERE id = ?")->execute([$existing['id']]);
                echo json_encode([
                    'success' => true,
                    'is_new' => false,
                    'message' => 'Courier partner already exists and is now selected.',
                    'courier' => $existing
                ]);
                exit();
            }

            $ins = $pdo->prepare("INSERT INTO logistics (name, tracking_url, contact_number, status) VALUES (?, ?, ?, 'active')");
            $ins->execute([$courierName, $trackingUrl ?: null, $contactNumber ?: null]);
            $newId = (int)$pdo->lastInsertId();

            if (function_exists('log_activity')) {
                log_activity($pdo, 'add_courier_partner', 'logistics', $newId, "Added new courier partner: $courierName");
            }

            echo json_encode([
                'success' => true,
                'is_new' => true,
                'message' => "Courier partner \"$courierName\" added successfully!",
                'courier' => [
                    'id' => $newId,
                    'name' => $courierName,
                    'tracking_url' => $trackingUrl,
                    'contact_number' => $contactNumber
                ]
            ]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
    } elseif ($action === 'save_shipping_address') {
        $line1 = sanitize_html(trim($_POST['address_line_1'] ?? ''));
        $line2 = sanitize_html(trim($_POST['address_line_2'] ?? ''));
        $city = sanitize_html(trim($_POST['city'] ?? ''));
        $state = sanitize_html(trim($_POST['state'] ?? ''));
        $pincode = sanitize_html(trim($_POST['pincode'] ?? ''));

        $cStmt = $pdo->prepare("SELECT customer_id FROM orders WHERE id = ?");
        $cStmt->execute([$orderId]);
        $orderCustId = (int)$cStmt->fetchColumn();

        if ($orderCustId > 0 && !empty($line1) && !empty($city) && !empty($state) && !empty($pincode)) {
            try {
                // Set all prior addresses to non-default
                $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE customer_id = ?")->execute([$orderCustId]);

                // Insert new address
                $insStmt = $pdo->prepare("
                    INSERT INTO addresses (customer_id, address_line_1, address_line_2, city, state, pincode, country, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, 'India', 1)
                ");
                $insStmt->execute([$orderCustId, $line1, $line2, $city, $state, $pincode]);

                log_activity($pdo, 'update_shipping_address', 'order', $orderId, "Saved delivery address for order #$orderId (Customer #$orderCustId)");
                $_SESSION['flash_message'] = "Delivery address saved successfully.";
                $_SESSION['flash_type'] = "success";
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = "Database Error: " . $e->getMessage();
                $_SESSION['flash_type'] = "error";
            }
        } else {
            $_SESSION['flash_message'] = "Please provide all required address fields (Address, City, State, Pincode).";
            $_SESSION['flash_type'] = "error";
        }
        header("Location: order-detail.php?id=" . urlencode($rawId));
        exit();
    }
}

// Fetch Logistics Partners for dropdown
$logistics_partners = [];
try {
    $logistics_partners = $pdo->query("SELECT * FROM logistics WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch Order Details
$stmt = $pdo->prepare("
    SELECT o.*, c.first_name, c.last_name, c.email, c.phone, c.created_at as customer_since
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    require_once __DIR__ . '/includes/header.php';
    require_once __DIR__ . '/includes/sidebar.php';
    echo "<div class='notice notice-error'><p>Order not found.</p></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

// Fetch Items
$item_stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.main_image, p.sku, p.slug
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$item_stmt->execute([$orderId]);
$items = $item_stmt->fetchAll();

// Fetch Shipping Address
$addr_stmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC LIMIT 1");
$addr_stmt->execute([$order['customer_id']]);
$address = $addr_stmt->fetch() ?: [];

$orderNumber = format_order_number($order['id']);
$custName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: 'Guest Customer';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$statusClass = 'status-pill-default';
$statusIcon = 'fa-clock';
switch($order['status']) {
    case 'Delivered':
        $statusClass = 'status-pill-delivered';
        $statusIcon = 'fa-circle-check';
        break;
    case 'Shipped':
        $statusClass = 'status-pill-shipped';
        $statusIcon = 'fa-truck-fast';
        break;
    case 'Processing':
        $statusClass = 'status-pill-processing';
        $statusIcon = 'fa-gears';
        break;
    case 'Cancelled':
        $statusClass = 'status-pill-cancelled';
        $statusIcon = 'fa-circle-xmark';
        break;
    default:
        $statusClass = 'status-pill-pending';
        $statusIcon = 'fa-clock';
}
?>

<!-- Order Detail Header -->
<div class="dashboard-header-banner" style="margin-bottom: 20px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting" style="flex-wrap: wrap; gap: 10px;">
            <a href="orders.php" class="shadcn-btn shadcn-btn-outline" style="height: 32px; width: 32px; padding: 0;" title="Back to orders">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h1>Order <?php echo $orderNumber; ?></h1>
            <span class="status-pill <?php echo $statusClass; ?>" style="font-size: 12px; padding: 4px 10px;">
                <i class="fa-solid <?php echo $statusIcon; ?>" style="font-size: 11px;"></i>
                <?php echo htmlspecialchars($order['status']); ?>
            </span>
        </div>
        <p class="dashboard-subtitle">
            <i class="fa-regular fa-calendar" style="margin-right: 4px;"></i> Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?> 
            • Customer: <strong><?php echo sanitize_html($custName); ?></strong>
        </p>
    </div>
    
    <div class="dashboard-actions">
        <a href="invoice-pdf.php?id=<?php echo $order['id']; ?>&action=print&auto=1" target="_blank" class="shadcn-btn shadcn-btn-outline">
            <i class="fa-solid fa-print"></i> Print Invoice
        </a>
        <a href="invoice-pdf.php?id=<?php echo $order['id']; ?>&action=pdf&download=1" class="shadcn-btn shadcn-btn-primary">
            <i class="fa-solid fa-file-pdf"></i> Download PDF
        </a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo $message; ?></p>
    </div>
<?php endif; ?>

<!-- 2-Column Responsive Layout -->
<div class="wp-editor-columns" style="display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap;">

    <!-- Left Main Column -->
    <div class="main-column" style="flex: 1 1 580px; min-width: 320px;">
        
        <!-- Customer & Shipping Information Card -->
        <div class="shadcn-card" style="margin-bottom: 24px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title">
                    <i class="fa-solid fa-address-card" style="color: #71717a;"></i>
                    Customer &amp; Shipping Details
                </h2>
            </div>
            <div class="shadcn-card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; padding: 20px;">
                    
                    <!-- Customer Information Block -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 16px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                            <div style="width: 28px; height: 28px; border-radius: 6px; background: #ffffff; border: 1px solid #e4e4e7; display: flex; align-items: center; justify-content: center; color: #09090b; font-size: 12px;">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; color: #09090b;">Customer Profile</span>
                        </div>
                        <div style="font-weight: 700; font-size: 14.5px; color: #09090b; margin-bottom: 6px;">
                            <?php echo sanitize_html($custName); ?>
                        </div>
                        <div style="font-size: 12.5px; color: #52525b; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-regular fa-envelope" style="color: #a1a1aa; font-size: 11px;"></i>
                            <span><?php echo sanitize_html($order['email'] ?: 'On file'); ?></span>
                        </div>
                        <div style="font-size: 12.5px; color: #52525b; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-phone" style="color: #a1a1aa; font-size: 11px;"></i>
                            <span><?php echo sanitize_html($order['phone'] ?: 'On file'); ?></span>
                        </div>
                        <?php if (!empty($order['customer_since'])): ?>
                            <div style="font-size: 11.5px; color: #71717a; margin-top: 10px; padding-top: 8px; border-top: 1px solid #e4e4e7;">
                                Registered on <?php echo date('M d, Y', strtotime($order['customer_since'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Shipping Address Block -->
                    <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 28px; height: 28px; border-radius: 6px; background: #ffffff; border: 1px solid #e4e4e7; display: flex; align-items: center; justify-content: center; color: #09090b; font-size: 12px;">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <span style="font-size: 13px; font-weight: 600; color: #09090b;">Delivery Destination</span>
                            </div>
                            <button type="button" onclick="document.getElementById('editAddressModal').style.display='flex'" class="shadcn-btn shadcn-btn-outline" style="height: 26px; padding: 0 10px; font-size: 11px;">
                                <i class="fa-solid <?php echo !empty($address['address_line_1']) ? 'fa-pen-to-square' : 'fa-plus'; ?>" style="margin-right: 4px;"></i>
                                <?php echo !empty($address['address_line_1']) ? 'Edit Address' : 'Add Address'; ?>
                            </button>
                        </div>
                        <div style="font-size: 13px; line-height: 1.6; color: #09090b;">
                            <?php if (!empty($address['address_line_1'])): ?>
                                <strong><?php echo sanitize_html($address['address_line_1']); ?></strong><br>
                                <?php if (!empty($address['address_line_2'])) echo sanitize_html($address['address_line_2']) . '<br>'; ?>
                                <?php echo sanitize_html($address['city']); ?>, <?php echo sanitize_html($address['state']); ?> - <strong><?php echo sanitize_html($address['pincode']); ?></strong>
                            <?php else: ?>
                                <div style="padding: 10px 12px; background: #fff1f2; border: 1px dashed #f43f5e; border-radius: 6px; color: #e11d48; font-size: 12px;">
                                    <i class="fa-solid fa-circle-exclamation" style="margin-right: 5px;"></i>
                                    <strong>No address recorded</strong>. Click <strong>Add Address</strong> above to add customer's delivery address.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Purchased Items Card -->
        <div class="shadcn-card" style="margin-bottom: 24px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title">
                    <i class="fa-solid fa-bag-shopping" style="color: #71717a;"></i>
                    Purchased Items
                    <span class="shadcn-badge shadcn-badge-sky" style="font-size: 10px; margin-left: 6px;">
                        <?php echo count($items); ?> Item<?php echo count($items) > 1 ? 's' : ''; ?>
                    </span>
                </h2>
            </div>
            
            <div class="shadcn-card-body">
                <div style="overflow-x: auto;">
                    <table class="shadcn-table">
                        <thead>
                            <tr>
                                <th style="width: 65px;">Item</th>
                                <th>Product Name &amp; SKU</th>
                                <th style="width: 120px; text-align: right;">Unit Price</th>
                                <th style="width: 80px; text-align: center;">Qty</th>
                                <th style="width: 130px; text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($item['main_image'])): ?>
                                            <img src="<?php echo sanitize_html(get_product_image_url($item['main_image'])); ?>" alt="item" style="width: 44px; height: 56px; object-fit: cover; border-radius: 6px; border: 1px solid #e4e4e7;">
                                        <?php else: ?>
                                            <div style="width: 44px; height: 56px; background: #f4f4f5; border: 1px solid #e4e4e7; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #a1a1aa;">
                                                <i class="fa-solid fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; font-size: 13.5px; color: #09090b;">
                                            <?php echo sanitize_html($item['name']); ?>
                                        </div>
                                        <div style="margin-top: 4px;">
                                            <span style="font-family: monospace; font-size: 11px; background: #f4f4f5; color: #52525b; padding: 2px 6px; border-radius: 4px; border: 1px solid #e4e4e7;">
                                                <?php echo sanitize_html($item['sku']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td style="text-align: right; font-size: 13px; color: #52525b;">
                                        ₹<?php echo number_format($item['price'], 2); ?>
                                    </td>
                                    <td style="text-align: center; font-weight: 600; font-size: 13px;">
                                        <?php echo $item['quantity']; ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; font-size: 14px; color: #09090b;">
                                        ₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Financial Calculation Summary Box -->
                <div style="padding: 20px; display: flex; justify-content: flex-end; border-top: 1px solid #f4f4f5; background: #fafafa;">
                    <div style="width: 100%; max-width: 360px; background: #ffffff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 18px;">
                        
                        <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: #71717a; margin-bottom: 8px;">
                            <span>Payment Method</span>
                            <span style="color: #09090b; font-weight: 600;"><?php echo htmlspecialchars($order['payment_method'] ?: 'Online Payment'); ?></span>
                        </div>

                        <?php if (!empty($order['transaction_id'])): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #71717a; margin-bottom: 8px;">
                                <span>Transaction ID</span>
                                <span style="color: #09090b; font-weight: 600; font-family: monospace;"><?php echo htmlspecialchars($order['transaction_id']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($order['courier_name'])): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: #71717a; margin-bottom: 8px;">
                                <span>Courier Partner</span>
                                <span style="color: #09090b; font-weight: 600;"><?php echo htmlspecialchars($order['courier_name']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($order['tracking_number'])): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: #71717a; margin-bottom: 8px;">
                                <span>POD / Tracking No.</span>
                                <span style="color: #0284c7; font-weight: 700; font-family: monospace;"><?php echo htmlspecialchars($order['tracking_number']); ?></span>
                            </div>
                        <?php endif; ?>

                        <div style="height: 1px; background: #e4e4e7; margin: 12px 0;"></div>

                        <?php 
                        $computedSubtotal = $order['subtotal_amount'] > 0 ? (float)$order['subtotal_amount'] : array_sum(array_map(function($i){ return (float)$i['price'] * (int)$i['quantity']; }, $items));
                        ?>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: #71717a; margin-bottom: 8px;">
                            <span>Items Subtotal</span>
                            <span style="color: #09090b; font-weight: 600;">₹<?php echo number_format($computedSubtotal, 2); ?></span>
                        </div>

                        <?php if ($order['discount_amount'] > 0 || !empty($order['coupon_code'])): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #0d9488; margin-bottom: 8px; font-weight: 600;">
                                <span>Coupon Discount <?php echo !empty($order['coupon_code']) ? '(' . htmlspecialchars($order['coupon_code']) . ')' : ''; ?></span>
                                <span>-₹<?php echo number_format($order['discount_amount'], 2); ?></span>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: #71717a; margin-bottom: 12px;">
                            <span>Shipping Charge</span>
                            <span>
                                <?php if ($order['shipping_charge'] > 0): ?>
                                    <span style="color: #09090b; font-weight: 600;">₹<?php echo number_format($order['shipping_charge'], 2); ?></span>
                                <?php else: ?>
                                    <span style="color: #16a34a; font-weight: 700; font-size: 11.5px; background: #dcfce7; padding: 2px 7px; border-radius: 4px;">FREE</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div style="display: flex; justify-content: space-between; font-size: 17px; font-weight: 700; color: #09090b; border-top: 2px solid #e4e4e7; padding-top: 12px; margin-top: 4px;">
                            <span>Grand Total</span>
                            <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Right Side Column -->
    <div class="side-column" style="flex: 0 0 340px; min-width: 280px;">
        
        <!-- POS Inventory & Approval Bill Card -->
        <div class="shadcn-card" style="margin-bottom: 24px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title">
                    <i class="fa-solid fa-cash-register" style="color: #71717a;"></i>
                    POS Central Inventory &amp; Bill
                </h2>
            </div>
            <div class="shadcn-card-padded">
                <?php if (!empty($order['pos_bill_number'])): ?>
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px 14px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #065f46; font-size: 13.5px;">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>POS Approval Bill Created</span>
                        </div>
                        <div style="font-family: monospace; font-size: 14px; font-weight: 700; color: #09090b; margin-top: 6px;">
                            <?php echo htmlspecialchars($order['pos_bill_number']); ?>
                        </div>
                        <div style="font-size: 11.5px; color: #047857; margin-top: 4px;">
                            <i class="fa-solid fa-clock"></i> Synced: <?php echo !empty($order['pos_synced_at']) ? date('d M Y, h:i A', strtotime($order['pos_synced_at'])) : 'Completed'; ?>
                        </div>
                        <div style="font-size: 11.5px; color: #065f46; margin-top: 2px;">
                            <i class="fa-solid fa-boxes-stacked"></i> Central inventory deducted in POS.
                        </div>
                    </div>
                    <a href="http://localhost/ss/pos/reports/approval.php" target="_blank" class="shadcn-btn shadcn-btn-outline" style="width: 100%; box-sizing: border-box; justify-content: center; font-size: 12.5px;">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Open POS Approval System
                    </a>
                <?php else: ?>
                    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
                        <div style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: #92400e; font-size: 13px;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Not Synced to POS</span>
                        </div>
                        <p style="font-size: 12px; color: #78350f; margin: 4px 0 0 0; line-height: 1.4;">
                            Generate an approval bill in the POS system to create this customer and deduct quantities from the central inventory.
                        </p>
                    </div>
                    <form method="POST" action="order-detail.php?id=<?php echo $order['id']; ?>">
                        <input type="hidden" name="action" value="sync_pos_approval_bill">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <button type="submit" class="shadcn-btn shadcn-btn-primary" style="width: 100%; justify-content: center; background: #09090b;">
                            <i class="fa-solid fa-file-invoice"></i> Generate POS Approval Bill
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Fulfillment & Tracking Card -->
        <div class="shadcn-card" style="margin-bottom: 24px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title">
                    <i class="fa-solid fa-truck-ramp-box" style="color: #71717a;"></i>
                    Fulfillment &amp; Shipment
                </h2>
            </div>
            <div class="shadcn-card-padded">
                <form method="POST" action="order-detail.php?id=<?php echo $order['id']; ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    
                    <div class="form-group">
                        <label>Fulfillment Status</label>
                        <select name="status" class="form-control" style="width: 100%; font-weight: 600;">
                            <option value="Pending" <?php echo ($order['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processing" <?php echo ($order['status'] === 'Processing') ? 'selected' : ''; ?>>Processing</option>
                            <option value="Shipped" <?php echo ($order['status'] === 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                            <option value="Delivered" <?php echo ($order['status'] === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                            <option value="Cancelled" <?php echo ($order['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label style="margin-bottom: 0;">Logistics Partner</label>
                            <button type="button" onclick="openAddCourierModal()" style="background: none; border: none; color: #4338ca; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; padding: 0;">
                                <i class="fa-solid fa-plus-circle"></i> + Add New Courier
                            </button>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <select name="courier_name" id="courier_select" class="form-control" style="flex: 1;" onchange="handleCourierSelectChange(this)">
                                <option value="">-- Select Logistics Partner --</option>
                                <?php foreach ($logistics_partners as $log): ?>
                                    <option value="<?php echo htmlspecialchars($log['name']); ?>" <?php echo ($order['courier_name'] === $log['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($log['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!empty($order['courier_name']) && !in_array($order['courier_name'], array_column($logistics_partners, 'name'))): ?>
                                    <option value="<?php echo htmlspecialchars($order['courier_name']); ?>" selected><?php echo htmlspecialchars($order['courier_name']); ?></option>
                                <?php endif; ?>
                                <option value="__add_new__" style="color: #4338ca; font-weight: 600;">+ Add New Courier...</option>
                            </select>
                            <button type="button" onclick="openAddCourierModal()" title="Add New Courier Partner" style="background: #f4f4f5; border: 1px solid #e4e4e7; border-radius: 6px; padding: 0 12px; color: #09090b; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px;" onmouseover="this.style.background='#e4e4e7'" onmouseout="this.style.background='#f4f4f5'">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <div id="courier_success_hint" style="display: none; margin-top: 6px; font-size: 12px; color: #15803d; font-weight: 500; align-items: center; gap: 5px;">
                            <i class="fa-solid fa-circle-check"></i> <span id="courier_success_hint_text"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>POD / Tracking Number (AWB)</label>
                        <input type="text" name="tracking_number" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>" placeholder="e.g. BD123456789IN" class="form-control" style="width: 100%; font-family: monospace;">
                    </div>

                    <button type="submit" class="shadcn-btn shadcn-btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-check"></i> Save &amp; Notify Customer
                    </button>
                </form>
            </div>
        </div>

        <!-- Cancellation & Razorpay Refund Card -->
        <div class="shadcn-card" style="margin-bottom: 24px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title">
                    <i class="fa-solid fa-hand-holding-dollar" style="color: #71717a;"></i>
                    Cancellation &amp; Razorpay Refund
                </h2>
            </div>
            <div class="shadcn-card-padded">
                <?php if ($order['refund_status'] === 'refunded' || $order['refund_status'] === 'partially_refunded'): ?>
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px 14px;">
                        <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #065f46; font-size: 13.5px;">
                            <i class="fa-solid fa-circle-check"></i>
                            <span><?php echo ($order['refund_status'] === 'partially_refunded') ? 'Partial Refund Processed' : 'Full Refund Processed'; ?></span>
                        </div>
                        <div style="font-size: 17px; font-weight: 700; color: #09090b; margin-top: 6px;">
                            ₹<?php echo number_format($order['refund_amount'], 2); ?>
                        </div>
                        <div style="font-size: 11.5px; color: #047857; margin-top: 4px; font-family: monospace;">
                            <strong>Refund ID:</strong> <?php echo htmlspecialchars($order['refund_id'] ?: 'N/A'); ?>
                        </div>
                        <div style="font-size: 11px; color: #71717a; margin-top: 3px;">
                            Reversed at: <?php echo !empty($order['refunded_at']) ? date('d M Y, h:i A', strtotime($order['refunded_at'])) : 'Completed'; ?>
                        </div>
                        <?php if (!empty($order['return_received'])): ?>
                            <div style="margin-top: 6px; font-size: 11.5px; color: #065f46; font-weight: 500;">
                                <i class="fa-solid fa-box-open"></i> Returned package received &amp; inspected.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($order['refund_status'] === 'on_hold'): ?>
                    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 14px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 6px; font-weight: 700; color: #92400e; font-size: 13px;">
                            <i class="fa-solid fa-hand"></i>
                            <span>Refund On Hold (Awaiting Return)</span>
                        </div>
                        <p style="font-size: 12px; color: #78350f; margin: 6px 0 0 0; line-height: 1.4;">
                            <?php echo htmlspecialchars($order['refund_reason'] ?: 'Awaiting return package receipt and physical inspection at our studio.'); ?>
                        </p>
                        <div style="font-size: 11px; color: #b45309; margin-top: 6px; font-weight: 600;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Do not release refund until package is received back.
                        </div>
                    </div>
                    <button type="button" onclick="openRefundModal(true)" class="shadcn-btn shadcn-btn-primary" style="width: 100%; justify-content: center; background: #059669;">
                        <i class="fa-solid fa-circle-check"></i> Package Received: Issue Refund
                    </button>
                <?php else: ?>
                    <?php 
                    $isDispatched = in_array(strtolower($order['status'] ?? ''), ['shipped', 'delivered']);
                    ?>
                    <?php if ($isDispatched): ?>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 12px;">
                            <div style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: #334155; font-size: 12.5px;">
                                <i class="fa-solid fa-truck-fast" style="color: #0284c7;"></i>
                                <span>Product Already Dispatched</span>
                            </div>
                            <p style="font-size: 11.5px; color: #64748b; margin: 4px 0 0 0; line-height: 1.4;">
                                If the customer cancels, put the refund on hold until the physical package is returned to the studio.
                            </p>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <form method="POST" action="order-detail.php?id=<?php echo $order['id']; ?>" onsubmit="return confirm('Hold refund for this order until the customer returns the product?');">
                                <input type="hidden" name="action" value="hold_refund">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="hold_reason" value="Product in transit/delivered. Refund placed on hold awaiting physical return inspection.">
                                <button type="submit" class="shadcn-btn shadcn-btn-outline" style="width: 100%; justify-content: center; color: #b45309; border-color: #fde68a; background: #fffbeb;">
                                    <i class="fa-solid fa-hand"></i> Hold Refund (Awaiting Return)
                                </button>
                            </form>
                            <button type="button" onclick="openRefundModal(false)" class="shadcn-btn shadcn-btn-outline" style="width: 100%; justify-content: center;">
                                <i class="fa-solid fa-arrow-rotate-left"></i> Inspect Return &amp; Refund
                            </button>
                        </div>
                    <?php else: ?>
                        <p style="font-size: 12px; color: #71717a; margin: 0 0 12px 0; line-height: 1.4;">
                            Order is not dispatched yet. You can cancel and issue a full or custom partial refund via Razorpay.
                        </p>
                        <button type="button" onclick="openRefundModal(true)" class="shadcn-btn shadcn-btn-outline" style="width: 100%; justify-content: center; color: #e11d48; border-color: #fecdd3;">
                            <i class="fa-solid fa-ban"></i> Cancel &amp; Issue Refund
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Email Actions Card -->
        <div class="shadcn-card" style="margin-bottom: 24px;">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title">
                    <i class="fa-regular fa-paper-plane" style="color: #71717a;"></i>
                    Email Notifications
                </h2>
            </div>
            <div class="shadcn-card-padded">
                <p style="font-size: 12.5px; color: #71717a; margin: 0 0 14px 0; line-height: 1.5;">
                    Send or resend order summary receipt to <code><?php echo htmlspecialchars($order['email']); ?></code> via configured SMTP gateway.
                </p>
                <form method="POST" action="order-detail.php?id=<?php echo $order['id']; ?>" style="margin-bottom: 12px;">
                    <input type="hidden" name="action" value="resend_confirmation_email">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <button type="submit" class="shadcn-btn shadcn-btn-outline" style="width: 100%;">
                        <i class="fa-solid fa-rotate"></i> Resend Confirmation Email
                    </button>
                </form>
                <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>?subject=Regarding%20Order%20<?php echo $orderNumber; ?>" class="shadcn-btn shadcn-btn-ghost" style="width: 100%; box-sizing: border-box;">
                    <i class="fa-solid fa-envelope-open-text"></i> Email Customer Directly
                </a>
            </div>
        </div>

        <!-- Invoice Documents Card -->
        <div class="shadcn-card">
            <div class="shadcn-card-header">
                <h2 class="shadcn-card-title">
                    <i class="fa-solid fa-file-lines" style="color: #71717a;"></i>
                    Invoice Documents
                </h2>
            </div>
            <div class="shadcn-card-padded" style="display: flex; flex-direction: column; gap: 8px;">
                <a href="invoice-pdf.php?id=<?php echo $order['id']; ?>&action=pdf&download=1" class="shadcn-btn shadcn-btn-outline" style="width: 100%; justify-content: flex-start; box-sizing: border-box;">
                    <i class="fa-solid fa-file-pdf" style="color: #ef4444; width: 16px;"></i> Download PDF Invoice
                </a>
                <a href="invoice-pdf.php?id=<?php echo $order['id']; ?>&action=print&auto=1" target="_blank" class="shadcn-btn shadcn-btn-outline" style="width: 100%; justify-content: flex-start; box-sizing: border-box;">
                    <i class="fa-solid fa-print" style="color: #3b82f6; width: 16px;"></i> Print Formal Invoice
                </a>
            </div>
        </div>

    </div>

</div>

<!-- Edit / Add Delivery Address Modal -->
<div id="editAddressModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; padding: 20px;" onclick="if(event.target===this) this.style.display='none';">
    <div style="background: #ffffff; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); overflow: hidden; animation: fadeIn 0.15s ease-out;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e4e4e7; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #f4f4f5; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #09090b;">
                    <i class="fa-solid fa-location-dot"></i>
                </div>
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #09090b;">
                    <?php echo !empty($address['address_line_1']) ? 'Edit Delivery Address' : 'Add Delivery Address'; ?>
                </h3>
            </div>
            <button type="button" onclick="document.getElementById('editAddressModal').style.display='none'" style="border: none; background: none; color: #71717a; cursor: pointer; font-size: 16px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="order-detail.php?id=<?php echo $order['id']; ?>" style="padding: 20px;">
            <input type="hidden" name="action" value="save_shipping_address">
            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
            
            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 6px;">
                    Flat / House No. / Building / Street <span style="color: #e11d48;">*</span>
                </label>
                <input type="text" name="address_line_1" required value="<?php echo htmlspecialchars($address['address_line_1'] ?? ''); ?>" placeholder="e.g. Flat 302, Green Valley Apts" class="shadcn-input" style="width: 100%; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 6px;">
                    Area / Street / Landmark <span style="color: #71717a; font-weight: 400;">(Optional)</span>
                </label>
                <input type="text" name="address_line_2" value="<?php echo htmlspecialchars($address['address_line_2'] ?? ''); ?>" placeholder="e.g. Near St. Mary's School" class="shadcn-input" style="width: 100%; box-sizing: border-box;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 6px;">
                        City <span style="color: #e11d48;">*</span>
                    </label>
                    <input type="text" name="city" required value="<?php echo htmlspecialchars($address['city'] ?? ''); ?>" placeholder="e.g. Mumbai" class="shadcn-input" style="width: 100%; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 6px;">
                        State <span style="color: #e11d48;">*</span>
                    </label>
                    <input type="text" name="state" required value="<?php echo htmlspecialchars($address['state'] ?? ''); ?>" placeholder="e.g. Maharashtra" class="shadcn-input" style="width: 100%; box-sizing: border-box;">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 6px;">
                    PIN Code <span style="color: #e11d48;">*</span>
                </label>
                <input type="text" name="pincode" required maxlength="10" value="<?php echo htmlspecialchars($address['pincode'] ?? ''); ?>" placeholder="e.g. 400050" class="shadcn-input" style="width: 100%; box-sizing: border-box;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 14px; border-top: 1px solid #e4e4e7;">
                <button type="button" onclick="document.getElementById('editAddressModal').style.display='none'" class="shadcn-btn shadcn-btn-outline">
                    Cancel
                </button>
                <button type="submit" class="shadcn-btn shadcn-btn-primary">
                    <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i> Save Address
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Process Razorpay Refund Modal (Full & Partial + Return Inspection Guard) -->
<div id="refundModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; padding: 20px;" onclick="if(event.target===this) closeRefundModal();">
    <div style="background: #ffffff; border-radius: 14px; width: 100%; max-width: 540px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); overflow: hidden; animation: fadeIn 0.15s ease-out;">
        
        <div style="padding: 16px 22px; border-bottom: 1px solid #e4e4e7; display: flex; align-items: center; justify-content: space-between; background: #fafafa;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(225, 29, 72, 0.1); color: #e11d48; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                    <i class="fa-solid fa-arrow-rotate-left"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #09090b;">
                        Issue Razorpay Refund
                    </h3>
                    <div style="font-size: 11.5px; color: #71717a;">
                        Order #<?php echo $orderNumber; ?> &bull; Total Paid: ₹<?php echo number_format($order['total_amount'], 2); ?>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeRefundModal()" style="border: none; background: none; color: #71717a; cursor: pointer; font-size: 18px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="order-detail.php?id=<?php echo $order['id']; ?>" onsubmit="return confirmRefundSubmission();" style="padding: 22px;">
            <input type="hidden" name="action" value="process_refund">
            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
            <input type="hidden" name="order_total" id="modal_order_total" value="<?php echo (float)$order['total_amount']; ?>">

            <!-- Return Inspection Guard Notice for Dispatched Orders -->
            <?php 
            $isDispatchedForModal = in_array(strtolower($order['status'] ?? ''), ['shipped', 'delivered']);
            ?>
            <div id="return_guard_container" style="<?php echo $isDispatchedForModal ? '' : 'display: none;'; ?> margin-bottom: 18px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 14px;">
                <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #991b1b; font-size: 13px; margin-bottom: 4px;">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Physical Return Verification Required</span>
                </div>
                <p style="font-size: 12px; color: #7f1d1d; margin: 0 0 10px 0; line-height: 1.4;">
                    This order was marked as <strong><?php echo htmlspecialchars($order['status']); ?></strong>. If the customer has not returned the product yet, hold the refund instead.
                </p>
                <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; color: #991b1b; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="return_confirmed" id="return_confirmed_checkbox" value="1" style="margin-top: 2px;">
                    <span>I confirm the returned product has been physically received and inspected at our studio.</span>
                </label>
            </div>

            <!-- Refund Type Selection (Full vs Custom/Partial) -->
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #09090b; margin-bottom: 8px;">
                    Refund Calculation Type <span style="color: #e11d48;">*</span>
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    
                    <label style="border: 1px solid #e4e4e7; border-radius: 8px; padding: 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; background: #ffffff;" id="label_full_refund">
                        <input type="radio" name="refund_type" value="full" checked onchange="toggleRefundType('full')" style="accent-color: #09090b;">
                        <div>
                            <div style="font-weight: 600; font-size: 13px; color: #09090b;">Full 100% Refund</div>
                            <div style="font-size: 11.5px; color: #059669; font-weight: 600;">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                        </div>
                    </label>

                    <label style="border: 1px solid #e4e4e7; border-radius: 8px; padding: 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; background: #ffffff;" id="label_partial_refund">
                        <input type="radio" name="refund_type" value="partial" onchange="toggleRefundType('partial')" style="accent-color: #09090b;">
                        <div>
                            <div style="font-weight: 600; font-size: 13px; color: #09090b;">Custom / Partial</div>
                            <div style="font-size: 11.5px; color: #71717a;">Deduct shipping / fees</div>
                        </div>
                    </label>

                </div>
            </div>

            <!-- Custom / Partial Amount Input -->
            <div id="partial_amount_group" style="display: none; margin-bottom: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label style="font-size: 12px; font-weight: 700; color: #0f172a;">
                        Custom Refund Amount (₹) <span style="color: #e11d48;">*</span>
                    </label>
                    <span style="font-size: 11px; color: #64748b;">Max: ₹<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                <div style="position: relative;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #64748b; font-size: 14px;">₹</span>
                    <input type="number" id="custom_refund_amount_input" name="custom_refund_amount" min="1" max="<?php echo (float)$order['total_amount']; ?>" step="0.01" value="<?php echo (float)$order['total_amount']; ?>" class="shadcn-input" style="width: 100%; box-sizing: border-box; padding-left: 28px; font-weight: 700; font-size: 15px; color: #09090b;" oninput="updateDeductionPreview()">
                </div>
                <div id="deduction_preview" style="font-size: 11.5px; color: #b45309; margin-top: 6px; font-weight: 500;">
                    <!-- Auto-updated via JS -->
                </div>
            </div>

            <!-- Reason Field -->
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #09090b; margin-bottom: 6px;">
                    Cancellation &amp; Refund Reason <span style="color: #e11d48;">*</span>
                </label>
                <select id="reason_preset_select" onchange="applyReasonPreset(this.value)" class="shadcn-input" style="width: 100%; box-sizing: border-box; margin-bottom: 8px; font-size: 12.5px;">
                    <option value="">-- Choose Common Reason or Type Below --</option>
                    <option value="Customer requested cancellation before dispatch">Customer requested cancellation before dispatch</option>
                    <option value="Return received & inspected in original condition; full refund released">Return received &amp; inspected in original condition; full refund released</option>
                    <option value="Customer returned item; refund released excluding shipping charge">Customer returned item; refund released excluding shipping charge</option>
                    <option value="Size / fitting exchange requested">Size / fitting exchange requested</option>
                    <option value="Defective or damaged parcel returned">Defective or damaged parcel returned</option>
                </select>
                <input type="text" id="refund_reason_input" name="refund_reason" required placeholder="e.g. Returned parcel inspected; refund processed to UPI" class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 12.5px;" value="Customer cancellation / Return inspected">
            </div>

            <!-- Restock Inventory Checkbox -->
            <div style="margin-bottom: 20px; background: #f4f4f5; padding: 10px 14px; border-radius: 8px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: #09090b; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="restock_inventory" value="1" checked style="accent-color: #09090b;">
                    <span>Restock returned quantity back into central POS inventory (phppos_items)</span>
                </label>
            </div>

            <!-- Modal Action Buttons -->
            <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 16px; border-top: 1px solid #e4e4e7;">
                <button type="button" onclick="closeRefundModal()" class="shadcn-btn shadcn-btn-outline">
                    Cancel
                </button>
                <button type="submit" class="shadcn-btn shadcn-btn-primary" style="background: #e11d48; border-color: #e11d48; color: #ffffff;">
                    <i class="fa-solid fa-arrow-rotate-left" style="margin-right: 6px;"></i> Process Razorpay Refund
                </button>
            </div>

        </form>
    </div>
</div>

<!-- Add New Courier Partner Modal -->
<div id="addCourierModal" style="display: none; position: fixed; inset: 0; z-index: 10000; background: rgba(0, 0, 0, 0.55); backdrop-filter: blur(2px); align-items: center; justify-content: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); width: 100%; max-width: 480px; overflow: hidden; border: 1px solid #e4e4e7;">
        <!-- Header -->
        <div style="padding: 16px 20px; border-bottom: 1px solid #f4f4f5; display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
            <div>
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #09090b; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-truck-fast" style="color: #4338ca;"></i> Add New Courier Partner
                </h3>
                <p style="margin: 2px 0 0; font-size: 12px; color: #71717a;">Instantly adds partner to list and selects it for this order without reloading.</p>
            </div>
            <button type="button" onclick="closeAddCourierModal()" style="background: none; border: none; font-size: 18px; color: #71717a; cursor: pointer; padding: 4px 8px; border-radius: 4px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Body / Form -->
        <form id="addCourierForm" onsubmit="submitNewCourier(event)" style="padding: 20px; margin: 0;">
            <div id="add_courier_error" style="display: none; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 8px 12px; border-radius: 6px; font-size: 12.5px; margin-bottom: 14px;"></div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #09090b; margin-bottom: 5px;">
                    Courier Partner Name <span style="color: #e11d48;">*</span>
                </label>
                <input type="text" id="new_courier_name" required placeholder="e.g. Shiprocket, Trackon, Tirupati Courier, Smartr" class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13px; height: 38px;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #09090b; margin-bottom: 5px;">
                    Tracking URL Template <span style="font-size: 11.5px; font-weight: 400; color: #71717a;">(Optional)</span>
                </label>
                <input type="url" id="new_courier_tracking_url" placeholder="https://example.com/track?awb={TRACKING_NO}" class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13px; height: 38px; font-family: monospace;">
                <span style="font-size: 11px; color: #71717a; display: block; margin-top: 3px;">Use <code>{TRACKING_NO}</code> as placeholder for tracking number in customer emails.</span>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #09090b; margin-bottom: 5px;">
                    Contact / Helpline Phone <span style="font-size: 11.5px; font-weight: 400; color: #71717a;">(Optional)</span>
                </label>
                <input type="text" id="new_courier_phone" placeholder="e.g. 1800-XXX-XXXX or +91 9876543210" class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13px; height: 38px;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f4f4f5; padding-top: 16px;">
                <button type="button" onclick="closeAddCourierModal()" class="shadcn-btn shadcn-btn-outline" style="font-size: 13px;">
                    Cancel
                </button>
                <button type="submit" id="btn_save_courier" class="shadcn-btn shadcn-btn-primary" style="background: #4338ca; border-color: #4338ca; color: #ffffff; font-size: 13px;">
                    <i class="fa-solid fa-plus" style="margin-right: 5px;"></i> Save &amp; Select Courier
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const ORDER_TOTAL_AMOUNT = <?php echo (float)$order['total_amount']; ?>;
const IS_DISPATCHED = <?php echo $isDispatchedForModal ? 'true' : 'false'; ?>;

function openRefundModal(preConfirmReturn = false) {
    const modal = document.getElementById('refundModal');
    const returnGuard = document.getElementById('return_guard_container');
    const returnCheckbox = document.getElementById('return_confirmed_checkbox');

    if (IS_DISPATCHED) {
        returnGuard.style.display = 'block';
        if (preConfirmReturn && returnCheckbox) {
            returnCheckbox.checked = true;
        }
    } else {
        returnGuard.style.display = 'none';
        if (returnCheckbox) returnCheckbox.checked = true;
    }

    toggleRefundType('full');
    modal.style.display = 'flex';
}

function closeRefundModal() {
    document.getElementById('refundModal').style.display = 'none';
}

function toggleRefundType(type) {
    const partialGroup = document.getElementById('partial_amount_group');
    const customInput = document.getElementById('custom_refund_amount_input');
    const labelFull = document.getElementById('label_full_refund');
    const labelPartial = document.getElementById('label_partial_refund');

    if (type === 'partial') {
        partialGroup.style.display = 'block';
        labelPartial.style.borderColor = '#09090b';
        labelFull.style.borderColor = '#e4e4e7';
        updateDeductionPreview();
    } else {
        partialGroup.style.display = 'none';
        customInput.value = ORDER_TOTAL_AMOUNT;
        labelFull.style.borderColor = '#09090b';
        labelPartial.style.borderColor = '#e4e4e7';
    }
}

function updateDeductionPreview() {
    const customInput = document.getElementById('custom_refund_amount_input');
    const preview = document.getElementById('deduction_preview');
    const val = parseFloat(customInput.value) || 0;
    const diff = Math.max(0, ORDER_TOTAL_AMOUNT - val);

    if (diff > 0) {
        preview.innerHTML = `<strong>Deduction: ₹${diff.toFixed(2)}</strong> will be retained by your studio (Customer receives ₹${val.toFixed(2)}).`;
    } else {
        preview.innerHTML = `Customer receives full ₹${val.toFixed(2)} with no deductions.`;
    }
}

function applyReasonPreset(val) {
    if (val) {
        document.getElementById('refund_reason_input').value = val;
    }
}

function confirmRefundSubmission() {
    const isDispatched = IS_DISPATCHED;
    const returnCheckbox = document.getElementById('return_confirmed_checkbox');

    if (isDispatched && returnCheckbox && !returnCheckbox.checked) {
        alert("Return Verification Notice:\nThis order was already shipped/delivered. You cannot release a refund until you confirm that the returned physical parcel has been received and inspected at your studio.");
        returnCheckbox.focus();
        return false;
    }

    const refundType = document.querySelector('input[name="refund_type"]:checked').value;
    const refundAmt = (refundType === 'partial') ? parseFloat(document.getElementById('custom_refund_amount_input').value) : ORDER_TOTAL_AMOUNT;

    if (isNaN(refundAmt) || refundAmt <= 0) {
        alert("Please enter a valid refund amount greater than ₹0.");
        return false;
    }

    return confirm(`Are you sure you want to process a Razorpay refund of ₹${refundAmt.toFixed(2)} for Order #<?php echo $orderNumber; ?>?\n\nThis will instantly reverse funds back to the customer's source account.`);
}

// -------------------------------------------------------------------------
// Courier Partner AJAX Management (Without Page Reload)
// -------------------------------------------------------------------------
function handleCourierSelectChange(selectElem) {
    if (selectElem.value === '__add_new__') {
        selectElem.value = '';
        openAddCourierModal();
    }
}

function openAddCourierModal() {
    const modal = document.getElementById('addCourierModal');
    if (!modal) return;
    const errDiv = document.getElementById('add_courier_error');
    if (errDiv) errDiv.style.display = 'none';
    document.getElementById('new_courier_name').value = '';
    document.getElementById('new_courier_tracking_url').value = '';
    document.getElementById('new_courier_phone').value = '';
    modal.style.display = 'flex';
    setTimeout(() => {
        const input = document.getElementById('new_courier_name');
        if (input) input.focus();
    }, 100);
}

function closeAddCourierModal() {
    const modal = document.getElementById('addCourierModal');
    if (modal) modal.style.display = 'none';
}

function submitNewCourier(e) {
    e.preventDefault();
    const nameInput = document.getElementById('new_courier_name');
    const urlInput = document.getElementById('new_courier_tracking_url');
    const phoneInput = document.getElementById('new_courier_phone');
    const errorDiv = document.getElementById('add_courier_error');
    const submitBtn = document.getElementById('btn_save_courier');

    const courierName = nameInput.value.trim();
    if (!courierName) {
        errorDiv.textContent = 'Please enter courier partner name.';
        errorDiv.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right: 5px;"></i> Saving...';
    errorDiv.style.display = 'none';

    const formData = new FormData();
    formData.append('action', 'ajax_add_courier');
    formData.append('courier_name', courierName);
    formData.append('tracking_url', urlInput.value.trim());
    formData.append('contact_number', phoneInput.value.trim());

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-plus" style="margin-right: 5px;"></i> Save &amp; Select Courier';

        if (!data.success) {
            errorDiv.textContent = data.message || 'Failed to add courier partner.';
            errorDiv.style.display = 'block';
            return;
        }

        // Add to select dropdown if not already present
        const select = document.getElementById('courier_select');
        let exists = false;
        for (let i = 0; i < select.options.length; i++) {
            if (select.options[i].value.toLowerCase() === data.courier.name.toLowerCase()) {
                select.options[i].selected = true;
                exists = true;
                break;
            }
        }

        if (!exists) {
            // Insert right before the last '__add_new__' option
            const newOption = new Option(data.courier.name, data.courier.name, true, true);
            const addNewOpt = select.querySelector('option[value="__add_new__"]');
            if (addNewOpt) {
                select.insertBefore(newOption, addNewOpt);
            } else {
                select.appendChild(newOption);
            }
            select.value = data.courier.name;
        }

        closeAddCourierModal();

        // Show inline feedback message
        const hint = document.getElementById('courier_success_hint');
        const hintText = document.getElementById('courier_success_hint_text');
        if (hint && hintText) {
            hintText.textContent = data.is_new ? `Courier "${data.courier.name}" added and selected!` : `Courier "${data.courier.name}" selected!`;
            hint.style.display = 'flex';
            setTimeout(() => {
                hint.style.display = 'none';
            }, 6000);
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-plus" style="margin-right: 5px;"></i> Save &amp; Select Courier';
        errorDiv.textContent = 'Network or server error. Please try again.';
        errorDiv.style.display = 'block';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
