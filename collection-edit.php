<?php
// admin/collection-edit.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$message_type = 'success';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: collections.php");
    exit();
}

// Fetch Collection Data
$stmt = $pdo->prepare("SELECT * FROM collections WHERE id = ?");
$stmt->execute([$id]);
$collection = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$collection) {
    header("Location: collections.php?error=notfound");
    exit();
}

// Handle Form Submission for Collection Metadata
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_collection_meta'])) {
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $category = trim($_POST['category'] ?? 'Client Diaries');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'published';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if (empty($title)) {
        $message = "Collection Title is required.";
        $message_type = "error";
    } else {
        try {
            $cover_image_path = $collection['cover_image'];

            // Handle New Cover Upload if uploaded via file input
            if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
                $upload = upload_image($_FILES['cover_file'], 'uploads/collections/custom', $collection['slug'] . '-cover-' . time());
                if (is_array($upload) && isset($upload['filepath'])) {
                    $cover_image_path = $upload['filepath'];
                }
            }

            $updStmt = $pdo->prepare("UPDATE collections SET title = ?, subtitle = ?, category = ?, description = ?, cover_image = ?, is_featured = ?, sort_order = ?, status = ? WHERE id = ?");
            $updStmt->execute([$title, $subtitle, $category, $description, $cover_image_path, $is_featured, $sort_order, $status, $id]);

            log_activity($pdo, 'update_collection', 'collection', $id, "Updated collection '$title'");
            $message = "Collection details saved successfully!";
            $message_type = "success";

            // Refresh collection data
            $stmt = $pdo->prepare("SELECT * FROM collections WHERE id = ?");
            $stmt->execute([$id]);
            $collection = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $message = "Error updating collection: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Total Photos in this collection
$totalPhotosCount = (int)$pdo->query("SELECT COUNT(*) FROM collection_images WHERE collection_id = $id")->fetchColumn();

// Fetch distinct categories for dropdown
$categoriesList = $pdo->query("SELECT DISTINCT category FROM collections WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

$page_title = "Manage Collection Photos - " . htmlspecialchars($collection['title']);
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* Progressive Masonry Gallery Layout */
.gallery-masonry-container {
    column-count: 2;
    column-gap: 16px;
    margin-top: 14px;
}
@media (min-width: 640px) {
    .gallery-masonry-container { column-count: 3; }
}
@media (min-width: 1100px) {
    .gallery-masonry-container { column-count: 4; }
}
@media (min-width: 1440px) {
    .gallery-masonry-container { column-count: 5; }
}

.masonry-photo-item {
    break-inside: avoid;
    margin-bottom: 16px;
    background: #ffffff;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    transition: transform 0.2s, box-shadow 0.2s, opacity 0.3s;
}
.masonry-photo-item:hover {
    box-shadow: 0 6px 16px rgba(0,0,0,0.12);
    border-color: #2271b1;
    transform: translateY(-2px);
}

.masonry-img-wrap {
    position: relative;
    background: #f0f0f1;
    overflow: hidden;
    min-height: 140px;
}
.masonry-img {
    width: 100%;
    height: auto;
    display: block;
    opacity: 0;
    transition: opacity 0.35s ease;
}
.masonry-img.loaded {
    opacity: 1;
}

.photo-badge-cover {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #dba617;
    color: #1a1a1a;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 3px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    z-index: 2;
}

.photo-overlay-actions {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, transparent 40%, rgba(0,0,0,0.4) 100%);
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 8px;
    z-index: 3;
}
.masonry-photo-item:hover .photo-overlay-actions {
    opacity: 1;
}

.btn-cover-star {
    align-self: flex-end;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(255,255,255,0.9);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8c8f94;
    transition: transform 0.2s, color 0.2s;
}
.btn-cover-star.active, .btn-cover-star:hover {
    color: #dba617;
    transform: scale(1.15);
}

.photo-caption-bar {
    padding: 8px 10px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    border-top: 1px solid #f0f0f1;
}
.photo-caption-input {
    flex: 1;
    font-size: 11px;
    padding: 3px 6px;
    border: 1px solid transparent;
    border-radius: 3px;
    background: transparent;
    color: #1d2327;
    outline: none;
}
.photo-caption-input:hover {
    border-color: #dcdcde;
    background: #f6f7f7;
}
.photo-caption-input:focus {
    border-color: #2271b1;
    background: #ffffff;
}

.btn-delete-photo {
    background: none;
    border: none;
    color: #b32d2e;
    font-size: 13px;
    cursor: pointer;
    padding: 4px;
    border-radius: 3px;
    transition: background 0.15s;
}
.btn-delete-photo:hover {
    background: #fbeaea;
}

/* Upload Dropzone */
.ajax-dropzone {
    border: 2px dashed #c3c4c7;
    background: #ffffff;
    padding: 24px;
    text-align: center;
    border-radius: 6px;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
}
.ajax-dropzone.dragover {
    border-color: #2271b1;
    background: #f0f6fc;
}

/* Toast */
.ajax-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #1d2327;
    color: #ffffff;
    padding: 12px 20px;
    border-radius: 6px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.25);
    font-size: 13px;
    font-weight: 600;
    z-index: 99999;
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.3s, transform 0.3s;
    pointer-events: none;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ajax-toast.show {
    opacity: 1;
    transform: translateY(0);
}
</style>

