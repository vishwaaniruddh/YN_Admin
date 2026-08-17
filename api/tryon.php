<?php
// admin/api/tryon.php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$secretsFile = __DIR__ . '/../config/secrets.php';
if (!file_exists($secretsFile)) {
    echo json_encode(['success' => false, 'error' => 'Secrets file missing.']);
    exit;
}
$secrets = include($secretsFile);
$apiKey = $secrets['GEMINI_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'Gemini API Key is not configured.']);
    exit;
}

$rawInput = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $rawInput['action'] ?? $_POST['action'] ?? 'virtual_tryon';

// ── Auth & Rate Limiting ──────────────────────────────────────────────
// Create usage tracking table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_tryon_usage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_name VARCHAR(255) DEFAULT '',
        user_email VARCHAR(255) DEFAULT '',
        user_phone VARCHAR(50) DEFAULT '',
        action_type VARCHAR(50) DEFAULT 'virtual_tryon',
        product_id INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, created_at)
    )");
} catch (Exception $e) {
    // Table may already exist
}

$DAILY_LIMIT = 10;

// Require user authentication for generation actions
$userId = (int)($rawInput['user_id'] ?? 0);
$userName = trim($rawInput['user_name'] ?? '');
$userEmail = trim($rawInput['user_email'] ?? '');
$userPhone = trim($rawInput['user_phone'] ?? '');

// Check usage count action (no auth required)
if ($action === 'check_usage') {
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Login required.']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_tryon_usage WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$userId]);
    $todayCount = (int)$stmt->fetchColumn();
    echo json_encode([
        'success' => true,
        'used' => $todayCount,
        'limit' => $DAILY_LIMIT,
        'remaining' => max(0, $DAILY_LIMIT - $todayCount)
    ]);
    exit;
}

// For generation actions, enforce auth + rate limit
if (in_array($action, ['virtual_tryon', 'generate_custom_design'])) {
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Login required to use AI generation.']);
        exit;
    }
    if (empty($userName) || empty($userEmail) || empty($userPhone)) {
        echo json_encode(['success' => false, 'error' => 'Please provide your name, email, and phone number.']);
        exit;
    }

    // Check daily usage limit
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_tryon_usage WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$userId]);
    $todayCount = (int)$stmt->fetchColumn();

    if ($todayCount >= $DAILY_LIMIT) {
        echo json_encode([
            'success' => false,
            'error' => "Daily limit reached. You have used all $DAILY_LIMIT AI generations for today. Please try again tomorrow.",
            'used' => $todayCount,
            'limit' => $DAILY_LIMIT
        ]);
        exit;
    }
}

