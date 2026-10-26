<?php
/**
 * admin/api/compress_collections.php
 * Automated Image Shortener & Optimizer for collections upload directory
 */
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Allow adequate memory and execution time for processing high-res camera images
ini_set('memory_limit', '512M');
set_time_limit(180);

$baseDir = realpath(__DIR__ . '/../uploads/collections');
if (!$baseDir || !is_dir($baseDir)) {
    echo json_encode(['success' => false, 'message' => 'Collections upload directory not found']);
    exit();
}

$action = $_GET['action'] ?? 'scan';

function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Safely resolves relative path and ensures it stays inside $baseDir
 */
function resolve_collection_path($baseDir, $relPath) {
    $relPath = str_replace('\\', '/', trim($relPath));
    $relPath = ltrim($relPath, '/');
    if (str_starts_with($relPath, 'uploads/collections/')) {
        $relPath = substr($relPath, 20);
    } elseif (str_starts_with($relPath, 'admin/uploads/collections/')) {
        $relPath = substr($relPath, 26);
    }
    
    $fullPath = realpath($baseDir . '/' . $relPath);
    if (!$fullPath || !str_starts_with($fullPath, $baseDir)) {
        return false;
    }
    return $fullPath;
}

/**
 * Compresses an image file with Lossy or Lossless mode
 */
function process_image_compression($fullPath, $mode = 'lossy', $maxDim = 1800, $quality = null) {
    if (!file_exists($fullPath) || !is_readable($fullPath) || !is_writable($fullPath)) {
        return ['success' => false, 'message' => 'File not found or permissions denied'];
    }

    $origSize = filesize($fullPath);
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        return ['success' => false, 'message' => "Unsupported format: $ext"];
    }

    $imageInfo = @getimagesize($fullPath);
    if (!$imageInfo) {
        return ['success' => false, 'message' => 'Invalid or corrupt image header'];
    }

    $origW = $imageInfo[0];
    $origH = $imageInfo[1];
    $mime = $imageInfo['mime'];

    // Load source image
    $srcImg = null;
    switch ($mime) {
        case 'image/jpeg':
            $srcImg = @imagecreatefromjpeg($fullPath);
            break;
        case 'image/png':
            $srcImg = @imagecreatefrompng($fullPath);
            break;
        case 'image/webp':
            $srcImg = @imagecreatefromwebp($fullPath);
            break;
    }

    if (!$srcImg) {
        return ['success' => false, 'message' => 'GD failed to load image resource'];
    }

    // Auto-rotate if EXIF orientation exists (common in digital camera photos)
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($fullPath);
        if (!empty($exif['Orientation'])) {
            switch ((int)$exif['Orientation']) {
                case 3:
                    $rotated = imagerotate($srcImg, 180, 0);
                    if ($rotated) { imagedestroy($srcImg); $srcImg = $rotated; }
                    break;
                case 6:
                    $rotated = imagerotate($srcImg, -90, 0);
                    if ($rotated) { imagedestroy($srcImg); $srcImg = $rotated; $temp = $origW; $origW = $origH; $origH = $temp; }
                    break;
                case 8:
                    $rotated = imagerotate($srcImg, 90, 0);
                    if ($rotated) { imagedestroy($srcImg); $srcImg = $rotated; $temp = $origW; $origW = $origH; $origH = $temp; }
                    break;
            }
        }
    }

    $targetW = $origW;
    $targetH = $origH;
    $dstImg = $srcImg;
    $didResample = false;

    if ($mode === 'lossy') {
        $quality = $quality ? (int)$quality : 82;
        $maxDim = max(800, min(3840, (int)$maxDim));

        // Scale down if larger than max dimension
        if ($origW > $maxDim || $origH > $maxDim) {
            $ratio = min($maxDim / $origW, $maxDim / $origH, 1.0);
            $targetW = max(1, (int)round($origW * $ratio));
            $targetH = max(1, (int)round($origH * $ratio));

            $dstImg = imagecreatetruecolor($targetW, $targetH);
            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($dstImg, false);
                imagesavealpha($dstImg, true);
                $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
                imagefilledrectangle($dstImg, 0, 0, $targetW, $targetH, $transparent);
            }
            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);
            $didResample = true;
        }

        // For PNG photos, reduce palette if no transparency to avoid huge deflate sizes
        if ($mime === 'image/png') {
            @imagetruecolortopalette($dstImg, false, 256);
        }
    } else {
        // Lossless: Preserve original dimensions exactly, strip EXIF metadata
        $quality = $quality ? (int)$quality : 92;
        $targetW = $origW;
        $targetH = $origH;
    }

    // Save to temp file in same folder to ensure same disk partition for atomic rename
    $tempFile = dirname($fullPath) . '/.comp_tmp_' . uniqid() . '.' . $ext;
    $saved = false;

    switch ($mime) {
        case 'image/jpeg':
            $saved = imagejpeg($dstImg, $tempFile, $quality);
            break;
        case 'image/png':
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $saved = imagepng($dstImg, $tempFile, 9);
            break;
        case 'image/webp':
            $saved = imagewebp($dstImg, $tempFile, $quality);
            break;
    }

    // Cleanup GD resources
    if ($didResample && is_resource($dstImg) || is_object($dstImg)) {
        imagedestroy($dstImg);
    }
    if (is_resource($srcImg) || is_object($srcImg)) {
        imagedestroy($srcImg);
    }

    if (!$saved || !file_exists($tempFile)) {
        @unlink($tempFile);
        return ['success' => false, 'message' => 'Failed to save compressed image to disk'];
    }

    $newSize = filesize($tempFile);

    // Safety rule: Only replace if the new size is actually smaller!
    if ($newSize < $origSize) {
        // Atomic replace
        rename($tempFile, $fullPath);
        clearstatcache(true, $fullPath);

        $savedBytes = $origSize - $newSize;
        $savingsPct = round(($savedBytes / $origSize) * 100, 1);

        return [
            'success' => true,
            'unchanged' => false,
            'mode' => $mode,
            'old_size' => $origSize,
            'new_size' => $newSize,
            'old_size_fmt' => format_file_size($origSize),
            'new_size_fmt' => format_file_size($newSize),
            'saved_bytes' => $savedBytes,
            'saved_bytes_fmt' => format_file_size($savedBytes),
            'savings_percent' => $savingsPct,
            'dimensions' => "{$targetW} × {$targetH} px",
            'message' => "Saved {$savingsPct}% (" . format_file_size($savedBytes) . ")"
        ];
    } else {
        // Temp file is larger or equal; discard and keep original
        @unlink($tempFile);
        return [
            'success' => true,
            'unchanged' => true,
            'mode' => $mode,
            'old_size' => $origSize,
            'new_size' => $origSize,
            'old_size_fmt' => format_file_size($origSize),
            'new_size_fmt' => format_file_size($origSize),
            'saved_bytes' => 0,
            'saved_bytes_fmt' => '0 B',
            'savings_percent' => 0,
            'dimensions' => "{$origW} × {$origH} px",
            'message' => 'Already optimally compressed'
        ];
    }
}

