<?php
// admin/generate-catalogue-pdf.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

set_time_limit(240);
ini_set('memory_limit', '512M');

// Read Input Payload
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['pdf_payload'])) {
        $input = json_decode($_POST['pdf_payload'], true);
    } else {
        $rawPost = file_get_contents('php://input');
        if (!empty($rawPost)) {
            $input = json_decode($rawPost, true);
        }
        if (empty($input)) {
            $input = $_POST;
        }
    }
}

$brandTitle = !empty($input['brand_title']) ? trim($input['brand_title']) : 'YosshitaNeha Fashion Studio';
$brandSubtitle = !empty($input['brand_subtitle']) ? trim($input['brand_subtitle']) : 'The Ultimate Fashion Destination';
$catalogueTitle = !empty($input['catalogue_title']) ? trim($input['catalogue_title']) : 'Product Catalogue';
$clientName = !empty($input['client_name']) ? trim($input['client_name']) : '';
$includeCover = isset($input['include_cover']) ? (bool)$input['include_cover'] : true;
$showProductLink = isset($input['show_product_link']) ? (bool)$input['show_product_link'] : true;
$layout = !empty($input['layout']) ? trim($input['layout']) : 'showcase'; // showcase, hero, grid
$showPrice = isset($input['show_price']) ? (bool)$input['show_price'] : true;
$showSku = isset($input['show_sku']) ? (bool)$input['show_sku'] : true;
$showCategory = isset($input['show_category']) ? (bool)$input['show_category'] : false;
$customNotes = !empty($input['custom_notes']) ? trim($input['custom_notes']) : '';
$outputMode = !empty($input['output_mode']) ? trim($input['output_mode']) : 'pdf'; // pdf, preview, html
$products = isset($input['products']) && is_array($input['products']) ? $input['products'] : [];

if (empty($products)) {
    die("<h3>No products selected for PDF generation. Please select at least one product.</h3><p><a href='pdf-maker.php'>&larr; Back to PDF Maker</a></p>");
}

// Flatten all items to display (if multiple angles selected per product, treat each angle or product as a slot)
$itemsToDisplay = [];
foreach ($products as $p) {
    $selectedImgs = !empty($p['selected_images']) && is_array($p['selected_images']) ? $p['selected_images'] : [$p['main_image']];
    $prodSlug = !empty($p['slug']) ? $p['slug'] : $p['sku'];
    $prodUrl = !empty($p['product_url']) ? $p['product_url'] : ('https://yosshitaneha.com/product/' . urlencode($prodSlug));

    foreach ($selectedImgs as $img) {
        $itemsToDisplay[] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'sku' => $p['sku'],
            'price' => $p['price'],
            'sale_price' => $p['sale_price'],
            'category_name' => $p['category_name'] ?? '',
            'image' => $img,
            'url' => $prodUrl
        ];
    }
}

/**
 * Standardize & normalize product image for Dompdf
 * 1. Resolves local path or live server URL.
 * 2. Samples 4 corners to detect image background (black / white / average).
 * 3. Proportional resize into uniform target canvas (prevents height mismatches and white gaps).
 * 4. Caches in uploads/cache/ to make subsequent generation blazing fast.
 */