<div class="wrap">
    
    <!-- Top Nav Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h1 class="wp-heading-inline" style="margin-bottom: 4px;">
                <a href="collections.php" style="text-decoration: none; color: #646970;"><i class="fa-solid fa-arrow-left"></i></a>
                Edit Collection: <?php echo htmlspecialchars($collection['title']); ?>
            </h1>
            <span class="count" style="color: #646970; font-size: 13px;">(ID #<?php echo $collection['id']; ?> &bull; <strong id="livePhotoCount"><?php echo $totalPhotosCount; ?></strong> Photos linked)</span>
        </div>

        <div>
            <a href="../our-work?category=<?php echo urlencode($collection['category']); ?>" target="_blank" class="button" title="View in Client Diaries Frontend">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> View Live Lookbook
            </a>
            <a href="collections.php" class="button">Back to All Collections</a>
        </div>
    </div>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible" style="margin: 15px 0;">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Two-Column WordPress Layout (Photos Gallery Left, Settings Right) -->
    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2" style="display: grid; grid-template-columns: 1fr 340px; gap: 20px;">
            
            <!-- MAIN LEFT COLUMN: Progressive Masonry Gallery -->
            <div id="post-body-content" style="min-width: 0;">
                
                <!-- Instant Multi-Upload Box -->
                <div class="postbox" style="margin-bottom: 20px;">
                    <div class="postbox-header">
                        <h2 class="hndle"><i class="fa-solid fa-cloud-arrow-up" style="color: #2271b1; margin-right: 6px;"></i> Upload Shoot Photos (Instant Dropzone)</h2>
                    </div>
                    <div class="inside" style="padding: 16px;">
                        <div class="ajax-dropzone" id="uploadDropzone" onclick="document.getElementById('galleryFileInput').click()">
                            <i class="fa-solid fa-images" style="font-size: 32px; color: #2271b1; margin-bottom: 8px;"></i>
                            <h3 style="margin: 4px 0; font-size: 15px;">Drag &amp; Drop photos here, or click to browse</h3>
                            <p style="color: #646970; font-size: 12px; margin: 4px 0 0 0;">Upload multiple JPG, PNG, or WebP images. They will be added instantly to this lookbook.</p>
                            <input type="file" id="galleryFileInput" multiple accept="image/*" style="display: none;" onchange="handleFileSelect(this.files)">
                        </div>

                        <!-- Upload Progress Bar -->
                        <div id="uploadProgressContainer" style="display: none; margin-top: 12px;">
                            <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                                <span id="uploadStatusText">Uploading photos...</span>
                                <span id="uploadPercentText">0%</span>
                            </div>
                            <div style="height: 6px; background: #f0f0f1; border-radius: 3px; overflow: hidden;">
                                <div id="uploadProgressBar" style="width: 0%; height: 100%; background: #2271b1; transition: width 0.2s;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gallery Manager & Search Filter Toolbar -->
                <div class="postbox">
                    <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center; padding-right: 12px;">
                        <h2 class="hndle"><i class="fa-solid fa-camera-retro" style="color: #2271b1; margin-right: 6px;"></i> Gallery Photos (<span id="galleryCounter"><?php echo $totalPhotosCount; ?></span>)</h2>
                        
                        <!-- Search input within collection photos -->
                        <div style="position: relative;">
                            <input type="search" id="photoSearchInput" placeholder="Filter photos..." style="padding-left: 24px; font-size: 12px; height: 28px; width: 160px;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 8px; top: 8px; color: #8c8f94; font-size: 11px;"></i>
                        </div>
                    </div>

                    <div class="inside" style="padding: 16px;">
                        
                        <!-- Dynamic Progressive Masonry Grid -->
                        <div class="gallery-masonry-container" id="galleryMasonry">
                            <!-- Populated progressively via API -->
                        </div>

                        <!-- Load More Trigger / Loader -->
                        <div id="galleryLoader" style="text-align: center; padding: 20px 0;">
                            <button id="btnLoadMore" onclick="loadNextPhotoBatch()" class="button button-secondary" style="font-weight: 600; padding: 6px 18px;">
                                <i class="fa-solid fa-plus"></i> Load More Photos
                            </button>
                        </div>

                        <div id="noPhotosNotice" style="display: none; text-align: center; padding: 40px 0; color: #646970;">
                            <i class="fa-regular fa-image" style="font-size: 32px; color: #c3c4c7; margin-bottom: 8px;"></i>
                            <p>No photos found in this collection.</p>
                        </div>

                    </div>
                </div>

            </div>

            <!-- RIGHT SIDEBAR: Collection Details & Cover Photo Form -->
            <div id="postbox-container-1" class="postbox-container">
                <form method="POST" action="collection-edit.php?id=<?php echo $id; ?>" enctype="multipart/form-data">
                    <input type="hidden" name="update_collection_meta" value="1">

                    <!-- Save Actions Box -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle">Publish Settings</h2>
                        </div>
                        <div class="inside" style="padding: 14px;">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px;">Status</label>
                                <select name="status" class="form-control">
                                    <option value="published" <?php echo $collection['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo $collection['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                    <input type="checkbox" name="is_featured" value="1" <?php echo $collection['is_featured'] ? 'checked' : ''; ?>>
                                    <span>Feature on Homepage ⭐</span>
                                </label>
                            </div>

                            <div class="form-group" style="margin-bottom: 14px;">
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px;">Sort Priority</label>
                                <input type="number" name="sort_order" value="<?php echo htmlspecialchars($collection['sort_order']); ?>" class="form-control" style="width: 100px;">
                            </div>

                            <button type="submit" class="button button-primary button-large" style="width: 100%; font-weight: 700;">
                                <i class="fa-solid fa-floppy-disk"></i> Save Collection Details
                            </button>
                        </div>
                    </div>

                    <!-- Details Box -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle">Collection Details</h2>
                        </div>
                        <div class="inside" style="padding: 14px;">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px;">Title *</label>
                                <input type="text" name="title" value="<?php echo htmlspecialchars($collection['title']); ?>" required class="form-control">
                            </div>

                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px;">Subtitle / Tagline</label>
                                <input type="text" name="subtitle" value="<?php echo htmlspecialchars($collection['subtitle'] ?? ''); ?>" class="form-control">
                            </div>

                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px;">Category</label>
                                <input type="text" name="category" list="catSuggestions" value="<?php echo htmlspecialchars($collection['category']); ?>" class="form-control">
                                <datalist id="catSuggestions">
                                    <?php foreach ($categoriesList as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php endforeach; ?>
                                    <option value="Editorial Shoots">
                                    <option value="Real Brides">
                                    <option value="Bridal Couture">
                                    <option value="Custom Studio">
                                </datalist>
                            </div>

                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px;">Story Description</label>
                                <textarea name="description" rows="4" class="form-control" style="font-size: 12px;"><?php echo htmlspecialchars($collection['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Current Cover Photo Preview Box -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle">Cover Photo</h2>
                        </div>
                        <div class="inside" style="padding: 14px; text-align: center;">
                            <div style="aspect-ratio: 16/10; background: #f0f0f1; border-radius: 4px; overflow: hidden; margin-bottom: 10px; border: 1px solid #dcdcde;">
                                <img id="coverPreviewImg" src="<?php echo htmlspecialchars(get_collection_image_url($collection['cover_image'])); ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <p style="font-size: 11px; color: #646970; margin-bottom: 10px;">
                                Tip: You can click the <strong>⭐ star</strong> on any gallery photo on the left to set it as the cover instantly!
                            </p>
                            <label class="button button-small" style="cursor: pointer;">
                                Upload New Cover
                                <input type="file" name="cover_file" accept="image/*" style="display: none;" onchange="previewCoverFile(this)">
                            </label>
                        </div>
                    </div>

                </form>
            </div>

        </div>
    </div>

</div>

<!-- Ajax Toast Alert -->
<div id="ajaxToast" class="ajax-toast">
    <i class="fa-solid fa-circle-check" style="color: #46b450;"></i>
    <span id="toastMessage">Saved</span>
</div>

<script>
const collectionId = <?php echo $id; ?>;
let currentPhotoPage = 1;
let currentCoverPath = "<?php echo addslashes($collection['cover_image']); ?>";
let totalPhotos = <?php echo $totalPhotosCount; ?>;
let currentSearch = '';
let hasMorePhotos = true;
let isLoadingPhotos = false;

document.addEventListener('DOMContentLoaded', () => {
    loadPhotoBatch(1, true);

    // Filter photos input
    let searchTimer = null;
    document.getElementById('photoSearchInput').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        currentSearch = e.target.value.trim();
        searchTimer = setTimeout(() => {
            currentPhotoPage = 1;
            loadPhotoBatch(1, true);
        }, 250);
    });

    // Setup drag and drop events
    const dropzone = document.getElementById('uploadDropzone');
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        }, false);
    });

    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            handleFileSelect(files);
        }
    });
});