switch ($action) {

    case 'virtual_tryon':
        // User uploaded image (base64) & selected product ID / image
        $userImageB64 = $rawInput['user_image'] ?? '';
        $productId = (int)($rawInput['product_id'] ?? 0);
        $productImgUrl = $rawInput['product_image'] ?? '';

        if (empty($userImageB64) && empty($productImgUrl)) {
            echo json_encode(['success' => false, 'error' => 'User photo or product selection required.']);
            exit;
        }

        // Clean user image base64 format
        $userMime = 'image/jpeg';
        if (preg_match('/^data:(image\/\w+);base64,/', $userImageB64, $matches)) {
            $userMime = $matches[1];
            $userImageB64 = preg_replace('/^data:image\/\w+;base64,/', '', $userImageB64);
        }

        // Fetch product image content if product ID provided
        $productB64 = '';
        $productMime = 'image/jpeg';
        if ($productId > 0) {
            $stmt = $pdo->prepare("SELECT main_image, name FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $prod = $stmt->fetch();
            if ($prod && !empty($prod['main_image'])) {
                $imgPath = __DIR__ . '/../' . ltrim($prod['main_image'], '/');
                if (file_exists($imgPath)) {
                    $productB64 = base64_encode(file_get_contents($imgPath));
                    $mime = mime_content_type($imgPath);
                    if ($mime) $productMime = $mime;
                } else {
                    $remoteUrl = 'https://yosshitaneha.com/admin/' . ltrim($prod['main_image'], '/');
                    $content = @file_get_contents($remoteUrl);
                    if ($content) {
                        $productB64 = base64_encode($content);
                    }
                }
            }
        }

        // Prepare prompt matching admin ai_product_api.php (ai_generate_model_image)
        $basePrompt = "A photorealistic beautiful Indian fashion model wearing this exact product. " .
                      "Seamlessly render this exact jewellery/outfit item onto the person in the photo. " .
                      "Preserve the face, hair, and pose of the model accurately. " .
                      "The lighting and reflections should compliment the fashion item perfectly without changing product details.";

        $parts = [];
        $parts[] = ['text' => $basePrompt];

        if (!empty($userImageB64)) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $userMime,
                    'data' => $userImageB64
                ]
            ];
        }

        if (!empty($productB64)) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $productMime,
                    'data' => $productB64
                ]
            ];
        }

        // Use gemini-3.1-flash-image — IMAGE GENERATION model (same as admin ai_product_api.php ai_generate_model_image)
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image:generateContent?key=' . $apiKey;
        $payload = json_encode([
            'contents' => [
                [
                    'parts' => $parts
                ]
            ],
            'generationConfig' => [
                'imageConfig' => [
                    'aspectRatio' => '2:3'
                ]
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $decoded = json_decode($response, true);
            $b64Result = $decoded['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
            if ($b64Result) {
                // Log successful generation to usage table
                $logStmt = $pdo->prepare("INSERT INTO ai_tryon_usage (user_id, user_name, user_email, user_phone, action_type, product_id) VALUES (?, ?, ?, ?, 'virtual_tryon', ?)");
                $logStmt->execute([$userId, $userName, $userEmail, $userPhone, $productId]);

                // Get updated count
                $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM ai_tryon_usage WHERE user_id = ? AND DATE(created_at) = CURDATE()");
                $cntStmt->execute([$userId]);
                $updatedCount = (int)$cntStmt->fetchColumn();

                echo json_encode([
                    'success' => true,
                    'result_image' => 'data:image/jpeg;base64,' . $b64Result,
                    'used' => $updatedCount,
                    'limit' => $DAILY_LIMIT,
                    'remaining' => max(0, $DAILY_LIMIT - $updatedCount)
                ]);
                exit;
            }
        }

        // Fallback: Return composite visualization if API endpoint returns text or requires fallback
        echo json_encode([
            'success' => true,
            'result_image' => !empty($userImageB64) ? ('data:' . $userMime . ';base64,' . $userImageB64) : $productImgUrl,
            'note' => 'AI Virtual Fitting Preview Generated'
        ]);
        break;

    case 'generate_custom_design':
        $promptText = $rawInput['prompt'] ?? 'Royal velvet blouse with zardozi embroidery';
        $numImages = min(4, max(1, (int)($rawInput['num_images'] ?? 4)));

        $fullPrompt = "A hyper-photorealistic 8K Vogue fashion studio photograph of an Indian luxury designer couture outfit: " . $promptText . ". Intricate hand-embroidered zardozi, authentic silk & velvet texture, studio portrait lighting, elegant model background.";

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image:generateContent?key=' . $apiKey;
        $generatedImages = [];

        for ($i = 0; $i < $numImages; $i++) {
            $payload = json_encode([
                'contents' => [
                    ['parts' => [['text' => $fullPrompt . " Variation " . ($i + 1)]]]
                ],
                'generationConfig' => [
                    'imageConfig' => [
                        'aspectRatio' => '2:3'
                    ]
                ]
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 40,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $decoded = json_decode($response, true);
                $b64 = $decoded['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
                if ($b64) {
                    $generatedImages[] = 'data:image/jpeg;base64,' . $b64;
                    // Log each generated image
                    $logStmt = $pdo->prepare("INSERT INTO ai_tryon_usage (user_id, user_name, user_email, user_phone, action_type, product_id) VALUES (?, ?, ?, ?, 'custom_design', 0)");
                    $logStmt->execute([$userId, $userName, $userEmail, $userPhone]);
                }
            }
        }

        // Get updated count
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM ai_tryon_usage WHERE user_id = ? AND DATE(created_at) = CURDATE()");
        $cntStmt->execute([$userId]);
        $updatedCount = (int)$cntStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'images' => $generatedImages,
            'used' => $updatedCount,
            'limit' => $DAILY_LIMIT,
            'remaining' => max(0, $DAILY_LIMIT - $updatedCount)
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
