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
            $rel = str_replace($base_dir . DIRECTORY_SEPARATOR, '', $path);
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
            $results[] = 'uploads/collections/' . $rel;
        }
    }
}
scan_collection_images_recursive($collections_dir, $collections_dir, $available_images);

// Categories list
$knownCategories = ['Blouse', 'Anarkali', 'Lehenga', 'Gown', 'Suit', 'Indo western', 'Kids wear', 'Sari', 'Sari Makeover', 'Family Twinning', 'Mens wear', 'Home furnishing'];
if (is_dir($collections_dir)) {
    foreach (scandir($collections_dir) as $d) {
        if ($d === '.' || $d === '..') continue;
        if (is_dir($collections_dir . '/' . $d)) {
            $cName = ucfirst($d);
            if (!in_array($cName, $knownCategories)) {
                $knownCategories[] = $cName;
            }
        }
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category = trim($_POST['category'] ?? 'Blouse');
    $fabric = trim($_POST['fabric'] ?? '');
    $work_type = trim($_POST['work_type'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $status = $_POST['status'] ?? 'published';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $selected_library_images = $_POST['library_images'] ?? [];
    $cover_choice = $_POST['cover_choice'] ?? '';

    if (empty($title)) {
        $message = "Outfit Title is required.";
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
                $upload = upload_image($_FILES['cover_file'], 'uploads/collections/' . $category . '/' . $slug, $slug . '-cover');
                if (is_array($upload) && isset($upload['filepath'])) {
                    $cover_image_path = $upload['filepath'];
                }
            } elseif (!empty($cover_choice)) {
                $cover_image_path = $cover_choice;
            }

            // 2. Insert Collection record
            $stmt = $pdo->prepare("INSERT INTO collections 
                (title, slug, sku, category, subtitle, description, fabric, work_type, color, cover_image, video_url, is_featured, sort_order, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $sku, $category, $subtitle, $description, $fabric, $work_type, $color, $cover_image_path, $video_url, $is_featured, $sort_order, $status]);
            $collection_id = $pdo->lastInsertId();

            $ins_img = $pdo->prepare("INSERT INTO collection_images 
                (collection_id, image_path, caption, angle_type, media_type, is_cover, sort_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $img_order = 1;

            // 3. Attach selected library images
            if (!empty($selected_library_images) && is_array($selected_library_images)) {
                foreach ($selected_library_images as $lib_img) {
                    $caption = pathinfo($lib_img, PATHINFO_FILENAME);
                    $isCover = ($lib_img === $cover_image_path) ? 1 : 0;
                    $ins_img->execute([$collection_id, $lib_img, $caption, 'Front View', 'image', $isCover, $img_order++]);
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
                        $upload = upload_image($single_file, 'uploads/collections/' . $category . '/' . $slug, 'photo_' . time() . '_' . $i);
                        if (is_array($upload) && isset($upload['filepath'])) {
                            $caption = pathinfo($files['name'][$i], PATHINFO_FILENAME);
                            $isCover = ($upload['filepath'] === $cover_image_path) ? 1 : 0;
                            $ins_img->execute([$collection_id, $upload['filepath'], $caption, 'Angle View', 'image', $isCover, $img_order++]);
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
            log_activity($pdo, 'add_collection', 'collection', $collection_id, "Created outfit style '$title'");
            header("Location: collections.php?msg=created");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Database Error: " . $e->getMessage();
        }
    }
}

$page_title = "Add Outfit Style";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">
                <i class="fa-solid fa-circle-plus" style="color: #6366f1;"></i> Add Outfit Style
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 0;">Add a new designer outfit with category, fabric details, multiple photoshoot angles, and video.</p>
        </div>
        <div>
            <a href="collections.php" class="button"><i class="fa-solid fa-arrow-left"></i> Back to Outfits</a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="notice notice-error" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
        <p><strong>Error:</strong> <?php echo htmlspecialchars($message); ?></p>
    </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
            <!-- Left Main Column -->
            <div>
                <!-- Primary Outfit Details Card -->
                <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <i class="fa-solid fa-vest-patches" style="color: #6366f1;"></i> Outfit Information
                    </h3>

                    <div style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 6px; color: #334155;">Outfit Title <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="title" class="form-control" style="width: 100%; font-size: 15px; font-weight: 600; padding: 8px 12px; border-radius: 6px;" placeholder="e.g. Royal Wine Raw Silk Zardozi Embroidered Bridal Blouse" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 6px; color: #334155;">Collection Category <span style="color: #ef4444;">*</span></label>
                            <select name="category" class="form-control" style="width: 100%; padding: 8px; border-radius: 6px;" required>
                                <?php foreach ($knownCategories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($cat === 'Blouse') ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 6px; color: #334155;">Style Code / SKU</label>
                            <input type="text" name="sku" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px;" placeholder="e.g. BLS-001">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 6px; color: #334155;">Fabric</label>
                            <input type="text" name="fabric" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px;" placeholder="e.g. Pure Raw Silk, Velvet">
                        </div>
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 6px; color: #334155;">Work / Embroidery</label>
                            <input type="text" name="work_type" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px;" placeholder="e.g. Hand Zardozi &amp; Pearl">
                        </div>
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 6px; color: #334155;">Color</label>
                            <input type="text" name="color" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px;" placeholder="e.g. Wine / Antique Gold">
                        </div>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 6px; color: #334155;">Short Subtitle / Tagline</label>
                        <input type="text" name="subtitle" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px;" placeholder="e.g. Handcrafted couture bridal edition">
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 6px; color: #334155;">Lookbook Description</label>
                        <textarea name="description" rows="4" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px;" placeholder="Detailed design notes, styling inspirations, neckline details, matching lehenga/sari pairing..."></textarea>
                    </div>

                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 6px; color: #334155;">Outfit Video URL (Optional)</label>
                        <input type="text" name="video_url" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px;" placeholder="e.g. https://www.youtube.com/watch?v=... or MP4 file link">
                    </div>
                </div>

                <!-- Photoshoot Media Card -->
                <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <i class="fa-solid fa-camera" style="color: #6366f1;"></i> Photoshoot Gallery (Multiple Angles)
                    </h3>

                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 6px; color: #334155;">Upload Photos (Front, Back, Close-up angles)</label>
                        <input type="file" name="gallery_files[]" multiple accept="image/*" class="form-control" style="width: 100%; padding: 8px; border-radius: 6px;">
                        <span style="font-size: 11px; color: #64748b;">Select multiple image files at once to create a complete outfit gallery.</span>
                    </div>

                    <?php if (!empty($available_images)): ?>
                    <div style="margin-top: 16px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px; color: #334155;">Or Select from Server Image Library:</label>
                        <div style="max-height: 240px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 8px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <?php foreach (array_slice($available_images, 0, 40) as $imgPath): ?>
                                <label style="display: block; position: relative; aspect-ratio: 1; border-radius: 4px; overflow: hidden; cursor: pointer; border: 1px solid #cbd5e1;">
                                    <input type="checkbox" name="library_images[]" value="<?php echo htmlspecialchars($imgPath); ?>" style="position: absolute; top: 4px; left: 4px; z-index: 2;">
                                    <img src="<?php echo htmlspecialchars(get_collection_image_url($imgPath)); ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='assets/images/placeholder.svg';">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Sidebar Column -->
            <div>
                <!-- Publish / Save Card -->
                <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        Publishing
                    </h3>

                    <div style="margin-bottom: 14px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Status</label>
                        <select name="status" class="form-control" style="width: 100%; padding: 6px; border-radius: 6px;">
                            <option value="published">Published (Visible in Lookbook)</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Sort Order</label>
                        <input type="number" name="sort_order" value="0" class="form-control" style="width: 100%; padding: 6px; border-radius: 6px;">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #334155;">
                            <input type="checkbox" name="is_featured" value="1"> 
                            <span><i class="fa-solid fa-star" style="color: #eab308;"></i> Featured Outfit</span>
                        </label>
                    </div>

                    <button type="submit" class="button button-primary" style="width: 100%; padding: 10px; font-weight: 700; font-size: 14px; background: #059669; border-color: #047857;">
                        <i class="fa-solid fa-check"></i> Save Outfit Style
                    </button>
                </div>

                <!-- Cover Image Card -->
                <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        Hero Cover Image
                    </h3>
                    <div>
                        <input type="file" name="cover_file" accept="image/*" class="form-control" style="width: 100%; padding: 6px; border-radius: 6px;">
                        <span style="font-size: 11px; color: #64748b; margin-top: 4px; display: block;">If empty, the first photoshoot image will be used automatically.</span>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
