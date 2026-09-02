<?php
/**
 * Direct Server Archive Importer (Enhanced Pre-Audit & Reliable Batching)
 * Imports products from the server's "archive" folder (Spreadsheet + SKU image folders)
 * Can be run via Browser or CLI (php import_archive.php)
 */

@ini_set('max_execution_time', 0);
@ini_set('memory_limit', '2048M');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Autoload composer & project dependencies
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cache.php';

// Auth check for non-CLI requests
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Allow if authenticated admin or during API action with active session
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        if (isset($_GET['action']) && $_GET['action'] === 'process_row') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized session. Please log in to admin.']);
            exit;
        }
        header("Location: login.php");
        exit;
    }
}

// Locate the import folder (ittar outside admin, archive, etc.)
$possibleArchivePaths = [
    __DIR__ . '/../ittar',
    dirname(__DIR__) . '/ittar',
    '/home/u464193275/domains/yosshitaneha.com/public_html/ittar',
    '/domains/yosshitaneha.com/public_html/ittar',
    '/public_html/ittar',
    'C:/xampp/htdocs/yn/ittar',
    __DIR__ . '/ittar',
    __DIR__ . '/../archive',
    dirname(__DIR__) . '/archive',
    __DIR__ . '/archive',
    '/home/u464193275/domains/yosshitaneha.com/public_html/archive',
    '/domains/yosshitaneha.com/public_html/archive',
    'C:/xampp/htdocs/yn/archive',
    'C:/xampp/htdocs/yn/admin/archive'
];

$archiveDir = null;
foreach ($possibleArchivePaths as $p) {
    if (is_dir($p)) {
        $archiveDir = realpath($p);
        break;
    }
}

// Check custom path from request
if (!empty($_POST['custom_path']) || !empty($_GET['custom_path'])) {
    $cPath = trim($_POST['custom_path'] ?? $_GET['custom_path']);
    if (is_dir($cPath)) {
        $archiveDir = realpath($cPath);
    } elseif (is_dir(__DIR__ . '/' . $cPath)) {
        $archiveDir = realpath(__DIR__ . '/' . $cPath);
    } elseif (is_dir(dirname(__DIR__) . '/' . $cPath)) {
        $archiveDir = realpath(dirname(__DIR__) . '/' . $cPath);
    } elseif (is_dir(__DIR__ . '/../' . ltrim($cPath, '/\\'))) {
        $archiveDir = realpath(__DIR__ . '/../' . ltrim($cPath, '/\\'));
    } elseif (is_dir('/home/u464193275/domains/yosshitaneha.com/public_html/' . ltrim($cPath, '/\\'))) {
        $archiveDir = realpath('/home/u464193275/domains/yosshitaneha.com/public_html/' . ltrim($cPath, '/\\'));
    } elseif (is_dir('/domains/yosshitaneha.com/public_html/' . ltrim($cPath, '/\\'))) {
        $archiveDir = realpath('/domains/yosshitaneha.com/public_html/' . ltrim($cPath, '/\\'));
    } elseif (is_dir('/' . ltrim($cPath, '/\\'))) {
        $archiveDir = realpath('/' . ltrim($cPath, '/\\'));
    }
}

// Ensure default fallback directory exists if none found
if (!$archiveDir) {
    $defaultIttar = dirname(__DIR__) . '/ittar';
    if (is_dir($defaultIttar)) {
        $archiveDir = realpath($defaultIttar);
    } else {
        $defaultArchive = __DIR__ . '/archive';
        if (!file_exists($defaultArchive)) {
            @mkdir($defaultArchive, 0777, true);
        }
        if (is_dir($defaultArchive)) {
            $archiveDir = realpath($defaultArchive);
        }
    }
}

// Action: Download Blank Excel/CSV Template
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $format = strtolower($_GET['format'] ?? 'xlsx');
    $sampleData = [
        ['sku', 'name', 'categories', 'price', 'sale_price', 'stock_qty', 'description', 'short_description', 'status', 'is_featured'],
        ['IT101', 'Royal Oudh Ittar 10ml', 'Ittar, Perfumes > Oudh', '1499', '1199', '25', 'Premium long-lasting concentrated pure Oudh ittar perfume oil.', 'Pure Oudh fragrance oil 10ml', 'published', '1'],
        ['IT102', 'Gulab Khas Rose Ittar 10ml', 'Ittar, Perfumes > Floral', '999', '799', '30', 'Authentic distilled Indian Damask rose ittar fragrance with sweet floral notes.', 'Indian Rose fragrance oil 10ml', 'published', '0'],
        ['IT103', 'Mitti Attar 10ml (Petrichor)', 'Ittar, Perfumes > Earthy', '1299', '999', '20', 'The iconic scent of baked earth after the first monsoon rain.', 'Natural petrichor baked earth ittar', 'published', '1'],
    ];

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="product_import_template.csv"');
        $output = fopen('php://output', 'w');
        foreach ($sampleData as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    } else {
        if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Products');

            // Populate data
            $sheet->fromArray($sampleData, null, 'A1');

            // Style headers
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5']
                ],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ];
            $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
            foreach (range('A', 'J') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="product_import_template.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } else {
            // Fallback to CSV if PhpSpreadsheet not available
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="product_import_template.csv"');
            $output = fopen('php://output', 'w');
            foreach ($sampleData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        }
    }
}