function showToast(msg) {
    const toast = document.getElementById('ajaxToast');
    document.getElementById('toastMessage').innerText = msg;
    toast.className = 'ajax-toast show';
    setTimeout(() => { toast.className = 'ajax-toast'; }, 2500);
}

// Progressive Masonry Batch Fetcher
function loadPhotoBatch(page = 1, isReset = false) {
    if (isLoadingPhotos) return;
    isLoadingPhotos = true;

    const btn = document.getElementById('btnLoadMore');
    if (btn) btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';

    const params = new URLSearchParams({
        collection_id: collectionId,
        page: page,
        per_page: 24,
        s: currentSearch
    });

    fetch(`api/admin_collection_photos.php?${params.toString()}`)
        .then(res => res.json())
        .then(json => {
            if (json.success) {
                const container = document.getElementById('galleryMasonry');
                if (isReset) container.innerHTML = '';

                if (json.cover_image) {
                    currentCoverPath = json.cover_image;
                }

                if (json.data && json.data.length > 0) {
                    document.getElementById('noPhotosNotice').style.display = 'none';
                    appendPhotosToMasonry(json.data);
                } else if (isReset) {
                    document.getElementById('noPhotosNotice').style.display = 'block';
                }

                currentPhotoPage = json.pagination.page;
                hasMorePhotos = json.pagination.has_more;

                const loader = document.getElementById('galleryLoader');
                if (hasMorePhotos) {
                    loader.style.display = 'block';
                    if (btn) btn.innerHTML = `<i class="fa-solid fa-plus"></i> Load More Photos (${json.pagination.total - (currentPhotoPage * json.pagination.per_page)} remaining)`;
                } else {
                    loader.style.display = 'none';
                }

                document.getElementById('galleryCounter').innerText = json.pagination.total;
                document.getElementById('livePhotoCount').innerText = json.pagination.total;
            }
        })
        .catch(err => {
            console.error("Error loading photos:", err);
            if (btn) btn.innerText = 'Retry Loading';
        })
        .finally(() => {
            isLoadingPhotos = false;
        });
}

