<?php
// admin/email-templates.php
$page_title = "Email Templates & Notifications";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Factory defaults for reset functionality
$factory_defaults = [
    'order_success' => [
        'name' => 'Order Confirmation / Payment Success',
        'description' => 'Sent automatically when payment is confirmed and order begins processing.',
        'subject' => 'Order Confirmation - {ORDER_NUMBER} | YosshitaNeha Fashion Studio',
        'badge_text' => 'PAYMENT CONFIRMED & ORDER PROCESSING',
        'headline' => 'Thank You for Your Order!',
        'subheading' => 'Your order <strong>{ORDER_NUMBER}</strong> has been placed successfully and is being prepared with exquisite care by our artisans.',
        'footer_note' => 'If you have any questions regarding your bespoke creation, our atelier concierge is always here to assist you.',
        'is_enabled' => 1
    ],
    'order_shipped' => [
        'name' => 'Order Shipped / Dispatched',
        'description' => 'Sent when the order is handed to the courier partner with live tracking details.',
        'subject' => 'Your Order {ORDER_NUMBER} Has Been Shipped! | YosshitaNeha',
        'badge_text' => 'ORDER SHIPPED & IN TRANSIT',
        'headline' => 'Great News! Your Order Is On Its Way',
        'subheading' => 'Your order <strong>{ORDER_NUMBER}</strong> has been carefully packaged and dispatched via courier.',
        'footer_note' => 'You can track your package in real-time using the live tracking button above.',
        'is_enabled' => 1
    ],
    'order_delivered' => [
        'name' => 'Order Delivered',
        'description' => 'Sent once the shipment has been successfully delivered to the customer destination.',
        'subject' => 'Order Delivered - {ORDER_NUMBER} | YosshitaNeha',
        'badge_text' => 'ORDER DELIVERED',
        'headline' => 'Your Order Has Been Delivered!',
        'subheading' => 'Your order <strong>{ORDER_NUMBER}</strong> has been successfully delivered to your shipping address. We hope you adore your luxury creation!',
        'footer_note' => 'We would love to see how you style it! Tag your moments on Instagram @yosshitaneha.',
        'is_enabled' => 1
    ],
    'order_cancelled' => [
        'name' => 'Order Cancelled / Payment Failed',
        'description' => 'Sent if an order is cancelled or a transaction fails.',
        'subject' => 'Order Cancellation Notice - {ORDER_NUMBER} | YosshitaNeha',
        'badge_text' => 'ORDER CANCELLED',
        'headline' => 'Order #{ORDER_NUMBER} Has Been Cancelled',
        'subheading' => 'Your order <strong>{ORDER_NUMBER}</strong> has been cancelled. If any payment was captured, a full refund will be processed to your original payment method within 5-7 business days.',
        'footer_note' => 'If this cancellation was unintended, please visit our studio website or contact our support team.',
        'is_enabled' => 1
    ],
    'customer_welcome' => [
        'name' => 'Customer Welcome Email',
        'description' => 'Sent when a new customer registers an account on the website.',
        'subject' => 'Welcome to YosshitaNeha Fashion Studio',
        'badge_text' => 'WELCOME TO OUR ATELIER',
        'headline' => 'Welcome, {CUSTOMER_NAME}!',
        'subheading' => 'Thank you for registering with YosshitaNeha. We are delighted to welcome you to our world of handcrafted luxury bridal couture, fine jewelry, and bespoke fashion.',
        'footer_note' => 'Your account is ready. You can now save your addresses, track orders in real-time, and curate your personalized wishlist.',
        'is_enabled' => 1
    ]
];

// Ensure table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_key VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            description VARCHAR(255) DEFAULT '',
            subject VARCHAR(255) NOT NULL,
            badge_text VARCHAR(100) DEFAULT '',
            headline VARCHAR(255) NOT NULL,
            subheading TEXT NOT NULL,
            footer_note TEXT DEFAULT NULL,
            is_enabled TINYINT(1) DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed any missing defaults
    $chkStmt = $pdo->prepare("SELECT id FROM email_templates WHERE template_key = ?");
    $insStmt = $pdo->prepare("
        INSERT INTO email_templates (template_key, name, description, subject, badge_text, headline, subheading, footer_note, is_enabled)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($factory_defaults as $k => $def) {
        $chkStmt->execute([$k]);
        if (!$chkStmt->fetch()) {
            $insStmt->execute([
                $k, $def['name'], $def['description'], $def['subject'], 
                $def['badge_text'], $def['headline'], $def['subheading'], 
                $def['footer_note'], $def['is_enabled']
            ]);
        }
    }

    // Ensure extra_to, cc_emails, and bcc_emails columns exist
    try { $pdo->exec("ALTER TABLE email_templates ADD COLUMN extra_to TEXT DEFAULT NULL AFTER footer_note"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE email_templates ADD COLUMN cc_emails TEXT DEFAULT NULL AFTER extra_to"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE email_templates ADD COLUMN bcc_emails TEXT DEFAULT NULL AFTER cc_emails"); } catch (Exception $e) {}
} catch (Exception $e) {}

// Selected active template tab
$selected_key = trim($_GET['template'] ?? 'order_success');
if (!array_key_exists($selected_key, $factory_defaults)) {
    $selected_key = 'order_success';
}

