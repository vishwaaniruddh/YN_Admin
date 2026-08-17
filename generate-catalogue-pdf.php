<?php
// admin/generate-catalogue-pdf.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

set_time_limit(180);

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

// Helper to convert image path to Base64 data URI (supports local and yosshitaneha.com live media)
function get_image_base64_for_pdf($imageRelPath) {
    if (empty($imageRelPath)) return '';
    $imageRelPath = trim((string)$imageRelPath);

    // 1. Clean path of localhost and domain prefix to check local filesystem
    $cleanPath = preg_replace('#^https?://(localhost(:[0-9]+)?|yosshitaneha\.com)(/yn)?(/admin)?/?#i', '', $imageRelPath);
    $cleanPath = ltrim($cleanPath, '/');

    $possiblePaths = [
        __DIR__ . '/' . $cleanPath,
        __DIR__ . '/../' . $cleanPath,
        __DIR__ . '/uploads/products/' . $cleanPath,
        __DIR__ . '/assets/' . $cleanPath
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_file($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : 'image/jpeg');
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

    $ctx = stream_context_create([
        'http' => ['timeout' => 4, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $remoteData = @file_get_contents($liveUrl, false, $ctx);
    if ($remoteData) {
        $ext = strtolower(pathinfo(parse_url($liveUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : 'image/jpeg');
        return 'data:' . $mime . ';base64,' . base64_encode($remoteData);
    }

    return htmlspecialchars($liveUrl);
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($catalogueTitle); ?> - <?php echo htmlspecialchars($brandTitle); ?></title>
    <style>
        @page {
            margin: 10mm 10mm 12mm 10mm;
            size: A4 portrait;
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

        /* Cover Page (Matching User Screenshot 2) */
        .cover-page {
            height: 100%;
            display: table;
            width: 100%;
            text-align: center;
            page-break-after: always;
        }
        .cover-inner {
            display: table-cell;
            vertical-align: middle;
            padding: 100px 30px 40px 30px;
        }
        .cover-brand-title {
            font-size: 32px;
            font-weight: bold;
            color: #3b4999; /* Royal Purple / Blue matching screenshot */
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .cover-brand-subtitle {
            font-size: 18px;
            font-style: italic;
            color: #6366f1; /* Light indigo subtitle */
            margin-bottom: 24px;
        }
        .cover-accent-bar {
            width: 70%;
            height: 5px;
            background: #3b4999;
            margin: 0 auto 35px auto;
            border-radius: 2px;
        }
        .cover-catalogue-title {
            font-size: 15px;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .cover-client {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .cover-date {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 30px;
        }

        /* Product Catalog Pages (Matching User Screenshot 3) */
        .product-page {
            page-break-inside: avoid;
            page-break-after: always;
            height: 100%;
        }
        .product-pair-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 15px 0;
            margin-top: 5px;
        }
        .product-col {
            width: 50%;
            vertical-align: top;
            text-align: center;
        }
        .product-img-box {
            width: 100%;
            height: 480px;
            background: #fafafa;
            border: 1px solid #f1f5f9;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .product-img-box img {
            max-width: 100%;
            max-height: 480px;
            object-fit: contain;
            display: inline-block;
        }
        .product-link-row {
            margin-top: 10px;
            text-align: center;
        }
        .product-view-link {
            color: #3b4999; /* Matching Screenshot 3 blue link */
            font-weight: bold;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        .product-price-label {
            font-size: 12px;
            font-weight: bold;
            color: #c8a55c;
            margin-top: 4px;
        }

        /* Footer */
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
         COVER PAGE (Matching User Screenshot 2)
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
        </div>
    </div>
    <?php endif; ?>

    <!-- ===================================================================
         PRODUCT PAGES (Matching User Screenshot 3)
         =================================================================== -->
    <?php
    $itemChunks = array_chunk($itemsToDisplay, 2);
    foreach ($itemChunks as $chunk):
    ?>
    <div class="product-page">
        <table class="product-pair-table">
            <tr>
                <?php foreach ($chunk as $item): 
                    $imgBase64 = get_image_base64_for_pdf($item['image']);
                    $effectivePrice = (float)($item['sale_price'] > 0 ? $item['sale_price'] : $item['price']);
                ?>
                <td class="product-col">
                    <div class="product-img-box">
                        <?php if ($imgBase64): ?>
                            <img src="<?php echo $imgBase64; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php else: ?>
                            <div style="padding-top: 200px; color: #94a3b8; font-size: 11px;">Photo Unavailable</div>
                        <?php endif; ?>
                    </div>

                    <div class="product-link-row">
                        <?php if ($showProductLink): ?>
                            <a href="<?php echo htmlspecialchars($item['url']); ?>" class="product-view-link" target="_blank">
                                View: <?php echo htmlspecialchars($item['sku']); ?>
                            </a>
                        <?php else: ?>
                            <div class="product-view-link">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                        <?php endif; ?>

                        <?php if ($showPrice && $effectivePrice > 0): ?>
                            <div class="product-price-label">₹<?php echo number_format($effectivePrice, 0); ?></div>
                        <?php endif; ?>
                    </div>
                </td>
                <?php endforeach; ?>

                <?php if (count($chunk) === 1): ?>
                    <td class="product-col" style="border:none; background:transparent;"></td>
                <?php endif; ?>
            </tr>
        </table>
    </div>
    <?php endforeach; ?>

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
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $cleanFilename = preg_replace('/[^A-Za-z0-9_-]/', '_', $catalogueTitle);
    if (empty($cleanFilename)) $cleanFilename = 'Catalog';
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
