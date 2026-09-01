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

    if (empty($title)) {
        $message = "Outfit Title is required.";
        $message_type = "error";
    } else {
        try {
            $cover_image_path = $collection['cover_image'];

            // Handle New Cover Upload if uploaded via file input
            if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
                $upload = upload_image($_FILES['cover_file'], 'uploads/collections/' . $category . '/' . $collection['slug'], $collection['slug'] . '-cover-' . time());
                if (is_array($upload) && isset($upload['filepath'])) {
                    $cover_image_path = $upload['filepath'];
                }
            }

            $updStmt = $pdo->prepare("UPDATE collections SET 
                title = ?, sku = ?, category = ?, fabric = ?, work_type = ?, color = ?, subtitle = ?, description = ?, video_url = ?, cover_image = ?, is_featured = ?, sort_order = ?, status = ? 
                WHERE id = ?");
            $updStmt->execute([$title, $sku, $category, $fabric, $work_type, $color, $subtitle, $description, $video_url, $cover_image_path, $is_featured, $sort_order, $status, $id]);

            log_activity($pdo, 'update_collection', 'collection', $id, "Updated outfit style '$title'");
            $message = "Outfit style saved successfully!";
            $message_type = "success";

            // Refresh collection data
            $stmt = $pdo->prepare("SELECT * FROM collections WHERE id = ?");
            $stmt->execute([$id]);
            $collection = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $message = "Error updating outfit: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Total Photos in this collection
$totalPhotosCount = (int)$pdo->query("SELECT COUNT(*) FROM collection_images WHERE collection_id = $id")->fetchColumn();

// Categories list
$knownCategories = ['Blouse', 'Anarkali', 'Lehenga', 'Gown', 'Suit', 'Indo western', 'Kids wear', 'Sari', 'Sari Makeover', 'Family Twinning', 'Mens wear', 'Home furnishing'];
$uploadsPath = realpath(__DIR__ . '/uploads/collections');
if ($uploadsPath && is_dir($uploadsPath)) {
    foreach (scandir($uploadsPath) as $d) {
        if ($d === '.' || $d === '..') continue;
        if (is_dir($uploadsPath . DIRECTORY_SEPARATOR . $d)) {
            $cName = ucfirst($d);
            if (!in_array($cName, $knownCategories)) {
                $knownCategories[] = $cName;
            }
        }
    }
}

$page_title = "Edit Outfit Style - " . htmlspecialchars($collection['title']);
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* Modern Lookbook Studio Layout */
.edit-top-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
}

.photos-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.photo-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.2s;
    position: relative;
}

.photo-card.is-cover {
    border-color: #eab308;
    box-shadow: 0 0 0 2px rgba(234, 179, 8, 0.4);
}

.photo-img-wrap {
    position: relative;
    aspect-ratio: 1;
    background: #f1f5f9;
    overflow: hidden;
}

.photo-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cover-badge-tag {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #eab308;
    color: #000;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.photo-card-body {
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.angle-select {
    width: 100%;
    padding: 4px 6px;
    font-size: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #f8fafc;
}

.photo-actions-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #f1f5f9;
    padding-top: 8px;
}
</style>