$flash_msg = '';
$flash_type = 'success';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $post_key = trim($_POST['template_key'] ?? $selected_key);

    if ($action === 'save_template') {
        $subject = trim($_POST['subject'] ?? '');
        $badge_text = trim($_POST['badge_text'] ?? '');
        $headline = trim($_POST['headline'] ?? '');
        $subheading = trim($_POST['subheading'] ?? '');
        $footer_note = trim($_POST['footer_note'] ?? '');

        // Normalize multiple comma-separated emails
        $extra_to_list = parse_email_list($_POST['extra_to'] ?? '');
        $extra_to = !empty($extra_to_list) ? implode(', ', $extra_to_list) : null;

        $cc_list = parse_email_list($_POST['cc_emails'] ?? '');
        $cc_emails = !empty($cc_list) ? implode(', ', $cc_list) : null;

        $bcc_list = parse_email_list($_POST['bcc_emails'] ?? '');
        $bcc_emails = !empty($bcc_list) ? implode(', ', $bcc_list) : null;

        $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("
                UPDATE email_templates 
                SET subject = ?, badge_text = ?, headline = ?, subheading = ?, footer_note = ?, extra_to = ?, cc_emails = ?, bcc_emails = ?, is_enabled = ?
                WHERE template_key = ?
            ");
            $stmt->execute([$subject, $badge_text, $headline, $subheading, $footer_note, $extra_to, $cc_emails, $bcc_emails, $is_enabled, $post_key]);

            log_activity($pdo, 'update_email_template', 'settings', 0, "Updated email template '$post_key' (TO: $extra_to | CC: $cc_emails | BCC: $bcc_emails)");
            $_SESSION['flash_msg'] = "Email template <strong>" . htmlspecialchars($factory_defaults[$post_key]['name'] ?? $post_key) . "</strong> updated successfully!";
            $_SESSION['flash_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['flash_msg'] = "Database Error: " . $e->getMessage();
            $_SESSION['flash_type'] = "error";
        }
        header("Location: email-templates.php?template=" . urlencode($post_key));
        exit();

    } elseif ($action === 'reset_template') {
        if (isset($factory_defaults[$post_key])) {
            $def = $factory_defaults[$post_key];
            try {
                $stmt = $pdo->prepare("
                    UPDATE email_templates 
                    SET subject = ?, badge_text = ?, headline = ?, subheading = ?, footer_note = ?, extra_to = NULL, cc_emails = NULL, bcc_emails = NULL, is_enabled = 1
                    WHERE template_key = ?
                ");
                $stmt->execute([
                    $def['subject'], $def['badge_text'], $def['headline'], 
                    $def['subheading'], $def['footer_note'], $post_key
                ]);

                log_activity($pdo, 'reset_email_template', 'settings', 0, "Reset email template '$post_key' to default");
                $_SESSION['flash_msg'] = "Template reset to original luxury studio default.";
                $_SESSION['flash_type'] = "success";
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = "Database Error: " . $e->getMessage();
                $_SESSION['flash_type'] = "error";
            }
        }
        header("Location: email-templates.php?template=" . urlencode($post_key));
        exit();

    } elseif ($action === 'send_test_email') {
        $test_email = trim($_POST['test_email'] ?? '');
        if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_msg'] = "Please provide a valid test recipient email address.";
            $_SESSION['flash_type'] = "error";
        } else {
            // Find an order or use realistic sample data
            $sampleOrderStmt = $pdo->query("SELECT id FROM orders ORDER BY id DESC LIMIT 1");
            $sampleOrderId = (int)$sampleOrderStmt->fetchColumn();

            if ($post_key === 'customer_welcome') {
                $res = send_welcome_email($pdo, $test_email, 'Valued Guest');
            } else {
                $statusMap = [
                    'order_success' => 'success',
                    'order_shipped' => 'shipped',
                    'order_delivered' => 'delivered',
                    'order_cancelled' => 'cancelled'
                ];
                $st = $statusMap[$post_key] ?? 'success';

                if ($sampleOrderId > 0) {
                    $res = send_order_email($pdo, $sampleOrderId, $st);
                } else {
                    $res = ['success' => false, 'error' => 'No orders exist in the database to preview items table. Please place a test order first.'];
                }
            }

            if ($res['success']) {
                $_SESSION['flash_msg'] = "Test preview email successfully dispatched to <strong>" . htmlspecialchars($test_email) . "</strong>!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_msg'] = "Failed to send test email: " . htmlspecialchars($res['error'] ?? $res['message'] ?? 'Check SMTP Settings');
                $_SESSION['flash_type'] = "error";
            }
        }
        header("Location: email-templates.php?template=" . urlencode($post_key));
        exit();
    }
}

