<?php
// admin/generate-yn-products-excel.php
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/includes/auth.php';
}
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

set_time_limit(300);
ini_set('memory_limit', '512M');

// Base URL calculation
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 0) == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dirName = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
if ($dirName === '' || $dirName === '.') {
    $baseUrl = $protocol . $domainName;
} else {
    $baseUrl = $protocol . $domainName . $dirName;
}

$templatePath = __DIR__ . '/../yn_products.xlsx';
$adminCopyPath = __DIR__ . '/yn_products.xlsx';

function generateYNProductsExcel($pdo, $templatePath, $adminCopyPath, $baseUrl) {
    // 1. Fetch active products
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.deleted_at IS NULL 
        ORDER BY p.id ASC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Pre-fetch gallery images map
    $galleryMap = [];
    try {
        $imgStmt = $pdo->query("
            SELECT product_id, GROUP_CONCAT(image_path SEPARATOR ',') as images 
            FROM product_images 
            GROUP BY product_id
        ");
        while ($row = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
            $galleryMap[$row['product_id']] = $row['images'];
        }
    } catch (Exception $e) {
        // Table might not exist or be empty
    }

    // 3. Load or create Spreadsheet
    if (file_exists($templatePath)) {
        $spreadsheet = IOFactory::load($templatePath);
    } else {
        $spreadsheet = new Spreadsheet();
    }
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Sheet1');

    // Clean existing rows from row 3 onwards
    $highestRow = $sheet->getHighestRow();
    if ($highestRow >= 3) {
        $sheet->removeRow(3, $highestRow - 2);
    }

    // 4. Build data rows
    $rows = [];
    foreach ($products as $p) {
        $sku = !empty($p['sku']) ? $p['sku'] : ('YN-PROD-' . $p['id']);
        $title = $p['name'];
        
        // Clean description
        $descRaw = !empty($p['description']) ? $p['description'] : (!empty($p['short_description']) ? $p['short_description'] : $title);
        $descClean = trim(html_entity_decode(strip_tags($descRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $descClean = preg_replace('/\s+/', ' ', $descClean);
        if (mb_strlen($descClean) > 4990) {
            $descClean = mb_substr($descClean, 0, 4990) . '...';
        }

        // Availability
        $stockQty = (int)($p['stock_qty'] ?? 0);
        $availability = ($stockQty > 0) ? 'in_stock' : 'out_of_stock';

        // Product Link
        $productLink = $baseUrl . '/product/' . $p['slug'];

        // Main Image Link
        $mainImg = $p['main_image'] ?? '';
        if (!empty($mainImg)) {
            if (str_starts_with($mainImg, 'http://') || str_starts_with($mainImg, 'https://')) {
                $mainImgUrl = $mainImg;
            } else {
                $mainImgUrl = $baseUrl . '/' . ltrim($mainImg, '/');
            }
        } else {
            $mainImgUrl = '';
        }

        // Prices
        $priceVal = number_format((float)$p['price'], 2, '.', '') . ' INR';
        $salePriceVal = ($p['sale_price'] && (float)$p['sale_price'] > 0) ? number_format((float)$p['sale_price'], 2, '.', '') . ' INR' : '';

        // Identifier
        $identifierExists = !empty($p['sku']) ? 'yes' : 'no';
        $mpn = !empty($p['sku']) ? $p['sku'] : '';

        // Short highlight
        $shortDescRaw = !empty($p['short_description']) ? $p['short_description'] : $descClean;
        $shortDescClean = trim(html_entity_decode(strip_tags($shortDescRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (mb_strlen($shortDescClean) > 150) {
            $shortDescClean = mb_substr($shortDescClean, 0, 147) . '...';
        }

        // Gallery Images
        $additionalImgUrls = '';
        if (isset($galleryMap[$p['id']]) && !empty($galleryMap[$p['id']])) {
            $galImgs = explode(',', $galleryMap[$p['id']]);
            $fullGalUrls = [];
            foreach ($galImgs as $gImg) {
                $gImg = trim($gImg);
                if (empty($gImg)) continue;
                if (str_starts_with($gImg, 'http://') || str_starts_with($gImg, 'https://')) {
                    $fullGalUrls[] = $gImg;
                } else {
                    $fullGalUrls[] = $baseUrl . '/' . ltrim($gImg, '/');
                }
            }
            $additionalImgUrls = implode(',', array_slice($fullGalUrls, 0, 10));
        }

        $categoryName = $p['category_name'] ?? '';

        $rows[] = [
            $sku,                   // A: id
            $title,                 // B: title
            $descClean,             // C: description
            $availability,          // D: availability
            '',                     // E: availability_date
            '',                     // F: expiration_date
            $productLink,           // G: link
            '',                     // H: mobile_link
            $mainImgUrl,            // I: image_link
            $priceVal,              // J: price
            $salePriceVal,          // K: sale_price
            '',                     // L: sale_price_effective_date
            $identifierExists,      // M: identifier_exists
            '',                     // N: gtin
            $mpn,                   // O: mpn
            'YosshitaNeha',         // P: brand
            $shortDescClean,        // Q: product_highlight
            '',                     // R: product_detail
            $additionalImgUrls,     // S: additional_image_link
            'new',                  // T: condition
            'no',                   // U: adult
            '',                     // V: color
            '',                     // W: size
            '',                     // X: size_type
            '',                     // Y: size_system
            'female',               // Z: gender
            '',                     // AA: material
            '',                     // AB: pattern
            'adult',                // AC: age_group
            '',                     // AD: multipack
            'no',                   // AE: is bundle
            '',                     // AF: unit_pricing_measure
            '',                     // AG: unit_pricing_base_measure
            '',                     // AH: energy_efficiency_class
            '',                     // AI: min_energy_efficiency_class
            '',                     // AJ: max_energy_efficiency
            $categoryName,          // AK: item_group_id
            '',                     // AL: video_link
            '',                     // AM: virtual_model_link
            ''                      // AN: cost_of_goods_sold
        ];
    }

    // Insert rows starting at row 3
    if (!empty($rows)) {
        $sheet->fromArray($rows, null, 'A3', true);
    }

    // Save output
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($templatePath);
    try {
        @copy($templatePath, $adminCopyPath);
    } catch (Exception $e) {}

    return count($products);
}

// Check if download action requested
$download = isset($_GET['download']) && $_GET['download'] == '1';

// Always generate / update Excel file on visit
try {
    $totalExported = generateYNProductsExcel($pdo, $templatePath, $adminCopyPath, $baseUrl);
} catch (Exception $e) {
    die("Error generating Excel sheet: " . $e->getMessage());
}

// Handle direct download if requested
if ($download) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="yn_products.xlsx"');
    header('Content-Length: ' . filesize($templatePath));
    header('Cache-Control: max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($templatePath);
    exit();
}

// Otherwise render Admin UI Page
$page_title = "YN Products Excel Generator";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$fileSizeKB = file_exists($templatePath) ? round(filesize($templatePath) / 1024, 2) : 0;
$lastModified = file_exists($templatePath) ? date('d M Y, h:i A', filemtime($templatePath)) : 'N/A';
?>

<div class="wrap-header" style="margin-bottom: 25px;">
    <div>
        <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin-bottom: 5px;">
            <i class="fa-solid fa-file-excel" style="color: #16a34a; margin-right: 8px;"></i>
            YN Products Excel Generator
        </h1>
        <p style="color: #64748b; font-size: 14px; margin: 0;">
            Automatically exports and syncs all active products into Google Merchant / Shopping Feed format (<code>yn_products.xlsx</code>).
        </p>
    </div>
</div>

<!-- Success Alert Banner -->
<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 5px solid #16a34a; padding: 18px 22px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
    <div style="display: flex; align-items: center; gap: 14px;">
        <div style="width: 44px; height: 44px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #16a34a; font-size: 20px; flex-shrink: 0;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div>
            <h3 style="font-size: 16px; font-weight: 700; color: #14532d; margin: 0 0 3px 0;">yn_products.xlsx Updated Successfully!</h3>
            <p style="font-size: 13px; color: #15803d; margin: 0;">
                The Excel file has been refreshed with <strong><?php echo number_format($totalExported); ?></strong> products.
            </p>
        </div>
    </div>
    <div>
        <a href="generate-yn-products-excel.php?download=1" class="button" style="background: #16a34a; color: #fff; border-color: #15803d; padding: 10px 20px; font-size: 14px; font-weight: 600; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 6px rgba(22, 163, 74, 0.3);">
            <i class="fa-solid fa-download"></i> Download Excel (.xlsx)
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
        <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 6px; letter-spacing: 0.5px;">Total Products Exported</div>
        <div style="font-size: 28px; font-weight: 700; color: #0f172a;"><?php echo number_format($totalExported); ?></div>
        <div style="font-size: 12px; color: #16a34a; margin-top: 4px;"><i class="fa-solid fa-check-double"></i> Active products in catalog</div>
    </div>

    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
        <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 6px; letter-spacing: 0.5px;">Feed Columns</div>
        <div style="font-size: 28px; font-weight: 700; color: #0f172a;">40</div>
        <div style="font-size: 12px; color: #0284c7; margin-top: 4px;"><i class="fa-solid fa-table-columns"></i> Google Merchant Feed Format</div>
    </div>

    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
        <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 6px; letter-spacing: 0.5px;">File Size</div>
        <div style="font-size: 28px; font-weight: 700; color: #0f172a;"><?php echo $fileSizeKB; ?> <span style="font-size: 16px; font-weight: 500; color: #64748b;">KB</span></div>
        <div style="font-size: 12px; color: #64748b; margin-top: 4px;"><i class="fa-solid fa-file"></i> yn_products.xlsx</div>
    </div>

    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
        <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 6px; letter-spacing: 0.5px;">Last Generated</div>
        <div style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 6px;"><?php echo $lastModified; ?></div>
        <div style="font-size: 12px; color: #16a34a; margin-top: 4px;"><i class="fa-solid fa-rotate"></i> Auto-synced on visit</div>
    </div>
</div>

<!-- Detailed Card & Direct Link -->
<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
    <h3 style="font-size: 16px; font-weight: 700; color: #1e293b; margin: 0 0 15px 0;">
        <i class="fa-solid fa-link" style="color: #6366f1; margin-right: 6px;"></i> Direct File Access & Feeds
    </h3>
    
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 18px; margin-bottom: 20px;">
        <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 5px;">Root File Link (Server Path):</label>
        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" readonly value="<?php echo $baseUrl . '/yn_products.xlsx'; ?>" style="width: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 12px; font-family: monospace; font-size: 13px; color: #334155;">
            <a href="../yn_products.xlsx" target="_blank" class="button" style="white-space: nowrap; font-weight: 600;">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open File
            </a>
        </div>
    </div>

    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="generate-yn-products-excel.php?download=1" class="button button-primary" style="background: #16a34a; border-color: #15803d; font-weight: 600; padding: 8px 18px;">
            <i class="fa-solid fa-download"></i> Download Excel (.xlsx)
        </a>
        <a href="generate-yn-products-excel.php?force=1" class="button button-secondary" style="font-weight: 600; padding: 8px 18px;">
            <i class="fa-solid fa-arrows-rotate"></i> Regenerate Now
        </a>
        <a href="products.php" class="button" style="font-weight: 500; padding: 8px 18px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Products
        </a>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
