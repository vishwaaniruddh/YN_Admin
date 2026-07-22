<?php
// admin/import-link.php
$page_title = "Import Product from Link";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$message = '';
$message_type = 'success';

$scraped_data = null;
$url = '';
$categories = [];

// Fetch categories for local catalog mapping
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $message = "Error loading categories: " . $e->getMessage();
    $message_type = "error";
}

// 1. Handle Fetch URL Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch') {
    $url = trim($_POST['url'] ?? '');
    
    if (empty($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        $message = "Please enter a valid HTTP/HTTPS URL.";
        $message_type = "error";
    } else {
        $scraped_data = scrape_product_from_url($url);
        if (!$scraped_data || empty($scraped_data['name'])) {
            $message = "Could not automatically scrape product details. The site might be protected or uses an unsupported layout. You can try another link.";
            $message_type = "error";
            $scraped_data = null;
        } else {
            // Generate dummy SKU if empty
            if (empty($scraped_data['sku'])) {
                $scraped_data['sku'] = 'SCR-' . strtoupper(substr(uniqid(), -6));
            }
            $message = "Product details successfully scraped! Review and customize before importing.";
            $message_type = "success";
        }
    }
}

// 2. Handle Import Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $url = trim($_POST['url'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $price = (float)($_POST['price'] ?? 0.0);
    $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $stock_qty = (int)($_POST['stock_qty'] ?? 0);
    $category_ids = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? $_POST['category_ids'] : [];
    $description = trim($_POST['description'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    $scraped_main_image = trim($_POST['scraped_main_image'] ?? '');
    $selected_gallery_urls = $_POST['scraped_gallery_images'] ?? [];

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
                // Download Main Image locally
                $main_image_path = null;
                if (!empty($scraped_main_image)) {
                    $downloaded_main = download_remote_image($scraped_main_image, 'uploads/products/' . $sku, 'main');
                    if (is_array($downloaded_main) && isset($downloaded_main['filepath'])) {
                        $main_image_path = $downloaded_main['filepath'];
                    }
                }

                // Start Transaction
                $pdo->beginTransaction();

                // Insert Product
                $sql = "INSERT INTO products 
                    (category_id, name, slug, sku, description, price, sale_price, stock_qty, is_featured, status, main_image) 
                    VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $name,
                    $slug,
                    $sku,
                    $description,
                    $price,
                    $sale_price,
                    $stock_qty,
                    $is_featured,
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

                // Handle Gallery Images (Download locally)
                if (!empty($selected_gallery_urls)) {
                    $ins_gallery = $pdo->prepare("INSERT INTO product_images (product_id, image_path, thumb_path, sort_order) VALUES (?, ?, ?, ?)");
                    $sort_order = 0;
                    
                    foreach ($selected_gallery_urls as $gal_url) {
                        $downloaded_gal = download_remote_image($gal_url, 'uploads/products/' . $sku);
                        if (is_array($downloaded_gal) && isset($downloaded_gal['filepath'])) {
                            $ins_gallery->execute([
                                $product_id,
                                $downloaded_gal['filepath'],
                                $downloaded_gal['thumbpath'],
                                $sort_order++
                            ]);
                        }
                    }
                }

                $pdo->commit();
                redirect('products.php?message=imported');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Error importing product: " . $e->getMessage();
            $message_type = "error";
        }
    }
}
?>

<div class="wrap-header">
    <h1>Import Product from Link</h1>
    <a href="products.php" class="button">Back to Products</a>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?>">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<!-- Step 1: Input URL Form -->