if (!empty($_SESSION['flash_msg'])) {
    $flash_msg = $_SESSION['flash_msg'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// Fetch all templates from DB
$all_templates = [];
try {
    $stmt = $pdo->query("SELECT * FROM email_templates ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $all_templates[$r['template_key']] = $r;
    }
} catch (Exception $e) {}

$current_template = $all_templates[$selected_key] ?? ($factory_defaults[$selected_key] ?? []);

// Admin default email for test button
$admin_email = $_SESSION['admin_email'] ?? 'info@yosshitaneha.com';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Header Banner -->
<div class="dashboard-header-banner" style="margin-bottom: 24px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting" style="flex-wrap: wrap; gap: 12px; align-items: center;">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: #09090b; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa-solid fa-envelope-circle-check"></i>
            </div>
            <div>
                <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #09090b;">Email Templates &amp; Notifications</h1>
                <p style="margin: 2px 0 0; font-size: 13px; color: #71717a;">Customize automated customer notifications for orders, shipments, cancellations, and welcome messages.</p>
            </div>
        </div>
    </div>
    <div class="dashboard-actions" style="display: flex; gap: 10px;">
        <a href="mail-settings.php" class="shadcn-btn shadcn-btn-outline" title="Configure SMTP Mail Gateway">
            <i class="fa-solid fa-server"></i> SMTP Settings
        </a>
    </div>
</div>

<?php if ($flash_msg): ?>
    <div class="notice notice-<?php echo $flash_type === 'error' ? 'error' : 'success'; ?> auto-dismiss" style="margin-bottom: 20px;">
        <p><?php echo $flash_msg; ?></p>
    </div>
<?php endif; ?>

<!-- Main 2-Column Workspace Grid -->
<div style="display: grid; grid-template-columns: 290px 1fr; gap: 24px; align-items: start;">

    <!-- Left Navigation: Template Selector Cards -->
    <div>
        <div class="shadcn-card" style="padding: 12px; border-radius: 12px;">
            <div style="padding: 10px 12px 14px; border-bottom: 1px solid #f4f4f5; margin-bottom: 8px;">
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #71717a;">
                    Select Notification Trigger
                </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 6px;">
                <?php
                $template_nav = [
                    'order_success' => [
                        'title' => 'Order Confirmation',
                        'subtitle' => 'Payment Success',
                        'icon' => 'fa-circle-check',
                        'color' => '#10b981'
                    ],
                    'order_shipped' => [
                        'title' => 'Order Shipped',
                        'subtitle' => 'In Transit / Courier',
                        'icon' => 'fa-truck-fast',
                        'color' => '#3b82f6'
                    ],
                    'order_delivered' => [
                        'title' => 'Order Delivered',
                        'subtitle' => 'Arrival Notice',
                        'icon' => 'fa-box-check',
                        'color' => '#059669'
                    ],
                    'order_cancelled' => [
                        'title' => 'Order Cancelled',
                        'subtitle' => 'Refund / Failed',
                        'icon' => 'fa-circle-xmark',
                        'color' => '#ef4444'
                    ],
                    'customer_welcome' => [
                        'title' => 'Customer Welcome',
                        'subtitle' => 'Account Registration',
                        'icon' => 'fa-user-plus',
                        'color' => '#d97706'
                    ]
                ];

                foreach ($template_nav as $k => $item):
                    $is_selected = ($k === $selected_key);
                    $t_data = $all_templates[$k] ?? ($factory_defaults[$k] ?? []);
                    $is_active = (int)($t_data['is_enabled'] ?? 1);
                ?>
                    <a href="email-templates.php?template=<?php echo urlencode($k); ?>" 
                       style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 8px; text-decoration: none; transition: all 0.15s ease; border: 1px solid <?php echo $is_selected ? '#09090b' : 'transparent'; ?>; background: <?php echo $is_selected ? '#09090b' : '#fafafa'; ?>; color: <?php echo $is_selected ? '#ffffff' : '#09090b'; ?>;">
                        <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                            <div style="width: 32px; height: 32px; border-radius: 6px; background: <?php echo $is_selected ? 'rgba(255,255,255,0.15)' : '#ffffff'; ?>; border: 1px solid <?php echo $is_selected ? 'transparent' : '#e4e4e7'; ?>; display: flex; align-items: center; justify-content: center; font-size: 13px; color: <?php echo $is_selected ? '#ffffff' : $item['color']; ?>; flex-shrink: 0;">
                                <i class="fa-solid <?php echo $item['icon']; ?>"></i>
                            </div>
                            <div style="overflow: hidden;">
                                <div style="font-weight: 600; font-size: 13px; line-height: 1.3; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </div>
                                <div style="font-size: 11px; opacity: 0.75; margin-top: 1px;">
                                    <?php echo htmlspecialchars($item['subtitle']); ?>
                                </div>
                                <?php if (!empty($t_data['bcc_emails']) || !empty($t_data['cc_emails']) || !empty($t_data['extra_to'])): ?>
                                    <div style="margin-top: 5px; display: flex; gap: 4px; flex-wrap: wrap;">
                                        <?php if (!empty($t_data['extra_to'])): 
                                            $cntTo = count(parse_email_list($t_data['extra_to']));
                                        ?>
                                            <span style="font-size: 9px; font-weight: 700; padding: 1px 5px; border-radius: 4px; background: <?php echo $is_selected ? 'rgba(255,255,255,0.2)' : '#e0f2fe'; ?>; color: <?php echo $is_selected ? '#ffffff' : '#0284c7'; ?>;">+TO<?php echo $cntTo > 1 ? " ($cntTo)" : ""; ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($t_data['cc_emails'])): 
                                            $cntCc = count(parse_email_list($t_data['cc_emails']));
                                        ?>
                                            <span style="font-size: 9px; font-weight: 700; padding: 1px 5px; border-radius: 4px; background: <?php echo $is_selected ? 'rgba(255,255,255,0.2)' : '#ecfdf5'; ?>; color: <?php echo $is_selected ? '#ffffff' : '#059669'; ?>;">CC<?php echo $cntCc > 1 ? " ($cntCc)" : ""; ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($t_data['bcc_emails'])): 
                                            $cntBcc = count(parse_email_list($t_data['bcc_emails']));
                                        ?>
                                            <span style="font-size: 9px; font-weight: 700; padding: 1px 5px; border-radius: 4px; background: <?php echo $is_selected ? 'rgba(255,255,255,0.2)' : '#f3e8ff'; ?>; color: <?php echo $is_selected ? '#ffffff' : '#7c3aed'; ?>;">BCC<?php echo $cntBcc > 1 ? " ($cntBcc)" : ""; ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <?php if ($is_active): ?>
                                <span style="font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 10px; background: <?php echo $is_selected ? 'rgba(16,185,129,0.25)' : '#ecfdf5'; ?>; color: <?php echo $is_selected ? '#34d399' : '#059669'; ?>; border: 1px solid <?php echo $is_selected ? 'transparent' : '#a7f3d0'; ?>;">
                                    ON
                                </span>
                            <?php else: ?>
                                <span style="font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 10px; background: <?php echo $is_selected ? 'rgba(239,68,68,0.25)' : '#fef2f2'; ?>; color: <?php echo $is_selected ? '#f87171' : '#dc2626'; ?>; border: 1px solid <?php echo $is_selected ? 'transparent' : '#fecaca'; ?>;">
                                    OFF
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Send Quick Test Email Card -->
            <div style="margin-top: 20px; padding: 14px; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px;">
                <div style="font-size: 12px; font-weight: 700; color: #09090b; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-paper-plane" style="color: #6366f1;"></i> Send Live Preview
                </div>
                <p style="font-size: 11.5px; color: #71717a; margin: 0 0 10px; line-height: 1.4;">
                    Send this <strong><?php echo htmlspecialchars($template_nav[$selected_key]['title'] ?? 'current'); ?></strong> email to your inbox to check mobile layout.
                </p>
                <form method="POST" action="email-templates.php?template=<?php echo urlencode($selected_key); ?>">
                    <input type="hidden" name="action" value="send_test_email">
                    <input type="hidden" name="template_key" value="<?php echo htmlspecialchars($selected_key); ?>">
                    <input type="email" name="test_email" required value="<?php echo htmlspecialchars($admin_email); ?>" placeholder="you@domain.com" class="shadcn-input" style="font-size: 12px; padding: 6px 10px; width: 100%; box-sizing: border-box; margin-bottom: 8px;">
                    <button type="submit" class="shadcn-btn shadcn-btn-outline" style="width: 100%; justify-content: center; font-size: 12px; height: 32px;">
                        <i class="fa-solid fa-paper-plane" style="margin-right: 5px;"></i> Send Test Email
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Workspace: Template Editor & Live Preview -->
    <div style="display: flex; flex-direction: column; gap: 20px;">

        <!-- Edit Form Card -->
        <div class="shadcn-card">
            <div class="shadcn-card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #e4e4e7;">
                <div>
                    <h2 class="shadcn-card-title" style="font-size: 16px; margin: 0;">
                        <?php echo htmlspecialchars($current_template['name'] ?? $factory_defaults[$selected_key]['name']); ?>
                    </h2>
                    <p style="margin: 4px 0 0; font-size: 12px; color: #71717a;">
                        <?php echo htmlspecialchars($current_template['description'] ?? $factory_defaults[$selected_key]['description']); ?>
                    </p>
                </div>
                
                <div style="display: flex; gap: 10px; align-items: center;">
                    <form method="POST" action="email-templates.php?template=<?php echo urlencode($selected_key); ?>" onsubmit="return confirm('Restore original luxury studio wording for this template?');" style="margin: 0;">
                        <input type="hidden" name="action" value="reset_template">
                        <input type="hidden" name="template_key" value="<?php echo htmlspecialchars($selected_key); ?>">
                        <button type="submit" class="shadcn-btn shadcn-btn-outline" style="height: 32px; font-size: 12px; color: #71717a;">
                            <i class="fa-solid fa-rotate-left" style="margin-right: 4px;"></i> Restore Default
                        </button>
                    </form>
                </div>
            </div>

            <form method="POST" action="email-templates.php?template=<?php echo urlencode($selected_key); ?>" style="padding: 20px;">
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="template_key" value="<?php echo htmlspecialchars($selected_key); ?>">

                <!-- Status Toggle -->
                <div style="margin-bottom: 20px; padding: 12px 16px; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-size: 13px; font-weight: 600; color: #09090b;">Automatic Email Dispatch</div>
                        <div style="font-size: 12px; color: #71717a;">Enable or disable sending this notification to customers when triggered.</div>
                    </div>
                    <label style="position: relative; display: inline-block; width: 44px; height: 24px; margin: 0; cursor: pointer;">
                        <input type="checkbox" name="is_enabled" value="1" <?php echo !empty($current_template['is_enabled']) ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                        <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .2s; border-radius: 24px;"></span>
                    </label>
                </div>

                <!-- Subject Line -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #09090b; margin-bottom: 6px;">
                        Email Subject Line <span style="color: #e11d48;">*</span>
                    </label>
                    <input type="text" name="subject" id="tpl_subject" required 
                           value="<?php echo htmlspecialchars($current_template['subject'] ?? ''); ?>" 
                           class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13.5px;" 
                           oninput="updatePreview()">
                </div>

                <!-- Badge Text & Headline -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 600; color: #09090b; margin-bottom: 6px;">
                            Status Pill / Badge Text
                        </label>
                        <input type="text" name="badge_text" id="tpl_badge_text" 
                               value="<?php echo htmlspecialchars($current_template['badge_text'] ?? ''); ?>" 
                               placeholder="e.g. PAYMENT CONFIRMED" 
                               class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13.5px;"
                               oninput="updatePreview()">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 600; color: #09090b; margin-bottom: 6px;">
                            Main Headline / Title <span style="color: #e11d48;">*</span>
                        </label>
                        <input type="text" name="headline" id="tpl_headline" required 
                               value="<?php echo htmlspecialchars($current_template['headline'] ?? ''); ?>" 
                               placeholder="e.g. Thank You for Your Order!" 
                               class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13.5px;"
                               oninput="updatePreview()">
                    </div>
                </div>

                <!-- Subheading / Message Body -->
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label style="font-size: 12.5px; font-weight: 600; color: #09090b;">
                            Message Body / Greeting Text <span style="color: #e11d48;">*</span>
                        </label>
                        <span style="font-size: 11px; color: #71717a;">HTML formatting supported (e.g. &lt;strong&gt;, &lt;br&gt;)</span>
                    </div>
                    <textarea name="subheading" id="tpl_subheading" rows="3" required class="shadcn-input" 
                              style="width: 100%; box-sizing: border-box; font-size: 13px; line-height: 1.5; font-family: inherit; height: auto;" 
                              oninput="updatePreview()"><?php echo htmlspecialchars($current_template['subheading'] ?? ''); ?></textarea>
                </div>

                <!-- Footer Note -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #09090b; margin-bottom: 6px;">
                        Concierge / Customer Support Note
                    </label>
                    <textarea name="footer_note" id="tpl_footer_note" rows="2" class="shadcn-input" 
                              style="width: 100%; box-sizing: border-box; font-size: 13px; line-height: 1.5; font-family: inherit; height: auto;" 
                              oninput="updatePreview()"><?php echo htmlspecialchars($current_template['footer_note'] ?? ''); ?></textarea>
                </div>

                <!-- Recipient Routing (TO / CC / BCC Copy for Client & Admin) -->
                <div style="margin-bottom: 22px; padding: 18px; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #e4e4e7; padding-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 28px; height: 28px; border-radius: 6px; background: #6366f1; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px;">
                                <i class="fa-solid fa-copy"></i>
                            </div>
                            <div>
                                <span style="font-size: 13px; font-weight: 700; color: #09090b;">Client &amp; Admin Copy Routing (TO / CC / BCC)</span>
                                <div style="font-size: 11.5px; color: #71717a;">Receive an automatic copy of this notification whenever it is sent to a customer. Supports multiple comma-separated emails.</div>
                            </div>
                        </div>
                        <span class="shadcn-badge shadcn-badge-sky" style="font-size: 10px; padding: 2px 8px;">Multi-Recipient Enabled</span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <!-- Additional Primary TO -->
                        <div>
                            <label style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 4px;">
                                <span><i class="fa-solid fa-at" style="color: #0284c7; margin-right: 4px;"></i> Additional Direct Recipient (TO)</span>
                                <span style="font-size: 10px; color: #0284c7; background: #e0f2fe; padding: 2px 7px; border-radius: 4px; font-weight: 600;">Multiple: comma-separated</span>
                            </label>
                            <input type="text" name="extra_to" id="tpl_extra_to" 
                                   value="<?php echo htmlspecialchars($current_template['extra_to'] ?? ''); ?>" 
                                   placeholder="e.g. client@gmail.com, boutique@yosshitaneha.com" 
                                   class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13px;"
                                   oninput="updatePreview(); renderEmailBadges('tpl_extra_to', 'badges_extra_to');">
                            <div id="badges_extra_to" style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px;"></div>
                            <p style="font-size: 11px; color: #71717a; margin: 3px 0 0;">These addresses will be primary recipients in the <code>TO</code> header alongside the customer. Add multiple separated by commas.</p>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <!-- CC -->
                            <div>
                                <label style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 4px;">
                                    <span><i class="fa-solid fa-envelope" style="color: #059669; margin-right: 4px;"></i> Carbon Copy (CC - Visible)</span>
                                    <span style="font-size: 10px; color: #059669; background: #ecfdf5; padding: 2px 7px; border-radius: 4px; font-weight: 600;">Multiple: comma-separated</span>
                                </label>
                                <input type="text" name="cc_emails" id="tpl_cc" 
                                       value="<?php echo htmlspecialchars($current_template['cc_emails'] ?? ''); ?>" 
                                       placeholder="e.g. client@gmail.com, boutique@yosshitaneha.com, manager@domain.com" 
                                       class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13px;"
                                       oninput="updatePreview(); renderEmailBadges('tpl_cc', 'badges_cc');">
                                <div id="badges_cc" style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px;"></div>
                                <p style="font-size: 11px; color: #71717a; margin: 3px 0 0;">Visible to customer in the email header. Separate multiple emails with commas.</p>
                            </div>

                            <!-- BCC -->
                            <div>
                                <label style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 4px;">
                                    <span><i class="fa-solid fa-eye-slash" style="color: #7c3aed; margin-right: 4px;"></i> Blind Carbon Copy (BCC - Hidden)</span>
                                    <span style="font-size: 10px; color: #7c3aed; background: #f3e8ff; padding: 2px 7px; border-radius: 4px; font-weight: 600;">Multiple: comma-separated (Recommended)</span>
                                </label>
                                <input type="text" name="bcc_emails" id="tpl_bcc" 
                                       value="<?php echo htmlspecialchars($current_template['bcc_emails'] ?? ''); ?>" 
                                       placeholder="e.g. client@gmail.com, orders@yosshitaneha.com, owner@domain.com" 
                                       class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13px;"
                                       oninput="updatePreview(); renderEmailBadges('tpl_bcc', 'badges_bcc');">
                                <div id="badges_bcc" style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px;"></div>
                                <p style="font-size: 11px; color: #71717a; margin: 3px 0 0;">Hidden copy — customer will NOT see these addresses. Recommended for boutique owner &amp; client monitoring. Separate multiple emails with commas.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Available Dynamic Variable Tags Pill Bar -->
                <div style="margin-bottom: 22px; padding: 12px 14px; background: #fafafa; border: 1px dashed #cbd5e1; border-radius: 8px;">
                    <div style="font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 8px;">
                        <i class="fa-solid fa-code" style="margin-right: 4px;"></i> Click Placeholder Tag to Copy / Insert:
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                        <?php
                        $tags = [
                            '{ORDER_NUMBER}' => 'Order ID (YNFS_1011)',
                            '{CUSTOMER_NAME}' => 'Customer Full Name',
                            '{ORDER_DATE}' => 'Date & Time Placed',
                            '{TOTAL_AMOUNT}' => 'Total Order Value (₹350)',
                            '{COURIER_NAME}' => 'Courier Name (Blue Dart)',
                            '{TRACKING_NUMBER}' => 'POD Waybill Number'
                        ];
                        foreach ($tags as $tag => $label):
                        ?>
                            <button type="button" onclick="insertTag('<?php echo $tag; ?>')" 
                                    style="border: 1px solid #e2e8f0; background: #ffffff; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-size: 11.5px; color: #09090b; cursor: pointer; transition: all 0.15s;" 
                                    title="Click to copy <?php echo $label; ?>">
                                <strong><?php echo $tag; ?></strong>
                                <span style="font-size: 10px; color: #71717a; margin-left: 4px;">(<?php echo $label; ?>)</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 16px; border-top: 1px solid #e4e4e7;">
                    <a href="email-templates.php?template=<?php echo urlencode($selected_key); ?>" class="shadcn-btn shadcn-btn-outline">
                        Cancel Changes
                    </a>
                    <button type="submit" class="shadcn-btn shadcn-btn-primary" style="height: 36px; padding: 0 20px;">
                        <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i> Save Template
                    </button>
                </div>
            </form>
        </div>

        <!-- Live Visual Email Preview Card -->
        <div class="shadcn-card">
            <div class="shadcn-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-eye" style="color: #c8a55c;"></i>
                    <h2 class="shadcn-card-title" style="font-size: 15px; margin: 0;">Live Customer Inbox Preview</h2>
                </div>
                <span class="shadcn-badge" style="background: #f4f4f5; color: #71717a; font-size: 11px;">
                    Simulated Luxury Email Layout
                </span>
            </div>

            <div style="padding: 24px; background: #f4f6f8; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; overflow-x: auto;">
                
                <!-- Email Mock Container -->
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); font-family: 'Helvetica Neue', Arial, sans-serif;">
                    
                    <!-- Simulated Email Client Routing Header Bar -->
                    <div style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 18px; font-size: 11.5px; line-height: 1.6; font-family: monospace;">
                        <div style="display: flex; gap: 8px;">
                            <span style="color: #64748b; width: 60px; font-weight: bold;">From:</span>
                            <span style="color: #0f172a;">YosshitaNeha Fashion Studio &lt;info@yosshitaneha.com&gt;</span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <span style="color: #64748b; width: 60px; font-weight: bold;">To:</span>
                            <span style="color: #0f172a;" id="prev_to_header">Sobha Anil Naik &lt;naiksobhaanil@gmail.com&gt;</span>
                        </div>
                        <div id="prev_cc_row" style="display: none; gap: 8px;">
                            <span style="color: #059669; width: 60px; font-weight: bold;">CC:</span>
                            <span style="color: #059669;" id="prev_cc_header"></span>
                        </div>
                        <div id="prev_bcc_row" style="display: none; gap: 8px; align-items: center;">
                            <span style="color: #7c3aed; width: 60px; font-weight: bold;">BCC:</span>
                            <span style="color: #7c3aed;" id="prev_bcc_header"></span>
                            <span style="font-size: 10px; color: #a855f7; margin-left: 6px; font-family: sans-serif; font-weight: bold; background: #f3e8ff; padding: 1px 6px; border-radius: 4px;">[Client Copy - Hidden from Customer]</span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <span style="color: #64748b; width: 60px; font-weight: bold;">Subject:</span>
                            <span style="color: #0f172a; font-weight: bold;" id="prev_subject_header"><?php echo htmlspecialchars($current_template['subject'] ?? ''); ?></span>
                        </div>
                    </div>

                    <!-- Branded Header -->
                    <div style="padding: 22px 25px; text-align: center; border-bottom: 2px solid #c8a55c; background: #ffffff;">
                        <h1 style="color: #c8a55c; font-size: 20px; letter-spacing: 2px; text-transform: uppercase; margin: 0; font-weight: 700;">YOSSHITANEHA</h1>
                        <span style="color: #646970; font-size: 10px; letter-spacing: 3px; text-transform: uppercase;">LUXURY BRIDAL &amp; FASHION STUDIO</span>
                    </div>

                    <!-- Email Inner Body -->
                    <div style="padding: 25px 30px;">
                        
                        <!-- Status Badge -->
                        <div id="prev_badge_container" style="display: inline-block; background-color: #e6f4ea; color: #137333; border: 1px solid #b7e1cd; font-size: 10px; font-weight: 700; padding: 5px 12px; border-radius: 20px; text-transform: uppercase; margin-bottom: 14px;">
                            <span id="prev_badge_text"><?php echo htmlspecialchars($current_template['badge_text'] ?: 'NOTIFICATION'); ?></span>
                        </div>

                        <!-- Headline -->
                        <h2 id="prev_headline" style="color: #1d2327; margin: 0 0 10px; font-size: 19px; font-weight: 700;">
                            <?php echo htmlspecialchars(str_replace('{ORDER_NUMBER}', 'YNFS_1011', $current_template['headline'])); ?>
                        </h2>

                        <p style="color: #50575e; font-size: 13.5px; line-height: 1.5; margin: 0 0 8px;">
                            Dear <strong>Sobha Anil Naik</strong>,
                        </p>

                        <!-- Subheading message -->
                        <div id="prev_subheading" style="color: #50575e; font-size: 13.5px; line-height: 1.6; margin-bottom: 16px;">
                            <?php echo str_replace('{ORDER_NUMBER}', 'YNFS_1011', $current_template['subheading']); ?>
                        </div>

                        <!-- Courier Tracking Box (Shown for shipped) -->
                        <?php if ($selected_key === 'order_shipped'): ?>
                        <div style="background-color: #e8f0fe; border: 1px solid #aecbfa; border-radius: 8px; padding: 14px; margin: 16px 0;">
                            <div style="color: #1a73e8; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Shipment &amp; Tracking Details</div>
                            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                                <tr>
                                    <td style="color: #5f6368; padding-bottom: 4px;">Logistics Partner:</td>
                                    <td style="color: #1d2327; font-weight: bold; text-align: right; padding-bottom: 4px;">Blue Dart Express</td>
                                </tr>
                                <tr>
                                    <td style="color: #5f6368;">Tracking Number:</td>
                                    <td style="color: #1a73e8; font-weight: bold; font-family: monospace; text-align: right;">BD8492049102IN</td>
                                </tr>
                            </table>
                            <div style="text-align: center; margin-top: 12px;">
                                <span style="display: inline-block; background-color: #1a73e8; color: #ffffff; text-decoration: none; padding: 8px 18px; border-radius: 6px; font-weight: bold; font-size: 12px;">
                                    Track Shipment Live
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($selected_key !== 'customer_welcome'): ?>
                        <!-- Sample Order Details Table -->
                        <div style="background-color: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin: 18px 0;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                <tr>
                                    <td style="color: #646970; padding-bottom: 4px;">Order Number:</td>
                                    <td style="color: #c8a55c; font-weight: bold; text-align: right; padding-bottom: 4px;">YNFS_1011</td>
                                </tr>
                                <tr>
                                    <td style="color: #646970;">Placed On:</td>
                                    <td style="color: #1d2327; text-align: right;"><?php echo date('F j, Y'); ?></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Sample Item Row -->
                        <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; margin-bottom: 16px;">
                            <thead>
                                <tr style="background: #f1f5f9; color: #475569; font-size: 11px; text-transform: uppercase;">
                                    <th style="padding: 8px 10px; text-align: left;">Item</th>
                                    <th style="padding: 8px 10px; text-align: center;">Qty</th>
                                    <th style="padding: 8px 10px; text-align: right;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 10px; color: #1d2327;">
                                        <strong>Raw Silk Embroidered Blouse</strong><br>
                                        <span style="color: #646970; font-size: 10.5px;">SKU: YN-BL-004</span>
                                    </td>
                                    <td style="padding: 10px; text-align: center; font-weight: 600;">1</td>
                                    <td style="padding: 10px; text-align: right; color: #c8a55c; font-weight: bold;">₹300.00</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Breakdown Card -->
                        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                            <tr>
                                <td style="width: 55%; vertical-align: top; padding-right: 10px;">
                                    <div style="background-color: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 11px; line-height: 1.4;">
                                        <div style="color: #c8a55c; font-weight: 700; margin-bottom: 4px; text-transform: uppercase;">Shipping Address</div>
                                        <strong>Sobha Anil Naik</strong><br>
                                        Flat 402, Royal Residency<br>
                                        Mumbai, Maharashtra - 400050
                                    </div>
                                </td>
                                <td style="width: 45%; vertical-align: top;">
                                    <div style="background-color: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 11.5px;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                                            <span>Subtotal:</span>
                                            <span>₹300.00</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                            <span>Shipping:</span>
                                            <span>₹50.00</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-weight: 700; border-top: 1px dashed #cbd5e1; padding-top: 4px; color: #c8a55c;">
                                            <span>Total Paid:</span>
                                            <span>₹350.00</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        <?php endif; ?>

                        <!-- Footer Note Preview -->
                        <div id="prev_footer_note" style="color: #64748b; font-size: 12px; line-height: 1.5; margin-top: 18px; text-align: center; font-style: italic;">
                            <?php echo htmlspecialchars($current_template['footer_note'] ?? ''); ?>
                        </div>

                        <!-- CTA Button -->
                        <div style="text-align: center; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 18px;">
                            <span style="display: inline-block; background-color: #c8a55c; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 20px; font-weight: bold; font-size: 12px;">
                                <?php echo $selected_key === 'customer_welcome' ? 'Explore My Account' : 'View Order Online'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div style="background-color: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 16px; text-align: center; color: #646970; font-size: 10.5px;">
                        © <?php echo date('Y'); ?> YosshitaNeha Fashion Studio. All rights reserved.<br>
                        Need assistance? Contact our concierge at info@yosshitaneha.com
                    </div>

                </div>
            </div>
        </div>

    </div>

