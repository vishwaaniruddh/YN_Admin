<?php
// admin/collection-edit.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$message_type = 'success';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'img_deleted') {
        $message = "Photo removed from collection.";
    } elseif ($_GET['msg'] === 'cover_updated') {
        $message = "Cover photo updated successfully.";
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: collections.php");
    exit();
}

// Fetch Collection
$stmt = $pdo->prepare("SELECT * FROM collections WHERE id = ?");
$stmt->execute([$id]);
$collection = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$collection) {
    header("Location: collections.php?error=notfound");
    exit();
}

// Handle Photo Deletion
if (isset($_GET['delete_image']) && is_numeric($_GET['delete_image'])) {
    $del_img_id = (int)$_GET['delete_image'];
    $pdo->prepare("DELETE FROM collection_images WHERE id = ? AND collection_id = ?")->execute([$del_img_id, $id]);
    header("Location: collection-edit.php?id=$id&msg=img_deleted");
    exit();
}

// Handle Set as Cover
if (isset($_GET['set_cover']) && is_numeric($_GET['set_cover'])) {
    $img_id = (int)$_GET['set_cover'];
    $stmt = $pdo->prepare("SELECT image_path FROM collection_images WHERE id = ? AND collection_id = ?");
    $stmt->execute([$img_id, $id]);
    $img = $stmt->fetch();
    if ($img) {
        $pdo->prepare("UPDATE collections SET cover_image = ? WHERE id = ?")->execute([$img['image_path'], $id]);
        header("Location: collection-edit.php?id=$id&msg=cover_updated");
        exit();
    }
}

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

    if (empty($title)) {
        $message = "Collection Title is required.";
        $message_type = "error";
    } else {
        try {
            $pdo->beginTransaction();

            $cover_image_path = $collection['cover_image'];

            // 1. Handle New Cover Upload
            if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
                $upload = upload_image($_FILES['cover_file'], 'uploads/collections/custom', $collection['slug'] . '-cover-' . time());
                if (is_array($upload) && isset($upload['filepath'])) {
                    $cover_image_path = $upload['filepath'];
                }
            }

            // 2. Update Collection Info
            $stmt = $pdo->prepare("UPDATE collections SET title = ?, subtitle = ?, category = ?, description = ?, cover_image = ?, is_featured = ?, sort_order = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $subtitle, $category, $description, $cover_image_path, $is_featured, $sort_order, $status, $id]);

            // 3. Attach additional library images if selected
            if (!empty($selected_library_images) && is_array($selected_library_images)) {
                $ins_img = $pdo->prepare("INSERT INTO collection_images (collection_id, image_path, caption, outfit_type, sort_order) VALUES (?, ?, ?, ?, ?)");
                $maxOrder = $pdo->query("SELECT IFNULL(MAX(sort_order), 0) FROM collection_images WHERE collection_id = $id")->fetchColumn();
                foreach ($selected_library_images as $lib_img) {
                    $caption = pathinfo($lib_img, PATHINFO_FILENAME);
                    $ins_img->execute([$id, $lib_img, $caption, $category, ++$maxOrder]);
                }
            }

            // 4. Handle Direct New Photos Multi-Upload
            if (isset($_FILES['gallery_files']) && !empty($_FILES['gallery_files']['name'][0])) {
                $files = $_FILES['gallery_files'];
                $count = count($files['name']);
                $ins_img = $pdo->prepare("INSERT INTO collection_images (collection_id, image_path, caption, outfit_type, sort_order) VALUES (?, ?, ?, ?, ?)");
                $maxOrder = $pdo->query("SELECT IFNULL(MAX(sort_order), 0) FROM collection_images WHERE collection_id = $id")->fetchColumn();
                
                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $single_file = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];
                        $upload = upload_image($single_file, 'uploads/collections/' . $collection['slug'], 'photo_' . time() . '_' . $i);
                        if (is_array($upload) && isset($upload['filepath'])) {
                            $caption = pathinfo($files['name'][$i], PATHINFO_FILENAME);
                            $ins_img->execute([$id, $upload['filepath'], $caption, $category, ++$maxOrder]);
                        }
                    }
                }
            }

            // 5. Update individual image captions & sort orders if sent
            if (isset($_POST['captions']) && is_array($_POST['captions'])) {
                $updCaption = $pdo->prepare("UPDATE collection_images SET caption = ? WHERE id = ? AND collection_id = ?");
                foreach ($_POST['captions'] as $imgId => $cap) {
                    $updCaption->execute([trim($cap), (int)$imgId, $id]);
                }
            }

            $pdo->commit();
            log_activity($pdo, 'update_collection', 'collection', $id, "Updated collection '$title'");
            $message = "Collection updated successfully!";
            $message_type = "success";

            // Refresh collection data
            $stmt = $pdo->prepare("SELECT * FROM collections WHERE id = ?");
            $stmt->execute([$id]);
            $collection = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Error updating collection: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Fetch all gallery images for this collection
