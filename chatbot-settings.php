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
    } elseif ($action === 'add_model') {
        $name = trim($_POST['model_name'] ?? '');
        $active_tab = 'models';
        
        if (!empty($name) && isset($_FILES['model_image']) && $_FILES['model_image']['error'] === UPLOAD_ERR_OK) {
            try {
                $ext = strtolower(pathinfo($_FILES['model_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    throw new Exception("Only JPG, PNG and WebP image files allowed.");
                }
                
                $uploadDir = __DIR__ . '/assets/models';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileName = 'model_custom_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $destPath = $uploadDir . '/' . $fileName;
                
                if (move_uploaded_file($_FILES['model_image']['tmp_name'], $destPath)) {
                    $relPath = 'assets/models/' . $fileName;
                    $insModel = $pdo->prepare("INSERT INTO ai_models (name, image_path, is_active) VALUES (?, ?, 1)");
                    $insModel->execute([$name, $relPath]);
                    $message = "AI Reference Model '$name' saved to database successfully!";
                    $message_type = "success";
                } else {
                    throw new Exception("Failed to save model image to disk.");
                }
            } catch (Exception $e) {
                $message = "Error creating AI model: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Model Name and Image file are required.";
            $message_type = "error";
        }
    } elseif ($action === 'edit_model') {
        $model_id = (int)($_POST['model_id'] ?? 0);
        $name = trim($_POST['model_name'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $active_tab = 'models';
        
        if ($model_id > 0 && !empty($name)) {
            try {
                $stmtM = $pdo->prepare("SELECT * FROM ai_models WHERE id = ?");
                $stmtM->execute([$model_id]);
                $curM = $stmtM->fetch(PDO::FETCH_ASSOC);
                
                $relPath = $curM['image_path'] ?? '';
                if (isset($_FILES['model_image']) && $_FILES['model_image']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['model_image']['name'], PATHINFO_EXTENSION));
                    $uploadDir = __DIR__ . '/assets/models';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    $fileName = 'model_' . $model_id . '_' . time() . '.' . $ext;
                    $destPath = $uploadDir . '/' . $fileName;
                    if (move_uploaded_file($_FILES['model_image']['tmp_name'], $destPath)) {
                        $relPath = 'assets/models/' . $fileName;
                    }
                }
                
                $updM = $pdo->prepare("UPDATE ai_models SET name = ?, image_path = ?, is_active = ? WHERE id = ?");
                $updM->execute([$name, $relPath, $is_active, $model_id]);
                $message = "AI Reference Model updated successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error updating AI model: " . $e->getMessage();
                $message_type = "error";
            }
        }
    } elseif ($action === 'delete_model') {
        $model_id = (int)($_POST['model_id'] ?? 0);
        $active_tab = 'models';
        if ($model_id > 0) {
            try {
                $stmtM = $pdo->prepare("SELECT image_path FROM ai_models WHERE id = ?");
                $stmtM->execute([$model_id]);
                $curPath = $stmtM->fetchColumn();
                
                if ($curPath && file_exists(__DIR__ . '/' . $curPath) && strpos($curPath, 'model_custom_') !== false) {
                    @unlink(__DIR__ . '/' . $curPath);
                }
                
                $delM = $pdo->prepare("DELETE FROM ai_models WHERE id = ?");
                $delM->execute([$model_id]);
                $message = "AI Reference Model deleted from database.";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error deleting AI model: " . $e->getMessage();
                $message_type = "error";
            }
        }
    } elseif ($action === 'add_shot_type') {
        $name = trim($_POST['shot_name'] ?? '');
        $prompt = trim($_POST['prompt_text'] ?? '');
        $active_tab = 'models';
        if (!empty($name)) {
            try {
                $stmtIns = $pdo->prepare("INSERT INTO ai_shot_types (name, prompt_text, is_active) VALUES (?, ?, 1)");
                $stmtIns->execute([$name, $prompt]);
                $message = "New Shot Type '$name' added to master!";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error adding Shot Type: " . $e->getMessage();
                $message_type = "error";
            }
        }
    } elseif ($action === 'edit_shot_type') {
        $id = (int)($_POST['shot_id'] ?? 0);
        $name = trim($_POST['shot_name'] ?? '');
        $prompt = trim($_POST['prompt_text'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $active_tab = 'models';
        if ($id > 0 && !empty($name)) {
            try {
                $stmtUpd = $pdo->prepare("UPDATE ai_shot_types SET name = ?, prompt_text = ?, is_active = ? WHERE id = ?");
                $stmtUpd->execute([$name, $prompt, $is_active, $id]);
                $message = "Shot Type updated successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error updating Shot Type: " . $e->getMessage();
                $message_type = "error";
            }
        }
    } elseif ($action === 'delete_shot_type') {
        $id = (int)($_POST['shot_id'] ?? 0);
        $active_tab = 'models';
        if ($id > 0) {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM ai_shot_types WHERE id = ?");
                $stmtDel->execute([$id]);
                $message = "Shot Type deleted from master.";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error deleting Shot Type: " . $e->getMessage();
                $message_type = "error";
            }
        }
    } elseif ($action === 'add_hair_style') {
        $name = trim($_POST['hair_name'] ?? '');
        $prompt = trim($_POST['prompt_text'] ?? '');
        $active_tab = 'models';
        if (!empty($name)) {
            try {
                $stmtIns = $pdo->prepare("INSERT INTO ai_hair_styles (name, prompt_text, is_active) VALUES (?, ?, 1)");
                $stmtIns->execute([$name, $prompt]);
                $message = "New Hair Style '$name' added to master!";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error adding Hair Style: " . $e->getMessage();
                $message_type = "error";
            }
        }
    } elseif ($action === 'edit_hair_style') {
        $id = (int)($_POST['hair_id'] ?? 0);
        $name = trim($_POST['hair_name'] ?? '');
        $prompt = trim($_POST['prompt_text'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $active_tab = 'models';
        if ($id > 0 && !empty($name)) {
            try {
                $stmtUpd = $pdo->prepare("UPDATE ai_hair_styles SET name = ?, prompt_text = ?, is_active = ? WHERE id = ?");
                $stmtUpd->execute([$name, $prompt, $is_active, $id]);
                $message = "Hair Style updated successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error updating Hair Style: " . $e->getMessage();
                $message_type = "error";
            }
        }
    } elseif ($action === 'delete_hair_style') {
        $id = (int)($_POST['hair_id'] ?? 0);
        $active_tab = 'models';
        if ($id > 0) {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM ai_hair_styles WHERE id = ?");
                $stmtDel->execute([$id]);
                $message = "Hair Style deleted from master.";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error deleting Hair Style: " . $e->getMessage();
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

// Auto-create and seed ai_models table with shot_type & hair_style
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_models (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        gender VARCHAR(20) DEFAULT 'Female',
        image_path VARCHAR(255) NOT NULL,
        shot_type VARCHAR(255) DEFAULT 'Full Body',
        hair_style VARCHAR(255) DEFAULT 'As per product',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    try { $pdo->exec("ALTER TABLE ai_models ADD COLUMN shot_type VARCHAR(255) DEFAULT 'Full Body'"); } catch (Exception $ex) {}
    try { $pdo->exec("ALTER TABLE ai_models ADD COLUMN hair_style VARCHAR(255) DEFAULT 'As per product'"); } catch (Exception $ex) {}

    $checkModels = $pdo->query("SELECT COUNT(*) FROM ai_models")->fetchColumn();
    if ($checkModels == 0) {
        $seedStmt = $pdo->prepare("INSERT INTO ai_models (name, gender, image_path, shot_type, hair_style, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $seedStmt->execute(["Model 1 - Fair Royal Face", "Female", "assets/models/model_1.png", "Full Body", "Open Flowing Hair"]);
        $seedStmt->execute(["Model 2 - North Indian Bridal", "Female", "assets/models/model_2.png", "Close-up Portrait", "Tied Bun with Gajra"]);
        $seedStmt->execute(["Model 3 - South Indian Grace", "Female", "assets/models/model_3.png", "Half Body", "Traditional Braid (Choti)"]);
        $seedStmt->execute(["Model 4 - Modern Minimalist", "Female", "assets/models/model_4.png", "Full Body", "Half Up, Half Down"]);
        $seedStmt->execute(["Model 5 - Dusky Glamour", "Female", "assets/models/model_5.png", "Close-up Portrait", "Side Swept Waves"]);
    }
} catch (PDOException $e) {}

// Auto-create and seed ai_shot_types table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_shot_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        prompt_text TEXT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $checkShots = $pdo->query("SELECT COUNT(*) FROM ai_shot_types")->fetchColumn();
    if ($checkShots == 0) {
        $seedShot = $pdo->prepare("INSERT INTO ai_shot_types (name, prompt_text, is_active) VALUES (?, ?, 1)");
        $seedShot->execute(["Full Body", "full body head-to-toe shot showing the complete outfit/jewelry look"]);
        $seedShot->execute(["Close-up Portrait", "close-up portrait shot focusing on the face and the jewelry"]);
        $seedShot->execute(["Half Body", "half body shot from waist up, showing the model's torso and face"]);
        $seedShot->execute(["Back View", "shot from behind showing the back design and details of the product"]);
    }
} catch (PDOException $e) {}

// Auto-create and seed ai_hair_styles table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_hair_styles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        prompt_text TEXT DEFAULT '',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $checkHairs = $pdo->query("SELECT COUNT(*) FROM ai_hair_styles")->fetchColumn();
    if ($checkHairs == 0) {
        $seedHair = $pdo->prepare("INSERT INTO ai_hair_styles (name, prompt_text, is_active) VALUES (?, ?, 1)");
        $seedHair->execute(["As per product", ""]);
        $seedHair->execute(["Open Flowing", "open flowing hair with soft waves (khule baal)"]);
        $seedHair->execute(["Tied Bun with Gajra", "neatly tied bun with gajra flowers"]);
        $seedHair->execute(["Traditional Braid (Choti)", "traditional long braided hair (gajra choti)"]);
        $seedHair->execute(["Half Up, Half Down", "elegant half-up half-down hairstyle"]);
        $seedHair->execute(["Side Swept Waves", "glamorous side-swept waves"]);
        $seedHair->execute(["Sleek Straight", "sleek straight hair with center part"]);
    }
} catch (PDOException $e) {}

$ai_models = $pdo->query("SELECT * FROM ai_models ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$ai_shot_types = $pdo->query("SELECT * FROM ai_shot_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$ai_hair_styles = $pdo->query("SELECT * FROM ai_hair_styles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

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
    <a href="chatbot-settings.php?tab=models" class="nav-tab <?php echo $active_tab === 'models' ? 'nav-tab-active' : ''; ?>" style="padding: 10px 20px; font-weight: 600; text-decoration: none; border: 1px solid #c3c4c7; border-bottom: none; border-radius: 4px 4px 0 0; background: <?php echo $active_tab === 'models' ? '#fff' : '#f0f0f1'; ?>; color: <?php echo $active_tab === 'models' ? '#1d2327' : '#50575e'; ?>;">
        <i class="fa-solid fa-layer-group" style="color: #2271b1;"></i> 3. AI Studio Masters (Models, Shot Types, Hair Styles)
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

<?php elseif ($active_tab === 'knowledge'): ?>
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
<?php elseif ($active_tab === 'models'): ?>
    <!-- TAB 3: AI FACE REFERENCE MODELS MANAGER -->
    <div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px; align-items: start; margin-bottom: 40px;">
        
        <!-- Add New AI Model Form -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-plus-circle" style="color: var(--wp-blue);"></i> Add New AI Model</h2>
            </div>
            <div class="postbox-body" style="padding: 20px;">
                <form method="POST" action="chatbot-settings.php?tab=models" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_model">

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Model Name <span style="color: var(--wp-error-red);">*</span></label>
                        <input type="text" name="model_name" class="form-control" placeholder="e.g. Model 6 - Royal Bride" required style="width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Face Reference Image <span style="color: var(--wp-error-red);">*</span></label>
                        <input type="file" name="model_image" accept="image/png, image/jpeg, image/webp" class="form-control" required style="width: 100%;">
                        <small style="color: #64748b; display: block; margin-top: 4px;">Upload a clear portrait front face image.</small>
                    </div>

                    <button type="submit" class="button button-primary" style="width: 100%; justify-content: center; padding: 8px;">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Save Model to DB
                    </button>
                </form>
            </div>
        </div>

        <!-- AI Models Grid -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-user-astronaut" style="color: var(--wp-blue);"></i> Active AI Reference Models in Database (<?php echo count($ai_models); ?>)</h2>
            </div>
            <div class="postbox-body" style="padding: 20px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
                    <?php foreach ($ai_models as $m): ?>
                        <div style="border: 1px solid var(--wp-border); border-radius: 6px; overflow: hidden; background: #fff; display: flex; flex-direction: column;">
                            <div style="position: relative; aspect-ratio: 1/1; background: #f6f7f7; overflow: hidden;">
                                <img src="<?php echo sanitize_html($m['image_path']); ?>?v=<?php echo strtotime($m['created_at'] ?? 'now'); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo sanitize_html($m['name']); ?>">
                                <span style="position: absolute; top: 6px; right: 6px; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 3px; background: <?php echo $m['is_active'] ? '#16a34a' : '#94a3b8'; ?>; color: #fff;">
                                    <?php echo $m['is_active'] ? 'Active' : 'Disabled'; ?>
                                </span>
                            </div>
                            <div style="padding: 10px; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
                                <div style="margin-bottom: 8px;">
                                    <h4 style="margin: 0 0 4px 0; font-size: 13px; font-weight: 600; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo sanitize_html($m['name']); ?></h4>
                                </div>
                                <div style="display: flex; gap: 6px; margin-top: 6px;">
                                    <button type="button" class="button" onclick="editModel(<?php echo $m['id']; ?>, <?php echo htmlspecialchars(json_encode($m['name'])); ?>, <?php echo $m['is_active']; ?>)" style="flex: 1; font-size: 11px; padding: 2px 4px; text-align: center;">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                    <form method="POST" action="chatbot-settings.php?tab=models" onsubmit="return confirm('Delete this model from DB?');" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_model">
                                        <input type="hidden" name="model_id" value="<?php echo $m['id']; ?>">
                                        <button type="submit" class="button" style="color: #ef4444; padding: 2px 6px; font-size: 11px;" title="Delete Model">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTIONS 2 & 3: SHOT TYPE MASTER & HAIR STYLE MASTER -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; margin-top: 30px; margin-bottom: 40px;">
        
        <!-- Shot Type Master -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-camera" style="color: var(--wp-blue);"></i> Shot Type Master (<?php echo count($ai_shot_types); ?>)</h2>
            </div>
            <div class="postbox-body" style="padding: 20px;">
                <form method="POST" action="chatbot-settings.php?tab=models" style="margin-bottom: 20px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <input type="hidden" name="action" value="add_shot_type">
                    <h4 style="margin: 0 0 10px 0; font-size: 13px;">Add New Shot Type</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 8px;">
                        <input type="text" name="shot_name" class="form-control" placeholder="Shot Name (e.g. Drone Top View)" required style="width: 100%; font-size: 12px;">
                        <input type="text" name="prompt_text" class="form-control" placeholder="Prompt instruction text" required style="width: 100%; font-size: 12px;">
                    </div>
                    <button type="submit" class="button button-primary" style="font-size: 12px; width: 100%;">
                        <i class="fa-solid fa-plus"></i> Add Shot Type
                    </button>
                </form>

                <table class="wp-list-table widefat fixed striped" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Shot Name</th>
                            <th>Prompt Instruction</th>
                            <th style="width: 70px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ai_shot_types as $st): ?>
                            <tr>
                                <td><strong><?php echo sanitize_html($st['name']); ?></strong></td>
                                <td style="color: #64748b; font-size: 11px;"><?php echo sanitize_html($st['prompt_text']); ?></td>
                                <td style="text-align: center;">
                                    <button type="button" class="button" onclick="editShotType(<?php echo $st['id']; ?>, <?php echo htmlspecialchars(json_encode($st['name'])); ?>, <?php echo htmlspecialchars(json_encode($st['prompt_text'])); ?>, <?php echo $st['is_active']; ?>)" style="padding: 1px 5px; font-size: 10px;" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="chatbot-settings.php?tab=models" onsubmit="return confirm('Delete this Shot Type?');" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_shot_type">
                                        <input type="hidden" name="shot_id" value="<?php echo $st['id']; ?>">
                                        <button type="submit" class="button" style="color: #ef4444; padding: 1px 5px; font-size: 10px;" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Hair Style Master -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-scissors" style="color: #ec4899;"></i> Hair Style Master (<?php echo count($ai_hair_styles); ?>)</h2>
            </div>
            <div class="postbox-body" style="padding: 20px;">
                <form method="POST" action="chatbot-settings.php?tab=models" style="margin-bottom: 20px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <input type="hidden" name="action" value="add_hair_style">
                    <h4 style="margin: 0 0 10px 0; font-size: 13px;">Add New Hair Style</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 8px;">
                        <input type="text" name="hair_name" class="form-control" placeholder="Hair Style Name (e.g. Messy Bun)" required style="width: 100%; font-size: 12px;">
                        <input type="text" name="prompt_text" class="form-control" placeholder="Prompt instruction text" style="width: 100%; font-size: 12px;">
                    </div>
                    <button type="submit" class="button button-primary" style="font-size: 12px; width: 100%;">
                        <i class="fa-solid fa-plus"></i> Add Hair Style
                    </button>
                </form>

                <table class="wp-list-table widefat fixed striped" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Style Name</th>
                            <th>Prompt Instruction</th>
                            <th style="width: 70px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ai_hair_styles as $hs): ?>
                            <tr>
                                <td><strong><?php echo sanitize_html($hs['name']); ?></strong></td>
                                <td style="color: #64748b; font-size: 11px;"><?php echo sanitize_html($hs['prompt_text'] ?: '(None / Default)'); ?></td>
                                <td style="text-align: center;">
                                    <button type="button" class="button" onclick="editHairStyle(<?php echo $hs['id']; ?>, <?php echo htmlspecialchars(json_encode($hs['name'])); ?>, <?php echo htmlspecialchars(json_encode($hs['prompt_text'])); ?>, <?php echo $hs['is_active']; ?>)" style="padding: 1px 5px; font-size: 10px;" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="chatbot-settings.php?tab=models" onsubmit="return confirm('Delete this Hair Style?');" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_hair_style">
                                        <input type="hidden" name="hair_id" value="<?php echo $hs['id']; ?>">
                                        <button type="submit" class="button" style="color: #ef4444; padding: 1px 5px; font-size: 10px;" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

<!-- Edit Model Modal -->
<div id="edit-model-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; width: 450px; max-width: 90vw; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0; margin-bottom: 16px;"><i class="fa-solid fa-pen-to-square" style="color: var(--wp-blue);"></i> Edit AI Model</h3>
        <form method="POST" action="chatbot-settings.php?tab=models" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_model">
            <input type="hidden" name="model_id" id="edit_model_id">

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Model Name</label>
                <input type="text" name="model_name" id="edit_model_name" class="form-control" required style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Replace Face Image (Optional)</label>
                <input type="file" name="model_image" accept="image/png, image/jpeg, image/webp" class="form-control" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="edit_model_active" value="1"> Enable / Active Model
                </label>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('edit-model-modal').style.display = 'none';" class="button">Cancel</button>
                <button type="submit" class="button button-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Shot Type Modal -->
<div id="edit-shot-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; width: 450px; max-width: 90vw; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0; margin-bottom: 16px;"><i class="fa-solid fa-camera" style="color: var(--wp-blue);"></i> Edit Shot Type</h3>
        <form method="POST" action="chatbot-settings.php?tab=models">
            <input type="hidden" name="action" value="edit_shot_type">
            <input type="hidden" name="shot_id" id="edit_shot_id">

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Shot Name</label>
                <input type="text" name="shot_name" id="edit_shot_name" class="form-control" required style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Prompt Instruction Text</label>
                <textarea name="prompt_text" id="edit_shot_prompt" rows="3" class="form-control" required style="width: 100%; font-size: 12px;"></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('edit-shot-modal').style.display = 'none';" class="button">Cancel</button>
                <button type="submit" class="button button-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Hair Style Modal -->
<div id="edit-hair-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; width: 450px; max-width: 90vw; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0; margin-bottom: 16px;"><i class="fa-solid fa-scissors" style="color: #ec4899;"></i> Edit Hair Style</h3>
        <form method="POST" action="chatbot-settings.php?tab=models">
            <input type="hidden" name="action" value="edit_hair_style">
            <input type="hidden" name="hair_id" id="edit_hair_id">

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Hair Style Name</label>
                <input type="text" name="hair_name" id="edit_hair_name" class="form-control" required style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Prompt Instruction Text</label>
                <textarea name="prompt_text" id="edit_hair_prompt" rows="3" class="form-control" style="width: 100%; font-size: 12px;"></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('edit-hair-modal').style.display = 'none';" class="button">Cancel</button>
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

function editModel(id, name, isActive) {
    document.getElementById('edit_model_id').value = id;
    document.getElementById('edit_model_name').value = name;
    document.getElementById('edit_model_active').checked = (isActive == 1);
    document.getElementById('edit-model-modal').style.display = 'flex';
}

function editShotType(id, name, prompt, isActive) {
    document.getElementById('edit_shot_id').value = id;
    document.getElementById('edit_shot_name').value = name;
    document.getElementById('edit_shot_prompt').value = prompt;
    document.getElementById('edit-shot-modal').style.display = 'flex';
}

function editHairStyle(id, name, prompt, isActive) {
    document.getElementById('edit_hair_id').value = id;
    document.getElementById('edit_hair_name').value = name;
    document.getElementById('edit_hair_prompt').value = prompt;
    document.getElementById('edit-hair-modal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
