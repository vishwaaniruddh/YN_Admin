<?php
// admin/api/ai_collection_sorter.php
header('Content-Type: application/json');

// Set higher execution time and memory for batch image analysis
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$secretsFile = __DIR__ . '/../config/secrets.php';
$apiKey = '';
if (file_exists($secretsFile)) {
    $secrets = include($secretsFile);
    $apiKey = $secrets['GEMINI_API_KEY'] ?? '';
}

if (empty($apiKey)) {
    // Check site_settings fallback
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'chatbot_gemini_api_key' LIMIT 1");
    $stmt->execute();
    $dbKey = $stmt->fetchColumn();
    if (!empty($dbKey) && strlen($dbKey) > 20) {
        $apiKey = $dbKey;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$collectionsBasePath = realpath(__DIR__ . '/../uploads/collections');

if (!$collectionsBasePath || !is_dir($collectionsBasePath)) {
    @mkdir(__DIR__ . '/../uploads/collections', 0777, true);
    $collectionsBasePath = realpath(__DIR__ . '/../uploads/collections');
}

/**
 * Helper: Format bytes to human readable string
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Helper: Generate downscaled JPEG Base64 for fast AI multimodal analysis
 */
function generate_optimized_base64_thumbnail($filePath, $maxDim = 512, $quality = 75) {
    if (!file_exists($filePath)) {
        return null;
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $img = null;

    if ($ext === 'jpg' || $ext === 'jpeg') {
        $img = @imagecreatefromjpeg($filePath);
    } elseif ($ext === 'png') {
        $img = @imagecreatefrompng($filePath);
    } elseif ($ext === 'webp') {
        if (function_exists('imagecreatefromwebp')) {
            $img = @imagecreatefromwebp($filePath);
        }
    }

    if (!$img) {
        // Fallback: raw file read if GD fails or file is small
        if (filesize($filePath) < 1.5 * 1024 * 1024) {
            $data = file_get_contents($filePath);
            $mime = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : 'image/jpeg');
            return [
                'mime_type' => $mime,
                'base64' => base64_encode($data)
            ];
        }
        return null;
    }

    $width = imagesx($img);
    $height = imagesy($img);

    if ($width > $maxDim || $height > $maxDim) {
        if ($width > $height) {
            $newWidth = $maxDim;
            $newHeight = (int)round(($height / $width) * $maxDim);
        } else {
            $newHeight = $maxDim;
            $newWidth = (int)round(($width / $height) * $maxDim);
        }
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    $resized = imagecreatetruecolor($newWidth, $newHeight);
    // Fill white background for transparent PNGs
    $white = imagecolorallocate($resized, 255, 255, 255);
    imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $white);

    imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    ob_start();
    imagejpeg($resized, null, $quality);
    $jpegData = ob_get_clean();

    imagedestroy($img);
    imagedestroy($resized);

    return [
        'mime_type' => 'image/jpeg',
        'base64' => base64_encode($jpegData)
    ];
}

/**
 * Helper: Call Gemini API with Fallback Models
 */
function call_gemini_vision_cluster($apiKey, $parts) {
    $models = [
        'gemini-1.5-flash',
        'gemini-2.5-flash',
        'gemini-flash-latest',
        'gemini-2.0-flash'
    ];

    $payload = json_encode([
        'contents' => [
            ['parts' => $parts]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'topP' => 0.95,
            'maxOutputTokens' => 16384,
            'responseMimeType' => 'application/json'
        ]
    ]);

    $lastError = '';

    foreach ($models as $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $lastError = "cURL error on model $model: $curlErr";
            continue;
        }

        if ($httpCode === 200) {
            $json = json_decode($response, true);
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (!empty($text)) {
                return ['success' => true, 'model' => $model, 'raw_text' => $text];
            }
        } else {
            $lastError = "HTTP $httpCode on model $model: $response";
        }
    }

    return ['success' => false, 'error' => $lastError ?: 'All Gemini models failed to respond.'];
}

try {
    // -------------------------------------------------------------------------
    // ACTION 1: List all collection categories & folder statistics
    // -------------------------------------------------------------------------
    if ($action === 'list_categories') {
        $dirs = scandir($collectionsBasePath);
        $categories = [];

        foreach ($dirs as $d) {
            if ($d === '.' || $d === '..') continue;
            $fullPath = $collectionsBasePath . DIRECTORY_SEPARATOR . $d;
            if (is_dir($fullPath)) {
                // Count direct images in this folder (unorganized)
                $items = scandir($fullPath);
                $unorganizedImages = [];
                $subfolders = [];

                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
                    if (is_dir($itemPath)) {
                        $subItems = scandir($itemPath);
                        $subImageCount = 0;
                        foreach ($subItems as $si) {
                            if (preg_match('/\.(jpg|jpeg|png|webp|gif|mp4|mov)$/i', $si)) {
                                $subImageCount++;
                            }
                        }
                        $subfolders[] = [
                            'name' => $item,
                            'path' => $item,
                            'images_count' => $subImageCount
                        ];
                    } elseif (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $item)) {
                        $unorganizedImages[] = $item;
                    }
                }

                // Check DB collections count for this category
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM collections WHERE category = ? OR category LIKE ?");
                $stmt->execute([$d, "%$d%"]);
                $dbCount = (int)$stmt->fetchColumn();

                // Get a preview thumbnail from unorganized or subfolders
                $previewImage = '';
                if (!empty($unorganizedImages)) {
                    $previewImage = 'uploads/collections/' . $d . '/' . $unorganizedImages[0];
                }

                $categories[] = [
                    'folder_name' => $d,
                    'category_name' => ucfirst($d),
                    'unorganized_count' => count($unorganizedImages),
                    'subfolders_count' => count($subfolders),
                    'db_outfits_count' => $dbCount,
                    'preview_image' => $previewImage ? get_collection_image_url($previewImage) : '',
                    'subfolders' => $subfolders
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'api_key_configured' => !empty($apiKey),
            'categories' => $categories
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // ACTION 2: Scan unorganized images in a specific category folder
    // -------------------------------------------------------------------------
    if ($action === 'scan_folder') {
        $folder = trim($_GET['folder'] ?? $_POST['folder'] ?? '');
        if (empty($folder)) {
            echo json_encode(['success' => false, 'error' => 'Folder parameter is required.']);
            exit;
        }

        $folderPath = $collectionsBasePath . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($folderPath)) {
            echo json_encode(['success' => false, 'error' => "Folder '$folder' does not exist."]);
            exit;
        }

        $items = scandir($folderPath);
        $images = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $filePath = $folderPath . DIRECTORY_SEPARATOR . $item;
            if (is_file($filePath) && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $item)) {
                $images[] = [
                    'filename' => $item,
                    'size' => filesize($filePath),
                    'size_formatted' => format_bytes(filesize($filePath)),
                    'url' => get_collection_image_url('uploads/collections/' . $folder . '/' . $item)
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'folder' => $folder,
            'total_images' => count($images),
            'images' => $images
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // ACTION 3: AI Cluster Batch using Gemini Vision
    // -------------------------------------------------------------------------
    if ($action === 'ai_cluster_batch') {
        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => 'Gemini API Key is missing. Please configure it in config/secrets.php.']);
            exit;
        }

        $rawInput = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $folder = trim($rawInput['folder'] ?? '');
        $filenames = $rawInput['filenames'] ?? [];
        $categoryContext = trim($rawInput['category_context'] ?? $folder);

        if (empty($folder) || empty($filenames) || !is_array($filenames)) {
            echo json_encode(['success' => false, 'error' => 'Folder and array of filenames are required.']);
            exit;
        }

        $folderPath = $collectionsBasePath . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($folderPath)) {
            echo json_encode(['success' => false, 'error' => "Folder '$folder' not found."]);
            exit;
        }

        // Support larger batch sizes up to 100 images per Gemini call
        $batch = array_slice($filenames, 0, 100);
        $parts = [];
        $validImageMap = [];

        $promptInstruction = "You are a world-class luxury Indian fashion designer and expert fashion catalog AI.\n" .
            "We have a mixed photoshoot batch of '{$categoryContext}' apparel images.\n\n" .
            "YOUR GOAL: Intelligently analyze the visual appearance of all provided images and GROUP/CLUSTER them by DISTINCT OUTFIT STYLE / DESIGN.\n\n" .
            "CRITICAL CLUSTERING RULES:\n" .
            "1. Photos that depict the SAME outfit/dress (front view, back view, close-up embroidery, model poses, side view, indoor/outdoor shoot angles of the same garment) MUST be grouped together into a single outfit cluster.\n" .
            "2. Distinguish outfits by matching: Fabric color/hue, embroidery pattern/materials (e.g. zardozi, mirror, sequins, gota patti, threadwork), neckline/back cut, and border motifs.\n" .
            "3. If an image is a standalone outfit with no matching views in this batch, put it in its own single-image outfit cluster.\n" .
            "4. For each outfit cluster, generate:\n" .
            "   - 'outfit_title': A rich, elegant, commercial title (e.g., 'Royal Crimson Velvet Zardozi Embroidered Blouse')\n" .
            "   - 'style_code': A short SKU code (e.g., 'BLS-" . strtoupper(substr(md5(uniqid()), 0, 4)) . "')\n" .
            "   - 'fabric': Primary fabric (e.g., 'Pure Raw Silk', 'Velvet', 'Organza', 'Georgette', 'Net', 'Banarasi Brocade')\n" .
            "   - 'work_type': Embroidery/technique (e.g., 'Hand Zardozi with Pearl & Dabka Work', 'Mirror & Resham Embroidery')\n" .
            "   - 'color': Primary and accent colors (e.g., 'Deep Emerald Green & Antique Gold')\n" .
            "   - 'description': An appealing 1-2 sentence luxury lookbook description.\n" .
            "   - 'suggested_folder': A clean URL-friendly kebab-case folder slug (e.g., 'royal-crimson-velvet-zardozi-blouse')\n" .
            "   - 'cover_image': The exact filename of the best hero / front-facing shot for this outfit.\n" .
            "   - 'images': Array of objects with 'filename' (exact string matching input) and 'angle_type' ('Front View', 'Back View', 'Close-up Detail', 'Side View', 'Model Shot', or 'Full View').\n\n" .
            "Return valid JSON adhering to this schema:\n" .
            "{\n" .
            "  \"outfits\": [\n" .
            "    {\n" .
            "      \"outfit_title\": \"...\",\n" .
            "      \"style_code\": \"...\",\n" .
            "      \"fabric\": \"...\",\n" .
            "      \"work_type\": \"...\",\n" .
            "      \"color\": \"...\",\n" .
            "      \"description\": \"...\",\n" .
            "      \"suggested_folder\": \"...\",\n" .
            "      \"cover_image\": \"filename.jpg\",\n" .
            "      \"images\": [\n" .
            "        {\"filename\": \"filename.jpg\", \"angle_type\": \"Front View\"}\n" .
            "      ]\n" .
            "    }\n" .
            "  ]\n" .
            "}\n\n" .
            "Here are the images with their exact filenames:\n";

        foreach ($batch as $idx => $fn) {
            $fPath = $folderPath . DIRECTORY_SEPARATOR . $fn;
            if (!file_exists($fPath)) continue;

            $thumb = generate_optimized_base64_thumbnail($fPath, 512, 75);
            if ($thumb) {
                $promptInstruction .= "[IMAGE #" . ($idx + 1) . ": \"$fn\"]\n";
                $validImageMap[$fn] = [
                    'filename' => $fn,
                    'url' => get_collection_image_url('uploads/collections/' . $folder . '/' . $fn)
                ];
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $thumb['mime_type'],
                        'data' => $thumb['base64']
                    ]
                ];
            }
        }

        if (empty($parts)) {
            echo json_encode(['success' => false, 'error' => 'No valid images could be processed.']);
            exit;
        }

        // Prepend text prompt
        array_unshift($parts, ['text' => $promptInstruction]);

        $aiRes = call_gemini_vision_cluster($apiKey, $parts);

        if (!$aiRes['success']) {
            echo json_encode(['success' => false, 'error' => $aiRes['error']]);
            exit;
        }

        $rawText = $aiRes['raw_text'];
        // Strip code fence if present
        $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
        $parsed = json_decode($cleanJson, true);

        if (!$parsed || !isset($parsed['outfits']) || !is_array($parsed['outfits'])) {
            // Fallback: try finding json object
            if (preg_match('/\{[\s\S]*\}/', $cleanJson, $m)) {
                $parsed = json_decode($m[0], true);
            }
        }

        if (!$parsed || !isset($parsed['outfits'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Could not parse JSON response from Gemini.',
                'raw_output' => $rawText
            ]);
            exit;
        }

        // Enrich the clustered outfits with real URLs and fallback handles
        $assignedImages = [];
        $outfits = [];

        foreach ($parsed['outfits'] as $cIdx => $outfit) {
            $oTitle = trim($outfit['outfit_title'] ?? 'Designer ' . ucfirst($categoryContext) . ' Style #' . ($cIdx + 1));
            $slug = trim($outfit['suggested_folder'] ?? generate_slug($oTitle));
            if (empty($slug)) $slug = 'outfit-style-' . ($cIdx + 1);

            $outfitImages = [];
            $rawImgs = $outfit['images'] ?? [];

            foreach ($rawImgs as $imgItem) {
                $fname = is_array($imgItem) ? ($imgItem['filename'] ?? '') : $imgItem;
                $angle = is_array($imgItem) ? ($imgItem['angle_type'] ?? 'View') : 'View';

                // Match exact or fuzzy filename
                if (isset($validImageMap[$fname])) {
                    $outfitImages[] = [
                        'filename' => $fname,
                        'url' => $validImageMap[$fname]['url'],
                        'angle_type' => $angle
                    ];
                    $assignedImages[$fname] = true;
                } else {
                    // Try basename or partial match in case Gemini cleaned the filename
                    foreach ($validImageMap as $origFn => $info) {
                        if (!isset($assignedImages[$origFn]) && (stripos($origFn, $fname) !== false || stripos($fname, $origFn) !== false)) {
                            $outfitImages[] = [
                                'filename' => $origFn,
                                'url' => $info['url'],
                                'angle_type' => $angle
                            ];
                            $assignedImages[$origFn] = true;
                            break;
                        }
                    }
                }
            }

            if (!empty($outfitImages)) {
                $coverImg = $outfit['cover_image'] ?? $outfitImages[0]['filename'];
                $coverUrl = $validImageMap[$coverImg]['url'] ?? $outfitImages[0]['url'];

                $outfits[] = [
                    'id' => 'cluster_' . uniqid(),
                    'outfit_title' => $oTitle,
                    'category' => ucfirst($folder),
                    'style_code' => $outfit['style_code'] ?? strtoupper(substr($folder, 0, 3)) . '-' . rand(100, 999),
                    'fabric' => $outfit['fabric'] ?? 'Premium Fabric',
                    'work_type' => $outfit['work_type'] ?? 'Handcrafted Detailing',
                    'color' => $outfit['color'] ?? 'Multicolor',
                    'description' => $outfit['description'] ?? '',
                    'folder_slug' => $slug,
                    'cover_image' => $coverImg,
                    'cover_url' => $coverUrl,
                    'images' => $outfitImages
                ];
            }
        }

        // Capture any unassigned images in this batch as individual or catch-all cluster
        $unassigned = [];
        foreach ($validImageMap as $fn => $info) {
            if (!isset($assignedImages[$fn])) {
                $unassigned[] = [
                    'filename' => $fn,
                    'url' => $info['url'],
                    'angle_type' => 'Full View'
                ];
            }
        }

        if (!empty($unassigned)) {
            $outfits[] = [
                'id' => 'cluster_misc_' . uniqid(),
                'outfit_title' => ucfirst($folder) . ' Curated Style',
                'category' => ucfirst($folder),
                'style_code' => strtoupper(substr($folder, 0, 3)) . '-' . rand(100, 999),
                'fabric' => 'Custom',
                'work_type' => 'Custom Detailing',
                'color' => 'Various',
                'description' => 'Curated piece from collection photoshoot.',
                'folder_slug' => generate_slug(ucfirst($folder) . '-style-' . time()),
                'cover_image' => $unassigned[0]['filename'],
                'cover_url' => $unassigned[0]['url'],
                'images' => $unassigned
            ];
        }

        echo json_encode([
            'success' => true,
            'model_used' => $aiRes['model'],
            'batch_count' => count($batch),
            'clusters_count' => count($outfits),
            'outfits' => $outfits
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // ACTION 4: Commit Outfits (Create Subfolders, Move/Copy Files, Insert into DB)
    // -------------------------------------------------------------------------
    if ($action === 'commit_outfits') {
        $rawInput = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $folder = trim($rawInput['folder'] ?? '');
        $outfits = $rawInput['outfits'] ?? [];
        $fileAction = $rawInput['file_action'] ?? 'move'; // 'move' or 'copy'
        $createSubfolders = isset($rawInput['create_subfolders']) ? (bool)$rawInput['create_subfolders'] : true;
        $saveDb = isset($rawInput['save_db']) ? (bool)$rawInput['save_db'] : true;

        if (empty($folder) || empty($outfits) || !is_array($outfits)) {
            echo json_encode(['success' => false, 'error' => 'Folder and outfits array are required.']);
            exit;
        }

        $folderPath = $collectionsBasePath . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($folderPath)) {
            echo json_encode(['success' => false, 'error' => "Category folder '$folder' does not exist."]);
            exit;
        }

        $createdCount = 0;
        $movedFilesCount = 0;
        $errors = [];

        $pdo->beginTransaction();

        try {
            $stmtInsertCollection = $pdo->prepare("INSERT INTO collections 
                (title, slug, sku, category, subtitle, description, fabric, work_type, color, cover_image, is_featured, sort_order, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmtInsertImage = $pdo->prepare("INSERT INTO collection_images 
                (collection_id, image_path, caption, angle_type, media_type, is_cover, sort_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            foreach ($outfits as $outfit) {
                $title = trim($outfit['outfit_title'] ?? 'Outfit Style');
                $slug = trim($outfit['folder_slug'] ?? generate_slug($title));
                $sku = trim($outfit['style_code'] ?? '');
                $category = trim($outfit['category'] ?? ucfirst($folder));
                $description = trim($outfit['description'] ?? '');
                $fabric = trim($outfit['fabric'] ?? '');
                $workType = trim($outfit['work_type'] ?? '');
                $color = trim($outfit['color'] ?? '');
                $coverFilename = trim($outfit['cover_image'] ?? '');
                $images = $outfit['images'] ?? [];

                if (empty($images)) continue;

                // Ensure unique slug in DB
                $testSlug = $slug;
                $counter = 1;
                while (true) {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM collections WHERE slug = ?");
                    $chk->execute([$testSlug]);
                    if ((int)$chk->fetchColumn() === 0) break;
                    $testSlug = $slug . '-' . (++$counter);
                }
                $finalSlug = $testSlug;

                // Target subfolder path on disk: uploads/collections/{category}/{finalSlug}/
                $targetSubfolderDir = $folderPath . DIRECTORY_SEPARATOR . $finalSlug;
                if ($createSubfolders && !is_dir($targetSubfolderDir)) {
                    @mkdir($targetSubfolderDir, 0777, true);
                }

                $coverDbPath = null;
                $processedMedia = [];

                foreach ($images as $idx => $img) {
                    $fn = is_array($img) ? ($img['filename'] ?? '') : $img;
                    $angle = is_array($img) ? ($img['angle_type'] ?? 'View') : 'View';

                    $srcFilePath = $folderPath . DIRECTORY_SEPARATOR . $fn;

                    // If file not in root of category, check if already in subfolder
                    if (!file_exists($srcFilePath)) {
                        // Check if file was already moved
                        $altPath = $targetSubfolderDir . DIRECTORY_SEPARATOR . $fn;
                        if (file_exists($altPath)) {
                            $srcFilePath = $altPath;
                        }
                    }

                    if (!file_exists($srcFilePath)) {
                        $errors[] = "File '$fn' not found on disk.";
                        continue;
                    }

                    $finalRelativePath = 'uploads/collections/' . $folder . '/' . $fn;

                    if ($createSubfolders) {
                        $destFilePath = $targetSubfolderDir . DIRECTORY_SEPARATOR . $fn;
                        if ($srcFilePath !== $destFilePath) {
                            if ($fileAction === 'copy') {
                                @copy($srcFilePath, $destFilePath);
                            } else {
                                @rename($srcFilePath, $destFilePath);
                            }
                            $movedFilesCount++;
                        }
                        $finalRelativePath = 'uploads/collections/' . $folder . '/' . $finalSlug . '/' . $fn;
                    }

                    $isCover = ($fn === $coverFilename || $idx === 0) ? 1 : 0;
                    if ($isCover && !$coverDbPath) {
                        $coverDbPath = $finalRelativePath;
                    }

                    $processedMedia[] = [
                        'path' => $finalRelativePath,
                        'caption' => pathinfo($fn, PATHINFO_FILENAME),
                        'angle_type' => $angle,
                        'is_cover' => $isCover,
                        'sort_order' => $idx + 1
                    ];
                }

                if (empty($processedMedia)) continue;

                if (!$coverDbPath) {
                    $coverDbPath = $processedMedia[0]['path'];
                    $processedMedia[0]['is_cover'] = 1;
                }

                if ($saveDb) {
                    // Insert Collection
                    $stmtInsertCollection->execute([
                        $title,
                        $finalSlug,
                        $sku,
                        $category,
                        $color ? "$fabric | $color" : $fabric,
                        $description,
                        $fabric,
                        $workType,
                        $color,
                        $coverDbPath,
                        0, // is_featured
                        $createdCount, // sort_order
                        'published'
                    ]);
                    $collectionId = $pdo->lastInsertId();

                    // Insert Images
                    foreach ($processedMedia as $pm) {
                        $stmtInsertImage->execute([
                            $collectionId,
                            $pm['path'],
                            $pm['caption'],
                            $pm['angle_type'],
                            'image',
                            $pm['is_cover'],
                            $pm['sort_order']
                        ]);
                    }
                }

                $createdCount++;
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'created_outfits_count' => $createdCount,
                'moved_files_count' => $movedFilesCount,
                'errors' => $errors,
                'message' => "Successfully organized $createdCount outfits and $movedFilesCount media files!"
            ]);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // ACTION 5: Create a new Category Folder on disk
    // -------------------------------------------------------------------------
    if ($action === 'create_category') {
        $catName = trim($_POST['category_name'] ?? '');
        if (empty($catName)) {
            echo json_encode(['success' => false, 'error' => 'Category name is required.']);
            exit;
        }

        $folderName = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $catName);
        $newDir = $collectionsBasePath . DIRECTORY_SEPARATOR . $folderName;

        if (is_dir($newDir)) {
            echo json_encode(['success' => false, 'error' => 'Category folder already exists.']);
            exit;
        }

        if (@mkdir($newDir, 0777, true)) {
            echo json_encode(['success' => true, 'folder_name' => $folderName, 'message' => "Category folder '$folderName' created."]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not create directory on server.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => "Unknown action '$action'."]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
