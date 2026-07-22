<?php
// admin/product-add.php
$page_title = "Add New Product";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$message = '';
$message_type = 'success';

// Fetch categories for the list box
try {
    $categories_raw = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
    $categories = get_category_tree($categories_raw);
} catch (PDOException $e) {
    $categories = [];
    $message = "Error loading categories: " . $e->getMessage();
    $message_type = "error";
}

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
            // Check unique SKU
            $check_sku = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ?");
            $check_sku->execute([$sku]);
            
            // Check unique Slug
            $check_slug = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
            $check_slug->execute([$slug]);

            if ($check_sku->fetchColumn() > 0) {
                $message = "Product SKU already exists. Please use a unique SKU.";
                $message_type = "error";
            } elseif ($check_slug->fetchColumn() > 0) {
                $slug .= '-' . time();
            }

            if ($message_type !== 'error') {
                // Handle Main Image Upload
                $main_image_path = null;
                if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                    $upload = upload_image($_FILES['main_image'], 'uploads/products/' . $sku, 'main');
                    if (is_array($upload) && isset($upload['filepath'])) {
                        $main_image_path = $upload['filepath'];
                    } elseif (is_array($upload) && isset($upload['error'])) {
                        $message = $upload['error'];
                        $message_type = 'error';
                    }
                }

                if ($message_type !== 'error') {
                    // Start Transaction
                    $pdo->beginTransaction();

                    $primary_category_id = !empty($category_ids) ? (int)$category_ids[0] : null;

                    // Insert Product
                    $sql = "INSERT INTO products 
                        (category_id, name, slug, sku, description, short_description, price, sale_price, stock_qty, is_featured, status, main_image) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
                        $main_image_path
                    ]);

                    $product_id = $pdo->lastInsertId();

                    // Insert Categories
                    if (!empty($category_ids)) {
                        $cat_stmt = $pdo->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
                        foreach ($category_ids as $cat_id) {
                            $cat_stmt->execute([$product_id, (int)$cat_id]);
                        }
                    }

                    // Handle Gallery Multi-Images Upload
                    if (isset($_FILES['gallery_images']) && !empty($_FILES['gallery_images']['name'][0])) {
                        $gallery_files = $_FILES['gallery_images'];
                        $file_count = count($gallery_files['name']);
                        
                        $ins_gallery = $pdo->prepare("INSERT INTO product_images (product_id, image_path, thumb_path, sort_order) VALUES (?, ?, ?, ?)");
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            // Re-format file array for helper function
                            $single_file = [
                                'name' => $gallery_files['name'][$i],
                                'type' => $gallery_files['type'][$i],
                                'tmp_name' => $gallery_files['tmp_name'][$i],
                                'error' => $gallery_files['error'][$i],
                                'size' => $gallery_files['size'][$i]
                            ];

                            if ($single_file['error'] === UPLOAD_ERR_OK) {
                                $upload_gal = upload_image($single_file, 'uploads/products/' . $sku, 'gallery_' . $i);
                                if (is_array($upload_gal) && isset($upload_gal['filepath'])) {
                                    $ins_gallery->execute([
                                        $product_id,
                                        $upload_gal['filepath'],
                                        $upload_gal['thumbpath'],
                                        $i // sort order
                                    ]);
                                }
                            }
                        }
                    }

                    $pdo->commit();
                    redirect('products.php?message=added');
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Database error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}
?>

<div class="wrap-header">
    <h1>Add New Product</h1>
    <a href="products.php" class="button">Back to Products</a>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?>">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<form action="product-add.php" method="POST" enctype="multipart/form-data">
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
                        <input type="text" name="name" id="p_name" class="form-control" placeholder="Enter product title..." required>
                    </div>

                    <div class="form-group">
                        <label for="p_slug">Slug (URL identifier)</label>
                        <input type="text" name="slug" id="p_slug" class="form-control" placeholder="auto-generated-if-blank">
                    </div>

                    <div class="form-group">
                        <label for="p_desc">Detailed Description</label>
                        <textarea name="description" id="p_desc" class="form-control" rows="8" placeholder="Provide a beautiful description of the product..."></textarea>
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
                        <textarea name="short_description" id="p_short_desc" class="form-control" rows="3" placeholder="Brief summary (e.g. materials, dimensions, or highlights)..."></textarea>
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
                            <input type="number" step="0.01" name="price" id="p_price" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="p_sale_price">Sale Price (₹)</label>
                            <input type="number" step="0.01" name="sale_price" id="p_sale_price" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                        <div class="form-group">
                            <label for="p_sku">SKU (Stock Keeping Unit) <span style="color: var(--wp-error-red);">*</span></label>
                            <input type="text" name="sku" id="p_sku" class="form-control" placeholder="e.g. YN-BW-LEH-01" required>
                        </div>
                        <div class="form-group">
                            <label for="p_stock">Stock Quantity</label>
                            <input type="number" name="stock_qty" id="p_stock" class="form-control" value="0">
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
                    <div class="image-upload-wrapper">
                        <i class="fa-solid fa-images"></i>
                        <p>Drag and drop or click here to upload multiple gallery images</p>
                        <input type="file" name="gallery_images[]" id="gallery_input" multiple accept="image/*">
                    </div>
                    <!-- Live Gallery Previews -->
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
                            <option value="published">Published</option>
                            <option value="draft" selected>Draft</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 15px 0;">
                        <label style="display: flex; align-items: center; cursor: pointer; font-weight: normal;">
                            <input type="checkbox" name="is_featured" value="1" style="margin-right: 8px;">
                            <strong>Feature this product</strong>
                        </label>
                        <p style="font-size: 11px; color: #646970; margin-top: 4px;">Featured products show up in highlighted homepage widgets.</p>
                    </div>

                    <div style="border-top: 1px solid var(--wp-border); padding-top: 15px; display: flex; justify-content: space-between;">
                        <button type="submit" class="button button-primary" style="flex: 1;">Publish Product</button>
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
                                        <input type="checkbox" name="category_ids[]" id="cat_check_<?php echo $cat['id']; ?>" value="<?php echo $cat['id']; ?>">
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
                        <img id="main_image_preview" src="" alt="Main Image Preview" style="display: none;">
                        <div id="main_image_placeholder" style="color: #8c8f94; padding: 20px 0;">
                            <i class="fa-regular fa-image" style="font-size: 40px; margin-bottom: 8px;"></i>
                            <p>No product image set</p>
                        </div>
                    </div>
                    <div class="image-upload-wrapper" style="padding: 10px; border-style: solid; border-width: 1px;">
                        <p><i class="fa-solid fa-cloud-arrow-up" style="font-size: 16px; margin: 0 5px 0 0;"></i> Set Main Image</p>
                        <input type="file" name="main_image" id="main_image_input" accept="image/*">
                    </div>
                </div>
            </div>

        </div>

    </div>
</form>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
