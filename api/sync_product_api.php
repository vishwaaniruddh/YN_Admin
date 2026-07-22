<?php
// admin/api/sync_product_api.php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'fetch') {
    $sku = trim($_GET['sku'] ?? '');
    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

    // Lookup SKU from product_id if not provided directly
    if (empty($sku) && $product_id > 0) {
        $stmt = $pdo->prepare("SELECT sku FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $sku = $stmt->fetchColumn() ?: '';
    }

    if (empty($sku)) {
        echo json_encode(['success' => false, 'message' => 'Product SKU is required to fetch details.']);
        exit;
    }

    // 1. Fetch Local Product
    $local_product = null;
    $local_gallery = [];
    if ($product_id > 0) {
        $stmtLocal = $pdo->prepare("SELECT id, name, slug, sku, price, main_image, description, short_description FROM products WHERE id = ?");
        $stmtLocal->execute([$product_id]);
        $local_product = $stmtLocal->fetch(PDO::FETCH_ASSOC);

        $stmtGal = $pdo->prepare("SELECT id, image_path, thumb_path, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
        $stmtGal->execute([$product_id]);
        $local_gallery = $stmtGal->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtLocal = $pdo->prepare("SELECT id, name, slug, sku, price, main_image, description, short_description FROM products WHERE sku = ?");
        $stmtLocal->execute([$sku]);
        $local_product = $stmtLocal->fetch(PDO::FETCH_ASSOC);

        if ($local_product) {
            $stmtGal = $pdo->prepare("SELECT id, image_path, thumb_path, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
            $stmtGal->execute([$local_product['id']]);
            $local_gallery = $stmtGal->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // 2. Fetch External API Data
    $external_url = "https://srishringarr.com/API/v1/product-detail-sku.php?sku=" . urlencode($sku);
    $ch = curl_init($external_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $raw_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($raw_response)) {
        echo json_encode([
            'success' => false,
            'message' => "Failed to connect to SriShringarr API (HTTP $http_code)."
        ]);
        exit;
    }

    $api_json = json_decode($raw_response, true);
    if (!isset($api_json['status']) || $api_json['status'] !== 'success' || empty($api_json['data'])) {
        echo json_encode([
            'success' => false,
            'message' => "No product details found on SriShringarr API for SKU '$sku'."
        ]);
        exit;
    }

    $api_data = $api_json['data'];
    $external_name = $api_data['name'] ?? '';
    $external_desc = $api_data['product_desc'] ?? '';
    $external_main_image = $api_data['details']['image_path'] ?? ($api_data['images'][0] ?? '');
    $external_images = is_array($api_data['images'] ?? null) ? $api_data['images'] : [];

    echo json_encode([
        'success' => true,
        'local' => [
            'id' => $local_product['id'] ?? null,
            'sku' => $sku,
            'name' => $local_product['name'] ?? '',
            'description' => $local_product['description'] ?? '',
            'main_image' => $local_product['main_image'] ?? '',
            'gallery' => $local_gallery
        ],
        'external' => [
            'sku' => $sku,
            'name' => $external_name,
            'description' => $external_desc,
            'main_image' => $external_main_image,
            'images' => $external_images
        ]
    ]);
    exit;
}

if ($action === 'apply') {
    // Read input (handles both JSON and POST form data)
    $input_json = file_get_contents('php://input');
    $post_data = json_decode($input_json, true);
    if (!is_array($post_data)) {
        $post_data = $_POST;
    }

    $product_id = (int)($post_data['product_id'] ?? 0);
    $sync_name = !empty($post_data['sync_name']);
    $sync_description = !empty($post_data['sync_description']);
    $sync_images = !empty($post_data['sync_images']);
    $external = $post_data['external'] ?? [];

    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
        exit;
    }

    // Lookup product
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found in local database.']);
        exit;
    }

    $sku = $product['sku'];

    try {
        $pdo->beginTransaction();

        $updated_name = $product['name'];
        $updated_slug = $product['slug'];
        $updated_desc = $product['description'];
        $updated_main_image = $product['main_image'];

        // 1. Sync Product Name & Slug
        if ($sync_name && !empty($external['name'])) {
            $updated_name = trim($external['name']);
            $new_slug = generate_slug($updated_name);
            
            // Ensure slug uniqueness (except this product)
            $check_slug = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ? AND id != ?");
            $check_slug->execute([$new_slug, $product_id]);
            if ($check_slug->fetchColumn() > 0) {
                $new_slug .= '-' . time();
            }
            $updated_slug = $new_slug;
        }

        // 2. Sync Description
        if ($sync_description && !empty($external['description'])) {
            $updated_desc = trim($external['description']);
        }

        // 3. Sync Images
        $new_main_image_path = $updated_main_image;
        if ($sync_images) {
            // Main Image
            if (!empty($external['main_image'])) {
                $download_main = download_remote_image($external['main_image'], "uploads/products/{$sku}", "main");
                if ($download_main && isset($download_main['filepath'])) {
                    $new_main_image_path = $download_main['filepath'];
                }
            }

            // Gallery Images
            if (!empty($external['images']) && is_array($external['images'])) {
                // Clear existing gallery items in DB
                $stmt_del_gal = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
                $stmt_del_gal->execute([$product_id]);

                $ins_gal = $pdo->prepare("INSERT INTO product_images (product_id, image_path, thumb_path, sort_order) VALUES (?, ?, ?, ?)");
                $sort_order = 1;

                foreach ($external['images'] as $img_url) {
                    if (empty($img_url)) continue;
                    $download_gal = download_remote_image($img_url, "uploads/products/{$sku}", "gallery");
                    if ($download_gal && isset($download_gal['filepath'])) {
                        $ins_gal->execute([
                            $product_id,
                            $download_gal['filepath'],
                            $download_gal['thumbpath'],
                            $sort_order++
                        ]);
                    }
                }
            }
        }

        // Execute product table update
        $sql = "UPDATE products SET name = ?, slug = ?, description = ?, main_image = ? WHERE id = ?";
        $stmt_upd = $pdo->prepare($sql);
        $stmt_upd->execute([
            $updated_name,
            $updated_slug,
            $updated_desc,
            $new_main_image_path,
            $product_id
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Product details successfully updated from SriShringarr API!',
            'updated' => [
                'name' => $updated_name,
                'slug' => $updated_slug,
                'description' => $updated_desc,
                'main_image' => $new_main_image_path
            ]
        ]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
