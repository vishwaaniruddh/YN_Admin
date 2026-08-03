<?php
// admin/api/sync_push_api.php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$input_raw = file_get_contents('php://input');
$data = json_decode($input_raw, true);

if (empty($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$secretKey = 'ss_yn_sync_secret_2026';
$passedSecret = $data['secret'] ?? '';

if ($passedSecret !== $secretKey) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access: Invalid secret key']);
    exit;
}

$sku = trim($data['sku'] ?? '');
if (empty($sku)) {
    echo json_encode(['success' => false, 'message' => 'SKU is required']);
    exit;
}

// 1. Target Directory inside Yosshitaneha admin uploads
$baseDir = __DIR__ . '/../uploads/products/' . $sku . '/';
$thumbDir = $baseDir . 'thumbs/';

if (!is_dir($baseDir)) {
    @mkdir($baseDir, 0755, true);
}
if (!is_dir($thumbDir)) {
    @mkdir($thumbDir, 0755, true);
}

// 2. Download Images via cURL
$images = $data['images'] ?? [];
$mainImageRelative = null;
$galleryImages = [];

foreach ($images as $idx => $imgUrl) {
    if (empty($imgUrl)) continue;

    $filename = basename(parse_url($imgUrl, PHP_URL_PATH));
    $destFile = $baseDir . $filename;
    $thumbFile = $thumbDir . $filename;

    $dbImgPath = 'uploads/products/' . $sku . '/' . $filename;
    $dbThumbPath = 'uploads/products/' . $sku . '/thumbs/' . $filename;

    if (!file_exists($destFile) || filesize($destFile) < 100) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $imgUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        ]);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200 && $content && strlen($content) > 100) {
            @file_put_contents($destFile, $content);

            // Thumbnail creation
            $savedThumb = false;
            if (function_exists('imagecreatefromstring')) {
                $gdImg = @imagecreatefromstring($content);
                if ($gdImg) {
                    $w = imagesx($gdImg);
                    $h = imagesy($gdImg);
                    $maxDim = 300;
                    if ($w > $maxDim || $h > $maxDim) {
                        $ratio = min($maxDim / $w, $maxDim / $h);
                        $newW = (int)($w * $ratio);
                        $newH = (int)($h * $ratio);
                        $thumbGd = imagecreatetruecolor($newW, $newH);
                        imagecopyresampled($thumbGd, $gdImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                        @imagejpeg($thumbGd, $thumbFile, 85);
                        $savedThumb = true;
                    }
                }
            }
            if (!$savedThumb) {
                @file_put_contents($thumbFile, $content);
            }
        }
    }

    if (!$mainImageRelative) {
        $mainImageRelative = $dbImgPath;
    }
    $galleryImages[] = [
        'image_path' => $dbImgPath,
        'thumb_path' => $dbThumbPath
    ];
}

// 3. Database UPSERT
$categoryId = null;
$catName = trim($data['category_name'] ?? '');
if (!empty($catName)) {
    $catSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($catName));
    $stmtCat = $pdo->prepare("SELECT id FROM categories WHERE name = ? OR slug = ? LIMIT 1");
    $stmtCat->execute([$catName, $catSlug]);
    $catRow = $stmtCat->fetch();
    if ($catRow) {
        $categoryId = $catRow['id'];
    } else {
        $stmtInsCat = $pdo->prepare("INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)");
        $stmtInsCat->execute([$catName, $catSlug, 'Auto-synced category']);
        $categoryId = $pdo->lastInsertId();
    }
}

$name = trim($data['name'] ?? ('Product ' . $sku));
$slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name)) . '-' . strtolower($sku);
$description = trim($data['description'] ?? '');
$shortDesc = mb_substr(strip_tags($description), 0, 200);
$price = (float)($data['price'] ?? 0);
$salePrice = isset($data['sale_price']) ? (float)$data['sale_price'] : null;
$stockQty = (int)($data['stock_qty'] ?? 10);
$status = $data['status'] ?? 'published';

$stmtCheck = $pdo->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
$stmtCheck->execute([$sku]);
$existing = $stmtCheck->fetch();

if ($existing) {
    $childProductId = $existing['id'];
    $stmtUpd = $pdo->prepare("UPDATE products SET category_id = ?, name = ?, slug = ?, description = ?, short_description = ?, price = ?, sale_price = ?, stock_qty = ?, status = ?, main_image = ?, updated_at = NOW() WHERE id = ?");
    $stmtUpd->execute([$categoryId, $name, $slug, $description, $shortDesc, $price, $salePrice, $stockQty, $status, $mainImageRelative, $childProductId]);
} else {
    $stmtIns = $pdo->prepare("INSERT INTO products (category_id, name, slug, sku, description, short_description, price, sale_price, stock_qty, status, main_image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmtIns->execute([$categoryId, $name, $slug, $sku, $description, $shortDesc, $price, $salePrice, $stockQty, $status, $mainImageRelative]);
    $childProductId = $pdo->lastInsertId();
}

// 4. Update Gallery DB entries
if ($childProductId) {
    $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$childProductId]);
    if (!empty($galleryImages)) {
        $stmtImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, thumb_path, sort_order) VALUES (?, ?, ?, ?)");
        $sort = 0;
        foreach ($galleryImages as $gi) {
            $stmtImg->execute([$childProductId, $gi['image_path'], $gi['thumb_path'], $sort++]);
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => "Successfully synced $sku and " . count($galleryImages) . " images to Child Store",
    'child_product_id' => $childProductId
]);