function loadNextPhotoBatch() {
    if (hasMorePhotos && !isLoadingPhotos) {
        loadPhotoBatch(currentPhotoPage + 1, false);
    }
}

// Render Photos progressively in Masonry
function appendPhotosToMasonry(photos) {
    const container = document.getElementById('galleryMasonry');
    
    photos.forEach(p => {
        const isCover = p.is_cover || (p.image_path === currentCoverPath);
        
        const card = document.createElement('div');
        card.className = 'masonry-photo-item';
        card.id = `photo-card-${p.id}`;

        card.innerHTML = `
            <div class="masonry-img-wrap">
                <img 
                    src="${escapeHtml(p.image_url)}" 
                    alt="${escapeHtml(p.caption || 'Shoot photo')}" 
                    class="masonry-img" 
                    loading="lazy"
                    onload="this.classList.add('loaded')"
                >

                ${isCover ? '<span class="photo-badge-cover" id="badge-cover-' + p.id + '">Cover Photo</span>' : '<span class="photo-badge-cover" id="badge-cover-' + p.id + '" style="display:none;">Cover Photo</span>'}

                <div class="photo-overlay-actions">
                    <button class="btn-cover-star ${isCover ? 'active' : ''}" id="star-${p.id}" onclick="setPhotoAsCover(${p.id}, '${escapeHtml(p.image_url)}')" title="Set as Cover Photo">
                        <i class="fa-solid fa-star"></i>
                    </button>
                </div>
            </div>

            <div class="photo-caption-bar">
                <input 
                    type="text" 
                    class="photo-caption-input" 
                    value="${escapeHtml(p.caption || '')}" 
                    placeholder="Add caption..." 
                    onchange="savePhotoCaption(${p.id}, this.value)"
                    title="Click to edit caption"
                >
                <button class="btn-delete-photo" onclick="deletePhoto(${p.id})" title="Delete Photo">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>
        `;

        container.appendChild(card);
    });
}

