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

// Calculate status counts for quick tabs
$status_counts = ['All' => 0, 'Pending' => 0, 'Processing' => 0, 'Shipped' => 0, 'Delivered' => 0, 'Cancelled' => 0];
try {
    $sc_stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status");
    while ($row = $sc_stmt->fetch()) {
        if (isset($status_counts[$row['status']])) {
            $status_counts[$row['status']] = (int)$row['cnt'];
        }
        $status_counts['All'] += (int)$row['cnt'];
    }
} catch (Exception $e) {}

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

<!-- Header Banner -->
<div class="dashboard-header-banner" style="margin-bottom: 20px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting">
            <h1>Customer Orders</h1>
            <span class="shadcn-badge shadcn-badge-sky" style="font-size: 11px; padding: 3px 8px;">
                <i class="fa-solid fa-bag-shopping" style="margin-right: 4px;"></i> <?php echo number_format($total_items); ?> Orders
            </span>
        </div>
        <p class="dashboard-subtitle">
            Track customer purchases, fulfillment states, shipments, and customer invoices.
        </p>
    </div>
    <div class="dashboard-actions">
        <a href="index.php" class="shadcn-btn shadcn-btn-outline">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <a href="orders.php" class="shadcn-btn shadcn-btn-outline" title="Refresh list">
            <i class="fa-solid fa-rotate"></i> Refresh
        </a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<!-- Quick Status Filter Tabs -->
<div class="shadcn-filter-tabs">
    <?php 
    $tab_statuses = [
        '' => ['label' => 'All Orders', 'count' => $status_counts['All'], 'icon' => 'fa-boxes-stacked'],
        'Pending' => ['label' => 'Pending', 'count' => $status_counts['Pending'], 'icon' => 'fa-clock'],
        'Processing' => ['label' => 'Processing', 'count' => $status_counts['Processing'], 'icon' => 'fa-gears'],
        'Shipped' => ['label' => 'Shipped', 'count' => $status_counts['Shipped'], 'icon' => 'fa-truck-fast'],
        'Delivered' => ['label' => 'Delivered', 'count' => $status_counts['Delivered'], 'icon' => 'fa-circle-check'],
        'Cancelled' => ['label' => 'Cancelled', 'count' => $status_counts['Cancelled'], 'icon' => 'fa-circle-xmark'],
    ];
    foreach ($tab_statuses as $st_key => $st_data): 
        $isActive = ($status_filter === $st_key);
        $tab_url = 'orders.php' . ($st_key !== '' ? '?status=' . urlencode($st_key) : '');
        if (!empty($search)) {
            $tab_url .= ($st_key !== '' ? '&' : '?') . 's=' . urlencode($search);
        }
    ?>
        <a href="<?php echo $tab_url; ?>" class="shadcn-tab-item <?php echo $isActive ? 'active' : ''; ?>">
            <i class="fa-solid <?php echo $st_data['icon']; ?>" style="font-size: 11px;"></i>
            <?php echo $st_data['label']; ?>
            <span class="shadcn-tab-count"><?php echo $st_data['count']; ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Filter Toolbar Card -->
