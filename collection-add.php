<?php
// admin/collection-add.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$message_type = 'error';

// Scan available server images from uploads/collections for the library picker
$available_images = [];
$collections_dir = __DIR__ . '/uploads/collections';

function scan_collection_images_recursive($dir, $base_dir, &$results) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            scan_collection_images_recursive($path, $base_dir, $results);
        } elseif (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $item)) {
            $rel = str_replace($base_dir . '/', '', $path);
            $results[] = 'uploads/collections/' . $rel;
        }
    }
}
scan_collection_images_recursive($collections_dir, $collections_dir, $available_images);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $category = trim($_POST['category'] ?? 'Client Diaries');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'published';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $selected_library_images = $_POST['library_images'] ?? [];
    $cover_choice = $_POST['cover_choice'] ?? '';

    if (empty($title)) {
        $message = "Collection Title is required.";
    } else {
        $slug = generate_slug($title);
        // Ensure unique slug
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM collections WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() > 0) {
            $slug .= '-' . time();
        }

        try {
            $pdo->beginTransaction();

            $cover_image_path = null;

            // 1. Handle Direct Upload Cover
            if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
                $upload = upload_image($_FILES['cover_file'], 'uploads/collections/custom', $slug . '-cover');
                if (is_array($upload) && isset($upload['filepath'])) {
                    $cover_image_path = $upload['filepath'];
                }
            } elseif (!empty($cover_choice)) {
                $cover_image_path = $cover_choice;
            }

            // 2. Insert Collection record
            $stmt = $pdo->prepare("INSERT INTO collections (title, slug, subtitle, category, description, cover_image, is_featured, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $subtitle, $category, $description, $cover_image_path, $is_featured, $sort_order, $status]);
            $collection_id = $pdo->lastInsertId();

            $ins_img = $pdo->prepare("INSERT INTO collection_images (collection_id, image_path, caption, outfit_type, sort_order) VALUES (?, ?, ?, ?, ?)");
            $img_order = 1;

            // 3. Attach selected library images
            if (!empty($selected_library_images) && is_array($selected_library_images)) {
                foreach ($selected_library_images as $lib_img) {
                    $caption = pathinfo($lib_img, PATHINFO_FILENAME);
                    $ins_img->execute([$collection_id, $lib_img, $caption, $category, $img_order++]);
                    if (!$cover_image_path) {
                        $cover_image_path = $lib_img;
                    }
                }
            }

            // 4. Handle Direct File Multi-Upload
            if (isset($_FILES['gallery_files']) && !empty($_FILES['gallery_files']['name'][0])) {
                $files = $_FILES['gallery_files'];
                $count = count($files['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $single_file = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];
                        $upload = upload_image($single_file, 'uploads/collections/' . $slug, 'photo_' . time() . '_' . $i);
                        if (is_array($upload) && isset($upload['filepath'])) {
                            $caption = pathinfo($files['name'][$i], PATHINFO_FILENAME);
                            $ins_img->execute([$collection_id, $upload['filepath'], $caption, $category, $img_order++]);
                            if (!$cover_image_path) {
                                $cover_image_path = $upload['filepath'];
                            }
                        }
                    }
                }
            }

            // Update cover image if it was picked from first attached item
            if ($cover_image_path) {
                $pdo->prepare("UPDATE collections SET cover_image = ? WHERE id = ?")->execute([$cover_image_path, $collection_id]);
            }

            $pdo->commit();
            log_activity($pdo, 'add_collection', 'collection', $collection_id, "Created collection '$title'");
            header("Location: collections.php?msg=created");
            exit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Error creating collection: " . $e->getMessage();
        }
    }
}

