<?php
// admin/invoice-pdf.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$rawId = trim($_GET['id'] ?? '');
$orderId = 0;
if (preg_match('/^YNFS_(\d+)$/i', $rawId, $matches)) {
    $orderId = (int)$matches[1] - 1000;
} elseif (is_numeric($rawId)) {
    $val = (int)$rawId;
    $orderId = ($val > 1000) ? ($val - 1000) : $val;
}

if ($orderId <= 0) {
    die("Invalid Order ID.");
}

// Fetch Order Details
$stmt = $pdo->prepare("
    SELECT o.*, c.first_name, c.last_name, c.email, c.phone, c.created_at as customer_since
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found.");
}

// Fetch Order Items
$item_stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.main_image, p.sku
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$item_stmt->execute([$orderId]);
$items = $item_stmt->fetchAll();

// Fetch Shipping Address
$addr_stmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC LIMIT 1");
$addr_stmt->execute([$order['customer_id']]);
$address = $addr_stmt->fetch() ?: [];

$orderNumber = format_order_number($order['id']);
$invoiceNumber = 'INV-' . $orderNumber;
$custName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: 'Guest Customer';
$orderDate = date('F j, Y', strtotime($order['created_at']));
$totalFormatted = '₹' . number_format($order['total_amount'], 2);

// Load Base64 Logo
$logoPath = __DIR__ . '/assets/images/logo.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}

// Build Address Text
$addrText = 'Primary address on file';
if (!empty($address['address_line_1'])) {
    $addrText = htmlspecialchars($address['address_line_1']);
    if (!empty($address['address_line_2'])) $addrText .= '<br>' . htmlspecialchars($address['address_line_2']);
    $addrText .= '<br>' . htmlspecialchars($address['city']) . ', ' . htmlspecialchars($address['state']) . ' - ' . htmlspecialchars($address['pincode']);
}

// Build Items Rows HTML with Product Images
$itemsRows = '';
$count = 1;
foreach ($items as $item) {
    $subtotal = '₹' . number_format($item['price'] * $item['quantity'], 2);
    $priceFormatted = '₹' . number_format($item['price'], 2);
    
    // Process product thumbnail for Dompdf / HTML
    $imgHtml = '<div style="width: 45px; height: 55px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 9px; color: #aaa;">No Image</div>';
    if (!empty($item['main_image'])) {
        $imgPath = strpos($item['main_image'], 'http') === 0 ? $item['main_image'] : __DIR__ . '/' . ltrim($item['main_image'], '/');
        if (file_exists($imgPath)) {
            $ext = pathinfo($imgPath, PATHINFO_EXTENSION);
            $mime = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : 'image/jpeg');
            $imgData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($imgPath));
            $imgHtml = '<img src="' . $imgData . '" style="width: 45px; height: 55px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">';
        } else {
            $imgHtml = '<img src="' . htmlspecialchars($item['main_image']) . '" style="width: 45px; height: 55px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">';
        }
    }

    $itemsRows .= '
    <tr>
        <td style="text-align: center; border-bottom: 1px solid #eee; padding: 10px 6px;">' . $count++ . '</td>
        <td style="text-align: center; border-bottom: 1px solid #eee; padding: 8px 4px; width: 55px;">' . $imgHtml . '</td>
        <td style="border-bottom: 1px solid #eee; padding: 10px 8px;">
            <strong style="color: #111; font-size: 12px;">' . htmlspecialchars($item['name']) . '</strong><br>
            <span style="font-size: 10px; color: #666;">SKU: ' . htmlspecialchars($item['sku']) . '</span>
        </td>
        <td style="text-align: right; border-bottom: 1px solid #eee; padding: 10px 8px;">' . $priceFormatted . '</td>
        <td style="text-align: center; border-bottom: 1px solid #eee; padding: 10px 8px; font-weight: bold;">' . (int)$item['quantity'] . '</td>
        <td style="text-align: right; border-bottom: 1px solid #eee; padding: 10px 8px; font-weight: bold; color: #111;">' . $subtotal . '</td>
    </tr>';
}

