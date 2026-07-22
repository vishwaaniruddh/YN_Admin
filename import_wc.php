<?php
// admin/import_wc.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$csv_file = __DIR__ . '/wc-product-export-18-7-2026-1784314480505.csv';
$limit = 5;

if (!file_exists($csv_file)) {
    die("CSV file not found.\n");
}

function download_and_process_image($url, $target_dir, $filename_without_ext) {
    // Support spaces in URLs
    $url = str_replace(' ', '%20', $url);
    
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
        ]
    ]);
    $image_data = @file_get_contents($url, false, $context);
    if (!$image_data) {
        return false;
    }
    
    $absolute_target_dir = __DIR__ . '/' . ltrim($target_dir, '/');
    if (!is_dir($absolute_target_dir)) {
        mkdir($absolute_target_dir, 0755, true);
    }
    $thumb_dir = $absolute_target_dir . '/thumbs/';
    if (!is_dir($thumb_dir)) {
        mkdir($thumb_dir, 0755, true);
    }
    
    $ext = 'jpg';
    $url_path = parse_url($url, PHP_URL_PATH);
    if ($url_path && preg_match('/\.([a-zA-Z0-9]+)$/i', $url_path, $matches)) {
        $ext = strtolower($matches[1]);
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $ext = 'jpg';
        }
    }
    
    $filename = $filename_without_ext . '.' . $ext;
    $target_file = rtrim($absolute_target_dir, '/') . '/' . $filename;
    
    file_put_contents($target_file, $image_data);
    
    $thumb_filename = 'thumb_' . $filename;
    $thumb_target = rtrim($thumb_dir, '/') . '/' . $thumb_filename;
    
    if (!file_exists($thumb_target)) {
        generate_square_thumbnail($target_file, $thumb_target, 150);
    }
    
    return [
        'filepath' => rtrim(ltrim($target_dir, '/'), '/') . '/' . $filename,
        'thumbpath' => rtrim(ltrim($target_dir, '/'), '/') . '/thumbs/' . $thumb_filename
    ];
}

function get_or_create_category($pdo, $category_string) {
    // Example: "Jewellery > Necklaces, Bridal"
    // We take the first branch: "Jewellery > Necklaces"
    $branches = explode(',', $category_string);
    $primary_branch = trim($branches[0]);
    
    if (empty($primary_branch)) {
        return null;
    }
    
    $parts = explode('>', $primary_branch);
    $parent_id = null;
    $current_id = null;
    
    foreach ($parts as $part) {
        $cat_name = trim($part);
        if (empty($cat_name)) continue;
        
        $slug = generate_slug($cat_name);
        
        // Find if exists
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $current_id = $existing['id'];
        } else {
            // Create
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$cat_name, $slug, $parent_id]);
            $current_id = $pdo->lastInsertId();
        }
        $parent_id = $current_id; // Next part is child of this part
    }
    
    return $current_id; // Return deepest child of the first branch
}

$handle = fopen($csv_file, "r");
if ($handle === FALSE) {
    die("Could not open CSV.\n");
}

$headers = fgetcsv($handle);
$header_map = array_flip($headers);

$count = 0;
while (($row = fgetcsv($handle)) !== FALSE) {
    if ($count >= $limit) {
        break;
    }
    
    $type = $row[$header_map['Type']] ?? '';
    if (strtolower($type) !== 'simple' && strtolower($type) !== 'variable') {
        // Skip variations or other types for now, mostly we want products
        if (!empty($type)) continue; 
    }
    
    $name = $row[$header_map['Name']] ?? '';
    if (empty($name)) continue;
    
    $sku = $row[$header_map['SKU']] ?? '';
    $short_desc = $row[$header_map['Short description']] ?? '';
    $desc = $row[$header_map['Description']] ?? '';
    $reg_price = $row[$header_map['Regular price']] ?? 0;
    $sale_price = $row[$header_map['Sale price']] ?? null;
    $stock_status = $row[$header_map['In stock?']] ?? '1';
    $stock_qty = $row[$header_map['Stock']] ?? ($stock_status == '1' ? 10 : 0);
    $published = $row[$header_map['Published']] ?? '1';
    $status = $published == '1' ? 'published' : 'draft';
    
    $categories_str = $row[$header_map['Categories']] ?? '';
    $images_str = $row[$header_map['Images']] ?? '';
    
    $slug = generate_slug($name);
    // Ensure unique slug
    $check_stmt = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
    $check_stmt->execute([$slug]);
    if ($check_stmt->fetch()) {
        $slug .= '-' . uniqid();
    }
    
    if (empty($sku)) {
        $sku = 'SKU-' . strtoupper(substr(md5($slug), 0, 6));
    }
    $check_sku = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
    $check_sku->execute([$sku]);
    if ($check_sku->fetch()) {
        $sku .= '-' . uniqid();
    }
    
    echo "Importing: $name (SKU: $sku)\n";
    
    // Category
    $category_id = get_or_create_category($pdo, $categories_str);
    
    $sale_price_val = (is_numeric($sale_price) && $sale_price > 0) ? $sale_price : null;
    $reg_price_val = (is_numeric($reg_price) && $reg_price > 0) ? $reg_price : 0;
    
    if ($reg_price_val == 0 && $sale_price_val > 0) {
        $reg_price_val = $sale_price_val; // Fail safe
    }
    
    // Insert Product (no main_image yet)
    $stmt = $pdo->prepare("
        INSERT INTO products (category_id, name, slug, sku, description, short_description, price, sale_price, stock_qty, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $category_id,
        $name,
        $slug,
        $sku,
        $desc,
        $short_desc,
        $reg_price_val,
        $sale_price_val,
        $stock_qty ?: 0,
        $status
    ]);
    
    $product_id = $pdo->lastInsertId();
    
    // Images
    $main_image_path = null;
    if (!empty($images_str)) {
        $image_urls = explode(',', $images_str);
        $sort_order = 0;
        
        foreach ($image_urls as $img_url) {
            $img_url = trim($img_url);
            if (empty($img_url)) continue;
            
            $filename_without_ext = generate_slug($name) . '-' . uniqid();
            $target_dir = 'uploads/products/' . $slug;
            
            echo "  Downloading image: $img_url ... ";
            $img_data = download_and_process_image($img_url, $target_dir, $filename_without_ext);
            
            if ($img_data) {
                echo "Success.\n";
                if ($sort_order === 0) {
                    $main_image_path = $img_data['filepath'];
                    $pdo->prepare("UPDATE products SET main_image = ? WHERE id = ?")->execute([$main_image_path, $product_id]);
                } else {
                    $pdo->prepare("INSERT INTO product_images (product_id, image_path, thumb_path, sort_order) VALUES (?, ?, ?, ?)")
                        ->execute([$product_id, $img_data['filepath'], $img_data['thumbpath'], $sort_order]);
                }
                $sort_order++;
            } else {
                echo "Failed.\n";
            }
        }
    }
    
    $count++;
}

fclose($handle);
echo "\nImport completed. $count products imported.\n";
