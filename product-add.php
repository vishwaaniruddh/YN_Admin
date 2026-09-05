<?php
// admin/product-add.php
$page_title = "Add New Product";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cache.php';
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
                                        $i
                                    ]);
                                }
                            }
                        }
                    }

                    $pdo->commit();

                    if (function_exists('purge_cache')) {
                        purge_cache();
                    }

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

<!-- Header Banner -->
<div class="dashboard-header-banner" style="margin-bottom: 24px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting">
            <h1>Add New Product</h1>
            <span class="shadcn-badge shadcn-badge-sky" style="font-size: 11px; padding: 3px 8px;">
                <i class="fa-solid fa-sparkles" style="margin-right: 4px;"></i> New Item
            </span>
        </div>
        <p class="dashboard-subtitle">
            Create and publish a new jewelry item in the catalog.
        </p>
    </div>
    <div class="dashboard-actions">
        <a href="products.php" class="shadcn-btn shadcn-btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Back to Products
        </a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<form action="product-add.php" method="POST" enctype="multipart/form-data">
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
                        <label for="p_name">
                            Product Name <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="name" id="p_name" class="form-control" placeholder="e.g. Royal Kundan Bridal Choker Necklace" required style="width: 100%; font-size: 14px;">
                    </div>

                    <div class="form-group">
                        <label for="p_slug">Slug (URL identifier)</label>
                        <div style="position: relative;">
                            <input type="text" name="slug" id="p_slug" class="form-control" placeholder="auto-generated-from-name" style="width: 100%; font-family: monospace; color: #52525b;">
                        </div>
                        <span style="font-size: 11.5px; color: #71717a; margin-top: 4px; display: block;">Leave blank to automatically generate from product name.</span>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="p_desc">Detailed Description</label>
                        <textarea name="description" id="p_desc" class="form-control" rows="8" placeholder="Provide a detailed description of the product, design craftsmanship, materials, and styling tips..." style="width: 100%; resize: vertical;"></textarea>
                    </div>
                </div>
            </div>

            <!-- Short Description Card -->
            <div class="shadcn-card" style="margin-bottom: 24px;">
                <div class="shadcn-card-header">
                    <h2 class="shadcn-card-title">
                        <i class="fa-solid fa-align-left" style="color: #71717a;"></i>
                        Short Description &amp; Highlights
                    </h2>
                </div>
                <div class="shadcn-card-padded">
                    <div class="form-group" style="margin-bottom: 0;">
                        <textarea name="short_description" id="p_short_desc" class="form-control" rows="3" placeholder="Brief summary (e.g. materials, dimensions, closure type, or key styling highlights)..." style="width: 100%; resize: vertical;"></textarea>
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
                            <input type="number" step="0.01" name="price" id="p_price" class="form-control" placeholder="0.00" required style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="p_sale_price">Sale Price (₹)</label>
                            <input type="number" step="0.01" name="sale_price" id="p_sale_price" class="form-control" placeholder="Optional discounted price" style="width: 100%;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="p_sku">
                                SKU (Stock Keeping Unit) <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="text" name="sku" id="p_sku" class="form-control" placeholder="e.g. YN-KUN-CH-01" required style="width: 100%; font-family: monospace;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="p_stock">Stock Quantity</label>
                            <input type="number" name="stock_qty" id="p_stock" class="form-control" value="0" min="0" style="width: 100%;">
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
                    <div class="image-upload-wrapper" style="border: 2px dashed #e4e4e7; background: #fafafa; border-radius: 8px; padding: 32px 20px; text-align: center; cursor: pointer; transition: all 0.2s ease;">
                        <div style="width: 44px; height: 44px; border-radius: 10px; background: #ffffff; border: 1px solid #e4e4e7; display: inline-flex; align-items: center; justify-content: center; color: #71717a; font-size: 20px; margin-bottom: 10px;">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </div>
                        <p style="font-size: 13px; font-weight: 500; color: #09090b; margin: 0 0 4px 0;">Click or drag &amp; drop to upload gallery images</p>
                        <p style="font-size: 11.5px; color: #71717a; margin: 0;">PNG, JPG, WEBP up to 5MB each</p>
                        <input type="file" name="gallery_images[]" id="gallery_input" multiple accept="image/*">
                    </div>
                    <!-- Live Gallery Previews -->
                    <div class="gallery-grid" id="gallery_preview_grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; margin-top: 16px;"></div>
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
                            <option value="draft" selected>Draft (Hidden)</option>
                            <option value="published">Published (Live in Store)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 16px 0; background: #fafafa; padding: 12px; border-radius: 6px; border: 1px solid #e4e4e7;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0; font-size: 13px;">
                            <input type="checkbox" name="is_featured" value="1" style="width: 16px; height: 16px; accent-color: #09090b;">
                            <span style="font-weight: 600; color: #09090b;">
                                <i class="fa-solid fa-star" style="color: #eab308; margin-right: 4px;"></i> Feature this product
                            </span>
                        </label>
                        <p style="font-size: 11px; color: #71717a; margin: 6px 0 0 24px; line-height: 1.3;">
                            Featured products appear on homepage showcases.
                        </p>
                    </div>

                    <button type="submit" class="shadcn-btn shadcn-btn-primary" style="width: 100%; height: 38px;">
                        <i class="fa-solid fa-check"></i> Save &amp; Publish Product
                    </button>
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
                            <p style="color: #71717a; font-size: 12.5px; margin: 0;">No categories available.</p>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <div class="category-checklist-item" style="margin-left: <?php echo (isset($cat['depth']) ? $cat['depth'] * 14 : 0); ?>px; margin-bottom: 8px;">
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #09090b; cursor: pointer;">
                                        <input type="checkbox" name="category_ids[]" id="cat_check_<?php echo $cat['id']; ?>" value="<?php echo $cat['id']; ?>" style="width: 15px; height: 15px; accent-color: #09090b;">
                                        <span><?php echo sanitize_html($cat['name']); ?></span>
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
                        <img id="main_image_preview" src="" alt="Main Image Preview" style="display: none; max-width: 100%; height: 220px; object-fit: contain; border-radius: 6px; border: 1px solid #e4e4e7; margin: 0 auto;">
                        <div id="main_image_placeholder" style="background: #fafafa; border: 1px dashed #e4e4e7; border-radius: 8px; padding: 32px 16px; color: #71717a;">
                            <i class="fa-regular fa-image" style="font-size: 36px; margin-bottom: 8px; color: #a1a1aa; display: block;"></i>
                            <span style="font-size: 12.5px;">No primary image set</span>
                        </div>
                    </div>
                    <div class="image-upload-wrapper" style="border: 1px solid #e4e4e7; border-radius: 6px; padding: 10px; background: #ffffff; text-align: center;">
                        <p style="font-size: 12.5px; font-weight: 500; color: #09090b; margin: 0;">
                            <i class="fa-solid fa-upload" style="margin-right: 6px;"></i> Choose Primary Image
                        </p>
                        <input type="file" name="main_image" id="main_image_input" accept="image/*">
                    </div>
                </div>
            </div>

        </div>

    </div>
</form>

<script>
// Main image live preview
document.getElementById('main_image_input')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(evt) {
            const preview = document.getElementById('main_image_preview');
            const placeholder = document.getElementById('main_image_placeholder');
            preview.src = evt.target.result;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
});

// Gallery live preview
document.getElementById('gallery_input')?.addEventListener('change', function(e) {
    const grid = document.getElementById('gallery_preview_grid');
    grid.innerHTML = '';
    const files = Array.from(e.target.files);
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = function(evt) {
            const div = document.createElement('div');
            div.style.cssText = 'position: relative; border: 1px solid #e4e4e7; border-radius: 6px; padding: 4px; background: #ffffff;';
            div.innerHTML = `<img src="${evt.target.result}" style="width: 100%; height: 80px; object-fit: cover; border-radius: 4px;">`;
            grid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
