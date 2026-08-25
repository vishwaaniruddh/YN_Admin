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
$action = $_GET['action'] ?? $rawInput['action'] ?? $_POST['action'] ?? 'wear_product';

// ── Auth & Rate Limiting ──────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_tryon_usage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL DEFAULT 0,
        ip_address VARCHAR(45) DEFAULT '',
        user_name VARCHAR(255) DEFAULT '',
        user_email VARCHAR(255) DEFAULT '',
        user_phone VARCHAR(50) DEFAULT '',
        action_type VARCHAR(50) DEFAULT 'wear_product',
        product_id INT DEFAULT 0,
        product_name VARCHAR(255) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, created_at),
        INDEX idx_ip_date (ip_address, created_at)
    )");
    // Ensure columns exist (migration for older tables)
    $pdo->exec("ALTER TABLE ai_tryon_usage ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) DEFAULT '' AFTER user_id");
    $pdo->exec("ALTER TABLE ai_tryon_usage ADD COLUMN IF NOT EXISTS product_name VARCHAR(255) DEFAULT '' AFTER product_id");
} catch (Exception $e) {
    // Table may already exist or column already present
}

$DAILY_LIMIT = 10;

$userId = (int)($rawInput['user_id'] ?? $_GET['user_id'] ?? 0);
$userName = trim($rawInput['user_name'] ?? '');
$userEmail = trim($rawInput['user_email'] ?? '');
$userPhone = trim($rawInput['user_phone'] ?? '');

$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (strpos($clientIp, ',') !== false) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}

// Function to get today's usage count
function getTodayUsageCount($pdo, $userId, $clientIp) {
    if ($userId > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_tryon_usage WHERE user_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_tryon_usage WHERE user_id = 0 AND ip_address = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$clientIp]);
        return (int)$stmt->fetchColumn();
    }
}

// Check usage count action
if ($action === 'check_usage') {
    $todayCount = getTodayUsageCount($pdo, $userId, $clientIp);
    echo json_encode([
        'success' => true,
        'used' => $todayCount,
        'limit' => $DAILY_LIMIT,
        'remaining' => max(0, $DAILY_LIMIT - $todayCount)
    ]);
    exit;
}

// ── Logging System ────────────────────────────────────────────────────
function ai_log($message, $context = []) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/ai_studio.log';
    $time = date('Y-m-d H:i:s');
    $ctxStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : '';
    @file_put_contents($logFile, "[$time] $message$ctxStr\n", FILE_APPEND | LOCK_EX);
}