// Action: Auto-generate spreadsheet directly from SKU folders in active directory
if (isset($_GET['action']) && $_GET['action'] === 'generate_from_folders') {
    if (!$archiveDir || !is_dir($archiveDir)) {
        header("Location: import_archive.php?error=" . urlencode("Archive directory not found"));
        exit;
    }

    $entries = scandir($archiveDir);
    $skuFolders = [];
    foreach ($entries as $e) {
        if ($e === '.' || $e === '..') continue;
        if (is_dir($archiveDir . DIRECTORY_SEPARATOR . $e)) {
            $skuFolders[] = trim($e);
        }
    }

    if (empty($skuFolders)) {
        header("Location: import_archive.php?error=" . urlencode("No SKU folders found in " . basename($archiveDir) . "/"));
        exit;
    }

    sort($skuFolders);

    $rows = [
        ['sku', 'name', 'categories', 'price', 'sale_price', 'stock_qty', 'description', 'short_description', 'status', 'is_featured']
    ];

    $defaultCat = (stripos(basename($archiveDir), 'ittar') !== false || stripos(basename($archiveDir), 'attar') !== false) ? 'Ittar' : 'Fashion';

    foreach ($skuFolders as $sku) {
        $cleanName = ucwords(str_replace(['_', '-'], ' ', $sku));
        $rows[] = [
            $sku,
            $cleanName,
            $defaultCat,
            '999',
            '799',
            '10',
            $cleanName . ' - Premium Quality Collection.',
            $cleanName,
            'published',
            '0'
        ];
    }

    $targetExcel = $archiveDir . DIRECTORY_SEPARATOR . 'products_' . strtolower(basename($archiveDir)) . '.xlsx';

    if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');
        $sheet->fromArray($rows, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '16A34A']
            ]
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($targetExcel);
    } else {
        $targetCsv = $archiveDir . DIRECTORY_SEPARATOR . 'products_' . strtolower(basename($archiveDir)) . '.csv';
        $fp = fopen($targetCsv, 'w');
        foreach ($rows as $r) {
            fputcsv($fp, $r);
        }
        fclose($fp);
    }

    header("Location: import_archive.php?custom_path=" . urlencode($archiveDir) . "&msg=" . urlencode("Successfully generated spreadsheet with " . count($skuFolders) . " SKUs from your folders!"));
    exit;
}

// Helper: Resolve or Create Category Tree
function resolve_or_create_category($pdo, $categoryInput) {
    if (empty($categoryInput)) return null;
    
    $branches = is_array($categoryInput) ? $categoryInput : explode(',', (string)$categoryInput);
    $categoryIds = [];

    foreach ($branches as $branch) {
        $branch = trim((string)$branch);
        if (empty($branch)) continue;

        // If numeric ID given
        if (is_numeric($branch) && (int)$branch > 0) {
            $categoryIds[] = (int)$branch;
            continue;
        }

        // Check if hierarchical with > or /
        $parts = preg_split('/[>\/]/', $branch);
        $parentId = null;
        $currentId = null;

        foreach ($parts as $part) {
            $catName = trim($part);
            if (empty($catName)) continue;

            $slug = generate_slug($catName);
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$slug]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $currentId = (int)$existing['id'];
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
                $insertStmt->execute([$catName, $slug, $parentId]);
                $currentId = (int)$pdo->lastInsertId();
            }
            $parentId = $currentId;
        }

        if ($currentId) {
            $categoryIds[] = $currentId;
        }
    }

    return array_values(array_unique(array_filter($categoryIds)));
}

// AJAX API Handler for Batch Row Processing
if (isset($_GET['action']) && $_GET['action'] === 'process_row') {
    header('Content-Type: application/json');
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            echo json_encode([
                'status' => 'error',
                'sku' => 'UNKNOWN',
                'message' => 'PHP Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ' line ' . $error['line']
            ]);
        }
    });

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    try {
        $code = trim($input['sku'] ?? $input['sku_code'] ?? $input['product_code'] ?? $input['code'] ?? '');
        $name = trim($input['name'] ?? $input['product_name'] ?? $input['title'] ?? '');
        $skuFolder = trim($input['sku_folder_path'] ?? '');

        if (empty($code)) {
            throw new \Exception("Missing SKU for product item.");
        }

        if (empty($name)) {
            $name = 'Product ' . $code;
        }

        // 1. Check if SKU already exists in products table -> SKIP if exists
        $checkStmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
        $checkStmt->execute([$code]);
        if ($checkStmt->fetch()) {
            echo json_encode([
                'status' => 'skipped',
                'sku' => $code,
                'message' => "SKU $code already exists in database. Skipped."
            ]);
            exit;
        }

        // 2. Prepare Upload Destination Folder for SKU
        $relativeUploadDir = 'uploads/products/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $code);
        $absoluteTargetDir = __DIR__ . '/' . $relativeUploadDir;
        $thumbDir = $absoluteTargetDir . '/thumbs';

        if (!is_dir($absoluteTargetDir)) {
            @mkdir($absoluteTargetDir, 0777, true);
        }
        if (!is_dir($thumbDir)) {
            @mkdir($thumbDir, 0777, true);
        }

        // 3. Process & Copy Images from SKU Folder
        $downloadedImages = [];
        if (!empty($skuFolder) && is_dir($skuFolder)) {
            $files = scandir($skuFolder);
            $validExts = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
            $imgIdx = 0;

            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $filePath = $skuFolder . DIRECTORY_SEPARATOR . $f;

                if (is_file($filePath)) {
                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    if (in_array($ext, $validExts)) {
                        $imgIdx++;
                        $safeSku = preg_replace('/[^a-zA-Z0-9_-]/', '_', $code);
                        $newFilename = $safeSku . '_' . $imgIdx . '_' . time() . '.' . $ext;
                        $destFilePath = $absoluteTargetDir . '/' . $newFilename;

                        if (@copy($filePath, $destFilePath)) {
                            $thumbFilename = 'thumb_' . $newFilename;
                            $thumbDestPath = $thumbDir . '/' . $thumbFilename;

                            // Generate square thumbnail using helper
                            if (function_exists('generate_square_thumbnail')) {
                                generate_square_thumbnail($destFilePath, $thumbDestPath, 150);
                            }

                            $downloadedImages[] = [
                                'filepath' => $relativeUploadDir . '/' . $newFilename,
                                'thumbpath' => file_exists($thumbDestPath) ? ($relativeUploadDir . '/thumbs/' . $thumbFilename) : ($relativeUploadDir . '/' . $newFilename)
                            ];
                        }
                    }
                }
            }
        }

        // 4. Category Resolution
        $rawCats = !empty($input['categories']) ? $input['categories'] : ($input['category'] ?? $input['category_id'] ?? '');
        $catIds = resolve_or_create_category($pdo, $rawCats);
        $primaryCatId = !empty($catIds) ? $catIds[0] : null;

        // 5. Price & Stock Values
        $price = (float)($input['price'] ?? $input['regular_price'] ?? $input['s_price'] ?? $input['sales_price'] ?? 0);
        $salePrice = !empty($input['sale_price']) ? (float)$input['sale_price'] : (!empty($input['rental_price']) ? (float)$input['rental_price'] : null);
        if ($price <= 0 && $salePrice > 0) {
            $price = $salePrice;
        }

        $stockQty = isset($input['stock_qty']) ? (int)$input['stock_qty'] : (isset($input['stock']) ? (int)$input['stock'] : 10);
        $status = in_array(strtolower($input['status'] ?? ''), ['draft', 'published']) ? strtolower($input['status']) : 'published';
        $isFeatured = (!empty($input['is_featured']) && (int)$input['is_featured'] === 1) ? 1 : 0;
        $description = trim($input['description'] ?? $input['desc'] ?? '');
        $shortDescription = trim($input['short_description'] ?? $input['short_desc'] ?? '');

        // Generate Slug
        $slug = generate_slug($name);
        $checkSlug = $pdo->prepare("SELECT id FROM products WHERE slug = ? LIMIT 1");
        $checkSlug->execute([$slug]);
        if ($checkSlug->fetch()) {
            $slug .= '-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $code)) . '-' . substr(uniqid(), -4);
        }

        $mainImagePath = !empty($downloadedImages) ? $downloadedImages[0]['filepath'] : null;

        // 6. Insert Product into Database
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO products 
            (category_id, name, slug, sku, description, short_description, price, sale_price, stock_qty, is_featured, status, main_image) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $primaryCatId,
            $name,
            $slug,
            $code,
            $description,
            $shortDescription,
            $price,
            $salePrice,
            $stockQty,
            $isFeatured,
            $status,
            $mainImagePath
        ]);
        $productId = (int)$pdo->lastInsertId();

        // 7. Insert into product_categories
        if (!empty($catIds)) {
            $catStmt = $pdo->prepare("INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)");
            foreach ($catIds as $cId) {
                $catStmt->execute([$productId, $cId]);
            }
        }

        // 8. Insert gallery images into product_images
        if (count($downloadedImages) > 1) {
            $imgStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, thumb_path, sort_order) VALUES (?, ?, ?, ?)");
            for ($i = 1; $i < count($downloadedImages); $i++) {
                $imgStmt->execute([
                    $productId,
                    $downloadedImages[$i]['filepath'],
                    $downloadedImages[$i]['thumbpath'],
                    $i
                ]);
            }
        }

        $pdo->commit();

        if (function_exists('purge_cache')) {
            purge_cache();
        }

        echo json_encode([
            'status' => 'success',
            'sku' => $code,
            'images_count' => count($downloadedImages),
            'message' => "Created product $code with " . count($downloadedImages) . " images."
        ]);
        exit;

    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'sku' => $code ?? 'UNKNOWN',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Function to scan archive folder for spreadsheet & SKU folders, with fast DB check
