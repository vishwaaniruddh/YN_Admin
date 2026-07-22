<?php
// admin/mail-settings.php
$page_title = "Mail & SMTP Settings";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Fetch current mail settings from site_settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    echo "<div class='notice notice-error'><p>Database error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
}

// Default values
$mail_mailer = 'smtp';
$smtp_host = $settings['smtp_host'] ?? 'smtp.gmail.com';
$smtp_port = $settings['smtp_port'] ?? '587';
$smtp_encryption = $settings['smtp_encryption'] ?? 'tls';
$smtp_username = $settings['smtp_username'] ?? '';
$smtp_password = $settings['smtp_password'] ?? '';
$smtp_from_email = $settings['smtp_from_email'] ?? 'info@yosshitaneha.com';
$smtp_from_name = $settings['smtp_from_name'] ?? 'YosshitaNeha Fashion Studio';
$smtp_enable_order_emails = $settings['smtp_enable_order_emails'] ?? '1';
$smtp_enable_welcome_emails = $settings['smtp_enable_welcome_emails'] ?? '1';

$success_msg = '';
$error_msg = '';
$test_email_msg = '';
$test_email_status = '';

if (!empty($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error_msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
if (!empty($_SESSION['flash_test_msg'])) {
    $test_email_msg = $_SESSION['flash_test_msg'];
    $test_email_status = $_SESSION['flash_test_status'] ?? '';
    unset($_SESSION['flash_test_msg'], $_SESSION['flash_test_status']);
}

// Handle Form Actions (Save Settings or Send Test Email)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_settings';

    if ($action === 'save_settings') {
        $new_mailer = 'smtp';
        $new_host = trim($_POST['smtp_host'] ?? '');
        $new_port = trim($_POST['smtp_port'] ?? '587');
        $new_encryption = trim($_POST['smtp_encryption'] ?? 'tls');
        $new_username = trim($_POST['smtp_username'] ?? '');
        $new_password = trim($_POST['smtp_password'] ?? '');
        $new_from_email = trim($_POST['smtp_from_email'] ?? '');
        $new_from_name = trim($_POST['smtp_from_name'] ?? '');
        $new_order_emails = isset($_POST['smtp_enable_order_emails']) ? '1' : '0';
        $new_welcome_emails = isset($_POST['smtp_enable_welcome_emails']) ? '1' : '0';

        $to_save = [
            'mail_mailer' => $new_mailer,
            'smtp_host' => $new_host,
            'smtp_port' => $new_port,
            'smtp_encryption' => $new_encryption,
            'smtp_username' => $new_username,
            'smtp_password' => $new_password,
            'smtp_from_email' => $new_from_email,
            'smtp_from_name' => $new_from_name,
            'smtp_enable_order_emails' => $new_order_emails,
            'smtp_enable_welcome_emails' => $new_welcome_emails,
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

            log_activity($pdo, 'update_mail_settings', 'settings', 0, 'Updated SMTP Mail Configuration Settings');
            $_SESSION['flash_success'] = 'Mail & SMTP settings updated successfully!';
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Failed to save mail settings: ' . $e->getMessage();
        }
        header("Location: mail-settings.php");
        exit();
    } elseif ($action === 'send_test_email') {
        $test_recipient = trim($_POST['test_recipient_email'] ?? '');
        if (empty($test_recipient) || !filter_var($test_recipient, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_test_status'] = 'error';
            $_SESSION['flash_test_msg'] = 'Please enter a valid recipient email address.';
        } else {
            $subject = "Test Email from " . ($smtp_from_name ?: 'YosshitaNeha Studio');
            $htmlBody = '
            <div style="font-family: Arial, sans-serif; background: #0f0f0f; color: #fff; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; border: 1px solid #333;">
                <h2 style="color: #d4af37; border-bottom: 1px solid #333; padding-bottom: 10px; margin-top: 0;">SMTP Test Connection Successful</h2>
                <p>Hello,</p>
                <p>This email confirms that your <strong>YosshitaNeha Fashion Studio</strong> SMTP email settings are working correctly.</p>
                <div style="background: #181818; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #d4af37;">
                    <strong>Configuration Used:</strong><br>
                    • SMTP Host: <code>' . htmlspecialchars($smtp_host) . '</code><br>
                    • Port: <code>' . htmlspecialchars($smtp_port) . '</code><br>
                    • Encryption: <code>' . strtoupper(htmlspecialchars($smtp_encryption)) . '</code><br>
                    • Sender Email: <code>' . htmlspecialchars($smtp_from_email) . '</code>
                </div>
                <p style="color: #888; font-size: 12px; margin-bottom: 0;">Sent on ' . date('Y-m-d H:i:s') . '</p>
            </div>';

            $result = send_system_email($pdo, $test_recipient, $subject, $htmlBody);
            if ($result['success']) {
                $_SESSION['flash_test_status'] = 'success';
                $_SESSION['flash_test_msg'] = "Test email sent successfully to <strong>" . htmlspecialchars($test_recipient) . "</strong>!";
            } else {
                $_SESSION['flash_test_status'] = 'error';
                $_SESSION['flash_test_msg'] = "SMTP Error: " . htmlspecialchars($result['error']);
            }
        }
        header("Location: mail-settings.php");
        exit();
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap-header">
    <h1><i class="fa-solid fa-envelope" style="color: var(--wp-blue);"></i> Mail &amp; SMTP Configuration</h1>
    <a href="settings.php" class="button"><i class="fa-solid fa-sliders"></i> General Settings</a>
</div>

<?php if ($success_msg): ?>
<div class="notice notice-success auto-dismiss">
    <p><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success_msg); ?></p>
</div>
<?php endif; ?>

<?php if ($error_msg): ?>
<div class="notice notice-error auto-dismiss">
    <p><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error_msg); ?></p>
</div>
<?php endif; ?>

<!-- WordPress 2-Column Layout -->
<div class="wp-editor-columns" style="display: flex; gap: 20px; flex-wrap: wrap;">
    
    <!-- Main Left Column: Configuration Form -->
    <div class="main-column" style="flex: 1; min-width: 500px;">
        <form method="POST" action="mail-settings.php">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="mail_mailer" value="smtp">

            <!-- SMTP Server Settings Postbox -->
            <div class="postbox" style="margin-bottom: 20px;">
                <div class="postbox-header">
                    <h2><i class="fa-solid fa-server" style="color: var(--wp-blue);"></i> Outgoing Mail Server (SMTP)</h2>
                </div>
                <div class="postbox-body" style="padding: 20px;">
                    
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <!-- SMTP Host -->
                        <div class="form-group">
                            <label for="smtp_host">SMTP Host *</label>
                            <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtp_host); ?>" placeholder="e.g. smtp.gmail.com or mail.yourdomain.com" required class="form-control">
                        </div>

                        <!-- SMTP Port -->
                        <div class="form-group">
                            <label for="smtp_port">SMTP Port *</label>
                            <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($smtp_port); ?>" placeholder="587" required class="form-control">
                        </div>
                    </div>

                    <!-- Encryption Protocol -->
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="smtp_encryption">Encryption Protocol</label>
                        <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                            <option value="tls" <?php echo strtolower($smtp_encryption) === 'tls' ? 'selected' : ''; ?>>TLS (Transport Layer Security - Port 587)</option>
                            <option value="ssl" <?php echo strtolower($smtp_encryption) === 'ssl' ? 'selected' : ''; ?>>SSL (Secure Sockets Layer - Port 465)</option>
                            <option value="none" <?php echo strtolower($smtp_encryption) === 'none' ? 'selected' : ''; ?>>None (Unencrypted - Port 25)</option>
                        </select>
                    </div>

                    <!-- Username & Password -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="smtp_username">SMTP Username / Email</label>
                            <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($smtp_username); ?>" placeholder="user@domain.com" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="smtp_password_input">SMTP Password</label>
                            <div style="position: relative;">
                                <input type="password" id="smtp_password_input" name="smtp_password" value="<?php echo htmlspecialchars($smtp_password); ?>" placeholder="••••••••••••" class="form-control" style="padding-right: 40px;">
                                <button type="button" onclick="togglePasswordVisibility()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #646970; cursor: pointer;">
                                    <i id="pass_toggle_icon" class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Sender Identity Postbox -->
            <div class="postbox" style="margin-bottom: 20px;">
                <div class="postbox-header">
                    <h2><i class="fa-solid fa-id-card" style="color: var(--wp-blue);"></i> Sender Information &amp; Notifications</h2>
                </div>
                <div class="postbox-body" style="padding: 20px;">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div class="form-group">
                            <label for="smtp_from_email">From Email Address *</label>
                            <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($smtp_from_email); ?>" required placeholder="info@yosshitaneha.com" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="smtp_from_name">From Sender Name *</label>
                            <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($smtp_from_name); ?>" required placeholder="YosshitaNeha Fashion Studio" class="form-control">
                        </div>
                    </div>

                    <div style="background: #f8f9fa; border: 1px solid #e2e8f0; padding: 14px; border-radius: 6px; display: flex; flex-direction: column; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1d2327; font-size: 13px;">
                            <input type="checkbox" name="smtp_enable_order_emails" value="1" <?php echo $smtp_enable_order_emails === '1' ? 'checked' : ''; ?>>
                            <span>Send automated order confirmation emails to customers after checkout</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1d2327; font-size: 13px;">
                            <input type="checkbox" name="smtp_enable_welcome_emails" value="1" <?php echo $smtp_enable_welcome_emails === '1' ? 'checked' : ''; ?>>
                            <span>Send welcome notification emails upon new customer account registration</span>
                        </label>
                    </div>

                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="button button-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Mail Settings
            </button>
        </form>
    </div>

    <!-- Right Side Column: Interactive Test Connection -->
    <div class="side-column" style="flex: 0 0 320px;">
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-paper-plane" style="color: var(--wp-blue);"></i> Test SMTP Connection</h2>
            </div>
            <div class="postbox-body" style="padding: 16px;">
                <p style="color: #646970; font-size: 12px; line-height: 1.4; margin-top: 0; margin-bottom: 14px;">
                    Send a test email using your current SMTP configuration to verify server connection and authentication.
                </p>

                <?php if ($test_email_msg): ?>
                    <div class="notice notice-<?php echo $test_email_status === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 14px; padding: 8px 12px;">
                        <p style="margin: 0; font-size: 12px;"><?php echo $test_email_msg; ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="mail-settings.php">
                    <input type="hidden" name="action" value="send_test_email">
                    
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="test_recipient_email" style="font-size: 12px;">Recipient Email Address</label>
                        <input type="email" id="test_recipient_email" name="test_recipient_email" placeholder="admin@example.com" required class="form-control">
                    </div>

                    <button type="submit" class="button button-secondary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px;">
                        <i class="fa-solid fa-paper-plane"></i> Send Test Email
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function togglePasswordVisibility() {
    const pwdInput = document.getElementById('smtp_password_input');
    const icon = document.getElementById('pass_toggle_icon');
    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        pwdInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
