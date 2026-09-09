<?php
// admin/product-import.php
$page_title = "Bulk Import Products";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Ensure autoload is loaded for PhpSpreadsheet
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Ensure user has permission
if (!current_user_can('manage_products')) {
    die("You do not have permission to import products.");
}

$message = '';
$message_type = 'success';

// Handle Action: Download Sample/Format Template (CSV / Excel)
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $format = strtolower($_GET['format'] ?? 'csv');
    
    $headers = [
        'SKU',
        'Name',
        'Categories',
        'Regular price',
        'Sale price',
        'Stock',
        'In stock?',
        'Published',
        'Short description',
        'Description',
        'Images'
    ];
    
    $sampleData = [
        $headers,
        [
            'YN-1001',
            'Embroidered Silk Sherwani',
            'Men > Ethnic Wear > Sherwanis',
            '15999',
            '12999',
            '10',
            '1',
            '1',
            'Handcrafted royal silk sherwani with intricate zardozi embroidery.',
            'Detailed description of the royal silk sherwani with matching stole and churidar. Perfect for weddings and festive occasions.',
            'https://yosshitaneha.com/uploads/sample1.jpg, https://yosshitaneha.com/uploads/sample2.jpg'
        ],
        [
            'YN-1002',
            'Floral Organza Lehenga',
            'Women > Bridal > Lehengas',
            '24999',
            '19999',
            '5',
            '1',
            '1',
            'Designer floral printed organza lehenga with delicate handwork.',
            'Complete bridal lehenga set with flared skirt, embroidered blouse, and scalloped dupatta in premium organza silk.',
            'https://yosshitaneha.com/uploads/sample3.jpg'
        ],
        [
            'YN-1003',
            'Royal Velvet Shawl',
            'Accessories > Shawls',
            '4999',
            '3999',
            '15',
            '1',
            '1',
            'Luxurious Kashmiri velvet shawl with golden tilla border.',
            'Exquisite warm velvet shawl crafted with traditional Kashmiri tilla work. Ideal for evening wear and gifting.',
            'https://yosshitaneha.com/uploads/sample4.jpg'
        ]
    ];
    
    if ($format === 'xlsx' && class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products Import Format');
        $sheet->fromArray($sampleData, null, 'A1');
        
        // Header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5']
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
        
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="product_import_format.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } else {
        // Output CSV with UTF-8 BOM
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="product_import_format.csv"');
        $output = fopen('php://output', 'w');
        // Output UTF-8 BOM
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        foreach ($sampleData as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}

// Handle Active Folder change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_active_folder') {
    $req_folder = trim($_POST['active_folder'] ?? '');
    $clean_folder = str_replace(['..', '\\'], ['', '/'], $req_folder);
    $clean_folder = trim($clean_folder, '/');
    if (empty($clean_folder)) {
        $clean_folder = 'uploads/products';
    }
    $_SESSION['product_import_active_folder'] = $clean_folder;
    $message = "Active folder changed to: " . htmlspecialchars($clean_folder) . "/";
    $message_type = "success";
}

if (!empty($_GET['active_folder'])) {
    $clean_folder = str_replace(['..', '\\'], ['', '/'], trim($_GET['active_folder']));
    $clean_folder = trim($clean_folder, '/');
    if (!empty($clean_folder)) {
        $_SESSION['product_import_active_folder'] = $clean_folder;
    }
}

$active_folder = $_SESSION['product_import_active_folder'] ?? 'uploads/products';

// Check if active folder exists on disk
function check_folder_on_server($folder) {
    $candidates = [
        __DIR__ . '/' . ltrim($folder, '/\\'),
        __DIR__ . '/../' . ltrim($folder, '/\\'),
        dirname(__DIR__) . '/' . ltrim($folder, '/\\')
    ];
    foreach ($candidates as $c) {
        if (is_dir($c)) {
            return realpath($c);
        }
    }
    return false;
}
$resolved_active_folder = check_folder_on_server($active_folder);
$active_folder_exists = ($resolved_active_folder !== false);

