<?php
// admin/api/thumb.php - Ultra Fast Dynamic Image Resizer & WebP Cache
require_once __DIR__ . '/cors_header.php';

ini_set('memory_limit', '512M');
set_time_limit(30);

$src = $_GET['src'] ?? '';
$width = isset($_GET['w']) ? (int)$_GET['w'] : 0;
$height = isset($_GET['h']) ? (int)$_GET['h'] : 0;
$quality = isset($_GET['q']) ? max(10, min(100, (int)$_GET['q'])) : 80;

if (empty($src)) {
    http_response_code(400);
    exit('Missing src parameter');
}

// Clean source path
$src = urldecode($src);
$src = preg_replace('#^https?://[^/]+#', '', $src);
$src = preg_replace('#^/?(yn/)?(admin/)?#', '', $src);

// Potential disk locations for the image
$baseAdmin = dirname(__DIR__); // c:/xampp/htdocs/yn/admin
$candidatePaths = [
    $baseAdmin . '/' . ltrim($src, '/'),
    $baseAdmin . '/uploads/' . ltrim($src, '/'),
    $baseAdmin . '/uploads/collections/' . basename($src),
    $baseAdmin . '/uploads/products/' . basename($src),
    dirname($baseAdmin) . '/' . ltrim($src, '/')
];

$filePath = null;
foreach ($candidatePaths as $cp) {
    if (file_exists($cp) && is_file($cp)) {
        $filePath = $cp;
        break;
    }
}

if (!$filePath) {
    // If not found locally, return 404 or redirect
    http_response_code(404);
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300" viewBox="0 0 300 300"><rect width="300" height="300" fill="#f1f5f9"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#94a3b8" font-family="sans-serif" font-size="14">Image Not Found</text></svg>';
    exit();
}

// Limit dimensions
$width = max(0, min(2400, $width));
$height = max(0, min(2400, $height));

// If no resize is requested, serve directly
if ($width === 0 && $height === 0) {
    $mime = mime_content_type($filePath) ?: 'image/jpeg';
    header("Content-Type: $mime");
    header("Cache-Control: public, max-age=31536000, immutable");
    readfile($filePath);
    exit();
}

// Cache setup
$cacheDir = $baseAdmin . '/uploads/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$fileMTime = filemtime($filePath);
$cacheKey = md5($filePath . '_' . $fileMTime . '_w' . $width . '_h' . $height . '_q' . $quality);
$cacheFile = $cacheDir . '/' . $cacheKey . '.webp';

// HTTP Cache Headers Check
$etag = '"' . $cacheKey . '"';
header("ETag: $etag");
header("Cache-Control: public, max-age=31536000, immutable");

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit();
}

// If already cached on disk, stream immediately
if (file_exists($cacheFile) && filesize($cacheFile) > 0) {
    header("Content-Type: image/webp");
    header("Content-Length: " . filesize($cacheFile));
    readfile($cacheFile);
    exit();
}

// Generate Resized Image
$imgInfo = @getimagesize($filePath);
if (!$imgInfo) {
    header("Content-Type: image/jpeg");
    readfile($filePath);
    exit();
}

$origWidth = $imgInfo[0];
$origHeight = $imgInfo[1];
$mimeType = $imgInfo['mime'];

// Calculate target dimensions keeping aspect ratio
if ($width > 0 && $height === 0) {
    $targetWidth = min($width, $origWidth);
    $targetHeight = (int)round(($origHeight / $origWidth) * $targetWidth);
} elseif ($height > 0 && $width === 0) {
    $targetHeight = min($height, $origHeight);
    $targetWidth = (int)round(($origWidth / $origHeight) * $targetHeight);
} else {
    // Both specified: fit within box
    $scale = min($width / $origWidth, $height / $origHeight);
    if ($scale >= 1) {
        $targetWidth = $origWidth;
        $targetHeight = $origHeight;
    } else {
        $targetWidth = (int)round($origWidth * $scale);
        $targetHeight = (int)round($origHeight * $scale);
    }
}

// Load source image
$srcImg = null;
switch ($mimeType) {
    case 'image/jpeg':
    case 'image/jpg':
        $srcImg = @imagecreatefromjpeg($filePath);
        break;
    case 'image/png':
        $srcImg = @imagecreatefrompng($filePath);
        break;
    case 'image/webp':
        $srcImg = @imagecreatefromwebp($filePath);
        break;
}

if (!$srcImg) {
    header("Content-Type: $mimeType");
    readfile($filePath);
    exit();
}

// Correct orientation from EXIF if JPEG
if (($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') && function_exists('exif_read_data')) {
    $exif = @exif_read_data($filePath);
    if (!empty($exif['Orientation'])) {
        switch ($exif['Orientation']) {
            case 3:
                $srcImg = imagerotate($srcImg, 180, 0);
                break;
            case 6:
                $srcImg = imagerotate($srcImg, -90, 0);
                $tmpW = $targetWidth;
                $targetWidth = $targetHeight;
                $targetHeight = $tmpW;
                break;
            case 8:
                $srcImg = imagerotate($srcImg, 90, 0);
                $tmpW = $targetWidth;
                $targetWidth = $targetHeight;
                $targetHeight = $tmpW;
                break;
        }
    }
}

// Create destination canvas
$dstImg = imagecreatetruecolor($targetWidth, $targetHeight);

// Retain transparency for PNG/WebP
if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
    imagealphablending($dstImg, false);
    imagesavealpha($dstImg, true);
    $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
    imagefilledrectangle($dstImg, 0, 0, $targetWidth, $targetHeight, $transparent);
}

// High quality resampling
imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($srcImg), imagesy($srcImg));

// Save to WebP cache
if (function_exists('imagewebp')) {
    imagewebp($dstImg, $cacheFile, $quality);
    header("Content-Type: image/webp");
} else {
    imagejpeg($dstImg, $cacheFile, $quality);
    header("Content-Type: image/jpeg");
}

// Cleanup GD memory
imagedestroy($srcImg);
imagedestroy($dstImg);

// Output generated file
if (file_exists($cacheFile)) {
    header("Content-Length: " . filesize($cacheFile));
    readfile($cacheFile);
}
exit();
