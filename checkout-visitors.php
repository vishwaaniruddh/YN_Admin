<?php
// admin/checkout-visitors.php
// Real-time monitor of checkout visits, funnel stages, and abandoned carts
$page_title = "Checkout Visitors & Abandoned Carts";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=checkout_visitors_' . date('Y-m-d_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'Customer Name', 'Email', 'Phone', 'Items Count', 'Cart Total (INR)', 'Coupon', 'Shipping Address', 'Status', 'Order ID', 'Device', 'IP Address']);
    
    $exportStmt = $pdo->query("SELECT * FROM checkout_visitors ORDER BY id DESC");
    while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['customer_name'] ?: 'Guest Shopper',
            $row['customer_email'],
            $row['customer_phone'],
            $row['cart_items_count'],
            $row['cart_total'],
            $row['coupon_code'],
            $row['shipping_address'],
            $row['status'],
            $row['order_id'] ? '#' . format_order_number($row['order_id']) : '',
            $row['device_type'],
            $row['ip_address']
        ]);
    }
    fclose($output);
    exit;
}

// Handle Single Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_visitor') {
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId > 0) {
        $pdo->prepare("DELETE FROM checkout_visitors WHERE id = ?")->execute([$delId]);
        $_SESSION['flash_success'] = "Checkout visitor record #{$delId} removed.";
        header('Location: checkout-visitors.php');
        exit;
    }
}

// Filter inputs
$range = $_GET['range'] ?? '7days';
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['s'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Build Date Range SQL
$date_where = "1=1";
if ($range === 'today') {
    $date_where = "created_at >= CURDATE()";
} elseif ($range === 'yesterday') {
    $date_where = "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND created_at < CURDATE()";
} elseif ($range === '7days') {
    $date_where = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($range === '30days') {
    $date_where = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// Build Status SQL
$status_where = "1=1";
if ($status_filter === 'completed') {
    $status_where = "status = 'completed'";
} elseif ($status_filter === 'payment_initiated') {
    $status_where = "status = 'payment_initiated'";
} elseif ($status_filter === 'abandoned') {
    $status_where = "status IN ('visited', 'payment_initiated') AND (order_id IS NULL OR order_id = 0) AND updated_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
} elseif ($status_filter === 'visited') {
    $status_where = "status = 'visited'";
}

// Build Search SQL
$search_where = "1=1";
$params = [];
if (!empty($search)) {
    $search_where = "(customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ? OR session_token LIKE ? OR order_id LIKE ?)";
    $s_term = "%{$search}%";
    $params = [$s_term, $s_term, $s_term, $s_term, $s_term];
}

// 1. KPI Statistics
$stats = [
    'total_visits' => 0,
    'completed' => 0,
    'payment_initiated' => 0,
    'abandoned' => 0,
    'abandoned_revenue' => 0.00,
    'conversion_rate' => 0.0
];

try {
    $kpiStmt = $pdo->query("SELECT 
        COUNT(*) as total_visits,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'payment_initiated' AND (order_id IS NULL OR order_id = 0) THEN 1 ELSE 0 END) as payment_initiated,
        SUM(CASE WHEN status IN ('visited', 'payment_initiated') AND (order_id IS NULL OR order_id = 0) AND updated_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE) THEN 1 ELSE 0 END) as abandoned,
        SUM(CASE WHEN status IN ('visited', 'payment_initiated') AND (order_id IS NULL OR order_id = 0) AND updated_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE) THEN cart_total ELSE 0 END) as abandoned_revenue
        FROM checkout_visitors 
        WHERE $date_where");
    $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);
    if ($kpi) {
        $stats['total_visits'] = (int)($kpi['total_visits'] ?? 0);
        $stats['completed'] = (int)($kpi['completed'] ?? 0);
        $stats['payment_initiated'] = (int)($kpi['payment_initiated'] ?? 0);
        $stats['abandoned'] = (int)($kpi['abandoned'] ?? 0);
        $stats['abandoned_revenue'] = (float)($kpi['abandoned_revenue'] ?? 0.00);
        if ($stats['total_visits'] > 0) {
            $stats['conversion_rate'] = round(($stats['completed'] / $stats['total_visits']) * 100, 1);
        }
    }
} catch (Exception $e) {}

