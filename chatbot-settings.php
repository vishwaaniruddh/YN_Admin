<?php
// admin/chatbot-settings.php
// Database-driven AI Chatbot Configuration & Knowledge Base Management
$page_title = "Chatbot Knowledge & Settings";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Ensure user has admin privileges
if (!current_user_can('manage_settings')) {
    die("Access denied. You do not have permission to manage settings.");
}

$message = '';
$message_type = 'success';

// Handle Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settingsToSave = [
            'chatbot_enabled' => isset($_POST['chatbot_enabled']) ? '1' : '0',
            'chatbot_gemini_api_key' => trim($_POST['chatbot_gemini_api_key'] ?? ''),
            'chatbot_welcome_message' => trim($_POST['chatbot_welcome_message'] ?? ''),
            'chatbot_phone_1' => trim($_POST['chatbot_phone_1'] ?? ''),
            'chatbot_phone_2' => trim($_POST['chatbot_phone_2'] ?? ''),
            'chatbot_email' => trim($_POST['chatbot_email'] ?? ''),
            'chatbot_address' => trim($_POST['chatbot_address'] ?? ''),
            'chatbot_hours' => trim($_POST['chatbot_hours'] ?? ''),
            'chatbot_stitching_price' => trim($_POST['chatbot_stitching_price'] ?? '1500'),
            'chatbot_custom_info' => trim($_POST['chatbot_custom_info'] ?? ''),
            'chatbot_shipping_info' => trim($_POST['chatbot_shipping_info'] ?? ''),
            'chatbot_return_info' => trim($_POST['chatbot_return_info'] ?? ''),
            'chatbot_system_prompt' => trim($_POST['chatbot_system_prompt'] ?? '')
        ];

        $stmtSave = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

        foreach ($settingsToSave as $k => $v) {
            $stmtSave->execute([$k, $v]);
        }

        $message = "Chatbot knowledge base & settings updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error saving settings: " . $e->getMessage();
        $message_type = "error";
    }
}