function get_image_base64_for_pdf($imageRelPath, $layout = 'showcase') {
    if (empty($imageRelPath)) return '';
    $imageRelPath = trim((string)$imageRelPath);

    // Target dimensions based on layout
    if ($layout === 'hero') {
        $targetW = 750;
        $targetH = 900;
    } elseif ($layout === 'grid') {
        $targetW = 500;
        $targetH = 500;
    } else { // showcase (default)
        $targetW = 600;
        $targetH = 750;
    }

    // Check disk cache first
    $cacheDir = __DIR__ . '/uploads/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    $cacheKey = md5($imageRelPath . '_' . $targetW . 'x' . $targetH);
    $cacheFile = $cacheDir . '/pdf_norm_' . $cacheKey . '.jpg';

    if (file_exists($cacheFile) && filesize($cacheFile) > 100) {
        $cachedData = @file_get_contents($cacheFile);
        if ($cachedData) {
            return 'data:image/jpeg;base64,' . base64_encode($cachedData);
        }
    }

    // 1. Clean path to check local filesystem
    $cleanPath = preg_replace('#^https?://(localhost(:[0-9]+)?|yosshitaneha\.com)(/yn)?(/admin)?/?#i', '', $imageRelPath);
    $cleanPath = ltrim($cleanPath, '/');

    $possiblePaths = [
        __DIR__ . '/' . $cleanPath,
        __DIR__ . '/../' . $cleanPath,
        __DIR__ . '/uploads/products/' . $cleanPath,
        __DIR__ . '/assets/' . $cleanPath
    ];

    $rawContent = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_file($path)) {
            $rawContent = @file_get_contents($path);
            break;
        }
    }

    // 2. Fallback to live server
    if (!$rawContent) {
        $liveUrl = $imageRelPath;
        if (!str_starts_with($imageRelPath, 'http') || str_contains($imageRelPath, 'localhost')) {
            $liveUrl = 'https://yosshitaneha.com/admin/' . $cleanPath;
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $rawContent = @file_get_contents($liveUrl, false, $ctx);
    }

    if (!$rawContent) return '';

    // If GD is available, normalize onto uniform canvas with detected background
    if (extension_loaded('gd')) {
        $srcImg = @imagecreatefromstring($rawContent);
        if ($srcImg) {
            $srcW = imagesx($srcImg);
            $srcH = imagesy($srcImg);

            // Sample corner colors
            $c1 = imagecolorat($srcImg, 0, 0);
            $c2 = imagecolorat($srcImg, max(0, $srcW - 1), 0);
            $c3 = imagecolorat($srcImg, 0, max(0, $srcH - 1));
            $c4 = imagecolorat($srcImg, max(0, $srcW - 1), max(0, $srcH - 1));

            $rgb1 = imagecolorsforindex($srcImg, $c1);
            $rgb2 = imagecolorsforindex($srcImg, $c2);
            $rgb3 = imagecolorsforindex($srcImg, $c3);
            $rgb4 = imagecolorsforindex($srcImg, $c4);

            $avgR = (int)(($rgb1['red'] + $rgb2['red'] + $rgb3['red'] + $rgb4['red']) / 4);
            $avgG = (int)(($rgb1['green'] + $rgb2['green'] + $rgb3['green'] + $rgb4['green']) / 4);
            $avgB = (int)(($rgb1['blue'] + $rgb2['blue'] + $rgb3['blue'] + $rgb4['blue']) / 4);

            // Snap near-black or near-white
            if ($avgR <= 35 && $avgG <= 35 && $avgB <= 35) {
                $bgR = 0; $bgG = 0; $bgB = 0;
            } elseif ($avgR >= 235 && $avgG >= 235 && $avgB >= 235) {
                $bgR = 255; $bgG = 255; $bgB = 255;
            } else {
                $bgR = $avgR; $bgG = $avgG; $bgB = $avgB;
            }

            // Create standardized canvas
            $dstImg = imagecreatetruecolor($targetW, $targetH);
            $bgCol = imagecolorallocate($dstImg, $bgR, $bgG, $bgB);
            imagefilledrectangle($dstImg, 0, 0, $targetW, $targetH, $bgCol);

            // Proportional centering
            $scale = min($targetW / $srcW, $targetH / $srcH);
            $newW = (int)round($srcW * $scale);
            $newH = (int)round($srcH * $scale);
            $dstX = (int)round(($targetW - $newW) / 2);
            $dstY = (int)round(($targetH - $newH) / 2);

            imagecopyresampled($dstImg, $srcImg, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);

            ob_start();
            imagejpeg($dstImg, null, 85);
            $jpegData = ob_get_clean();

            imagedestroy($srcImg);
            imagedestroy($dstImg);

            // Save to disk cache
            @file_put_contents($cacheFile, $jpegData);

            return 'data:image/jpeg;base64,' . base64_encode($jpegData);
        }
    }

    // Fallback: encode raw content
    $ext = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : 'image/jpeg');
    return 'data:' . $mime . ';base64,' . base64_encode($rawContent);
}

