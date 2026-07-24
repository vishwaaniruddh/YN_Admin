<?php
// admin/api/ai_product_api.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$secretsFile = __DIR__ . '/../config/secrets.php';
if (!file_exists($secretsFile)) {
    echo json_encode(['error' => 'Secrets configuration file missing.']);
    exit;
}
$secrets = include($secretsFile);
$apiKey = $secrets['GEMINI_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['error' => 'Gemini API Key is not configured in config/secrets.php.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$productId = (int)($_GET['product_id'] ?? $_GET['id'] ?? 0);

if ($productId <= 0) {
    // Attempt to read from JSON payload if not in query params
    $rawInput = json_decode(file_get_contents('php://input'), true);
    if (!empty($rawInput['product_id'])) {
        $productId = (int)$rawInput['product_id'];
    }
}

/**
 * Helper to fetch product primary image (main_image or first gallery image)
 */
function getProductImageData($pdo, $productId) {
    // 1. Check main_image in products table
    $stmt = $pdo->prepare("SELECT sku, name, main_image FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        return ['error' => 'Product not found.'];
    }

    $imagePath = $product['main_image'];

    // 2. Fallback to product_images table if main_image is empty
    if (empty($imagePath)) {
        $gstmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC LIMIT 1");
        $gstmt->execute([$productId]);
        $gimg = $gstmt->fetch();
        if ($gimg && !empty($gimg['image_path'])) {
            $imagePath = $gimg['image_path'];
        }
    }

    if (empty($imagePath)) {
        return ['error' => 'Product has no main image or gallery images to analyze.'];
    }

    // Resolve file locally or remotely
    $localPath = __DIR__ . '/../' . ltrim($imagePath, '/');
    $imgContent = null;
    $mimeType = 'image/jpeg';

    if (file_exists($localPath)) {
        $imgContent = file_get_contents($localPath);
        $mime = mime_content_type($localPath);
        if ($mime) $mimeType = $mime;
    } else {
        // Fallback check: root directory
        $altPath = __DIR__ . '/../../' . ltrim($imagePath, '/');
        if (file_exists($altPath)) {
            $imgContent = file_get_contents($altPath);
            $mime = mime_content_type($altPath);
            if ($mime) $mimeType = $mime;
        } else {
            // Try fetching via HTTP/HTTPS URL
            $remoteUrl = (str_starts_with($imagePath, 'http')) ? $imagePath : 'http://localhost/yn/admin/' . ltrim($imagePath, '/');
            $imgContent = @file_get_contents($remoteUrl);
            $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            if ($ext === 'png') $mimeType = 'image/png';
            elseif ($ext === 'webp') $mimeType = 'image/webp';
        }
    }

    if (empty($imgContent)) {
        return ['error' => 'Failed to load product image content for AI analysis.'];
    }

    return [
        'product' => $product,
        'image_content' => $imgContent,
        'mime_type' => $mimeType,
        'base64' => base64_encode($imgContent)
    ];
}

// Handler Dispatcher
switch ($action) {
    case 'ai_suggest_names':
        if ($productId <= 0) {
            echo json_encode(['error' => 'Product ID is required']);
            exit;
        }

        $imgDataRes = getProductImageData($pdo, $productId);
        if (isset($imgDataRes['error'])) {
            echo json_encode(['error' => $imgDataRes['error']]);
            exit;
        }

        $prompt = "You are a professional fashion copywriter for Yosshita & Neha / Srishringarr. " .
                  "Analyze the product in the image. Suggest exactly 5 descriptive product names (each name MUST be at least 10 words long) suitable for this fashion item. " .
                  "Use very simple, clear, and easy-to-understand English. Do NOT use complex, rare, fancy, flowery, or poetic words (such as 'ethereal', 'wisteria', 'intricately', 'enchanted', 'resplendent', 'mystique', 'regal', etc.). " .
                  "Instead, use common, everyday words to describe the product's colors, materials, design, embroidery, and style. " .
                  "Each name MUST have at least 10 words. " .
                  "Example of expected output format and style: " .
                  "\"Beautiful red lehenga choli for wedding functions with heavy gold embroidery and a matching net dupatta\" or " .
                  "\"Traditional gold plated necklace set with green beads and matching earrings for party wear\". " .
                  "Return ONLY a raw JSON array of strings containing the 5 suggested names. Do not include markdown code block formatting (no ```json, no ```).";

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $apiKey;
        $payload = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inlineData' => [
                                'mimeType' => $imgDataRes['mime_type'],
                                'data' => $imgDataRes['base64']
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo json_encode(['error' => 'Gemini API request failed: ' . $response]);
            exit;
        }

        $decoded = json_decode($response, true);
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = trim(preg_replace('/^```json|```$/', '', trim($text)));
        $names = json_decode($text, true);

        if (!is_array($names)) {
            preg_match_all('/"(.*?)"/', $text, $matches);
            $names = !empty($matches[1]) ? array_slice($matches[1], 0, 5) : [];
        }

        echo json_encode(['success' => true, 'names' => $names]);
        break;

    case 'ai_suggest_description':
        if ($productId <= 0) {
            echo json_encode(['error' => 'Product ID is required']);
            exit;
        }

        $maxWords = (int)($_GET['max_words'] ?? 100);
        if ($maxWords < 10) $maxWords = 10;
        if ($maxWords > 1000) $maxWords = 1000;

        $imgDataRes = getProductImageData($pdo, $productId);
        if (isset($imgDataRes['error'])) {
            echo json_encode(['error' => $imgDataRes['error']]);
            exit;
        }

        $prompt = "You are a professional luxury fashion brand copywriter for Yosshita & Neha. " .
                  "Analyze the product in the image. Write a detailed, premium, and compelling product description for this fashion item. " .
                  "The total description MUST NOT exceed $maxWords words. Be extremely concise if the word count limit is small. " .
                  "Structure the response to have:\n" .
                  "1. A compelling description paragraph introducing the item, emphasizing its visual elegance, style, and suitability for weddings, receptions, sangeets, or special occasions.\n" .
                  "2. A section titled 'Key Features:' followed by bullet points detailing specific design details, craftsmanship, embroidery/sequins/beading, fabric/metal materials, and accessories as visible or appropriate for this item.\n" .
                  "CRITICAL FORMATTING RULES FOR PLAIN TEXT:\n" .
                  "- Do not use any markdown tags (no '**', no '*', no '__', no '#').\n" .
                  "- For bullet points, start each bullet item with a literal bullet character '•' followed by a space (e.g., '• Feature Name: Feature description.').\n" .
                  "- Simply write headings as plain text (e.g., 'Key Features:').\n" .
                  "Do not include any placeholders, conversational text, or greetings. Return ONLY the clean plain text of description and key features.";

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $apiKey;
        $payload = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inlineData' => [
                                'mimeType' => $imgDataRes['mime_type'],
                                'data' => $imgDataRes['base64']
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo json_encode(['error' => 'Gemini API request failed: ' . $response]);
            exit;
        }

        $decoded = json_decode($response, true);
        $description = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

        echo json_encode(['success' => true, 'description' => trim($description)]);
        break;

    case 'ai_generate_model_image':
        if ($productId <= 0) {
            echo json_encode(['error' => 'Product ID is required']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $basePrompt = $input['prompt'] ?? 'A photorealistic beautiful Indian fashion model wearing this exact product. The model should have open flowing hair. The background should have elegant props like a palace or traditional setting that compliments the jewelry perfectly. Do not change the product details.';
        $faceReference = $input['face_reference'] ?? '';
        $numImages = (int)($input['num_images'] ?? 1);
        if ($numImages < 1) $numImages = 1;
        if ($numImages > 4) $numImages = 4;

        $imgDataRes = getProductImageData($pdo, $productId);
        if (isset($imgDataRes['error'])) {
            echo json_encode(['error' => $imgDataRes['error']]);
            exit;
        }

        $mediaParts = [
            [
                'inlineData' => [
                    'mimeType' => $imgDataRes['mime_type'],
                    'data' => $imgDataRes['base64']
                ]
            ]
        ];

        // Add face reference if selected
        if (!empty($faceReference)) {
            $facePath = __DIR__ . '/../assets/models/' . basename($faceReference);
            if (file_exists($facePath)) {
                $faceContent = file_get_contents($facePath);
                $faceMime = mime_content_type($facePath) ?: 'image/png';
                $mediaParts[] = [
                    'inlineData' => [
                        'mimeType' => $faceMime,
                        'data' => base64_encode($faceContent)
                    ]
                ];
            }
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image:generateContent?key=' . $apiKey;
        $generatedImages = [];
        $errors = [];

        $variations = [
            '', 
            ' Also, ensure a slight side-profile angle.',
            ' Also, ensure a dynamic and confident fashion pose.',
            ' Also, ensure a different elegant camera angle.'
        ];

        for ($i = 0; $i < $numImages; $i++) {
            $currentPrompt = $basePrompt . ($variations[$i] ?? '');
            $parts = array_merge([['text' => $currentPrompt]], $mediaParts);
            
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
                $b64 = $decoded['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
                if ($b64) {
                    $generatedImages[] = $b64;
                } else {
                    $errors[] = 'No image data returned in variation ' . ($i + 1);
                }
            } else {
                $errObj = json_decode($response, true);
                $errMsg = $errObj['error']['message'] ?? 'API request failed';
                $errors[] = 'API Error variation ' . ($i + 1) . ': ' . $errMsg;
            }
        }

        if (count($generatedImages) > 0) {
            echo json_encode(['success' => true, 'images_base64' => $generatedImages, 'partial_errors' => $errors]);
        } else {
            echo json_encode(['error' => implode(' | ', $errors)]);
        }
        break;

    case 'save_ai_image':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        if ($productId <= 0) {
            echo json_encode(['error' => 'Product ID is required']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $b64 = $input['image_base64'] ?? '';

        if (empty($b64)) {
            echo json_encode(['error' => 'Image data (base64) is required']);
            exit;
        }

        $imgBinary = base64_decode($b64);
        if (!$imgBinary) {
            echo json_encode(['error' => 'Invalid image base64 data']);
            exit;
        }

        // Get product SKU to structure directory
        $stmt = $pdo->prepare("SELECT sku FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch();
        $sku = $prod['sku'] ?? ('product_' . $productId);

        $uploadFolder = __DIR__ . '/../uploads/products/' . $sku;
        $thumbFolder = $uploadFolder . '/thumbs';

        if (!file_exists($uploadFolder)) {
            mkdir($uploadFolder, 0777, true);
        }
        if (!file_exists($thumbFolder)) {
            mkdir($thumbFolder, 0777, true);
        }

        $filename = 'ai_' . time() . '_' . rand(100, 999) . '.jpg';
        $fullSavePath = $uploadFolder . '/' . $filename;
        
        if (file_put_contents($fullSavePath, $imgBinary) === false) {
            echo json_encode(['error' => 'Failed to write image file to disk']);
            exit;
        }

        $relativePath = 'uploads/products/' . $sku . '/' . $filename;
        $thumbFilename = 'thumb_' . $filename;
        $fullThumbPath = $thumbFolder . '/' . $thumbFilename;
        $relativeThumbPath = $relativePath;

        // Generate 150x150 square thumbnail matching YN image uploads
        if (function_exists('generate_square_thumbnail')) {
            if (generate_square_thumbnail($fullSavePath, $fullThumbPath, 150)) {
                $relativeThumbPath = 'uploads/products/' . $sku . '/thumbs/' . $thumbFilename;
            }
        }

        // Get current max sort order
        $soStmt = $pdo->prepare("SELECT MAX(sort_order) FROM product_images WHERE product_id = ?");
        $soStmt->execute([$productId]);
        $maxSort = (int)$soStmt->fetchColumn();

        // Insert into product_images table with valid image_path and thumb_path
        $insStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, thumb_path, sort_order) VALUES (?, ?, ?, ?)");
        $insStmt->execute([$productId, $relativePath, $relativeThumbPath, $maxSort + 1]);
        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => $newId,
            'path' => $relativePath,
            'thumb_path' => $relativeThumbPath,
            'sku' => $sku
        ]);
        break;

    default:
        echo json_encode(['error' => 'Invalid or specified action action']);
        break;
}
