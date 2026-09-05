<?php
// admin/sku-lookup.php
$page_title = 'SKU Lookup';

// Load dependencies BEFORE any HTML output (needed for export)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

set_time_limit(120);
ini_set('memory_limit', '256M');

// ------------------------------------------------------------------
// Handle Export (MUST run before any HTML output)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_results'])) {
    $exportData = json_decode($_POST['export_data'], true);

    if (!empty($exportData)) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SKU Lookup Results');

        // Header row
        $sheet->setCellValue('A1', 'SKU');
        $sheet->setCellValue('B1', 'Product Name');
        $sheet->setCellValue('C1', 'Description');

        // Style header
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1A1A2E'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '3A3A4E'],
                ],
            ],
        ];
        $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Data rows
        $row = 2;
        foreach ($exportData as $item) {
            $sheet->setCellValue('A' . $row, $item['sku']);
            $sheet->setCellValue('B' . $row, $item['name']);
            $sheet->setCellValue('C' . $row, $item['description']);

            // Highlight not-found rows
            if (empty($item['name'])) {
                $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFF3CD'],
                    ],
                    'font' => [
                        'color' => ['rgb' => '856404'],
                    ],
                ]);
            }
            $row++;
        }

        // Auto-size columns
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getColumnDimension('C')->setWidth(80);

        // Apply borders to all data
        $lastRow = $row - 1;
        $sheet->getStyle('A1:C' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD'],
                ],
            ],
        ]);

        // Set text wrapping for description column
        $sheet->getStyle('C2:C' . $lastRow)->getAlignment()->setWrapText(true);

        // Output as download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="sku_lookup_' . date('Ymd_His') . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

// ------------------------------------------------------------------
// Now safe to output HTML — include header & sidebar
// ------------------------------------------------------------------
require_once __DIR__ . '/includes/header.php';

$results = [];
$error = '';
$totalSkus = 0;
$foundCount = 0;
$notFoundCount = 0;
$uploaded = false;

// ------------------------------------------------------------------
// Handle Upload & Lookup
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sku_file'])) {
    $file = $_FILES['sku_file'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed. Error code: ' . $file['error'];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $error = 'Invalid file format. Please upload .xlsx, .xls, or .csv files only.';
        }
    }

    if (empty($error)) {
        try {
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // Collect all SKUs from column A (skip row 1 if it looks like a header)
            $skus = [];
            $startRow = 1;

            $firstCellValue = trim((string) $sheet->getCell('A1')->getValue());
            if (strtolower($firstCellValue) === 'sku' || strtolower($firstCellValue) === 'sku code' || strtolower($firstCellValue) === 'product sku') {
                $startRow = 2; // Skip header row
            }

            for ($row = $startRow; $row <= $highestRow; $row++) {
                $sku = trim((string) $sheet->getCell('A' . $row)->getValue());
                if ($sku !== '') {
                    $skus[] = [
                        'row' => $row,
                        'sku' => $sku,
                    ];
                }
            }

            $totalSkus = count($skus);

            if ($totalSkus === 0) {
                $error = 'No SKUs found in column A. Please make sure the first column contains SKU values.';
            } else {
                // Batch lookup all SKUs from the database
                $placeholders = implode(',', array_fill(0, $totalSkus, '?'));
                $skuValues = array_column($skus, 'sku');

                $stmt = $pdo->prepare("
                    SELECT sku, name, description 
                    FROM products 
                    WHERE sku IN ($placeholders) 
                    AND deleted_at IS NULL
                ");
                $stmt->execute($skuValues);
                $dbProducts = [];
                while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $dbProducts[strtolower(trim($p['sku']))] = $p;
                }

                // Map results in the SAME ORDER as uploaded
                foreach ($skus as $item) {
                    $lookupKey = strtolower(trim($item['sku']));
                    if (isset($dbProducts[$lookupKey])) {
                        $p = $dbProducts[$lookupKey];
                        // Strip HTML from description
                        $cleanDesc = strip_tags(html_entity_decode($p['description'] ?? '', ENT_QUOTES, 'UTF-8'));
                        $results[] = [
                            'sku'         => $item['sku'],
                            'name'        => $p['name'],
                            'description' => $cleanDesc,
                            'found'       => true,
                        ];
                        $foundCount++;
                    } else {
                        $results[] = [
                            'sku'         => $item['sku'],
                            'name'        => '',
                            'description' => '',
                            'found'       => false,
                        ];
                        $notFoundCount++;
                    }
                }
                $uploaded = true;
            }
        } catch (Exception $e) {
            $error = 'Error reading Excel file: ' . $e->getMessage();
        }
    }
}


