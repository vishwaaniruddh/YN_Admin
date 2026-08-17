<?php
// admin/generate-catalogue-pdf.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Set time limit for larger PDF rendering
set_time_limit(120);

// Read Input Payload (either from POST json, POST form, or session)
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
} elseif (isset($_GET['demo'])) {
    // Quick demo preview if triggered directly
    $stmt = $pdo->query("SELECT id, name, sku, price, sale_price, description, main_image, 'Jewellery' as category_name FROM products WHERE deleted_at IS NULL LIMIT 4");
    $demoProds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($demoProds as &$p) {
        $p['selected_images'] = [$p['main_image']];
    }
    $input = [
        'catalogue_title' => 'Luxury Festive Collection 2026',
        'client_name' => 'Demo Preview',
        'layout' => 'showcase',
        'show_price' => 1,
        'show_sku' => 1,
        'show_category' => 1,
        'show_description' => 1,
        'products' => $demoProds,
        'output_mode' => 'html'
    ];
}

$catalogueTitle = !empty($input['catalogue_title']) ? trim($input['catalogue_title']) : 'Product Catalogue';
$clientName = !empty($input['client_name']) ? trim($input['client_name']) : '';
$layout = !empty($input['layout']) ? trim($input['layout']) : 'showcase'; // hero, showcase, grid
$showPrice = isset($input['show_price']) ? (bool)$input['show_price'] : true;
$showSku = isset($input['show_sku']) ? (bool)$input['show_sku'] : true;
$showCategory = isset($input['show_category']) ? (bool)$input['show_category'] : true;
$showDescription = isset($input['show_description']) ? (bool)$input['show_description'] : true;
$customNotes = !empty($input['custom_notes']) ? trim($input['custom_notes']) : '';
$outputMode = !empty($input['output_mode']) ? trim($input['output_mode']) : 'pdf'; // pdf, preview, html
$products = isset($input['products']) && is_array($input['products']) ? $input['products'] : [];

if (empty($products)) {
    die("<h3>No products selected for PDF generation. Please select at least one product.</h3><p><a href='pdf-maker.php'>&larr; Back to PDF Maker</a></p>");
}

