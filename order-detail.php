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
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                            <div style="width: 28px; height: 28px; border-radius: 6px; background: #ffffff; border: 1px solid #e4e4e7; display: flex; align-items: center; justify-content: center; color: #09090b; font-size: 12px;">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; color: #09090b;">Delivery Destination</span>
                        </div>
                        <div style="font-size: 13px; line-height: 1.6; color: #09090b;">
                            <?php if (!empty($address['address_line_1'])): ?>
                                <strong><?php echo sanitize_html($address['address_line_1']); ?></strong><br>
                                <?php if (!empty($address['address_line_2'])) echo sanitize_html($address['address_line_2']) . '<br>'; ?>
                                <?php echo sanitize_html($address['city']); ?>, <?php echo sanitize_html($address['state']); ?> - <strong><?php echo sanitize_html($address['pincode']); ?></strong>
                            <?php else: ?>
                                <span style="color: #71717a; font-style: italic;">Primary address recorded on checkout</span>
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
                                            <img src="<?php echo sanitize_html($item['main_image']); ?>" alt="item" style="width: 44px; height: 56px; object-fit: cover; border-radius: 6px; border: 1px solid #e4e4e7;">
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
                        <label>Logistics Partner</label>
                        <select name="courier_name" class="form-control" style="width: 100%;">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