</div>

<style>
/* Slider switch styling */
input:checked + .slider {
    background-color: #10b981 !important;
}
input:checked + .slider:before {
    transform: translateX(20px);
}
.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .2s;
    border-radius: 50%;
}
</style>

<script>
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

// Render dynamic real-time validation pills under recipient inputs
function renderEmailBadges(inputId, containerId) {
    const input = document.getElementById(inputId);
    const container = document.getElementById(containerId);
    if (!input || !container) return;

    const raw = input.value.trim();
    if (!raw) {
        container.innerHTML = '';
        return;
    }

    const emails = raw.split(/[,;\s]+/).filter(e => e.trim().length > 0);
    let html = '';

    emails.forEach(email => {
        email = email.trim();
        const valid = isValidEmail(email);
        const safeEmail = email.replace(/'/g, "\\'");
        if (valid) {
            html += `<span style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-size: 11px; padding: 2px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500;">
                <i class="fa-solid fa-check" style="font-size: 10px;"></i> ${escapeHtml(email)}
                <button type="button" onclick="removeEmailTag('${inputId}', '${safeEmail}')" style="background: none; border: none; padding: 0; color: #059669; cursor: pointer; font-size: 14px; line-height: 1; margin-left: 2px;" title="Remove">&times;</button>
            </span>`;
        } else {
            html += `<span style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-size: 11px; padding: 2px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 10px;"></i> ${escapeHtml(email)}
                <button type="button" onclick="removeEmailTag('${inputId}', '${safeEmail}')" style="background: none; border: none; padding: 0; color: #dc2626; cursor: pointer; font-size: 14px; line-height: 1; margin-left: 2px;" title="Remove">&times;</button>
            </span>`;
        }
    });

    container.innerHTML = html;
}

function removeEmailTag(inputId, emailToRemove) {
    const input = document.getElementById(inputId);
    if (!input) return;

    let emails = input.value.split(/[,;\s]+/).map(e => e.trim()).filter(e => e.length > 0 && e.toLowerCase() !== emailToRemove.toLowerCase());
    input.value = emails.join(', ');
    updatePreview();
    renderAllEmailBadges();
}

function renderAllEmailBadges() {
    renderEmailBadges('tpl_extra_to', 'badges_extra_to');
    renderEmailBadges('tpl_cc', 'badges_cc');
    renderEmailBadges('tpl_bcc', 'badges_bcc');
}

function updatePreview() {
    const badge = document.getElementById('tpl_badge_text') ? document.getElementById('tpl_badge_text').value : '';
    const headline = document.getElementById('tpl_headline') ? document.getElementById('tpl_headline').value : '';
    const subheading = document.getElementById('tpl_subheading') ? document.getElementById('tpl_subheading').value : '';
    const footerNote = document.getElementById('tpl_footer_note') ? document.getElementById('tpl_footer_note').value : '';
    const subject = document.getElementById('tpl_subject') ? document.getElementById('tpl_subject').value : '';
    const extraTo = document.getElementById('tpl_extra_to') ? document.getElementById('tpl_extra_to').value.trim() : '';
    const cc = document.getElementById('tpl_cc') ? document.getElementById('tpl_cc').value.trim() : '';
    const bcc = document.getElementById('tpl_bcc') ? document.getElementById('tpl_bcc').value.trim() : '';

    const prevBadge = document.getElementById('prev_badge_text');
    const prevHeadline = document.getElementById('prev_headline');
    const prevSub = document.getElementById('prev_subheading');
    const prevFoot = document.getElementById('prev_footer_note');
    const prevSubject = document.getElementById('prev_subject_header');
    const prevTo = document.getElementById('prev_to_header');
    const prevCcRow = document.getElementById('prev_cc_row');
    const prevCcHeader = document.getElementById('prev_cc_header');
    const prevBccRow = document.getElementById('prev_bcc_row');
    const prevBccHeader = document.getElementById('prev_bcc_header');

    if (prevBadge) prevBadge.textContent = badge || 'NOTIFICATION';
    if (prevHeadline) prevHeadline.textContent = headline.replace('{ORDER_NUMBER}', 'YNFS_1011').replace('{CUSTOMER_NAME}', 'Sobha Anil Naik');
    if (prevSub) prevSub.innerHTML = subheading.replace('{ORDER_NUMBER}', 'YNFS_1011').replace('{CUSTOMER_NAME}', 'Sobha Anil Naik');
    if (prevFoot) prevFoot.textContent = footerNote;
    if (prevSubject) prevSubject.textContent = subject.replace('{ORDER_NUMBER}', 'YNFS_1011').replace('{CUSTOMER_NAME}', 'Sobha Anil Naik');

    if (prevTo) {
        const extraToList = extraTo ? extraTo.split(/[,;\s]+/).filter(e => e.trim().length > 0) : [];
        prevTo.textContent = 'Sobha Anil Naik <naiksobhaanil@gmail.com>' + (extraToList.length ? ', ' + extraToList.join(', ') : '');
    }

    if (prevCcRow && prevCcHeader) {
        if (cc) {
            const ccList = cc.split(/[,;\s]+/).filter(e => e.trim().length > 0);
            prevCcHeader.textContent = ccList.join(', ');
            prevCcRow.style.display = 'flex';
        } else {
            prevCcRow.style.display = 'none';
        }
    }

    if (prevBccRow && prevBccHeader) {
        if (bcc) {
            const bccList = bcc.split(/[,;\s]+/).filter(e => e.trim().length > 0);
            prevBccHeader.textContent = bccList.join(', ');
            prevBccRow.style.display = 'flex';
        } else {
            prevBccRow.style.display = 'none';
        }
    }
}

// Run immediately on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
    renderAllEmailBadges();
});

function insertTag(tag) {
    const subArea = document.getElementById('tpl_subheading');
    if (!subArea) return;
    
    // Copy to clipboard
    navigator.clipboard.writeText(tag).then(() => {
        // Also insert at cursor if possible
        const start = subArea.selectionStart;
        const end = subArea.selectionEnd;
        const text = subArea.value;
        subArea.value = text.substring(0, start) + tag + text.substring(end);
        subArea.focus();
        subArea.selectionStart = subArea.selectionEnd = start + tag.length;
        updatePreview();
    }).catch(() => {
        subArea.value += ' ' + tag;
        updatePreview();
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
