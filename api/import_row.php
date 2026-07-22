<?php
// admin/api/import_row.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!current_user_can('manage_products')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$row_index = isset($_POST['row_index']) ? (int)$_POST['row_index'] : 0;
$file_name = isset($_POST['file_name']) ? $_POST['file_name'] : '';

if ($row_index < 1 || empty($file_name)) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$file_path = __DIR__ . '/../uploads/' . basename($file_name);

if (!file_exists($file_path)) {
    echo json_encode(['success' => false, 'error' => 'CSV file not found on server']);
    exit;
}

// Image Downloader Helper
function download_img($url, $target_dir, $filename_without_ext) {
    $url = str_replace(' ', '%20', $url);
    $context = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0\r\n"]]);
    $image_data = @file_get_contents($url, false, $context);
    if (!$image_data) return false;
    
    $absolute_target_dir = __DIR__ . '/../' . ltrim($target_dir, '/');
    if (!is_dir($absolute_target_dir)) @mkdir($absolute_target_dir, 0755, true);
    
    $thumb_dir = $absolute_target_dir . '/thumbs/';
    if (!is_dir($thumb_dir)) @mkdir($thumb_dir, 0755, true);
    
    $ext = 'jpg';
    $url_path = parse_url($url, PHP_URL_PATH);
    if ($url_path && preg_match('/\.([a-zA-Z0-9]+)$/i', $url_path, $matches)) {
        $ext = strtolower($matches[1]);
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $ext = 'jpg';
    }
    
    $filename = $filename_without_ext . '.' . $ext;
    $target_file = rtrim($absolute_target_dir, '/') . '/' . $filename;
    
    file_put_contents($target_file, $image_data);
    $file_size = filesize($target_file);
    
    $thumb_filename = 'thumb_' . $filename;
    $thumb_target = rtrim($thumb_dir, '/') . '/' . $thumb_filename;
    
    if (!file_exists($thumb_target)) {
        generate_square_thumbnail($target_file, $thumb_target, 150);
    }
    
    return [
        'filepath' => rtrim(ltrim($target_dir, '/'), '/') . '/' . $filename,
        'thumbpath' => rtrim(ltrim($target_dir, '/'), '/') . '/thumbs/' . $thumb_filename,
        'size' => $file_size
    ];
}

// Category Tree Parser Helper
function get_or_create_cat($pdo, $category_string) {
    if (empty($category_string)) return null;
    $branches = explode(',', $category_string);
    $primary_branch = trim($branches[0]);
    if (empty($primary_branch)) return null;
    
    $parts = explode('>', $primary_branch);
    $parent_id = null;
    $current_id = null;
    
    foreach ($parts as $part) {
        $cat_name = trim($part);
        if (empty($cat_name)) continue;
        
        $slug = generate_slug($cat_name);
        
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $current_id = $existing['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$cat_name, $slug, $parent_id]);
            $current_id = $pdo->lastInsertId();
        }
        $parent_id = $current_id;
    }
    return $current_id;
}

$handle = fopen($file_path, "r");
if ($handle === FALSE) {
    echo json_encode(['success' => false, 'error' => 'Could not open CSV']);
    exit;
}

// Read Header
$raw_headers = fgetcsv($handle);
if (!$raw_headers) {
    fclose($handle);
    echo json_encode(['success' => false, 'error' => 'Empty CSV header']);
    exit;
}

// Clean UTF-8 BOM if present on first header column
$raw_headers[0] = preg_replace('/\x{EF}\x{BB}\x{BF}/u', '', $raw_headers[0]);

$header_map = [];
foreach ($raw_headers as $idx => $hname) {
    $clean_k = strtolower(trim($hname));
    $header_map[$clean_k] = $idx;
}

function get_col_val($row_data, $header_map, $possible_names, $default = '') {
    foreach ($possible_names as $name) {
        $key = strtolower(trim($name));
        if (isset($header_map[$key]) && isset($row_data[$header_map[$key]])) {
            $val = trim($row_data[$header_map[$key]]);
            if ($val !== '') return $val;
        }
    }
    return $default;
}

// Locate Row Data
$current_row = 1;
$row_data = null;
while (($row = fgetcsv($handle)) !== FALSE) {
    if ($current_row == $row_index) {
        $row_data = $row;
        break;
    }
    $current_row++;
}
fclose($handle);

if (!$row_data) {
    echo json_encode(['success' => true, 'log' => "Skipped row $row_index: Empty row"]);
    exit;
}