// Include sidebar
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Page Title -->
<div class="content-header">
    <h1 class="page-title">
        <i class="fa-solid fa-magnifying-glass" style="color: #c8a55c; margin-right: 8px;"></i>
        SKU Lookup Tool
    </h1>
    <p class="page-subtitle" style="color: #8a8fa3; margin-top: 4px; font-size: 14px;">
        Upload an Excel file with SKUs → Get product names & descriptions → Export results
    </p>
</div>

<style>
    /* ── SKU Lookup Page Styles ── */
    .sku-lookup-container {
        max-width: 1100px;
    }

    .sku-upload-card {
        background: #ffffff;
        border: 1px solid #e4e4e7;
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .sku-upload-card h2 {
        font-size: 14.5px;
        font-weight: 600;
        color: #09090b;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sku-upload-card h2 i {
        color: #71717a;
    }

    /* Drag-and-drop zone */
    .upload-dropzone {
        border: 2px dashed #e4e4e7;
        border-radius: 8px;
        padding: 40px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.15s ease;
        background: #fafafa;
        position: relative;
    }

    .upload-dropzone:hover,
    .upload-dropzone.dragover {
        border-color: #09090b;
        background: #f4f4f5;
    }

    .upload-dropzone .dropzone-icon {
        font-size: 38px;
        color: #a1a1aa;
        margin-bottom: 12px;
        transition: color 0.15s ease;
    }

    .upload-dropzone:hover .dropzone-icon,
    .upload-dropzone.dragover .dropzone-icon {
        color: #09090b;
    }

    .upload-dropzone h3 {
        font-size: 13.5px;
        color: #09090b;
        margin-bottom: 6px;
        font-weight: 500;
    }

    .upload-dropzone p {
        color: #71717a;
        font-size: 12px;
    }

    .upload-dropzone input[type="file"] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
    }

    .selected-file-info {
        display: none;
        margin-top: 14px;
        padding: 10px 14px;
        background: #f4f4f5;
        border: 1px solid #e4e4e7;
        border-radius: 6px;
        color: #09090b;
        font-size: 12.5px;
        align-items: center;
        gap: 8px;
    }

    .selected-file-info.active {
        display: flex;
    }

    .btn-lookup {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 18px;
        padding: 0 16px;
        height: 34px;
        background: #09090b;
        color: #ffffff;
        border: none;
        border-radius: 6px;
        font-size: 12.5px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-lookup:hover {
        background: #27272a;
    }

    .btn-lookup:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Stats strip */
    .stats-strip {
        display: flex;
        gap: 14px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .stat-badge {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #ffffff;
        border: 1px solid #e4e4e7;
        border-radius: 8px;
        padding: 12px 18px;
        flex: 1;
        min-width: 180px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .stat-badge .stat-icon {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .stat-badge .stat-icon.total { background: rgba(99, 102, 241, 0.08); color: #6366f1; }
    .stat-badge .stat-icon.found { background: rgba(16, 185, 129, 0.08); color: #10b981; }
    .stat-badge .stat-icon.missing { background: rgba(245, 158, 11, 0.08); color: #f59e0b; }

    .stat-badge .stat-text .stat-num {
        font-size: 20px;
        font-weight: 600;
        color: #09090b;
        line-height: 1.2;
    }

    .stat-badge .stat-text .stat-label {
        font-size: 11px;
        color: #71717a;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 500;
    }

    /* Results Table */
    .results-card {
        background: #ffffff;
        border: 1px solid #e4e4e7;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .results-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-bottom: 1px solid #f4f4f5;
        flex-wrap: wrap;
        gap: 12px;
        background: #ffffff;
    }

    .results-card-header h2 {
        font-size: 14px;
        color: #09090b;
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
        font-weight: 600;
    }

    .results-card-header h2 i {
        color: #71717a;
    }

    .btn-export {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0 14px;
        height: 32px;
        background: #10b981;
        color: #ffffff;
        border: none;
        border-radius: 6px;
        font-size: 12.5px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-export:hover {
        background: #059669;
    }

    .results-table-wrap {
        overflow-x: auto;
    }

    .results-table {
        width: 100%;
        border-collapse: collapse;
    }

    .results-table thead th {
        background: #fafafa;
        color: #71717a;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 9px 14px;
        text-align: left;
        border-bottom: 1px solid #e4e4e7;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .results-table thead th:first-child { padding-left: 16px; }

    .results-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid #f4f4f5;
        font-size: 13px;
        color: #09090b;
        vertical-align: top;
    }

    .results-table tbody td:first-child { padding-left: 16px; }

    .results-table tbody tr:hover { background: #fafafa; }

    .results-table .sku-cell {
        font-family: monospace;
        font-weight: 500;
        color: #09090b;
        white-space: nowrap;
    }

    .results-table .name-cell {
        color: #09090b;
        font-weight: 500;
        max-width: 300px;
    }

    .results-table .desc-cell {
        color: #71717a;
        max-width: 400px;
        line-height: 1.4;
    }

    .results-table .desc-cell .desc-truncated {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .row-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 2px 7px;
        border-radius: 4px;
        font-size: 10.5px;
        font-weight: 500;
        white-space: nowrap;
        text-transform: uppercase;
    }

    .row-status.found {
        background: rgba(16, 185, 129, 0.08);
        color: #047857;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .row-status.not-found {
        background: rgba(245, 158, 11, 0.08);
        color: #b45309;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    /* Format hint card */
    .format-hint {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px 18px;
        margin-top: 18px;
    }

    .format-hint h4 {
        font-size: 12.5px;
        color: #334155;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
    }

    .format-hint .hint-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
    }

    .format-hint .hint-table th,
    .format-hint .hint-table td {
        padding: 7px 12px;
        border: 1px solid #e2e8f0;
        font-size: 12px;
        text-align: left;
    }

    .format-hint .hint-table th {
        background: #f1f5f9;
        color: #475569;
        font-weight: 500;
    }

    .format-hint .hint-table td {
        color: #334155;
    }

    /* Error alert */
    .alert-error {
        background: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.25);
        border-radius: 10px;
        padding: 16px 20px;
        color: #fca5a5;
        font-size: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-error i { color: #ef4444; font-size: 18px; }

    /* Responsive */
    @media (max-width: 768px) {
        .sku-upload-card { padding: 20px; }
        .upload-dropzone { padding: 30px 16px; }
        .stats-strip { flex-direction: column; }
        .stat-badge { min-width: auto; }
        .results-card-header { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="sku-lookup-container">

    <!-- Error Alert -->
    <?php if (!empty($error)): ?>
        <div class="alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Upload Card -->
    <div class="sku-upload-card">
        <h2>
            <i class="fa-solid fa-file-arrow-up"></i>
            Upload Excel File
        </h2>

        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-dropzone" id="dropzone">
                <input type="file" name="sku_file" id="skuFileInput" accept=".xlsx,.xls,.csv" required>
                <div class="dropzone-icon">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <h3>Drag & drop your Excel file here</h3>
                <p>or click to browse &nbsp;·&nbsp; Supports .xlsx, .xls, .csv</p>
            </div>

            <div class="selected-file-info" id="fileInfo">
                <i class="fa-solid fa-file-excel" style="font-size: 20px;"></i>
                <span id="fileName"></span>
            </div>

            <button type="submit" class="btn-lookup" id="lookupBtn" disabled>
                <i class="fa-solid fa-magnifying-glass"></i>
                Lookup Products
            </button>
        </form>

        <!-- Format Hint -->
        <div class="format-hint">
            <h4><i class="fa-solid fa-circle-info"></i> Expected Format</h4>
            <p style="color: #9ca3af; font-size: 13px; margin-bottom: 8px;">
                Column A should contain SKU codes. A header row (optional) will be auto-detected and skipped.
            </p>
            <table class="hint-table">
                <thead>
                    <tr>
                        <th>Column A (SKU)</th>
                        <th>Column B (Auto-filled)</th>
                        <th>Column C (Auto-filled)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>YN-BL-001</td>
                        <td><em style="color: #4ade80;">→ Product Name</em></td>
                        <td><em style="color: #4ade80;">→ Description</em></td>
                    </tr>
                    <tr>
                        <td>YN-JW-042</td>
                        <td><em style="color: #4ade80;">→ Product Name</em></td>
                        <td><em style="color: #4ade80;">→ Description</em></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($uploaded && !empty($results)): ?>

        <!-- Stats Strip -->
        <div class="stats-strip">
            <div class="stat-badge">
                <div class="stat-icon total"><i class="fa-solid fa-list-ol"></i></div>
                <div class="stat-text">
                    <div class="stat-num"><?php echo $totalSkus; ?></div>
                    <div class="stat-label">Total SKUs</div>
                </div>
            </div>
            <div class="stat-badge">
                <div class="stat-icon found"><i class="fa-solid fa-circle-check"></i></div>
                <div class="stat-text">
                    <div class="stat-num"><?php echo $foundCount; ?></div>
                    <div class="stat-label">Found</div>
                </div>
            </div>
            <div class="stat-badge">
                <div class="stat-icon missing"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="stat-text">
                    <div class="stat-num"><?php echo $notFoundCount; ?></div>
                    <div class="stat-label">Not Found</div>
                </div>
            </div>
        </div>

        <!-- Results Table -->
        <div class="results-card">
            <div class="results-card-header">
                <h2>
                    <i class="fa-solid fa-table-list"></i>
                    Lookup Results
                </h2>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="export_results" value="1">
                    <input type="hidden" name="export_data" value="<?php echo htmlspecialchars(json_encode($results)); ?>">
                    <button type="submit" class="btn-export">
                        <i class="fa-solid fa-file-export"></i>
                        Export to Excel
                    </button>
                </form>
            </div>

            <div class="results-table-wrap" style="max-height: 600px; overflow-y: auto;">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 80px;">Status</th>
                            <th style="width: 160px;">SKU</th>
                            <th style="width: 280px;">Product Name</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $i => $r): ?>
                            <tr>
                                <td style="color: #6b7280;"><?php echo $i + 1; ?></td>
                                <td>
                                    <?php if ($r['found']): ?>
                                        <span class="row-status found"><i class="fa-solid fa-check"></i> Found</span>
                                    <?php else: ?>
                                        <span class="row-status not-found"><i class="fa-solid fa-xmark"></i> Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td class="sku-cell"><?php echo htmlspecialchars($r['sku']); ?></td>
                                <td class="name-cell"><?php echo htmlspecialchars($r['name'] ?: '—'); ?></td>
                                <td class="desc-cell">
                                    <div class="desc-truncated"><?php echo htmlspecialchars($r['description'] ?: '—'); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('skuFileInput');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const lookupBtn = document.getElementById('lookupBtn');

    // File selected
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            const f = this.files[0];
            fileName.textContent = f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
            fileInfo.classList.add('active');
            lookupBtn.disabled = false;
        } else {
            fileInfo.classList.remove('active');
            lookupBtn.disabled = true;
        }
    });

    // Drag-and-drop visual feedback
    ['dragenter', 'dragover'].forEach(evt => {
        dropzone.addEventListener(evt, function(e) {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        dropzone.addEventListener(evt, function(e) {
            e.preventDefault();
            dropzone.classList.remove('dragover');
        });
    });

    // Show loading on submit
    const form = document.getElementById('uploadForm');
    form.addEventListener('submit', function() {
        lookupBtn.disabled = true;
        lookupBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Looking up...';
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