// Chunk items based on selected layout
if ($layout === 'hero') {
    $chunkSize = 1;
} elseif ($layout === 'grid') {
    $chunkSize = 4;
} else {
    $chunkSize = 2; // showcase
}
$itemChunks = array_chunk($itemsToDisplay, $chunkSize);
$totalChunks = count($itemChunks);

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($catalogueTitle); ?> - <?php echo htmlspecialchars($brandTitle); ?></title>
    <style>
        @page {
            size: 10in 7.5in;
            margin: 0.3in 0.5in 0.35in 0.5in;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            color: #1e293b;
            background: #ffffff;
            font-size: 11px;
            line-height: 1.4;
        }

        /* ===================================================================
           COVER PAGE (Matching Sri Shringarr Landscape Presentation Format)
           =================================================================== */
        .cover-page {
            width: 100%;
            text-align: center;
            padding-top: 115px;
            padding-bottom: 30px;
            page-break-after: always;
        }
        .cover-inner {
            width: 100%;
            text-align: center;
        }
        .cover-brand-title {
            font-size: 34px;
            font-weight: bold;
            color: #3b4999;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .cover-brand-subtitle {
            font-size: 17px;
            font-style: italic;
            color: #6366f1;
            margin-bottom: 22px;
        }
        .cover-accent-bar {
            width: 55%;
            height: 4px;
            background: #3b4999;
            margin: 0 auto 28px auto;
            border-radius: 2px;
        }
        .cover-catalogue-title {
            font-size: 16px;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
        }
        .cover-client {
            font-size: 13.5px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .cover-date {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 22px;
        }
        .cover-notes-box {
            margin: 25px auto 0 auto;
            max-width: 550px;
            padding: 10px 18px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 11px;
            color: #64748b;
            font-style: italic;
            line-height: 1.5;
        }

        /* ===================================================================
           PRODUCT PAGES (Landscape Format - Fills Page Vertically)
           =================================================================== */
        .product-page {
            page-break-inside: avoid;
            padding-top: 16px;
        }
        .product-page:last-child {
            page-break-after: auto !important;
        }

        /* SHOWCASE LAYOUT (2 Products Per Page - Landscape) */
        .showcase-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 24px 0;
            table-layout: fixed;
        }
        .showcase-col {
            width: 50%;
            vertical-align: top;
            text-align: center;
        }
        .showcase-card {
            width: 100%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 4px;
            text-align: center;
        }
        .showcase-img {
            width: auto;
            height: 505px;
            max-width: 100%;
            display: block;
            margin: 0 auto;
            border-radius: 2px;
        }
        .product-info {
            margin-top: 10px;
            text-align: center;
        }
        .product-view-link {
            color: #3b4999;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        .product-category-label {
            font-size: 10.5px;
            color: #64748b;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .product-price-label {
            font-size: 13.5px;
            font-weight: bold;
            color: #b45309;
            margin-top: 3px;
        }

        /* HERO LAYOUT (1 Product Per Page) */
        .hero-page-box {
            text-align: center;
            padding-top: 14px;
        }
        .hero-card {
            display: inline-block;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px;
            margin: 0 auto;
        }
        .hero-img {
            width: auto;
            height: 520px;
            max-width: 580px;
            display: block;
            margin: 0 auto;
            border-radius: 4px;
        }
        .hero-info {
            margin-top: 12px;
            text-align: center;
        }
        .hero-title {
            font-size: 16px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .hero-view-link {
            font-size: 15px;
            font-weight: bold;
            color: #3b4999;
            text-decoration: none;
            display: inline-block;
        }
        .hero-price {
            font-size: 15px;
            font-weight: bold;
            color: #b45309;
            margin-top: 3px;
        }

        /* GRID LAYOUT (4 Products Per Page - 2x2) */
        .grid-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 16px 10px;
            table-layout: fixed;
            margin-top: 2px;
        }
        .grid-col {
            width: 50%;
            vertical-align: top;
            text-align: center;
        }
        .grid-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 4px;
            text-align: center;
        }
        .grid-img {
            width: auto;
            height: 230px;
            max-width: 100%;
            display: block;
            margin: 0 auto;
            border-radius: 2px;
        }
        .grid-info {
            margin-top: 4px;
            text-align: center;
        }
        .grid-link {
            color: #3b4999;
            font-weight: bold;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .grid-price {
            font-size: 11.5px;
            font-weight: bold;
            color: #b45309;
            margin-top: 2px;
        }

        /* Running PDF Footer */
        .pdf-footer {
            position: fixed;
            bottom: -8mm;
            left: 0;
            right: 0;
            height: 6mm;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 3px;
        }

        /* No print toolbar for HTML mode */
        .no-print-bar {
            background: #1e293b;
            color: #fff;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        @media print {
            .no-print-bar { display: none !important; }
        }
    </style>
</head>
<body>

    <?php if ($outputMode === 'html'): ?>
    <div class="no-print-bar">
        <div>
            <strong style="font-size: 14px;"><?php echo htmlspecialchars($catalogueTitle); ?></strong>
            <span style="color: #94a3b8; margin-left: 10px;">(<?php echo count($itemsToDisplay); ?> Items)</span>
        </div>
        <div>
            <button onclick="window.print()" style="background: #3b4999; color: #fff; border: none; padding: 8px 18px; border-radius: 4px; font-weight: bold; cursor: pointer; margin-right: 8px;">
                Print / Save as PDF
            </button>
            <a href="pdf-maker.php" style="color: #cbd5e1; text-decoration: none; font-size: 12px;">&larr; Back to Editor</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Running PDF Footer -->
    <div class="pdf-footer">
        <?php echo htmlspecialchars($brandTitle); ?> &bull; www.yosshitaneha.com &bull; Inquiries: +91 98204 77798
    </div>

    <!-- ===================================================================
         COVER PAGE (Matching User Aesthetic, Sized to 1 single A4 page)
         =================================================================== -->
    <?php if ($includeCover): ?>
    <div class="cover-page">
        <div class="cover-inner">
            <div class="cover-brand-title"><?php echo htmlspecialchars($brandTitle); ?></div>
            <div class="cover-brand-subtitle"><?php echo htmlspecialchars($brandSubtitle); ?></div>
            <div class="cover-accent-bar"></div>

            <?php if (!empty($catalogueTitle)): ?>
                <div class="cover-catalogue-title"><?php echo htmlspecialchars($catalogueTitle); ?></div>
            <?php endif; ?>

            <?php if (!empty($clientName)): ?>
                <div class="cover-client">Curated for: <strong><?php echo htmlspecialchars($clientName); ?></strong></div>
            <?php endif; ?>

            <div class="cover-date"><?php echo date('d M Y'); ?></div>

            <?php if (!empty($customNotes)): ?>
                <div class="cover-notes-box">
                    <?php echo nl2br(htmlspecialchars($customNotes)); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===================================================================
         PRODUCT PAGES ACCORDING TO SELECTED LAYOUT
         =================================================================== -->

    <?php if ($layout === 'hero'): ?>
        <!-- HERO LAYOUT: 1 Item Per Page -->
        <?php foreach ($itemChunks as $idx => $chunk): 
            $item = $chunk[0];
            $imgBase64 = get_image_base64_for_pdf($item['image'], 'hero');
            $effectivePrice = (float)($item['sale_price'] > 0 ? $item['sale_price'] : $item['price']);
            $isLast = ($idx === $totalChunks - 1);
        ?>
        <div class="product-page" style="<?php echo $isLast ? '' : 'page-break-after: always;'; ?>">
            <div class="hero-page-box">
                <div class="hero-card">
                    <?php if ($imgBase64): ?>
                        <img src="<?php echo $imgBase64; ?>" class="hero-img" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <?php else: ?>
                        <div style="width: 490px; height: 490px; line-height: 490px; background: #f8fafc; color: #94a3b8; font-size: 13px;">Photo Unavailable</div>
                    <?php endif; ?>
                </div>

                <div class="hero-info">
                    <div class="hero-title"><?php echo htmlspecialchars($item['name']); ?></div>

                    <?php if ($showProductLink): ?>
                        <a href="<?php echo htmlspecialchars($item['url']); ?>" class="hero-view-link" target="_blank">
                            View: <?php echo htmlspecialchars($item['sku']); ?>
                        </a>
                    <?php elseif ($showSku): ?>
                        <div class="hero-view-link">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                    <?php endif; ?>

                    <?php if ($showCategory && !empty($item['category_name'])): ?>
                        <div class="product-category-label"><?php echo htmlspecialchars($item['category_name']); ?></div>
                    <?php endif; ?>

                    <?php if ($showPrice && $effectivePrice > 0): ?>
                        <div class="hero-price">₹<?php echo number_format($effectivePrice, 0); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    <?php elseif ($layout === 'grid'): ?>
        <!-- GRID LAYOUT: 4 Items Per Page (2x2) -->
        <?php foreach ($itemChunks as $idx => $chunk): 
            $isLast = ($idx === $totalChunks - 1);
            $subRows = array_chunk($chunk, 2);
        ?>
        <div class="product-page" style="<?php echo $isLast ? '' : 'page-break-after: always;'; ?>">
            <table class="grid-table">
                <?php foreach ($subRows as $subRow): ?>
                <tr>
                    <?php foreach ($subRow as $item): 
                        $imgBase64 = get_image_base64_for_pdf($item['image'], 'grid');
                        $effectivePrice = (float)($item['sale_price'] > 0 ? $item['sale_price'] : $item['price']);
                    ?>
                    <td class="grid-col">
                        <div class="grid-card">
                            <?php if ($imgBase64): ?>
                                <img src="<?php echo $imgBase64; ?>" class="grid-img" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php else: ?>
                                <div style="height: 210px; line-height: 210px; background: #f8fafc; color: #94a3b8; font-size: 10px;">Photo Unavailable</div>
                            <?php endif; ?>
                        </div>

                        <div class="grid-info">
                            <?php if ($showProductLink): ?>
                                <a href="<?php echo htmlspecialchars($item['url']); ?>" class="grid-link" target="_blank">
                                    View: <?php echo htmlspecialchars($item['sku']); ?>
                                </a>
                            <?php elseif ($showSku): ?>
                                <div class="grid-link">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                            <?php endif; ?>

                            <?php if ($showPrice && $effectivePrice > 0): ?>
                                <div class="grid-price">₹<?php echo number_format($effectivePrice, 0); ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endforeach; ?>

                    <?php if (count($subRow) === 1): ?>
                        <td class="grid-col" style="border:none; background:transparent;"></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endforeach; ?>

    <?php else: ?>
        <!-- SHOWCASE LAYOUT: 2 Items Per Page (Default & Most Popular) -->
        <?php foreach ($itemChunks as $idx => $chunk): 
            $isLast = ($idx === $totalChunks - 1);
        ?>
        <div class="product-page" style="<?php echo $isLast ? '' : 'page-break-after: always;'; ?>">
            <table class="showcase-table">
                <tr>
                    <?php foreach ($chunk as $item): 
                        $imgBase64 = get_image_base64_for_pdf($item['image'], 'showcase');
                        $effectivePrice = (float)($item['sale_price'] > 0 ? $item['sale_price'] : $item['price']);
                    ?>
                    <td class="showcase-col">
                        <div class="showcase-card">
                            <?php if ($imgBase64): ?>
                                <img src="<?php echo $imgBase64; ?>" class="showcase-img" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php else: ?>
                                <div style="height: 400px; line-height: 400px; background: #f8fafc; color: #94a3b8; font-size: 11px;">Photo Unavailable</div>
                            <?php endif; ?>
                        </div>

                        <div class="product-info">
                            <?php if ($showProductLink): ?>
                                <a href="<?php echo htmlspecialchars($item['url']); ?>" class="product-view-link" target="_blank">
                                    View: <?php echo htmlspecialchars($item['sku']); ?>
                                </a>
                            <?php elseif ($showSku): ?>
                                <div class="product-view-link">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                            <?php endif; ?>

                            <?php if ($showCategory && !empty($item['category_name'])): ?>
                                <div class="product-category-label"><?php echo htmlspecialchars($item['category_name']); ?></div>
                            <?php endif; ?>

                            <?php if ($showPrice && $effectivePrice > 0): ?>
                                <div class="product-price-label">₹<?php echo number_format($effectivePrice, 0); ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endforeach; ?>

                    <?php if (count($chunk) === 1): ?>
                        <td class="showcase-col" style="border:none; background:transparent;"></td>
                    <?php endif; ?>
                </tr>
            </table>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>

</body>
</html>
<?php
$html = ob_get_clean();

if ($outputMode === 'html') {
    header("Content-Type: text/html; charset=UTF-8");
    echo $html;
    exit();
}

try {
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper([0, 0, 720, 540], 'landscape');
    $dompdf->render();

    $cleanFilename = preg_replace('/[^A-Za-z0-9_-]/', '_', $catalogueTitle);
    if (empty($cleanFilename)) $cleanFilename = 'Catalog';
    $pdfFilename = $cleanFilename . '_' . date('Ymd_His') . '.pdf';

    $isAttachment = ($outputMode !== 'preview' && (isset($_GET['download']) || !isset($_GET['preview'])));
    
    if (php_sapi_name() === 'cli') {
        echo $dompdf->output();
        exit();
    }

    $dompdf->stream($pdfFilename, ["Attachment" => $isAttachment]);
    exit();

} catch (Exception $e) {
    header("Content-Type: text/html; charset=UTF-8");
    echo "<div style='color:red; padding: 20px; font-family: sans-serif;'><h3>PDF Generation Error</h3><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
    echo $html;
    exit();
}
