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

    $defaultCat = (stripos(basename($archiveDir), 'ittar') !== false || stripos(basename($archiveDir), 'attar') !== false) ? 'Kalamkari Collection' : 'Jewellery';

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

        // Check if hierarchical with >
        $parts = explode('>', $branch);
        $parentId = null;
        $currentId = null;

        foreach ($parts as $part) {
            $catName = trim($part);
            if (empty($catName)) continue;

            $slug = generate_slug($catName);
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE (slug = ? OR LOWER(name) = ?) AND parent_id " . ($parentId ? "= ?" : "IS NULL") . " AND deleted_at IS NULL LIMIT 1");
            if ($parentId) {
                $stmt->execute([$slug, strtolower($catName), $parentId]);
            } else {
                $stmt->execute([$slug, strtolower($catName)]);
            }
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $currentId = (int)$existing['id'];
            } else {
                // Fallback check by name or slug regardless of parent
                $stmtFallback = $pdo->prepare("SELECT id FROM categories WHERE (slug = ? OR LOWER(name) = ?) AND deleted_at IS NULL LIMIT 1");
                $stmtFallback->execute([$slug, strtolower($catName)]);
                $fallback = $stmtFallback->fetch(PDO::FETCH_ASSOC);

                if ($fallback && empty($parentId)) {
                    $currentId = (int)$fallback['id'];
                } else {
                    $checkSlug = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
                    $checkSlug->execute([$slug]);
                    if ($checkSlug->fetchColumn() > 0) {
                        $slug .= '-' . rand(100, 999);
                    }

                    $insertStmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
                    $insertStmt->execute([$catName, $slug, $parentId]);
                    $currentId = (int)$pdo->lastInsertId();
                }
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

$page_title = "Bulk Product & SKU Archive Importer";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap" style="max-width: 1400px; margin: 0 auto; padding-bottom: 60px;">
    <!-- Wrap Header -->
    <div class="wrap-header" style="margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 42px; height: 42px; border-radius: 8px; background: #e0e7ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                <i class="fa-solid fa-folder-tree"></i>
            </div>
            <div>
                <h1 style="margin: 0; font-size: 20px; font-weight: 600; color: #09090b; display: flex; align-items: center; gap: 8px;">
                    Bulk Product &amp; SKU Archive Importer
                    <span style="font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 12px; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; text-transform: uppercase;">Direct Engine</span>
                </h1>
                <p style="margin: 3px 0 0; font-size: 12.5px; color: #71717a;">Pre-audited direct import matching spreadsheet records with SKU image folders on the server.</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <a href="products.php" class="shadcn-btn shadcn-btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Back to Products
            </a>
        </div>
    </div>

    <?php if ($uploadMessage): ?>
        <div class="notice notice-success">
            <i class="fa-solid fa-circle-check"></i>
            <p><?php echo $uploadMessage; ?></p>
        </div>
    <?php endif; ?>

    <?php if ($uploadError): ?>
        <div class="notice notice-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <p><?php echo $uploadError; ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($scanResult['error'])): ?>
        <!-- Setup / Missing Spreadsheet Card -->
        <div class="shadcn-card" style="margin-bottom: 24px;">
            <div class="shadcn-card-header" style="background: #fffbeb; border-color: #fde68a;">
                <div style="display: flex; align-items: center; gap: 8px; color: #b45309; font-weight: 600; font-size: 13px;">
                    <i class="fa-solid fa-file-circle-exclamation"></i>
                    <span>Spreadsheet Needed in <code style="background: #ffffff; padding: 2px 6px; border-radius: 4px; border: 1px solid #fde68a; color: #78350f; font-family: monospace;"><?php echo htmlspecialchars(basename($archiveDir)); ?>/</code></span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <a href="import_archive.php?action=download_template&format=xlsx" class="shadcn-btn shadcn-btn-outline" style="font-size: 12px; height: 28px;">
                        <i class="fa-solid fa-file-excel" style="color: #10b981;"></i> Download Sample Excel (.xlsx)
                    </a>
                    <a href="import_archive.php?action=download_template&format=csv" class="shadcn-btn shadcn-btn-outline" style="font-size: 12px; height: 28px;">
                        <i class="fa-solid fa-file-csv" style="color: #6366f1;"></i> Download CSV
                    </a>
                </div>
            </div>
            <div class="shadcn-card-padded" style="display: flex; flex-direction: column; gap: 18px;">
                <?php if ($archiveDir && is_dir($archiveDir) && !empty($scanResult['total_folders'])): ?>
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <div style="font-size: 13px; font-weight: 600; color: #15803d; display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Found <?php echo $scanResult['total_folders']; ?> SKU folder(s) in <?php echo htmlspecialchars(basename($archiveDir)); ?>/
                            </div>
                            <p style="font-size: 12px; color: #166534; margin: 4px 0 0;">You can instantly auto-generate a pre-filled Excel spreadsheet with all your folder SKUs with 1 click!</p>
                        </div>
                        <a href="import_archive.php?action=generate_from_folders&custom_path=<?php echo urlencode($archiveDir); ?>" class="shadcn-btn shadcn-btn-primary" style="height: 36px; padding: 0 16px;">
                            <i class="fa-solid fa-bolt"></i> Auto-Generate Excel for <?php echo $scanResult['total_folders']; ?> SKUs
                        </a>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">
                    <!-- Option 1: Upload Excel -->
                    <div style="border: 1px solid #e4e4e7; border-radius: 8px; padding: 16px; background: #fafafa;">
                        <h4 style="margin: 0 0 10px; font-size: 13px; font-weight: 600; color: #09090b; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-file-excel" style="color: #71717a;"></i> Option 1: Upload Existing Spreadsheet (.xlsx / .csv)
                        </h4>
                        <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($requestUri); ?>" style="display: flex; flex-direction: column; gap: 10px;">
                            <input type="hidden" name="custom_path" value="<?php echo htmlspecialchars($archiveDir); ?>">
                            <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required class="form-control" style="padding: 4px 8px; font-size: 12px; height: 34px;">
                            <button type="submit" class="shadcn-btn shadcn-btn-primary" style="height: 34px;">
                                <i class="fa-solid fa-upload"></i> Upload &amp; Start Audit
                            </button>
                        </form>
                    </div>

                    <!-- Option 2: Upload ZIP -->
                    <div style="border: 1px solid #e4e4e7; border-radius: 8px; padding: 16px; background: #fafafa;">
                        <h4 style="margin: 0 0 10px; font-size: 13px; font-weight: 600; color: #09090b; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-file-zipper" style="color: #71717a;"></i> Option 2: Upload Full ZIP (Spreadsheet + SKU Folders)
                        </h4>
                        <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($requestUri); ?>" style="display: flex; flex-direction: column; gap: 10px;">
                            <input type="hidden" name="custom_path" value="<?php echo htmlspecialchars($archiveDir); ?>">
                            <input type="file" name="zip_file" accept=".zip" required class="form-control" style="padding: 4px 8px; font-size: 12px; height: 34px;">
                            <button type="submit" class="shadcn-btn shadcn-btn-primary" style="height: 34px;">
                                <i class="fa-solid fa-file-zipper"></i> Upload &amp; Extract ZIP
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Excel Format Guide Table -->
                <div style="border: 1px solid #e4e4e7; border-radius: 8px; overflow: hidden;">
                    <div style="padding: 10px 14px; background: #f4f4f5; font-size: 12px; font-weight: 600; color: #09090b; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-table-list" style="color: #6366f1;"></i> Excel Column Format Reference
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="shadcn-table" style="font-size: 12px;">
                            <thead>
                                <tr>
                                    <th>Column Header</th>
                                    <th>Required?</th>
                                    <th>Example Value</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code style="font-weight: 600; color: #4338ca;">sku</code></td>
                                    <td><span style="color: #15803d; font-weight: 600;">Yes</span></td>
                                    <td><span style="color: #71717a;">IT101</span></td>
                                    <td>Must match the SKU folder name in archive/</td>
                                </tr>
                                <tr>
                                    <td><code style="font-weight: 600; color: #4338ca;">name</code></td>
                                    <td><span style="color: #15803d; font-weight: 600;">Yes</span></td>
                                    <td><span style="color: #71717a;">Royal Oudh Ittar 10ml</span></td>
                                    <td>Product title displayed on store</td>
                                </tr>
                                <tr>
                                    <td><code style="font-weight: 600; color: #4338ca;">categories</code></td>
                                    <td><span style="color: #71717a;">Optional</span></td>
                                    <td><span style="color: #71717a;">Ittar, Perfumes &gt; Oudh</span></td>
                                    <td>Category or subcategory names separated by comma</td>
                                </tr>
                                <tr>
                                    <td><code style="font-weight: 600; color: #4338ca;">price</code></td>
                                    <td><span style="color: #15803d; font-weight: 600;">Yes</span></td>
                                    <td><span style="color: #71717a;">1499</span></td>
                                    <td>Regular / MRP price</td>
                                </tr>
                                <tr>
                                    <td><code style="font-weight: 600; color: #4338ca;">sale_price</code></td>
                                    <td><span style="color: #71717a;">Optional</span></td>
                                    <td><span style="color: #71717a;">1199</span></td>
                                    <td>Discounted / offer price</td>
                                </tr>
                                <tr>
                                    <td><code style="font-weight: 600; color: #4338ca;">stock_qty</code></td>
                                    <td><span style="color: #71717a;">Optional</span></td>
                                    <td><span style="color: #71717a;">25</span></td>
                                    <td>Available inventory quantity (default: 10)</td>
                                </tr>
                                <tr>
                                    <td><code style="font-weight: 600; color: #4338ca;">description</code></td>
                                    <td><span style="color: #71717a;">Optional</span></td>
                                    <td><span style="color: #71717a;">Pure concentrated perfume oil...</span></td>
                                    <td>Full product details / craftsmanship notes</td>
                                </tr>
                                <tr>
                                    <td><code style="font-weight: 600; color: #4338ca;">status</code></td>
                                    <td><span style="color: #71717a;">Optional</span></td>
                                    <td><span style="color: #71717a;">published</span></td>
                                    <td>"published" or "draft"</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Server Path Switcher -->
                <div style="border-top: 1px solid #e4e4e7; padding-top: 14px;">
                    <span style="font-size: 12px; color: #71717a; font-weight: 500; display: block; margin-bottom: 6px;">Or specify a folder path on the server:</span>
                    <form method="GET" style="display: flex; gap: 8px;">
                        <input type="text" name="custom_path" value="<?php echo htmlspecialchars($_GET['custom_path'] ?? ''); ?>" placeholder="/public_html/ittar, ittar, or custom folder path" class="form-control" style="flex: 1; font-family: monospace;">
                        <button type="submit" class="shadcn-btn shadcn-btn-primary">Scan Folder</button>
                    </form>
                </div>
            </div>
        </div>

    <?php else: ?>

        <!-- Active Folder Bar -->
        <div class="shadcn-card" style="margin-bottom: 18px;">
            <div class="shadcn-card-padded" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 12px 16px;">
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                    <span style="font-weight: 600; color: #4338ca; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-folder-open"></i> Active Folder:
                    </span>
                    <code style="background: #f4f4f5; padding: 3px 8px; border-radius: 6px; border: 1px solid #e4e4e7; color: #09090b; font-family: monospace; font-size: 12px;">
                        <?php echo htmlspecialchars($scanResult['archive_dir']); ?>
                    </code>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button type="button" onclick="const d = document.getElementById('excel_upload_drawer'); d.style.display = (d.style.display === 'none' ? 'block' : 'none');" class="shadcn-btn shadcn-btn-outline" style="font-size: 12px; height: 30px;">
                        <i class="fa-solid fa-file-excel" style="color: #10b981;"></i> Replace Excel
                    </button>
                    <button type="button" onclick="const d = document.getElementById('zip_upload_drawer'); d.style.display = (d.style.display === 'none' ? 'block' : 'none');" class="shadcn-btn shadcn-btn-outline" style="font-size: 12px; height: 30px;">
                        <i class="fa-solid fa-file-zipper" style="color: #d97706;"></i> Upload ZIP
                    </button>
                    <form method="GET" style="display: flex; align-items: center; gap: 6px;">
                        <input type="text" name="custom_path" placeholder="Switch folder (e.g. ittar)" class="form-control" style="width: 190px; height: 30px; font-size: 12px;">
                        <button type="submit" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px;">Switch</button>
                    </form>
                </div>
            </div>

            <!-- Drawer for Uploading Excel File -->
            <div id="excel_upload_drawer" style="display: none; border-top: 1px solid #e4e4e7; background: #fafafa; padding: 14px 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <h4 style="margin: 0; font-size: 13px; font-weight: 600; color: #059669; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-file-excel"></i> Upload / Replace Spreadsheet in <?php echo htmlspecialchars(basename($scanResult['archive_dir'])); ?>/
                    </h4>
                    <button type="button" onclick="document.getElementById('excel_upload_drawer').style.display='none'" class="shadcn-btn shadcn-btn-ghost" style="height: 24px; width: 24px; padding: 0;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <p style="font-size: 11.5px; color: #71717a; margin: 0 0 10px;">Uploading a new Excel (.xlsx, .xls) or .csv file will save it directly into this folder and refresh the product audit table.</p>
                <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($requestUri); ?>" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="custom_path" value="<?php echo htmlspecialchars($scanResult['archive_dir']); ?>">
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required class="form-control" style="flex: 1; height: 34px; padding: 3px 8px; font-size: 12px;">
                    <button type="submit" class="shadcn-btn shadcn-btn-primary" style="background: #059669 !important; border-color: #059669 !important; height: 34px;">
                        <i class="fa-solid fa-upload"></i> Upload &amp; Refresh
                    </button>
                </form>
            </div>

            <!-- Drawer for Uploading ZIP Archive -->
            <div id="zip_upload_drawer" style="display: none; border-top: 1px solid #e4e4e7; background: #fafafa; padding: 14px 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <h4 style="margin: 0; font-size: 13px; font-weight: 600; color: #d97706; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-file-zipper"></i> Extract ZIP Archive into <?php echo htmlspecialchars(basename($scanResult['archive_dir'])); ?>/
                    </h4>
                    <button type="button" onclick="document.getElementById('zip_upload_drawer').style.display='none'" class="shadcn-btn shadcn-btn-ghost" style="height: 24px; width: 24px; padding: 0;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <p style="font-size: 11.5px; color: #71717a; margin: 0 0 10px;">Upload a .zip file containing your product spreadsheet and SKU image folders. It will be extracted directly into the active archive folder.</p>
                <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($requestUri); ?>" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="custom_path" value="<?php echo htmlspecialchars($scanResult['archive_dir']); ?>">
                    <input type="file" name="zip_file" accept=".zip" required class="form-control" style="flex: 1; height: 34px; padding: 3px 8px; font-size: 12px;">
                    <button type="submit" class="shadcn-btn" style="background: #d97706 !important; border-color: #d97706 !important; color: #ffffff !important; height: 34px;">
                        <i class="fa-solid fa-file-zipper"></i> Extract &amp; Audit
                    </button>
                </form>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="shadcn-stat-grid" style="margin-bottom: 20px;">
            <div class="shadcn-stat-card">
                <div class="shadcn-stat-top">
                    <span class="shadcn-stat-title">Total in Spreadsheet</span>
                    <span class="shadcn-stat-icon-wrap" style="background: #f4f4f5; color: #52525b;"><i class="fa-solid fa-file-lines"></i></span>
                </div>
                <div class="shadcn-stat-value"><?php echo $scanResult['total_products']; ?></div>
                <div class="shadcn-stat-note" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($scanResult['spreadsheet_file']); ?>"><?php echo htmlspecialchars($scanResult['spreadsheet_file']); ?></div>
            </div>

            <div class="shadcn-stat-card" style="cursor: pointer;" onclick="filterTable('ready')">
                <div class="shadcn-stat-top">
                    <span class="shadcn-stat-title" style="color: #059669;">Ready to Upload (New)</span>
                    <span class="shadcn-stat-icon-wrap" style="background: #ecfdf5; color: #059669;"><i class="fa-solid fa-circle-plus"></i></span>
                </div>
                <div class="shadcn-stat-value" style="color: #059669;"><?php echo $scanResult['ready_count']; ?></div>
                <div class="shadcn-stat-note">Will be newly created</div>
            </div>

            <div class="shadcn-stat-card" style="cursor: pointer;" onclick="filterTable('exists')">
                <div class="shadcn-stat-top">
                    <span class="shadcn-stat-title" style="color: #d97706;">Already in DB (Skipped)</span>
                    <span class="shadcn-stat-icon-wrap" style="background: #fffbeb; color: #d97706;"><i class="fa-solid fa-forward"></i></span>
                </div>
                <div class="shadcn-stat-value" style="color: #d97706;"><?php echo $scanResult['exist_count']; ?></div>
                <div class="shadcn-stat-note">Will not be touched</div>
            </div>

            <div class="shadcn-stat-card">
                <div class="shadcn-stat-top">
                    <span class="shadcn-stat-title" style="color: #4f46e5;">SKU Folders on Server</span>
                    <span class="shadcn-stat-icon-wrap" style="background: #eef2ff; color: #4f46e5;"><i class="fa-solid fa-folder-tree"></i></span>
                </div>
                <div class="shadcn-stat-value" style="color: #4f46e5;"><?php echo $scanResult['total_folders']; ?></div>
                <div class="shadcn-stat-note">Matched in archive folder</div>
            </div>
        </div>

        <!-- Import Execution Controls Card -->
        <div class="shadcn-card" style="margin-bottom: 24px;">
            <div class="shadcn-card-header" style="flex-wrap: wrap; gap: 12px; padding: 14px 16px;">
                <div>
                    <h3 class="shadcn-card-title">
                        <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i> Import Execution Controls
                    </h3>
                    <p style="font-size: 12px; color: #71717a; margin: 3px 0 0;">Choose to import only new products or process the full batch sequentially.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button id="download_report_btn" style="display: none;" class="shadcn-btn shadcn-btn-outline">
                        <i class="fa-solid fa-download" style="color: #10b981;"></i> Download CSV Report
                    </button>
                    <button id="start_new_only_btn" class="shadcn-btn shadcn-btn-primary" style="background: #059669 !important; border-color: #059669 !important; height: 34px;">
                        <i class="fa-solid fa-rocket"></i> Import ONLY New (<?php echo $scanResult['ready_count']; ?> Items)
                    </button>
                    <button id="start_all_btn" class="shadcn-btn shadcn-btn-primary" style="background: #4f46e5 !important; border-color: #4f46e5 !important; height: 34px;">
                        <i class="fa-solid fa-play"></i> Process All (<?php echo $scanResult['total_products']; ?> Items)
                    </button>
                </div>
            </div>

            <div class="shadcn-card-padded" style="padding: 16px;">
                <!-- Filter Tabs & Stats -->
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
                    <div style="display: flex; gap: 6px;">
                        <button type="button" onclick="filterTable('all')" id="tab_all" class="shadcn-btn shadcn-btn-primary" style="height: 30px; font-size: 12px;">All (<?php echo $scanResult['total_products']; ?>)</button>
                        <button type="button" onclick="filterTable('ready')" id="tab_ready" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px;">Ready / New (<?php echo $scanResult['ready_count']; ?>)</button>
                        <button type="button" onclick="filterTable('exists')" id="tab_exists" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px;">Already in DB (<?php echo $scanResult['exist_count']; ?>)</button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 16px; font-size: 12px; font-family: monospace;">
                        <div>Created: <b id="cnt_created" style="color: #059669;">0</b></div>
                        <div>Skipped: <b id="cnt_skipped" style="color: #d97706;">0</b></div>
                        <div>Errors: <b id="cnt_error" style="color: #dc2626;">0</b></div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div style="margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 6px;">
                        <span id="progress_status" style="color: #71717a; font-family: monospace;">Pre-scan completed. Select an action above to begin.</span>
                        <span id="progress_pct" style="font-weight: 600; color: #4f46e5;">0%</span>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e4e4e7; border-radius: 9999px; overflow: hidden;">
                        <div id="progress_bar" style="height: 100%; width: 0%; background: #4f46e5; transition: width 0.15s ease; border-radius: 9999px;"></div>
                    </div>
                </div>

                <!-- Pre-Scan Table -->
                <div style="border: 1px solid #e4e4e7; border-radius: 6px; overflow: hidden;">
                    <div style="max-height: 480px; overflow-y: auto;">
                        <table class="shadcn-table" style="margin: 0;">
                            <thead style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th style="width: 45px;">#</th>
                                    <th style="width: 130px;">SKU</th>
                                    <th>Product Name</th>
                                    <th style="width: 130px;">Photos Found</th>
                                    <th style="width: 220px; text-align: right;">Status / Pre-Audit</th>
                                </tr>
                            </thead>
                            <tbody id="log_tbody">
                                <?php foreach ($scanResult['products'] as $idx => $p): ?>
                                    <tr id="row-<?php echo $idx; ?>" class="product-row" data-prestatus="<?php echo $p['pre_status']; ?>" data-sku="<?php echo htmlspecialchars($p['sku']); ?>">
                                        <td style="color: #a1a1aa;"><?php echo $idx + 1; ?></td>
                                        <td><strong style="font-family: monospace; color: #09090b;"><?php echo htmlspecialchars($p['sku']); ?></strong></td>
                                        <td>
                                            <div style="max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($p['name'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($p['name'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($p['images_count'] > 0): ?>
                                                <span style="display: inline-flex; align-items: center; gap: 4px; color: #059669; font-weight: 600; font-size: 12px;">
                                                    <i class="fa-solid fa-images"></i> <?php echo $p['images_count']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #a1a1aa;">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;" class="status-col">
                                            <?php if ($p['pre_status'] === 'exists'): ?>
                                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-size: 11px; font-weight: 600;">
                                                    <i class="fa-solid fa-forward"></i> <?php echo $p['pre_message']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 11px; font-weight: 600;">
                                                    <i class="fa-solid fa-check"></i> <?php echo $p['pre_message']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
                            tab.className = "shadcn-btn shadcn-btn-primary";
                        } else {
                            tab.className = "shadcn-btn shadcn-btn-outline";
                        }
                    }
                });

                const rows = document.querySelectorAll('.product-row');
                rows.forEach(r => {
                    if (type === 'all' || r.getAttribute('data-prestatus') === type) {
                        r.style.display = '';
                    } else {
                        r.style.display = 'none';
                    }
                });
            }

            async function runImport(itemsToProcess) {
                startNewOnlyBtn.disabled = true;
                startAllBtn.disabled = true;
                startNewOnlyBtn.style.opacity = '0.5';
                startNewOnlyBtn.style.cursor = 'not-allowed';
                startAllBtn.style.opacity = '0.5';
                startAllBtn.style.cursor = 'not-allowed';

                let created = 0, skipped = 0, errors = 0;

                for (let i = 0; i < itemsToProcess.length; i++) {
                    const item = itemsToProcess[i];
                    const rowEl = document.querySelector(`.product-row[data-sku="${item.sku}"]`);
                    const statusCol = rowEl ? rowEl.querySelector('.status-col') : null;

                    if (statusCol) {
                        statusCol.innerHTML = `<span style="color: #4f46e5; display: inline-flex; align-items: center; gap: 4px; font-weight: 600; font-size: 11px;"><i class="fa-solid fa-spinner fa-spin"></i> Saving...</span>`;
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
                                    statusCol.innerHTML = `<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 11px; font-weight: 600;"><i class="fa-solid fa-circle-check"></i> Created (${data.images_count || 0} imgs)</span>`;
                                }
                                fullResults.push([i+1, item.sku, item.name || '', 'Created', data.images_count || 0, data.message || '']);
                            } else if (data.status === 'skipped') {
                                skipped++;
                                cntSkipped.textContent = skipped;
                                if (statusCol) {
                                    statusCol.innerHTML = `<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-size: 11px; font-weight: 600;"><i class="fa-solid fa-forward"></i> Skipped (In DB)</span>`;
                                }
                                fullResults.push([i+1, item.sku, item.name || '', 'Skipped', 0, data.message || 'Already exists']);
                            } else {
                                errors++;
                                cntError.textContent = errors;
                                if (statusCol) {
                                    statusCol.innerHTML = `<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; font-size: 11px; font-weight: 600;" title="${data.message}"><i class="fa-solid fa-circle-xmark"></i> Failed</span>`;
                                }
                                fullResults.push([i+1, item.sku, item.name || '', 'Error', 0, data.message || 'Unknown error']);
                            }
                        } catch (err) {
                            retries++;
                            if (retries >= 2) {
                                errors++;
                                cntError.textContent = errors;
                                if (statusCol) {
                                    statusCol.innerHTML = `<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; font-size: 11px; font-weight: 600;"><i class="fa-solid fa-circle-xmark"></i> Network Error</span>`;
                                }
                                fullResults.push([i+1, item.sku, item.name || '', 'Error', 0, err.message]);
                            } else {
                                await new Promise(r => setTimeout(r, 500));
                            }
                        }
                    }

                    await new Promise(r => setTimeout(r, 40));

                    const pct = Math.round(((i + 1) / itemsToProcess.length) * 100);
                    progressBar.style.width = pct + '%';
                    progressPct.textContent = pct + '%';
                    progressStatus.textContent = `Processed ${i + 1} of ${itemsToProcess.length} (${item.sku})...`;
                }

                progressStatus.textContent = `Completed! Created: ${created}, Skipped: ${skipped}, Errors: ${errors}`;
                startNewOnlyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Completed';
                startNewOnlyBtn.style.opacity = '1';
                startNewOnlyBtn.style.cursor = 'default';
                downloadReportBtn.style.display = 'inline-flex';
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
