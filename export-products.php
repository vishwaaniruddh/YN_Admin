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

try {
    // 1. Fetch all products with category names
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.sku, c.name as category_name, p.price, p.sale_price, p.stock_qty, p.is_featured, p.status, p.main_image, p.created_at 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.id ASC
    ");
    $products = $stmt->fetchAll();

    // 2. Create Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Products Catalog');

    // Enable gridlines
    $sheet->setShowGridlines(true);

    // 3. Define Headers
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

    // Header Styling
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 11
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1D2327'] // Matching WordPress dark
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'D3D3D3']
            ]
        ]
    ];

    $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
    $sheet->getRowDimension(1)->setRowHeight(28);

    // 4. Fill Data
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

        // Formatting columns
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('₹#,##0.00');
        $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('₹#,##0.00');

        // Alignments
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('K' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Border styling for data rows
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E5E5E5']
                ]
            ]
        ];
        $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray($dataStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;
    }

    // 5. Auto-size columns to fit text nicely
    foreach (range('A', 'K') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 6. Generate File for Download
    $filename = 'YosshitaNeha_Catalog_' . date('Ymd_His') . '.xlsx';

    // Clear output buffer to avoid corruption in spreadsheet binary stream
    if (ob_get_length()) {
        ob_clean();
    }

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1'); // for compatibility with IE9/SSL

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();

} catch (Exception $e) {
    die("Error exporting spreadsheet catalog: " . $e->getMessage());
}
?>
