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

                // Handle Main Image replacement
                $main_image_path = $product['main_image'];
                if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                    $upload = upload_image($_FILES['main_image'], 'uploads/products/' . $sku, 'main');
                    if (is_array($upload) && isset($upload['filepath'])) {
                        // Delete previous main image from disk
                        if ($product['main_image'] && file_exists(__DIR__ . '/../' . $product['main_image'])) {
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

<div class="wrap-header">
    <h1>Edit Product: <?php echo sanitize_html($product['name']); ?></h1>
    <div style="display: flex; gap: 8px; align-items: center;">
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
                        <label for="p_name">Product Name <span style="color: var(--wp-error-red);">*</span></label>
                        <input type="text" name="name" id="p_name" class="form-control" value="<?php echo sanitize_html($product['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="p_slug">Slug (URL identifier)</label>
                        <input type="text" name="slug" id="p_slug" class="form-control" value="<?php echo sanitize_html($product['slug']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="p_desc">Detailed Description</label>
                        <textarea name="description" id="p_desc" class="form-control" rows="8"><?php echo sanitize_html($product['description']); ?></textarea>
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
                            <?php foreach ($gallery_images as $gimg): ?>
                                <div class="gallery-item" id="gallery_item_<?php echo $gimg['id']; ?>">
                                    <img src="<?php echo sanitize_html($gimg['thumb_path']); ?>" alt="Gallery Image">
                                    <div class="gallery-item-delete" onclick="markGalleryImageForDeletion(<?php echo $gimg['id']; ?>)" title="Remove this image">
                                        <i class="fa-solid fa-xmark"></i>
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
                    <div class="main-image-preview-container">
                        <?php if ($product['main_image']): ?>
                            <img id="main_image_preview" src="<?php echo sanitize_html($product['main_image']); ?>" alt="Main Image">
                            <div id="main_image_placeholder" style="display: none;"></div>
                        <?php else: ?>
                            <img id="main_image_preview" src="" alt="Main Image Preview" style="display: none;">
                            <div id="main_image_placeholder" style="color: #8c8f94; padding: 20px 0;">
                                <i class="fa-regular fa-image" style="font-size: 40px; margin-bottom: 8px;"></i>
                                <p>No product image set</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="image-upload-wrapper" style="padding: 10px; border-style: solid; border-width: 1px;">
                        <p><i class="fa-solid fa-cloud-arrow-up" style="font-size: 16px; margin: 0 5px 0 0;"></i> Replace Main Image</p>
                        <input type="file" name="main_image" id="main_image_input" accept="image/*">
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

<script>
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
    const syncImages = document.getElementById('chk_sync_images').checked;

    if (!syncName && !syncDesc && !syncImages) {
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
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
