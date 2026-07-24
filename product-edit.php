<?php
// admin/product-edit.php
$page_title = "Edit Product";
require_once __DIR__ . '/config/db.php';
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
    $sku = trim($_POST['sku'] ?? '');
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
                    if ($selected_path !== '') {
                        $main_image_path = $selected_path;
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

<div class="wrap-header" style="flex-wrap: wrap; gap: 12px; align-items: flex-start;">
    <div>
        <h1 style="margin: 0; font-size: 22px; font-weight: 600; color: var(--wp-text-dark);">Edit Product</h1>
        <p style="margin: 4px 0 0 0; font-size: 13px; color: #50575e; font-weight: 500; line-height: 1.4; max-width: 650px;"><?php echo sanitize_html($product['name']); ?></p>
    </div>
    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <button type="button" class="button" onclick="openSyncModal()" style="background-color: #8b2e3b; color: #fff; border-color: #72242e;">
            <i class="fa-solid fa-cloud-arrow-down"></i> Sync from SriShringarr API
        </button>
        <a href="product-add.php" class="button button-primary"><i class="fa-solid fa-plus"></i> Add New Product</a>
        <a href="products.php" class="button">Back to Products</a>
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

    <div class="wp-editor-columns">
        
        <!-- Left Main Content Column -->
        <div class="main-column">
            <!-- Title and Description -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Product Title & Description</h2>
                </div>
                <div class="postbox-body">
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label for="p_name" style="margin-bottom: 0;">Product Name <span style="color: var(--wp-error-red);">*</span></label>
                            <button type="button" onclick="aiGenerateNames()" id="aiNamesBtn" class="button" style="font-size: 11px; padding: 2px 8px; height: 26px;">
                                <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--wp-blue);"></i> AI Suggest Names
                            </button>
                        </div>
                        <input type="text" name="name" id="p_name" class="form-control" value="<?php echo sanitize_html($product['name']); ?>" required>
                        <div id="aiNamesResult" style="display: none; margin-top: 8px; padding: 10px; background: #f6f7f7; border: 1px solid var(--wp-border); border-radius: 4px;">
                            <p style="font-size: 11px; font-weight: 600; color: #50575e; margin-bottom: 6px;">Click to apply suggested name:</p>
                            <div id="aiNamesList" style="display: flex; flex-direction: column; gap: 4px;"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="p_slug">Slug (URL identifier)</label>
                        <input type="text" name="slug" id="p_slug" class="form-control" value="<?php echo sanitize_html($product['slug']); ?>">
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
                            <label for="p_desc" style="margin-bottom: 0;">Detailed Description</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 11px; color: #50575e;">Words: <input type="number" id="aiDescMaxWords" value="100" min="10" max="500" class="form-control" style="width: 55px; height: 24px; padding: 1px 4px; font-size: 11px; display: inline-block;"></span>
                                <button type="button" onclick="aiGenerateDescription()" id="aiDescBtn" class="button" style="font-size: 11px; padding: 2px 8px; height: 26px;">
                                    <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--wp-blue);"></i> AI Generate Description
                                </button>
                            </div>
                        </div>
                        <textarea name="description" id="p_desc" class="form-control" rows="8"><?php echo sanitize_html($product['description']); ?></textarea>
                        
                        <div id="aiLoading" style="display: none; align-items: center; gap: 6px; padding: 8px; font-size: 12px; color: #50575e; margin-top: 6px;">
                            <i class="fa-solid fa-spinner fa-spin" style="color: var(--wp-blue);"></i> AI is generating description...
                        </div>

                        <div id="aiDescResult" style="display: none; margin-top: 8px; padding: 10px; background: #f6f7f7; border: 1px solid var(--wp-border); border-radius: 4px;">
                            <p style="font-size: 11px; font-weight: 600; color: #50575e; margin-bottom: 4px;">Generated Description Preview:</p>
                            <textarea id="aiDescTextarea" rows="5" class="form-control" style="width: 100%; margin-bottom: 6px; font-size: 12px;"></textarea>
                            <button type="button" onclick="applyAiDescription()" id="applyDescBtn" class="button button-primary" style="font-size: 11px; height: 26px; padding: 2px 10px;">
                                Apply to Description Field
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Short Description -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Product Short Description</h2>
                </div>
                <div class="postbox-body">
                    <div class="form-group">
                        <textarea name="short_description" id="p_short_desc" class="form-control" rows="3"><?php echo sanitize_html($product['short_description']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Pricing & Inventory Details -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Pricing & Inventory</h2>
                </div>
                <div class="postbox-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="p_price">Regular Price (₹) <span style="color: var(--wp-error-red);">*</span></label>
                            <input type="number" step="0.01" name="price" id="p_price" class="form-control" value="<?php echo (float)$product['price']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="p_sale_price">Sale Price (₹)</label>
                            <input type="number" step="0.01" name="sale_price" id="p_sale_price" class="form-control" value="<?php echo $product['sale_price'] ? (float)$product['sale_price'] : ''; ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                        <div class="form-group">
                            <label for="p_sku">SKU (Stock Keeping Unit) <span style="color: var(--wp-error-red);">*</span></label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" name="sku" id="p_sku" class="form-control" value="<?php echo sanitize_html($product['sku']); ?>" required style="flex: 1;">
                                <button type="button" class="button" onclick="openSyncModal()" style="background-color: #8b2e3b; color: #fff; border-color: #72242e; white-space: nowrap;" title="Fetch product details from SriShringarr API">
                                    <i class="fa-solid fa-arrows-rotate"></i> Sync API
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="p_stock">Stock Quantity</label>
                            <input type="number" name="stock_qty" id="p_stock" class="form-control" value="<?php echo (int)$product['stock_qty']; ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Gallery (Multi-Image) -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Product Gallery (Multiple Images)</h2>
                </div>
                <div class="postbox-body">
                    <!-- Current Gallery Items -->
                    <?php if (!empty($gallery_images)): ?>
                        <p style="font-weight: 500; margin-bottom: 8px;">Current Gallery Images:</p>
                        <div class="gallery-grid" style="margin-bottom: 20px;">
                            <?php foreach ($gallery_images as $gidx => $gimg): ?>
                                <div class="gallery-item" id="gallery_item_<?php echo $gimg['id']; ?>" style="display: flex; flex-direction: column; align-items: center; background: #fff; padding: 6px; border: 1px solid var(--wp-border); border-radius: 4px;">
                                    <div style="position: relative; width: 100%; aspect-ratio: 1/1; overflow: hidden; border-radius: 3px;">
                                        <img src="<?php echo sanitize_html($gimg['thumb_path'] ?: $gimg['image_path']); ?>" alt="Gallery Image" style="width: 100%; height: 100%; object-fit: cover;">
                                        <div class="gallery-item-delete" onclick="markGalleryImageForDeletion(<?php echo $gimg['id']; ?>)" title="Remove this image">
                                            <i class="fa-solid fa-xmark"></i>
                                        </div>
                                    </div>
                                    <div style="margin-top: 6px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 4px;" title="Set rendering weight order on frontend">
                                        <span style="font-size: 10px; color: #50575e; font-weight: 600;">Weight:</span>
                                        <input type="number" min="0" name="image_weights[<?php echo $gimg['id']; ?>]" value="<?php echo (int)($gimg['sort_order'] ?? ($gidx + 1)); ?>" class="form-control" style="width: 50px; height: 24px; padding: 1px 4px; font-size: 11px; text-align: center;">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="image-upload-wrapper">
                        <i class="fa-solid fa-images"></i>
                        <p>Drag and drop or click here to upload additional gallery images</p>
                        <input type="file" name="gallery_images[]" id="gallery_input" multiple accept="image/*">
                    </div>
                    <!-- Live Gallery Previews (New uploads) -->
                    <div class="gallery-grid" id="gallery_preview_grid">
                        <!-- JS inserted items here -->
                    </div>
                </div>
            </div>

            <!-- AI Image Studio (Gemini) -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>AI Image Studio</h2>
                </div>
                <div class="postbox-body">
                    <p style="font-size: 12px; color: #50575e; margin-top: 0; margin-bottom: 15px;">Generate AI fashion model photos wearing this exact product.</p>

                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <!-- Face Reference Models -->
                        <div>
                            <label style="font-size: 12px; font-weight: 600; color: var(--wp-text-dark); margin-bottom: 6px; display: block;">Model Face (Optional)</label>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <label class="ai-model-picker">
                                    <input type="radio" name="ai_model_face" value="" checked class="ai-radio-hidden">
                                    <div class="ai-model-box" style="width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; background: #f6f7f7; border: 1px solid var(--wp-border); border-radius: 4px; cursor: pointer;">
                                        <span style="font-size: 10px; color: #50575e; font-weight: 600;">NONE</span>
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
                                    <div class="ai-model-box" style="width: 56px; height: 56px; border: 1px solid var(--wp-border); border-radius: 4px; overflow: hidden; cursor: pointer;">
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
                                    <div class="ai-model-box" style="width: 56px; height: 56px; border: 1px solid var(--wp-border); border-radius: 4px; overflow: hidden; cursor: pointer;" title="Model <?= $i ?>">
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
                            <label style="font-size: 12px; font-weight: 600; color: var(--wp-text-dark); margin-bottom: 6px; display: block;">Background / Props Preset</label>
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
                            <input type="text" id="ai_bg_custom" class="form-control" style="margin-top: 6px; width: 100%; font-size: 12px;" value="elegant royal palace with marble pillars and chandeliers" placeholder="Describe background and props...">
                        </div>

                        <!-- Shot & Hair Controls (Dynamic from Masters DB) -->
                        <?php
                        $db_shot_types = [];
                        $db_hair_styles = [];
                        try {
                            $db_shot_types = $pdo->query("SELECT * FROM ai_shot_types WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
                            $db_hair_styles = $pdo->query("SELECT * FROM ai_hair_styles WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {}
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label style="font-size: 12px; font-weight: 600; color: var(--wp-text-dark); margin-bottom: 6px; display: block;">Shot Type Master</label>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <?php
                                    if (!empty($db_shot_types)):
                                        $sIdx = 0;
                                        foreach ($db_shot_types as $st):
                                    ?>
                                        <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; color: var(--wp-text-dark);" title="<?php echo sanitize_html($st['prompt_text']); ?>">
                                            <input type="radio" name="ai_shot_type" value="<?php echo sanitize_html($st['prompt_text']); ?>" data-name="<?php echo sanitize_html($st['name']); ?>" <?php echo $sIdx === 0 ? 'checked' : ''; ?>>
                                            <?php echo sanitize_html($st['name']); ?>
                                        </label>
                                    <?php
                                        $sIdx++;
                                        endforeach;
                                    else:
                                    ?>
                                        <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; color: var(--wp-text-dark);"><input type="radio" name="ai_shot_type" value="close-up portrait shot focusing on the face and details" data-name="Close-up Portrait"> Close-up Portrait</label>
                                        <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; color: var(--wp-text-dark);"><input type="radio" name="ai_shot_type" value="half body shot from waist up, showing torso and face" data-name="Half Body"> Half Body</label>
                                        <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; color: var(--wp-text-dark);"><input type="radio" name="ai_shot_type" value="full body head-to-toe shot showing the complete outfit/jewelry look" data-name="Full Body" checked> Full Body</label>
                                        <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; color: var(--wp-text-dark);"><input type="radio" name="ai_shot_type" value="shot from behind showing the back design and details" data-name="Back View"> Back View</label>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label style="font-size: 12px; font-weight: 600; color: var(--wp-text-dark); margin-bottom: 6px; display: block;">Hair Style Master</label>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <?php
                                    if (!empty($db_hair_styles)):
                                        $hIdx = 0;
                                        foreach ($db_hair_styles as $hs):
                                    ?>
                                        <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; color: var(--wp-text-dark);" title="<?php echo sanitize_html($hs['prompt_text']); ?>">
                                            <input type="radio" name="ai_hair_style" value="<?php echo sanitize_html($hs['prompt_text']); ?>" data-name="<?php echo sanitize_html($hs['name']); ?>" <?php echo $hIdx === 0 ? 'checked' : ''; ?>>
                                            <?php echo sanitize_html($hs['name']); ?>
                                        </label>
                                    <?php
                                        $hIdx++;
                                        endforeach;
                                    else:
                                    ?>
                                        <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; color: var(--wp-text-dark);"><input type="radio" name="ai_hair_style" value="open flowing hair with soft waves" data-name="Open Flowing"> Open Flowing</label>
                                        <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; color: var(--wp-text-dark);"><input type="radio" name="ai_hair_style" value="neatly tied bun with gajra flowers" data-name="Tied / Bun"> Tied / Bun</label>
                                        <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; color: var(--wp-text-dark);"><input type="radio" name="ai_hair_style" value="traditional long braided hair" data-name="Traditional Braid"> Traditional Braid</label>
                                        <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; color: var(--wp-text-dark);"><input type="radio" name="ai_hair_style" value="" data-name="As per product" checked> Default</label>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Final Prompt Textarea -->
                        <div>
                            <label style="font-size: 12px; font-weight: 600; color: var(--wp-text-dark); margin-bottom: 6px; display: block;">Final Prompt (Auto-Assembled)</label>
                            <textarea id="ai_final_prompt" rows="3" class="form-control" style="width: 100%; font-size: 12px;">A photorealistic beautiful Indian fashion model wearing this exact product. The background should have elegant royal palace with marble pillars and chandeliers. Shot type: full body head-to-toe shot showing the complete outfit/jewelry look. Aspect ratio: 2:3 vertical fashion portrait format.</textarea>
                        </div>

                        <!-- Action Bar -->
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--wp-border); padding-top: 12px;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <label style="font-size: 12px; font-weight: 500;">Variations:</label>
                                <div style="display: flex; gap: 4px;">
                                    <?php for ($n = 1; $n <= 4; $n++): ?>
                                    <label class="ai-bg-picker">
                                        <input type="radio" name="ai_num_images" value="<?= $n ?>" <?= $n === 1 ? 'checked' : '' ?> class="ai-radio-hidden">
                                        <div class="ai-bg-pill"><?= $n ?> <?= $n === 1 ? 'Image' : 'Images' ?></div>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <button type="button" onclick="aiGenerateAdvancedImage()" id="aiImageBtn" class="button button-primary">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate Model Image
                            </button>
                        </div>

                        <!-- Loading Indicator -->
                        <div id="aiImageLoading" style="display: none; align-items: center; gap: 8px; padding: 10px; background: #f6f7f7; border: 1px solid var(--wp-border); border-radius: 4px; font-size: 12px; color: #50575e;">
                            <i class="fa-solid fa-spinner fa-spin" style="font-size: 14px; color: var(--wp-blue);"></i>
                            <span>AI is generating image(s)... Please wait 15-20 seconds.</span>
                        </div>

                        <!-- Generated Results -->
                        <div id="aiImageResult" style="display: none; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--wp-border);">
                            <p style="font-size: 12px; font-weight: 600; margin-bottom: 8px;">Generated Images:</p>
                            
                            <div id="aiImageGrid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-bottom: 10px;"></div>
                            
                            <div style="display: flex; justify-content: center;">
                                <button type="button" onclick="resetAiImage()" class="button button-small">
                                    <i class="fa-solid fa-rotate-left"></i> Clear & Try Again
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side Column -->
        <div class="side-column">
            
            <!-- Publish Actions -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Publish Settings</h2>
                </div>
                <div class="postbox-body">
                    <div class="form-group">
                        <label for="p_status">Status</label>
                        <select name="status" id="p_status" class="form-control">
                            <option value="published" <?php echo ($product['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo ($product['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 15px 0;">
                        <label style="display: flex; align-items: center; cursor: pointer; font-weight: normal;">
                            <input type="checkbox" name="is_featured" value="1" style="margin-right: 8px;" <?php echo ($product['is_featured'] == 1) ? 'checked' : ''; ?>>
                            <strong>Feature this product</strong>
                        </label>
                        <p style="font-size: 11px; color: #646970; margin-top: 4px;">Featured products show up in highlighted homepage widgets.</p>
                    </div>

                    <div style="border-top: 1px solid var(--wp-border); padding-top: 15px; display: flex; justify-content: space-between; gap: 10px;">
                        <a href="products.php?delete=<?php echo $product['id']; ?>" class="button button-danger delete-confirm" data-name="<?php echo sanitize_html($product['name']); ?>" style="padding: 6px 10px;">Delete</a>
                        <button type="submit" class="button button-primary" style="flex: 1;">Update Product</button>
                    </div>
                </div>
            </div>

            <!-- Categories -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Product Categories</h2>
                </div>
                <div class="postbox-body">
                    <div class="category-checklist-container" style="max-height: 200px; overflow-y: auto; border: 1px solid var(--wp-border); padding: 12px; background: #fff; border-radius: 4px; margin-bottom: 10px;">
                        <?php if (empty($categories)): ?>
                            <p style="color: #646970; font-size: 13px; margin: 0;">No categories created yet. <a href="categories.php">Create categories</a>.</p>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <div class="category-checklist-item" style="margin-left: <?php echo (isset($cat['depth']) ? $cat['depth'] * 15 : 0); ?>px; margin-bottom: 6px;">
                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--wp-text-dark); cursor: pointer;">
                                        <input type="checkbox" name="category_ids[]" id="cat_check_<?php echo $cat['id']; ?>" value="<?php echo $cat['id']; ?>" <?php echo in_array($cat['id'], $product_categories) ? 'checked' : ''; ?>>
                                        <?php echo sanitize_html($cat['name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="categories.php" style="text-decoration: none; font-size: 13px;"><i class="fa-solid fa-plus"></i> Add new category</a>
                    </div>
                </div>
            </div>

            <!-- Featured Image -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Main Product Image</h2>
                </div>
                <div class="postbox-body">
                    <div class="main-image-preview-container" style="text-align: center; margin-bottom: 12px;">
                        <?php if ($product['main_image']): ?>
                            <img id="main_image_preview" src="<?php echo sanitize_html($product['main_image']); ?>" alt="Main Image" style="max-width: 100%; max-height: 220px; border-radius: 4px; border: 1px solid var(--wp-border); object-fit: contain;">
                            <div id="main_image_placeholder" style="display: none;"></div>
                        <?php else: ?>
                            <img id="main_image_preview" src="" alt="Main Image Preview" style="display: none; max-width: 100%; max-height: 220px; border-radius: 4px; border: 1px solid var(--wp-border); object-fit: contain;">
                            <div id="main_image_placeholder" style="color: #8c8f94; padding: 20px 0;">
                                <i class="fa-regular fa-image" style="font-size: 40px; margin-bottom: 8px;"></i>
                                <p>No product image set</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <input type="hidden" name="selected_main_image_path" id="selected_main_image_path" value="">

                    <!-- 2 Replacement Options -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <button type="button" class="button" onclick="openSelectMainImageModal()" style="width: 100%; justify-content: center; font-size: 12px; height: 32px;">
                            <i class="fa-solid fa-images" style="color: var(--wp-blue);"></i> Replace from Available Images
                        </button>
                        <label class="button button-primary" style="width: 100%; text-align: center; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; height: 32px; margin: 0;">
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

<!-- Sync Confirmation Modal -->
<style>
.wp-editor-columns {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    width: 100%;
}
.main-column {
    flex: 1 1 0%;
    min-width: 0;
}
.side-column {
    width: 280px;
    min-width: 280px;
    max-width: 280px;
    flex: 0 0 280px;
}
@media (max-width: 991px) {
    .wp-editor-columns {
        flex-direction: column;
    }
    .side-column {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        flex: 1 1 auto !important;
    }
}

.ai-radio-hidden {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.ai-radio-hidden:checked + .ai-model-box {
    border-color: #2271b1 !important;
    box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.3);
}
.ai-bg-pill {
    padding: 4px 10px;
    background: #f6f7f7;
    border: 1px solid var(--wp-border);
    border-radius: 4px;
    font-size: 11px;
    color: var(--wp-text-dark);
    cursor: pointer;
    transition: all 0.15s ease;
}
.ai-bg-pill:hover {
    background: #f0f0f1;
    border-color: #8c8f94;
}
.ai-radio-hidden:checked + .ai-bg-pill {
    background: #2271b1;
    color: #fff;
    border-color: #135e96;
}

.sync-modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(3px);
}
.sync-modal-dialog {
    background: #fff;
    border-radius: 8px;
    width: 100%;
    max-width: 920px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.35);
    overflow: hidden;
}
.sync-modal-header {
    padding: 16px 24px;
    background: #1d2327;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.sync-modal-header h3 {
    margin: 0;
    font-size: 16px;
    color: #c8a55c;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}
.sync-modal-close {
    background: none;
    border: none;
    color: #a7aaad;
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
}
.sync-modal-close:hover { color: #fff; }
.sync-modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}
.sync-modal-footer {
    padding: 14px 24px;
    background: #f6f7f7;
    border-top: 1px solid #dcdcde;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}
.sync-compare-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
    font-size: 13px;
}
.sync-compare-table th {
    background: #f0f0f1;
    padding: 10px 12px;
    border: 1px solid #c3c4c7;
    text-align: left;
    font-weight: 600;
    color: #1d2327;
}
.sync-compare-table td {
    padding: 12px;
    border: 1px solid #dcdcde;
    vertical-align: top;
}
.sync-val-old { color: #d63638; font-weight: 500; }
.sync-val-new { color: #008a20; font-weight: 600; }
.sync-text-box {
    max-height: 140px;
    overflow-y: auto;
    white-space: pre-wrap;
    background: #f6f7f7;
    padding: 10px;
    border-radius: 4px;
    font-size: 12px;
    line-height: 1.5;
    border: 1px solid #e2e8f0;
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
    border-radius: 4px;
    border: 1px solid #dcdcde;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
</style>

<div id="sync_api_modal" class="sync-modal-overlay" style="display: none;">
    <div class="sync-modal-dialog">
        <div class="sync-modal-header">
            <h3><i class="fa-solid fa-cloud-arrow-down"></i> Sync Details from SriShringarr API</h3>
            <button type="button" class="sync-modal-close" onclick="closeSyncModal()">&times;</button>
        </div>
        
        <div class="sync-modal-body" id="sync_modal_body">
            <!-- Loading State -->
            <div id="sync_loading_state" style="text-align: center; padding: 40px;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 36px; color: #8b2e3b; margin-bottom: 15px;"></i>
                <p style="font-size: 14px; color: #50575e; margin: 0;">Fetching details from SriShringarr API for SKU: <strong id="sync_sku_label"></strong>...</p>
            </div>

            <!-- Content Comparison State -->
            <div id="sync_content_state" style="display: none;">
                <div style="background: #e8f0fe; border: 1px solid #aecbfa; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px; color: #1a73e8; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-info" style="font-size: 16px;"></i>
                    <span>Review the fetched external details below. Select the checkboxes for fields you wish to update in your database, then click <strong>Confirm & Update Product</strong>.</span>
                </div>

                <table class="sync-compare-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;"><input type="checkbox" id="sync_check_all" checked onclick="toggleAllSyncChecks(this)" title="Select / Deselect All"></th>
                            <th style="width: 130px;">Field</th>
                            <th style="width: 40%;">Current Local Data</th>
                            <th style="width: 50%;">Fetched API Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Product Name -->
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" id="chk_sync_name" class="sync-field-check" checked>
                            </td>
                            <td><strong>Product Name</strong></td>
                            <td><span id="sync_local_name" class="sync-val-old"></span></td>
                            <td><span id="sync_remote_name" class="sync-val-new"></span></td>
                        </tr>

                        <!-- Description -->
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" id="chk_sync_desc" class="sync-field-check" checked>
                            </td>
                            <td><strong>Description</strong></td>
                            <td><div id="sync_local_desc" class="sync-val-old sync-text-box"></div></td>
                            <td><div id="sync_remote_desc" class="sync-val-new sync-text-box"></div></td>
                        </tr>

                        <!-- Stock Quantity / Inventory -->
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" id="chk_sync_stock" class="sync-field-check" checked>
                            </td>
                            <td><strong>Stock Quantity (Inventory)</strong></td>
                            <td><span id="sync_local_stock" class="sync-val-old"></span></td>
                            <td><span id="sync_remote_stock" class="sync-val-new"></span></td>
                        </tr>

                        <!-- Images -->
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" id="chk_sync_images" class="sync-field-check" checked>
                            </td>
                            <td><strong>Images & Gallery</strong></td>
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
            <div id="sync_error_state" style="display: none; padding: 30px; text-align: center; color: var(--wp-error-red);">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 36px; margin-bottom: 12px;"></i>
                <p id="sync_error_msg" style="font-weight: 600; font-size: 14px; margin: 0;"></p>
            </div>

            <!-- Applying Progress State -->
            <div id="sync_applying_state" style="display: none; text-align: center; padding: 40px;">
                <i class="fa-solid fa-cloud-arrow-down fa-bounce" style="font-size: 40px; color: #8b2e3b; margin-bottom: 15px;"></i>
                <p style="font-size: 15px; font-weight: bold; color: #1d2327; margin-bottom: 6px;">Updating Product & Downloading High-Res Images...</p>
                <p style="font-size: 12px; color: #646970; margin: 0;">Please wait, downloading images from SriShringarr server into local product directory...</p>
            </div>
        </div>

        <div class="sync-modal-footer">
            <button type="button" class="button" onclick="closeSyncModal()">Cancel</button>
            <button type="button" class="button button-primary" id="btn_confirm_sync" style="background-color: #8b2e3b; border-color: #72242e;" onclick="executeSyncApply()">
                <i class="fa-solid fa-check"></i> Confirm & Update Product
            </button>
        </div>
    </div>
</div>

<!-- Modal: Select Main Image from Available Images -->
<div id="selectMainImageModal" class="sync-modal-overlay" style="display: none;">
    <div class="sync-modal-dialog" style="max-width: 600px; padding: 20px; background: #fff; border-radius: 8px; border: 1px solid var(--wp-border); color: var(--wp-text-dark);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--wp-border); padding-bottom: 12px; margin-bottom: 15px;">
            <h3 style="margin: 0; font-size: 15px; font-weight: 600; color: var(--wp-text-dark); display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-images" style="color: var(--wp-blue);"></i> Select Main Product Image
            </h3>
            <button type="button" onclick="closeSelectMainImageModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #646970;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <p style="font-size: 12px; color: #50575e; margin-bottom: 12px;">Click any image below to set it as the primary product image:</p>

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
            <div class="available-img-card" onclick="chooseMainImage('<?php echo sanitize_html($aimg['path']); ?>', this)" style="position: relative; aspect-ratio: 1/1; border: 2px solid var(--wp-border); border-radius: 4px; overflow: hidden; cursor: pointer; transition: all 0.15s ease;">
                <img src="<?php echo sanitize_html($aimg['thumb'] ?: $aimg['path']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php if (!empty($aimg['is_main'])): ?>
                    <span style="position: absolute; top: 4px; left: 4px; background: #2271b1; color: #fff; font-size: 9px; font-weight: 700; padding: 2px 5px; border-radius: 3px;">CURRENT MAIN</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; border-top: 1px solid var(--wp-border); padding-top: 12px;">
            <button type="button" class="button" onclick="closeSelectMainImageModal()">Cancel</button>
            <button type="button" id="confirmMainImgBtn" class="button button-primary" onclick="confirmSelectedMainImage()" disabled>
                <i class="fa-solid fa-check"></i> Set as Main Image
            </button>
        </div>
    </div>
</div>

<script>
let pendingMainImagePath = '';

function openSelectMainImageModal() {
    document.getElementById('selectMainImageModal').style.display = 'flex';
}

function closeSelectMainImageModal() {
    document.getElementById('selectMainImageModal').style.display = 'none';
    pendingMainImagePath = '';
}

function chooseMainImage(imgPath, cardEl) {
    pendingMainImagePath = imgPath;
    document.querySelectorAll('.available-img-card').forEach(c => {
        c.style.borderColor = 'var(--wp-border)';
        c.style.boxShadow = 'none';
    });
    cardEl.style.borderColor = '#2271b1';
    cardEl.style.boxShadow = '0 0 0 2px rgba(34, 113, 177, 0.4)';
    document.getElementById('confirmMainImgBtn').disabled = false;
}

function confirmSelectedMainImage() {
    if (!pendingMainImagePath) return;
    
    document.getElementById('selected_main_image_path').value = pendingMainImagePath;
    
    const preview = document.getElementById('main_image_preview');
    const placeholder = document.getElementById('main_image_placeholder');
    if (preview) {
        preview.src = pendingMainImagePath;
        preview.style.display = 'inline-block';
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
        alert('Please enter a Product SKU first.');
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
        alert('Please select at least one field to sync.');
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
                <button type="button" onclick="applyProductName('${name.replace(/'/g, "\\'")}')" class="button button-small" style="width: 100%; text-align: left; background: #ffffff; color: var(--wp-text-dark); border-color: #c3c4c7; font-size: 11px; padding: 4px 8px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="white-space: normal; line-height: 1.3;">${name}</span>
                    <i class="fa-solid fa-check" style="font-size: 10px; color: var(--wp-blue); flex-shrink: 0; margin-left: 6px;"></i>
                </button>
            `).join('');
            document.getElementById('aiNamesResult').style.display = 'block';
        } else {
            alert('Error: ' + (data.error || 'Failed to generate names'));
        }
    } catch (err) {
        console.error(err);
        alert('A network error occurred while generating product names.');
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
        nameInput.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.4)';
        setTimeout(() => { nameInput.style.boxShadow = ''; }, 1000);
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
        } else {
            alert('Error: ' + (data.error || 'Failed to generate description'));
        }
    } catch (err) {
        console.error(err);
        alert('A network error occurred while generating description.');
    } finally {
        btn.disabled = false;
        hideEl('aiLoading');
    }
}

function applyAiDescription() {
    const val = document.getElementById('aiDescTextarea').value.trim();
    const descInput = document.getElementById('p_desc');
    if (descInput && val) {
        descInput.value = val;
        descInput.focus();
        descInput.style.transition = 'all 0.3s ease';
        descInput.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.4)';
        setTimeout(() => { descInput.style.boxShadow = ''; }, 1000);
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
                    <div style="display: flex; flex-direction: column; gap: 8px; background: #ffffff; border: 1px solid var(--wp-border); padding: 8px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <img src="data:image/jpeg;base64,${b64}" style="width: 100%; aspect-ratio: 2/3; object-fit: cover; border-radius: 4px;">
                        <button type="button" onclick="saveAiGeneratedImage(this, '${b64}')" class="button button-primary" style="width: 100%; justify-content: center; padding: 6px; font-size: 11px; background: #008a20; border-color: #00701a;">
                            <i class="fa-solid fa-floppy-disk"></i> Save Image ${index + 1}
                        </button>
                    </div>
                `;
            });
            document.getElementById('aiImageResult').style.display = 'block';
        } else {
            alert('Error: ' + (data.error || 'Failed to generate model images'));
        }
    } catch (err) {
        console.error(err);
        alert('A network error occurred while generating images.');
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
            alert('Error saving image: ' + (data.error || 'Unknown error'));
            btn.innerHTML = origHTML;
            btn.disabled = false;
        }
    } catch (err) {
        console.error(err);
        alert('Network error while saving AI image.');
        btn.innerHTML = origHTML;
        btn.disabled = false;
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