// 2. Total Records for Pagination
$countSql = "SELECT COUNT(*) FROM checkout_visitors WHERE $date_where AND $status_where AND $search_where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_rows = (int)$countStmt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $limit));

// 3. Fetch Records
$listSql = "SELECT * FROM checkout_visitors 
    WHERE $date_where AND $status_where AND $search_where 
    ORDER BY id DESC 
    LIMIT $limit OFFSET $offset";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$visitors = $listStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap" style="max-width: 1400px; margin: 0 auto; padding: 24px 20px;">
    
    <!-- Top Breadcrumb & Action Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #71717a; margin-bottom: 4px;">
                <span>Sales &amp; Customers</span>
                <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
                <span style="color: #09090b; font-weight: 500;">Checkout Visitors</span>
            </div>
            <h1 style="font-size: 26px; font-weight: 700; color: #09090b; margin: 0; letter-spacing: -0.02em; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-cart-shopping" style="color: #B08D57;"></i>
                Checkout Traffic &amp; Funnel Monitor
            </h1>
            <p style="color: #71717a; font-size: 14px; margin: 4px 0 0 0;">
                Track every visitor reaching checkout, capture abandoned carts, and analyze conversion drop-offs.
            </p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="checkout-visitors.php?action=export_csv" class="button" style="background: #ffffff; border: 1px solid #e4e4e7; color: #09090b; border-radius: 8px; font-weight: 500; font-size: 13px; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                <i class="fa-solid fa-file-csv" style="color: #10b981;"></i> Export CSV
            </a>
            <a href="checkout-visitors.php" class="button" style="background: #09090b; color: #ffffff; border-radius: 8px; font-weight: 500; font-size: 13px; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                <i class="fa-solid fa-arrow-rotate-right"></i> Refresh
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-circle-check"></i>
            <span><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
        
        <!-- Total Visits -->
        <div style="background: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #71717a;">Total Checkout Visits</span>
                <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                    <i class="fa-solid fa-users"></i>
                </span>
            </div>
            <div style="font-size: 28px; font-weight: 700; color: #09090b; line-height: 1.1;">
                <?php echo number_format($stats['total_visits']); ?>
            </div>
            <div style="font-size: 12px; color: #71717a; margin-top: 6px;">
                Shoppers who entered checkout
            </div>
        </div>

        <!-- Completed Orders -->
        <div style="background: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #71717a;">Completed Purchases</span>
                <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                    <i class="fa-solid fa-circle-check"></i>
                </span>
            </div>
            <div style="display: flex; align-items: baseline; gap: 8px;">
                <span style="font-size: 28px; font-weight: 700; color: #10b981; line-height: 1.1;">
                    <?php echo number_format($stats['completed']); ?>
                </span>
                <span style="font-size: 13px; font-weight: 600; color: #059669; background: #ecfdf5; padding: 2px 8px; border-radius: 20px; border: 1px solid #a7f3d0;">
                    <?php echo $stats['conversion_rate']; ?>% Conv.
                </span>
            </div>
            <div style="font-size: 12px; color: #71717a; margin-top: 6px;">
                Paid and converted orders
            </div>
        </div>

        <!-- Payment Attempted -->
        <div style="background: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #71717a;">Payment Gateway Opened</span>
                <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                    <i class="fa-solid fa-credit-card"></i>
                </span>
            </div>
            <div style="font-size: 28px; font-weight: 700; color: #f59e0b; line-height: 1.1;">
                <?php echo number_format($stats['payment_initiated']); ?>
            </div>
            <div style="font-size: 12px; color: #71717a; margin-top: 6px;">
                Clicked pay but didn't finish
            </div>
        </div>

        <!-- Abandoned Carts & Recoverable Value -->
        <div style="background: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #71717a;">Abandoned Checkouts</span>
                <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </span>
            </div>
            <div style="display: flex; align-items: baseline; gap: 8px;">
                <span style="font-size: 28px; font-weight: 700; color: #ef4444; line-height: 1.1;">
                    <?php echo number_format($stats['abandoned']); ?>
                </span>
                <span style="font-size: 12px; font-weight: 600; color: #b91c1c;">
                    (&#8377;<?php echo number_format($stats['abandoned_revenue']); ?> Lost)
                </span>
            </div>
            <div style="font-size: 12px; color: #71717a; margin-top: 6px;">
                Potential recoverable revenue
            </div>
        </div>

    </div>

    <!-- Filters & Search Toolbar -->
    <div style="background: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <form method="GET" action="checkout-visitors.php" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between;">
            
            <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                <!-- Status Filter Pills -->
                <div style="display: inline-flex; background: #f4f4f5; padding: 3px; border-radius: 8px;">
                    <a href="checkout-visitors.php?range=<?php echo $range; ?>&status=all&s=<?php echo urlencode($search); ?>" style="padding: 5px 12px; font-size: 12px; font-weight: 500; border-radius: 6px; text-decoration: none; color: <?php echo $status_filter === 'all' ? '#09090b' : '#71717a'; ?>; background: <?php echo $status_filter === 'all' ? '#ffffff' : 'transparent'; ?>; box-shadow: <?php echo $status_filter === 'all' ? '0 1px 2px rgba(0,0,0,0.06)' : 'none'; ?>;">
                        All
                    </a>
                    <a href="checkout-visitors.php?range=<?php echo $range; ?>&status=abandoned&s=<?php echo urlencode($search); ?>" style="padding: 5px 12px; font-size: 12px; font-weight: 500; border-radius: 6px; text-decoration: none; color: <?php echo $status_filter === 'abandoned' ? '#ef4444' : '#71717a'; ?>; background: <?php echo $status_filter === 'abandoned' ? '#ffffff' : 'transparent'; ?>; box-shadow: <?php echo $status_filter === 'abandoned' ? '0 1px 2px rgba(0,0,0,0.06)' : 'none'; ?>;">
                        Abandoned (<?php echo $stats['abandoned']; ?>)
                    </a>
                    <a href="checkout-visitors.php?range=<?php echo $range; ?>&status=payment_initiated&s=<?php echo urlencode($search); ?>" style="padding: 5px 12px; font-size: 12px; font-weight: 500; border-radius: 6px; text-decoration: none; color: <?php echo $status_filter === 'payment_initiated' ? '#f59e0b' : '#71717a'; ?>; background: <?php echo $status_filter === 'payment_initiated' ? '#ffffff' : 'transparent'; ?>; box-shadow: <?php echo $status_filter === 'payment_initiated' ? '0 1px 2px rgba(0,0,0,0.06)' : 'none'; ?>;">
                        Payment Attempted
                    </a>
                    <a href="checkout-visitors.php?range=<?php echo $range; ?>&status=completed&s=<?php echo urlencode($search); ?>" style="padding: 5px 12px; font-size: 12px; font-weight: 500; border-radius: 6px; text-decoration: none; color: <?php echo $status_filter === 'completed' ? '#10b981' : '#71717a'; ?>; background: <?php echo $status_filter === 'completed' ? '#ffffff' : 'transparent'; ?>; box-shadow: <?php echo $status_filter === 'completed' ? '0 1px 2px rgba(0,0,0,0.06)' : 'none'; ?>;">
                        Completed (<?php echo $stats['completed']; ?>)
                    </a>
                </div>

                <!-- Date Range Selector -->
                <select name="range" onchange="this.form.submit()" style="padding: 6px 12px; border: 1px solid #e4e4e7; border-radius: 8px; font-size: 13px; color: #09090b; background: #ffffff; cursor: pointer;">
                    <option value="today" <?php echo $range === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="yesterday" <?php echo $range === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                    <option value="7days" <?php echo $range === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="30days" <?php echo $range === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="all" <?php echo $range === 'all' ? 'selected' : ''; ?>>All Time</option>
                </select>
            </div>

            <!-- Search Field -->
            <div style="display: flex; gap: 8px; align-items: center; flex: 1; max-width: 380px; min-width: 260px;">
                <div style="position: relative; width: 100%;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #a1a1aa; font-size: 13px;"></i>
                    <input type="text" name="s" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email, phone, order #..." style="width: 100%; padding: 7px 12px 7px 34px; border: 1px solid #e4e4e7; border-radius: 8px; font-size: 13px; color: #09090b; background: #ffffff; outline: none;">
                </div>
                <button type="submit" class="button" style="padding: 7px 14px; background: #09090b; color: #ffffff; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer;">
                    Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="checkout-visitors.php?range=<?php echo $range; ?>&status=<?php echo $status_filter; ?>" style="color: #71717a; font-size: 13px; text-decoration: none; padding: 6px;">
                        Reset
                    </a>
                <?php endif; ?>
            </div>

        </form>
    </div>

    <!-- Main Data Table -->
    <div style="background: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="overflow-x: auto;">
            <table class="wp-list-table widefat fixed striped" style="border: none; margin: 0; width: 100%;">
                <thead>
                    <tr style="background: #fafafa; border-bottom: 1px solid #e4e4e7;">
                        <th style="padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #71717a; width: 220px;">Customer / Contact</th>
                        <th style="padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #71717a; width: 280px;">Cart Contents</th>
                        <th style="padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #71717a; width: 110px;">Cart Value</th>
                        <th style="padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #71717a; width: 170px;">Funnel Stage</th>
                        <th style="padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #71717a; width: 180px;">Destination</th>
                        <th style="padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #71717a; width: 130px;">Time</th>
                        <th style="padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #71717a; text-align: right; width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visitors)): ?>
                        <tr>
                            <td colspan="7" style="padding: 48px 16px; text-align: center; color: #71717a;">
                                <div style="width: 52px; height: 52px; border-radius: 50%; background: #f4f4f5; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; color: #a1a1aa; margin-bottom: 12px;">
                                    <i class="fa-solid fa-cart-arrow-down"></i>
                                </div>
                                <h3 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 600; color: #09090b;">No Checkout Visitors Found</h3>
                                <p style="margin: 0; font-size: 13px; color: #a1a1aa; max-width: 440px; margin: 0 auto;">
                                    <?php echo !empty($search) ? "No results matched your search term '{$search}'." : "Visitors who proceed to the checkout page will appear here automatically with their cart details and recovery options."; ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($visitors as $v): 
                            $cartItems = !empty($v['cart_data']) ? json_decode($v['cart_data'], true) : [];
                            $name = trim($v['customer_name'] ?? '') ?: 'Guest Visitor';
                            $email = $v['customer_email'] ?? '';
                            $phone = $v['customer_phone'] ?? '';
                            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
                            if (strlen($cleanPhone) === 10) {
                                $cleanPhone = '91' . $cleanPhone;
                            }
                            
                            $isCompleted = ($v['status'] === 'completed');
                            $isRecent = (strtotime($v['updated_at']) >= (time() - 1800));
                            $isAbandoned = !$isCompleted && !$isRecent;
                            
                            $statusBadge = '';
                            if ($isCompleted) {
                                $statusBadge = '<span style="display: inline-flex; align-items: center; gap: 5px; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;"><i class="fa-solid fa-circle-check"></i> Order Placed</span>';
                            } elseif ($v['status'] === 'payment_initiated') {
                                $statusBadge = '<span style="display: inline-flex; align-items: center; gap: 5px; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 3px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;"><i class="fa-solid fa-credit-card"></i> Payment Attempted</span>';
                            } elseif ($isAbandoned) {
                                $statusBadge = '<span style="display: inline-flex; align-items: center; gap: 5px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 3px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;"><i class="fa-solid fa-triangle-exclamation"></i> Abandoned</span>';
                            } else {
                                $statusBadge = '<span style="display: inline-flex; align-items: center; gap: 5px; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 3px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;"><i class="fa-solid fa-spinner fa-spin"></i> Active at Checkout</span>';
                            }
                            
                            $deviceIcon = 'fa-desktop';
                            if ($v['device_type'] === 'mobile') $deviceIcon = 'fa-mobile-screen';
                            elseif ($v['device_type'] === 'tablet') $deviceIcon = 'fa-tablet-screen-button';
                            
                            $firstItemName = !empty($cartItems[0]['name']) ? $cartItems[0]['name'] : 'Luxury Outfit';
                            $recoveryMsg = "Hello " . ($v['customer_name'] ?: 'there') . "! We noticed you left " . $firstItemName . " in your checkout at Yosshita & Neha Fashion Studio. Can we assist you with completing your bespoke order?";
                        ?>
                            <tr style="border-bottom: 1px solid #f4f4f5;">
                                
                                <!-- Customer / Contact -->
                                <td style="padding: 14px 16px; vertical-align: middle;">
                                    <div style="font-weight: 600; font-size: 13px; color: #09090b; display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid <?php echo $deviceIcon; ?>" style="color: #a1a1aa; font-size: 12px;" title="<?php echo ucfirst($v['device_type'] ?? 'desktop'); ?>"></i>
                                        <span><?php echo htmlspecialchars($name); ?></span>
                                    </div>
                                    <?php if (!empty($email)): ?>
                                        <div style="font-size: 12px; color: #71717a; margin-top: 2px;">
                                            <a href="mailto:<?php echo htmlspecialchars($email); ?>" style="color: #71717a; text-decoration: none;">
                                                <i class="fa-regular fa-envelope" style="margin-right: 4px;"></i><?php echo htmlspecialchars($email); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($phone)): ?>
                                        <div style="font-size: 12px; color: #059669; margin-top: 2px; font-weight: 500;">
                                            <i class="fa-solid fa-phone" style="margin-right: 4px;"></i><?php echo htmlspecialchars($phone); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Cart Contents Preview -->
                                <td style="padding: 14px 16px; vertical-align: middle;">
                                    <?php if (!empty($cartItems) && is_array($cartItems)): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <?php 
                                            $showCount = min(3, count($cartItems));
                                            for ($i = 0; $i < $showCount; $i++): 
                                                $it = $cartItems[$i];
                                                $imgUrl = !empty($it['image']) ? $it['image'] : 'assets/images/placeholder.png';
                                                if (strpos($imgUrl, 'http') !== 0 && strpos($imgUrl, '/') !== 0) {
                                                    $imgUrl = '../' . ltrim($imgUrl, '/');
                                                }
                                            ?>
                                                <div style="position: relative; width: 40px; height: 48px; border-radius: 6px; overflow: hidden; background: #f4f4f5; border: 1px solid #e4e4e7; flex-shrink: 0;" title="<?php echo htmlspecialchars(($it['name'] ?? '') . ' (Qty: ' . ($it['quantity'] ?? 1) . ')'); ?>">
                                                    <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                                    <span style="position: absolute; bottom: 0; right: 0; background: rgba(0,0,0,0.7); color: #fff; font-size: 9px; font-weight: 700; padding: 1px 3px; border-top-left-radius: 4px;">
                                                        x<?php echo (int)($it['quantity'] ?? 1); ?>
                                                    </span>
                                                </div>
                                            <?php endfor; ?>
                                            
                                            <div style="font-size: 12px; color: #71717a; line-height: 1.3;">
                                                <span style="font-weight: 600; color: #09090b;"><?php echo (int)$v['cart_items_count']; ?> item<?php echo $v['cart_items_count'] == 1 ? '' : 's'; ?></span>
                                                <?php if (!empty($v['coupon_code'])): ?>
                                                    <br><span style="font-size: 11px; background: #fef3c7; color: #b45309; padding: 1px 5px; border-radius: 4px; font-weight: 500;">
                                                        <i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($v['coupon_code']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: #a1a1aa; font-style: italic;">Cart details not cached</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Cart Value -->
                                <td style="padding: 14px 16px; vertical-align: middle;">
                                    <div style="font-weight: 700; font-size: 14px; color: #09090b;">
                                        &#8377;<?php echo number_format((float)$v['cart_total']); ?>
                                    </div>
                                </td>

                                <!-- Funnel Stage / Status -->
                                <td style="padding: 14px 16px; vertical-align: middle;">
                                    <?php echo $statusBadge; ?>
                                    <?php if (!empty($v['order_id'])): ?>
                                        <div style="margin-top: 4px; font-size: 11px;">
                                            <a href="order-detail.php?id=<?php echo (int)$v['order_id']; ?>" style="color: #2563eb; text-decoration: none; font-weight: 600;">
                                                <i class="fa-solid fa-receipt"></i> Order #<?php echo format_order_number($v['order_id']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Destination Address -->
                                <td style="padding: 14px 16px; vertical-align: middle;">
                                    <?php if (!empty($v['shipping_address'])): ?>
                                        <div style="font-size: 12px; color: #09090b; line-height: 1.3; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($v['shipping_address']); ?>">
                                            <i class="fa-solid fa-location-dot" style="color: #ef4444; margin-right: 4px;"></i>
                                            <?php echo htmlspecialchars($v['shipping_address']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #a1a1aa;">Not entered</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Time -->
                                <td style="padding: 14px 16px; vertical-align: middle; font-size: 12px; color: #71717a;">
                                    <div style="color: #09090b; font-weight: 500;">
                                        <?php echo date('d M, h:i A', strtotime($v['created_at'])); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #a1a1aa;">
                                        <?php echo human_time_diff_custom(strtotime($v['created_at'])); ?>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td style="padding: 14px 16px; vertical-align: middle; text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                        
                                        <!-- WhatsApp Recovery Button -->
                                        <?php if (!empty($cleanPhone)): ?>
                                            <a href="https://wa.me/<?php echo $cleanPhone; ?>?text=<?php echo urlencode($recoveryMsg); ?>" target="_blank" class="button button-small" style="background: #25d366; color: #ffffff; border: none; border-radius: 6px; padding: 4px 8px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; text-decoration: none;" title="Send WhatsApp Recovery Message">
                                                <i class="fa-brands fa-whatsapp"></i> Recover
                                            </a>
                                        <?php endif; ?>

                                        <!-- View Items Modal Trigger -->
                                        <button type="button" onclick="showVisitorModal(<?php echo htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8'); ?>)" class="button button-small" style="background: #ffffff; border: 1px solid #e4e4e7; border-radius: 6px; padding: 4px 8px; font-size: 12px; color: #09090b; cursor: pointer;" title="View Details">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>

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
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid #e4e4e7; background: #fafafa; font-size: 13px; color: #71717a;">
                <div>
                    Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($total_rows, $offset + $limit); ?></strong> of <strong><?php echo $total_rows; ?></strong> visitors
                </div>
                <div style="display: flex; gap: 6px;">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="checkout-visitors.php?range=<?php echo $range; ?>&status=<?php echo $status_filter; ?>&s=<?php echo urlencode($search); ?>&p=<?php echo $p; ?>" style="padding: 5px 10px; border-radius: 6px; font-weight: 500; font-size: 12px; text-decoration: none; border: 1px solid <?php echo $p == $page ? '#09090b' : '#e4e4e7'; ?>; background: <?php echo $p == $page ? '#09090b' : '#ffffff'; ?>; color: <?php echo $p == $page ? '#ffffff' : '#09090b'; ?>;">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

</div>

<!-- Visitor Detail Modal -->
<div id="visitorDetailModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10005; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; width: 100%; max-width: 600px; border-radius: 14px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        
        <div style="padding: 18px 22px; border-bottom: 1px solid #e4e4e7; display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #09090b;" id="modalTitle">Checkout Visitor Session</h3>
                <span style="font-size: 12px; color: #71717a;" id="modalSubtitle">Session details</span>
            </div>
            <button type="button" onclick="closeVisitorModal()" style="background: none; border: none; font-size: 18px; color: #71717a; cursor: pointer;">
                &times;
            </button>
        </div>

        <div style="padding: 22px; max-height: 70vh; overflow-y: auto;" id="modalBody">
            <!-- Injected via JavaScript -->
        </div>

        <div style="padding: 14px 22px; border-top: 1px solid #e4e4e7; background: #fafafa; display: flex; justify-content: space-between; align-items: center;">
            <div id="modalRecoveryAction"></div>
            <button type="button" onclick="closeVisitorModal()" class="button" style="padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 500; background: #09090b; color: #ffffff; border: none; cursor: pointer;">
                Close
            </button>
        </div>

    </div>
</div>

<script>
function showVisitorModal(data) {
    const modal = document.getElementById('visitorDetailModal');
    const title = document.getElementById('modalTitle');
    const subtitle = document.getElementById('modalSubtitle');
    const body = document.getElementById('modalBody');
    const recoveryAction = document.getElementById('modalRecoveryAction');

    title.innerText = (data.customer_name || 'Guest Shopper') + ' - Session #' + data.id;
    subtitle.innerText = 'Token: ' + data.session_token + ' • ' + (data.device_type || 'Desktop');

    let itemsHtml = '';
    let cartItems = [];
    try {
        if (data.cart_data) cartItems = JSON.parse(data.cart_data);
    } catch(e) {}

    if (cartItems && cartItems.length > 0) {
        itemsHtml += '<div style="margin-top: 12px; display: flex; flex-direction: column; gap: 8px;">';
        cartItems.forEach(item => {
            let img = item.image || 'assets/images/placeholder.png';
            if (!img.startsWith('http') && !img.startsWith('/')) img = '../' + img;
            itemsHtml += `
                <div style="display: flex; gap: 12px; align-items: center; padding: 10px; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px;">
                    <img src="${img}" style="width: 48px; height: 58px; object-fit: cover; border-radius: 6px; border: 1px solid #e4e4e7;">
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; font-size: 13px; color: #09090b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${item.name || 'Product'}</div>
                        <div style="font-size: 12px; color: #71717a;">SKU: ${item.sku || 'N/A'} • Qty: ${item.quantity || 1}</div>
                        <div style="font-weight: 700; font-size: 13px; color: #B08D57;">₹${Number(item.price || 0).toLocaleString('en-IN')}</div>
                    </div>
                </div>
            `;
        });
        itemsHtml += '</div>';
    } else {
        itemsHtml = '<p style="color: #71717a; font-size: 13px;">No item snapshots recorded for this visit.</p>';
    }

    body.innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; background: #f8fafc; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 13px;">
            <div>
                <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600;">Customer Contact</span>
                <div style="font-weight: 600; color: #0f172a; margin-top: 2px;">${data.customer_name || 'Guest Shopper'}</div>
                <div style="color: #475569;">${data.customer_email || 'No email entered'}</div>
                <div style="color: #059669; font-weight: 600;">${data.customer_phone || 'No phone entered'}</div>
            </div>
            <div>
                <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600;">Checkout Cart</span>
                <div style="font-size: 18px; font-weight: 700; color: #0f172a; margin-top: 2px;">₹${Number(data.cart_total || 0).toLocaleString('en-IN')}</div>
                <div style="color: #64748b; font-size: 12px;">Items: ${data.cart_items_count || 0} ${data.coupon_code ? '• Coupon: ' + data.coupon_code : ''}</div>
                <div style="color: #2563eb; font-weight: 600; font-size: 12px; margin-top: 2px;">Status: ${data.status.toUpperCase()}</div>
            </div>
        </div>

        <div style="margin-bottom: 16px; font-size: 13px;">
            <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600;">Shipping Address</span>
            <div style="margin-top: 4px; padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; color: #334155;">
                ${data.shipping_address ? '<i class="fa-solid fa-location-dot" style="color: #ef4444; margin-right: 6px;"></i>' + data.shipping_address : '<span style="color: #94a3b8; font-style: italic;">No shipping address was filled yet.</span>'}
            </div>
        </div>

        <div>
            <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600;">Cart Products Snapshot</span>
            ${itemsHtml}
        </div>
    `;

    const cleanPhone = (data.customer_phone || '').replace(/[^0-9]/g, '');
    let finalPhone = cleanPhone;
    if (finalPhone.length === 10) finalPhone = '91' + finalPhone;

    if (finalPhone.length >= 10) {
        const msg = encodeURIComponent(`Hello ${data.customer_name || 'there'}! We noticed you visited checkout at Yosshita & Neha Fashion Studio. Can we assist you with completing your bespoke order?`);
        recoveryAction.innerHTML = `
            <a href="https://wa.me/${finalPhone}?text=${msg}" target="_blank" class="button" style="background: #25d366; color: #fff; border: none; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-brands fa-whatsapp"></i> Chat &amp; Recover via WhatsApp
            </a>
        `;
    } else {
        recoveryAction.innerHTML = '';
    }

    modal.style.display = 'flex';
}

function closeVisitorModal() {
    document.getElementById('visitorDetailModal').style.display = 'none';
}

// Close on backdrop click
document.getElementById('visitorDetailModal').addEventListener('click', function(e) {
    if (e.target === this) closeVisitorModal();
});
</script>

<?php
// Custom helper for relative time
function human_time_diff_custom($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return round($diff / 60) . ' mins ago';
    if ($diff < 86400) return round($diff / 3600) . ' hrs ago';
    return round($diff / 86400) . ' days ago';
}

require_once __DIR__ . '/includes/footer.php';
?>