<div class="wrap">
    <div class="edit-top-header">
        <div>
            <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">
                <i class="fa-solid fa-vest-patches" style="color: #6366f1;"></i>
                Edit Outfit: <?php echo htmlspecialchars($collection['title']); ?>
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 0;">
                Category: <strong><?php echo htmlspecialchars($collection['category']); ?></strong> &bull; 
                Slug: <code><?php echo htmlspecialchars($collection['slug']); ?></code> &bull; 
                <span id="headerPhotoCount"><?php echo $totalPhotosCount; ?> Media Items</span>
            </p>
        </div>

        <div style="display: flex; gap: 8px;">
            <a href="collections.php?cat=<?php echo urlencode($collection['category']); ?>" class="button"><i class="fa-solid fa-arrow-left"></i> All <?php echo htmlspecialchars($collection['category']); ?> Outfits</a>
            <a href="collection-ai-sorter.php" class="button button-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border: none;">
                <i class="fa-solid fa-wand-magic-sparkles"></i> AI Outfit Sorter
            </a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="notice notice-<?php echo $message_type; ?>" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
        <p><?php echo htmlspecialchars($message); ?></p>
    </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <!-- LEFT COLUMN: Outfit Info & Photoshoot Gallery -->
        <div>
            <!-- Form Details -->
            <form method="POST" action="" enctype="multipart/form-data" id="metaForm">
                <input type="hidden" name="update_collection_meta" value="1">

                <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <i class="fa-solid fa-sliders" style="color: #6366f1;"></i> Outfit Specifications
                    </h3>

                    <div style="margin-bottom: 14px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Outfit Title</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($collection['title']); ?>" class="form-control" style="width: 100%; font-size: 15px; font-weight: 600; padding: 8px 12px; border-radius: 6px;" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px;">
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Category</label>
                            <select name="category" class="form-control" style="width: 100%; padding: 7px; border-radius: 6px;">
                                <?php foreach ($knownCategories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($cat === $collection['category']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Style Code / SKU</label>
                            <input type="text" name="sku" value="<?php echo htmlspecialchars($collection['sku'] ?? ''); ?>" class="form-control" style="width: 100%; padding: 7px 12px; border-radius: 6px;" placeholder="e.g. BLS-001">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 14px;">
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Fabric</label>
                            <input type="text" name="fabric" value="<?php echo htmlspecialchars($collection['fabric'] ?? ''); ?>" class="form-control" style="width: 100%; padding: 7px 12px; border-radius: 6px;" placeholder="e.g. Pure Raw Silk">
                        </div>
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Work / Embroidery</label>
                            <input type="text" name="work_type" value="<?php echo htmlspecialchars($collection['work_type'] ?? ''); ?>" class="form-control" style="width: 100%; padding: 7px 12px; border-radius: 6px;" placeholder="e.g. Hand Zardozi &amp; Pearl">
                        </div>
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Color</label>
                            <input type="text" name="color" value="<?php echo htmlspecialchars($collection['color'] ?? ''); ?>" class="form-control" style="width: 100%; padding: 7px 12px; border-radius: 6px;" placeholder="e.g. Emerald Green">
                        </div>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Subtitle / Tagline</label>
                        <input type="text" name="subtitle" value="<?php echo htmlspecialchars($collection['subtitle'] ?? ''); ?>" class="form-control" style="width: 100%; padding: 7px 12px; border-radius: 6px;">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Lookbook Description</label>
                        <textarea name="description" rows="3" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px;"><?php echo htmlspecialchars($collection['description'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Outfit Video URL (Optional)</label>
                        <input type="text" name="video_url" value="<?php echo htmlspecialchars($collection['video_url'] ?? ''); ?>" class="form-control" style="width: 100%; padding: 7px 12px; border-radius: 6px;" placeholder="e.g. YouTube / Vimeo / MP4 Link">
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="button button-primary" style="background: #059669; border-color: #047857; padding: 6px 20px; font-weight: 700;">
                            <i class="fa-solid fa-floppy-disk"></i> Save Outfit Changes
                        </button>
                    </div>
                </div>
            </form>

            <!-- Photoshoot Media Section -->
            <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                    <div>
                        <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0;">
                            <i class="fa-solid fa-camera-retro" style="color: #6366f1;"></i> Photoshoot Media Angles
                        </h3>
                        <div style="font-size: 12px; color: #64748b; margin-top: 2px;">Assign angle tags (Front, Back, Close-up, Side, Model) or set the hero cover image.</div>
                    </div>
                    <div>
                        <label class="button button-primary" style="cursor: pointer; background: #6366f1; border-color: #4f46e5; margin: 0;">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload More Photos
                            <input type="file" id="ajaxUploadInput" multiple accept="image/*" style="display: none;" onchange="handleAjaxUpload(this)">
                        </label>
                    </div>
                </div>

                <div id="photosGrid" class="photos-gallery-grid">
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #94a3b8;">
                        <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
                        <div style="margin-top: 8px;">Loading gallery photos...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Publishing & Hero Cover Card -->
        <div>
            <!-- Publishing & Status -->
            <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                    Publishing
                </h3>

                <div style="margin-bottom: 14px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Status</label>
                    <select name="status" form="metaForm" class="form-control" style="width: 100%; padding: 6px; border-radius: 6px;">
                        <option value="published" <?php echo ($collection['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?php echo ($collection['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #334155;">Sort Order</label>
                    <input type="number" name="sort_order" form="metaForm" value="<?php echo (int)$collection['sort_order']; ?>" class="form-control" style="width: 100%; padding: 6px; border-radius: 6px;">
                </div>

                <div style="margin-bottom: 18px;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #334155;">
                        <input type="checkbox" name="is_featured" form="metaForm" value="1" <?php echo $collection['is_featured'] ? 'checked' : ''; ?>> 
                        <span><i class="fa-solid fa-star" style="color: #eab308;"></i> Featured Lookbook Item</span>
                    </label>
                </div>

                <button type="submit" form="metaForm" class="button button-primary" style="width: 100%; padding: 10px; font-weight: 700; background: #059669; border-color: #047857;">
                    <i class="fa-solid fa-check"></i> Save Outfit Style
                </button>
            </div>

            <!-- Current Hero Cover Card -->
            <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                    Hero Cover Image
                </h3>

                <div style="aspect-ratio: 16/11; background: #f1f5f9; border-radius: 6px; overflow: hidden; margin-bottom: 12px; border: 1px solid #e2e8f0;">
                    <img id="coverPreviewImg" src="<?php echo htmlspecialchars(get_collection_image_url($collection['cover_image'])); ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='assets/images/placeholder.svg';">
                </div>

                <div>
                    <label style="font-weight: 600; font-size: 12px; display: block; margin-bottom: 4px; color: #334155;">Upload Replacement Cover:</label>
                    <input type="file" name="cover_file" form="metaForm" accept="image/*" class="form-control" style="width: 100%; padding: 6px; border-radius: 6px;">
                    <span style="font-size: 11px; color: #64748b; margin-top: 4px; display: block;">Or click "Make Cover" on any photo in the gallery on the left.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const collectionId = <?php echo $id; ?>;
let currentPhotos = [];

const angleOptions = ['Front View', 'Back View', 'Close-up Detail', 'Side View', 'Model Shot', 'Full View', 'Video Preview'];

document.addEventListener('DOMContentLoaded', () => {
    loadPhotos();
});

async function loadPhotos() {
    const grid = document.getElementById('photosGrid');
    grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #94a3b8;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><div style="margin-top: 8px;">Loading photoshoot media...</div></div>`;

    try {
        const res = await fetch(`api/admin_collection_photos.php?action=list&collection_id=${collectionId}&per_page=100`);
        const data = await res.json();

        if (!data.success) {
            grid.innerHTML = `<div style="grid-column: 1/-1; color: #ef4444; padding: 20px;">Error: ${data.message || 'Failed to load photos'}</div>`;
            return;
        }

        currentPhotos = data.data || [];
        document.getElementById('headerPhotoCount').textContent = `${currentPhotos.length} Media Items`;

        if (data.cover_image) {
            document.getElementById('coverPreviewImg').src = getImageUrl(data.cover_image);
        }

        renderPhotosGrid();

    } catch (err) {
        grid.innerHTML = `<div style="grid-column: 1/-1; color: #ef4444; padding: 20px;">Network error: ${err.message}</div>`;
    }
}

function renderPhotosGrid() {
    const grid = document.getElementById('photosGrid');
    if (!currentPhotos.length) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; color: #64748b;">
                <i class="fa-solid fa-camera fa-2x" style="color: #cbd5e1; margin-bottom: 8px;"></i>
                <div>No photos uploaded for this outfit yet. Click <strong>Upload More Photos</strong> above.</div>
            </div>
        `;
        return;
    }

    grid.innerHTML = currentPhotos.map(p => {
        const isCover = p.is_cover;
        return `
            <div class="photo-card ${isCover ? 'is-cover' : ''}" id="photoCard_${p.id}">
                <div class="photo-img-wrap">
                    <img src="${p.image_url}" alt="Outfit Photo" onerror="this.onerror=null; this.src='assets/images/placeholder.svg';">
                    ${isCover ? `<span class="cover-badge-tag"><i class="fa-solid fa-star"></i> COVER</span>` : ''}
                </div>

                <div class="photo-card-body">
                    <div>
                        <label style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; display: block; margin-bottom: 2px;">Angle / View</label>
                        <select class="angle-select" onchange="updatePhotoAngle(${p.id}, this.value)">
                            ${angleOptions.map(opt => `
                                <option value="${opt}" ${opt === p.angle_type ? 'selected' : ''}>${opt}</option>
                            `).join('')}
                        </select>
                    </div>

                    <div class="photo-actions-row">
                        ${!isCover ? `
                            <button type="button" class="button" style="font-size: 11px; padding: 2px 8px;" onclick="setAsCover(${p.id})">
                                <i class="fa-solid fa-star"></i> Make Cover
                            </button>
                        ` : `
                            <span style="font-size: 11px; font-weight: 700; color: #b45309;"><i class="fa-solid fa-check"></i> Hero Cover</span>
                        `}

                        <button type="button" class="button" style="color: #ef4444; border-color: #fca5a5; font-size: 11px; padding: 2px 8px;" onclick="deletePhoto(${p.id})">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

async function updatePhotoAngle(photoId, angleType) {
    const formData = new FormData();
    formData.append('photo_id', photoId);
    formData.append('collection_id', collectionId);
    formData.append('angle_type', angleType);

    try {
        await fetch('api/admin_collection_photos.php?action=update_angle', {
            method: 'POST',
            body: formData
        });
    } catch (e) {
        console.error(e);
    }
}

async function setAsCover(photoId) {
    const formData = new FormData();
    formData.append('photo_id', photoId);
    formData.append('collection_id', collectionId);

    try {
        const res = await fetch('api/admin_collection_photos.php?action=set_cover', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            loadPhotos();
        }
    } catch (e) {
        alert('Failed to set cover photo');
    }
}

async function deletePhoto(photoId) {
    if (!confirm('Are you sure you want to delete this photo?')) return;

    const formData = new FormData();
    formData.append('photo_id', photoId);
    formData.append('collection_id', collectionId);

    try {
        const res = await fetch('api/admin_collection_photos.php?action=delete_photo', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            loadPhotos();
        } else {
            alert(data.message || 'Error deleting photo');
        }
    } catch (e) {
        alert('Network error');
    }
}

async function handleAjaxUpload(input) {
    if (!input.files || !input.files.length) return;

    const formData = new FormData();
    formData.append('collection_id', collectionId);
    for (let i = 0; i < input.files.length; i++) {
        formData.append('files[]', input.files[i]);
    }

    const origText = input.parentElement.innerHTML;
    input.parentElement.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Uploading ${input.files.length} photo(s)...`;

    try {
        const res = await fetch('api/admin_collection_photos.php?action=upload_photos', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            loadPhotos();
        } else {
            alert(data.message || 'Upload failed');
        }
    } catch (e) {
        alert('Upload failed: ' + e.message);
    }
}

function getImageUrl(path) {
    if (!path) return 'assets/images/placeholder.svg';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    const clean = path.replace(/^\/+/, '').replace(/^admin\//, '');
    return clean.split('/').map(encodeURIComponent).join('/');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