// Set Cover via AJAX
function setPhotoAsCover(photoId, newCoverUrl) {
    const fd = new FormData();
    fd.append('photo_id', photoId);
    fd.append('collection_id', collectionId);

    fetch('api/admin_collection_photos.php?action=set_cover', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Cover photo updated!');
            document.querySelectorAll('.photo-badge-cover').forEach(b => b.style.display = 'none');
            document.querySelectorAll('.btn-cover-star').forEach(s => s.classList.remove('active'));

            const badge = document.getElementById(`badge-cover-${photoId}`);
            if (badge) badge.style.display = 'inline-block';

            const star = document.getElementById(`star-${photoId}`);
            if (star) star.classList.add('active');

            const coverPrev = document.getElementById('coverPreviewImg');
            if (coverPrev) coverPrev.src = newCoverUrl;
        } else {
            alert(data.message || 'Failed to update cover');
        }
    })
    .catch(err => alert('Network error: ' + err.message));
}

// Delete Photo via AJAX
function deletePhoto(photoId) {
    if (!confirm('Remove this photo from collection?')) return;

    const card = document.getElementById(`photo-card-${photoId}`);
    if (card) card.style.opacity = '0.3';

    const fd = new FormData();
    fd.append('photo_id', photoId);
    fd.append('collection_id', collectionId);

    fetch('api/admin_collection_photos.php?action=delete_photo', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Photo removed');
            if (card) card.remove();
            
            document.getElementById('galleryCounter').innerText = data.remaining_count;
            document.getElementById('livePhotoCount').innerText = data.remaining_count;
        } else {
            if (card) card.style.opacity = '1';
            alert(data.message || 'Failed to delete photo');
        }
    })
    .catch(err => {
        if (card) card.style.opacity = '1';
        alert('Network error: ' + err.message);
    });
}

// Save Caption via AJAX
function savePhotoCaption(photoId, caption) {
    const fd = new FormData();
    fd.append('photo_id', photoId);
    fd.append('collection_id', collectionId);
    fd.append('caption', caption);

    fetch('api/admin_collection_photos.php?action=update_caption', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Caption saved');
        }
    })
    .catch(err => console.error("Caption save error:", err));
}

// Multi-File Drag & Drop Upload via AJAX
function handleFileSelect(files) {
    if (!files || files.length === 0) return;

    const fd = new FormData();
    fd.append('collection_id', collectionId);
    for (let i = 0; i < files.length; i++) {
        fd.append('files[]', files[i]);
    }

    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const percentText = document.getElementById('uploadPercentText');
    const statusText = document.getElementById('uploadStatusText');

    progressContainer.style.display = 'block';
    progressBar.style.width = '10%';
    percentText.innerText = '10%';
    statusText.innerText = `Uploading ${files.length} photo(s)...`;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/admin_collection_photos.php?action=upload_photos', true);

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            percentText.innerText = percent + '%';
        }
    };

    xhr.onload = function() {
        progressContainer.style.display = 'none';
        progressBar.style.width = '0%';

        if (xhr.status === 200) {
            try {
                const json = JSON.parse(xhr.responseText);
                if (json.success) {
                    showToast(json.message);
                    // Prepend newly uploaded photos
                    if (json.uploaded && json.uploaded.length > 0) {
                        appendPhotosToMasonry(json.uploaded);
                        loadPhotoBatch(1, true); // reload fresh
                    }
                } else {
                    alert(json.message || 'Upload failed');
                }
            } catch (e) {
                alert('Invalid response from server');
            }
        } else {
            alert('Upload error (status ' + xhr.status + ')');
        }
    };

    xhr.onerror = function() {
        progressContainer.style.display = 'none';
        alert('Network upload error');
    };

    xhr.send(fd);
}

// Preview locally chosen cover file
function previewCoverFile(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('coverPreviewImg').src = e.target.result;
            showToast('New cover chosen (click Save Details to persist)');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
