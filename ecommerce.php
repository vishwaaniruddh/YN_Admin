<?php
// admin/ecommerce.php
$page_title = "Ecommerce Settings";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$tab = strtolower($_GET['tab'] ?? 'shipping');
if (!in_array($tab, ['shipping', 'payment', 'discounts', 'coupons'])) {
    $tab = 'shipping';
}

$message = '';
$message_type = 'success';

if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// -------------------------------------------------------------
// POST HANDLER FOR SHIPPING RULES TAB
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'shipping') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $min = (float)($_POST['min_amount'] ?? 0);
        $max_raw = trim($_POST['max_amount'] ?? '');
        $max = ($max_raw === '' || strtolower($max_raw) === 'null') ? null : (float)$max_raw;
        $charge = (float)($_POST['charge'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        try {
            $stmt = $pdo->prepare("INSERT INTO shipping_rules (min_amount, max_amount, charge, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$min, $max, $charge, $status]);
            log_activity($pdo, 'add_shipping_rule', 'shipping', $pdo->lastInsertId(), "Added shipping rule: Min ₹$min, Charge ₹$charge");
            $_SESSION['flash_message'] = "New shipping rule added successfully.";
            $_SESSION['flash_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "Error adding rule: " . $e->getMessage();
            $_SESSION['flash_type'] = "error";
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $min = (float)($_POST['min_amount'] ?? 0);
        $max_raw = trim($_POST['max_amount'] ?? '');
        $max = ($max_raw === '' || strtolower($max_raw) === 'null') ? null : (float)$max_raw;
        $charge = (float)($_POST['charge'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE shipping_rules SET min_amount = ?, max_amount = ?, charge = ?, status = ? WHERE id = ?");
                $stmt->execute([$min, $max, $charge, $status, $id]);
                log_activity($pdo, 'update_shipping_rule', 'shipping', $id, "Updated shipping rule ID $id");
                $_SESSION['flash_message'] = "Shipping rule updated successfully.";
                $_SESSION['flash_type'] = "success";
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = "Error updating rule: " . $e->getMessage();
                $_SESSION['flash_type'] = "error";
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM shipping_rules WHERE id = ?");
                $stmt->execute([$id]);
                log_activity($pdo, 'delete_shipping_rule', 'shipping', $id, "Deleted shipping rule ID $id");
                $_SESSION['flash_message'] = "Shipping rule deleted successfully.";
                $_SESSION['flash_type'] = "success";
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = "Error deleting rule: " . $e->getMessage();
                $_SESSION['flash_type'] = "error";
            }
        }
    }
    header("Location: ecommerce.php?tab=shipping");
    exit();
}

// -------------------------------------------------------------
// POST HANDLER FOR PAYMENT GATEWAY TAB
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'payment') {
    $new_rzp_mode = isset($_POST['razorpay_mode']) && $_POST['razorpay_mode'] === 'live' ? 'live' : 'test';
    $new_rzp_live_key = trim($_POST['razorpay_live_key_id'] ?? '');
    $new_rzp_live_secret = trim($_POST['razorpay_live_key_secret'] ?? '');
    $new_rzp_test_key = trim($_POST['razorpay_test_key_id'] ?? '');
    $new_rzp_test_secret = trim($_POST['razorpay_test_key_secret'] ?? '');

    $to_save = [
        'razorpay_mode' => $new_rzp_mode,
        'razorpay_live_key_id' => $new_rzp_live_key,
        'razorpay_live_key_secret' => $new_rzp_live_secret,
        'razorpay_test_key_id' => $new_rzp_test_key,
        'razorpay_test_key_secret' => $new_rzp_test_secret,
    ];

    try {
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($to_save as $key => $val) {
            $stmt->execute([$key, $val]);
        }

        // Sync to u464193275_srishrinjewels database if available locally
        try {
            $pdo_ss = new PDO('mysql:host=localhost;dbname=u464193275_srishrinjewels', 'root', '');
            $pdo_ss->exec("CREATE TABLE IF NOT EXISTS site_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            $stmt_ss = $pdo_ss->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($to_save as $key => $val) {
                $stmt_ss->execute([$key, $val]);
            }
        } catch (Exception $ex) {}

        log_activity($pdo, 'update_razorpay_settings', 'settings', 0, "Updated Razorpay Payment Gateway settings (Mode: $new_rzp_mode)");
        $_SESSION['flash_message'] = "Razorpay Payment Gateway settings saved successfully!";
        $_SESSION['flash_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Failed to save payment settings: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
    }
    header("Location: ecommerce.php?tab=payment");
    exit();
}

// -------------------------------------------------------------
// POST HANDLER FOR DISCOUNT RULES TAB
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'discounts') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $scope = $_POST['scope'] ?? 'global';
        $type = $_POST['type'] ?? 'percentage';
        $value = (float)($_POST['value'] ?? 0);
        $weight = (int)($_POST['weight'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        $targets = [];
        if ($scope === 'product' && !empty($_POST['product_targets'])) {
            $targets = is_array($_POST['product_targets']) ? $_POST['product_targets'] : [$_POST['product_targets']];
        } elseif (($scope === 'category' || strpos($scope, 'cat_price') !== false) && !empty($_POST['category_targets'])) {
            $targets = is_array($_POST['category_targets']) ? $_POST['category_targets'] : [$_POST['category_targets']];
        }
        $target_str = implode(',', array_map('intval', $targets));
        if ($scope === 'global') $target_str = 'all';

        $threshold = ($_POST['threshold'] !== '') ? (float)$_POST['threshold'] : null;
        $threshold_max = ($_POST['threshold_max'] !== '') ? (float)$_POST['threshold_max'] : null;

        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO discount_rules (name, scope, target, threshold, threshold_max, type, value, weight, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $scope, $target_str, $threshold, $threshold_max, $type, $value, $weight, $status]);
                $_SESSION['flash_message'] = "Discount rule added successfully.";
                $_SESSION['flash_type'] = "success";
            } else {
                $stmt = $pdo->prepare("UPDATE discount_rules SET name = ?, scope = ?, target = ?, threshold = ?, threshold_max = ?, type = ?, value = ?, weight = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $scope, $target_str, $threshold, $threshold_max, $type, $value, $weight, $status, $id]);
                $_SESSION['flash_message'] = "Discount rule updated successfully.";
                $_SESSION['flash_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "Database Error: " . $e->getMessage();
            $_SESSION['flash_type'] = "error";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM discount_rules WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_message'] = "Discount rule deleted successfully.";
                $_SESSION['flash_type'] = "success";
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = "Error deleting rule: " . $e->getMessage();
                $_SESSION['flash_type'] = "error";
            }
        }
    }
    header("Location: ecommerce.php?tab=discounts");
    exit();
}

// -------------------------------------------------------------
// POST HANDLER FOR COUPONS TAB
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'coupons') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $discount_type = $_POST['discount_type'] ?? 'percent';
        $coupon_amount = (float)($_POST['coupon_amount'] ?? 0);
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $usage_limit = ($_POST['usage_limit'] !== '') ? (int)$_POST['usage_limit'] : null;
        $usage_limit_per_user = ($_POST['usage_limit_per_user'] !== '') ? (int)$_POST['usage_limit_per_user'] : null;
        $minimum_amount = ($_POST['minimum_amount'] !== '') ? (float)$_POST['minimum_amount'] : null;
        $maximum_amount = ($_POST['maximum_amount'] !== '') ? (float)$_POST['maximum_amount'] : null;
        $individual_use = isset($_POST['individual_use']) ? 1 : 0;
        $exclude_sale_items = isset($_POST['exclude_sale_items']) ? 1 : 0;
        $status = $_POST['status'] ?? 'active';

        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO coupons (code, description, discount_type, coupon_amount, expiry_date, usage_limit, usage_limit_per_user, minimum_amount, maximum_amount, individual_use, exclude_sale_items, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $description, $discount_type, $coupon_amount, $expiry_date, $usage_limit, $usage_limit_per_user, $minimum_amount, $maximum_amount, $individual_use, $exclude_sale_items, $status]);
                log_activity($pdo, 'add_coupon', 'coupons', $pdo->lastInsertId(), "Created coupon code $code");
                $_SESSION['flash_message'] = "Coupon <strong>" . htmlspecialchars($code) . "</strong> created successfully.";
                $_SESSION['flash_type'] = "success";
            } else {
                $stmt = $pdo->prepare("UPDATE coupons SET code = ?, description = ?, discount_type = ?, coupon_amount = ?, expiry_date = ?, usage_limit = ?, usage_limit_per_user = ?, minimum_amount = ?, maximum_amount = ?, individual_use = ?, exclude_sale_items = ?, status = ? WHERE id = ?");
                $stmt->execute([$code, $description, $discount_type, $coupon_amount, $expiry_date, $usage_limit, $usage_limit_per_user, $minimum_amount, $maximum_amount, $individual_use, $exclude_sale_items, $status, $id]);
                log_activity($pdo, 'update_coupon', 'coupons', $id, "Updated coupon code $code");
                $_SESSION['flash_message'] = "Coupon <strong>" . htmlspecialchars($code) . "</strong> updated successfully.";
                $_SESSION['flash_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "Error saving coupon: " . $e->getMessage();
            $_SESSION['flash_type'] = "error";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
                $stmt->execute([$id]);
                log_activity($pdo, 'delete_coupon', 'coupons', $id, "Deleted coupon ID $id");
                $_SESSION['flash_message'] = "Coupon deleted successfully.";
                $_SESSION['flash_type'] = "success";
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = "Error deleting coupon: " . $e->getMessage();
                $_SESSION['flash_type'] = "error";
            }
        }
    }
    header("Location: ecommerce.php?tab=coupons");
    exit();
}

