<?php
// admin/export-products.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : '';
$cat_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// If format is specified ('excel', 'xlsx', 'csv'), process download immediately
if ($format === 'excel' || $format === 'xlsx' || $format === 'csv') {
    try {
        $whereSql = " WHERE p.deleted_at IS NULL";
        $params = [];
        if ($cat_id > 0) {
            $whereSql .= " AND p.category_id = ?";
            $params[] = $cat_id;
        }

        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.sku, c.name as category_name, p.price, p.sale_price, p.stock_qty, p.is_featured, p.status, p.main_image, p.created_at 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            $whereSql
            ORDER BY p.id ASC
        ");
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 1. CSV Export Handler
        if ($format === 'csv') {
            $filename = 'YosshitaNeha_Catalog_' . date('Ymd_His') . '.csv';

            if (ob_get_length()) ob_clean();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $output = fopen('php://output', 'w');
            // Add UTF-8 BOM for Microsoft Excel compatibility
            fputs($output, "\xEF\xBB\xBF");

            // Header row
            fputcsv($output, [
                'Product ID',
                'Product Name',
                'SKU',
                'Category',
                'Price (INR)',
                'Sale Price (INR)',
                'Stock Quantity',
                'Is Featured',
                'Status',
                'Main Image',
                'Date Created'
            ]);

            foreach ($products as $p) {
                fputcsv($output, [
                    $p['id'],
                    $p['name'],
                    $p['sku'] ?: 'N/A',
                    $p['category_name'] ?: 'Uncategorized',
                    number_format((float)$p['price'], 2, '.', ''),
                    $p['sale_price'] ? number_format((float)$p['sale_price'], 2, '.', '') : '',
                    $p['stock_qty'],
                    $p['is_featured'] ? 'Yes' : 'No',
                    ucfirst($p['status']),
                    $p['main_image'] ?: '',
                    date('Y-m-d H:i:s', strtotime($p['created_at']))
                ]);
            }

            fclose($output);
            exit();
        }

        // 2. Excel (.xlsx) Export Handler
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products Catalog');
        $sheet->setShowGridlines(true);

        $headers = [
            'A1' => 'Product ID',
            'B1' => 'Product Name',
            'C1' => 'SKU',
            'D1' => 'Category',
            'E1' => 'Price (INR)',
            'F1' => 'Sale Price (INR)',
            'G1' => 'Stock Quantity',
            'H1' => 'Is Featured',
            'I1' => 'Status',
            'J1' => 'Main Image',
            'K1' => 'Date Created'
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D2327']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D3D3D3']]]
        ];
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $row = 2;
        foreach ($products as $p) {
            $sheet->setCellValue('A' . $row, $p['id']);
            $sheet->setCellValue('B' . $row, $p['name']);
            $sheet->setCellValue('C' . $row, $p['sku']);
            $sheet->setCellValue('D' . $row, $p['category_name'] ?: 'Uncategorized');
            $sheet->setCellValue('E' . $row, $p['price']);
            $sheet->setCellValue('F' . $row, $p['sale_price'] ?: '');
            $sheet->setCellValue('G' . $row, $p['stock_qty']);
            $sheet->setCellValue('H' . $row, $p['is_featured'] ? 'Yes' : 'No');
            $sheet->setCellValue('I' . $row, ucfirst($p['status']));
            $sheet->setCellValue('J' . $row, $p['main_image'] ?: '');
            $sheet->setCellValue('K' . $row, date('Y-m-d H:i', strtotime($p['created_at'])));

            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('₹#,##0.00');
            $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('₹#,##0.00');

            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('K' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dataStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E5E5']]]];
            $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray($dataStyle);
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        foreach (range('A', 'K') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $filename = 'YosshitaNeha_Catalog_' . date('Ymd_His') . '.xlsx';
        if (ob_get_length()) ob_clean();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();

    } catch (Exception $e) {
        die("Error exporting catalog: " . $e->getMessage());
    }
}

// Display UI Selection Page if no format specified in URL
$page_title = "Export Products Catalog";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch categories for filtering
$stmtCats = $pdo->query("SELECT id, name FROM categories WHERE deleted_at IS NULL ORDER BY name ASC");
$categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

// Count total products
$stmtCount = $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL");
$totalProducts = (int)$stmtCount->fetchColumn();
?>

<div class="wrap-header">
    <h1><i class="fa-solid fa-file-export" style="color: var(--wp-blue);"></i> Export Product Catalog</h1>
</div>

<div class="postbox" style="max-width: 800px; margin-bottom: 25px;">
    <div class="postbox-header">
        <h2><i class="fa-solid fa-download" style="color: var(--wp-blue);"></i> Choose Export Format &amp; Options</h2>
    </div>
    <div class="postbox-body" style="padding: 24px;">
        <p style="color: #646970; font-size: 14px; margin-top: 0; margin-bottom: 24px;">
            Export your entire product catalog (total <strong><?php echo number_format($totalProducts); ?> products</strong>) into an Excel spreadsheet or CSV format.
        </p>

        <form method="GET" action="export-products.php" style="display: flex; flex-direction: column; gap: 20px;">
            
            <div class="form-group">
                <label for="category_id" style="font-weight: 600; display: block; margin-bottom: 6px;">Filter by Category (Optional)</label>
                <select name="category_id" id="category_id" class="form-control" style="max-width: 400px;">
                    <option value="0">-- All Categories (Entire Catalog) --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 10px;">
                
                <!-- Excel Export Card -->
                <button type="submit" name="format" value="excel" class="button button-primary" style="padding: 12px 24px; font-size: 14px; font-weight: 600; height: auto; display: inline-flex; align-items: center; gap: 10px; background: #16a34a; border-color: #15803d;">
                    <i class="fa-solid fa-file-excel" style="font-size: 18px;"></i> Export as Excel (.xlsx)
                </button>

                <!-- CSV Export Card -->
                <button type="submit" name="format" value="csv" class="button button-secondary" style="padding: 12px 24px; font-size: 14px; font-weight: 600; height: auto; display: inline-flex; align-items: center; gap: 10px; background: #0284c7; color: #fff; border-color: #0369a1;">
                    <i class="fa-solid fa-file-csv" style="font-size: 18px;"></i> Export as CSV (.csv)
                </button>

            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