function scanArchiveDirectory($archiveDir, $pdo) {
    if (!$archiveDir || !is_dir($archiveDir)) {
        return ['error' => 'Archive directory not found: ' . htmlspecialchars($archiveDir)];
    }

    $spreadsheetFile = null;
    $skuFolders = [];
    $allEntries = scandir($archiveDir);

    foreach ($allEntries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $fullPath = $archiveDir . DIRECTORY_SEPARATOR . $entry;

        if (is_file($fullPath)) {
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if (in_array($ext, ['xlsx', 'xls', 'csv'])) {
                $spreadsheetFile = $fullPath;
            }
        } elseif (is_dir($fullPath)) {
            $skuFolders[strtolower(trim($entry))] = $fullPath;
        }
    }

    if (!$spreadsheetFile) {
        return [
            'archive_dir' => $archiveDir,
            'total_folders' => count($skuFolders),
            'error' => 'No .xlsx, .xls, or .csv spreadsheet found in ' . $archiveDir
        ];
    }

    // Read spreadsheet rows
    $parsedProducts = [];
    $ext = strtolower(pathinfo($spreadsheetFile, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        $handle = fopen($spreadsheetFile, 'r');
        if ($handle) {
            $headers = [];
            $rowIdx = 0;
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $rowIdx++;
                if ($rowIdx === 1) {
                    $headers = array_map(function($h) {
                        return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', (string)$h)));
                    }, $row);
                    continue;
                }
                if (empty(array_filter($row))) continue;
                $item = [];
                foreach ($headers as $idx => $header) {
                    $item[$header] = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
                }
                $parsedProducts[] = $item;
            }
            fclose($handle);
        }
    } else {
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($spreadsheetFile);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);

                if (!empty($rows) && count($rows) > 1) {
                    $rawHeaders = $rows[0];
                    $headers = array_map(function($h) {
                        return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', (string)$h)));
                    }, $rawHeaders);

                    for ($i = 1; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        if (empty(array_filter($row, function($v) { return $v !== null && $v !== ''; }))) continue;
                        $item = [];
                        foreach ($headers as $idx => $header) {
                            if (!empty($header)) {
                                $val = $row[$idx] ?? '';
                                $item[$header] = is_string($val) ? trim($val) : (string)$val;
                            }
                        }
                        $parsedProducts[] = $item;
                    }
                }
            } catch (\Exception $e) {
                return ['error' => 'Spreadsheet parse error: ' . $e->getMessage()];
            }
        } else {
            return ['error' => 'PhpSpreadsheet is not loaded. Please use a CSV file or check vendor/autoload.php.'];
        }
    }

    // Match products with SKU folders
    $validProducts = [];
    $skuList = [];
    foreach ($parsedProducts as $p) {
        $sku = trim($p['sku'] ?? $p['sku_code'] ?? $p['product_code'] ?? $p['code'] ?? '');
        $name = trim($p['name'] ?? $p['product_name'] ?? $p['title'] ?? '');
        if (empty($sku) && empty($name)) continue;

        if (empty($sku)) {
            $sku = 'SKU-' . strtoupper(substr(md5($name), 0, 6));
        }
        $p['sku'] = $sku;

        $skuKey = strtolower($sku);
        $matchedPath = $skuFolders[$skuKey] ?? null;

        // Try loose matching (without hyphens/underscores/spaces)
        if (!$matchedPath) {
            $normKey = preg_replace('/[^a-zA-Z0-9]/', '', $skuKey);
            foreach ($skuFolders as $fKey => $fPath) {
                if (preg_replace('/[^a-zA-Z0-9]/', '', $fKey) === $normKey) {
                    $matchedPath = $fPath;
                    break;
                }
            }
        }

        $imgCount = 0;
        if ($matchedPath && is_dir($matchedPath)) {
            $imgs = scandir($matchedPath);
            $validExts = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
            foreach ($imgs as $im) {
                if ($im !== '.' && $im !== '..') {
                    $ext = strtolower(pathinfo($im, PATHINFO_EXTENSION));
                    if (in_array($ext, $validExts)) $imgCount++;
                }
            }
        }

        $p['sku_folder_path'] = $matchedPath ?: '';
        $p['images_count'] = $imgCount;
        $validProducts[] = $p;
        $skuList[] = $sku;
    }

    // Fast Single Bulk DB Query to Pre-Check Existing SKUs
    $existingSkus = [];
    if (!empty($skuList)) {
        foreach (array_chunk($skuList, 400) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare("SELECT sku FROM products WHERE sku IN ($placeholders)");
            $stmt->execute($chunk);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existingSkus[strtolower($row['sku'])] = true;
            }
        }
    }

    $readyCount = 0;
    $existCount = 0;

    foreach ($validProducts as &$p) {
        $key = strtolower($p['sku']);
        if (isset($existingSkus[$key])) {
            $p['pre_status'] = 'exists';
            $p['pre_message'] = 'Already in DB';
            $existCount++;
        } else {
            $p['pre_status'] = 'ready';
            $p['pre_message'] = 'Ready to Create (' . $p['images_count'] . ' photos)';
            $readyCount++;
        }
    }

    return [
        'archive_dir' => $archiveDir,
        'spreadsheet_file' => basename($spreadsheetFile),
        'total_folders' => count($skuFolders),
        'total_products' => count($validProducts),
        'ready_count' => $readyCount,
        'exist_count' => $existCount,
        'products' => $validProducts
    ];
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? 'import_archive.php';

