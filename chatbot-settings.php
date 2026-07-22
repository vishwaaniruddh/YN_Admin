<?php
// admin/chatbot-settings.php
$page_title = "Chatbot Settings";
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
        $enabled = isset($_POST['chatbot_enabled']) ? '1' : '0';
        $apiKey = trim($_POST['chatbot_gemini_api_key'] ?? '');
        $welcomeMsg = trim($_POST['chatbot_welcome_message'] ?? '');
        $systemPrompt = trim($_POST['chatbot_system_prompt'] ?? '');

        $settingsToSave = [
            'chatbot_enabled' => $enabled,
            'chatbot_gemini_api_key' => $apiKey,
            'chatbot_welcome_message' => $welcomeMsg,
            'chatbot_system_prompt' => $systemPrompt
        ];

        $stmtSave = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

        foreach ($settingsToSave as $k => $v) {
            $stmtSave->execute([$k, $v]);
        }

        $message = "Chatbot settings saved successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error saving settings: " . $e->getMessage();
        $message_type = "error";
    }
}

// Load Current Settings
$settings = [];
$stmtFetch = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'chatbot_%'");
while ($row = $stmtFetch->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$enabled = ($settings['chatbot_enabled'] ?? '1') === '1';
$apiKey = $settings['chatbot_gemini_api_key'] ?? '';
$welcomeMsg = $settings['chatbot_welcome_message'] ?? "Namaste! ✨ I am your YosshitaNeha Personal Assistant & Stylist. How can I help you today?";
$systemPrompt = $settings['chatbot_system_prompt'] ?? "You are an expert luxury Indian fashion stylist and customer service assistant for YosshitaNeha Fashion Studio. Specialising in handcrafted designer blouses, heritage jewellery, and bespoke bridal customisation. Be warm, polite, and helpful.";

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap-header">
    <h1><i class="fa-solid fa-robot" style="color: var(--wp-blue);"></i> AI Chatbot Configuration</h1>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<div class="postbox" style="max-width: 800px; margin-bottom: 30px;">
    <div class="postbox-header">
        <h2><i class="fa-solid fa-sliders" style="color: var(--wp-blue);"></i> Chatbot Control &amp; AI Settings</h2>
    </div>
    <div class="postbox-body" style="padding: 24px;">
        <form method="POST" action="chatbot-settings.php">
            
            <!-- Enable/Disable Switch -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="margin: 0 0 4px 0; font-size: 16px; color: #0f172a;">Enable AI Chatbot on Website</h3>
                    <p style="margin: 0; color: #64748b; font-size: 13px;">Toggle whether the floating assistant widget is visible to customers on the storefront.</p>
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

            <!-- Gemini API Key -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="chatbot_gemini_api_key" style="font-weight: 600; display: block; margin-bottom: 6px; color: #1e293b;">
                    Gemini AI API Key (Optional)
                </label>
                <input type="password" id="chatbot_gemini_api_key" name="chatbot_gemini_api_key" value="<?php echo htmlspecialchars($apiKey); ?>" class="form-control" placeholder="AIzaSy..." style="width: 100%; max-width: 500px; font-family: monospace;">
                <small style="color: #64748b; display: block; margin-top: 4px;">
                    Powers conversational fashion advice &amp; outfit image matching. Leave empty to use native catalog match mode.
                </small>
            </div>

            <!-- Welcome Message -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="chatbot_welcome_message" style="font-weight: 600; display: block; margin-bottom: 6px; color: #1e293b;">
                    Initial Welcome Message
                </label>
                <input type="text" id="chatbot_welcome_message" name="chatbot_welcome_message" value="<?php echo htmlspecialchars($welcomeMsg); ?>" class="form-control" style="width: 100%;">
            </div>

            <!-- System Persona Prompt -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="chatbot_system_prompt" style="font-weight: 600; display: block; margin-bottom: 6px; color: #1e293b;">
                    AI Assistant Persona &amp; System Prompt
                </label>
                <textarea id="chatbot_system_prompt" name="chatbot_system_prompt" rows="4" class="form-control" style="width: 100%; font-size: 13px;"><?php echo htmlspecialchars($systemPrompt); ?></textarea>
            </div>

            <button type="submit" class="button button-primary" style="padding: 8px 20px; font-size: 14px; font-weight: 600;">
                <i class="fa-solid fa-floppy-disk"></i> Save Chatbot Settings
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