// Helper to convert image path to Base64 data URI (supports local and yosshitaneha.com live media)
function get_image_base64_for_pdf($imageRelPath) {
    if (empty($imageRelPath)) {
        return '';
    }

    $imageRelPath = trim((string)$imageRelPath);

    // 1. Clean path of localhost and domain prefix to check local filesystem
    $cleanPath = preg_replace('#^https?://(localhost(:[0-9]+)?|yosshitaneha\.com)(/yn)?(/admin)?/?#i', '', $imageRelPath);
    $cleanPath = ltrim($cleanPath, '/');

    // Possible local file locations
    $possiblePaths = [
        __DIR__ . '/' . $cleanPath,
        __DIR__ . '/../' . $cleanPath,
        __DIR__ . '/uploads/products/' . $cleanPath,
        __DIR__ . '/assets/' . $cleanPath
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_file($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = 'image/jpeg';
            if ($ext === 'png') $mime = 'image/png';
            elseif ($ext === 'webp') $mime = 'image/webp';
            elseif ($ext === 'gif') $mime = 'image/gif';

            $data = @file_get_contents($path);
            if ($data) {
                return 'data:' . $mime . ';base64,' . base64_encode($data);
            }
        }
    }

    // 2. Fetch from live production server https://yosshitaneha.com/admin/...
    $liveUrl = $imageRelPath;
    if (!str_starts_with($imageRelPath, 'http')) {
        $liveUrl = 'https://yosshitaneha.com/admin/' . $cleanPath;
    } elseif (str_contains($imageRelPath, 'localhost')) {
        $liveUrl = 'https://yosshitaneha.com/admin/' . $cleanPath;
    }

    // Fetch live image with fast timeout context
    $ctx = stream_context_create([
        'http' => ['timeout' => 4, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $remoteData = @file_get_contents($liveUrl, false, $ctx);
    if ($remoteData) {
        $ext = strtolower(pathinfo(parse_url($liveUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mime = 'image/jpeg';
        if ($ext === 'png') $mime = 'image/png';
        elseif ($ext === 'webp') $mime = 'image/webp';
        elseif ($ext === 'gif') $mime = 'image/gif';

        return 'data:' . $mime . ';base64,' . base64_encode($remoteData);
    }

    // Fallback: return live URL for Dompdf remote parser
    return htmlspecialchars($liveUrl);
}

// Prepare Logo
$logoPath = __DIR__ . '/assets/images/logo.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}

$generationDate = date('d M Y');
$totalPagesCount = count($products);

// Begin Building HTML Output
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($catalogueTitle); ?> - Yosshita &amp; Neha</title>
    <style>
        @page {
            margin: 12mm 10mm 15mm 10mm;
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
            font-size: 10.5px;
            line-height: 1.4;
        }

        /* Top Brand Bar & Header */
        .pdf-header-table {
            width: 100%;
            border-bottom: 2px solid #c8a55c;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .brand-logo-cell {
            vertical-align: middle;
            width: 45%;
        }
        .brand-logo-img {
            max-height: 48px;
            max-width: 180px;
            display: block;
        }
        .brand-text-title {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .brand-text-subtitle {
            font-size: 8.5px;
            color: #c8a55c;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .doc-meta-cell {
            vertical-align: middle;
            text-align: right;
            width: 55%;
        }
        .doc-title {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-client {
            font-size: 9.5px;
            color: #64748b;
            margin-top: 2px;
        }
        .doc-date {
            font-size: 8.5px;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* Fixed Footer on each page */
        .pdf-footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            height: 8mm;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            font-size: 8px;
            color: #64748b;
            text-align: center;
        }
        .pdf-footer-table {
            width: 100%;
        }

        /* Reusable Typography & Badges */
        .badge-gold {
            display: inline-block;
            background: #fdf8eb;
            color: #a88438;
            border: 1px solid #e9d5a1;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .product-name {
            font-size: 12.5px;
            font-weight: bold;
            color: #0f172a;
            margin: 4px 0 2px 0;
            line-height: 1.3;
        }
        .product-sku {
            font-size: 9px;
            color: #64748b;
            font-weight: 500;
        }
        .price-tag {
            font-size: 13px;
            font-weight: bold;
            color: #c8a55c;
            margin-top: 4px;
        }
        .price-tag .strike {
            font-size: 10px;
            color: #94a3b8;
            text-decoration: line-through;
            font-weight: normal;
            margin-left: 5px;
        }
        .product-desc {
            font-size: 9px;
            color: #475569;
            margin-top: 6px;
            line-height: 1.45;
        }

        /* =======================================================
           LAYOUT 1: HERO LOOKBOOK (1 Product per page)
           ======================================================= */
        .hero-page {
            page-break-inside: avoid;
            page-break-after: always;
            height: 100%;
        }
        .hero-layout-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .hero-img-col {
            width: 58%;
            vertical-align: top;
            padding-right: 15px;
        }
        .hero-info-col {
            width: 42%;
            vertical-align: top;
            padding-left: 10px;
            border-left: 1px solid #f1f5f9;
        }
        .hero-main-img-box {
            width: 100%;
            height: 330px;
            background: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
            margin-bottom: 8px;
        }
        .hero-main-img {
            max-width: 100%;
            max-height: 330px;
            object-fit: contain;
            display: inline-block;
        }
        .hero-gallery-row {
            width: 100%;
            margin-top: 6px;
        }
        .hero-gallery-thumb-box {
            display: inline-block;
            width: 72px;
            height: 72px;
            background: #fafafa;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            margin-right: 6px;
            margin-bottom: 4px;
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
        }
        .hero-gallery-thumb {
            max-width: 100%;
            max-height: 72px;
            object-fit: cover;
        }

        /* =======================================================
           LAYOUT 2: SHOWCASE (2 Products per page)
           ======================================================= */
        .showcase-page {
            page-break-inside: avoid;
            page-break-after: always;
        }
        .showcase-card {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 15px;
            background: #ffffff;
        }
        .showcase-card-table {
            width: 100%;
            border-collapse: collapse;
        }
        .showcase-img-col {
            width: 36%;
            vertical-align: top;
            padding-right: 12px;
        }
        .showcase-info-col {
            width: 64%;
            vertical-align: top;
        }
        .showcase-img-box {
            width: 100%;
            height: 180px;
            background: #fafafa;
            border: 1px solid #f1f5f9;
            border-radius: 4px;
            text-align: center;
            overflow: hidden;
        }
        .showcase-img {
            max-width: 100%;
            max-height: 180px;
            object-fit: contain;
        }
        .showcase-extra-imgs {
            margin-top: 6px;
        }
        .showcase-extra-thumb {
            display: inline-block;
            width: 44px;
            height: 44px;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
            margin-right: 4px;
            overflow: hidden;
            vertical-align: middle;
            text-align: center;
        }
        .showcase-extra-thumb img {
            max-width: 100%;
            max-height: 44px;
        }

        /* =======================================================
           LAYOUT 3: GRID CATALOGUE (4 Products per page - 2x2)
           ======================================================= */
        .grid-page {
            page-break-inside: avoid;
            page-break-after: always;
        }
        .grid-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px;
        }
        .grid-card-cell {
            width: 50%;
            vertical-align: top;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px;
            background: #ffffff;
            height: 250px;
        }
        .grid-img-box {
            width: 100%;
            height: 135px;
            background: #fafafa;
            border-radius: 4px;
            text-align: center;
            overflow: hidden;
            margin-bottom: 6px;
        }
        .grid-img {
            max-width: 100%;
            max-height: 135px;
            object-fit: contain;
        }

        /* Notes & Terms Box */
        .notes-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 10px 14px;
            margin-top: 15px;
            font-size: 8.5px;
            color: #475569;
            page-break-inside: avoid;
        }
        .notes-title {
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            margin-bottom: 3px;
            font-size: 9px;
        }

        /* No-Print UI bar for HTML mode */
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
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <?php if ($outputMode === 'html'): ?>
    <div class="no-print-bar">
        <div>
            <strong style="font-size: 14px;"><?php echo htmlspecialchars($catalogueTitle); ?></strong>
            <span style="color: #94a3b8; margin-left: 10px;">(<?php echo count($products); ?> Products Selected)</span>
        </div>
        <div>
            <button onclick="window.print()" style="background: #c8a55c; color: #fff; border: none; padding: 8px 18px; border-radius: 4px; font-weight: bold; cursor: pointer; margin-right: 8px;">
                Print / Save as PDF
            </button>
            <a href="pdf-maker.php" style="color: #cbd5e1; text-decoration: none; font-size: 12px;">&larr; Back to Editor</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Running PDF Footer -->
    <div class="pdf-footer">
        <table class="pdf-footer-table">
            <tr>
                <td style="text-align: left; width: 33%;">Yosshita &amp; Neha Fashion Studio</td>
                <td style="text-align: center; width: 34%;">WhatsApp / Inquiries: +91 98204 77798 &bull; www.yosshitaneha.com</td>
                <td style="text-align: right; width: 33%;">Instagram: @yosshitanehafashionstudio</td>
            </tr>
        </table>
    </div>

    <?php
    // =========================================================================
    // RENDER: LAYOUT 1 - HERO LOOKBOOK (1 product per page)
    // =========================================================================
    if ($layout === 'hero'):
        foreach ($products as $idx => $prod):
            $selectedImages = !empty($prod['selected_images']) && is_array($prod['selected_images']) 
                ? $prod['selected_images'] 
                : (!empty($prod['main_image']) ? [$prod['main_image']] : []);
            
            $mainImgBase64 = !empty($selectedImages[0]) ? get_image_base64_for_pdf($selectedImages[0]) : '';
            $extraImages = array_slice($selectedImages, 1);
            $effectivePrice = (float)($prod['sale_price'] > 0 ? $prod['sale_price'] : $prod['price']);
    ?>
        <div class="hero-page <?php echo ($idx === count($products) - 1 && empty($customNotes)) ? 'last-page' : ''; ?>">
            <!-- Page Header -->
            <table class="pdf-header-table">
                <tr>
                    <td class="brand-logo-cell">
                        <?php if ($logoBase64): ?>
                            <img src="<?php echo $logoBase64; ?>" class="brand-logo-img" alt="Logo">
                        <?php else: ?>
                            <div class="brand-text-title">YOSSHITA &bull; NEHA</div>
                            <div class="brand-text-subtitle">Haute Couture &amp; Fine Jewellery</div>
                        <?php endif; ?>
                    </td>
                    <td class="doc-meta-cell">
                        <div class="doc-title"><?php echo htmlspecialchars($catalogueTitle); ?></div>
                        <?php if ($clientName): ?><div class="doc-client">Curated for: <strong><?php echo htmlspecialchars($clientName); ?></strong></div><?php endif; ?>
                        <div class="doc-date"><?php echo $generationDate; ?> &bull; Item <?php echo ($idx + 1); ?> of <?php echo count($products); ?></div>
                    </td>
                </tr>
            </table>

            <!-- Main Product Hero Layout -->
            <table class="hero-layout-table">
                <tr>
                    <td class="hero-img-col">
                        <div class="hero-main-img-box">
                            <?php if ($mainImgBase64): ?>
                                <img src="<?php echo $mainImgBase64; ?>" class="hero-main-img" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                            <?php else: ?>
                                <div style="padding-top: 140px; color: #94a3b8; font-size: 11px;">Image Showcase</div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($extraImages)): ?>
                        <div class="hero-gallery-row">
                            <?php foreach (array_slice($extraImages, 0, 4) as $extra): 
                                $extraB64 = get_image_base64_for_pdf($extra);
                                if ($extraB64):
                            ?>
                                <div class="hero-gallery-thumb-box">
                                    <img src="<?php echo $extraB64; ?>" class="hero-gallery-thumb" alt="">
                                </div>
                            <?php 
                                endif;
                            endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <td class="hero-info-col">
                        <?php if ($showCategory && !empty($prod['category_name'])): ?>
                            <span class="badge-gold"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                        <?php endif; ?>

                        <div class="product-name"><?php echo htmlspecialchars($prod['name']); ?></div>

                        <?php if ($showSku && !empty($prod['sku'])): ?>
                            <div class="product-sku">Product Code: <strong><?php echo htmlspecialchars($prod['sku']); ?></strong></div>
                        <?php endif; ?>

                        <?php if ($showPrice && $effectivePrice > 0): ?>
                            <div class="price-tag">
                                ₹<?php echo number_format($effectivePrice, 0); ?>
                                <?php if (!empty($prod['sale_price']) && $prod['sale_price'] < $prod['price']): ?>
                                    <span class="strike">₹<?php echo number_format($prod['price'], 0); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div style="height: 1px; background: #f1f5f9; margin: 12px 0;"></div>

                        <?php if ($showDescription && !empty($prod['description'])): ?>
                            <div class="product-desc">
                                <?php 
                                    $cleanDesc = strip_tags($prod['description']);
                                    echo nl2br(htmlspecialchars(substr($cleanDesc, 0, 500)));
                                    if (strlen($cleanDesc) > 500) echo '...';
                                ?>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 25px; padding: 10px; background: #fdfcf9; border-left: 3px solid #c8a55c; font-size: 8.5px; color: #786438;">
                            <strong>Customization Available:</strong> All designs can be customized for sizing, color themes, and styling at our studio.
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    <?php 
        endforeach;

    // =========================================================================
    // RENDER: LAYOUT 2 - SHOWCASE (2 products per page)
    // =========================================================================
    elseif ($layout === 'showcase'):
        $productChunks = array_chunk($products, 2);
        foreach ($productChunks as $chunkIdx => $chunk):
    ?>
        <div class="showcase-page">
            <!-- Header for each page -->
            <table class="pdf-header-table">
                <tr>
                    <td class="brand-logo-cell">
                        <?php if ($logoBase64): ?>
                            <img src="<?php echo $logoBase64; ?>" class="brand-logo-img" alt="Logo">
                        <?php else: ?>
                            <div class="brand-text-title">YOSSHITA &bull; NEHA</div>
                            <div class="brand-text-subtitle">Haute Couture &amp; Fine Jewellery</div>
                        <?php endif; ?>
                    </td>
                    <td class="doc-meta-cell">
                        <div class="doc-title"><?php echo htmlspecialchars($catalogueTitle); ?></div>
                        <?php if ($clientName): ?><div class="doc-client">Curated for: <strong><?php echo htmlspecialchars($clientName); ?></strong></div><?php endif; ?>
                        <div class="doc-date"><?php echo $generationDate; ?> &bull; Page <?php echo ($chunkIdx + 1); ?> of <?php echo count($productChunks); ?></div>
                    </td>
                </tr>
            </table>

            <?php foreach ($chunk as $prod): 
                $selectedImages = !empty($prod['selected_images']) && is_array($prod['selected_images']) 
                    ? $prod['selected_images'] 
                    : (!empty($prod['main_image']) ? [$prod['main_image']] : []);
                
                $mainImgBase64 = !empty($selectedImages[0]) ? get_image_base64_for_pdf($selectedImages[0]) : '';
                $extraImages = array_slice($selectedImages, 1);
                $effectivePrice = (float)($prod['sale_price'] > 0 ? $prod['sale_price'] : $prod['price']);
            ?>
            <div class="showcase-card">
                <table class="showcase-card-table">
                    <tr>
                        <td class="showcase-img-col">
                            <div class="showcase-img-box">
                                <?php if ($mainImgBase64): ?>
                                    <img src="<?php echo $mainImgBase64; ?>" class="showcase-img" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                                <?php else: ?>
                                    <div style="padding-top: 80px; color: #94a3b8; font-size: 10px;">No Image</div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($extraImages)): ?>
                            <div class="showcase-extra-imgs">
                                <?php foreach (array_slice($extraImages, 0, 3) as $extra): 
                                    $extraB64 = get_image_base64_for_pdf($extra);
                                    if ($extraB64):
                                ?>
                                    <div class="showcase-extra-thumb">
                                        <img src="<?php echo $extraB64; ?>" alt="">
                                    </div>
                                <?php 
                                    endif;
                                endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <td class="showcase-info-col">
                            <?php if ($showCategory && !empty($prod['category_name'])): ?>
                                <span class="badge-gold"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                            <?php endif; ?>

                            <div class="product-name"><?php echo htmlspecialchars($prod['name']); ?></div>

                            <?php if ($showSku && !empty($prod['sku'])): ?>
                                <div class="product-sku">SKU: <strong><?php echo htmlspecialchars($prod['sku']); ?></strong></div>
                            <?php endif; ?>

                            <?php if ($showPrice && $effectivePrice > 0): ?>
                                <div class="price-tag">
                                    ₹<?php echo number_format($effectivePrice, 0); ?>
                                    <?php if (!empty($prod['sale_price']) && $prod['sale_price'] < $prod['price']): ?>
                                        <span class="strike">₹<?php echo number_format($prod['price'], 0); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($showDescription && !empty($prod['description'])): ?>
                                <div class="product-desc">
                                    <?php 
                                        $cleanDesc = strip_tags($prod['description']);
                                        echo nl2br(htmlspecialchars(substr($cleanDesc, 0, 260)));
                                        if (strlen($cleanDesc) > 260) echo '...';
                                    ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endforeach; ?>
        </div>
    <?php 
        endforeach;

    // =========================================================================
    // RENDER: LAYOUT 3 - GRID CATALOGUE (4 products per page - 2x2)
    // =========================================================================
    else: // grid
        $productChunks = array_chunk($products, 4);
        foreach ($productChunks as $chunkIdx => $chunk):
    ?>
        <div class="grid-page">
            <!-- Page Header -->
            <table class="pdf-header-table">
                <tr>
                    <td class="brand-logo-cell">
                        <?php if ($logoBase64): ?>
                            <img src="<?php echo $logoBase64; ?>" class="brand-logo-img" alt="Logo">
                        <?php else: ?>
                            <div class="brand-text-title">YOSSHITA &bull; NEHA</div>
                            <div class="brand-text-subtitle">Haute Couture &amp; Fine Jewellery</div>
                        <?php endif; ?>
                    </td>
                    <td class="doc-meta-cell">
                        <div class="doc-title"><?php echo htmlspecialchars($catalogueTitle); ?></div>
                        <?php if ($clientName): ?><div class="doc-client">Curated for: <strong><?php echo htmlspecialchars($clientName); ?></strong></div><?php endif; ?>
                        <div class="doc-date"><?php echo $generationDate; ?> &bull; Page <?php echo ($chunkIdx + 1); ?> of <?php echo count($productChunks); ?></div>
                    </td>
                </tr>
            </table>

            <!-- 2x2 Table Layout -->
            <table class="grid-table">
                <?php 
                $rows = array_chunk($chunk, 2);
                foreach ($rows as $row):
                ?>
                <tr>
                    <?php foreach ($row as $prod): 
                        $selectedImages = !empty($prod['selected_images']) && is_array($prod['selected_images']) 
                            ? $prod['selected_images'] 
                            : (!empty($prod['main_image']) ? [$prod['main_image']] : []);
                        
                        $mainImgBase64 = !empty($selectedImages[0]) ? get_image_base64_for_pdf($selectedImages[0]) : '';
                        $effectivePrice = (float)($prod['sale_price'] > 0 ? $prod['sale_price'] : $prod['price']);
                    ?>
                    <td class="grid-card-cell">
                        <div class="grid-img-box">
                            <?php if ($mainImgBase64): ?>
                                <img src="<?php echo $mainImgBase64; ?>" class="grid-img" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                            <?php else: ?>
                                <div style="padding-top: 55px; color: #94a3b8; font-size: 9px;">No Image</div>
                            <?php endif; ?>
                        </div>

                        <?php if ($showCategory && !empty($prod['category_name'])): ?>
                            <span class="badge-gold" style="font-size: 7.5px; padding: 1px 4px;"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                        <?php endif; ?>

                        <div class="product-name" style="font-size: 10.5px; height: 28px; overflow: hidden;"><?php echo htmlspecialchars($prod['name']); ?></div>

                        <table style="width: 100%; margin-top: 4px;">
                            <tr>
                                <?php if ($showSku && !empty($prod['sku'])): ?>
                                    <td style="font-size: 8.5px; color: #64748b;">SKU: <strong><?php echo htmlspecialchars($prod['sku']); ?></strong></td>
                                <?php endif; ?>

                                <?php if ($showPrice && $effectivePrice > 0): ?>
                                    <td style="text-align: right; font-size: 11px; font-weight: bold; color: #c8a55c;">
                                        ₹<?php echo number_format($effectivePrice, 0); ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        </table>
                    </td>
                    <?php endforeach; ?>

                    <?php if (count($row) === 1): ?>
                        <td class="grid-card-cell" style="border: none; background: transparent;"></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php 
        endforeach;
    endif;
    ?>

    <?php if (!empty($customNotes)): ?>
    <div class="notes-box">
        <div class="notes-title">Important Notes &amp; Studio Guidelines</div>
        <p><?php echo nl2br(htmlspecialchars($customNotes)); ?></p>
    </div>
    <?php endif; ?>

</body>
</html>
<?php
$html = ob_get_clean();

// Deliver output according to mode
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
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $cleanFilename = preg_replace('/[^A-Za-z0-9_-]/', '_', $catalogueTitle);
    if (empty($cleanFilename)) $cleanFilename = 'Catalogue';
    $pdfFilename = $cleanFilename . '_' . date('Ymd_His') . '.pdf';

    $isAttachment = ($outputMode !== 'preview' && (isset($_GET['download']) || !isset($_GET['preview'])));
    
    $dompdf->stream($pdfFilename, ["Attachment" => $isAttachment]);
    exit();

} catch (Exception $e) {
    header("Content-Type: text/html; charset=UTF-8");
    echo "<div style='color:red; padding: 20px; font-family: sans-serif;'><h3>PDF Generation Error</h3><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
    echo $html;
    exit();
}