// Handle file uploads (Excel / CSV or ZIP Archive)
$uploadMessage = $_GET['msg'] ?? null;
$uploadError = $_GET['error'] ?? null;

if ($requestMethod === 'POST') {
    // 1. Direct Excel / CSV Upload
    if (isset($_FILES['excel_file'])) {
        if (!$archiveDir || !is_dir($archiveDir)) {
            $uploadError = "Target folder does not exist or is not specified.";
        } else {
            $uploaded = $_FILES['excel_file'];
            if ($uploaded['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($uploaded['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['xlsx', 'xls', 'csv'])) {
                    $targetFile = $archiveDir . DIRECTORY_SEPARATOR . $uploaded['name'];
                    if (move_uploaded_file($uploaded['tmp_name'], $targetFile)) {
                        $uploadMessage = "Successfully uploaded " . htmlspecialchars($uploaded['name']) . " to " . htmlspecialchars(basename($archiveDir)) . "/";
                    } else {
                        $uploadError = "Failed to move uploaded file. Check folder write permissions on: " . htmlspecialchars($archiveDir);
                    }
                } else {
                    $uploadError = "Invalid file type (." . htmlspecialchars($ext) . "). Please upload a .xlsx, .xls, or .csv file.";
                }
            } else {
                $uploadError = "File upload failed with error code: " . $uploaded['error'];
            }
        }
    }

    // 2. ZIP Archive Upload (extracts SKU folders & spreadsheet)
    if (isset($_FILES['zip_file'])) {
        if (!$archiveDir || !is_dir($archiveDir)) {
            $uploadError = "Target folder does not exist or is not specified.";
        } else {
            $uploaded = $_FILES['zip_file'];
            if ($uploaded['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($uploaded['name'], PATHINFO_EXTENSION));
                if ($ext === 'zip' && class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($uploaded['tmp_name']) === TRUE) {
                        $zip->extractTo($archiveDir);
                        $zip->close();
                        $uploadMessage = "Successfully extracted ZIP archive (" . htmlspecialchars($uploaded['name']) . ") into " . htmlspecialchars(basename($archiveDir)) . "/";
                    } else {
                        $uploadError = "Failed to open and extract the ZIP archive.";
                    }
                } else {
                    $uploadError = "Invalid ZIP file or ZipArchive PHP extension not enabled.";
                }
            } else {
                $uploadError = "ZIP upload failed with error code: " . $uploaded['error'];
            }
        }
    }
}