// -------------------------------------------------------------
// FETCH DATA FOR DISPLAY
// -------------------------------------------------------------
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {}

$razorpay_mode = $settings['razorpay_mode'] ?? 'test';
$razorpay_live_key_id = $settings['razorpay_live_key_id'] ?? 'rzp_live_DW1px0XkHJ4tAv';
$razorpay_live_key_secret = $settings['razorpay_live_key_secret'] ?? 'A52buJeuJW1E8hsEg6ssfm70';
$razorpay_test_key_id = $settings['razorpay_test_key_id'] ?? 'rzp_test_4gwWqpQ2mlWxfH';
$razorpay_test_key_secret = $settings['razorpay_test_key_secret'] ?? 'e5DXo5IJdIkBO3apRU5zhCVd';

// Edit Mode Items
$edit_rule = null;
if (isset($_GET['edit']) && $tab === 'shipping') {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM shipping_rules WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_rule = $stmt->fetch();
}

$edit_discount = null;
if (isset($_GET['edit']) && $tab === 'discounts') {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM discount_rules WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_discount = $stmt->fetch();
}

$edit_coupon = null;
if (isset($_GET['edit']) && $tab === 'coupons') {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_coupon = $stmt->fetch();
}

// Lists
$shipping_rules = [];
if ($tab === 'shipping') {
    $rules_stmt = $pdo->query("SELECT * FROM shipping_rules ORDER BY min_amount ASC");
    $shipping_rules = $rules_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$all_products = [];
$all_categories = [];
$discount_rules = [];
if ($tab === 'discounts') {
    $all_products = $pdo->query("SELECT id, name, sku, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $all_categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $discount_rules = $pdo->query("SELECT * FROM discount_rules ORDER BY weight DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

$all_coupons = [];
if ($tab === 'coupons') {
    $all_coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap-header">
    <h1><i class="fa-solid fa-cart-shopping" style="color: var(--wp-blue);"></i> Ecommerce Settings</h1>
</div>

<?php if (!empty($message)): ?>
<div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
    <p><?php echo $message; ?></p>
</div>
<?php endif; ?>

<!-- WordPress Tab Navigation Bar -->
<div style="border-bottom: 1px solid #ccc; margin-bottom: 20px; display: flex; gap: 5px;">
    <a href="ecommerce.php?tab=shipping" class="button <?php echo $tab === 'shipping' ? 'button-primary' : 'button-secondary'; ?>" style="font-weight: 600; padding: 6px 16px;">
        <i class="fa-solid fa-truck-ramp-box"></i> Dynamic Shipping Charges
    </a>
    <a href="ecommerce.php?tab=payment" class="button <?php echo $tab === 'payment' ? 'button-primary' : 'button-secondary'; ?>" style="font-weight: 600; padding: 6px 16px;">
        <i class="fa-solid fa-credit-card"></i> Payment Gateway (Razorpay)
    </a>
    <a href="ecommerce.php?tab=discounts" class="button <?php echo $tab === 'discounts' ? 'button-primary' : 'button-secondary'; ?>" style="font-weight: 600; padding: 6px 16px;">
        <i class="fa-solid fa-tags"></i> Discount Architect (Rules)
    </a>
    <a href="ecommerce.php?tab=coupons" class="button <?php echo $tab === 'coupons' ? 'button-primary' : 'button-secondary'; ?>" style="font-weight: 600; padding: 6px 16px;">
        <i class="fa-solid fa-ticket"></i> Coupons
    </a>
</div>

<?php if ($tab === 'shipping'): ?>
    <!-- TAB 1: SHIPPING RULES -->
    <div class="wp-editor-columns" style="display: flex; gap: 20px; flex-wrap: wrap;">
        <div class="side-column" style="flex: 0 0 340px;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2>
                        <?php if ($edit_rule): ?>
                            <i class="fa-solid fa-pen-to-square" style="color: var(--wp-blue);"></i> Edit Shipping Tier
                        <?php else: ?>
                            <i class="fa-solid fa-circle-plus" style="color: var(--wp-blue);"></i> Add Shipping Tier
                        <?php endif; ?>
                    </h2>
                </div>
                <div class="postbox-body" style="padding: 16px;">
                    <form method="POST" action="ecommerce.php?tab=shipping">
                        <input type="hidden" name="action" value="<?php echo $edit_rule ? 'edit' : 'add'; ?>">
                        <?php if ($edit_rule): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_rule['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="min_amount">Min Order Amount (₹) *</label>
                            <input type="number" step="0.01" id="min_amount" name="min_amount" value="<?php echo htmlspecialchars($edit_rule['min_amount'] ?? '0'); ?>" required placeholder="0" class="form-control">
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="max_amount">Max Order Amount (₹)</label>
                            <input type="number" step="0.01" id="max_amount" name="max_amount" value="<?php echo htmlspecialchars($edit_rule['max_amount'] ?? ''); ?>" placeholder="Leave blank for No Limit" class="form-control">
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="charge">Shipping Charge Amount (₹) *</label>
                            <input type="number" step="0.01" id="charge" name="charge" value="<?php echo htmlspecialchars($edit_rule['charge'] ?? '0'); ?>" required placeholder="50" class="form-control">
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active" <?php echo ($edit_rule && $edit_rule['status'] === 'active') || !$edit_rule ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($edit_rule && $edit_rule['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="button button-primary">
                                <?php echo $edit_rule ? '<i class="fa-solid fa-floppy-disk"></i> Update Rule' : '<i class="fa-solid fa-plus"></i> Save Shipping Rule'; ?>
                            </button>
                            <?php if ($edit_rule): ?>
                                <a href="ecommerce.php?tab=shipping" class="button button-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="main-column" style="flex: 1; min-width: 480px;">
            <div class="postbox">
                <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2><i class="fa-solid fa-table-list" style="color: var(--wp-blue);"></i> Active Shipping Tiers &amp; Rules</h2>
                    <span style="font-size: 12px; color: #646970; font-weight: normal;"><?php echo count($shipping_rules); ?> tier(s) configured</span>
                </div>
                <div class="postbox-body" style="padding: 16px;">
                    <table class="wp-list-table" style="width: 100%;">
                        <thead>
                            <tr style="background: #f1f5f9;">
                                <th style="width: 40px; text-align: center;">#</th>
                                <th>Cart Amount Range (₹)</th>
                                <th style="width: 130px; text-align: right;">Shipping Charge</th>
                                <th style="width: 80px; text-align: center;">Status</th>
                                <th style="width: 120px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($shipping_rules)): ?>
                                <tr><td colspan="5" style="text-align: center; color: #888; padding: 20px;">No shipping rules configured.</td></tr>
                            <?php else: ?>
                                <?php $idx = 1; foreach ($shipping_rules as $r): ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: bold; color: #888;"><?php echo $idx++; ?></td>
                                        <td>
                                            <strong style="color: #1d2327; font-size: 14px;">
                                                ₹<?php echo number_format($r['min_amount'], 0); ?> – 
                                                <?php echo ($r['max_amount'] !== null && $r['max_amount'] > 0) ? '₹' . number_format($r['max_amount'], 0) : 'Above ₹' . number_format($r['min_amount'], 0); ?>
                                            </strong>
                                        </td>
                                        <td style="text-align: right; font-weight: 700; color: var(--wp-blue); font-size: 14px;">₹<?php echo number_format($r['charge'], 2); ?></td>
                                        <td style="text-align: center;">
                                            <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; background: <?php echo $r['status'] === 'active' ? '#e6f4ea; color: #137333;' : '#fce8e6; color: #c5221f;'; ?>">
                                                <?php echo ucfirst($r['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="ecommerce.php?tab=shipping&edit=<?php echo $r['id']; ?>" class="button button-secondary" style="font-size: 11px; padding: 2px 8px;"><i class="fa-solid fa-pen"></i> Edit</a>
                                            <form method="POST" action="ecommerce.php?tab=shipping" style="display: inline;" onsubmit="return confirm('Delete this shipping tier rule?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                                <button type="submit" class="button" style="font-size: 11px; padding: 2px 8px; color: #c5221f;"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'payment'): ?>
    <!-- TAB 2: PAYMENT GATEWAY (RAZORPAY) -->
    <div class="postbox" style="max-width: 900px;">
        <div class="postbox-header" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px;">
            <h2><i class="fa-solid fa-credit-card" style="color: var(--wp-blue);"></i> Razorpay Payment Gateway Configuration</h2>
            <span style="background: <?php echo $razorpay_mode === 'live' ? '#e6f4ea' : '#fff4e5'; ?>; color: <?php echo $razorpay_mode === 'live' ? '#137333' : '#b45309'; ?>; border: 1px solid <?php echo $razorpay_mode === 'live' ? '#b7e1cd' : '#fcd34d'; ?>; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 20px; text-transform: uppercase;">
                Mode: <?php echo strtoupper($razorpay_mode); ?>
            </span>
        </div>
        <div class="postbox-body" style="padding: 20px;">
            <form method="POST" action="ecommerce.php?tab=payment">
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; color: #1d2327; margin-bottom: 8px;">Environment Mode</label>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1d2327; font-size: 13px; background: #f8f9fa; padding: 12px 20px; border-radius: 6px; border: 1px solid <?php echo $razorpay_mode === 'test' ? '#2271b1' : '#e2e8f0'; ?>;">
                            <input type="radio" name="razorpay_mode" value="test" <?php echo $razorpay_mode === 'test' ? 'checked' : ''; ?>>
                            <span><strong>Test Mode</strong> (Sandbox / Development)</span>
                        </label>
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1d2327; font-size: 13px; background: #f8f9fa; padding: 12px 20px; border-radius: 6px; border: 1px solid <?php echo $razorpay_mode === 'live' ? '#137333' : '#e2e8f0'; ?>;">
                            <input type="radio" name="razorpay_mode" value="live" <?php echo $razorpay_mode === 'live' ? 'checked' : ''; ?>>
                            <span><strong>Live Mode</strong> (Production Payments)</span>
                        </label>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-bottom: 24px;">
                    <div style="background: #f6fbf7; border: 1px solid #b7e1cd; padding: 18px; border-radius: 8px;">
                        <h4 style="margin: 0 0 14px 0; color: #137333; font-size: 14px; display: flex; align-items: center; gap: 6px;"><i class="fa-solid fa-shield-halved"></i> Live Production Credentials</h4>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 12px; color: #1d2327; font-weight: 600; margin-bottom: 4px;">Live Key ID</label>
                            <input type="text" name="razorpay_live_key_id" value="<?php echo htmlspecialchars($razorpay_live_key_id); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-size: 12px; color: #1d2327; font-weight: 600; margin-bottom: 4px;">Live Key Secret</label>
                            <input type="password" name="razorpay_live_key_secret" value="<?php echo htmlspecialchars($razorpay_live_key_secret); ?>" class="form-control">
                        </div>
                    </div>

                    <div style="background: #fffbf5; border: 1px solid #fcd34d; padding: 18px; border-radius: 8px;">
                        <h4 style="margin: 0 0 14px 0; color: #b45309; font-size: 14px; display: flex; align-items: center; gap: 6px;"><i class="fa-solid fa-vial"></i> Test Sandbox Credentials</h4>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 12px; color: #1d2327; font-weight: 600; margin-bottom: 4px;">Test Key ID</label>
                            <input type="text" name="razorpay_test_key_id" value="<?php echo htmlspecialchars($razorpay_test_key_id); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-size: 12px; color: #1d2327; font-weight: 600; margin-bottom: 4px;">Test Key Secret</label>
                            <input type="password" name="razorpay_test_key_secret" value="<?php echo htmlspecialchars($razorpay_test_key_secret); ?>" class="form-control">
                        </div>
                    </div>
                </div>

                <button type="submit" class="button button-primary" style="padding: 6px 20px; font-weight: 600;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Payment Gateway Settings
                </button>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'discounts'): ?>
    <!-- TAB 3: DISCOUNT ARCHITECT (RULES) -->
    <div class="wp-editor-columns" style="display: flex; gap: 20px; flex-wrap: wrap;">

        <div class="side-column" style="flex: 0 0 380px;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2>
                        <?php if ($edit_discount): ?>
                            <i class="fa-solid fa-pen-to-square" style="color: var(--wp-blue);"></i> Edit Discount Rule
                        <?php else: ?>
                            <i class="fa-solid fa-plus" style="color: var(--wp-blue);"></i> Add New Discount Rule
                        <?php endif; ?>
                    </h2>
                </div>
                <div class="postbox-body" style="padding: 16px;">
                    <form method="POST" action="ecommerce.php?tab=discounts">
                        <input type="hidden" name="action" value="<?php echo $edit_discount ? 'edit' : 'add'; ?>">
                        <?php if ($edit_discount): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_discount['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="rule_name">Rule Name / Description</label>
                            <input type="text" id="rule_name" name="name" value="<?php echo htmlspecialchars($edit_discount['name'] ?? ''); ?>" placeholder="e.g. Summer Bridal Sale 15% Off" class="form-control">
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="scope">Discount Scope *</label>
                            <select id="scope" name="scope" class="form-control" onchange="toggleScopeInputs(this.value)">
                                <optgroup label="Global Rules">
                                    <option value="global" <?php echo ($edit_discount && $edit_discount['scope'] === 'global') ? 'selected' : ''; ?>>All Products (Storewide)</option>
                                </optgroup>
                                <optgroup label="Specific Targets">
                                    <option value="product" <?php echo ($edit_discount && $edit_discount['scope'] === 'product') ? 'selected' : ''; ?>>Product Specific</option>
                                    <option value="category" <?php echo ($edit_discount && $edit_discount['scope'] === 'category') ? 'selected' : ''; ?>>Category Specific</option>
                                </optgroup>
                                <optgroup label="Price Threshold Conditions">
                                    <option value="price_gt" <?php echo ($edit_discount && $edit_discount['scope'] === 'price_gt') ? 'selected' : ''; ?>>Price Greater Than (>)</option>
                                    <option value="price_lt" <?php echo ($edit_discount && $edit_discount['scope'] === 'price_lt') ? 'selected' : ''; ?>>Price Less Than (<)</option>
                                    <option value="price_between" <?php echo ($edit_discount && $edit_discount['scope'] === 'price_between') ? 'selected' : ''; ?>>Price Between (Min / Max)</option>
                                </optgroup>
                                <optgroup label="Compound Category + Price">
                                    <option value="cat_price_gt" <?php echo ($edit_discount && $edit_discount['scope'] === 'cat_price_gt') ? 'selected' : ''; ?>>Category + Price Greater Than</option>
                                    <option value="cat_price_lt" <?php echo ($edit_discount && $edit_discount['scope'] === 'cat_price_lt') ? 'selected' : ''; ?>>Category + Price Less Than</option>
                                    <option value="cat_price_between" <?php echo ($edit_discount && $edit_discount['scope'] === 'cat_price_between') ? 'selected' : ''; ?>>Category + Price Between</option>
                                </optgroup>
                            </select>
                        </div>

                        <?php 
                        $selected_targets = !empty($edit_discount['target']) ? explode(',', $edit_discount['target']) : [];
                        ?>

                        <div id="row_product_target" class="form-group" style="margin-bottom: 14px; display: none;">
                            <label>Select Target Products</label>
                            <div style="max-height: 150px; overflow-y: auto; border: 1px solid #dcdcde; border-radius: 4px; padding: 8px; background: #fff;">
                                <?php foreach ($all_products as $p): ?>
                                    <label style="display: block; font-size: 12px; margin-bottom: 4px;">
                                        <input type="checkbox" name="product_targets[]" value="<?php echo $p['id']; ?>" <?php echo in_array((string)$p['id'], $selected_targets) ? 'checked' : ''; ?>>
                                        #<?php echo $p['id']; ?> <?php echo htmlspecialchars($p['name']); ?> (₹<?php echo number_format($p['price'], 0); ?>)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div id="row_category_target" class="form-group" style="margin-bottom: 14px; display: none;">
                            <label>Select Target Categories</label>
                            <div style="max-height: 150px; overflow-y: auto; border: 1px solid #dcdcde; border-radius: 4px; padding: 8px; background: #fff;">
                                <?php foreach ($all_categories as $cat): ?>
                                    <label style="display: block; font-size: 12px; margin-bottom: 4px;">
                                        <input type="checkbox" name="category_targets[]" value="<?php echo $cat['id']; ?>" <?php echo in_array((string)$cat['id'], $selected_targets) ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?> (ID: <?php echo $cat['id']; ?>)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div id="row_price_threshold" class="form-group" style="margin-bottom: 14px; display: none;">
                            <label for="threshold">Price Threshold / Min Price (₹)</label>
                            <input type="number" step="0.01" id="threshold" name="threshold" value="<?php echo htmlspecialchars($edit_discount['threshold'] ?? ''); ?>" placeholder="e.g. 1000" class="form-control">
                        </div>

                        <div id="row_price_threshold_max" class="form-group" style="margin-bottom: 14px; display: none;">
                            <label for="threshold_max">Max Price Threshold (₹)</label>
                            <input type="number" step="0.01" id="threshold_max" name="threshold_max" value="<?php echo htmlspecialchars($edit_discount['threshold_max'] ?? ''); ?>" placeholder="e.g. 5000" class="form-control">
                        </div>

                        <div style="display: flex; gap: 10px; margin-bottom: 14px;">
                            <div style="flex: 1;">
                                <label for="type">Discount Type *</label>
                                <select id="type" name="type" class="form-control">
                                    <option value="percentage" <?php echo ($edit_discount && $edit_discount['type'] === 'percentage') ? 'selected' : ''; ?>>Percentage (%)</option>
                                    <option value="flat" <?php echo ($edit_discount && $edit_discount['type'] === 'flat') ? 'selected' : ''; ?>>Flat Amount (₹)</option>
                                </select>
                            </div>
                            <div style="flex: 1;">
                                <label for="value">Discount Value *</label>
                                <input type="number" step="0.01" id="value" name="value" value="<?php echo htmlspecialchars($edit_discount['value'] ?? '0'); ?>" required placeholder="e.g. 15 or 500" class="form-control">
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px; margin-bottom: 16px;">
                            <div style="flex: 1;">
                                <label for="weight">Priority Weight</label>
                                <input type="number" id="weight" name="weight" value="<?php echo htmlspecialchars($edit_discount['weight'] ?? '0'); ?>" placeholder="0" class="form-control">
                                <p style="font-size: 11px; color: #646970; margin-top: 2px;">Higher weight takes priority.</p>
                            </div>
                            <div style="flex: 1;">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="active" <?php echo ($edit_discount && $edit_discount['status'] === 'active') || !$edit_discount ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($edit_discount && $edit_discount['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="button button-primary">
                                <?php echo $edit_discount ? '<i class="fa-solid fa-floppy-disk"></i> Update Discount' : '<i class="fa-solid fa-plus"></i> Save Discount Rule'; ?>
                            </button>
                            <?php if ($edit_discount): ?>
                                <a href="ecommerce.php?tab=discounts" class="button button-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="main-column" style="flex: 1; min-width: 500px;">
            <div class="postbox">
                <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2><i class="fa-solid fa-tags" style="color: var(--wp-blue);"></i> Active Discount Architect Rules</h2>
                    <span style="font-size: 12px; color: #646970; font-weight: normal;"><?php echo count($discount_rules); ?> rule(s) active</span>
                </div>
                <div class="postbox-body" style="padding: 16px;">
                    
                    <div style="max-height: 480px; overflow-y: auto; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <table class="wp-list-table" style="width: 100%; margin: 0; border: none;">
                            <thead>
                                <tr style="background: #f1f5f9; position: sticky; top: 0; z-index: 10;">
                                    <th>Rule Name &amp; Scope</th>
                                    <th>Target Condition</th>
                                    <th style="width: 100px;">Discount</th>
                                    <th style="width: 70px; text-align: center;">Priority</th>
                                    <th style="width: 70px; text-align: center;">Status</th>
                                    <th style="width: 130px; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($discount_rules)): ?>
                                    <tr><td colspan="6" style="text-align: center; color: #888; padding: 20px;">No discount rules created yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($discount_rules as $dr): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: #1d2327; font-size: 13px;"><?php echo htmlspecialchars($dr['name'] ?: 'Rule #' . $dr['id']); ?></strong>
                                                <div style="font-size: 11px; color: #646970; text-transform: uppercase; font-weight: 600;">
                                                    Scope: <?php echo htmlspecialchars($dr['scope']); ?>
                                                </div>
                                            </td>
                                            <td style="font-size: 12px; color: #50575e;">
                                                <?php
                                                if ($dr['scope'] === 'global') {
                                                    echo '<em>All Store Products</em>';
                                                } elseif ($dr['scope'] === 'product') {
                                                    echo 'Products: <code>' . htmlspecialchars($dr['target']) . '</code>';
                                                } elseif ($dr['scope'] === 'category') {
                                                    echo 'Categories: <code>' . htmlspecialchars($dr['target']) . '</code>';
                                                } elseif ($dr['scope'] === 'price_gt') {
                                                    echo 'Price &gt; ₹' . number_format($dr['threshold'], 2);
                                                } elseif ($dr['scope'] === 'price_lt') {
                                                    echo 'Price &lt; ₹' . number_format($dr['threshold'], 2);
                                                } elseif ($dr['scope'] === 'price_between') {
                                                    echo 'Price Range: ₹' . number_format($dr['threshold'], 0) . ' – ₹' . number_format($dr['threshold_max'], 0);
                                                } else {
                                                    echo 'Cat IDs: <code>' . htmlspecialchars($dr['target']) . '</code> | Price: ₹' . number_format($dr['threshold'], 0);
                                                }
                                                ?>
                                            </td>
                                            <td style="font-weight: 700; color: #137333;">
                                                <?php echo ($dr['type'] === 'percentage') ? (float)$dr['value'] . '% OFF' : '₹' . number_format($dr['value'], 0) . ' OFF'; ?>
                                            </td>
                                            <td style="text-align: center; font-weight: 700; color: var(--wp-blue);">
                                                #<?php echo $dr['weight']; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; background: <?php echo $dr['status'] === 'active' ? '#e6f4ea; color: #137333;' : '#fce8e6; color: #c5221f;'; ?>">
                                                    <?php echo ucfirst($dr['status']); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <div style="display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end;">
                                                    <a href="ecommerce.php?tab=discounts&edit=<?php echo $dr['id']; ?>" class="button button-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px; border-radius: 4px; height: 26px;">
                                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                                    </a>
                                                    <form method="POST" action="ecommerce.php?tab=discounts" style="display: inline-block; margin: 0;" onsubmit="return confirm('Delete this discount rule?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $dr['id']; ?>">
                                                        <button type="submit" class="button" style="font-size: 11px; padding: 3px 8px; color: #c5221f; border-color: #f5c2c0; background: #fff5f5; display: inline-flex; align-items: center; gap: 4px; border-radius: 4px; height: 26px;">
                                                            <i class="fa-solid fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleScopeInputs(scope) {
        var prodRow = document.getElementById('row_product_target');
        var catRow = document.getElementById('row_category_target');
        var priceMinRow = document.getElementById('row_price_threshold');
        var priceMaxRow = document.getElementById('row_price_threshold_max');

        prodRow.style.display = (scope === 'product') ? 'block' : 'none';
        catRow.style.display = (scope === 'category' || scope.indexOf('cat_price') !== -1) ? 'block' : 'none';
        priceMinRow.style.display = (scope.indexOf('price') !== -1) ? 'block' : 'none';
        priceMaxRow.style.display = (scope.indexOf('between') !== -1) ? 'block' : 'none';
    }
    document.addEventListener('DOMContentLoaded', function() {
        var scopeSel = document.getElementById('scope');
        if (scopeSel) toggleScopeInputs(scopeSel.value);
    });
    </script>

<?php elseif ($tab === 'coupons'): ?>
    <!-- TAB 4: COUPONS -->
    <div class="wp-editor-columns" style="display: flex; gap: 20px; flex-wrap: wrap;">

        <!-- Left Column: Add / Edit Coupon Form -->
        <div class="side-column" style="flex: 0 0 380px;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2>
                        <?php if ($edit_coupon): ?>
                            <i class="fa-solid fa-pen-to-square" style="color: var(--wp-blue);"></i> Edit Coupon Code
                        <?php else: ?>
                            <i class="fa-solid fa-plus" style="color: var(--wp-blue);"></i> Create New Coupon
                        <?php endif; ?>
                    </h2>
                </div>
                <div class="postbox-body" style="padding: 16px;">
                    
                    <form method="POST" action="ecommerce.php?tab=coupons">
                        <input type="hidden" name="action" value="<?php echo $edit_coupon ? 'edit' : 'add'; ?>">
                        <?php if ($edit_coupon): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_coupon['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="coupon_code">Coupon Code *</label>
                            <input type="text" id="coupon_code" name="code" value="<?php echo htmlspecialchars($edit_coupon['code'] ?? ''); ?>" required placeholder="e.g. WELCOME10" class="form-control" style="font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="coupon_desc">Description / Campaign Notes</label>
                            <input type="text" id="coupon_desc" name="description" value="<?php echo htmlspecialchars($edit_coupon['description'] ?? ''); ?>" placeholder="e.g. 10% Off for first time customers" class="form-control">
                        </div>

                        <div style="display: flex; gap: 10px; margin-bottom: 14px;">
                            <div style="flex: 1;">
                                <label for="discount_type">Discount Type *</label>
                                <select id="discount_type" name="discount_type" class="form-control">
                                    <option value="percent" <?php echo ($edit_coupon && $edit_coupon['discount_type'] === 'percent') ? 'selected' : ''; ?>>Percentage (%)</option>
                                    <option value="fixed_cart" <?php echo ($edit_coupon && $edit_coupon['discount_type'] === 'fixed_cart') ? 'selected' : ''; ?>>Fixed Cart (₹)</option>
                                    <option value="fixed_product" <?php echo ($edit_coupon && $edit_coupon['discount_type'] === 'fixed_product') ? 'selected' : ''; ?>>Fixed Product (₹)</option>
                                </select>
                            </div>
                            <div style="flex: 1;">
                                <label for="coupon_amount">Coupon Amount *</label>
                                <input type="number" step="0.01" id="coupon_amount" name="coupon_amount" value="<?php echo htmlspecialchars($edit_coupon['coupon_amount'] ?? '0'); ?>" required placeholder="e.g. 10 or 500" class="form-control">
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px; margin-bottom: 14px;">
                            <div style="flex: 1;">
                                <label for="minimum_amount">Min Spend (₹)</label>
                                <input type="number" step="0.01" id="minimum_amount" name="minimum_amount" value="<?php echo htmlspecialchars($edit_coupon['minimum_amount'] ?? ''); ?>" placeholder="No Min" class="form-control">
                            </div>
                            <div style="flex: 1;">
                                <label for="maximum_amount">Max Spend (₹)</label>
                                <input type="number" step="0.01" id="maximum_amount" name="maximum_amount" value="<?php echo htmlspecialchars($edit_coupon['maximum_amount'] ?? ''); ?>" placeholder="No Max" class="form-control">
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px; margin-bottom: 14px;">
                            <div style="flex: 1;">
                                <label for="expiry_date">Expiry Date</label>
                                <input type="date" id="expiry_date" name="expiry_date" value="<?php echo htmlspecialchars($edit_coupon['expiry_date'] ?? ''); ?>" class="form-control">
                            </div>
                            <div style="flex: 1;">
                                <label for="usage_limit">Total Usage Limit</label>
                                <input type="number" id="usage_limit" name="usage_limit" value="<?php echo htmlspecialchars($edit_coupon['usage_limit'] ?? ''); ?>" placeholder="Unlimited" class="form-control">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="usage_limit_per_user">Usage Limit Per User</label>
                            <input type="number" id="usage_limit_per_user" name="usage_limit_per_user" value="<?php echo htmlspecialchars($edit_coupon['usage_limit_per_user'] ?? ''); ?>" placeholder="Unlimited" class="form-control">
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label style="display: block; font-size: 12px; cursor: pointer;">
                                <input type="checkbox" name="individual_use" value="1" <?php echo ($edit_coupon && $edit_coupon['individual_use']) ? 'checked' : ''; ?>>
                                <strong>Individual Use Only</strong> (Cannot be used with other coupons)
                            </label>
                            <label style="display: block; font-size: 12px; cursor: pointer; margin-top: 4px;">
                                <input type="checkbox" name="exclude_sale_items" value="1" <?php echo ($edit_coupon && $edit_coupon['exclude_sale_items']) ? 'checked' : ''; ?>>
                                <strong>Exclude Sale Items</strong>
                            </label>
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="coupon_status">Status</label>
                            <select id="coupon_status" name="status" class="form-control">
                                <option value="active" <?php echo ($edit_coupon && $edit_coupon['status'] === 'active') || !$edit_coupon ? 'selected' : ''; ?>>Active</option>
                                <option value="expired" <?php echo ($edit_coupon && $edit_coupon['status'] === 'expired') ? 'selected' : ''; ?>>Expired</option>
                                <option value="disabled" <?php echo ($edit_coupon && $edit_coupon['status'] === 'disabled') ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="button button-primary">
                                <?php echo $edit_coupon ? '<i class="fa-solid fa-floppy-disk"></i> Update Coupon' : '<i class="fa-solid fa-ticket"></i> Save Coupon Code'; ?>
                            </button>
                            <?php if ($edit_coupon): ?>
                                <a href="ecommerce.php?tab=coupons" class="button button-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>

                </div>
            </div>
        </div>

        <!-- Right Column: Coupons List Table -->
        <div class="main-column" style="flex: 1; min-width: 500px;">
            <div class="postbox">
                <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2><i class="fa-solid fa-ticket" style="color: var(--wp-blue);"></i> Promotional Coupon Codes</h2>
                    <span style="font-size: 12px; color: #646970; font-weight: normal;"><?php echo count($all_coupons); ?> coupon(s) total</span>
                </div>
                <div class="postbox-body" style="padding: 16px;">
                    
                    <div style="max-height: 480px; overflow-y: auto; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <table class="wp-list-table" style="width: 100%; margin: 0; border: none;">
                            <thead>
                                <tr style="background: #f1f5f9; position: sticky; top: 0; z-index: 10;">
                                    <th>Coupon Code</th>
                                    <th>Discount Type</th>
                                    <th style="width: 100px;">Amount</th>
                                    <th>Expiry Date</th>
                                    <th style="width: 80px; text-align: center;">Usage</th>
                                    <th style="width: 70px; text-align: center;">Status</th>
                                    <th style="width: 130px; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_coupons)): ?>
                                    <tr><td colspan="7" style="text-align: center; color: #888; padding: 20px;">No coupons created yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($all_coupons as $c): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: #1d2327; font-size: 14px; letter-spacing: 1px;"><?php echo htmlspecialchars($c['code']); ?></strong>
                                                <?php if (!empty($c['description'])): ?>
                                                    <div style="font-size: 11px; color: #646970;"><?php echo htmlspecialchars($c['description']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 12px; color: #50575e;">
                                                <?php 
                                                $types = [
                                                    'percent' => 'Percentage Discount',
                                                    'fixed_cart' => 'Fixed Cart Discount',
                                                    'fixed_product' => 'Fixed Product Discount'
                                                ];
                                                echo $types[$c['discount_type']] ?? $c['discount_type'];
                                                ?>
                                            </td>
                                            <td style="font-weight: 700; color: #137333;">
                                                <?php echo ($c['discount_type'] === 'percent') ? (float)$c['coupon_amount'] . '% OFF' : '₹' . number_format($c['coupon_amount'], 2) . ' OFF'; ?>
                                            </td>
                                            <td style="font-size: 12px; color: #50575e;">
                                                <?php echo !empty($c['expiry_date']) ? date('M d, Y', strtotime($c['expiry_date'])) : '<em>Never Expires</em>'; ?>
                                            </td>
                                            <td style="text-align: center; font-size: 12px; color: #1d2327;">
                                                <strong><?php echo (int)$c['usage_count']; ?></strong> / <?php echo ($c['usage_limit'] !== null) ? (int)$c['usage_limit'] : '∞'; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; background: <?php 
                                                    echo $c['status'] === 'active' ? '#e6f4ea; color: #137333;' : 
                                                        ($c['status'] === 'expired' ? '#fce8e6; color: #c5221f;' : '#f1f5f9; color: #646970;'); 
                                                ?>">
                                                    <?php echo ucfirst($c['status']); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <div style="display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end;">
                                                    <a href="ecommerce.php?tab=coupons&edit=<?php echo $c['id']; ?>" class="button button-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px; border-radius: 4px; height: 26px;">
                                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                                    </a>
                                                    <form method="POST" action="ecommerce.php?tab=coupons" style="display: inline-block; margin: 0;" onsubmit="return confirm('Delete this coupon?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                        <button type="submit" class="button" style="font-size: 11px; padding: 3px 8px; color: #c5221f; border-color: #f5c2c0; background: #fff5f5; display: inline-flex; align-items: center; gap: 4px; border-radius: 4px; height: 26px;">
                                                            <i class="fa-solid fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
