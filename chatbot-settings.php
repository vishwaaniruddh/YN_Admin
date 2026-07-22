<?php
// admin/chatbot-settings.php
// Tabbed AI Chatbot Configuration & Dynamic Knowledge Base Q&A Manager
$page_title = "Chatbot Knowledge & Settings";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (!current_user_can('manage_settings')) {
    die("Access denied. You do not have permission to manage settings.");
}

$message = '';
$message_type = 'success';
$active_tab = $_GET['tab'] ?? 'config';

// 1. Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_config';

    if ($action === 'save_config') {
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
                'chatbot_system_prompt' => trim($_POST['chatbot_system_prompt'] ?? '')
            ];

            $stmtSave = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($settingsToSave as $k => $v) {
                $stmtSave->execute([$k, $v]);
            }

            $message = "AI Configuration & Contact settings saved successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error saving settings: " . $e->getMessage();
            $message_type = "error";
        }
    } elseif ($action === 'add_faq') {
        $keywords = trim($_POST['keywords'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        if (!empty($keywords) && !empty($answer)) {
            try {
                $stmtIns = $pdo->prepare("INSERT INTO chatbot_faqs (keywords, answer) VALUES (?, ?)");
                $stmtIns->execute([$keywords, $answer]);
                $message = "New Custom Question & Answer added to Knowledge Base!";
                $message_type = "success";
                $active_tab = 'knowledge';
            } catch (PDOException $e) {
                $message = "Error adding Q&A: " . $e->getMessage();
                $message_type = "error";
            }
        }
    } elseif ($action === 'edit_faq') {
        $faq_id = (int)($_POST['faq_id'] ?? 0);
        $keywords = trim($_POST['keywords'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        if ($faq_id > 0 && !empty($keywords) && !empty($answer)) {
            try {
                $stmtUpd = $pdo->prepare("UPDATE chatbot_faqs SET keywords = ?, answer = ? WHERE id = ?");
                $stmtUpd->execute([$keywords, $answer, $faq_id]);
                $message = "Question & Answer updated successfully!";
                $message_type = "success";
                $active_tab = 'knowledge';
            } catch (PDOException $e) {
                $message = "Error updating Q&A: " . $e->getMessage();
                $message_type = "error";
            }
        }
    } elseif ($action === 'delete_faq') {
        $faq_id = (int)($_POST['faq_id'] ?? 0);
        if ($faq_id > 0) {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM chatbot_faqs WHERE id = ?");
                $stmtDel->execute([$faq_id]);
                $message = "Q&A deleted from Knowledge Base.";
                $message_type = "success";
                $active_tab = 'knowledge';
            } catch (PDOException $e) {
                $message = "Error deleting Q&A: " . $e->getMessage();
                $message_type = "error";
            }
        }
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
$phone1 = $settings['chatbot_phone_1'] ?? '+91 9324243011';
$phone2 = $settings['chatbot_phone_2'] ?? '+91 7506628663';
$email = $settings['chatbot_email'] ?? 'yosshita.neha@gmail.com';
$address = $settings['chatbot_address'] ?? '104, Shyamkamal Building B/1, Agarwal Market, Near Deenanath Mangeshkar Natya Mandir, Vile Parle East, Mumbai - 400057';
$hours = $settings['chatbot_hours'] ?? 'Mon - Sat: 11:00 AM - 7:30 PM (IST)';
$systemPrompt = $settings['chatbot_system_prompt'] ?? "You are an expert luxury Indian fashion stylist for YosshitaNeha Fashion Studio. Specialising in handcrafted designer blouses, heritage jewellery, and bespoke bridal customisation. Be helpful, concise, and polite.";

// Load All Custom FAQs
$faqs = $pdo->query("SELECT * FROM chatbot_faqs ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap-header">
    <h1><i class="fa-solid fa-robot" style="color: var(--wp-blue);"></i> AI Chatbot Manager</h1>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<!-- Tabs Navigation -->
<div class="nav-tab-wrapper" style="margin-bottom: 24px; border-bottom: 1px solid #c3c4c7; display: flex; gap: 8px;">
    <a href="chatbot-settings.php?tab=config" class="nav-tab <?php echo $active_tab === 'config' ? 'nav-tab-active' : ''; ?>" style="padding: 10px 20px; font-weight: 600; text-decoration: none; border: 1px solid #c3c4c7; border-bottom: none; border-radius: 4px 4px 0 0; background: <?php echo $active_tab === 'config' ? '#fff' : '#f0f0f1'; ?>; color: <?php echo $active_tab === 'config' ? '#1d2327' : '#50575e'; ?>;">
        <i class="fa-solid fa-sliders"></i> 1. Gemini AI &amp; Studio Config
    </a>
    <a href="chatbot-settings.php?tab=knowledge" class="nav-tab <?php echo $active_tab === 'knowledge' ? 'nav-tab-active' : ''; ?>" style="padding: 10px 20px; font-weight: 600; text-decoration: none; border: 1px solid #c3c4c7; border-bottom: none; border-radius: 4px 4px 0 0; background: <?php echo $active_tab === 'knowledge' ? '#fff' : '#f0f0f1'; ?>; color: <?php echo $active_tab === 'knowledge' ? '#1d2327' : '#50575e'; ?>;">
        <i class="fa-solid fa-book-open" style="color: #16a34a;"></i> 2. Knowledge Base &amp; Custom Q&amp;A (<?php echo count($faqs); ?>)
    </a>
</div>

<?php if ($active_tab === 'config'): ?>
    <!-- TAB 1: GEMINI AI & STUDIO CONFIGURATION -->
    <form method="POST" action="chatbot-settings.php?tab=config">
        <input type="hidden" name="action" value="save_config">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; max-width: 1100px;">
            <div>
                <!-- Enable/Disable Switch -->
                <div class="postbox" style="margin-bottom: 24px;">
                    <div class="postbox-header">
                        <h2><i class="fa-solid fa-power-off" style="color: var(--wp-blue);"></i> Chatbot Visibility</h2>
                    </div>
                    <div class="postbox-body" style="padding: 20px;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <h3 style="margin: 0 0 4px 0; font-size: 15px; color: #0f172a;">Enable Website Chatbot Widget</h3>
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

                <!-- Gemini AI API Configuration -->
                <div class="postbox" style="margin-bottom: 24px;">
                    <div class="postbox-header">
                        <h2><i class="fa-solid fa-key" style="color: var(--wp-blue);"></i> Gemini AI Integration</h2>
                    </div>
                    <div class="postbox-body" style="padding: 20px;">
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;">Gemini AI API Key (Optional)</label>
                            <input type="password" name="chatbot_gemini_api_key" value="<?php echo htmlspecialchars($apiKey); ?>" class="form-control" style="width: 100%; font-family: monospace;" placeholder="AIzaSy...">
                            <small style="color: #64748b; display: block; margin-top: 4px;">Powers natural conversational fashion advice &amp; outfit image matching.</small>
                        </div>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;">Initial Welcome Greeting</label>
                            <input type="text" name="chatbot_welcome_message" value="<?php echo htmlspecialchars($welcomeMsg); ?>" class="form-control" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;">AI Stylist System Prompt</label>
                            <textarea name="chatbot_system_prompt" rows="3" class="form-control" style="width: 100%; font-size: 13px;"><?php echo htmlspecialchars($systemPrompt); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Studio Contact Information -->
            <div>
                <div class="postbox" style="margin-bottom: 24px;">
                    <div class="postbox-header">
                        <h2><i class="fa-solid fa-address-book" style="color: var(--wp-blue);"></i> Studio Contact Details</h2>
                    </div>
                    <div class="postbox-body" style="padding: 20px;">
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;">Primary Phone / WhatsApp</label>
                            <input type="text" name="chatbot_phone_1" value="<?php echo htmlspecialchars($phone1); ?>" class="form-control" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;">Secondary Phone</label>
                            <input type="text" name="chatbot_phone_2" value="<?php echo htmlspecialchars($phone2); ?>" class="form-control" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;">Support Email</label>
                            <input type="email" name="chatbot_email" value="<?php echo htmlspecialchars($email); ?>" class="form-control" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;">Studio Address</label>
                            <textarea name="chatbot_address" rows="3" class="form-control" style="width: 100%;"><?php echo htmlspecialchars($address); ?></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;">Operating Hours</label>
                            <input type="text" name="chatbot_hours" value="<?php echo htmlspecialchars($hours); ?>" class="form-control" style="width: 100%;">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 10px; margin-bottom: 40px;">
            <button type="submit" class="button button-primary" style="padding: 10px 24px; font-size: 15px; font-weight: 600;">
                <i class="fa-solid fa-floppy-disk"></i> Save AI &amp; Studio Config
            </button>
        </div>
    </form>

<?php else: ?>
    <!-- TAB 2: KNOWLEDGE BASE & CUSTOM Q&A MANAGER -->
    <div style="display: grid; grid-template-columns: 360px 1fr; gap: 24px; align-items: start; margin-bottom: 40px;">
        
        <!-- Add New Custom Q&A Form -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-plus-circle" style="color: #16a34a;"></i> Add Custom Q&amp;A to AI Knowledge</h2>
            </div>
            <div class="postbox-body" style="padding: 20px;">
                <form method="POST" action="chatbot-settings.php?tab=knowledge">
                    <input type="hidden" name="action" value="add_faq">

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Trigger Keywords / Question</label>
                        <input type="text" name="keywords" class="form-control" placeholder="e.g. custom, stitching charges, cost, price" required style="width: 100%;">
                        <small style="color: #64748b; display: block; margin-top: 4px;">Comma separated keywords that trigger this answer.</small>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Answer / Trained Response</label>
                        <textarea name="answer" rows="5" class="form-control" placeholder="e.g. Basic blouse stitching starts from ₹1,500..." required style="width: 100%; font-size: 13px;"></textarea>
                    </div>

                    <button type="submit" class="button button-primary" style="width: 100%; background: #16a34a; border-color: #15803d; font-weight: 600;">
                        <i class="fa-solid fa-plus"></i> Add Question to Knowledge Base
                    </button>
                </form>
            </div>
        </div>

        <!-- Existing Knowledge Base Table -->
        <div>
            <div class="postbox">
                <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2><i class="fa-solid fa-list-check" style="color: var(--wp-blue);"></i> Active Knowledge Base Questions &amp; Answers (<?php echo count($faqs); ?>)</h2>
                </div>
                <div class="postbox-body" style="padding: 0;">
                    <?php if (empty($faqs)): ?>
                        <p style="padding: 24px; text-align: center; color: #64748b;">No custom Q&amp;As in Knowledge Base yet. Use the form on the left to add your first custom Q&amp;A!</p>
                    <?php else: ?>
                        <table class="wp-list-table" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th style="width: 250px;">Trigger Keywords</th>
                                    <th>Trained Answer Response</th>
                                    <th style="width: 100px; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faqs as $f): ?>
                                    <tr>
                                        <td>
                                            <strong style="color: #0f172a; font-size: 13px;"><?php echo sanitize_html($f['keywords']); ?></strong>
                                        </td>
                                        <td style="font-size: 13px; line-height: 1.5; color: #334155;">
                                            <?php echo nl2br(sanitize_html($f['answer'])); ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; gap: 8px; justify-content: center;">
                                                <!-- Edit button opens inline modal/form -->
                                                <button type="button" onclick="editFaq(<?php echo $f['id']; ?>, <?php echo htmlspecialchars(json_encode($f['keywords'])); ?>, <?php echo htmlspecialchars(json_encode($f['answer'])); ?>)" class="button" title="Edit Q&A">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                
                                                <!-- Delete button -->
                                                <form method="POST" action="chatbot-settings.php?tab=knowledge" onsubmit="return confirm('Are you sure you want to delete this Q&A from the Knowledge Base?');" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_faq">
                                                    <input type="hidden" name="faq_id" value="<?php echo $f['id']; ?>">
                                                    <button type="submit" class="button" style="color: #ef4444;" title="Delete Q&A">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Edit FAQ Modal -->
<div id="edit-faq-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; width: 500px; max-width: 90vw; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0; margin-bottom: 16px;"><i class="fa-solid fa-pen-to-square" style="color: var(--wp-blue);"></i> Edit Knowledge Q&amp;A</h3>
        <form method="POST" action="chatbot-settings.php?tab=knowledge">
            <input type="hidden" name="action" value="edit_faq">
            <input type="hidden" name="faq_id" id="edit_faq_id">

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Trigger Keywords</label>
                <input type="text" name="keywords" id="edit_keywords" class="form-control" required style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Answer Response</label>
                <textarea name="answer" id="edit_answer" rows="5" class="form-control" required style="width: 100%; font-size: 13px;"></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('edit-faq-modal').style.display = 'none';" class="button">Cancel</button>
                <button type="submit" class="button button-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editFaq(id, keywords, answer) {
    document.getElementById('edit_faq_id').value = id;
    document.getElementById('edit_keywords').value = keywords;
    document.getElementById('edit_answer').value = answer;
    document.getElementById('edit-faq-modal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