// Handle DB Wipe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_db') {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = ['product_images', 'products', 'categories', 'blog_images', 'blogs', 'order_items', 'orders', 'cart_items', 'wishlists', 'activity_logs', 'search_logs', 'newsletters'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$t'");
        if ($stmt->rowCount() > 0) {
            $pdo->exec("TRUNCATE TABLE `$t`");
        }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    $message = "Database cleared successfully. Ready for fresh import.";
    $message_type = "success";
}

// Handle File Upload for AJAX processing (supports CSV, XLSX, XLS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'File upload error (Code: ' . $file['error'] . ')']);
        exit;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
        echo json_encode(['success' => false, 'error' => 'Only CSV, XLSX, or XLS files allowed']);
        exit;
    }
    
    $temp_name = 'temp_import_' . time() . '.csv';
    $target_file = __DIR__ . '/uploads/' . $temp_name;
    
    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
    
    $converted = false;
    if (in_array($ext, ['xlsx', 'xls'])) {
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
                $writer->save($target_file);
                $converted = true;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to convert Excel file: ' . $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Excel processing engine not found. Please upload file in CSV format.']);
            exit;
        }
    } else {
        $converted = move_uploaded_file($file['tmp_name'], $target_file);
    }
    
    if ($converted && file_exists($target_file)) {
        // Count rows
        $lines = 0;
        $handle = fopen($target_file, "r");
        if ($handle) {
            while (!feof($handle)) {
                if (fgets($handle) !== false) {
                    $lines++;
                }
            }
            fclose($handle);
        }
        $total_rows = max(0, $lines - 1); // subtract header
        
        echo json_encode([
            'success' => true,
            'file_name' => $temp_name,
            'total_rows' => $total_rows
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
.active-folder-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #4f46e5;
    border-radius: 8px;
    padding: 14px 18px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.format-download-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 22px;
}
.folder-pill {
    display: inline-block;
    padding: 3px 9px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 14px;
    font-size: 11px;
    font-family: monospace;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
}
.folder-pill:hover {
    background: #e0e7ff;
    border-color: #a5b4fc;
    color: #3730a3;
}
.guide-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    margin-top: 12px;
}
.guide-table th {
    background: #f1f5f9;
    padding: 8px 10px;
    text-align: left;
    border: 1px solid #e2e8f0;
    color: #334155;
    font-weight: 600;
}
.guide-table td {
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    color: #475569;
}
.guide-table tr:nth-child(even) td {
    background: #fcfcfc;
}
</style>

<div class="wrap">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
        <h1 class="wp-heading-inline" style="margin: 0;">Bulk Import Products</h1>
        <div style="display: flex; align-items: center; gap: 8px;">
            <a href="import_archive.php" class="button" style="color: #4f46e5; border-color: #c7d2fe; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-folder-tree"></i> Archive / Folder Import
            </a>
            <a href="products.php" class="button button-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back to Products
            </a>
        </div>
    </div>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Active Folder Banner & Switcher -->
    <div class="active-folder-card">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px;">
                    <span style="color: #4f46e5; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 7px;">
                        <i class="fa-solid fa-folder-open"></i> Active Folder:
                    </span>
                    <code id="activeFolderBadge" style="background: #eef2ff; color: #3730a3; padding: 4px 10px; border-radius: 6px; border: 1px solid #c7d2fe; font-family: monospace; font-size: 13px; font-weight: 600;">
                        <?php echo htmlspecialchars($active_folder); ?>/
                    </code>
                    <?php if ($active_folder_exists): ?>
                        <span style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-circle-check"></i> Folder Ready on Server
                        </span>
                    <?php else: ?>
                        <span style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-folder-plus"></i> Will be created automatically
                        </span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    <span>Quick presets:</span>
                    <button type="button" class="folder-pill" onclick="selectPresetFolder('uploads/products')">uploads/products</button>
                    <button type="button" class="folder-pill" onclick="selectPresetFolder('ittar')">ittar</button>
                    <button type="button" class="folder-pill" onclick="selectPresetFolder('archive')">archive</button>
                    <button type="button" class="folder-pill" onclick="selectPresetFolder('uploads/catalog')">uploads/catalog</button>
                </div>
            </div>

            <!-- Form to Switch Active Folder -->
            <form method="POST" action="product-import.php" style="display: flex; align-items: center; gap: 6px; margin: 0;">
                <input type="hidden" name="action" value="set_active_folder">
                <input type="text" name="active_folder" id="activeFolderHeaderInput" value="<?php echo htmlspecialchars($active_folder); ?>" placeholder="e.g. uploads/products, ittar" style="padding: 5px 10px; font-size: 12px; font-family: monospace; border: 1px solid #94a3b8; border-radius: 5px; width: 200px;" required>
                <button type="submit" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 5px; height: 31px;">
                    <i class="fa-solid fa-arrows-rotate"></i> Change Directory
                </button>
            </form>
        </div>
    </div>

    <!-- Format Download & Guide Box -->
    <div class="format-download-box">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div>
                <h3 style="margin: 0 0 4px 0; font-size: 15px; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-file-arrow-down" style="color: #4f46e5;"></i> Download Import Format Template
                </h3>
                <p style="margin: 0; font-size: 12px; color: #64748b;">
                    Download the pre-configured template with proper column headers and sample data to format your products.
                </p>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <a href="product-import.php?action=download_template&format=csv" class="button button-primary" style="background: #16a34a; border-color: #15803d; display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; font-weight: 600;">
                    <i class="fa-solid fa-file-csv"></i> Download CSV Format (.csv)
                </a>
                <a href="product-import.php?action=download_template&format=xlsx" class="button button-primary" style="background: #4f46e5; border-color: #4338ca; display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; font-weight: 600;">
                    <i class="fa-solid fa-file-excel"></i> Download Excel Format (.xlsx)
                </a>
                <button type="button" id="toggleGuideBtn" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 5px;" onclick="toggleGuide()">
                    <i class="fa-solid fa-circle-question"></i> <span id="toggleGuideText">View Column Guide</span>
                </button>
            </div>
        </div>

        <!-- Collapsible Column Format Guide Table -->
        <div id="formatGuideDrawer" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
            <div style="font-size: 12px; font-weight: 600; color: #1e293b; margin-bottom: 8px;">
                Supported Column Headers & Specifications:
            </div>
            <div style="overflow-x: auto;">
                <table class="guide-table">
                    <thead>
                        <tr>
                            <th>Column Header</th>
                            <th>Required?</th>
                            <th>Sample Value</th>
                            <th>Description & Usage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong style="color: #4f46e5;">SKU</strong></td>
                            <td><span style="color: #d97706; font-weight: 600;">Recommended</span></td>
                            <td><code>YN-1001</code></td>
                            <td>Unique product identifier. Auto-generated if left blank.</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #4f46e5;">Name</strong></td>
                            <td><span style="color: #dc2626; font-weight: 700;">Required</span></td>
                            <td><code>Embroidered Silk Sherwani</code></td>
                            <td>Product title / display name.</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #4f46e5;">Categories</strong></td>
                            <td>Optional</td>
                            <td><code>Men > Ethnic Wear > Sherwanis</code></td>
                            <td>Hierarchical categories separated with <code>&gt;</code>. Multiple categories separated by commas. Automatically created if not found.</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #4f46e5;">Regular price</strong></td>
                            <td><span style="color: #dc2626; font-weight: 700;">Required</span></td>
                            <td><code>15999</code></td>
                            <td>Standard retail / MRP price (numbers only).</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #4f46e5;">Sale price</strong></td>
                            <td>Optional</td>
                            <td><code>12999</code></td>
                            <td>Discounted offer price (numbers only).</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #4f46e5;">Stock</strong></td>
                            <td>Optional</td>
                            <td><code>10</code></td>
                            <td>Available quantity in inventory (default: 10).</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #4f46e5;">In stock?</strong></td>
                            <td>Optional</td>
                            <td><code>1</code></td>
                            <td><code>1</code> = In Stock, <code>0</code> = Out of Stock.</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #4f46e5;">Published</strong></td>
                            <td>Optional</td>
                            <td><code>1</code></td>
                            <td><code>1</code> (or "published") for live product, <code>0</code> (or "draft") for draft mode.</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #4f46e5;">Short description</strong></td>
                            <td>Optional</td>
                            <td><code>Royal handcrafted sherwani...</code></td>
                            <td>Brief summary displayed near buy buttons and in listings.</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #4f46e5;">Description</strong></td>
                            <td>Optional</td>
                            <td><code>Full details & fabric specs...</code></td>
                            <td>Detailed product description (plain text or basic HTML).</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #4f46e5;">Images</strong></td>
                            <td>Optional</td>
                            <td><code>https://.../img1.jpg, photo2.jpg</code></td>
                            <td>Comma-separated image URLs (downloaded automatically) or local filenames searched in the <strong>Active Folder</strong>.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div id="post-body-content">
                <!-- Import UI -->
                <div class="postbox">
                    <h2 class="hndle"><span>Upload Spreadsheet (CSV or Excel)</span></h2>
                    <div class="inside" style="padding: 15px 20px;">
                        <p style="margin-top: 0; color: #475569;">Upload your WooCommerce CSV or Excel (.xlsx, .xls) spreadsheet. Images will be saved into <code><span class="active-folder-text"><?php echo htmlspecialchars($active_folder); ?></span>/</code>.</p>
                        
                        <form id="importForm" enctype="multipart/form-data">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="csvFile">Spreadsheet File</label></th>
                                    <td>
                                        <input type="file" id="csvFile" name="csv_file" accept=".csv, .xlsx, .xls" required style="padding: 4px 0;">
                                        <p class="description">Select a <strong>.csv</strong>, <strong>.xlsx</strong>, or <strong>.xls</strong> file formatted according to our template.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="activeFolderInput">Active Target Directory</label></th>
                                    <td>
                                        <input type="text" id="activeFolderInput" name="active_folder" value="<?php echo htmlspecialchars($active_folder); ?>" class="regular-text font-mono" style="font-family: monospace;">
                                        <p class="description">Images will be stored under <code>[Active Folder]/[SKU]/</code>. If local image paths are used in CSV, they are also checked in this folder.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="importLimit">Import Limit</label></th>
                                    <td>
                                        <input type="number" id="importLimit" name="importLimit" value="5" min="1" class="small-text">
                                        <p class="description">Number of rows to process. Set to a high number (e.g., 9999) to import all products.</p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit" style="margin-bottom: 0;">
                                <button type="submit" class="button button-primary button-large" id="startImportBtn" style="height: 38px; padding: 0 20px; font-size: 14px; font-weight: 600;">
                                    <i class="fa-solid fa-upload"></i> Start Import
                                </button>
                            </p>
                        </form>

                        <!-- Progress UI -->
                        <div id="progressContainer" style="display: none; margin-top: 25px; border-top: 1px solid #ccd0d4; padding-top: 20px;">
                            <h3 id="progressText" style="margin-top:0; color: #1e293b;">Preparing...</h3>
                            <div style="width: 100%; background: #e0e0e0; border-radius: 4px; overflow: hidden; height: 24px; margin-bottom: 20px;">
                                <div id="progressBar" style="width: 0%; height: 100%; background: #2271b1; transition: width 0.3s; text-align: center; color: white; line-height: 24px; font-size: 12px; font-weight: bold;">0%</div>
                            </div>
                            
                            <!-- Live Stats -->
                            <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                                <div style="flex: 1; min-width: 140px; background: #f8f9fa; border: 1px solid #ccd0d4; padding: 15px; text-align: center; border-radius: 6px;">
                                    <h4 style="margin: 0 0 5px 0; color: #50575e; font-size: 12px; text-transform: uppercase;">Products Done</h4>
                                    <span id="statDone" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</span> / <span id="statTotal">0</span>
                                </div>
                                <div style="flex: 1; min-width: 140px; background: #f8f9fa; border: 1px solid #ccd0d4; padding: 15px; text-align: center; border-radius: 6px;">
                                    <h4 style="margin: 0 0 5px 0; color: #50575e; font-size: 12px; text-transform: uppercase;">Images Saved</h4>
                                    <span id="statImages" style="font-size: 24px; font-weight: bold; color: #00a32a;">0</span>
                                </div>
                                <div style="flex: 1; min-width: 140px; background: #f8f9fa; border: 1px solid #ccd0d4; padding: 15px; text-align: center; border-radius: 6px;">
                                    <h4 style="margin: 0 0 5px 0; color: #50575e; font-size: 12px; text-transform: uppercase;">Data Downloaded</h4>
                                    <span id="statSize" style="font-size: 24px; font-weight: bold; color: #d63638;">0 MB</span>
                                </div>
                                <div style="flex: 1; min-width: 140px; background: #f8f9fa; border: 1px solid #ccd0d4; padding: 15px; text-align: center; border-radius: 6px;">
                                    <h4 style="margin: 0 0 5px 0; color: #50575e; font-size: 12px; text-transform: uppercase;">Target Directory</h4>
                                    <span id="statFolder" style="font-size: 14px; font-weight: bold; color: #4f46e5; font-family: monospace;" class="active-folder-text"><?php echo htmlspecialchars($active_folder); ?></span>
                                </div>
                            </div>

                            <div id="logWindow" style="background: #1e1e1e; color: #00ff00; font-family: 'Consolas', monospace; padding: 15px; border-radius: 6px; height: 320px; overflow-y: auto; font-size: 13px; line-height: 1.5;">
                                > Ready to import...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="postbox-container-1" class="postbox-container">
                <!-- Info Metabox -->
                <div class="postbox">
                    <h2 class="hndle"><span><i class="fa-solid fa-circle-info" style="color: #4f46e5;"></i> Directory Tips</span></h2>
                    <div class="inside" style="font-size: 12px; line-height: 1.6; color: #475569;">
                        <p><strong>Active Folder</strong> defines where imported product photos and thumbnails will be organized.</p>
                        <ul style="margin-left: 15px; list-style-type: disc;">
                            <li>Photos are placed in: <code>[Active Folder]/[SKU]/</code></li>
                            <li>Square thumbnails (150x150) are auto-generated in <code>thumbs/</code>.</li>
                            <li>If your server already has folders like <code>ittar/</code> or <code>archive/</code>, you can point directly to them!</li>
                        </ul>
                        <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                            <a href="import_archive.php" style="text-decoration: none; color: #4f46e5; font-weight: 600;">
                                <i class="fa-solid fa-folder-tree"></i> Need folder-based SKU import?
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Wipe DB -->
                <div class="postbox" style="border-left: 4px solid #d63638;">
                    <h2 class="hndle"><span style="color: #d63638;"><i class="fa-solid fa-triangle-exclamation"></i> Clear Database</span></h2>
                    <div class="inside">
                        <p style="font-size: 12px; color: #64748b;">Safely TRUNCATE the catalog database before starting a completely fresh import.</p>
                        <form method="POST" onsubmit="return confirm('WARNING: This will permanently delete all products, categories, logs, and orders! Proceed?');">
                            <input type="hidden" name="action" value="clear_db">
                            <button type="submit" class="button" style="color: #d63638; border-color: #d63638; width: 100%; text-align: center; font-weight: 600;">
                                <i class="fa-solid fa-trash"></i> Wipe Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectPresetFolder(path) {
    document.getElementById('activeFolderHeaderInput').value = path;
    document.getElementById('activeFolderInput').value = path;
}

function toggleGuide() {
    const drawer = document.getElementById('formatGuideDrawer');
    const text = document.getElementById('toggleGuideText');
    if (drawer.style.display === 'none' || drawer.style.display === '') {
        drawer.style.display = 'block';
        text.innerText = 'Hide Column Guide';
    } else {
        drawer.style.display = 'none';
        text.innerText = 'View Column Guide';
    }
}

// Keep activeFolderInput and activeFolderHeaderInput synced
document.getElementById('activeFolderInput').addEventListener('input', function(e) {
    document.getElementById('activeFolderHeaderInput').value = e.target.value;
});
document.getElementById('activeFolderHeaderInput').addEventListener('input', function(e) {
    document.getElementById('activeFolderInput').value = e.target.value;
});

function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 MB';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

document.getElementById('importForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('csvFile');
    const limitInput = document.getElementById('importLimit');
    const activeFolderInput = document.getElementById('activeFolderInput');
    const activeFolder = activeFolderInput.value.trim() || 'uploads/products';
    
    if (!fileInput.files[0]) return;
    
    const btn = document.getElementById('startImportBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading & Parsing...';
    
    const formData = new FormData();
    formData.append('csv_file', fileInput.files[0]);
    formData.append('active_folder', activeFolder);
    
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const logWindow = document.getElementById('logWindow');
    
    // Stats elements
    const statDone = document.getElementById('statDone');
    const statTotal = document.getElementById('statTotal');
    const statImages = document.getElementById('statImages');
    const statSize = document.getElementById('statSize');
    const statFolder = document.getElementById('statFolder');
    if (statFolder) statFolder.innerText = activeFolder;
    
    progressContainer.style.display = 'block';
    logWindow.innerHTML = '';
    
    let totalImages = 0;
    let totalBytes = 0;
    
    function logMessage(msg, isError = false) {
        const div = document.createElement('div');
        div.innerHTML = '> ' + msg;
        if (isError) div.style.color = '#ff6b6b';
        logWindow.appendChild(div);
        logWindow.scrollTop = logWindow.scrollHeight;
    }
    
    try {
        logMessage(`Uploading spreadsheet to server (Active Folder: <b>${activeFolder}</b>)...`);
        const response = await fetch('product-import.php', {
            method: 'POST',
            body: formData
        });
        
        const initData = await response.json();
        if (!initData.success) {
            logMessage(initData.error, true);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-upload"></i> Start Import';
            return;
        }
        
        const fileName = initData.file_name;
        const requestedLimit = parseInt(limitInput.value, 10) || 5;
        const totalRowsToProcess = Math.min(initData.total_rows, requestedLimit);
        
        statTotal.innerText = totalRowsToProcess;
        logMessage(`File uploaded & verified. Beginning row-by-row import of ${totalRowsToProcess} products into <b>${activeFolder}/</b>...`);
        
        for (let i = 1; i <= totalRowsToProcess; i++) {
            progressText.innerText = `Importing Row ${i} of ${totalRowsToProcess}...`;
            const percentage = ((i) / totalRowsToProcess) * 100;
            progressBar.style.width = percentage + '%';
            progressBar.innerText = Math.round(percentage) + '%';
            
            const rowData = new FormData();
            rowData.append('file_name', fileName);
            rowData.append('row_index', i);
            rowData.append('active_folder', activeFolder);
            
            try {
                const rowRes = await fetch('api/import_row.php', {
                    method: 'POST',
                    body: rowData
                });
                
                const rowResult = await rowRes.json();
                if (rowResult.success) {
                    logMessage(rowResult.log);
                    
                    if (rowResult.images_downloaded) {
                        totalImages += rowResult.images_downloaded;
                        statImages.innerText = totalImages;
                    }
                    if (rowResult.image_size) {
                        totalBytes += rowResult.image_size;
                        statSize.innerText = formatBytes(totalBytes);
                    }
                } else {
                    logMessage(`Row ${i} Error: ` + (rowResult.error || 'Failed'), true);
                }
            } catch (err) {
                logMessage(`Row ${i} Network Error: ` + err.message, true);
            }
            
            statDone.innerText = i;
        }
        
        progressBar.style.width = '100%';
        progressBar.innerText = '100%';
        progressText.innerText = "Import Complete!";
        logMessage("Finished processing! All products and images have been saved.", false);
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Import Done!';
        
    } catch (error) {
        logMessage("Server error during upload: " + error.message, true);
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-upload"></i> Start Import';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