// Load Current Settings from Database
$settings = [];
$stmtFetch = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'chatbot_%'");
while ($row = $stmtFetch->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$enabled = ($settings['chatbot_enabled'] ?? '1') === '1';
$apiKey = $settings['chatbot_gemini_api_key'] ?? '';
$welcomeMsg = $settings['chatbot_welcome_message'] ?? "Namaste! ✨ I am your YosshitaNeha Personal Assistant & Stylist. How can I help you today?";
$phone1 = $settings['chatbot_phone_1'] ?? '+91 9324243011';
$phone2 = $settings['chatbot_phone_2'] ?? '+91 7506628663';
$email = $settings['chatbot_email'] ?? 'yosshita.neha@gmail.com';
$address = $settings['chatbot_address'] ?? '104, Shyamkamal Building B/1, Agarwal Market, Near Deenanath Mangeshkar Natya Mandir, Vile Parle East, Mumbai - 400057';
$hours = $settings['chatbot_hours'] ?? 'Mon - Sat: 11:00 AM - 7:30 PM (IST)';
$stitchingPrice = $settings['chatbot_stitching_price'] ?? '1500';
$customInfo = $settings['chatbot_custom_info'] ?? 'Blouse stitching starts from ₹1,500. Custom bridal lehengas & hand embroidery charges depend on fabric, zari work, and pattern complexity.';
$shippingInfo = $settings['chatbot_shipping_info'] ?? 'Domestic shipping: 3-7 business days. Free shipping on orders above ₹5,000. International shipping: 7-12 business days worldwide.';
$returnInfo = $settings['chatbot_return_info'] ?? 'Readymade items eligible for 7-day exchange. Custom tailored outfits are non-refundable but complimentary alterations are provided.';
$systemPrompt = $settings['chatbot_system_prompt'] ?? "You are an expert luxury Indian fashion stylist for YosshitaNeha Fashion Studio. Specialising in handcrafted designer blouses, heritage jewellery, and bespoke bridal customisation. Be helpful, concise, and polite.";

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap-header">
    <h1><i class="fa-solid fa-robot" style="color: var(--wp-blue);"></i> AI Chatbot Knowledge Base &amp; Settings</h1>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="chatbot-settings.php">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; max-width: 1200px;">
        
        <!-- Column 1: Controls & Contact Data -->
        <div>
            <!-- Enable/Disable Switch -->
            <div class="postbox" style="margin-bottom: 24px;">
                <div class="postbox-header">
                    <h2><i class="fa-solid fa-power-off" style="color: var(--wp-blue);"></i> Chatbot Visibility Switch</h2>
                </div>
                <div class="postbox-body" style="padding: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0 0 4px 0; font-size: 15px; color: #0f172a;">Enable Website Chatbot</h3>
                            <p style="margin: 0; color: #64748b; font-size: 13px;">Toggle whether the floating assistant widget is live on storefront.</p>
                        </div>
                        <div>
                            <label style="position: relative; display: inline-block; width: 52px; height: 28px;">
                                <input type="checkbox" name="chatbot_enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;" onchange="this.nextElementSibling.style.backgroundColor = this.checked ? '#16a34a' : '#cbd5e1';">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?php echo $enabled ? '#16a34a' : '#cbd5e1'; ?>; transition: .3s; border-radius: 34px;">
                                    <span style="position: absolute; content: ''; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .3s; border-radius: 50%; transform: <?php echo $enabled ? 'translateX(24px)' : 'translateX(0)'; ?>;"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Studio Contact & Information Knowledge Base -->
            <div class="postbox" style="margin-bottom: 24px;">
                <div class="postbox-header">
                    <h2><i class="fa-solid fa-address-book" style="color: var(--wp-blue);"></i> Studio Contact Information Knowledge Base</h2>
                </div>
                <div class="postbox-body" style="padding: 20px;">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Primary Phone / WhatsApp Number</label>
                        <input type="text" name="chatbot_phone_1" value="<?php echo htmlspecialchars($phone1); ?>" class="form-control" style="width: 100%;">
                    </div>
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Secondary Phone Number</label>
                        <input type="text" name="chatbot_phone_2" value="<?php echo htmlspecialchars($phone2); ?>" class="form-control" style="width: 100%;">
                    </div>
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Support Email Address</label>
                        <input type="email" name="chatbot_email" value="<?php echo htmlspecialchars($email); ?>" class="form-control" style="width: 100%;">
                    </div>
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Studio Address</label>
                        <textarea name="chatbot_address" rows="2" class="form-control" style="width: 100%;"><?php echo htmlspecialchars($address); ?></textarea>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Operating Hours</label>
                        <input type="text" name="chatbot_hours" value="<?php echo htmlspecialchars($hours); ?>" class="form-control" style="width: 100%;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Column 2: Customisation Charges & Policy Knowledge Base -->
        <div>
            <!-- Customisation & Stitching Charges -->
            <div class="postbox" style="margin-bottom: 24px;">
                <div class="postbox-header">
                    <h2><i class="fa-solid fa-scissors" style="color: var(--wp-blue);"></i> Customisation &amp; Stitching Charges Knowledge</h2>
                </div>
                <div class="postbox-body" style="padding: 20px;">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Basic Blouse Stitching Starting Price (₹)</label>
                        <input type="number" name="chatbot_stitching_price" value="<?php echo htmlspecialchars($stitchingPrice); ?>" class="form-control" style="width: 100%; max-width: 200px;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Custom Bridal Embroidery &amp; Tailoring Guidance</label>
                        <textarea name="chatbot_custom_info" rows="3" class="form-control" style="width: 100%; font-size: 13px;"><?php echo htmlspecialchars($customInfo); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Shipping & Return Knowledge Base -->
            <div class="postbox" style="margin-bottom: 24px;">
                <div class="postbox-header">
                    <h2><i class="fa-solid fa-truck-fast" style="color: var(--wp-blue);"></i> Shipping &amp; Policy Knowledge</h2>
                </div>
                <div class="postbox-body" style="padding: 20px;">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Shipping &amp; Delivery Policy</label>
                        <textarea name="chatbot_shipping_info" rows="2" class="form-control" style="width: 100%; font-size: 13px;"><?php echo htmlspecialchars($shippingInfo); ?></textarea>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Return &amp; Exchange Policy</label>
                        <textarea name="chatbot_return_info" rows="2" class="form-control" style="width: 100%; font-size: 13px;"><?php echo htmlspecialchars($returnInfo); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Gemini API Key & Welcome Msg -->
            <div class="postbox" style="margin-bottom: 24px;">
                <div class="postbox-header">
                    <h2><i class="fa-solid fa-key" style="color: var(--wp-blue);"></i> Gemini AI &amp; Greetings</h2>
                </div>
                <div class="postbox-body" style="padding: 20px;">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Gemini AI API Key (Optional)</label>
                        <input type="password" name="chatbot_gemini_api_key" value="<?php echo htmlspecialchars($apiKey); ?>" class="form-control" style="width: 100%; font-family: monospace;" placeholder="AIzaSy...">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Initial Welcome Greeting</label>
                        <input type="text" name="chatbot_welcome_message" value="<?php echo htmlspecialchars($welcomeMsg); ?>" class="form-control" style="width: 100%;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top: 10px; margin-bottom: 40px;">
        <button type="submit" class="button button-primary" style="padding: 10px 24px; font-size: 15px; font-weight: 600;">
            <i class="fa-solid fa-floppy-disk"></i> Save Chatbot Knowledge Base &amp; Settings
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