$page_title = "Add New Collection";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap">
    
    <!-- WordPress Standard Header -->
    <h1 class="wp-heading-inline">Add New Collection</h1>
    <a href="collections.php" class="page-title-action">&larr; Back to Collections</a>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-error is-dismissible" style="margin: 15px 0;">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" action="collection-add.php" enctype="multipart/form-data" id="post">
        
        <!-- WordPress #poststuff 2-Column Layout -->
        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                
                <!-- Main Left Column -->
                <div id="post-body-content">
                    
                    <!-- Title Input -->
                    <div id="titlediv" style="margin-bottom: 20px;">
                        <div id="titlewrap">
                            <label class="screen-reader-text" id="title-prompt-text" for="title">Enter collection title here</label>
                            <input type="text" name="title" size="30" value="" id="title" placeholder="Collection Title (e.g. Royal Rajputana Bridal Diaries)" required style="width: 100%; font-size: 1.7em; height: 1.7em; line-height: 100%; padding: 3px 8px;">
                        </div>
                    </div>

                    <!-- Postbox: Basic Information -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><strong>Collection Overview &amp; Story</strong></h2>
                        </div>
                        <div class="inside" style="padding: 15px;">
                            
                            <p>
                                <label for="coll_subtitle"><strong>Subtitle / Tagline:</strong></label><br>
                                <input type="text" id="coll_subtitle" name="subtitle" value="" style="width: 100%;" placeholder="e.g. Handcrafted crimson velvet lehengas & uncut Polki jewels">
                            </p>

                            <p>
                                <label for="coll_category"><strong>Category:</strong></label><br>
                                <select id="coll_category" name="category" style="width: 100%; max-width: 350px;">
                                    <option value="Real Brides">Real Brides &amp; Client Celebrations</option>
                                    <option value="Bridal Couture">Bridal Couture &amp; Lehengas</option>
                                    <option value="Designer Blouses">Designer Blouses &amp; Cholis</option>
                                    <option value="Menswear Couture">Menswear &amp; Groom Sherwanis</option>
                                    <option value="Fine Jewellery">Fine Jewellery &amp; Heritage Sets</option>
                                    <option value="Editorial Shoots">Editorial &amp; Indoor Studio Shoots</option>
                                    <option value="Celebrity Styling">Celebrity &amp; VIP Styling</option>
                                </select>
                            </p>

                            <p>
                                <label for="coll_description"><strong>Editorial Narrative / Description:</strong></label><br>
                                <textarea id="coll_description" name="description" rows="5" style="width: 100%; font-size: 13px;" placeholder="Write about the inspiration, fabrics, embroidery, karigari, or client story..."></textarea>
                            </p>

                        </div>
                    </div>

                    <!-- Postbox: Attach Shoot Photos -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><strong>Attach Shoot Photos</strong></h2>
                        </div>
                        <div class="inside" style="padding: 15px;">
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <!-- Direct Upload -->
                                <div style="background: #f6f7f7; border: 1px dashed #c3c4c7; border-radius: 4px; padding: 15px;">
                                    <h4 style="margin: 0 0 8px 0;"><i class="fa-solid fa-cloud-arrow-up"></i> Upload from Computer:</h4>
                                    <input type="file" name="gallery_files[]" multiple accept="image/*" style="width: 100%;">
                                    <p class="description" style="margin-top: 6px;">Select one or multiple image files (JPG, PNG, WEBP).</p>
                                </div>

                                <!-- Library Selector -->
                                <div style="background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <h4 style="margin: 0;"><i class="fa-solid fa-folder-open"></i> Pick from Server Library:</h4>
                                        <span id="selectedCountBadge" style="font-size: 11px; background: #2271b1; color: #fff; padding: 2px 8px; border-radius: 10px; font-weight: bold;">0 Selected</span>
                                    </div>
                                    <details open>
                                        <summary style="color: #2271b1; cursor: pointer; font-weight: 600; font-size: 13px;">Browse Library (<?php echo count($available_images); ?> photos available)</summary>
                                        <div style="max-height: 300px; overflow-y: auto; margin-top: 10px; display: grid; grid-template-columns: repeat(auto-fill, minmax(75px, 1fr)); gap: 6px; padding: 6px; background: #ffffff; border: 1px solid #dcdcde; border-radius: 3px;">
                                            <?php foreach ($available_images as $imgPath): 
                                                $thumb = get_collection_image_url($imgPath);
                                            ?>
                                            <label style="position: relative; aspect-ratio: 1; border-radius: 3px; overflow: hidden; display: block; cursor: pointer; border: 1px solid #dcdcde;">
                                                <input type="checkbox" name="library_images[]" value="<?php echo htmlspecialchars($imgPath); ?>" style="position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; z-index: 2;" onchange="updateSelectedCount()">
                                                <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Photo" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

                <!-- Right Sidebar Column (WordPress Standard Sidebar) -->
                <div id="postbox-container-1" class="postbox-container">
                    
                    <!-- Postbox: Publish Options -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><strong>Publish &amp; Visibility</strong></h2>
                        </div>
                        <div class="inside" style="padding: 12px 15px;">
                            
                            <div class="misc-pub-section" style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;">
                                <label for="coll_status"><strong>Status:</strong></label>
                                <select id="coll_status" name="status" style="width: 100%; margin-top: 4px;">
                                    <option value="published">Published (Live on Website)</option>
                                    <option value="draft">Draft (Hidden)</option>
                                </select>
                            </div>

                            <div class="misc-pub-section" style="padding: 10px 0; border-bottom: 1px solid #f0f0f1;">
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                    <input type="checkbox" name="is_featured" value="1" checked>
                                    <strong>Feature on Homepage</strong>
                                </label>
                            </div>

                            <div class="misc-pub-section" style="padding: 8px 0;">
                                <label for="coll_sort_order"><strong>Display Order:</strong></label>
                                <input type="number" id="coll_sort_order" name="sort_order" value="0" style="width: 80px; margin-top: 4px;">
                            </div>

                            <div id="major-publishing-actions" style="margin-top: 15px; padding-top: 12px; border-top: 1px solid #dcdcde; text-align: right;">
                                <button type="submit" class="button button-primary button-large" style="width: 100%;">
                                    <i class="fa-solid fa-save"></i> Save Collection
                                </button>
                            </div>

                        </div>
                    </div>

                    <!-- Postbox: Cover Image -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><strong>Featured Cover Image (Optional)</strong></h2>
                        </div>
                        <div class="inside" style="padding: 15px;">
                            <label for="cover_file"><strong>Upload Specific Cover:</strong></label>
                            <input type="file" id="cover_file" name="cover_file" accept="image/*" style="width: 100%; margin-top: 4px;">
                            <p class="description" style="margin-top: 6px;">Leave empty to auto-use the first attached photo as the collection cover.</p>
                        </div>
                    </div>

                </div>

            </div>
        </div>

    </form>

</div>

<script>
function updateSelectedCount() {
    const checked = document.querySelectorAll('input[name="library_images[]"]:checked').length;
    document.getElementById('selectedCountBadge').innerText = checked + ' Selected';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