<div class="shadcn-card" style="margin-bottom: 20px;">
    <div class="shadcn-card-padded" style="padding: 14px 18px;">
        <form action="orders.php" method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin: 0;">
            <div style="position: relative; flex: 1; min-width: 260px;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #a1a1aa; font-size: 13px;"></i>
                <input type="text" name="s" value="<?php echo sanitize_html($search); ?>" placeholder="Search Order ID (e.g. YNFS_1001), Customer Name, Email..." class="form-control" style="padding-left: 34px; width: 100%;">
            </div>
            
            <div style="position: relative; width: 180px;">
                <select name="status" class="form-control" style="width: 100%;">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="Processing" <?php echo ($status_filter === 'Processing') ? 'selected' : ''; ?>>Processing</option>
                    <option value="Shipped" <?php echo ($status_filter === 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                    <option value="Delivered" <?php echo ($status_filter === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                    <option value="Cancelled" <?php echo ($status_filter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <button type="submit" class="shadcn-btn shadcn-btn-primary">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <?php if (!empty($search) || !empty($status_filter)): ?>
                <a href="orders.php" class="shadcn-btn shadcn-btn-ghost">
                    <i class="fa-solid fa-xmark"></i> Clear Filters
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Orders Data Table Card -->
<div class="shadcn-card">
    <div class="shadcn-card-header">
        <h2 class="shadcn-card-title">
            <i class="fa-solid fa-receipt" style="color: #71717a; font-size: 14px;"></i>
            Order Records
            <span style="font-size: 12px; color: #71717a; font-weight: normal; margin-left: 4px;">
                (Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>)
            </span>
        </h2>
    </div>
    
    <div class="shadcn-card-body" style="overflow-x: auto;">
        <table class="shadcn-table">
            <thead>
                <tr>
                    <th style="width: 130px;"><i class="fa-solid fa-hashtag" style="margin-right: 4px;"></i> Order ID</th>
                    <th style="min-width: 200px;"><i class="fa-solid fa-user" style="margin-right: 4px;"></i> Customer</th>
                    <th style="min-width: 280px;"><i class="fa-solid fa-bag-shopping" style="margin-right: 4px;"></i> Purchased Items</th>
                    <th style="width: 130px; text-align: right;"><i class="fa-solid fa-indian-rupee-sign" style="margin-right: 4px;"></i> Total</th>
                    <th style="width: 170px;"><i class="fa-solid fa-truck-ramp-box" style="margin-right: 4px;"></i> Status</th>
                    <th style="width: 140px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #71717a; padding: 48px 20px;">
                            <div style="width: 48px; height: 48px; border-radius: 12px; background: #f4f4f5; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; color: #a1a1aa; font-size: 22px;">
                                <i class="fa-solid fa-inbox"></i>
                            </div>
                            <div style="font-weight: 600; font-size: 14px; color: #09090b; margin-bottom: 4px;">No orders found</div>
                            <div style="font-size: 12.5px; color: #71717a;">Try clearing the search or status filter.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $ord): 
                        $orderNum = format_order_number($ord['id']);
                        $orderDate = !empty($ord['created_at']) ? date('M j, Y • g:i A', strtotime($ord['created_at'])) : '—';
                        $statusClass = 'status-pill-default';
                        $statusIcon = 'fa-clock';
                        switch($ord['status']) {
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
                        <tr>
                            <td>
                                <a href="order-detail.php?id=<?php echo $ord['id']; ?>" style="font-size: 13px; font-weight: 600; color: #09090b; text-decoration: none; display: inline-block; font-family: monospace;">
                                    <?php echo $orderNum; ?>
                                </a>
                                <div style="font-size: 11px; color: #71717a; margin-top: 2px;">
                                    <?php echo $orderDate; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500; font-size: 13px; color: #09090b;">
                                    <?php echo sanitize_html(trim(($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? '')) ?: 'Guest Customer'); ?>
                                </div>
                                <div style="font-size: 11.5px; color: #71717a; margin-top: 2px; display: flex; align-items: center; gap: 5px;">
                                    <i class="fa-regular fa-envelope" style="font-size: 10.5px; color: #a1a1aa;"></i> 
                                    <span><?php echo sanitize_html($ord['email'] ?: 'No email'); ?></span>
                                </div>
                                <?php if (!empty($ord['phone'])): ?>
                                    <div style="font-size: 11px; color: #71717a; margin-top: 1px; display: flex; align-items: center; gap: 5px;">
                                        <i class="fa-solid fa-phone" style="font-size: 9.5px; color: #a1a1aa;"></i>
                                        <span><?php echo sanitize_html($ord['phone']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($ord['items'])): ?>
                                    <div style="display: flex; flex-direction: column; gap: 6px;">
                                        <?php 
                                        $itemCount = count($ord['items']);
                                        $displayedItems = array_slice($ord['items'], 0, 2);
                                        foreach ($displayedItems as $item): 
                                        ?>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="width: 28px; height: 34px; border-radius: 4px; overflow: hidden; position: relative; background: #f4f4f5; border: 1px solid #e4e4e7; flex-shrink: 0;">
                                                    <?php if (!empty($item['main_image'])): ?>
                                                        <img src="<?php echo sanitize_html($item['main_image']); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; display: block;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                        <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: #a1a1aa;">
                                                            <i class="fa-solid fa-gem" style="font-size: 10px;"></i>
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #a1a1aa;">
                                                            <i class="fa-solid fa-gem" style="font-size: 10px;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 12px; min-width: 0;">
                                                    <div style="font-weight: 500; color: #09090b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;" title="<?php echo sanitize_html($item['name']); ?>">
                                                        <?php echo sanitize_html($item['name']); ?>
                                                    </div>
                                                    <span style="font-size: 11px; color: #71717a;">
                                                        Qty: <strong><?php echo $item['quantity']; ?></strong> × ₹<?php echo number_format($item['price'], 2); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($itemCount > 2): ?>
                                            <div style="font-size: 11px; color: #71717a; padding-left: 36px;">
                                                + <?php echo ($itemCount - 2); ?> more item<?php echo ($itemCount - 2) > 1 ? 's' : ''; ?>...
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #a1a1aa; font-style: italic; font-size: 12px;">Order details unavailable</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="font-size: 13.5px; font-weight: 600; color: #09090b;">
                                    ₹<?php echo number_format($ord['total_amount'], 2); ?>
                                </div>
                                <span style="font-size: 10.5px; color: #71717a; text-transform: uppercase; letter-spacing: 0.03em;">
                                    <?php echo sanitize_html($ord['payment_method'] ?: 'Online'); ?>
                                </span>
                            </td>
                            <td>
                                <form action="orders.php" method="POST" class="status-form" style="margin: 0;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                                    <div style="display: inline-flex; align-items: center; gap: 6px;">
                                        <select name="status" onchange="this.form.submit()" class="<?php echo $statusClass; ?>" style="
                                            cursor: pointer;
                                            font-size: 11.5px;
                                            font-weight: 500;
                                            padding: 3px 8px;
                                            border-radius: 5px;
                                            outline: none;
                                            height: 26px;
                                        ">
                                            <option value="Pending" <?php echo ($ord['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Processing" <?php echo ($ord['status'] === 'Processing') ? 'selected' : ''; ?>>Processing</option>
                                            <option value="Shipped" <?php echo ($ord['status'] === 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="Delivered" <?php echo ($ord['status'] === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="Cancelled" <?php echo ($ord['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                </form>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 4px;">
                                    <a href="order-detail.php?id=<?php echo $ord['id']; ?>" class="shadcn-btn shadcn-btn-outline" style="height: 30px; padding: 0 9px; font-size: 12px;" title="View Complete Order Details">
                                        <i class="fa-solid fa-eye"></i> Details
                                    </a>
                                    <a href="invoice-pdf.php?id=<?php echo $ord['id']; ?>&action=pdf&download=1" class="shadcn-btn shadcn-btn-ghost" style="height: 30px; width: 30px; padding: 0;" title="Download PDF Invoice">
                                        <i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <?php if ($total_pages > 1): ?>
        <div style="padding: 14px 20px; border-top: 1px solid #f4f4f5; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="font-size: 12.5px; color: #71717a;">
                Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $items_per_page, $total_items)); ?> of <?php echo number_format($total_items); ?> orders
            </div>
            <div style="display: flex; gap: 4px;">
                <?php
                $query_params = $_GET;
                unset($query_params['p']);
                $q = http_build_query($query_params);
                $base_url = 'orders.php?' . ($q ? $q . '&' : '') . 'p=';
                
                if ($current_page > 1): ?>
                    <a href="<?php echo $base_url . ($current_page - 1); ?>" class="shadcn-btn shadcn-btn-outline" style="height: 30px; padding: 0 10px; font-size: 12px;">
                        <i class="fa-solid fa-chevron-left"></i> Prev
                    </a>
                <?php endif; ?>

                <?php 
                $start_p = max(1, $current_page - 2);
                $end_p = min($total_pages, $current_page + 2);
                for ($i = $start_p; $i <= $end_p; $i++): 
                    $isCurr = ($i === $current_page);
                ?>
                    <a href="<?php echo $base_url . $i; ?>" class="shadcn-btn <?php echo $isCurr ? 'shadcn-btn-primary' : 'shadcn-btn-outline'; ?>" style="height: 30px; width: 30px; padding: 0; font-size: 12px;">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo $base_url . ($current_page + 1); ?>" class="shadcn-btn shadcn-btn-outline" style="height: 30px; padding: 0 10px; font-size: 12px;">
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
