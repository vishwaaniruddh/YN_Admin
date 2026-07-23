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
?>

<div class="wrap-header">
    <div>
        <h1 style="display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-receipt" style="color: var(--wp-blue);"></i> Order Details: <?php echo $orderNumber; ?>
            <span style="
                font-size: 11px; 
                font-weight: 700; 
                padding: 3px 10px; 
                border-radius: 12px;
                text-transform: uppercase;
                margin-left: 8px;
                <?php 
                    switch($order['status']) {
                        case 'Delivered': echo 'background: #e6f4ea; color: #137333;'; break;
                        case 'Shipped': echo 'background: #e8f0fe; color: #1a73e8;'; break;
                        case 'Processing': echo 'background: #fef7e0; color: #b06000;'; break;
                        case 'Cancelled': echo 'background: #fce8e6; color: #c5221f;'; break;
                        default: echo 'background: #f1f3f4; color: #3c4043;';
                    }
                ?>
            ">
                <?php echo htmlspecialchars($order['status']); ?>
            </span>
        </h1>
        <div style="font-size: 12px; color: #646970; margin-top: 4px;">
            Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?>
        </div>
    </div>

    <div style="display: flex; gap: 8px;">
        <a href="orders.php" class="button"><i class="fa-solid fa-arrow-left"></i> Back to Orders</a>
        <a href="invoice-pdf.php?id=<?php echo $order['id']; ?>&action=print&auto=1" target="_blank" class="button button-secondary"><i class="fa-solid fa-print"></i> Print Invoice</a>
        <a href="invoice-pdf.php?id=<?php echo $order['id']; ?>&action=pdf&download=1" class="button button-primary"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
    </div>
</div>

<?php if (!empty($message)): ?>
<div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
    <p><?php echo $message; ?></p>
</div>
<?php endif; ?>

