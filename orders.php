<?php
// admin/orders.php
$page_title = "Orders Management";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$message_type = 'success';

// Handle Status Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $allowed_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

    if ($order_id > 0 && in_array($status, $allowed_statuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $order_id]);
            send_order_email($pdo, $order_id, $status);
            log_activity($pdo, 'update_order_status', 'order', $order_id, "Updated order #$order_id status to $status");
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Status updated']);
                exit();
            }
            $message = "Order #$order_id status updated to $status.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Filter logic
$search = isset($_GET['s']) ? trim($_GET['s']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$query_parts = [];
$params = [];

if (!empty($search)) {
    $query_parts[] = "(o.id = ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
    $clean_search = trim($search);
    $search_id = 0;
    if (preg_match('/^YNFS_(\d+)$/i', $clean_search, $matches)) {
        $search_id = (int)$matches[1] - 1000;
    } elseif (is_numeric($clean_search)) {
        $val = (int)$clean_search;
        $search_id = ($val > 1000) ? ($val - 1000) : $val;
    }
    $search_term = "%$search%";
    $params[] = $search_id;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $query_parts[] = "o.status = ?";
    $params[] = $status_filter;
}

$where_clause = "";
if (!empty($query_parts)) {
    $where_clause = "WHERE " . implode(" AND ", $query_parts);
}

try {
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM orders o LEFT JOIN customers c ON o.customer_id = c.id $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetchColumn();

    // Pagination
    $items_per_page = 15;
    $current_page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $total_pages = max(1, ceil($total_items / $items_per_page));
    $offset = ($current_page - 1) * $items_per_page;

    // Fetch Orders
    $sql = "
        SELECT o.*, c.first_name, c.last_name, c.email, c.phone
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        $where_clause 
        ORDER BY o.id DESC 
        LIMIT $items_per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Fetch Items & Address for each order
    foreach ($orders as &$ord) {
        $item_stmt = $pdo->prepare("
            SELECT oi.*, p.name, p.main_image, p.sku 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $item_stmt->execute([$ord['id']]);
        $ord['items'] = $item_stmt->fetchAll();

        $addr_stmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC LIMIT 1");
        $addr_stmt->execute([$ord['customer_id']]);
        $ord['shipping_address'] = $addr_stmt->fetch() ?: [];
    }

} catch (PDOException $e) {
    $message = "Database Error: " . $e->getMessage();
    $message_type = "error";
}
?>

<style>
.order-modal-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(3px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 100000;
    padding: 20px;
}
.order-modal-card {
    background: #ffffff;
    border-radius: 12px;
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    border: 1px solid var(--wp-border);
    position: relative;
    color: #1d2327;
}
.order-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--wp-border);
    background: #f8f9fa;
}
.order-modal-body {
    padding: 24px;
}
.order-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--wp-border);
    background: #f8f9fa;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}