$invSubtotalVal = $order['subtotal_amount'] > 0 ? (float)$order['subtotal_amount'] : array_sum(array_map(function($i){ return (float)$i['price'] * (int)$i['quantity']; }, $items));
$invShippingVal = (float)($order['shipping_charge'] ?? 0);
$invDiscountVal = (float)($order['discount_amount'] ?? 0);
$invCouponCode = trim($order['coupon_code'] ?? '');

$action = strtolower(trim($_GET['action'] ?? $_GET['mode'] ?? 'pdf'));

// Clean Standalone HTML Template for PDF & Print using DejaVu Sans for Rupee Symbol Support
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tax Invoice - ' . $orderNumber . '</title>
    <style>
        @page { margin: 20px; }
        body { font-family: "DejaVu Sans", sans-serif; color: #222; margin: 0; padding: 15px; background: #fff; font-size: 11px; line-height: 1.5; }
        .invoice-box { max-width: 800px; margin: auto; padding: 15px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, 0.05); }
        table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
        .header-title { font-size: 20px; color: #c8a55c; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; margin: 0; }
        .header-subtitle { font-size: 10px; color: #666; letter-spacing: 2px; text-transform: uppercase; margin-top: 2px; }
        .invoice-type { font-size: 16px; font-weight: bold; color: #111; text-transform: uppercase; text-align: right; }
        .meta-table td { padding: 4px 0; vertical-align: top; }
        .items-table { margin-top: 15px; border: 1px solid #e2e8f0; }
        .items-table th { background: #f8f9fa; color: #333; font-weight: bold; text-transform: uppercase; font-size: 10px; padding: 8px; border-bottom: 2px solid #cbd5e1; }
        .totals-box { margin-top: 15px; float: right; width: 330px; }
        .totals-table td { padding: 6px; }
        .grand-total td { font-size: 14px; font-weight: bold; color: #c8a55c; border-top: 2px solid #c8a55c; padding-top: 8px; }
        .footer-note { text-align: center; margin-top: 30px; padding-top: 10px; border-top: 1px solid #eee; font-size: 9px; color: #777; }
        @media print {
            body { padding: 0; }
            .invoice-box { border: none; box-shadow: none; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    ' . ($action === 'print' || $action === 'view' ? '
    <div class="no-print" style="margin-bottom: 20px; text-align: right; background: #f8f9fa; padding: 12px 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
        <button onclick="window.print()" style="background: #c8a55c; color: #fff; border: none; padding: 8px 18px; border-radius: 4px; font-weight: bold; cursor: pointer; margin-right: 10px;">
            Print Invoice
        </button>
        <a href="invoice-pdf.php?id=' . $order['id'] . '&action=pdf&download=1" style="background: #1e293b; color: #fff; text-decoration: none; padding: 8px 18px; border-radius: 4px; font-weight: bold;">
            Download PDF
        </a>
    </div>
    ' : '') . '

    <div class="invoice-box">
        <!-- Header Banner with Pasted Logo -->
        <table>
            <tr>
                <td style="width: 55%; vertical-align: middle;">
                    ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" style="max-height: 55px; width: auto; display: block; margin-bottom: 6px;">' : '<div class="header-title">YOSSHITANEHA</div><div class="header-subtitle">Luxury Bridal &amp; Fashion Studio</div>') . '
                    <div style="font-size: 9px; color: #555; margin-top: 4px;">
                        GSTIN: 27AABCY1234F1Z9 | Email: info@yosshitaneha.com
                    </div>
                </td>
                <td style="width: 45%; text-align: right; vertical-align: middle;">
                    <div class="invoice-type">TAX INVOICE</div>
                    <div style="font-size: 11px; color: #444; margin-top: 4px; line-height: 1.4;">
                        <strong>Invoice No:</strong> ' . $invoiceNumber . '<br>
                        <strong>Order No:</strong> ' . $orderNumber . '<br>
                        <strong>Date:</strong> ' . $orderDate . '<br>
                        <strong>Payment Status:</strong> <span style="color: #16a34a; font-weight: bold;">PAID</span>
                    </div>
                </td>
            </tr>
        </table>

        <div style="height: 1px; background: #c8a55c; margin: 12px 0;"></div>

        <!-- Billed & Shipped Information Grid -->
        <table class="meta-table">
            <tr>
                <td style="width: 50%; padding-right: 10px;">
                    <strong style="color: #c8a55c; text-transform: uppercase; font-size: 10px;">CUSTOMER DETAILS:</strong><br>
                    <strong style="font-size: 12px; color: #111;">' . htmlspecialchars($custName) . '</strong><br>
                    Email: ' . htmlspecialchars($order['email'] ?: 'On file') . '<br>
                    Phone: ' . htmlspecialchars($order['phone'] ?: 'On file') . '
                </td>
                <td style="width: 50%; padding-left: 10px;">
                    <strong style="color: #c8a55c; text-transform: uppercase; font-size: 10px;">SHIPPING ADDRESS:</strong><br>
                    ' . $addrText . '
                </td>
            </tr>
        </table>

        <!-- Items Table with Product Images -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 30px; text-align: center;">#</th>
                    <th style="width: 55px; text-align: center;">Image</th>
                    <th>Item Description &amp; SKU</th>
                    <th style="width: 90px; text-align: right;">Unit Price</th>
                    <th style="width: 40px; text-align: center;">Qty</th>
                    <th style="width: 100px; text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                ' . $itemsRows . '
            </tbody>
        </table>

        <!-- Totals Summary -->
        <div class="totals-box">
            <table class="totals-table">
                <tr>
                    <td style="color: #666; width: 140px; white-space: nowrap;">Payment Method:</td>
                    <td style="text-align: right; font-weight: bold; color: #111;">' . htmlspecialchars($order['payment_method'] ?: 'Online Payment') . '</td>
                </tr>
                ' . (!empty($order['transaction_id']) ? '
                <tr>
                    <td style="color: #666;">Txn ID:</td>
                    <td style="text-align: right; font-family: monospace; font-size: 10px; color: #333;">' . htmlspecialchars($order['transaction_id']) . '</td>
                </tr>
                ' : '') . '
                <tr>
                    <td style="color: #666;">Items Subtotal:</td>
                    <td style="text-align: right; color: #111; font-weight: bold;">₹' . number_format($invSubtotalVal, 2) . '</td>
                </tr>
                ' . ($invDiscountVal > 0 || !empty($invCouponCode) ? '
                <tr>
                    <td style="color: #0d9488; font-weight: bold;">Coupon (' . htmlspecialchars($invCouponCode ?: 'Discount') . '):</td>
                    <td style="text-align: right; color: #0d9488; font-weight: bold;">-₹' . number_format($invDiscountVal, 2) . '</td>
                </tr>
                ' : '') . '
                <tr>
                    <td style="color: #666;">Shipping Fee:</td>
                    <td style="text-align: right; color: ' . ($invShippingVal > 0 ? '#111' : '#16a34a') . '; font-weight: bold;">' . ($invShippingVal > 0 ? '₹' . number_format($invShippingVal, 2) : 'FREE') . '</td>
                </tr>
                <tr class="grand-total">
                    <td>Grand Total:</td>
                    <td style="text-align: right;">' . $totalFormatted . '</td>
                </tr>
            </table>
        </div></div>

        <div style="clear: both;"></div>

        <!-- Footer Notice -->
        <div class="footer-note">
            Thank you for choosing <strong>YosshitaNeha Fashion Studio</strong> for your luxury couture requirements.<br>
            This document is a computer-generated tax invoice and requires no physical signature.
        </div>
    </div>

    ' . ($action === 'print' || $action === 'view' ? '
    <script>
        window.onload = function() {
            if (window.location.search.indexOf("auto=1") !== -1) {
                window.print();
            }
        };
    </script>
    ' : '') . '
</body>
</html>';

// Handle Output Mode: Dompdf Stream vs HTML View
if ($action === 'print' || $action === 'view' || isset($_GET['html'])) {
    header("Content-Type: text/html; charset=UTF-8");
    echo $html;
    exit();
} else {
    // Generate PDF using Dompdf library with DejaVu Sans font support
    try {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $attachment = isset($_GET['download']) && $_GET['download'] == '1';
        $dompdf->stream("Invoice_" . $orderNumber . ".pdf", ["Attachment" => $attachment]);
        exit();
    } catch (Exception $e) {
        header("Content-Type: text/html; charset=UTF-8");
        echo $html;
        exit();
    }
}