try {
    // -------------------------------------------------------------
    // ACTION: SCAN
    // -------------------------------------------------------------
    if ($action === 'scan') {
        $minSizeKb = isset($_GET['min_size_kb']) ? (int)$_GET['min_size_kb'] : 0;
        $minSizeBytes = $minSizeKb * 1024;
        
        $filesIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
        );

        $images = [];
        $totalFiles = 0;
        $totalBytes = 0;
        $countGt10mb = 0;
        $countGt5mb = 0;
        $countGt2mb = 0;
        $countGt1mb = 0;
        $countGt500kb = 0;
        $heavyBytes = 0;

        foreach ($filesIterator as $file) {
            if (!$file->isFile()) continue;
            
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

            $size = $file->getSize();
            $totalFiles++;
            $totalBytes += $size;

            if ($size > 10 * 1048576) $countGt10mb++;
            if ($size > 5 * 1048576) $countGt5mb++;
            if ($size > 2 * 1048576) $countGt2mb++;
            if ($size > 1 * 1048576) {
                $countGt1mb++;
                $heavyBytes += $size;
            }
            if ($size > 500 * 1024) $countGt500kb++;

            if ($size >= $minSizeBytes) {
                $pathname = str_replace('\\', '/', $file->getPathname());
                $relPath = substr($pathname, strlen(str_replace('\\', '/', $baseDir)) + 1);
                $parts = explode('/', $relPath);
                $subfolder = count($parts) > 1 ? $parts[0] : 'root';

                $badgeType = 'success';
                if ($size > 5 * 1048576) {
                    $badgeType = 'danger';
                } elseif ($size > 1 * 1048576) {
                    $badgeType = 'warning';
                } elseif ($size > 500 * 1024) {
                    $badgeType = 'info';
                }

                $images[] = [
                    'id' => md5($relPath),
                    'rel_path' => $relPath,
                    'filename' => $file->getFilename(),
                    'folder' => $subfolder,
                    'size' => $size,
                    'size_fmt' => format_file_size($size),
                    'ext' => $ext,
                    'badge' => $badgeType,
                    'mtime' => $file->getMTime(),
                    'date_fmt' => date('d M Y, h:i A', $file->getMTime()),
                    'preview_url' => 'uploads/collections/' . $relPath
                ];
            }
        }

        // Sort descending by size (largest first)
        usort($images, fn($a, $b) => $b['size'] - $a['size']);

        // Populate dimensions for top images for immediate visual inspection
        $topCount = min(count($images), 100);
        for ($i = 0; $i < $topCount; $i++) {
            $fp = $baseDir . '/' . $images[$i]['rel_path'];
            $dim = @getimagesize($fp);
            if ($dim) {
                $images[$i]['dimensions'] = "{$dim[0]} × {$dim[1]} px";
            }
        }

        // Estimated savings: assuming ~85% reduction on files > 1MB
        $estSavingsBytes = (int)round($heavyBytes * 0.85);

        echo json_encode([
            'success' => true,
            'summary' => [
                'total_files' => $totalFiles,
                'total_size_bytes' => $totalBytes,
                'total_size_fmt' => format_file_size($totalBytes),
                'count_gt_10mb' => $countGt10mb,
                'count_gt_5mb' => $countGt5mb,
                'count_gt_2mb' => $countGt2mb,
                'count_gt_1mb' => $countGt1mb,
                'count_gt_500kb' => $countGt500kb,
                'heavy_size_bytes' => $heavyBytes,
                'heavy_size_fmt' => format_file_size($heavyBytes),
                'est_savings_bytes' => $estSavingsBytes,
                'est_savings_fmt' => format_file_size($estSavingsBytes)
            ],
            'files_count' => count($images),
            'files' => $images
        ]);
        exit();
    }

    // -------------------------------------------------------------
    // ACTION: COMPRESS SINGLE
    // -------------------------------------------------------------
    if ($action === 'compress_single' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        if (!$data) $data = $_POST;

        $relPath = $data['file'] ?? '';
        $mode = ($data['mode'] ?? 'lossy') === 'lossless' ? 'lossless' : 'lossy';
        $maxDim = (int)($data['max_dimension'] ?? 1800);
        $quality = isset($data['quality']) ? (int)$data['quality'] : null;

        if (empty($relPath)) {
            echo json_encode(['success' => false, 'message' => 'File path required']);
            exit();
        }

        $fullPath = resolve_collection_path($baseDir, $relPath);
        if (!$fullPath) {
            echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized file path']);
            exit();
        }

        $result = process_image_compression($fullPath, $mode, $maxDim, $quality);
        $result['rel_path'] = $relPath;
        $result['file_id'] = md5($relPath);

        echo json_encode($result);
        exit();
    }

    // -------------------------------------------------------------
    // ACTION: COMPRESS BATCH (Process small chunk, e.g. 5 files)
    // -------------------------------------------------------------
    if ($action === 'compress_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        if (!$data) $data = $_POST;

        $files = $data['files'] ?? [];
        $mode = ($data['mode'] ?? 'lossy') === 'lossless' ? 'lossless' : 'lossy';
        $maxDim = (int)($data['max_dimension'] ?? 1800);
        $quality = isset($data['quality']) ? (int)$data['quality'] : null;

        if (empty($files) || !is_array($files)) {
            echo json_encode(['success' => false, 'message' => 'No files array provided']);
            exit();
        }

        $batchResults = [];
        $totalSavedBytes = 0;
        $processedCount = 0;

        foreach ($files as $relPath) {
            $fullPath = resolve_collection_path($baseDir, $relPath);
            if (!$fullPath) {
                $batchResults[] = [
                    'rel_path' => $relPath,
                    'file_id' => md5($relPath),
                    'success' => false,
                    'message' => 'Path resolution failed'
                ];
                continue;
            }

            $res = process_image_compression($fullPath, $mode, $maxDim, $quality);
            $res['rel_path'] = $relPath;
            $res['file_id'] = md5($relPath);

            if (!empty($res['success'])) {
                $processedCount++;
                $totalSavedBytes += ($res['saved_bytes'] ?? 0);
            }
            $batchResults[] = $res;
        }

        echo json_encode([
            'success' => true,
            'processed' => $processedCount,
            'total_saved_bytes' => $totalSavedBytes,
            'total_saved_fmt' => format_file_size($totalSavedBytes),
            'results' => $batchResults
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