$img_stmt = $pdo->prepare("SELECT * FROM collection_images WHERE collection_id = ? ORDER BY sort_order ASC, id ASC");
$img_stmt->execute([$id]);
$gallery_images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

// Scan server library
$available_images = [];
$collections_dir = __DIR__ . '/uploads/collections';
function scan_collection_images_recursive2($dir, $base_dir, &$results) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            scan_collection_images_recursive2($path, $base_dir, $results);
        } elseif (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $item)) {
            $rel = str_replace($base_dir . '/', '', $path);
            $results[] = 'uploads/collections/' . $rel;
        }
    }
}
scan_collection_images_recursive2($collections_dir, $collections_dir, $available_images);

$page_title = "Edit Collection";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* WordPress Poststuff & Full Image Masonry Styling */
.wp-gallery-masonry {
    column-count: 2;
    column-gap: 16px;
    margin-top: 15px;
}
@media (min-width: 768px) {
    .wp-gallery-masonry { column-count: 3; }
}
@media (min-width: 1200px) {
    .wp-gallery-masonry { column-count: 4; }
}

.wp-photo-card {
    break-inside: avoid;
    margin-bottom: 16px;
    background: #ffffff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    transition: transform 0.2s, box-shadow 0.2s;
}
.wp-photo-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    border-color: #2271b1;
}

.wp-photo-img-wrap {
    position: relative;
    background: #f0f0f1;
    overflow: hidden;
}
.wp-photo-img {
    width: 100%;
    height: auto;
    display: block;
    opacity: 0;
    transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.wp-photo-img.loaded {
    opacity: 1;
}

.wp-photo-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 40%, rgba(0,0,0,0.4) 100%);
    opacity: 0;
    transition: opacity 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 8px;
}
.wp-photo-card:hover .wp-photo-overlay {
    opacity: 1;
}

.wp-cover-tag {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #dba617;
    color: #000000;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 3px;
    letter-spacing: 0.5px;
    z-index: 2;
}