<?php if ($scraped_data === null): ?>
    <div class="postbox" style="max-width: 800px; margin: 0 auto 20px;">
        <div class="postbox-header">
            <h2><i class="fa-solid fa-link"></i> Enter Existing Product URL</h2>
        </div>
        <div class="postbox-body">
            <form action="import-link.php" method="POST">
                <input type="hidden" name="action" value="fetch">
                
                <div class="form-group">
                    <label for="import_url">Product Link (URL)</label>
                    <input type="url" name="url" id="import_url" class="form-control" placeholder="https://example.com/product/bridal-necklace" value="<?php echo sanitize_html($url); ?>" required>
                    <p style="font-size: 11px; color: #646970; margin-top: 6px;">
                        Paste the full link to the product details page from your existing website. The script will try to fetch the product title, description, SKU, and image files automatically.
                    </p>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="button button-primary">
                        <i class="fa-solid fa-gears"></i> Fetch Product Details
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Step 2: Edit & Confirm Import Form -->
<?php if ($scraped_data !== null): ?>
    <form action="import-link.php" method="POST">
        <input type="hidden" name="action" value="import">
        <input type="hidden" name="url" value="<?php echo sanitize_html($url); ?>">
        <input type="hidden" name="scraped_main_image" value="<?php echo sanitize_html($scraped_data['main_image']); ?>">

        <div class="wp-editor-columns">
            
            <!-- Left Main Content Column -->
            <div class="main-column">
                <!-- Product Details -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Import Details</h2>
                    </div>
                    <div class="postbox-body">
                        <div class="form-group">
                            <label for="p_name">Product Name <span style="color: var(--wp-error-red);">*</span></label>
                            <input type="text" name="name" id="p_name" class="form-control" value="<?php echo sanitize_html($scraped_data['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="p_slug">Slug</label>
                            <input type="text" name="slug" id="p_slug" class="form-control" placeholder="auto-generated-if-blank">
                        </div>

                        <div class="form-group">
                            <label for="p_desc">Description</label>
                            <textarea name="description" id="p_desc" class="form-control" rows="8"><?php echo sanitize_html($scraped_data['description']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Pricing & Inventory -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Pricing & Inventory</h2>
                    </div>
                    <div class="postbox-body">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label for="p_price">Regular Price (₹) <span style="color: var(--wp-error-red);">*</span></label>
                                <input type="number" step="0.01" name="price" id="p_price" class="form-control" value="<?php echo (float)$scraped_data['price']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="p_sale_price">Sale Price (₹)</label>
                                <input type="number" step="0.01" name="sale_price" id="p_sale_price" class="form-control">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                            <div class="form-group">
                                <label for="p_sku">SKU <span style="color: var(--wp-error-red);">*</span></label>
                                <input type="text" name="sku" id="p_sku" class="form-control" value="<?php echo sanitize_html($scraped_data['sku']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="p_stock">Stock Quantity</label>
                                <input type="number" name="stock_qty" id="p_stock" class="form-control" value="5">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gallery Selection -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Select Gallery Images to Import</h2>
                    </div>
                    <div class="postbox-body">
                        <?php if (empty($scraped_data['gallery_images'])): ?>
                            <p style="color: #646970; font-size: 12px; text-align: center; padding: 20px;">No additional gallery images detected.</p>
                        <?php else: ?>
                            <p style="font-size: 13px; color: #646970; margin-bottom: 12px;">Choose which image files to download to your local library:</p>
                            <div class="gallery-grid">
                                <?php foreach ($scraped_data['gallery_images'] as $key => $gal_url): ?>
                                    <div class="gallery-item" style="padding: 10px; text-align: center;">
                                        <img src="<?php echo sanitize_html($gal_url); ?>" alt="Gallery" style="height: 100px; object-fit: contain;">
                                        <div style="margin-top: 8px;">
                                            <label style="cursor: pointer; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; width: 100%;">
                                                <input type="checkbox" name="scraped_gallery_images[]" value="<?php echo sanitize_html($gal_url); ?>" checked style="margin-right: 4px;">
                                                Import
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Side Column -->
            <div class="side-column">
                <!-- Import Actions -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Import Settings</h2>
                    </div>
                    <div class="postbox-body">
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; cursor: pointer; font-weight: normal;">
                                <input type="checkbox" name="is_featured" value="1" style="margin-right: 8px;">
                                <strong>Feature this product</strong>
                            </label>
                        </div>

                        <div style="border-top: 1px solid var(--wp-border); padding-top: 15px; display: flex; gap: 10px;">
                            <a href="import-link.php" class="button" style="flex: 1; text-align: center;">Cancel</a>
                            <button type="submit" class="button button-primary" style="flex: 2;">Import Product</button>
                        </div>
                    </div>
                </div>

                <!-- Category Assignment -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Select Category</h2>
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

                <!-- Main Image Preview -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Main Image Preview</h2>
                    </div>
                    <div class="postbox-body">
                        <div class="main-image-preview-container">
                            <?php if ($scraped_data['main_image']): ?>
                                <img src="<?php echo sanitize_html($scraped_data['main_image']); ?>" alt="Scraped Main Image" style="max-width: 100%; max-height: 200px;">
                            <?php else: ?>
                                <div style="color: #8c8f94; padding: 20px 0; text-align: center;">
                                    <i class="fa-regular fa-image" style="font-size: 40px; margin-bottom: 8px;"></i>
                                    <p>No main image detected</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 11px; color: #646970; line-height: 1.4; text-align: center;">
                            The main image will be automatically downloaded, named, and cropped to a square thumbnail.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </form>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
