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
$whatsapp_number = $settings['whatsapp_number'] ?? '919321864422';
$whatsapp_product_message = $settings['whatsapp_product_message'] ?? 'Hello YosshitaNeha Fashion Studio, I am interested in: "{product_name}" (SKU: {sku}, Price: {price}). {stock_status}. Product Link: {product_url}';
$whatsapp_floating_message = $settings['whatsapp_floating_message'] ?? 'Hi YosshitaNeha Studio! I would like to inquire about your collection.';
$whatsapp_btn_label = $settings['whatsapp_btn_label'] ?? 'Order on WhatsApp';

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
    
    $raw_number = trim($_POST['whatsapp_number'] ?? '919321864422');
    $clean_number = preg_replace('/[^0-9]/', '', $raw_number);
    if (empty($clean_number)) {
        $clean_number = '919321864422';
    }

    $prod_msg = trim($_POST['whatsapp_product_message'] ?? '');
    if ($prod_msg === '') {
        $prod_msg = 'Hello YosshitaNeha Fashion Studio, I am interested in: "{product_name}" (SKU: {sku}, Price: {price}). {stock_status}. Product Link: {product_url}';
    }

    $float_msg = trim($_POST['whatsapp_floating_message'] ?? '');
    if ($float_msg === '') {
        $float_msg = 'Hi YosshitaNeha Studio! I would like to inquire about your collection.';
    }

    $btn_label = trim($_POST['whatsapp_btn_label'] ?? 'Order on WhatsApp');
    if ($btn_label === '') {
        $btn_label = 'Order on WhatsApp';
    }
    
    $to_save = [
        'theme_mode' => $new_theme,
        'whatsapp_number' => $clean_number,
        'whatsapp_product_message' => $prod_msg,
        'whatsapp_floating_message' => $float_msg,
        'whatsapp_btn_label' => $btn_label,
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

        log_activity($pdo, 'update_general_settings', 'settings', 0, "Updated General & WhatsApp Settings");
        $_SESSION['flash_success'] = 'General & WhatsApp Settings saved successfully!';
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

    <!-- WhatsApp Integration & Auto-Formatting Settings Postbox -->
    <div class="postbox" style="max-width: 850px; margin-bottom: 24px; border-top: 3px solid #25D366;">
        <div class="postbox-header" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 20px;">
            <h2 style="margin: 0; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                <i class="fa-brands fa-whatsapp" style="color: #25D366; font-size: 20px;"></i> WhatsApp Customisation & Auto-Formatting
            </h2>
            <span style="background: #e6f9ed; color: #166534; border: 1px solid #bbf7d0; font-weight: 700; font-size: 11px; padding: 3px 10px; border-radius: 9999px; text-transform: uppercase;">
                Storefront Live
            </span>
        </div>
        <div class="postbox-body" style="padding: 20px;">
            <p style="color: #64748b; font-size: 13px; margin-top: 0; margin-bottom: 20px; line-height: 1.5;">
                Configure the WhatsApp business phone number and custom auto-formatted messages for both product page inquiries and the global floating widget.
            </p>

            <!-- 1. WhatsApp Number Selection -->
            <div style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
                <label style="display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; color: #0f172a;">
                    <i class="fa-solid fa-phone" style="color: #25D366; margin-right: 6px;"></i> WhatsApp Contact Number Selection
                </label>
                <p style="font-size: 12px; color: #64748b; margin: 0 0 12px 0;">
                    Choose an official studio line or enter a custom business number (with country code):
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 14px;">
                    <!-- Option 1: Primary 93218 64422 -->
                    <label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 2px solid <?php echo ($whatsapp_number === '919321864422' || empty($whatsapp_number)) ? '#25D366' : '#e2e8f0'; ?>; border-radius: 10px; background: #fff; cursor: pointer; transition: all 0.2s;">
                        <input type="radio" name="whatsapp_preset" value="919321864422" <?php echo ($whatsapp_number === '919321864422' || empty($whatsapp_number)) ? 'checked' : ''; ?> style="margin-top: 3px;" onchange="setWhatsappNumber('919321864422')">
                        <div>
                            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">+91 93218 64422</div>
                            <div style="font-size: 11px; color: #166534; font-weight: 600; margin-top: 2px;">Primary Designer Studio</div>
                            <div style="font-size: 11px; color: #64748b;">Consultation & Bespoke Couture</div>
                        </div>
                    </label>

                    <!-- Option 2: Alternate 98205 09930 -->
                    <label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 2px solid <?php echo ($whatsapp_number === '919820509930') ? '#25D366' : '#e2e8f0'; ?>; border-radius: 10px; background: #fff; cursor: pointer; transition: all 0.2s;">
                        <input type="radio" name="whatsapp_preset" value="919820509930" <?php echo ($whatsapp_number === '919820509930') ? 'checked' : ''; ?> style="margin-top: 3px;" onchange="setWhatsappNumber('919820509930')">
                        <div>
                            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">+91 98205 09930</div>
                            <div style="font-size: 11px; color: #1e40af; font-weight: 600; margin-top: 2px;">Sales & Customer Line</div>
                            <div style="font-size: 11px; color: #64748b;">Direct Orders & Restock Dispatch</div>
                        </div>
                    </label>

                    <!-- Option 3: Custom -->
                    <label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 2px solid <?php echo (!in_array($whatsapp_number, ['919321864422', '919820509930']) && !empty($whatsapp_number)) ? '#25D366' : '#e2e8f0'; ?>; border-radius: 10px; background: #fff; cursor: pointer; transition: all 0.2s;">
                        <input type="radio" name="whatsapp_preset" value="custom" <?php echo (!in_array($whatsapp_number, ['919321864422', '919820509930']) && !empty($whatsapp_number)) ? 'checked' : ''; ?> style="margin-top: 3px;" onchange="focusCustomNumber()">
                        <div>
                            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Custom Phone Number</div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Specify international business number</div>
                        </div>
                    </label>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; max-width: 420px;">
                    <span style="font-family: monospace; font-weight: 700; color: #334155; background: #f1f5f9; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">+</span>
                    <input type="text" name="whatsapp_number" id="whatsapp_number" value="<?php echo htmlspecialchars($whatsapp_number); ?>" placeholder="e.g. 919321864422" style="flex: 1; font-family: monospace; font-size: 13px; font-weight: 600;" class="regular-text" required>
                </div>
                <small style="display: block; color: #64748b; font-size: 11px; margin-top: 6px;">Digits only with country code (e.g., India = 91 followed by 10-digit number).</small>
            </div>

            <!-- 2. Product Page Auto-Formatted Message -->
            <div style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label style="font-weight: 700; font-size: 13px; color: #0f172a;">
                        <i class="fa-solid fa-comment-dots" style="color: #25D366; margin-right: 6px;"></i> Product Page Auto-Formatted Message Template
                    </label>
                    <button type="button" class="button button-secondary button-small" onclick="resetProductMsg()" style="font-size: 11px;">
                        <i class="fa-solid fa-rotate-left"></i> Reset Default
                    </button>
                </div>
                <p style="font-size: 12px; color: #64748b; margin: 0 0 10px 0;">
                    When users tap the WhatsApp button on any product page, this message opens pre-filled in their WhatsApp app. Click any tag to insert dynamic product details:
                </p>

                <!-- Variable Insertion Buttons -->
                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px;">
                    <button type="button" class="button button-small" onclick="insertTag('{product_name}')" style="font-family: monospace; font-size: 11px; background: #f8fafc;">+ {product_name}</button>
                    <button type="button" class="button button-small" onclick="insertTag('{sku}')" style="font-family: monospace; font-size: 11px; background: #f8fafc;">+ {sku}</button>
                    <button type="button" class="button button-small" onclick="insertTag('{price}')" style="font-family: monospace; font-size: 11px; background: #f8fafc;">+ {price}</button>
                    <button type="button" class="button button-small" onclick="insertTag('{stock_status}')" style="font-family: monospace; font-size: 11px; background: #f8fafc;">+ {stock_status}</button>
                    <button type="button" class="button button-small" onclick="insertTag('{product_url}')" style="font-family: monospace; font-size: 11px; background: #f8fafc;">+ {product_url}</button>
                </div>

                <textarea name="whatsapp_product_message" id="whatsapp_product_message" rows="4" style="width: 100%; font-family: inherit; font-size: 13px; line-height: 1.5; border-radius: 8px; padding: 10px; border: 1px solid #cbd5e1; box-sizing: border-box;"><?php echo htmlspecialchars($whatsapp_product_message); ?></textarea>
                
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-top: 10px;">
                    <span style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 4px;">Live Preview Example:</span>
                    <p style="margin: 0; font-size: 12px; color: #0f172a; font-style: italic;" id="preview_sample">
                        Hello YosshitaNeha Fashion Studio, I am interested in: "Antique Gold and Off-white Brocade long Blouse" (SKU: YNLB005ON, Price: ₹1,710). Status: Sold Out / Custom Stitching Request. Product Link: https://yosshitaneha.com/product/antique-gold-blouse
                    </p>
                </div>
            </div>

            <!-- 3. Floating Bottom-Right Widget Message -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; color: #0f172a;">
                    <i class="fa-solid fa-message" style="color: #0284c7; margin-right: 6px;"></i> Floating Bottom-Right Widget Message
                </label>
                <p style="font-size: 12px; color: #64748b; margin: 0 0 8px 0;">
                    Default greeting opened when visitors click the floating WhatsApp button at the bottom-right corner of any page:
                </p>
                <input type="text" name="whatsapp_floating_message" id="whatsapp_floating_message" value="<?php echo htmlspecialchars($whatsapp_floating_message); ?>" style="width: 100%;" class="regular-text" placeholder="Hi YosshitaNeha Studio! I would like to inquire about your collection.">
            </div>

            <!-- 4. Product Page Button Text -->
            <div style="margin-bottom: 10px;">
                <label style="display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; color: #0f172a;">
                    Product Page WhatsApp Button Label (In Stock)
                </label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="text" name="whatsapp_btn_label" id="whatsapp_btn_label" value="<?php echo htmlspecialchars($whatsapp_btn_label); ?>" style="max-width: 260px;" class="regular-text" placeholder="Order on WhatsApp">
                    <span style="font-size: 12px; color: #64748b;">(Displays the green WhatsApp icon with this text on in-stock product pages)</span>
                </div>
            </div>

        </div>
    </div>

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

            <button type="submit" class="button button-primary" style="padding: 8px 28px; font-weight: 700; font-size: 13px;">
                <i class="fa-solid fa-floppy-disk"></i> Save All Settings
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

// Helper functions for WhatsApp settings
function setWhatsappNumber(num) {
    document.getElementById('whatsapp_number').value = num;
}

function focusCustomNumber() {
    var input = document.getElementById('whatsapp_number');
    input.focus();
    input.select();
}

function insertTag(tag) {
    var textarea = document.getElementById('whatsapp_product_message');
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var text = textarea.value;
    textarea.value = text.substring(0, start) + tag + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
    textarea.focus();
}

function resetProductMsg() {
    document.getElementById('whatsapp_product_message').value = 'Hello YosshitaNeha Fashion Studio, I am interested in: "{product_name}" (SKU: {sku}, Price: {price}). {stock_status}. Product Link: {product_url}';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