// ── Helper: Fetch remote image via curl (more reliable than file_get_contents) ──
function fetchRemoteImage($url) {
    ai_log("Fetching remote image", ['url' => $url]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($code === 200 && $data && strlen($data) > 100) {
        ai_log("Remote image fetched successfully", ['url' => $url, 'bytes' => strlen($data), 'mime' => $mime]);
        return ['data' => $data, 'mime' => $mime ?: 'image/jpeg'];
    }
    ai_log("Remote image fetch failed", ['url' => $url, 'http_code' => $code]);
    return null;
}

// ── Helper: Call Gemini Image Generation ─────────────────────────────
function callGeminiImageGeneration($apiKey, $parts, $aspectRatio = '2:3') {
    $models = ['gemini-3.1-flash-image', 'gemini-2.5-flash-image'];
    $lastError = 'No models succeeded';

    foreach ($models as $model) {
        ai_log("Calling Gemini model", ['model' => $model, 'parts_count' => count($parts), 'aspectRatio' => $aspectRatio]);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
        $payload = json_encode([
            'contents' => [
                ['parts' => $parts]
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
                'imageConfig' => [
                    'aspectRatio' => $aspectRatio
                ]
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $startT = microtime(true);
        $response = curl_exec($ch);
        $duration = round(microtime(true) - $startT, 2);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        ai_log("Gemini response received", ['model' => $model, 'http_code' => $httpCode, 'duration_sec' => $duration]);

        // Curl-level failure (timeout, DNS, etc.)
        if ($response === false || $httpCode === 0) {
            $lastError = "Model $model: network/timeout error" . ($curlErr ? " ($curlErr)" : '');
            ai_log("Gemini network error", ['model' => $model, 'error' => $lastError]);
            continue;
        }

        if ($httpCode !== 200) {
            $errBody = json_decode($response, true);
            $errMsg  = $errBody['error']['message'] ?? substr($response, 0, 300);
            $lastError = "Model $model HTTP $httpCode: $errMsg";
            ai_log("Gemini HTTP error", ['model' => $model, 'http_code' => $httpCode, 'error' => $errMsg]);
            continue;
        }

        // HTTP 200 – search ALL response parts for image data
        $decoded    = json_decode($response, true);
        $candidates = $decoded['candidates'] ?? [];

        if (empty($candidates)) {
            // Check for prompt-level blocks
            $blockReason = $decoded['promptFeedback']['blockReason'] ?? null;
            if ($blockReason) {
                $lastError = "Content blocked by safety filter ($blockReason). Try a different photo or product.";
            } else {
                $lastError = "Model $model returned empty candidates.";
            }
            continue;
        }

        // Check finish reason for safety blocks
        $finishReason = $candidates[0]['finishReason'] ?? '';
        if (in_array($finishReason, ['SAFETY', 'RECITATION', 'OTHER'])) {
            $lastError = "Model $model blocked the output (reason: $finishReason). Try a different photo.";
            continue;
        }

        $responseParts = $candidates[0]['content']['parts'] ?? [];
        $imageB64  = null;
        $textReply = '';

        // Scan ALL parts – image can be at any index
        foreach ($responseParts as $part) {
            if (isset($part['inlineData']['data'])) {
                $imageB64 = $part['inlineData']['data'];
                break; // found an image, use it
            }
            if (isset($part['text'])) {
                $textReply .= $part['text'] . ' ';
            }
        }

        if ($imageB64) {
            return ['success' => true, 'base64' => $imageB64, 'model' => $model];
        }

        // Model returned text only (refusal / description) – capture it
        $textReply = trim($textReply);
        if ($textReply) {
            $lastError = "Model $model did not generate an image. Response: " . substr($textReply, 0, 200);
        } else {
            $lastError = "Model $model returned HTTP 200 but no image data in response.";
        }
    }

    return ['success' => false, 'error' => $lastError];
}

switch ($action) {

    case 'wear_product':
    case 'virtual_tryon':
        // Rate limit enforcement (10 generations / day)
        $todayCount = getTodayUsageCount($pdo, $userId, $clientIp);
        if ($todayCount >= $DAILY_LIMIT) {
            echo json_encode([
                'success' => false,
                'error' => "You've reached your daily limit of {$DAILY_LIMIT} AI generations today. Please try again tomorrow!",
                'limit_reached' => true,
                'used' => $todayCount,
                'limit' => $DAILY_LIMIT,
                'remaining' => 0
            ]);
            exit;
        }

        // User photo base64
        $userImageB64 = $rawInput['user_image'] ?? '';
        $productId = (int)($rawInput['product_id'] ?? 0);
        $productImgUrl = $rawInput['product_image'] ?? '';
        $shotType = trim($rawInput['shot_type'] ?? 'Portrait (Head & Neckline)');
        $backgroundSetting = trim($rawInput['background_style'] ?? 'Royal Heritage Palace');
        $hairStyle = trim($rawInput['hair_style'] ?? 'Open Flowing Royal Curls');
        $customNotes = trim($rawInput['custom_notes'] ?? '');

        if (empty($userImageB64) && empty($productImgUrl) && $productId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Please upload your photo and select a product to wear.']);
            exit;
        }

        // Clean user image base64
        $userMime = 'image/jpeg';
        if (preg_match('/^data:(image\/\w+);base64,/', $userImageB64, $matches)) {
            $userMime = $matches[1];
            $userImageB64 = preg_replace('/^data:image\/\w+;base64,/', '', $userImageB64);
        }

        // Load Product(s) Details & Image(s)
        $rawProducts = $rawInput['products'] ?? [];
        if (empty($rawProducts) && ($productId > 0 || !empty($productImgUrl))) {
            $rawProducts = [[
                'id' => $productId,
                'name' => '',
                'image' => $productImgUrl
            ]];
        }

        $processedProducts = [];
        foreach ($rawProducts as $pItem) {
            $pId = (int)($pItem['id'] ?? 0);
            $pName = trim($pItem['name'] ?? '');
            $pImg = trim($pItem['image'] ?? $pItem['product_image'] ?? $pItem['main_image'] ?? '');
            $pCat = trim($pItem['category_name'] ?? $pItem['category'] ?? '');
            $pSlot = trim($pItem['slot'] ?? $pItem['category_slot'] ?? '');

            if ($pId > 0) {
                $stmt = $pdo->prepare("SELECT p.name, p.main_image, p.category_id, c.name as cat_name 
                                       FROM products p 
                                       LEFT JOIN categories c ON p.category_id = c.id 
                                       WHERE p.id = ?");
                $stmt->execute([$pId]);
                $prodRow = $stmt->fetch();
                if ($prodRow) {
                    if (empty($pName)) $pName = $prodRow['name'];
                    if (empty($pCat)) $pCat = $prodRow['cat_name'] ?? '';
                    if (empty($pImg)) $pImg = $prodRow['main_image'];
                }
            }

            // Fetch image data
            $pB64 = '';
            $pMime = 'image/jpeg';
            if (!empty($pImg)) {
                if (preg_match('/^data:(image\/\w+);base64,/', $pImg, $matches)) {
                    $pMime = $matches[1];
                    $pB64 = preg_replace('/^data:image\/\w+;base64,/', '', $pImg);
                } else {
                    $localPath = __DIR__ . '/../' . ltrim($pImg, '/');
                    if (file_exists($localPath)) {
                        $pB64 = base64_encode(file_get_contents($localPath));
                        $m = mime_content_type($localPath);
                        if ($m) $pMime = $m;
                    } else {
                        $remoteUrl = str_starts_with($pImg, 'http') ? $pImg : 'https://yosshitaneha.com/admin/' . ltrim($pImg, '/');
                        $fetched = fetchRemoteImage($remoteUrl);
                        if ($fetched) {
                            $pB64 = base64_encode($fetched['data']);
                            $pMime = $fetched['mime'];
                        }
                    }
                }
            }

            if (!empty($pB64)) {
                $processedProducts[] = [
                    'id' => $pId,
                    'name' => $pName ?: 'Luxury Piece',
                    'category' => $pCat,
                    'slot' => $pSlot,
                    'base64' => $pB64,
                    'mime' => $pMime
                ];
            }
        }

        if (empty($processedProducts)) {
            echo json_encode(['success' => false, 'error' => 'Could not load product image(s). Please select valid products and try again.']);
            exit;
        }

        // Determine if set has an outfit or is pure jewellery
        $hasOutfit = false;
        $jewelleryCount = 0;
        foreach ($processedProducts as $p) {
            $catLower = strtolower($p['category'] . ' ' . $p['name'] . ' ' . $p['slot']);
            if (strpos($catLower, 'outfit') !== false || strpos($catLower, 'lehenga') !== false || strpos($catLower, 'saree') !== false || strpos($catLower, 'blouse') !== false || strpos($catLower, 'gown') !== false) {
                $hasOutfit = true;
            } else {
                $jewelleryCount++;
            }
        }

        $allProductNames = implode(', ', array_map(function($p) { return $p['name']; }, $processedProducts));

        // Construct Intelligent Multi-Modal Gemini Prompt
        $promptParts = [
            "You are a world-class AI bridal stylist and virtual try-on specialist.",
            "Image 1 is the customer photo.",
            "The customer has selected a matching ensemble consisting of " . count($processedProducts) . " items to wear simultaneously:"
        ];

        // List each product with its image index and specific placement guide
        foreach ($processedProducts as $idx => $p) {
            $imgNum = $idx + 2; // Image 2, Image 3, etc.
            $pName = $p['name'];
            $catLower = strtolower($p['category'] . ' ' . $p['name'] . ' ' . $p['slot']);

            if (strpos($catLower, 'earring') !== false || strpos($catLower, 'bugadi') !== false || strpos($catLower, 'jhumk') !== false) {
                $placement = "to be worn on both ears";
            } elseif (strpos($catLower, 'necklace') !== false || strpos($catLower, 'choker') !== false || strpos($catLower, 'pendant') !== false || strpos($catLower, 'mala') !== false) {
                $placement = "to be worn around the neck and neckline / collarbone";
            } elseif (strpos($catLower, 'tikka') !== false || strpos($catLower, 'mathapatti') !== false || strpos($catLower, 'damini') !== false || strpos($catLower, 'borla') !== false) {
                $placement = "to be placed centered on the forehead along the hairline / parting";
            } elseif (strpos($catLower, 'bangle') !== false || strpos($catLower, 'bracelet') !== false || strpos($catLower, 'kada') !== false || strpos($catLower, 'hath phool') !== false) {
                $placement = "to be worn on the wrists / hands";
            } elseif (strpos($catLower, 'nath') !== false) {
                $placement = "to be worn as a delicate nose ring on the left nostril";
            } elseif (strpos($catLower, 'baju') !== false) {
                $placement = "to be worn as an armlet on the upper arm";
            } elseif (strpos($catLower, 'kamar') !== false) {
                $placement = "to be worn around the waistline";
            } elseif (strpos($catLower, 'hair') !== false) {
                $placement = "to be adorned gracefully in the hair";
            } elseif ($hasOutfit) {
                $placement = "to be worn as the main attire";
            } else {
                $placement = "to be worn naturally on the body";
            }

            $promptParts[] = "- Image {$imgNum}: [{$pName}] - {$placement}.";
        }

        $promptParts[] = "CRITICAL MANDATORY INSTRUCTIONS:";
        $promptParts[] = "1. COMBINE ALL PIECES: Seamlessly render all " . count($processedProducts) . " items from Image 2 to Image " . (count($processedProducts) + 1) . " onto the customer in Image 1 as a single, cohesive, unified luxury ensemble.";

        if (!$hasOutfit) {
            $promptParts[] = "2. DO NOT CHANGE EXISTING CLOTHING: The customer's existing top, shirt, t-shirt, neckline, fabric, and colors from Image 1 MUST remain exactly as they are. Do NOT replace their clothing with a saree or lehenga. Only add the selected jewellery items onto their body/skin/neck/ears/wrists.";
        } else {
            $promptParts[] = "2. OUTFIT REPLACEMENT: Replace only the clothing with the selected couture outfit from the reference image, preserving the customer's face and identity.";
        }

        $promptParts[] = "3. IDENTITY & POSTURE PRESERVATION: Preserve the person's exact face, facial features, eyes, skin tone, hairstyle, tattoos, and natural pose from Image 1.";
        $promptParts[] = "4. CRAFTSMANSHIP FIDELITY: Each piece's gold finish, gemstones, pearls, diamonds, and intricate details must match its reference image with 100% precision, casting realistic shadows.";
        $promptParts[] = "Ultra-realistic 8K photorealistic fashion portrait.";

        if (!empty($customNotes)) {
            $promptParts[] = "User custom notes: {$customNotes}.";
        }

        $fullPrompt = implode(' ', $promptParts);

        // Build media parts: prompt + user image + all product images
        $mediaParts = [['text' => $fullPrompt]];

        if (!empty($userImageB64)) {
            $mediaParts[] = [
                'inlineData' => [
                    'mimeType' => $userMime,
                    'data' => $userImageB64
                ]
            ];
        }

        foreach ($processedProducts as $p) {
            $mediaParts[] = [
                'inlineData' => [
                    'mimeType' => $p['mime'],
                    'data' => $p['base64']
                ]
            ];
        }

        $productName = $allProductNames;

        // Validate we have user photo and at least one product
        if (empty($userImageB64)) {
            ai_log("Validation error: user photo missing");
            echo json_encode(['success' => false, 'error' => 'Your photo could not be processed. Please re-upload a clear JPEG or PNG photo.']);
            exit;
        }
        if (empty($processedProducts)) {
            ai_log("Validation error: no processed products");
            echo json_encode(['success' => false, 'error' => 'Could not load product image(s). Please select a different piece and try again.']);
            exit;
        }

        ai_log("Initiating Gemini generation", [
            'products_count' => count($processedProducts),
            'product_names' => $productName,
            'user_id' => $userId,
            'ip' => $clientIp
        ]);

        $res = callGeminiImageGeneration($apiKey, $mediaParts, '2:3');

        if ($res['success']) {
            ai_log("Generation succeeded", ['model' => $res['model'], 'result_b64_len' => strlen($res['base64'])]);

            // Log usage (wrapped in try-catch so logging failure never kills the response)
            try {
                $logStmt = $pdo->prepare("INSERT INTO ai_tryon_usage (user_id, ip_address, user_name, user_email, user_phone, action_type, product_id, product_name) VALUES (?, ?, ?, ?, ?, 'wear_product', ?, ?)");
                $logStmt->execute([$userId, $clientIp, $userName, $userEmail, $userPhone, $productId, $productName]);
            } catch (Exception $logErr) {
                ai_log("Usage logging failed", ['error' => $logErr->getMessage()]);
            }

            $newUsed = $todayCount + 1;
            echo json_encode([
                'success' => true,
                'result_image' => 'data:image/jpeg;base64,' . $res['base64'],
                'product_name' => $productName,
                'model' => $res['model'],
                'used' => $newUsed,
                'limit' => $DAILY_LIMIT,
                'remaining' => max(0, $DAILY_LIMIT - $newUsed)
            ]);
        } else {
            ai_log("Generation failed", ['error' => $res['error'] ?? 'Unknown']);
            echo json_encode([
                'success' => false,
                'error' => 'AI Generation failed: ' . ($res['error'] ?? 'Please try with a clearer photo.')
            ]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
