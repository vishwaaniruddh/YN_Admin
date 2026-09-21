<?php
// admin/product-edit.php
$page_title = "Edit Product";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$message = '';
$message_type = 'success';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    redirect('products.php');
}

// 1. Fetch Product, Categories and Gallery Images
try {
    // Product details
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        redirect('products.php');
    }

    // Get associated categories
    $pc_stmt = $pdo->prepare("SELECT category_id FROM product_categories WHERE product_id = ?");
    $pc_stmt->execute([$product_id]);
    $product_categories = $pc_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Categories
    $categories_raw = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
    $categories = get_category_tree($categories_raw);

    // Gallery images
    $gal_stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
    $gal_stmt->execute([$product_id]);
    $gallery_images = $gal_stmt->fetchAll();

} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = "error";
}

// 2. Handle Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    // SKU is non-editable in edit mode; preserve product's existing SKU
    $sku = $product['sku'];
    $price = (float)($_POST['price'] ?? 0.0);
    $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $stock_qty = (int)($_POST['stock_qty'] ?? 0);
    $category_ids = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? $_POST['category_ids'] : [];
    $description = trim($_POST['description'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $status = $_POST['status'] === 'published' ? 'published' : 'draft';
    $deleted_gallery_ids = trim($_POST['deleted_gallery_ids'] ?? '');

    if (empty($name) || empty($sku) || $price <= 0) {
        $message = "Please fill in all required fields (Product Name, SKU, and Price).";
        $message_type = "error";
    } else {
        if (empty($slug)) {
            $slug = generate_slug($name);
        } else {
            $slug = generate_slug($slug);
        }

        try {
            // Check unique SKU (except this product)
            $check_sku = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ? AND id != ?");
            $check_sku->execute([$sku, $product_id]);
            
            // Check unique Slug (except this product)
            $check_slug = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ? AND id != ?");
            $check_slug->execute([$slug, $product_id]);

            if ($check_sku->fetchColumn() > 0) {
                $message = "Product SKU already exists. Please use a unique SKU.";
                $message_type = "error";
            } elseif ($check_slug->fetchColumn() > 0) {
                $slug .= '-' . time();
            }

            if ($message_type !== 'error') {
                // Begin Transaction
                $pdo->beginTransaction();

                // Handle Main Image replacement via file upload OR selected available image
                $main_image_path = $product['main_image'];
                $old_main_image_path = $product['main_image'];
                if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                    $upload = upload_image($_FILES['main_image'], 'uploads/products/' . $sku, 'main');
                    if (is_array($upload) && isset($upload['filepath'])) {
                        // Delete previous main image from disk if custom file
                        if ($product['main_image'] && file_exists(__DIR__ . '/../' . $product['main_image']) && strpos($product['main_image'], 'ai_') === false) {
                            unlink(__DIR__ . '/../' . $product['main_image']);
                            
                            // Delete thumbnail of main image if exists
                            $path_parts = pathinfo($product['main_image']);
                            $thumb_file = $path_parts['dirname'] . '/thumbs/thumb_' . $path_parts['basename'];
                            if (file_exists(__DIR__ . '/../' . $thumb_file)) {
                                unlink(__DIR__ . '/../' . $thumb_file);
                            }
                        }
                        $main_image_path = $upload['filepath'];
                    } elseif (is_array($upload) && isset($upload['error'])) {
                        $message = $upload['error'];
                        $message_type = 'error';
                        throw new Exception($upload['error']);
                    }
                } elseif (!empty($_POST['selected_main_image_path'])) {
                    $selected_path = trim($_POST['selected_main_image_path']);
                    if ($selected_path !== '' && $selected_path !== $old_main_image_path) {
                        $main_image_path = $selected_path;

                        // Exchange position: old main image moves to gallery slot of selected image
                        if (!empty($old_main_image_path)) {
                            // Find gallery image entry matching selected_path
                            $chk_stmt = $pdo->prepare("SELECT id, image_path, thumb_path FROM product_images WHERE product_id = ? AND image_path = ?");
                            $chk_stmt->execute([$product_id, $selected_path]);
                            $existing_gal_img = $chk_stmt->fetch();

                            // Determine thumbnail for old main image if available
                            $old_thumb = $old_main_image_path;
                            $path_parts = pathinfo($old_main_image_path);
                            $possible_thumb = ($path_parts['dirname'] ?? '') . '/thumbs/thumb_' . ($path_parts['basename'] ?? '');
                            if (file_exists(__DIR__ . '/../' . $possible_thumb)) {
                                $old_thumb = $possible_thumb;
                            }

                            if ($existing_gal_img) {
                                // Swap: update gallery image record to point to old main image
                                $swap_stmt = $pdo->prepare("UPDATE product_images SET image_path = ?, thumb_path = ? WHERE id = ? AND product_id = ?");
                                $swap_stmt->execute([$old_main_image_path, $old_thumb, $existing_gal_img['id'], $product_id]);
                            } else {
                                // If not found in product_images, insert old main image into product_images
                                $sort_stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ?");
                                $sort_stmt->execute([$product_id]);
                                $max_sort = (int)$sort_stmt->fetchColumn();

                                $ins_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, thumb_path, sort_order) VALUES (?, ?, ?, ?)");
                                $ins_stmt->execute([$product_id, $old_main_image_path, $old_thumb, $max_sort + 1]);
                            }
                        }
                    }
                }

                // Update gallery image weights / sort order
                if (!empty($_POST['image_weights']) && is_array($_POST['image_weights'])) {
                    $stmt_weight = $pdo->prepare("UPDATE product_images SET sort_order = ? WHERE id = ? AND product_id = ?");
                    foreach ($_POST['image_weights'] as $img_id => $weight_val) {
                        $stmt_weight->execute([(int)$weight_val, (int)$img_id, $product_id]);
                    }
                }

                // Delete selected gallery images
                if (!empty($deleted_gallery_ids)) {
                    $del_ids = array_map('intval', explode(',', $deleted_gallery_ids));
                    foreach ($del_ids as $del_id) {
                        if ($del_id > 0) {
                            // Get paths to delete files from disk
                            $stmt_get_img = $pdo->prepare("SELECT image_path, thumb_path FROM product_images WHERE id = ? AND product_id = ?");
                            $stmt_get_img->execute([$del_id, $product_id]);
                            $img_info = $stmt_get_img->fetch();
                            
                            if ($img_info) {
                                if (file_exists(__DIR__ . '/../' . $img_info['image_path'])) {
                                    unlink(__DIR__ . '/../' . $img_info['image_path']);
                                }
                                if (file_exists(__DIR__ . '/../' . $img_info['thumb_path'])) {
                                    unlink(__DIR__ . '/../' . $img_info['thumb_path']);
                                }
                            }
                            
                            // Delete from DB
                            $stmt_del_img = $pdo->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?");
                            $stmt_del_img->execute([$del_id, $product_id]);
                        }
                    }
                }

                // Add new gallery images
                if (isset($_FILES['gallery_images']) && !empty($_FILES['gallery_images']['name'][0])) {
                    $gallery_files = $_FILES['gallery_images'];
                    $file_count = count($gallery_files['name']);
                    
                    // Get next sort order
                    $sort_stmt = $pdo->prepare("SELECT MAX(sort_order) FROM product_images WHERE product_id = ?");
                    $sort_stmt->execute([$product_id]);
                    $max_sort = (int)$sort_stmt->fetchColumn();

                    $ins_gallery = $pdo->prepare("INSERT INTO product_images (product_id, image_path, thumb_path, sort_order) VALUES (?, ?, ?, ?)");
                    
                    for ($i = 0; $i < $file_count; $i++) {
                        $single_file = [
                            'name' => $gallery_files['name'][$i],
                            'type' => $gallery_files['type'][$i],
                            'tmp_name' => $gallery_files['tmp_name'][$i],
                            'error' => $gallery_files['error'][$i],
                            'size' => $gallery_files['size'][$i]
                        ];

                        if ($single_file['error'] === UPLOAD_ERR_OK) {
                            $upload_gal = upload_image($single_file, 'uploads/products/' . $sku);
                            if (is_array($upload_gal) && isset($upload_gal['filepath'])) {
                                $ins_gallery->execute([
                                    $product_id,
                                    $upload_gal['filepath'],
                                    $upload_gal['thumbpath'],
                                    ($max_sort + $i + 1)
                                ]);
                            }
                        }
                    }
                }

                $primary_category_id = !empty($category_ids) ? (int)$category_ids[0] : null;

                // Update product table
                $sql = "UPDATE products SET 
                    category_id = ?, name = ?, slug = ?, sku = ?, description = ?, 
                    short_description = ?, price = ?, sale_price = ?, stock_qty = ?, 
                    is_featured = ?, status = ?, main_image = ? 
                    WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $primary_category_id,
                    $name,
                    $slug,
                    $sku,
                    $description,
                    $short_description,
                    $price,
                    $sale_price,
                    $stock_qty,
                    $is_featured,
                    $status,
                    $main_image_path,
                    $product_id
                ]);

                // Update Categories
                $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$product_id]);
                if (!empty($category_ids)) {
                    $cat_stmt = $pdo->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
                    foreach ($category_ids as $cat_id) {
                        $cat_stmt->execute([$product_id, (int)$cat_id]);
                    }
                }

                $pdo->commit();

                // Automatically invalidate cache on product update
                if (function_exists('purge_cache')) {
                    purge_cache();
                }

                // Reload the page with success message
                redirect("product-edit.php?id=$product_id&message=updated");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Check redirect message
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'updated') {
        $message = "Product successfully updated.";
        $message_type = "success";
    } elseif ($_GET['message'] === 'synced') {
        $message = "Product details (Name, Description & Images) successfully synced from SriShringarr API!";
        $message_type = "success";
    }
}