.order-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
.order-detail-box {
    background: #f8f9fa;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.order-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.order-items-table th, .order-items-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}
</style>

<div class="wrap-header">
    <h1><i class="fa-solid fa-box-open" style="color: var(--wp-blue);"></i> Customer Orders</h1>
    <div>
        <a href="index.php" class="button"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<!-- Filter & Search Bar -->
<div class="tablenav">
    <form action="orders.php" method="GET" class="alignleft" style="display: flex; gap: 10px; align-items: center;">
        <input type="text" name="s" value="<?php echo sanitize_html($search); ?>" placeholder="Search Order ID, Customer Name..." class="form-control" style="width: 240px; padding: 6px 10px;">
        
        <select name="status" class="form-control" style="width: 160px; padding: 6px 10px;">
            <option value="">All Statuses</option>
            <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
            <option value="Processing" <?php echo ($status_filter === 'Processing') ? 'selected' : ''; ?>>Processing</option>
            <option value="Shipped" <?php echo ($status_filter === 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
            <option value="Delivered" <?php echo ($status_filter === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
            <option value="Cancelled" <?php echo ($status_filter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
        </select>

        <button type="submit" class="button button-primary"><i class="fa-solid fa-filter"></i> Filter</button>
        <?php if (!empty($search) || !empty($status_filter)): ?>
            <a href="orders.php" class="button"><i class="fa-solid fa-xmark"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Orders Table -->
<table class="wp-list-table" style="margin-top: 15px;">
    <thead>
        <tr>
            <th style="width: 90px;">Order ID</th>
            <th>Customer Info</th>
            <th>Purchased Items</th>
            <th style="width: 120px;">Total</th>
            <th style="width: 140px;">Status</th>
            <th style="width: 140px;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($orders)): ?>
            <tr>
                <td colspan="6" style="text-align: center; color: #646970; padding: 30px;">
                    <i class="fa-solid fa-box-open" style="font-size: 24px; margin-bottom: 8px; color: #ccc; display: block;"></i>
                    No orders found matching criteria.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($orders as $ord): ?>
                <tr>
                    <td>
                        <a href="order-detail.php?id=<?php echo $ord['id']; ?>" style="font-size: 15px; font-weight: 700; color: var(--wp-blue);">
                            <?php echo format_order_number($ord['id']); ?>
                        </a>
                    </td>
                    <td>
                        <div style="font-weight: 600; font-size: 14px; color: #1d2327;">
                            <?php echo sanitize_html(trim(($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? '')) ?: 'Guest Customer'); ?>
                        </div>
                        <div style="font-size: 12px; color: #646970; margin-top: 2px;">
                            <i class="fa-solid fa-envelope" style="font-size: 11px;"></i> <?php echo sanitize_html($ord['email'] ?: 'No email'); ?>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($ord['items'])): ?>
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                <?php foreach ($ord['items'] as $item): ?>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php if (!empty($item['main_image'])): ?>
                                            <img src="<?php echo sanitize_html($item['main_image']); ?>" alt="item" style="width: 36px; height: 46px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                                        <?php else: ?>
                                            <div style="width: 36px; height: 46px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;"><i class="fa-solid fa-image"></i></div>
                                        <?php endif; ?>
                                        <div style="font-size: 13px;">
                                            <strong><?php echo sanitize_html($item['name']); ?></strong>
                                            <span style="font-size: 11px; color: #646970; display: block;">SKU: <?php echo sanitize_html($item['sku']); ?> | Qty: <?php echo $item['quantity']; ?> × ₹<?php echo number_format($item['price'], 2); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #888; font-style: italic; font-size: 12px;">Order details unavailable</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong style="font-size: 15px; color: var(--wp-success-green);">₹<?php echo number_format($ord['total_amount'], 2); ?></strong>
                    </td>
                    <td>
                        <form action="orders.php" method="POST" class="status-form">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                            <select name="status" onchange="this.form.submit()" style="
                                font-size: 12px; 
                                font-weight: 600; 
                                padding: 4px 8px; 
                                border-radius: 6px; 
                                border: 1px solid #ccc;
                                background-color: <?php 
                                    switch($ord['status']) {
                                        case 'Delivered': echo '#e6f4ea; color: #137333;'; break;
                                        case 'Shipped': echo '#e8f0fe; color: #1a73e8;'; break;
                                        case 'Processing': echo '#fef7e0; color: #b06000;'; break;
                                        case 'Cancelled': echo '#fce8e6; color: #c5221f;'; break;
                                        default: echo '#f1f3f4; color: #3c4043;';
                                    }
                                ?>
                            ">
                                <option value="Pending" <?php echo ($ord['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="Processing" <?php echo ($ord['status'] === 'Processing') ? 'selected' : ''; ?>>Processing</option>
                                <option value="Shipped" <?php echo ($ord['status'] === 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                                <option value="Delivered" <?php echo ($ord['status'] === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                                <option value="Cancelled" <?php echo ($ord['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </form>
                    </td>
                    <td style="white-space: nowrap; vertical-align: middle;">
                        <a href="order-detail.php?id=<?php echo $ord['id']; ?>" class="button button-primary" style="font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; padding: 5px 12px;">
                            <i class="fa-solid fa-eye"></i> View Details
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="pagination" style="margin-top: 20px; display: flex; gap: 5px;">
        <?php
        $query_params = $_GET;
        unset($query_params['p']);
        $q = http_build_query($query_params);
        $base_url = 'orders.php?' . ($q ? $q . '&' : '') . 'p=';
        for ($i = 1; $i <= $total_pages; $i++): 
        ?>
            <a href="<?php echo $base_url . $i; ?>" class="button <?php echo ($i === $current_page) ? 'button-primary' : 'button-secondary'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
