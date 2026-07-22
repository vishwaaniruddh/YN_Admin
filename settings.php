<?php
// admin/settings.php
$page_title = "Settings";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Fetch current settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {}

$theme_mode = $settings['theme_mode'] ?? 'dark';

$success_msg = '';
$error_msg = '';

if (!empty($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error_msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_theme = isset($_POST['theme_mode']) ? trim($_POST['theme_mode']) : 'dark';
    if (!in_array($new_theme, ['dark', 'light'])) {
        $new_theme = 'dark';
    }
    
    $to_save = [
        'theme_mode' => $new_theme,
    ];

    try {
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($to_save as $key => $val) {
            $stmt->execute([$key, $val]);
        }
        
        // Also sync to u464193275_srishrinjewels database if connected locally
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

        log_activity($pdo, 'update_general_settings', 'settings', 0, "Updated General Settings (Theme: $new_theme)");
        $_SESSION['flash_success'] = 'General Settings saved successfully!';
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = 'Failed to save settings: ' . $e->getMessage();
    }

    header("Location: settings.php");
    exit();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap-header">
    <h1><i class="fa-solid fa-sliders" style="color: var(--wp-blue);"></i> General Settings</h1>
</div>

<?php if ($success_msg): ?>
<div class="notice notice-success auto-dismiss">
    <p><i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?></p>
</div>
<?php endif; ?>

<?php if ($error_msg): ?>
<div class="notice notice-error auto-dismiss">
    <p><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_msg; ?></p>
</div>
<?php endif; ?>

<form method="POST" action="settings.php">

    <!-- Theme Settings Postbox -->
    <div class="postbox" style="max-width: 850px; margin-bottom: 24px;">
        <div class="postbox-header">
            <h2>
                <i class="fa-solid fa-palette" style="color: var(--wp-blue);"></i> Website Theme Appearance
            </h2>
        </div>
        <div class="postbox-body" style="padding: 20px;">
            <p style="color: #646970; font-size: 13px; margin-top: 0; margin-bottom: 20px;">
                Select the global aesthetic theme mode for the storefront web application.
            </p>

            <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 20px;">
                <!-- Dark Theme Card -->
                <label for="theme_dark" style="cursor: pointer; flex: 1; min-width: 250px;">
                    <input type="radio" name="theme_mode" id="theme_dark" value="dark" <?php echo $theme_mode === 'dark' ? 'checked' : ''; ?> style="display: none;">
                    <div class="theme-card" id="card-dark" style="
                        border: 2px solid <?php echo $theme_mode === 'dark' ? '#c8a55c' : '#e2e8f0'; ?>;
                        border-radius: 12px;
                        padding: 24px;
                        text-align: center;
                        transition: all 0.3s ease;
                        background: #0a0a0a;
                    ">
                        <div style="width: 100%; height: 100px; background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #111 100%); border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                            <i class="fa-solid fa-moon" style="font-size: 32px; color: #c8a55c;"></i>
                        </div>
                        <h3 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 600; color: #f5f0e8;">Dark Mode</h3>
                        <p style="margin: 0; font-size: 13px; color: #888;">Luxury dark aesthetic with gold accents</p>
                    </div>
                </label>

                <!-- Light Theme Card -->
                <label for="theme_light" style="cursor: pointer; flex: 1; min-width: 250px;">
                    <input type="radio" name="theme_mode" id="theme_light" value="light" <?php echo $theme_mode === 'light' ? 'checked' : ''; ?> style="display: none;">
                    <div class="theme-card" id="card-light" style="
                        border: 2px solid <?php echo $theme_mode === 'light' ? '#c8a55c' : '#e2e8f0'; ?>;
                        border-radius: 12px;
                        padding: 24px;
                        text-align: center;
                        transition: all 0.3s ease;
                        background: #f8f6f1;
                    ">
                        <div style="width: 100%; height: 100px; background: linear-gradient(135deg, #ffffff 0%, #f5f0e8 50%, #ede8dc 100%); border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                            <i class="fa-solid fa-sun" style="font-size: 32px; color: #a68b4b;"></i>
                        </div>
                        <h3 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 600; color: #1a1a1a;">Light Mode</h3>
                        <p style="margin: 0; font-size: 13px; color: #666;">Clean, bright appearance with warm tones</p>
                    </div>
                </label>
            </div>

            <button type="submit" class="button button-primary" style="padding: 6px 24px; font-weight: 600;">
                <i class="fa-solid fa-floppy-disk"></i> Save General Settings
            </button>
        </div>
    </div>

</form>

<script>
// Interactive theme card selection
document.querySelectorAll('input[name="theme_mode"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.theme-card').forEach(card => {
            card.style.borderColor = '#e2e8f0';
        });
        if (this.value === 'dark') {
            document.getElementById('card-dark').style.borderColor = '#c8a55c';
        } else {
            document.getElementById('card-light').style.borderColor = '#c8a55c';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