<!-- WordPress 2-Column Editor Layout -->
<div class="wp-editor-columns" style="display: flex; gap: 20px; flex-wrap: wrap;">

    <!-- Left Column: Primary Order Content -->
    <div class="main-column" style="flex: 1; min-width: 500px;">
        
        <!-- Customer & Shipping Information Box -->
        <div class="postbox" style="margin-bottom: 20px;">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-address-card" style="color: var(--wp-blue);"></i> Customer &amp; Delivery Information</h2>
            </div>
            <div class="postbox-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 20px;">
                
                <!-- Customer Info -->
                <div style="background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                    <h4 style="margin: 0 0 10px 0; color: var(--wp-blue); font-size: 14px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-user"></i> Customer Info
                    </h4>
                    <div style="font-weight: 700; font-size: 15px; color: #1d2327; margin-bottom: 4px;"><?php echo sanitize_html($custName); ?></div>
                    <div style="font-size: 13px; color: #50575e; margin-bottom: 3px;">
                        <i class="fa-solid fa-envelope" style="font-size: 11px;"></i> <?php echo sanitize_html($order['email'] ?: 'On file'); ?>
                    </div>
                    <div style="font-size: 13px; color: #50575e;">
                        <i class="fa-solid fa-phone" style="font-size: 11px;"></i> <?php echo sanitize_html($order['phone'] ?: 'On file'); ?>
                    </div>
                    <?php if (!empty($order['customer_since'])): ?>
                        <div style="font-size: 11px; color: #646970; margin-top: 8px; padding-top: 8px; border-top: 1px solid #e2e8f0;">
                            Customer registered on <?php echo date('M d, Y', strtotime($order['customer_since'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Shipping Address -->
                <div style="background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                    <h4 style="margin: 0 0 10px 0; color: var(--wp-blue); font-size: 14px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-location-dot"></i> Shipping Address
                    </h4>
                    <div style="font-size: 13px; line-height: 1.5; color: #1d2327;">
                        <?php if (!empty($address['address_line_1'])): ?>
                            <strong><?php echo sanitize_html($address['address_line_1']); ?></strong><br>
                            <?php if (!empty($address['address_line_2'])) echo sanitize_html($address['address_line_2']) . '<br>'; ?>
                            <?php echo sanitize_html($address['city']); ?>, <?php echo sanitize_html($address['state']); ?> - <strong><?php echo sanitize_html($address['pincode']); ?></strong>
                        <?php else: ?>
                            <span style="color: #646970; font-style: italic;">Primary address on file</span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Purchased Items Table Card -->
        <div class="postbox" style="margin-bottom: 20px;">
            <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px;">
                <h2><i class="fa-solid fa-basket-shopping" style="color: var(--wp-blue);"></i> Purchased Items (<?php echo count($items); ?>)</h2>
            </div>
            <div class="postbox-body" style="padding: 16px;">
                
                <table class="wp-list-table" style="width: 100%; border-collapse: collapse; margin-top: 0;">
                    <thead>
                        <tr style="background: #f1f5f9;">
                            <th style="width: 60px; padding: 10px;">Item</th>
                            <th style="padding: 10px;">Product Name &amp; SKU</th>
                            <th style="width: 110px; text-align: right; padding: 10px;">Unit Price</th>
                            <th style="width: 70px; text-align: center; padding: 10px;">Qty</th>
                            <th style="width: 120px; text-align: right; padding: 10px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px;">
                                    <?php if (!empty($item['main_image'])): ?>
                                        <img src="<?php echo sanitize_html(strpos($item['main_image'], 'http') === 0 ? $item['main_image'] : $item['main_image']); ?>" alt="item" style="width: 40px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0;">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 50px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;"><i class="fa-solid fa-image"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px;">
                                    <strong style="color: #1d2327; font-size: 14px;"><?php echo sanitize_html($item['name']); ?></strong>
                                    <div style="font-size: 11px; color: #646970; margin-top: 2px;">SKU: <code><?php echo sanitize_html($item['sku']); ?></code></div>
                                </td>
                                <td style="text-align: right; padding: 10px; font-size: 13px;">₹<?php echo number_format($item['price'], 2); ?></td>
                                <td style="text-align: center; padding: 10px; font-weight: 600;"><?php echo $item['quantity']; ?></td>
                                <td style="text-align: right; padding: 10px; font-weight: 700; color: var(--wp-blue);">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Total Amount Summary Box -->
                <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                    <div style="width: 320px; background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: #646970; margin-bottom: 6px;">
                            <span>Payment Method</span>
                            <span style="color: #1d2327; font-weight: 600;"><?php echo htmlspecialchars($order['payment_method'] ?: 'Online Payment'); ?></span>
                        </div>

                        <?php if (!empty($order['transaction_id'])): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #646970; margin-bottom: 6px;">
                                <span>Transaction ID</span>
                                <span style="color: #0f172a; font-weight: 600; font-family: monospace;"><?php echo htmlspecialchars($order['transaction_id']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($order['courier_name'])): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #646970; margin-bottom: 6px;">
                                <span>Courier Partner</span>
                                <span style="color: #1d2327; font-weight: 600;"><?php echo htmlspecialchars($order['courier_name']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($order['tracking_number'])): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #646970; margin-bottom: 6px;">
                                <span>POD / Tracking No.</span>
                                <span style="color: var(--wp-blue); font-weight: 700; font-family: monospace;"><?php echo htmlspecialchars($order['tracking_number']); ?></span>
                            </div>
                        <?php endif; ?>

                        <div style="height: 1px; background: #e2e8f0; margin: 8px 0;"></div>

                        <?php 
                        $computedSubtotal = $order['subtotal_amount'] > 0 ? (float)$order['subtotal_amount'] : array_sum(array_map(function($i){ return (float)$i['price'] * (int)$i['quantity']; }, $items));
                        ?>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: #646970; margin-bottom: 6px;">
                            <span>Items Subtotal</span>
                            <span style="color: #1d2327; font-weight: 600;">₹<?php echo number_format($computedSubtotal, 2); ?></span>
                        </div>

                        <?php if ($order['discount_amount'] > 0 || !empty($order['coupon_code'])): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #0d9488; margin-bottom: 6px; font-weight: 600;">
                                <span>Coupon Discount <?php echo !empty($order['coupon_code']) ? '(' . htmlspecialchars($order['coupon_code']) . ')' : ''; ?></span>
                                <span>-₹<?php echo number_format($order['discount_amount'], 2); ?></span>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: #646970; margin-bottom: 8px;">
                            <span>Shipping Fee</span>
                            <span>
                                <?php if ($order['shipping_charge'] > 0): ?>
                                    <span style="color: #1d2327; font-weight: 600;">₹<?php echo number_format($order['shipping_charge'], 2); ?></span>
                                <?php else: ?>
                                    <span style="color: var(--wp-success-green); font-weight: 700;">FREE</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 700; color: var(--wp-blue); border-top: 2px solid #e2e8f0; padding-top: 8px; margin-top: 4px;">
                            <span>Grand Total</span>
                            <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Right Side Column: Actions Panel -->
    <div class="side-column" style="flex: 0 0 340px;">
        
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-sliders" style="color: var(--wp-blue);"></i> Order Actions &amp; Options</h2>
            </div>
            <div class="postbox-body" style="padding: 16px;">
                
                <!-- OPTION 1: Resend Order Confirmation Email (First Option) -->
                <div style="margin-bottom: 20px; background: #f0f6fc; border: 1px solid #c5d9ed; padding: 14px; border-radius: 6px;">
                    <label style="display: block; font-weight: 700; color: #0969da; font-size: 13px; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-paper-plane"></i> Email Confirmation
                    </label>
                    <p style="color: #57606a; font-size: 11px; margin: 0 0 12px 0; line-height: 1.4;">
                        Resend order confirmation receipt with itemized summary via SMTP to customer's email (<code><?php echo htmlspecialchars($order['email']); ?></code>).
                    </p>
                    <form method="POST" action="order-detail.php?id=<?php echo $order['id']; ?>">
                        <input type="hidden" name="action" value="resend_confirmation_email">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <button type="submit" class="button button-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 6px 12px; font-weight: 600;">
                            <i class="fa-solid fa-rotate"></i> Send Order Confirmation Again
                        </button>
                    </form>
                </div>

                <!-- OPTION 2: Update Order Status & Shipment Tracking -->
                <div style="margin-bottom: 20px; background: #f8f9fa; border: 1px solid #e2e8f0; padding: 14px; border-radius: 6px;">
                    <label style="display: block; font-weight: 600; color: #1d2327; font-size: 13px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-truck-ramp-box" style="color: var(--wp-blue);"></i> Fulfillment &amp; Shipment Tracking
                    </label>
                    <form method="POST" action="order-detail.php?id=<?php echo $order['id']; ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        
                        <div style="margin-bottom: 10px;">
                            <label style="font-size: 11px; color: #646970; font-weight: 600; display: block; margin-bottom: 3px;">Fulfillment Status</label>
                            <select name="status" class="form-control" style="width: 100%; font-weight: 600; padding: 6px;">
                                <option value="Pending" <?php echo ($order['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="Processing" <?php echo ($order['status'] === 'Processing') ? 'selected' : ''; ?>>Processing</option>
                                <option value="Shipped" <?php echo ($order['status'] === 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                                <option value="Delivered" <?php echo ($order['status'] === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                                <option value="Cancelled" <?php echo ($order['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <div style="margin-bottom: 10px;">
                            <label style="font-size: 11px; color: #646970; font-weight: 600; display: block; margin-bottom: 3px;">Courier / Shipment Partner</label>
                            <select name="courier_name" class="form-control" style="width: 100%; padding: 6px;">
                                <option value="">-- Select Logistics Partner --</option>
                                <?php foreach ($logistics_partners as $log): ?>
                                    <option value="<?php echo htmlspecialchars($log['name']); ?>" <?php echo ($order['courier_name'] === $log['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($log['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!empty($order['courier_name']) && !in_array($order['courier_name'], array_column($logistics_partners, 'name'))): ?>
                                    <option value="<?php echo htmlspecialchars($order['courier_name']); ?>" selected><?php echo htmlspecialchars($order['courier_name']); ?></option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 11px; color: #646970; font-weight: 600; display: block; margin-bottom: 3px;">POD / Tracking No. (AWB)</label>
                            <input type="text" name="tracking_number" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>" placeholder="e.g. BD123456789IN" class="form-control" style="width: 100%; padding: 6px;">
                        </div>

                        <button type="submit" class="button button-secondary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px;">
                            <i class="fa-solid fa-check"></i> Save &amp; Notify Customer
                        </button>
                    </form>
                </div>

                <!-- OPTION 3: Additional Actions -->
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <a href="invoice-pdf.php?id=<?php echo $order['id']; ?>&action=pdf&download=1" class="button button-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; box-sizing: border-box;">
                        <i class="fa-solid fa-file-pdf"></i> Download PDF Invoice
                    </a>

                    <a href="invoice-pdf.php?id=<?php echo $order['id']; ?>&action=print&auto=1" target="_blank" class="button" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; box-sizing: border-box;">
                        <i class="fa-solid fa-print"></i> Print Formal Invoice
                    </a>

                    <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>?subject=Regarding%20Order%20<?php echo $orderNumber; ?>" class="button" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; box-sizing: border-box;">
                        <i class="fa-solid fa-envelope-open-text"></i> Email Customer Directly
                    </a>
                </div>

            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