// Re-fetch product details and updated gallery items
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    $pc_stmt = $pdo->prepare("SELECT category_id FROM product_categories WHERE product_id = ?");
    $pc_stmt->execute([$product_id]);
    $product_categories = $pc_stmt->fetchAll(PDO::FETCH_COLUMN);

    $gal_stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
    $gal_stmt->execute([$product_id]);
    $gallery_images = $gal_stmt->fetchAll();
} catch (PDOException $e) {
    // Fail silently in display, showing message
}
?>

<div class="dashboard-header-banner" style="margin-bottom: 24px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting" style="flex-wrap: wrap; gap: 10px;">
            <a href="products.php" class="shadcn-btn shadcn-btn-outline" style="height: 32px; width: 32px; padding: 0;" title="Back to products">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h1>Edit Product</h1>
            <span class="shadcn-badge" style="font-size: 11px; padding: 3px 8px;">
                <i class="fa-solid fa-hashtag" style="margin-right: 2px;"></i> ID #<?php echo $product['id']; ?>
            </span>
            <span class="shadcn-badge" style="font-size: 11px; padding: 3px 8px; font-family: monospace;">
                <?php echo sanitize_html($product['sku']); ?>
            </span>
        </div>
        <p class="dashboard-subtitle" style="max-width: 700px;">
            <?php echo sanitize_html($product['name']); ?>
        </p>
    </div>
    <div class="dashboard-actions">
        <button type="button" class="shadcn-btn shadcn-btn-outline" onclick="openSyncModal()">
            <i class="fa-solid fa-arrows-rotate"></i> Sync API
        </button>
        <a href="product-add.php" class="shadcn-btn shadcn-btn-outline">
            <i class="fa-solid fa-plus"></i> Add New
        </a>
        <a href="products.php" class="shadcn-btn shadcn-btn-outline">
            <i class="fa-solid fa-boxes-stacked"></i> All Products
        </a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<form action="product-edit.php?id=<?php echo $product['id']; ?>" method="POST" enctype="multipart/form-data" id="edit_product_form">
    <!-- Hidden input to track gallery item deletions -->
    <input type="hidden" name="deleted_gallery_ids" id="deleted_gallery_ids" value="">

    <div class="wp-editor-columns" style="display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap;">
        
        <!-- Left Main Content Column -->
        <div class="main-column" style="flex: 1 1 580px; min-width: 320px;">
            
            <!-- Title and Description Card -->
            <div class="shadcn-card" style="margin-bottom: 24px;">
                <div class="shadcn-card-header">
                    <h2 class="shadcn-card-title">
                        <i class="fa-solid fa-pen-to-square" style="color: #71717a;"></i>
                        Product Information
                    </h2>
                </div>
                <div class="shadcn-card-padded">
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
                            <label for="p_name" style="margin-bottom: 0;">
                                Product Name <span style="color: #ef4444;">*</span>
                            </label>
                            <button type="button" onclick="aiGenerateNames()" id="aiNamesBtn" class="shadcn-btn shadcn-btn-outline" style="font-size: 11px; padding: 0 8px; height: 26px;">
                                <i class="fa-solid fa-wand-magic-sparkles" style="color: #71717a;"></i> AI Suggest Names
                            </button>
                        </div>
                        <input type="text" name="name" id="p_name" class="form-control" value="<?php echo sanitize_html($product['name']); ?>" required style="width: 100%; font-size: 14px;">
                        <div id="aiNamesResult" style="display: none; margin-top: 8px; padding: 10px; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px;">
                            <p style="font-size: 11px; font-weight: 600; color: #71717a; margin-bottom: 6px;">Click to apply suggested name:</p>
                            <div id="aiNamesList" style="display: flex; flex-direction: column; gap: 4px;"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="p_slug">Slug (URL identifier)</label>
                        <input type="text" name="slug" id="p_slug" class="form-control" value="<?php echo sanitize_html($product['slug']); ?>" style="width: 100%; font-family: monospace; color: #52525b;">
                        <span style="font-size: 11.5px; color: #71717a; margin-top: 4px; display: block;">Leave blank to automatically regenerate from product name.</span>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label for="p_desc" style="margin-bottom: 0;">Detailed Description</label>
                                <span id="desc_length_counter" style="font-size: 11.5px; color: #71717a; font-weight: 500;">0 words &bull; 0 chars</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 11px; color: #71717a;">Words: <input type="number" id="aiDescMaxWords" value="100" min="10" max="500" class="form-control" style="width: 55px; height: 24px; padding: 1px 4px; font-size: 11px; display: inline-block;"></span>
                                <button type="button" onclick="aiGenerateDescription()" id="aiDescBtn" class="shadcn-btn shadcn-btn-outline" style="font-size: 11px; padding: 0 8px; height: 26px;">
                                    <i class="fa-solid fa-wand-magic-sparkles" style="color: #71717a;"></i> AI Generate Description
                                </button>
                            </div>
                        </div>
                        <textarea name="description" id="p_desc" class="form-control auto-expand-textarea" rows="5" style="width: 100%; line-height: 1.6; resize: vertical; overflow-y: hidden; box-sizing: border-box;"><?php echo sanitize_html($product['description']); ?></textarea>
                        
                        <div id="aiLoading" style="display: none; align-items: center; gap: 6px; padding: 8px; font-size: 12px; color: #71717a; margin-top: 6px;">
                            <i class="fa-solid fa-spinner fa-spin" style="color: #09090b;"></i> AI is generating description...
                        </div>

                        <div id="aiDescResult" style="display: none; margin-top: 8px; padding: 10px; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px;">
                            <p style="font-size: 11px; font-weight: 600; color: #71717a; margin-bottom: 4px;">Generated Description Preview:</p>
                            <textarea id="aiDescTextarea" rows="6" class="form-control" style="width: 100%; min-height: 140px; margin-bottom: 8px; font-size: 13px; line-height: 1.5;"></textarea>
                            <button type="button" onclick="applyAiDescription()" id="applyDescBtn" class="shadcn-btn shadcn-btn-primary" style="font-size: 11px; height: 28px; padding: 0 10px;">
                                Apply to Description Field
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Short Description Card -->
            <div class="shadcn-card" style="margin-bottom: 24px;">
                <div class="shadcn-card-header" style="flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <h2 class="shadcn-card-title">
                            <i class="fa-solid fa-align-left" style="color: #71717a;"></i>
                            Short Description &amp; Highlights
                        </h2>
                        <span id="short_desc_length_counter" style="font-size: 11.5px; color: #71717a; font-weight: 500;">0 words &bull; 0 chars</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 11px; color: #71717a;">Words: <input type="number" id="aiShortDescMaxWords" value="50" min="10" max="250" class="form-control" style="width: 55px; height: 24px; padding: 1px 4px; font-size: 11px; display: inline-block;"></span>
                        <button type="button" onclick="aiGenerateShortDescription()" id="aiShortDescBtn" class="shadcn-btn shadcn-btn-outline" style="font-size: 11px; padding: 0 8px; height: 26px;">
                            <i class="fa-solid fa-wand-magic-sparkles" style="color: #71717a;"></i> AI Generate Short Description
                        </button>
                    </div>
                </div>
                <div class="shadcn-card-padded">
                    <div class="form-group" style="margin-bottom: 0;">
                        <textarea name="short_description" id="p_short_desc" class="form-control auto-expand-textarea" rows="3" style="width: 100%; line-height: 1.5; resize: vertical; overflow-y: hidden; box-sizing: border-box;"><?php echo sanitize_html($product['short_description']); ?></textarea>
                        <div id="aiShortDescLoading" style="display: none; align-items: center; gap: 6px; padding: 8px; font-size: 12px; color: #71717a; margin-top: 6px;">
                            <i class="fa-solid fa-spinner fa-spin" style="color: #09090b;"></i> AI is generating short description &amp; highlights...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing & Inventory Details Card -->
            <div class="shadcn-card" style="margin-bottom: 24px;">
                <div class="shadcn-card-header">
                    <h2 class="shadcn-card-title">
                        <i class="fa-solid fa-tags" style="color: #71717a;"></i>
                        Pricing &amp; Inventory
                    </h2>
                </div>
                <div class="shadcn-card-padded">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="p_price">
                                Regular Price (₹) <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="number" step="0.01" name="price" id="p_price" class="form-control" value="<?php echo (float)$product['price']; ?>" required style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="p_sale_price">Sale Price (₹)</label>
                            <input type="number" step="0.01" name="sale_price" id="p_sale_price" class="form-control" value="<?php echo $product['sale_price'] ? (float)$product['sale_price'] : ''; ?>" style="width: 100%;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="p_sku">
                                SKU (Stock Keeping Unit) <span style="font-size: 11px; color: #71717a; font-weight: normal;">(Permanent / Non-editable)</span>
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" name="sku" id="p_sku" class="form-control" value="<?php echo sanitize_html($product['sku']); ?>" readonly style="flex: 1; background-color: #f4f4f5; color: #52525b; cursor: not-allowed; font-family: monospace; font-weight: 500;">
                                <button type="button" class="shadcn-btn shadcn-btn-outline" onclick="openSyncModal()" title="Fetch product details from SriShringarr API" style="white-space: nowrap;">
                                    <i class="fa-solid fa-arrows-rotate"></i> Sync API
                                </button>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="p_stock">Stock Quantity</label>
                            <input type="number" name="stock_qty" id="p_stock" class="form-control" value="<?php echo (int)$product['stock_qty']; ?>" min="0" style="width: 100%;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Gallery (Multi-Image) Card -->
            <div class="shadcn-card" style="margin-bottom: 24px;">
                <div class="shadcn-card-header">
                    <h2 class="shadcn-card-title">
                        <i class="fa-solid fa-images" style="color: #71717a;"></i>
                        Product Gallery Images
                    </h2>
                </div>
                <div class="shadcn-card-padded">
                    <!-- Current Gallery Items -->
                    <?php if (!empty($gallery_images)): ?>
                        <p style="font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 8px;">Current Gallery Images:</p>
                        <div class="gallery-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px; margin-bottom: 20px;">
                            <?php foreach ($gallery_images as $gidx => $gimg): ?>
                                <div class="gallery-item" id="gallery_item_<?php echo $gimg['id']; ?>" data-img-id="<?php echo $gimg['id']; ?>" data-img-path="<?php echo sanitize_html($gimg['image_path']); ?>" data-thumb-path="<?php echo sanitize_html($gimg['thumb_path'] ?: $gimg['image_path']); ?>" style="display: flex; flex-direction: column; align-items: center; background: #ffffff; padding: 8px; border: 1px solid #e4e4e7; border-radius: 6px;">
                                    <div style="position: relative; width: 100%; aspect-ratio: 1/1; overflow: hidden; border-radius: 4px;">
                                        <img src="<?php echo sanitize_html(get_product_image_url($gimg['thumb_path'] ?: $gimg['image_path'])); ?>" alt="Gallery Image" style="width: 100%; height: 100%; object-fit: cover;">
                                        <div class="gallery-item-delete" onclick="markGalleryImageForDeletion(<?php echo $gimg['id']; ?>)" title="Remove this image">
                                            <i class="fa-solid fa-xmark"></i>
                                        </div>
                                    </div>
                                    <div style="margin-top: 6px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 4px;" title="Set rendering weight order on frontend">
                                        <span style="font-size: 10px; color: #71717a; font-weight: 600;">Weight:</span>
                                        <input type="number" min="0" name="image_weights[<?php echo $gimg['id']; ?>]" value="<?php echo (int)($gimg['sort_order'] ?? ($gidx + 1)); ?>" class="form-control" style="width: 48px; height: 24px; padding: 1px 4px; font-size: 11px; text-align: center;">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="image-upload-wrapper" style="border: 2px dashed #e4e4e7; background: #fafafa; border-radius: 8px; padding: 28px 16px; text-align: center; cursor: pointer; transition: all 0.2s ease;">
                        <div style="width: 44px; height: 44px; border-radius: 10px; background: #ffffff; border: 1px solid #e4e4e7; display: inline-flex; align-items: center; justify-content: center; color: #71717a; font-size: 20px; margin-bottom: 10px;">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </div>
                        <p style="font-size: 13px; font-weight: 500; color: #09090b; margin: 0 0 4px 0;">Click or drag &amp; drop to upload additional gallery images</p>
                        <p style="font-size: 11.5px; color: #71717a; margin: 0;">PNG, JPG, WEBP up to 5MB each</p>
                        <input type="file" name="gallery_images[]" id="gallery_input" multiple accept="image/*">
                    </div>
                    <!-- Live Gallery Previews (New uploads) -->
                    <div class="gallery-grid" id="gallery_preview_grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; margin-top: 16px;"></div>
                </div>
            </div>

            <!-- AI Image Studio Card -->
            <div class="shadcn-card" style="margin-bottom: 24px;">
                <div class="shadcn-card-header">
                    <h2 class="shadcn-card-title">
                        <i class="fa-solid fa-wand-magic-sparkles" style="color: #71717a;"></i>
                        AI Image Studio
                    </h2>
                </div>
                <div class="shadcn-card-padded">
                    <p style="font-size: 12.5px; color: #71717a; margin-top: 0; margin-bottom: 15px;">Generate AI fashion model photos wearing this exact product.</p>

                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <!-- Face Reference Models -->
                        <div>
                            <label style="font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 6px; display: block;">Model Face (Optional)</label>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <label class="ai-model-picker">
                                    <input type="radio" name="ai_model_face" value="" checked class="ai-radio-hidden">
                                    <div class="ai-model-box" style="width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; cursor: pointer;">
                                        <span style="font-size: 10px; color: #71717a; font-weight: 600;">NONE</span>
                                    </div>
                                </label>
                                <?php
                                $db_ai_models = [];
                                try {
                                    $db_ai_models = $pdo->query("SELECT * FROM ai_models WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Exception $e) {}

                                if (!empty($db_ai_models)):
                                    foreach ($db_ai_models as $dbm):
                                ?>
                                <label class="ai-model-picker" style="position: relative;" title="<?php echo sanitize_html($dbm['name']); ?>">
                                    <input type="radio" name="ai_model_face" value="<?php echo sanitize_html($dbm['image_path']); ?>" data-shot="<?php echo sanitize_html($dbm['shot_type'] ?? 'Full Body'); ?>" data-hair="<?php echo sanitize_html($dbm['hair_style'] ?? 'As per product'); ?>" class="ai-radio-hidden">
                                    <div class="ai-model-box" style="width: 56px; height: 56px; border: 1px solid #e4e4e7; border-radius: 6px; overflow: hidden; cursor: pointer;">
                                        <img src="<?php echo sanitize_html($dbm['image_path']); ?>" alt="<?php echo sanitize_html($dbm['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                </label>
                                <?php
                                    endforeach;
                                else:
                                    for ($i = 1; $i <= 5; $i++):
                                ?>
                                <label class="ai-model-picker" style="position: relative;">
                                    <input type="radio" name="ai_model_face" value="assets/models/model_<?= $i ?>.png" class="ai-radio-hidden">
                                    <div class="ai-model-box" style="width: 56px; height: 56px; border: 1px solid #e4e4e7; border-radius: 6px; overflow: hidden; cursor: pointer;" title="Model <?= $i ?>">
                                        <img src="assets/models/model_<?= $i ?>.png" alt="Model <?= $i ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                </label>
                                <?php
                                    endfor;
                                endif;
                                ?>
                            </div>
                        </div>

                        <!-- Background Presets -->
                        <div>
                            <label style="font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 6px; display: block;">Background / Props Preset</label>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;" id="bg_preset_container">
                                <?php
                                $bgPresets = [
                                    'Palace' => 'elegant royal palace with marble pillars and chandeliers',
                                    'Beach' => 'golden hour beach with soft waves and sunset sky',
                                    'Studio' => 'clean professional photography studio with soft gradient backdrop',
                                    'Mountains' => 'majestic Himalayan mountains with misty peaks',
                                    'Lake' => 'serene lake with reflections and lush greenery',
                                    'Garden' => 'blooming flower garden with roses and jasmine',
                                    'Haveli' => 'traditional Rajasthani haveli with jharokha windows',
                                    'City Night' => 'modern city skyline at night with bokeh lights'
                                ];
                                $first = true;
                                foreach ($bgPresets as $label => $promptPart):
                                ?>
                                <label class="ai-bg-picker">
                                    <input type="radio" name="ai_bg_preset" value="<?= htmlspecialchars($promptPart) ?>" <?= $first ? 'checked' : '' ?> class="ai-radio-hidden">
                                    <div class="ai-bg-pill"><?= $label ?></div>
                                </label>
                                <?php $first = false; endforeach; ?>
                            </div>
                            <input type="text" id="ai_bg_custom" class="form-control" style="margin-top: 8px; width: 100%; font-size: 12px;" value="elegant royal palace with marble pillars and chandeliers" placeholder="Describe background and props...">
                        </div>

                        <!-- Shot & Hair Controls -->
                        <?php
                        $db_shot_types = [];
                        $db_hair_styles = [];
                        try {
                            $db_shot_types = $pdo->query("SELECT * FROM ai_shot_types WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
                            $db_hair_styles = $pdo->query("SELECT * FROM ai_hair_styles WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {}
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 6px; display: block;">Shot Type Master</label>
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <?php
                                    if (!empty($db_shot_types)):
                                        $sIdx = 0;
                                        foreach ($db_shot_types as $st):
                                    ?>
                                        <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #09090b;" title="<?php echo sanitize_html($st['prompt_text']); ?>">
                                            <input type="radio" name="ai_shot_type" value="<?php echo sanitize_html($st['prompt_text']); ?>" data-name="<?php echo sanitize_html($st['name']); ?>" <?php echo $sIdx === 0 ? 'checked' : ''; ?> style="accent-color: #09090b;">
                                            <?php echo sanitize_html($st['name']); ?>
                                        </label>
                                    <?php
                                        $sIdx++;
                                        endforeach;
                                    else:
                                    ?>
                                        <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #09090b;"><input type="radio" name="ai_shot_type" value="close-up portrait shot focusing on the face and details" data-name="Close-up Portrait" style="accent-color: #09090b;"> Close-up Portrait</label>
                                        <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #09090b;"><input type="radio" name="ai_shot_type" value="half body shot from waist up, showing torso and face" data-name="Half Body" style="accent-color: #09090b;"> Half Body</label>
                                        <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #09090b;"><input type="radio" name="ai_shot_type" value="full body head-to-toe shot showing the complete outfit/jewelry look" data-name="Full Body" checked style="accent-color: #09090b;"> Full Body</label>
                                        <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #09090b;"><input type="radio" name="ai_shot_type" value="shot from behind showing the back design and details" data-name="Back View" style="accent-color: #09090b;"> Back View</label>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label style="font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 6px; display: block;">Hair Style Master</label>
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <?php
                                    if (!empty($db_hair_styles)):
                                        $hIdx = 0;
                                        foreach ($db_hair_styles as $hs):
                                    ?>
                                        <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #09090b;" title="<?php echo sanitize_html($hs['prompt_text']); ?>">
                                            <input type="radio" name="ai_hair_style" value="<?php echo sanitize_html($hs['prompt_text']); ?>" data-name="<?php echo sanitize_html($hs['name']); ?>" <?php echo $hIdx === 0 ? 'checked' : ''; ?> style="accent-color: #09090b;">
                                            <?php echo sanitize_html($hs['name']); ?>
                                        </label>
                                    <?php
                                        $hIdx++;
                                        endforeach;
                                    else:
                                    ?>
                                        <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #09090b;"><input type="radio" name="ai_hair_style" value="open flowing hair with soft waves" data-name="Open Flowing" style="accent-color: #09090b;"> Open Flowing</label>
                                        <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #09090b;"><input type="radio" name="ai_hair_style" value="neatly tied bun with gajra flowers" data-name="Tied / Bun" style="accent-color: #09090b;"> Tied / Bun</label>
                                        <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #09090b;"><input type="radio" name="ai_hair_style" value="traditional long braided hair" data-name="Traditional Braid" style="accent-color: #09090b;"> Traditional Braid</label>
                                        <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #09090b;"><input type="radio" name="ai_hair_style" value="" data-name="As per product" checked style="accent-color: #09090b;"> Default</label>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Final Prompt Textarea -->
                        <div>
                            <label style="font-size: 12px; font-weight: 600; color: #09090b; margin-bottom: 6px; display: block;">Final Prompt (Auto-Assembled)</label>
                            <textarea id="ai_final_prompt" rows="3" class="form-control" style="width: 100%; font-size: 12px; line-height: 1.5;">A photorealistic beautiful Indian fashion model wearing this exact product. The background should have elegant royal palace with marble pillars and chandeliers. Shot type: full body head-to-toe shot showing the complete outfit/jewelry look. Aspect ratio: 2:3 vertical fashion portrait format.</textarea>
                        </div>

                        <!-- Action Bar -->
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e4e4e7; padding-top: 14px; flex-wrap: wrap; gap: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <label style="font-size: 12px; font-weight: 500; color: #71717a;">Variations:</label>
                                <div style="display: flex; gap: 4px;">
                                    <?php for ($n = 1; $n <= 4; $n++): ?>
                                    <label class="ai-bg-picker">
                                        <input type="radio" name="ai_num_images" value="<?= $n ?>" <?= $n === 1 ? 'checked' : '' ?> class="ai-radio-hidden">
                                        <div class="ai-bg-pill"><?= $n ?> <?= $n === 1 ? 'Image' : 'Images' ?></div>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <button type="button" onclick="aiGenerateAdvancedImage()" id="aiImageBtn" class="shadcn-btn shadcn-btn-primary">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate Model Image
                            </button>
                        </div>

                        <!-- Loading Indicator -->
                        <div id="aiImageLoading" style="display: none; align-items: center; gap: 8px; padding: 12px; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; font-size: 12.5px; color: #71717a;">
                            <i class="fa-solid fa-spinner fa-spin" style="font-size: 14px; color: #09090b;"></i>
                            <span>AI is generating image(s)... Please wait 15-20 seconds.</span>
                        </div>

                        <!-- Generated Results -->
                        <div id="aiImageResult" style="display: none; margin-top: 12px; padding-top: 14px; border-top: 1px solid #e4e4e7;">
                            <p style="font-size: 12.5px; font-weight: 600; color: #09090b; margin-bottom: 10px;">Generated Images:</p>
                            
                            <div id="aiImageGrid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 12px;"></div>
                            
                            <div style="display: flex; justify-content: center;">
                                <button type="button" onclick="resetAiImage()" class="shadcn-btn shadcn-btn-outline">
                                    <i class="fa-solid fa-rotate-left"></i> Clear &amp; Try Again
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side Column -->
        <div class="side-column" style="flex: 0 0 320px; min-width: 280px;">
            
            <!-- Publish Actions Card -->
            <div class="shadcn-card" style="margin-bottom: 24px;">
                <div class="shadcn-card-header">
                    <h2 class="shadcn-card-title">
                        <i class="fa-solid fa-paper-plane" style="color: #71717a;"></i>
                        Publishing
                    </h2>
                </div>
                <div class="shadcn-card-padded">
                    <div class="form-group">
                        <label for="p_status">Visibility Status</label>
                        <select name="status" id="p_status" class="form-control" style="width: 100%; font-weight: 500;">
                            <option value="published" <?php echo ($product['status'] === 'published') ? 'selected' : ''; ?>>Published (Live in Store)</option>
                            <option value="draft" <?php echo ($product['status'] === 'draft') ? 'selected' : ''; ?>>Draft (Hidden)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 16px 0; background: #fafafa; padding: 12px; border-radius: 6px; border: 1px solid #e4e4e7;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0; font-size: 13px;">
                            <input type="checkbox" name="is_featured" value="1" style="width: 16px; height: 16px; accent-color: #09090b;" <?php echo ($product['is_featured'] == 1) ? 'checked' : ''; ?>>
                            <span style="font-weight: 600; color: #09090b;">
                                <i class="fa-solid fa-star" style="color: #71717a; margin-right: 4px;"></i> Feature this product
                            </span>
                        </label>
                        <p style="font-size: 11px; color: #71717a; margin: 6px 0 0 24px; line-height: 1.3;">
                            Featured products appear on homepage showcases.
                        </p>
                    </div>

                    <div style="border-top: 1px solid #e4e4e7; padding-top: 16px; display: flex; justify-content: space-between; gap: 10px;">
                        <a href="products.php?delete=<?php echo $product['id']; ?>" class="shadcn-btn shadcn-btn-danger delete-confirm" data-name="<?php echo sanitize_html($product['name']); ?>" style="padding: 0 12px; height: 38px;" title="Delete this product">
                            <i class="fa-solid fa-trash-can"></i> Delete
                        </a>
                        <button type="submit" class="shadcn-btn shadcn-btn-primary" style="flex: 1; height: 38px;">
                            <i class="fa-solid fa-check"></i> Update Product
                        </button>
                    </div>
                </div>
            </div>

            <!-- Categories Card -->
            <div class="shadcn-card" style="margin-bottom: 24px;">
                <div class="shadcn-card-header">
                    <h2 class="shadcn-card-title">
                        <i class="fa-solid fa-folder-tree" style="color: #71717a;"></i>
                        Categories
                    </h2>
                </div>
                <div class="shadcn-card-padded">
                    <div class="category-checklist-container" style="max-height: 240px; overflow-y: auto; border: 1px solid #e4e4e7; padding: 12px; background: #fafafa; border-radius: 6px; margin-bottom: 12px;">
                        <?php if (empty($categories)): ?>
                            <p style="color: #71717a; font-size: 12.5px; margin: 0;">No categories created yet. <a href="categories.php">Create categories</a>.</p>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <div class="category-checklist-item" style="margin-left: <?php echo (isset($cat['depth']) ? $cat['depth'] * 14 : 0); ?>px; margin-bottom: 8px;">
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #09090b; cursor: pointer;">
                                        <input type="checkbox" name="category_ids[]" id="cat_check_<?php echo $cat['id']; ?>" value="<?php echo $cat['id']; ?>" <?php echo in_array($cat['id'], $product_categories) ? 'checked' : ''; ?> style="width: 15px; height: 15px; accent-color: #09090b;">
                                        <span style="text-transform: capitalize;"><?php echo sanitize_html($cat['name']); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="categories.php" target="_blank" style="text-decoration: none; font-size: 12.5px; color: #09090b; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-plus" style="font-size: 11px;"></i> Manage Categories
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Product Image Card -->
            <div class="shadcn-card">
                <div class="shadcn-card-header">
                    <h2 class="shadcn-card-title">
                        <i class="fa-regular fa-image" style="color: #71717a;"></i>
                        Main Product Image
                    </h2>
                </div>
                <div class="shadcn-card-padded">
                    <div class="main-image-preview-container" style="text-align: center; margin-bottom: 14px;">
                        <?php if ($product['main_image']): ?>
                            <img id="main_image_preview" src="<?php echo sanitize_html(get_product_image_url($product['main_image'])); ?>" data-img-path="<?php echo sanitize_html($product['main_image']); ?>" alt="Main Image" style="max-width: 100%; height: 220px; border-radius: 6px; border: 1px solid #e4e4e7; object-fit: contain; margin: 0 auto;">
                            <div id="main_image_placeholder" style="display: none;"></div>
                        <?php else: ?>
                            <img id="main_image_preview" src="" data-img-path="" alt="Main Image Preview" style="display: none; max-width: 100%; height: 220px; border-radius: 6px; border: 1px solid #e4e4e7; object-fit: contain; margin: 0 auto;">
                            <div id="main_image_placeholder" style="background: #fafafa; border: 1px dashed #e4e4e7; border-radius: 8px; padding: 32px 16px; color: #71717a;">
                                <i class="fa-regular fa-image" style="font-size: 36px; margin-bottom: 8px; color: #a1a1aa; display: block;"></i>
                                <span style="font-size: 12.5px;">No product image set</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <input type="hidden" name="selected_main_image_path" id="selected_main_image_path" value="">

                    <!-- 2 Replacement Options -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <button type="button" class="shadcn-btn shadcn-btn-outline" onclick="openSelectMainImageModal()" style="width: 100%; justify-content: center; font-size: 12px; height: 34px;">
                            <i class="fa-solid fa-images" style="color: #71717a;"></i> Replace from Available Images
                        </button>
                        <label class="shadcn-btn shadcn-btn-primary" style="width: 100%; text-align: center; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; height: 34px; margin: 0;">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Replace with New File
                            <input type="file" name="main_image" id="main_image_input" accept="image/*" style="display: none;" onchange="previewNewMainImage(this)">
                        </label>
                    </div>
                </div>
            </div>

        </div>

    </div>
</form>

<script>
// Helper to mark a gallery image for deletion when click (x)
function markGalleryImageForDeletion(imageId) {
    const deletedField = document.getElementById('deleted_gallery_ids');
    let currentIds = deletedField.value ? deletedField.value.split(',') : [];
    
    if (!currentIds.includes(imageId.toString())) {
        currentIds.push(imageId);
        deletedField.value = currentIds.join(',');
        
        // Hide the item visually
        const element = document.getElementById('gallery_item_' + imageId);
        if (element) {
            element.style.transition = 'all 0.3s ease';
            element.style.opacity = '0.3';
            element.style.border = '2px solid var(--wp-error-red)';
            element.querySelector('.gallery-item-delete').innerHTML = '<i class="fa-solid fa-rotate-left"></i>';
            element.querySelector('.gallery-item-delete').title = 'Undo delete';
            element.querySelector('.gallery-item-delete').onclick = function() {
                undoGalleryImageDeletion(imageId);
            };
        }
    }
}

// Helper to undo marked gallery image deletion
function undoGalleryImageDeletion(imageId) {
    const deletedField = document.getElementById('deleted_gallery_ids');
    let currentIds = deletedField.value ? deletedField.value.split(',') : [];
    
    currentIds = currentIds.filter(id => id !== imageId.toString());
    deletedField.value = currentIds.join(',');
    
    // Restore the item visually
    const element = document.getElementById('gallery_item_' + imageId);
    if (element) {
        element.style.opacity = '1';
        element.style.border = '1px solid var(--wp-border)';
        element.querySelector('.gallery-item-delete').innerHTML = '<i class="fa-solid fa-xmark"></i>';
        element.querySelector('.gallery-item-delete').title = 'Remove this image';
        element.querySelector('.gallery-item-delete').onclick = function() {
            markGalleryImageForDeletion(imageId);
        };
    }
}
</script>

<!-- Sync Confirmation Modal Styles -->
<style>
.ai-radio-hidden {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.ai-radio-hidden:checked + .ai-model-box {
    border-color: #09090b !important;
    box-shadow: 0 0 0 2px rgba(9, 9, 11, 0.3);
}
.ai-bg-pill {
    padding: 5px 12px;
    background: #f4f4f5;
    border: 1px solid #e4e4e7;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 500;
    color: #18181b;
    cursor: pointer;
    transition: all 0.15s ease;
}
.ai-bg-pill:hover {
    background: #e4e4e7;
    color: #09090b;
}
.ai-radio-hidden:checked + .ai-bg-pill {
    background: #09090b;
    color: #ffffff;
    border-color: #09090b;
}

.sync-modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(9, 9, 11, 0.65);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(4px);
}
.sync-modal-dialog {
    background: #ffffff;
    border-radius: 8px;
    width: 100%;
    max-width: 920px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    border: 1px solid #e4e4e7;
    overflow: hidden;
}
.sync-modal-header {
    padding: 16px 20px;
    background: #09090b;
    color: #ffffff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.sync-modal-header h3 {
    margin: 0;
    font-size: 14px;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
}
.sync-modal-close {
    background: none;
    border: none;
    color: #a1a1aa;
    font-size: 20px;
    cursor: pointer;
    line-height: 1;
}
.sync-modal-close:hover { color: #ffffff; }
.sync-modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}
.sync-modal-footer {
    padding: 14px 20px;
    background: #fafafa;
    border-top: 1px solid #e4e4e7;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.sync-compare-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
    font-size: 13px;
    border: 1px solid #e4e4e7;
}
.sync-compare-table th {
    background: #fafafa;
    padding: 10px 12px;
    border: 1px solid #e4e4e7;
    text-align: left;
    font-weight: 600;
    color: #09090b;
}
.sync-compare-table td {
    padding: 12px;
    border: 1px solid #e4e4e7;
    vertical-align: top;
}
.sync-val-old { color: #71717a; font-weight: 500; }
.sync-val-new { color: #09090b; font-weight: 600; }
.sync-text-box {
    max-height: 140px;
    overflow-y: auto;
    white-space: pre-wrap;
    background: #fafafa;
    padding: 10px;
    border-radius: 6px;
    font-size: 12px;
    line-height: 1.5;
    border: 1px solid #e4e4e7;
}
.sync-img-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.sync-img-grid img {
    width: 64px;
    height: 64px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e4e4e7;
}
</style>

<div id="sync_api_modal" class="sync-modal-overlay" style="display: none;">
    <div class="sync-modal-dialog">
        <div class="sync-modal-header">
            <h3><i class="fa-solid fa-arrows-rotate"></i> Sync Details from SriShringarr API</h3>
            <button type="button" class="sync-modal-close" onclick="closeSyncModal()">&times;</button>
        </div>
        
        <div class="sync-modal-body" id="sync_modal_body">
            <!-- Loading State -->
            <div id="sync_loading_state" style="text-align: center; padding: 40px;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 36px; color: #09090b; margin-bottom: 15px;"></i>
                <p style="font-size: 13.5px; color: #71717a; margin: 0;">Fetching details from SriShringarr API for SKU: <strong id="sync_sku_label" style="color: #09090b;"></strong>...</p>
            </div>

            <!-- Content Comparison State -->
            <div id="sync_content_state" style="display: none;">
                <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; font-size: 12.5px; color: #09090b; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-info" style="font-size: 16px; color: #71717a;"></i>
                    <span>Review the fetched external details below. Select the checkboxes for fields you wish to update in your database, then click <strong>Confirm &amp; Update Product</strong>.</span>
                </div>

                <table class="sync-compare-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;"><input type="checkbox" id="sync_check_all" checked onclick="toggleAllSyncChecks(this)" title="Select / Deselect All" style="accent-color: #09090b;"></th>
                            <th style="width: 130px;">Field</th>
                            <th style="width: 40%;">Current Local Data</th>
                            <th style="width: 50%;">Fetched API Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Product Name -->
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" id="chk_sync_name" class="sync-field-check" checked style="accent-color: #09090b;">
                            </td>
                            <td><strong>Product Name</strong></td>
                            <td><span id="sync_local_name" class="sync-val-old"></span></td>
                            <td><span id="sync_remote_name" class="sync-val-new"></span></td>
                        </tr>

                        <!-- Description -->
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" id="chk_sync_desc" class="sync-field-check" checked style="accent-color: #09090b;">
                            </td>
                            <td><strong>Description</strong></td>
                            <td><div id="sync_local_desc" class="sync-val-old sync-text-box"></div></td>
                            <td><div id="sync_remote_desc" class="sync-val-new sync-text-box"></div></td>
                        </tr>

                        <!-- Stock Quantity / Inventory -->
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" id="chk_sync_stock" class="sync-field-check" checked style="accent-color: #09090b;">
                            </td>
                            <td><strong>Stock Quantity (Inventory)</strong></td>
                            <td><span id="sync_local_stock" class="sync-val-old"></span></td>
                            <td><span id="sync_remote_stock" class="sync-val-new"></span></td>
                        </tr>

                        <!-- Images -->
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" id="chk_sync_images" class="sync-field-check" checked style="accent-color: #09090b;">
                            </td>
                            <td><strong>Images &amp; Gallery</strong></td>
                            <td>
                                <div id="sync_local_images_preview" class="sync-img-grid"></div>
                            </td>
                            <td>
                                <div id="sync_remote_images_preview" class="sync-img-grid"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Error State -->
            <div id="sync_error_state" style="display: none; padding: 30px; text-align: center; color: #ef4444;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 36px; margin-bottom: 12px;"></i>
                <p id="sync_error_msg" style="font-weight: 600; font-size: 14px; margin: 0;"></p>
            </div>

            <!-- Applying Progress State -->
            <div id="sync_applying_state" style="display: none; text-align: center; padding: 40px;">
                <i class="fa-solid fa-cloud-arrow-down fa-bounce" style="font-size: 40px; color: #09090b; margin-bottom: 15px;"></i>
                <p style="font-size: 14px; font-weight: 600; color: #09090b; margin-bottom: 6px;">Updating Product &amp; Downloading Images...</p>
                <p style="font-size: 12px; color: #71717a; margin: 0;">Please wait, downloading images into local product directory...</p>
            </div>
        </div>

        <div class="sync-modal-footer">
            <button type="button" class="shadcn-btn shadcn-btn-outline" onclick="closeSyncModal()">Cancel</button>
            <button type="button" class="shadcn-btn shadcn-btn-primary" id="btn_confirm_sync" onclick="executeSyncApply()">
                <i class="fa-solid fa-check"></i> Confirm &amp; Update Product
            </button>
        </div>
    </div>
</div>

<!-- Modal: Select Main Image from Available Images -->
<div id="selectMainImageModal" class="sync-modal-overlay" style="display: none;">
    <div class="sync-modal-dialog" style="max-width: 600px; padding: 20px; background: #ffffff; border-radius: 8px; border: 1px solid #e4e4e7;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e4e4e7; padding-bottom: 12px; margin-bottom: 15px;">
            <h3 style="margin: 0; font-size: 14px; font-weight: 600; color: #09090b; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-images" style="color: #71717a;"></i> Select Main Product Image
            </h3>
            <button type="button" onclick="closeSelectMainImageModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #71717a;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <p style="font-size: 12.5px; color: #71717a; margin-bottom: 12px;">Click any image below to set it as the primary product image:</p>

        <div id="available_images_grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; max-height: 320px; overflow-y: auto; padding: 4px;">
            <?php
            $all_available_imgs = [];
            if (!empty($product['main_image'])) {
                $all_available_imgs[] = ['path' => $product['main_image'], 'thumb' => $product['main_image'], 'is_main' => true];
            }
            if (!empty($gallery_images)) {
                foreach ($gallery_images as $gimg) {
                    if ($gimg['image_path'] !== $product['main_image']) {
                        $all_available_imgs[] = ['path' => $gimg['image_path'], 'thumb' => $gimg['thumb_path'], 'is_main' => false];
                    }
                }
            }
            foreach ($all_available_imgs as $aidx => $aimg):
            ?>
            <div class="available-img-card" onclick="chooseMainImage('<?php echo sanitize_html($aimg['path']); ?>', this)" style="position: relative; aspect-ratio: 1/1; border: 2px solid #e4e4e7; border-radius: 6px; overflow: hidden; cursor: pointer; transition: all 0.15s ease;">
                <img src="<?php echo sanitize_html($aimg['thumb'] ?: $aimg['path']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php if (!empty($aimg['is_main'])): ?>
                    <span style="position: absolute; top: 4px; left: 4px; background: #09090b; color: #ffffff; font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 4px;">CURRENT MAIN</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; border-top: 1px solid #e4e4e7; padding-top: 12px;">
            <button type="button" class="shadcn-btn shadcn-btn-outline" onclick="closeSelectMainImageModal()">Cancel</button>
            <button type="button" id="confirmMainImgBtn" class="shadcn-btn shadcn-btn-primary" onclick="confirmSelectedMainImage()" disabled>
                <i class="fa-solid fa-check"></i> Set as Main Image
            </button>
        </div>
    </div>
</div>

<script>
let pendingMainImagePath = '';

function openSelectMainImageModal() {
    renderAvailableImagesModal();
    document.getElementById('selectMainImageModal').style.display = 'flex';
}

function closeSelectMainImageModal() {
    document.getElementById('selectMainImageModal').style.display = 'none';
    pendingMainImagePath = '';
}

function renderAvailableImagesModal() {
    const grid = document.getElementById('available_images_grid');
    if (!grid) return;

    const mainImgEl = document.getElementById('main_image_preview');
    const selectedInput = document.getElementById('selected_main_image_path');
    const currentMainPath = selectedInput.value || (mainImgEl ? mainImgEl.getAttribute('data-img-path') || mainImgEl.src : '');

    let html = '';

    // Render Current Main Image card
    if (currentMainPath) {
        let cleanMain = currentMainPath;
        if (cleanMain.includes(window.location.origin)) {
            cleanMain = cleanMain.replace(window.location.origin + '/', '');
        }
        let displaySrc = mainImgEl ? mainImgEl.src : currentMainPath;
        html += `
            <div class="available-img-card" onclick="chooseMainImage('${cleanMain}', this)" style="position: relative; aspect-ratio: 1/1; border: 2px solid #e4e4e7; border-radius: 6px; overflow: hidden; cursor: pointer; transition: all 0.15s ease;">
                <img src="${displaySrc}" style="width: 100%; height: 100%; object-fit: cover;">
                <span style="position: absolute; top: 4px; left: 4px; background: #09090b; color: #ffffff; font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 4px;">CURRENT MAIN</span>
            </div>
        `;
    }

    // Render Gallery Images cards
    document.querySelectorAll('.gallery-item').forEach(item => {
        const imgPath = item.getAttribute('data-img-path');
        const thumbPath = item.getAttribute('data-thumb-path') || imgPath;
        const imgEl = item.querySelector('img');
        const imgSrc = imgEl ? imgEl.src : thumbPath;

        if (imgPath && imgPath !== currentMainPath) {
            html += `
                <div class="available-img-card" onclick="chooseMainImage('${imgPath}', this)" style="position: relative; aspect-ratio: 1/1; border: 2px solid #e4e4e7; border-radius: 6px; overflow: hidden; cursor: pointer; transition: all 0.15s ease;">
                    <img src="${imgSrc}" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            `;
        }
    });

    grid.innerHTML = html;
    document.getElementById('confirmMainImgBtn').disabled = true;
    pendingMainImagePath = '';
}

function chooseMainImage(imgPath, cardEl) {
    pendingMainImagePath = imgPath;
    document.querySelectorAll('.available-img-card').forEach(c => {
        c.style.borderColor = '#e4e4e7';
        c.style.boxShadow = 'none';
    });
    cardEl.style.borderColor = '#09090b';
    cardEl.style.boxShadow = '0 0 0 2px rgba(9, 9, 11, 0.3)';
    document.getElementById('confirmMainImgBtn').disabled = false;
}

function confirmSelectedMainImage() {
    if (!pendingMainImagePath) return;

    const mainImgPreview = document.getElementById('main_image_preview');
    const placeholder = document.getElementById('main_image_placeholder');
    const selectedInput = document.getElementById('selected_main_image_path');

    const currentMainPath = selectedInput.value || (mainImgPreview ? mainImgPreview.getAttribute('data-img-path') : '');

    if (pendingMainImagePath !== currentMainPath) {
        // Find gallery item for pendingMainImagePath
        let targetGalleryItem = null;
        document.querySelectorAll('.gallery-item').forEach(item => {
            if (item.getAttribute('data-img-path') === pendingMainImagePath) {
                targetGalleryItem = item;
            }
        });

        if (targetGalleryItem && mainImgPreview) {
            const oldMainSrc = mainImgPreview.src;
            const oldMainPath = mainImgPreview.getAttribute('data-img-path') || currentMainPath;

            const targetImgEl = targetGalleryItem.querySelector('img');
            const targetImgSrc = targetImgEl ? targetImgEl.src : pendingMainImagePath;

            // 1. Swap main preview image to selected target
            mainImgPreview.src = targetImgSrc;
            mainImgPreview.setAttribute('data-img-path', pendingMainImagePath);

            // 2. Swap target gallery item image to old main image
            if (targetImgEl) {
                targetImgEl.src = oldMainSrc;
            }
            targetGalleryItem.setAttribute('data-img-path', oldMainPath);
            targetGalleryItem.setAttribute('data-thumb-path', oldMainSrc);
        } else if (mainImgPreview) {
            mainImgPreview.src = pendingMainImagePath;
            mainImgPreview.setAttribute('data-img-path', pendingMainImagePath);
        }

        selectedInput.value = pendingMainImagePath;
    }

    if (mainImgPreview) {
        mainImgPreview.style.display = 'inline-block';
    }
    if (placeholder) {
        placeholder.style.display = 'none';
    }

    closeSelectMainImageModal();
}

function previewNewMainImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('main_image_preview');
            const placeholder = document.getElementById('main_image_placeholder');
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'inline-block';
            }
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            document.getElementById('selected_main_image_path').value = '';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
let fetchedSyncPayload = null;
const currentProductId = <?php echo (int)$product['id']; ?>;

function openSyncModal() {
    const skuInput = document.getElementById('p_sku');
    const sku = skuInput ? skuInput.value.trim() : '<?php echo sanitize_html($product['sku']); ?>';
    
    if (!sku) {
        toast.warning('SKU Required', 'Please enter a Product SKU first.');
        return;
    }

    document.getElementById('sync_sku_label').textContent = sku;
    document.getElementById('sync_loading_state').style.display = 'block';
    document.getElementById('sync_content_state').style.display = 'none';
    document.getElementById('sync_error_state').style.display = 'none';
    document.getElementById('sync_applying_state').style.display = 'none';
    document.getElementById('btn_confirm_sync').style.display = 'inline-block';
    document.getElementById('btn_confirm_sync').disabled = false;
    document.getElementById('sync_api_modal').style.display = 'flex';

    fetch(`api/sync_product_api.php?action=fetch&sku=${encodeURIComponent(sku)}&product_id=${currentProductId}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('sync_loading_state').style.display = 'none';
            if (data.success) {
                fetchedSyncPayload = data.external;
                populateSyncComparison(data.local, data.external);
                document.getElementById('sync_content_state').style.display = 'block';
            } else {
                document.getElementById('sync_error_msg').textContent = data.message || 'Failed to fetch external API data.';
                document.getElementById('sync_error_state').style.display = 'block';
                document.getElementById('btn_confirm_sync').style.display = 'none';
            }
        })
        .catch(err => {
            document.getElementById('sync_loading_state').style.display = 'none';
            document.getElementById('sync_error_msg').textContent = 'Network or server error while connecting to sync API.';
            document.getElementById('sync_error_state').style.display = 'block';
            document.getElementById('btn_confirm_sync').style.display = 'none';
        });
}

function closeSyncModal() {
    document.getElementById('sync_api_modal').style.display = 'none';
}

function toggleAllSyncChecks(master) {
    const checks = document.querySelectorAll('.sync-field-check');
    checks.forEach(c => c.checked = master.checked);
}

function populateSyncComparison(local, external) {
    document.getElementById('sync_local_name').textContent = local.name || '(Empty)';
    document.getElementById('sync_remote_name').textContent = external.name || '(None)';

    document.getElementById('sync_local_desc').textContent = local.description || '(Empty)';
    document.getElementById('sync_remote_desc').textContent = external.description || '(None)';

    document.getElementById('sync_local_stock').textContent = (local.stock_qty !== undefined ? local.stock_qty : 0) + ' units';
    document.getElementById('sync_remote_stock').textContent = (external.stock_qty !== undefined ? external.stock_qty : 0) + ' units';

    // Local Images preview
    const localImgGrid = document.getElementById('sync_local_images_preview');
    localImgGrid.innerHTML = '';
    if (local.main_image) {
        localImgGrid.innerHTML += `<img src="${local.main_image}" title="Main Image">`;
    }
    if (local.gallery && local.gallery.length > 0) {
        local.gallery.forEach(g => {
            localImgGrid.innerHTML += `<img src="${g.thumb_path || g.image_path}" title="Gallery Image">`;
        });
    }
    if (!local.main_image && (!local.gallery || local.gallery.length === 0)) {
        localImgGrid.innerHTML = '<span style="color: #8c8f94; font-size: 12px;">No images</span>';
    }

    // Remote Images preview
    const remoteImgGrid = document.getElementById('sync_remote_images_preview');
    remoteImgGrid.innerHTML = '';
    if (external.main_image) {
        remoteImgGrid.innerHTML += `<img src="${external.main_image}" title="Main Image">`;
    }
    if (external.images && external.images.length > 0) {
        external.images.forEach(imgUrl => {
            if (imgUrl !== external.main_image) {
                remoteImgGrid.innerHTML += `<img src="${imgUrl}" title="Gallery Image">`;
            }
        });
    }
    if (!external.main_image && (!external.images || external.images.length === 0)) {
        remoteImgGrid.innerHTML = '<span style="color: #8c8f94; font-size: 12px;">No remote images</span>';
    }
}

function executeSyncApply() {
    if (!fetchedSyncPayload) return;

    const syncName = document.getElementById('chk_sync_name').checked;
    const syncDesc = document.getElementById('chk_sync_desc').checked;
    const syncStock = document.getElementById('chk_sync_stock').checked;
    const syncImages = document.getElementById('chk_sync_images').checked;

    if (!syncName && !syncDesc && !syncStock && !syncImages) {
        toast.warning('Selection Required', 'Please select at least one field to sync.');
        return;
    }

    document.getElementById('sync_content_state').style.display = 'none';
    document.getElementById('sync_applying_state').style.display = 'block';
    document.getElementById('btn_confirm_sync').disabled = true;

    const payload = {
        action: 'apply',
        product_id: currentProductId,
        sync_name: syncName,
        sync_description: syncDesc,
        sync_stock: syncStock,
        sync_images: syncImages,
        external: fetchedSyncPayload
    };

    fetch('api/sync_product_api.php?action=apply', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = `product-edit.php?id=${currentProductId}&message=synced`;
        } else {
            document.getElementById('sync_applying_state').style.display = 'none';
            document.getElementById('sync_error_msg').textContent = data.message || 'Failed to update product.';
            document.getElementById('sync_error_state').style.display = 'block';
            document.getElementById('btn_confirm_sync').disabled = false;
        }
    })
    .catch(err => {
        document.getElementById('sync_applying_state').style.display = 'none';
        document.getElementById('sync_error_msg').textContent = 'Network or server error while applying sync changes.';
        document.getElementById('sync_error_state').style.display = 'block';
        document.getElementById('btn_confirm_sync').disabled = false;
    });
}

// --- AI Features JavaScript Handlers ---
function showEl(id) { 
    const el = document.getElementById(id); 
    if (el) { el.style.display = 'flex'; el.classList.remove('hidden'); }
}
function hideEl(id) { 
    const el = document.getElementById(id); 
    if (el) { el.style.display = 'none'; el.classList.add('hidden'); }
}

async function aiGenerateNames() {
    const btn = document.getElementById('aiNamesBtn');
    if (!btn) return;
    btn.disabled = true;
    showEl('aiLoading');
    hideEl('aiNamesResult');
    try {
        const response = await fetch(`api/ai_product_api.php?action=ai_suggest_names&product_id=${currentProductId}`);
        const data = await response.json();
        if (data.success && data.names) {
            document.getElementById('aiNamesList').innerHTML = data.names.map(name => `
                <button type="button" onclick="applyProductName('${name.replace(/'/g, "\\'")}')" class="shadcn-btn shadcn-btn-outline" style="width: 100%; text-align: left; justify-content: space-between; font-size: 11.5px; height: auto; padding: 6px 10px;">
                    <span style="white-space: normal; line-height: 1.3;">${name}</span>
                    <i class="fa-solid fa-check" style="font-size: 10px; color: #71717a; flex-shrink: 0; margin-left: 6px;"></i>
                </button>
            `).join('');
            document.getElementById('aiNamesResult').style.display = 'block';
        } else {
            toast.error('Name Generation Failed', data.error || 'Failed to generate names');
        }
    } catch (err) {
        console.error(err);
        toast.error('Network Error', 'A network error occurred while generating product names.');
    } finally {
        btn.disabled = false;
        hideEl('aiLoading');
    }
}

function applyProductName(newName) {
    const nameInput = document.getElementById('p_name');
    if (nameInput) {
        nameInput.value = newName;
        nameInput.focus();
        nameInput.style.transition = 'all 0.3s ease';
        nameInput.style.boxShadow = '0 0 0 3px rgba(9, 9, 11, 0.2)';
        setTimeout(() => { nameInput.style.boxShadow = ''; }, 1000);
        toast.success('Name Applied', 'Product name has been updated.');
    }
}

async function aiGenerateDescription() {
    const btn = document.getElementById('aiDescBtn');
    const maxWords = document.getElementById('aiDescMaxWords')?.value || 100;
    if (!btn) return;
    btn.disabled = true;
    showEl('aiLoading');
    hideEl('aiDescResult');
    try {
        const response = await fetch(`api/ai_product_api.php?action=ai_suggest_description&product_id=${currentProductId}&max_words=${maxWords}`);
        const data = await response.json();
        if (data.success && data.description) {
            document.getElementById('aiDescTextarea').value = data.description;
            document.getElementById('aiDescResult').style.display = 'block';
            toast.success('Description Generated', 'Review the preview below and click apply.');
        } else {
            toast.error('Description Failed', data.error || 'Failed to generate description');
        }
    } catch (err) {
        console.error(err);
        toast.error('Network Error', 'A network error occurred while generating description.');
    } finally {
        btn.disabled = false;
        hideEl('aiLoading');
    }
}

function updateTextareaCounter(textarea, counterId) {
    const counter = document.getElementById(counterId);
    if (!counter || !textarea) return;
    const val = textarea.value || '';
    const trimmed = val.trim();
    const words = trimmed ? trimmed.split(/\s+/).length : 0;
    const chars = val.length;
    counter.textContent = `${words} words • ${chars} chars`;
}

function autoResizeTextarea(textarea) {
    if (!textarea) return;
    textarea.style.height = 'auto';
    const newHeight = Math.max(textarea.scrollHeight + 2, 70);
    textarea.style.height = newHeight + 'px';
}

function initAutoExpandTextareas() {
    const configs = [
        { id: 'p_desc', counterId: 'desc_length_counter' },
        { id: 'p_short_desc', counterId: 'short_desc_length_counter' }
    ];

    configs.forEach(({ id, counterId }) => {
        const el = document.getElementById(id);
        if (!el) return;

        const update = () => {
            autoResizeTextarea(el);
            updateTextareaCounter(el, counterId);
        };

        el.addEventListener('input', update);
        el.addEventListener('change', update);
        el.addEventListener('keyup', update);

        update();
    });
}

function applyAiDescription() {
    const val = document.getElementById('aiDescTextarea').value.trim();
    const descInput = document.getElementById('p_desc');
    if (descInput && val) {
        descInput.value = val;
        descInput.dispatchEvent(new Event('input', { bubbles: true }));
        descInput.focus();
        descInput.style.transition = 'all 0.3s ease';
        descInput.style.boxShadow = '0 0 0 3px rgba(9, 9, 11, 0.2)';
        setTimeout(() => { descInput.style.boxShadow = ''; }, 1000);
        toast.success('Description Applied', 'Detailed description field updated.');
    }
}

async function aiGenerateShortDescription() {
    const btn = document.getElementById('aiShortDescBtn');
    const loading = document.getElementById('aiShortDescLoading');
    const shortDescInput = document.getElementById('p_short_desc');
    const maxWords = document.getElementById('aiShortDescMaxWords')?.value || 50;
    if (!btn || !shortDescInput) return;

    btn.disabled = true;
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
    if (loading) loading.style.display = 'flex';

    try {
        const response = await fetch(`api/ai_product_api.php?action=ai_suggest_short_description&product_id=${currentProductId}&max_words=${maxWords}`);
        const data = await response.json();
        if (data.success && data.short_description) {
            shortDescInput.value = data.short_description;
            shortDescInput.dispatchEvent(new Event('input', { bubbles: true }));
            shortDescInput.focus();
            shortDescInput.style.transition = 'all 0.3s ease';
            shortDescInput.style.boxShadow = '0 0 0 3px rgba(9, 9, 11, 0.2)';
            setTimeout(() => { shortDescInput.style.boxShadow = ''; }, 1000);
            toast.success('Short Description Generated', 'Short description and highlights have been updated.');
        } else {
            toast.error('Short Description Failed', data.error || 'Failed to generate short description');
        }
    } catch (err) {
        console.error(err);
        toast.error('Network Error', 'A network error occurred while generating short description.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHTML;
        if (loading) loading.style.display = 'none';
    }
}

function updateFinalPrompt() {
    const prodName = document.getElementById('p_name')?.value || 'fashion item';
    const faceInput = document.querySelector('input[name="ai_model_face"]:checked')?.value || '';
    const customBg = document.getElementById('ai_bg_custom')?.value.trim() || 'clean studio background';
    const shotType = document.querySelector('input[name="ai_shot_type"]:checked')?.value || '';
    const hairStyle = document.querySelector('input[name="ai_hair_style"]:checked')?.value || '';

    let promptParts = [
        `A photorealistic beautiful Indian fashion model wearing this exact ${prodName}.`,
        `The background should have ${customBg}.`,
        `Shot type: ${shotType}.`,
        `Do not change the product details.`,
        `Aspect ratio: 2:3 vertical fashion portrait format.`
    ];

    if (hairStyle) {
        promptParts.push(`The model should have ${hairStyle}.`);
    }
    if (faceInput) {
        promptParts.push(`The model's face must match the reference photo exactly.`);
    }

    const finalBox = document.getElementById('ai_final_prompt');
    if (finalBox) {
        finalBox.value = promptParts.join(' ');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initAutoExpandTextareas();

    document.querySelectorAll('input[name="ai_model_face"], input[name="ai_bg_preset"], input[name="ai_shot_type"], input[name="ai_hair_style"]').forEach(input => {
        input.addEventListener('change', updateFinalPrompt);
    });

    document.querySelectorAll('input[name="ai_model_face"]').forEach(input => {
        input.addEventListener('change', function() {
            const shotVal = this.getAttribute('data-shot');
            const hairVal = this.getAttribute('data-hair');
            if (shotVal) {
                const shotRadio = Array.from(document.querySelectorAll('input[name="ai_shot_type"]')).find(r => (r.getAttribute('data-name') || r.value).toLowerCase().includes(shotVal.toLowerCase()));
                if (shotRadio) shotRadio.checked = true;
            }
            if (hairVal) {
                const hairRadio = Array.from(document.querySelectorAll('input[name="ai_hair_style"]')).find(r => (r.getAttribute('data-name') || r.value).toLowerCase().includes(hairVal.toLowerCase()));
                if (hairRadio) hairRadio.checked = true;
            }
            updateFinalPrompt();
        });
    });

    document.getElementById('ai_bg_custom')?.addEventListener('input', updateFinalPrompt);

    document.querySelectorAll('input[name="ai_bg_preset"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            const customInput = document.getElementById('ai_bg_custom');
            if (customInput) {
                customInput.value = e.target.value;
                updateFinalPrompt();
            }
        });
    });
});

window.addEventListener('load', () => {
    setTimeout(initAutoExpandTextareas, 80);
});

window.addEventListener('resize', () => {
    autoResizeTextarea(document.getElementById('p_desc'));
    autoResizeTextarea(document.getElementById('p_short_desc'));
});


async function aiGenerateAdvancedImage() {
    const btn = document.getElementById('aiImageBtn');
    const faceInput = document.querySelector('input[name="ai_model_face"]:checked')?.value || '';
    const finalPrompt = document.getElementById('ai_final_prompt')?.value.trim() || '';
    const numImages = document.querySelector('input[name="ai_num_images"]:checked')?.value || 1;

    if (!btn) return;
    btn.disabled = true;
    showEl('aiImageLoading');
    hideEl('aiImageResult');
    document.getElementById('aiImageGrid').innerHTML = '';

    try {
        const response = await fetch(`api/ai_product_api.php?action=ai_generate_model_image&product_id=${currentProductId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                prompt: finalPrompt,
                face_reference: faceInput,
                num_images: parseInt(numImages)
            })
        });
        const data = await response.json();

        if (data.success && data.images_base64 && data.images_base64.length > 0) {
            const grid = document.getElementById('aiImageGrid');
            data.images_base64.forEach((b64, index) => {
                grid.innerHTML += `
                    <div style="display: flex; flex-direction: column; gap: 8px; background: #ffffff; border: 1px solid #e4e4e7; padding: 8px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <img src="data:image/jpeg;base64,${b64}" style="width: 100%; aspect-ratio: 2/3; object-fit: cover; border-radius: 4px;">
                        <button type="button" onclick="saveAiGeneratedImage(this, '${b64}')" class="shadcn-btn shadcn-btn-primary" style="width: 100%; justify-content: center; padding: 6px; font-size: 11px;">
                            <i class="fa-solid fa-floppy-disk"></i> Save Image ${index + 1}
                        </button>
                    </div>
                `;
            });
            document.getElementById('aiImageResult').style.display = 'block';
            toast.success('Images Generated', 'Review the generated photos and save them to the gallery.');
        } else {
            toast.error('Image Generation Failed', data.error || 'Failed to generate model images');
        }
    } catch (err) {
        console.error(err);
        toast.error('Network Error', 'A network error occurred while generating images.');
    } finally {
        btn.disabled = false;
        hideEl('aiImageLoading');
    }
}

function resetAiImage() {
    hideEl('aiImageResult');
    document.getElementById('aiImageGrid').innerHTML = '';
    document.getElementById('ai_final_prompt')?.focus();
}

async function saveAiGeneratedImage(btn, base64Str) {
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;

    try {
        const response = await fetch(`api/ai_product_api.php?action=save_ai_image&product_id=${currentProductId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image_base64: base64Str })
        });
        const data = await response.json();

        if (data.success && data.path) {
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Saved!';
            btn.style.background = '#059669';
            toast.success('Image Saved', 'AI model image was saved to the product gallery.');

            // Dynamically append into product gallery list!
            const galGrid = document.querySelector('.gallery-grid') || document.getElementById('gallery_preview_grid');
            if (galGrid) {
                const newItem = document.createElement('div');
                newItem.className = 'gallery-item';
                newItem.id = 'gallery_item_' + data.id;
                newItem.innerHTML = `
                    <img src="${data.thumb_path || data.path}" alt="Gallery Image">
                    <div class="gallery-item-delete" onclick="markGalleryImageForDeletion(${data.id})" title="Remove this image">
                        <i class="fa-solid fa-xmark"></i>
                    </div>
                `;
                galGrid.appendChild(newItem);
            }
        } else {
            toast.error('Save Failed', data.error || 'Unknown error');
            btn.innerHTML = origHTML;
            btn.disabled = false;
        }
    } catch (err) {
        console.error(err);
        toast.error('Network Error', 'Network error while saving AI image.');
        btn.innerHTML = origHTML;
        btn.disabled = false;
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