$scanResult = scanArchiveDirectory($archiveDir, $pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Archive & SKU Importer - Yosshita Neha Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #0f172a; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 md:p-10">
    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Top Bar -->
        <div class="bg-slate-900/90 border border-slate-800 p-6 rounded-2xl shadow-xl flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 text-xl">
                    <i class="fas fa-folder-tree"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white flex items-center gap-2">
                        Bulk Product &amp; SKU Archive Importer
                        <span class="px-2.5 py-0.5 bg-emerald-500/20 text-emerald-400 text-[10px] font-bold rounded-full border border-emerald-500/30 uppercase">Direct Engine</span>
                    </h1>
                    <p class="text-xs text-slate-400 mt-0.5">Pre-audited direct import matching spreadsheet records with SKU image folders.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="products.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold border border-slate-700 transition-all flex items-center gap-1.5">
                    <i class="fas fa-arrow-left"></i> Back to Products
                </a>
            </div>
        </div>

        <?php if ($uploadMessage): ?>
            <div class="bg-emerald-950/40 border border-emerald-500/40 p-4 rounded-2xl text-xs text-emerald-300 flex items-center gap-2.5">
                <i class="fas fa-check-circle text-emerald-400 text-base"></i>
                <span><?php echo $uploadMessage; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($uploadError): ?>
            <div class="bg-rose-950/40 border border-rose-500/40 p-4 rounded-2xl text-xs text-rose-300 flex items-center gap-2.5">
                <i class="fas fa-exclamation-circle text-rose-400 text-base"></i>
                <span><?php echo $uploadError; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($scanResult['error'])): ?>
            <!-- Error / Setup Card -->
            <div class="bg-slate-900/90 border border-slate-800 p-6 rounded-2xl text-xs space-y-5">
                <div class="flex items-center justify-between flex-wrap gap-3 pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2.5 text-sm font-bold text-amber-400">
                        <i class="fas fa-file-circle-exclamation text-lg"></i>
                        <span>Spreadsheet Needed in <code class="text-white bg-slate-950 px-2 py-0.5 rounded border border-slate-800"><?php echo htmlspecialchars(basename($archiveDir)); ?>/</code></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="import_archive.php?action=download_template&format=xlsx" class="px-3 py-1.5 bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 hover:bg-indigo-600/30 font-semibold rounded-lg text-xs transition-all flex items-center gap-1.5">
                            <i class="fas fa-file-excel"></i> Download Sample Excel (.xlsx)
                        </a>
                        <a href="import_archive.php?action=download_template&format=csv" class="px-3 py-1.5 bg-slate-800 text-slate-300 border border-slate-700 hover:bg-slate-700 font-semibold rounded-lg text-xs transition-all flex items-center gap-1.5">
                            <i class="fas fa-file-csv"></i> Download CSV
                        </a>
                    </div>
                </div>

                <?php if ($archiveDir && is_dir($archiveDir) && !empty($scanResult['total_folders'])): ?>
                    <!-- Quick 1-Click Auto Generator Box -->
                    <div class="bg-emerald-950/40 border border-emerald-500/40 rounded-xl p-5 flex items-center justify-between flex-wrap gap-4">
                        <div class="space-y-1">
                            <div class="text-xs font-bold text-emerald-300 flex items-center gap-2">
                                <i class="fas fa-magic text-emerald-400"></i> Found <?php echo $scanResult['total_folders']; ?> SKU folder(s) in <?php echo htmlspecialchars(basename($archiveDir)); ?>/
                            </div>
                            <p class="text-[11px] text-emerald-400/80">You can instantly auto-generate a pre-filled Excel spreadsheet with all your folder SKUs with 1 click!</p>
                        </div>
                        <a href="import_archive.php?action=generate_from_folders&custom_path=<?php echo urlencode($archiveDir); ?>" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-all flex items-center gap-2 shadow-lg shadow-emerald-600/30">
                            <i class="fas fa-bolt"></i> Auto-Generate Excel for <?php echo $scanResult['total_folders']; ?> SKUs
                        </a>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Direct Excel / CSV Uploader -->
                    <div class="bg-slate-950 border border-indigo-500/30 rounded-xl p-5 space-y-3">
                        <div class="flex items-center gap-2 text-xs font-bold text-indigo-300">
                            <i class="fas fa-file-excel text-emerald-400"></i> Option 1: Upload Existing Spreadsheet (.xlsx / .csv)
                        </div>
                        <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($requestUri); ?>" class="space-y-3">
                            <input type="hidden" name="custom_path" value="<?php echo htmlspecialchars($archiveDir); ?>">
                            <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-slate-300 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 focus:outline-none">
                            <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-all flex items-center justify-center gap-1.5 shadow-lg shadow-emerald-600/20">
                                <i class="fas fa-upload"></i> Upload &amp; Start Audit
                            </button>
                        </form>
                    </div>

                    <!-- ZIP Archive Uploader -->
                    <div class="bg-slate-950 border border-yellow-500/30 rounded-xl p-5 space-y-3">
                        <div class="flex items-center gap-2 text-xs font-bold text-yellow-400">
                            <i class="fas fa-file-zipper text-yellow-400"></i> Option 2: Upload Full ZIP (Spreadsheet + SKU Folders)
                        </div>
                        <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($requestUri); ?>" class="space-y-3">
                            <input type="hidden" name="custom_path" value="<?php echo htmlspecialchars($archiveDir); ?>">
                            <input type="file" name="zip_file" accept=".zip" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-slate-300 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-yellow-600 file:text-white hover:file:bg-yellow-700 focus:outline-none">
                            <button type="submit" class="w-full py-2 bg-yellow-600 hover:bg-yellow-700 text-white font-bold rounded-xl text-xs transition-all flex items-center justify-center gap-1.5 shadow-lg shadow-yellow-600/20">
                                <i class="fas fa-file-zipper"></i> Upload &amp; Extract ZIP
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Excel Format Guide Table -->
                <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 space-y-2">
                    <div class="text-xs font-bold text-slate-300 flex items-center gap-2">
                        <i class="fas fa-table-list text-indigo-400"></i> Excel Column Format Reference
                    </div>
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left text-[11px] font-mono border-collapse">
                            <thead>
                                <tr class="text-slate-400 border-b border-slate-800">
                                    <th class="py-1.5 px-2">Column Header</th>
                                    <th class="py-1.5 px-2">Required?</th>
                                    <th class="py-1.5 px-2">Example Value</th>
                                    <th class="py-1.5 px-2">Description</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 text-slate-300">
                                <tr>
                                    <td class="py-1.5 px-2 text-indigo-300 font-bold">sku</td>
                                    <td class="py-1.5 px-2 text-emerald-400 font-bold">Yes</td>
                                    <td class="py-1.5 px-2 text-slate-400">IT101</td>
                                    <td class="py-1.5 px-2">Must match the SKU folder name in ittar/</td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 px-2 text-indigo-300 font-bold">name</td>
                                    <td class="py-1.5 px-2 text-emerald-400 font-bold">Yes</td>
                                    <td class="py-1.5 px-2 text-slate-400">Royal Oudh Ittar 10ml</td>
                                    <td class="py-1.5 px-2">Product title displayed on store</td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 px-2 text-indigo-300 font-bold">categories</td>
                                    <td class="py-1.5 px-2 text-slate-400">Optional</td>
                                    <td class="py-1.5 px-2 text-slate-400">Ittar, Perfumes > Oudh</td>
                                    <td class="py-1.5 px-2">Category or subcategory names separated by comma</td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 px-2 text-indigo-300 font-bold">price</td>
                                    <td class="py-1.5 px-2 text-emerald-400 font-bold">Yes</td>
                                    <td class="py-1.5 px-2 text-slate-400">1499</td>
                                    <td class="py-1.5 px-2">Regular / MRP price</td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 px-2 text-indigo-300 font-bold">sale_price</td>
                                    <td class="py-1.5 px-2 text-slate-400">Optional</td>
                                    <td class="py-1.5 px-2 text-slate-400">1199</td>
                                    <td class="py-1.5 px-2">Discounted / offer price</td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 px-2 text-indigo-300 font-bold">stock_qty</td>
                                    <td class="py-1.5 px-2 text-slate-400">Optional</td>
                                    <td class="py-1.5 px-2 text-slate-400">25</td>
                                    <td class="py-1.5 px-2">Available inventory quantity (default: 10)</td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 px-2 text-indigo-300 font-bold">description</td>
                                    <td class="py-1.5 px-2 text-slate-400">Optional</td>
                                    <td class="py-1.5 px-2 text-slate-400">Pure concentrated perfume oil...</td>
                                    <td class="py-1.5 px-2">Full product details / fragrance notes</td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 px-2 text-indigo-300 font-bold">status</td>
                                    <td class="py-1.5 px-2 text-slate-400">Optional</td>
                                    <td class="py-1.5 px-2 text-slate-400">published</td>
                                    <td class="py-1.5 px-2">"published" or "draft"</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="pt-3 border-t border-rose-500/20">
                    <span class="text-[11px] text-slate-400 font-medium block mb-1.5">Or specify a folder path on the server:</span>
                    <form method="GET" class="flex gap-2">
                        <input type="text" name="custom_path" value="<?php echo htmlspecialchars($_GET['custom_path'] ?? ''); ?>" placeholder="/public_html/ittar, ittar, or custom folder path" class="flex-1 bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs font-mono text-white focus:outline-none focus:border-indigo-500">
                        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition-all">Scan Folder</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Active Scanned Folder Bar -->
            <div class="bg-slate-900/60 border border-slate-800/80 px-5 py-3.5 rounded-2xl flex items-center justify-between flex-wrap gap-3 text-xs">
                <div class="flex items-center gap-2 text-slate-300">
                    <span class="text-indigo-400 font-bold flex items-center gap-1.5"><i class="fas fa-folder-open"></i> Active Folder:</span>
                    <code class="bg-slate-950 px-2.5 py-1 rounded-lg border border-slate-800 text-indigo-300 font-mono text-[11px]"><?php echo htmlspecialchars($scanResult['archive_dir']); ?></code>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" onclick="document.getElementById('excel_upload_drawer').classList.toggle('hidden')" class="px-3 py-1.5 bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-600/30 font-semibold rounded-lg text-xs transition-all flex items-center gap-1.5">
                        <i class="fas fa-file-excel"></i> Replace Excel
                    </button>
                    <button type="button" onclick="document.getElementById('zip_upload_drawer').classList.toggle('hidden')" class="px-3 py-1.5 bg-yellow-600/20 text-yellow-400 border border-yellow-500/30 hover:bg-yellow-600/30 font-semibold rounded-lg text-xs transition-all flex items-center gap-1.5">
                        <i class="fas fa-file-zipper"></i> Upload ZIP
                    </button>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="text" name="custom_path" placeholder="Switch folder (e.g. ittar)" class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1 text-xs font-mono text-slate-200 focus:outline-none focus:border-indigo-500 w-52">
                        <button type="submit" class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-medium rounded-lg text-xs transition-all">Switch</button>
                    </form>
                </div>
            </div>

            <!-- Drawer for Uploading Excel File -->
            <div id="excel_upload_drawer" class="hidden bg-slate-900/90 border border-emerald-500/30 rounded-2xl p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-emerald-400 flex items-center gap-2">
                        <i class="fas fa-file-excel"></i> Upload / Replace Spreadsheet in <?php echo htmlspecialchars(basename($scanResult['archive_dir'])); ?>/
                    </h3>
                    <button type="button" onclick="document.getElementById('excel_upload_drawer').classList.add('hidden')" class="text-slate-400 hover:text-white text-xs">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-[11px] text-slate-400">Uploading a new Excel (.xlsx, .xls) or .csv file will save it directly into this folder and refresh the product audit table.</p>
                <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($requestUri); ?>" class="flex items-center gap-3 flex-wrap">
                    <input type="hidden" name="custom_path" value="<?php echo htmlspecialchars($scanResult['archive_dir']); ?>">
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-slate-300 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 focus:outline-none">
                    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-all flex items-center gap-1.5">
                        <i class="fas fa-upload"></i> Upload &amp; Refresh
                    </button>
                </form>
            </div>

            <!-- Drawer for Uploading ZIP Archive -->
            <div id="zip_upload_drawer" class="hidden bg-slate-900/90 border border-yellow-500/30 rounded-2xl p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-yellow-400 flex items-center gap-2">
                        <i class="fas fa-file-zipper"></i> Extract ZIP Archive (Spreadsheet &amp; SKU folders) into <?php echo htmlspecialchars(basename($scanResult['archive_dir'])); ?>/
                    </h3>
                    <button type="button" onclick="document.getElementById('zip_upload_drawer').classList.add('hidden')" class="text-slate-400 hover:text-white text-xs">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-[11px] text-slate-400">Upload a .zip file containing your product spreadsheet and SKU image folders. It will be extracted directly into the active archive folder.</p>
                <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($requestUri); ?>" class="flex items-center gap-3 flex-wrap">
                    <input type="hidden" name="custom_path" value="<?php echo htmlspecialchars($scanResult['archive_dir']); ?>">
                    <input type="file" name="zip_file" accept=".zip" required class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-slate-300 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-yellow-600 file:text-white hover:file:bg-yellow-700 focus:outline-none">
                    <button type="submit" class="px-5 py-2 bg-yellow-600 hover:bg-yellow-700 text-white font-bold rounded-xl text-xs transition-all flex items-center gap-1.5">
                        <i class="fas fa-file-zipper"></i> Extract &amp; Audit
                    </button>
                </form>
            </div>

            <!-- Pre-Scan Audit Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total in Spreadsheet</div>
                    <div class="text-2xl font-extrabold text-white"><?php echo $scanResult['total_products']; ?></div>
                    <div class="text-[10px] text-slate-500 mt-0.5 truncate"><?php echo htmlspecialchars($scanResult['spreadsheet_file']); ?></div>
                </div>

                <div class="bg-emerald-950/30 border border-emerald-500/30 p-4 rounded-2xl cursor-pointer hover:border-emerald-500 transition-all" onclick="filterTable('ready')">
                    <div class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider mb-1 flex items-center justify-between">
                        <span>Ready to Upload (New)</span>
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="text-2xl font-extrabold text-emerald-400"><?php echo $scanResult['ready_count']; ?></div>
                    <div class="text-[10px] text-emerald-300/70 mt-0.5">Will be newly created</div>
                </div>

                <div class="bg-amber-950/30 border border-amber-500/30 p-4 rounded-2xl cursor-pointer hover:border-amber-500 transition-all" onclick="filterTable('exists')">
                    <div class="text-[10px] font-bold text-amber-400 uppercase tracking-wider mb-1 flex items-center justify-between">
                        <span>Already in DB (Skipped)</span>
                        <i class="fas fa-forward"></i>
                    </div>
                    <div class="text-2xl font-extrabold text-amber-400"><?php echo $scanResult['exist_count']; ?></div>
                    <div class="text-[10px] text-amber-300/70 mt-0.5">Will not be touched</div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">SKU Folders on Server</div>
                    <div class="text-2xl font-extrabold text-indigo-400"><?php echo $scanResult['total_folders']; ?></div>
                    <div class="text-[10px] text-indigo-300/70 mt-0.5">Matched from archive/</div>
                </div>
            </div>

            <!-- Import Execution Section -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5">
                <div class="flex items-center justify-between flex-wrap gap-4 pb-4 border-b border-slate-800">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fas fa-bolt text-yellow-400"></i> Import Execution Controls
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Choose to import only new products or process the full batch.</p>
                    </div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <button id="download_report_btn" class="hidden px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold border border-slate-700 transition-all flex items-center gap-1.5">
                            <i class="fas fa-download text-emerald-400"></i> Download CSV Report
                        </button>
                        <button id="start_new_only_btn" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30 transition-all flex items-center gap-2">
                            <i class="fas fa-rocket"></i> Import ONLY New (<?php echo $scanResult['ready_count']; ?> Items)
                        </button>
                        <button id="start_all_btn" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                            <i class="fas fa-play"></i> Process All (<?php echo $scanResult['total_products']; ?> Items)
                        </button>
                    </div>
                </div>

                <!-- Filter Tabs & Stats -->
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-1.5 bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs">
                        <button type="button" onclick="filterTable('all')" id="tab_all" class="px-3.5 py-1.5 rounded-lg font-bold bg-indigo-600 text-white transition-all">All (<?php echo $scanResult['total_products']; ?>)</button>
                        <button type="button" onclick="filterTable('ready')" id="tab_ready" class="px-3.5 py-1.5 rounded-lg font-bold text-slate-400 hover:text-white transition-all">Ready / New (<?php echo $scanResult['ready_count']; ?>)</button>
                        <button type="button" onclick="filterTable('exists')" id="tab_exists" class="px-3.5 py-1.5 rounded-lg font-bold text-slate-400 hover:text-white transition-all">Already in DB (<?php echo $scanResult['exist_count']; ?>)</button>
                    </div>

                    <div class="flex items-center gap-4 text-xs font-mono">
                        <div>Created: <b id="cnt_created" class="text-emerald-400">0</b></div>
                        <div>Skipped: <b id="cnt_skipped" class="text-amber-400">0</b></div>
                        <div>Errors: <b id="cnt_error" class="text-rose-400">0</b></div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="space-y-1.5">
                    <div class="flex justify-between text-xs">
                        <span id="progress_status" class="text-slate-400 font-mono">Pre-scan completed. Select an action above to begin.</span>
                        <span id="progress_pct" class="font-bold text-indigo-400">0%</span>
                    </div>
                    <div class="w-full bg-slate-800 rounded-full h-3 overflow-hidden border border-slate-700">
                        <div id="progress_bar" class="bg-indigo-600 h-full w-0 transition-all duration-150"></div>
                    </div>
                </div>

                <!-- Pre-Scan Table -->
                <div class="border border-slate-800 rounded-xl overflow-hidden bg-slate-950">
                    <div class="max-h-[500px] overflow-y-auto custom-scrollbar">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-900/90 text-slate-400 text-[10px] font-bold uppercase tracking-wider sticky top-0 border-b border-slate-800 z-10">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">SKU</th>
                                    <th class="px-4 py-3">Product Name</th>
                                    <th class="px-4 py-3">Photos Found</th>
                                    <th class="px-4 py-3 text-right">Status / Pre-Audit</th>
                                </tr>
                            </thead>
                            <tbody id="log_tbody" class="divide-y divide-slate-800/60 font-mono text-[11px]">
                                <?php foreach ($scanResult['products'] as $idx => $p): ?>
                                    <tr id="row-<?php echo $idx; ?>" class="product-row hover:bg-slate-900/40 transition-colors" data-prestatus="<?php echo $p['pre_status']; ?>" data-sku="<?php echo htmlspecialchars($p['sku']); ?>">
                                        <td class="px-4 py-2.5 text-slate-500"><?php echo $idx + 1; ?></td>
                                        <td class="px-4 py-2.5 font-bold text-indigo-300"><?php echo htmlspecialchars($p['sku']); ?></td>
                                        <td class="px-4 py-2.5 text-slate-300 truncate max-w-[220px]" title="<?php echo htmlspecialchars($p['name'] ?? ''); ?>"><?php echo htmlspecialchars($p['name'] ?? ''); ?></td>
                                        <td class="px-4 py-2.5 text-slate-400">
                                            <?php if ($p['images_count'] > 0): ?>
                                                <span class="text-emerald-400 font-bold"><i class="fas fa-images mr-1"></i><?php echo $p['images_count']; ?></span>
                                            <?php else: ?>
                                                <span class="text-slate-600">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-2.5 text-right status-col">
                                            <?php if ($p['pre_status'] === 'exists'): ?>
                                                <span class="text-amber-400/90 font-bold"><i class="fas fa-forward mr-1"></i><?php echo $p['pre_message']; ?></span>
                                            <?php else: ?>
                                                <span class="text-emerald-400/90 font-bold"><i class="fas fa-check mr-1"></i><?php echo $p['pre_message']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                const allProductsData = <?php echo json_encode($scanResult['products']); ?>;
                const startNewOnlyBtn = document.getElementById('start_new_only_btn');
                const startAllBtn = document.getElementById('start_all_btn');
                const downloadReportBtn = document.getElementById('download_report_btn');
                const progressBar = document.getElementById('progress_bar');
                const progressPct = document.getElementById('progress_pct');
                const progressStatus = document.getElementById('progress_status');
                
                const cntCreated = document.getElementById('cnt_created');
                const cntSkipped = document.getElementById('cnt_skipped');
                const cntError = document.getElementById('cnt_error');

                let fullResults = [['#', 'SKU', 'Name', 'Status', 'Images', 'Reason/Message']];

                function filterTable(type) {
                    ['all', 'ready', 'exists'].forEach(t => {
                        const tab = document.getElementById('tab_' + t);
                        if (tab) {
                            if (t === type) {
                                tab.className = "px-3.5 py-1.5 rounded-lg font-bold bg-indigo-600 text-white transition-all";
                            } else {
                                tab.className = "px-3.5 py-1.5 rounded-lg font-bold text-slate-400 hover:text-white transition-all";
                            }
                        }
                    });

                    const rows = document.querySelectorAll('.product-row');
                    rows.forEach(r => {
                        if (type === 'all' || r.getAttribute('data-prestatus') === type) {
                            r.classList.remove('hidden');
                        } else {
                            r.classList.add('hidden');
                        }
                    });
                }

                async function runImport(itemsToProcess) {
                    startNewOnlyBtn.disabled = true;
                    startAllBtn.disabled = true;
                    startNewOnlyBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    startAllBtn.classList.add('opacity-50', 'cursor-not-allowed');

                    let created = 0, skipped = 0, errors = 0;

                    for (let i = 0; i < itemsToProcess.length; i++) {
                        const item = itemsToProcess[i];
                        const rowEl = document.querySelector(`.product-row[data-sku="${item.sku}"]`);
                        const statusCol = rowEl ? rowEl.querySelector('.status-col') : null;

                        if (statusCol) {
                            statusCol.innerHTML = `<span class="text-indigo-400 animate-pulse"><i class="fas fa-spinner fa-spin mr-1"></i>Saving</span>`;
                        }
                        if (rowEl) rowEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                        let success = false;
                        let retries = 0;

                        while (!success && retries < 2) {
                            try {
                                const res = await fetch('import_archive.php?action=process_row', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify(item)
                                });
                                const data = await res.json();
                                success = true;

                                if (data.status === 'success') {
                                    created++;
                                    cntCreated.textContent = created;
                                    if (statusCol) {
                                        statusCol.innerHTML = `<span class="text-emerald-400 font-bold"><i class="fas fa-check-circle mr-1"></i>Created (${data.images_count || 0} imgs)</span>`;
                                    }
                                    fullResults.push([i+1, item.sku, item.name || '', 'Created', data.images_count || 0, data.message || '']);
                                } else if (data.status === 'skipped') {
                                    skipped++;
                                    cntSkipped.textContent = skipped;
                                    if (statusCol) {
                                        statusCol.innerHTML = `<div><span class="text-amber-400 font-bold"><i class="fas fa-forward mr-1"></i>Skipped</span><div class="text-[9px] text-amber-500/80 mt-0.5">Already in DB</div></div>`;
                                    }
                                    fullResults.push([i+1, item.sku, item.name || '', 'Skipped', 0, data.message || 'Already exists']);
                                } else {
                                    errors++;
                                    cntError.textContent = errors;
                                    if (statusCol) {
                                        statusCol.innerHTML = `<div><span class="text-rose-400 font-bold"><i class="fas fa-times-circle mr-1"></i>Failed</span><div class="text-[9px] text-rose-400 mt-0.5 max-w-[280px] truncate" title="${data.message}">${data.message}</div></div>`;
                                    }
                                    fullResults.push([i+1, item.sku, item.name || '', 'Error', 0, data.message || 'Unknown error']);
                                }
                            } catch (err) {
                                retries++;
                                if (retries >= 2) {
                                    errors++;
                                    cntError.textContent = errors;
                                    if (statusCol) {
                                        statusCol.innerHTML = `<div><span class="text-rose-400 font-bold"><i class="fas fa-times-circle mr-1"></i>Connection Error</span><div class="text-[9px] text-rose-400 mt-0.5">${err.message}</div></div>`;
                                    }
                                    fullResults.push([i+1, item.sku, item.name || '', 'Error', 0, err.message]);
                                } else {
                                    await new Promise(r => setTimeout(r, 500));
                                }
                            }
                        }

                        // Short delay to avoid pegging resources
                        await new Promise(r => setTimeout(r, 50));

                        const pct = Math.round(((i + 1) / itemsToProcess.length) * 100);
                        progressBar.style.width = pct + '%';
                        progressPct.textContent = pct + '%';
                        progressStatus.textContent = `Processed ${i + 1} of ${itemsToProcess.length} (${item.sku})...`;
                    }

                    progressStatus.textContent = `Completed! Created: ${created}, Skipped: ${skipped}, Errors: ${errors}`;
                    startNewOnlyBtn.textContent = 'Completed';
                    startNewOnlyBtn.className = 'px-6 py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-bold cursor-default';
                    downloadReportBtn.classList.remove('hidden');
                }

                startNewOnlyBtn.addEventListener('click', function() {
                    const newItems = allProductsData.filter(p => p.pre_status === 'ready');
                    if (newItems.length === 0) {
                        alert('All products already exist in database!');
                        return;
                    }
                    runImport(newItems);
                });

                startAllBtn.addEventListener('click', function() {
                    runImport(allProductsData);
                });

                downloadReportBtn.addEventListener('click', function() {
                    const csvContent = fullResults.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = `Archive_Import_Report_${new Date().toISOString().slice(0,10)}.csv`;
                    link.click();
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
