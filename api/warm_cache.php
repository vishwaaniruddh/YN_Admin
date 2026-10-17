<?php
// admin/api/warm_cache.php - Pre-compress and Warm Image Cache for Collection & Lookbook Images
ini_set('memory_limit', '1024M');
set_time_limit(600);

require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';

$cacheDir = __DIR__ . '/../uploads/cache';
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

function warm_single_image($relPath, $targetW, $quality, $cacheDir) {
    $clean = ltrim($relPath, '/');
    $baseAdmin = dirname(__DIR__);
    $candidatePaths = [
        $baseAdmin . '/' . $clean,
        $baseAdmin . '/uploads/' . $clean,
        $baseAdmin . '/uploads/collections/' . basename($clean),
        $baseAdmin . '/uploads/products/' . basename($clean),
        dirname($baseAdmin) . '/' . $clean
    ];

    $filePath = null;
    foreach ($candidatePaths as $cp) {
        if (file_exists($cp) && is_file($cp)) {
            $filePath = $cp;
            break;
        }
    }

    if (!$filePath) return false;

    $fileMTime = filemtime($filePath);
    $cacheKey = md5($filePath . '_' . $fileMTime . '_w' . $targetW . '_h0_q' . $quality);
    $cacheFile = $cacheDir . '/' . $cacheKey . '.webp';

    if (file_exists($cacheFile) && filesize($cacheFile) > 0) {
        return true; // Already cached
    }

    $imgInfo = @getimagesize($filePath);
    if (!$imgInfo) return false;

    $origW = $imgInfo[0];
    $origH = $imgInfo[1];
    $mimeType = $imgInfo['mime'];

    $w = min($targetW, $origW);
    $h = (int)round(($origH / $origW) * $w);

    $src = null;
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $src = @imagecreatefromjpeg($filePath);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($filePath);
            break;
        case 'image/webp':
            $src = @imagecreatefromwebp($filePath);
            break;
    }
    if (!$src) return false;

    // EXIF orientation
    if (($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') && function_exists('exif_read_data')) {
        $exif = @exif_read_data($filePath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3: $src = imagerotate($src, 180, 0); break;
                case 6: $src = imagerotate($src, -90, 0); $tmp = $w; $w = $h; $h = $tmp; break;
                case 8: $src = imagerotate($src, 90, 0); $tmp = $w; $w = $h; $h = $tmp; break;
            }
        }
    }

    if (function_exists('imagescale') && $mimeType !== 'image/png' && $mimeType !== 'image/webp') {
        $dst = imagescale($src, $w, $h, IMG_BICUBIC);
    } else {
        $dst = imagecreatetruecolor($w, $h);
        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
    }

    if (function_exists('imagewebp')) {
        imagewebp($dst, $cacheFile, $quality);
    } else {
        imagejpeg($dst, $cacheFile, $quality);
    }

    imagedestroy($src);
    imagedestroy($dst);
    return true;
}

// Get collection images
$slug = trim($_GET['slug'] ?? '');
if ($slug !== '') {
    $stmt = $pdo->prepare("SELECT ci.image_path FROM collection_images ci JOIN collections c ON ci.collection_id = c.id WHERE c.slug = ? OR c.sku = ?");
    $stmt->execute([$slug, $slug]);
} else {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $stmt = $pdo->query("SELECT image_path FROM collection_images WHERE image_path IS NOT NULL ORDER BY id DESC LIMIT $limit");
}

$images = $stmt->fetchAll(PDO::FETCH_COLUMN);
$processed = 0;
$cached = 0;
$failed = 0;

foreach ($images as $img) {
    if (empty($img)) continue;
    $processed++;
    // Generate thumbnail (100px) and modal size (900px)
    $tRes = warm_single_image($img, 100, 65, $cacheDir);
    $mRes = warm_single_image($img, 900, 75, $cacheDir);
    if ($tRes && $mRes) {
        $cached++;
    } else {
        $failed++;
    }
}

if (php_sapi_name() === 'cli') {
    echo "Done! Processed: $processed images ($cached cached, $failed skipped/failed)\n";
} else {
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'cached' => $cached,
        'failed' => $failed
    ]);
}