.wp-btn-trash {
    background: #d63638;
    color: #ffffff;
    border-radius: 3px;
    padding: 3px 6px;
    font-size: 11px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.wp-btn-trash:hover {
    background: #b32d2e;
    color: #ffffff;
}

.wp-btn-cover {
    background: #ffffff;
    color: #1d2327;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 3px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.wp-btn-cover:hover {
    background: #f0f0f1;
    color: #2271b1;
}

.wp-photo-caption-input {
    width: 100%;
    padding: 6px 8px;
    font-size: 11px;
    border: none;
    border-top: 1px solid #dcdcde;
    background: #f6f7f7;
    box-sizing: border-box;
}
.wp-photo-caption-input:focus {
    background: #ffffff;
    outline: none;
}
</style>

<div class="wrap">
    
    <!-- WordPress Standard Header -->
    <h1 class="wp-heading-inline">
        Edit Collection: <?php echo htmlspecialchars($collection['title']); ?>
    </h1>
    <a href="collections.php" class="page-title-action">&larr; Back to Collections</a>
    <a href="collection-add.php" class="page-title-action">Add New Collection</a>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible" style="margin: 15px 0;">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" action="collection-edit.php?id=<?php echo $id; ?>" enctype="multipart/form-data" id="post">
        
        <!-- WordPress #poststuff 2-Column Layout -->
        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                
                <!-- Main Left Column -->
                <div id="post-body-content">
                    
                    <!-- Title Input -->
                    <div id="titlediv" style="margin-bottom: 20px;">
                        <div id="titlewrap">
                            <label class="screen-reader-text" id="title-prompt-text" for="title">Enter collection title here</label>
                            <input type="text" name="title" size="30" value="<?php echo htmlspecialchars($collection['title']); ?>" id="title" placeholder="Collection Title (e.g. Royal Rajputana Bridal Diaries)" required style="width: 100%; font-size: 1.7em; height: 1.7em; line-height: 100%; padding: 3px 8px;">
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
                                <input type="text" id="coll_subtitle" name="subtitle" value="<?php echo htmlspecialchars($collection['subtitle'] ?? ''); ?>" style="width: 100%;" placeholder="e.g. Handcrafted crimson velvet lehengas & uncut Polki jewels">
                            </p>

                            <p>
                                <label for="coll_category"><strong>Category:</strong></label><br>
                                <select id="coll_category" name="category" style="width: 100%; max-width: 350px;">
                                    <?php 
                                    $cats = ['Real Brides', 'Bridal Couture', 'Designer Blouses', 'Menswear Couture', 'Fine Jewellery', 'Editorial Shoots', 'Celebrity Styling'];
                                    foreach ($cats as $c): ?>
                                        <option value="<?php echo $c; ?>" <?php echo ($collection['category'] === $c) ? 'selected' : ''; ?>>
                                            <?php echo $c; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>

                            <p>
                                <label for="coll_description"><strong>Editorial Narrative / Description:</strong></label><br>
                                <textarea id="coll_description" name="description" rows="5" style="width: 100%; font-size: 13px;" placeholder="Write about the inspiration, fabrics, embroidery, karigari, or client story..."><?php echo htmlspecialchars($collection['description'] ?? ''); ?></textarea>
                            </p>

                        </div>
                    </div>

                    <!-- Postbox: Attached Shoot Photos (FULL IMAGE MASONRY) -->
                    <div class="postbox">
                        <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center; padding-right: 15px;">
                            <h2>
                                <strong>Attached Shoot Photos (<span style="color: #2271b1;"><?php echo count($gallery_images); ?> Photos</span>)</strong>
                            </h2>
                            <span style="font-size: 12px; color: #646970;">
                                <i class="fa-solid fa-eye"></i> Full Image Progressive Masonry
                            </span>
                        </div>
                        <div class="inside" style="padding: 15px;">
                            
                            <?php if (empty($gallery_images)): ?>
                                <div style="text-align: center; padding: 40px; color: #646970; background: #f6f7f7; border: 1px dashed #c3c4c7; border-radius: 4px;">
                                    <i class="fa-solid fa-images" style="font-size: 32px; margin-bottom: 8px;"></i>
                                    <p>No photos attached to this collection yet. Add photos below!</p>
                                </div>
                            <?php else: ?>
                                <p style="font-size: 12px; color: #646970; margin-top: 0;">
                                    Hover on any photo to set it as Cover or remove it. All images are displayed in full natural aspect ratio.
                                </p>

                                <!-- PROGRESSIVE FULL-IMAGE MASONRY GRID -->
                                <div class="wp-gallery-masonry">
                                    <?php foreach ($gallery_images as $g): 
                                        $isCover = ($collection['cover_image'] === $g['image_path']);
                                        $imgSrc = get_collection_image_url($g['image_path']);
                                    ?>
                                    <div class="wp-photo-card" id="photo-<?php echo $g['id']; ?>">
                                        
                                        <div class="wp-photo-img-wrap">
                                            <!-- Full Natural Height Progressive Image -->
                                            <img 
                                                src="<?php echo htmlspecialchars($imgSrc); ?>" 
                                                alt="Photo" 
                                                class="wp-photo-img" 
                                                loading="lazy"
                                                onload="this.classList.add('loaded')"
                                            >

                                            <?php if ($isCover): ?>
                                                <span class="wp-cover-tag">★ COVER</span>
                                            <?php endif; ?>

                                            <!-- Hover Action Overlay -->
                                            <div class="wp-photo-overlay">
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                    <div>
                                                        <?php if (!$isCover): ?>
                                                            <a href="collection-edit.php?id=<?php echo $id; ?>&set_cover=<?php echo $g['id']; ?>" class="wp-btn-cover" title="Set as Collection Cover Photo">
                                                                <i class="fa-solid fa-star"></i> Set Cover
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>

                                                    <a href="collection-edit.php?id=<?php echo $id; ?>&delete_image=<?php echo $g['id']; ?>" class="wp-btn-trash" onclick="return confirm('Remove this photo from the collection?');" title="Remove Photo">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
                                                </div>

                                                <div style="text-align: right;">
                                                    <a href="<?php echo htmlspecialchars($imgSrc); ?>" target="_blank" class="wp-btn-cover" style="font-size: 10px; padding: 2px 6px;" title="View Full Original">
                                                        <i class="fa-solid fa-magnifying-glass-plus"></i> Zoom
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Caption / Tag Input -->
                                        <input 
                                            type="text" 
                                            name="captions[<?php echo $g['id']; ?>]" 
                                            value="<?php echo htmlspecialchars($g['caption'] ?? ''); ?>" 
                                            placeholder="Caption / Tag..."
                                            class="wp-photo-caption-input"
                                        >
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- Postbox: Add More Photos -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><strong>Add Photos to this Collection</strong></h2>
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
                                    <h4 style="margin: 0 0 8px 0;"><i class="fa-solid fa-folder-open"></i> Pick from Server Library (<?php echo count($available_images); ?> photos):</h4>
                                    <details>
                                        <summary style="color: #2271b1; cursor: pointer; font-weight: 600; font-size: 13px;">Browse Library Photos</summary>
                                        <div style="max-height: 250px; overflow-y: auto; margin-top: 10px; display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: 6px; padding: 6px; background: #ffffff; border: 1px solid #dcdcde; border-radius: 3px;">
                                            <?php foreach ($available_images as $imgPath): 
                                                $thumb = get_collection_image_url($imgPath);
                                            ?>
                                            <label style="position: relative; aspect-ratio: 1; border-radius: 3px; overflow: hidden; display: block; cursor: pointer; border: 1px solid #dcdcde;">
                                                <input type="checkbox" name="library_images[]" value="<?php echo htmlspecialchars($imgPath); ?>" style="position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; z-index: 2;">
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
                                    <option value="published" <?php echo ($collection['status'] === 'published') ? 'selected' : ''; ?>>Published (Live on Website)</option>
                                    <option value="draft" <?php echo ($collection['status'] === 'draft') ? 'selected' : ''; ?>>Draft (Hidden)</option>
                                </select>
                            </div>

                            <div class="misc-pub-section" style="padding: 10px 0; border-bottom: 1px solid #f0f0f1;">
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                    <input type="checkbox" name="is_featured" value="1" <?php echo ($collection['is_featured'] == 1) ? 'checked' : ''; ?>>
                                    <strong>Feature on Homepage</strong>
                                </label>
                            </div>

                            <div class="misc-pub-section" style="padding: 8px 0;">
                                <label for="coll_sort_order"><strong>Display Order:</strong></label>
                                <input type="number" id="coll_sort_order" name="sort_order" value="<?php echo (int)$collection['sort_order']; ?>" style="width: 80px; margin-top: 4px;">
                            </div>

                            <div id="major-publishing-actions" style="margin-top: 15px; padding-top: 12px; border-top: 1px solid #dcdcde; display: flex; justify-content: space-between; align-items: center;">
                                <a href="collections.php?delete=<?php echo $id; ?>" onclick="return confirm('Are you sure you want to delete this collection?');" style="color: #b32d2e; text-decoration: underline; font-size: 12px;">Delete</a>
                                <button type="submit" class="button button-primary button-large">
                                    <i class="fa-solid fa-floppy-disk"></i> Update Collection
                                </button>
                            </div>

                        </div>
                    </div>

                    <!-- Postbox: Cover Image -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><strong>Featured Cover Image</strong></h2>
                        </div>
                        <div class="inside" style="padding: 15px;">
                            
                            <?php if ($collection['cover_image']): 
                                $coverSrc = get_collection_image_url($collection['cover_image']);
                            ?>
                                <div style="border-radius: 4px; overflow: hidden; border: 1px solid #dcdcde; margin-bottom: 10px; background: #f0f0f1;">
                                    <img 
                                        src="<?php echo htmlspecialchars($coverSrc); ?>" 
                                        alt="Cover" 
                                        style="width: 100%; height: auto; display: block;"
                                        loading="lazy"
                                        onload="this.style.opacity=1"
                                    >
                                </div>
                            <?php endif; ?>

                            <label for="cover_file"><strong>Replace Cover:</strong></label>
                            <input type="file" id="cover_file" name="cover_file" accept="image/*" style="width: 100%; margin-top: 4px;">
                            <p class="description" style="margin-top: 4px;">Or click "Set Cover" on any photo in the gallery on the left.</p>

                        </div>
                    </div>

                </div>

            </div>
        </div>

    </form>

</div>

<script>
// Auto progressive image load fallback
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.wp-photo-img').forEach(function(img) {
        if (img.complete) {
            img.classList.add('loaded');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