// Flexible field extraction
$sku = get_col_val($row_data, $header_map, ['SKU', 'sku', 'product_sku']);
$name = get_col_val($row_data, $header_map, ['Name', 'name', 'Title', 'title', 'Product Name', 'post_title']);
$short_desc = get_col_val($row_data, $header_map, ['Short description', 'short_description', 'excerpt']);
$desc = get_col_val($row_data, $header_map, ['Description', 'description', 'content', 'post_content']);
$reg_price = get_col_val($row_data, $header_map, ['Regular price', 'regular_price', 'Price', 'price']);
$sale_price = get_col_val($row_data, $header_map, ['Sale price', 'sale_price']);
$stock_status = get_col_val($row_data, $header_map, ['In stock?', 'in_stock', 'stock_status'], '1');
$stock_qty = get_col_val($row_data, $header_map, ['Stock', 'stock', 'stock_qty'], ($stock_status == '1' ? '10' : '0'));
$published = get_col_val($row_data, $header_map, ['Published', 'published', 'status'], '1');
$categories_str = get_col_val($row_data, $header_map, ['Categories', 'categories', 'category']);
$images_str = get_col_val($row_data, $header_map, ['Images', 'images', 'image', 'image_url']);

// Smart name fallback if Name column is empty
if (empty($name)) {
    if (!empty($sku)) {
        $name = 'Product ' . $sku;
    } elseif (!empty($short_desc)) {
        $name = substr(trim(strip_tags($short_desc)), 0, 60);
    } elseif (!empty($desc)) {
        $name = substr(trim(strip_tags($desc)), 0, 60);
    } else {
        $name = 'Imported Product #' . $row_index;
    }
}

try {
    $status = ($published == '1' || strtolower($published) == 'published' || strtolower($published) == 'true') ? 'published' : 'draft';

    $slug = generate_slug($name);
    $check = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
    $check->execute([$slug]);
    if ($check->fetch()) $slug .= '-' . uniqid();
    
    if (empty($sku)) $sku = 'SKU-' . strtoupper(substr(md5($slug . '_' . $row_index), 0, 6));
    $check_sku = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
    $check_sku->execute([$sku]);
    if ($check_sku->fetch()) $sku .= '-' . uniqid();
    
    $cat_id = get_or_create_cat($pdo, $categories_str);
    
    $sale_price_val = (is_numeric($sale_price) && $sale_price > 0) ? (float)$sale_price : null;
    $reg_price_val = (is_numeric($reg_price) && $reg_price > 0) ? (float)$reg_price : 0;
    if ($reg_price_val == 0 && $sale_price_val > 0) $reg_price_val = $sale_price_val;
    
    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, slug, sku, description, short_description, price, sale_price, stock_qty, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$cat_id, $name, $slug, $sku, $desc, $short_desc, $reg_price_val, $sale_price_val, is_numeric($stock_qty) ? (int)$stock_qty : 0, $status]);
    $product_id = $pdo->lastInsertId();
    
    if ($cat_id) {
        $pdo->prepare("INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)")->execute([$product_id, $cat_id]);
    }
    
    $image_count = 0;
    $total_image_size = 0;
    if (!empty($images_str)) {
        $image_urls = explode(',', $images_str);
        $sort_order = 0;
        foreach ($image_urls as $img_url) {
            $img_url = trim($img_url);
            if (empty($img_url)) continue;
            
            $filename_without_ext = generate_slug($name) . '-' . uniqid();
            $target_dir = 'uploads/products/' . $sku;
            
            $img_data = download_img($img_url, $target_dir, $filename_without_ext);
            if ($img_data) {
                if ($sort_order === 0) {
                    $pdo->prepare("UPDATE products SET main_image = ? WHERE id = ?")->execute([$img_data['filepath'], $product_id]);
                } else {
                    $pdo->prepare("INSERT INTO product_images (product_id, image_path, thumb_path, sort_order) VALUES (?, ?, ?, ?)")->execute([$product_id, $img_data['filepath'], $img_data['thumbpath'], $sort_order]);
                }
                $sort_order++;
                $image_count++;
                $total_image_size += $img_data['size'];
            }
        }
    }
    
    echo json_encode([
        'success' => true, 
        'log' => "Imported Row $row_index: <b>$name</b> (SKU: $sku | Images: $image_count)",
        'images_downloaded' => $image_count,
        'image_size' => $total_image_size
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
