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

// Fetch AI Model Generator reference masters
$db_ai_models = [];
$db_shot_types = [];
$db_hair_styles = [];
try {
    $db_ai_models = $pdo->query("SELECT * FROM ai_models WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $db_shot_types = $pdo->query("SELECT * FROM ai_shot_types WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $db_hair_styles = $pdo->query("SELECT * FROM ai_hair_styles WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$page_title = "Edit Outfit Style - " . htmlspecialchars($collection['title']);
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* AI Studio Styles */
.ai-radio-hidden {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.ai-radio-hidden:checked + .ai-model-box {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.35);
}
.ai-bg-pill {
    padding: 5px 12px;
    background: #f4f4f5;
    border: 1px solid #e4e4e7;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 500;
    color: #18181b;
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
}
.ai-bg-pill:hover {
    background: #e4e4e7;
    color: #09090b;
}
.ai-radio-hidden:checked + .ai-bg-pill {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}

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

            <!-- AI Image Studio Card (Gemini) -->
            <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 16px;">
                    <div>
                        <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-wand-magic-sparkles" style="color: #6366f1;"></i> AI Model Image Studio (Gemini)
                        </h3>
                        <div style="font-size: 12px; color: #64748b; margin-top: 2px;">Generate photorealistic fashion model photos wearing this exact outfit. Saved photos will automatically be added to the Photoshoot Media gallery above.</div>
                    </div>
                    <span style="font-size: 11px; font-weight: 700; background: #e0e7ff; color: #4338ca; padding: 3px 10px; border-radius: 9999px;">
                        <i class="fa-solid fa-bolt"></i> Gemini Vision 3.1
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- Model Face Picker -->
                    <div>
                        <label style="font-size: 12px; font-weight: 600; color: #0f172a; margin-bottom: 6px; display: block;">Model Face (Optional)</label>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <label class="ai-model-picker">
                                <input type="radio" name="ai_model_face" value="" checked class="ai-radio-hidden">
                                <div class="ai-model-box" style="width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; background: #fafafa; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer;">
                                    <span style="font-size: 10px; color: #64748b; font-weight: 700;">NONE</span>
                                </div>
                            </label>
                            <?php if (!empty($db_ai_models)): ?>
                                <?php foreach ($db_ai_models as $dbm): ?>
                                <label class="ai-model-picker" style="position: relative;" title="<?php echo htmlspecialchars($dbm['name']); ?>">
                                    <input type="radio" name="ai_model_face" value="<?php echo htmlspecialchars($dbm['image_path']); ?>" data-shot="<?php echo htmlspecialchars($dbm['shot_type'] ?? 'Full Body'); ?>" data-hair="<?php echo htmlspecialchars($dbm['hair_style'] ?? 'As per product'); ?>" class="ai-radio-hidden">
                                    <div class="ai-model-box" style="width: 56px; height: 56px; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; cursor: pointer;">
                                        <img src="<?php echo htmlspecialchars($dbm['image_path']); ?>" alt="<?php echo htmlspecialchars($dbm['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="ai-model-picker" style="position: relative;">
                                    <input type="radio" name="ai_model_face" value="assets/models/model_<?= $i ?>.png" class="ai-radio-hidden">
                                    <div class="ai-model-box" style="width: 56px; height: 56px; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; cursor: pointer;" title="Model <?= $i ?>">
                                        <img src="assets/models/model_<?= $i ?>.png" alt="Model <?= $i ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                </label>
                                <?php endfor; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Background / Props Presets -->
                    <div>
                        <label style="font-size: 12px; font-weight: 600; color: #0f172a; margin-bottom: 6px; display: block;">Background / Props Preset</label>
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
                        <input type="text" id="ai_bg_custom" class="form-control" style="margin-top: 8px; width: 100%; font-size: 12px; padding: 7px 12px; border-radius: 6px;" value="elegant royal palace with marble pillars and chandeliers" placeholder="Describe background and props...">
                    </div>

                    <!-- Shot Type & Hair Controls -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; color: #0f172a; margin-bottom: 6px; display: block;">Shot Type</label>
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                <?php if (!empty($db_shot_types)): ?>
                                    <?php foreach ($db_shot_types as $sIdx => $st): ?>
                                    <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1e293b;" title="<?php echo htmlspecialchars($st['prompt_text']); ?>">
                                        <input type="radio" name="ai_shot_type" value="<?php echo htmlspecialchars($st['prompt_text']); ?>" data-name="<?php echo htmlspecialchars($st['name']); ?>" <?php echo $sIdx === 0 ? 'checked' : ''; ?> style="accent-color: #0f172a;">
                                        <?php echo htmlspecialchars($st['name']); ?>
                                    </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1e293b;"><input type="radio" name="ai_shot_type" value="close-up portrait shot focusing on the face and details" data-name="Close-up Portrait" style="accent-color: #0f172a;"> Close-up Portrait</label>
                                    <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1e293b;"><input type="radio" name="ai_shot_type" value="half body shot from waist up, showing torso and face" data-name="Half Body" style="accent-color: #0f172a;"> Half Body</label>
                                    <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1e293b;"><input type="radio" name="ai_shot_type" value="full body head-to-toe shot showing the complete outfit look" data-name="Full Body" checked style="accent-color: #0f172a;"> Full Body</label>
                                    <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1e293b;"><input type="radio" name="ai_shot_type" value="shot from behind showing the back design and details" data-name="Back View" style="accent-color: #0f172a;"> Back View</label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <label style="font-size: 12px; font-weight: 600; color: #0f172a; margin-bottom: 6px; display: block;">Hair Style</label>
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                <?php if (!empty($db_hair_styles)): ?>
                                    <?php foreach ($db_hair_styles as $hIdx => $hs): ?>
                                    <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1e293b;" title="<?php echo htmlspecialchars($hs['prompt_text']); ?>">
                                        <input type="radio" name="ai_hair_style" value="<?php echo htmlspecialchars($hs['prompt_text']); ?>" data-name="<?php echo htmlspecialchars($hs['name']); ?>" <?php echo $hIdx === 0 ? 'checked' : ''; ?> style="accent-color: #0f172a;">
                                        <?php echo htmlspecialchars($hs['name']); ?>
                                    </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1e293b;"><input type="radio" name="ai_hair_style" value="open flowing hair with soft waves" data-name="Open Flowing" style="accent-color: #0f172a;"> Open Flowing</label>
                                    <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1e293b;"><input type="radio" name="ai_hair_style" value="neatly tied bun with flowers" data-name="Tied / Bun" style="accent-color: #0f172a;"> Tied / Bun</label>
                                    <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1e293b;"><input type="radio" name="ai_hair_style" value="traditional long braided hair" data-name="Traditional Braid" style="accent-color: #0f172a;"> Traditional Braid</label>
                                    <label style="font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: #1e293b;"><input type="radio" name="ai_hair_style" value="" data-name="As per outfit" checked style="accent-color: #0f172a;"> Default</label>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Final Auto-Assembled Prompt -->
                    <div>
                        <label style="font-size: 12px; font-weight: 600; color: #0f172a; margin-bottom: 6px; display: block;">Final Prompt (Auto-Assembled)</label>
                        <textarea id="ai_final_prompt" rows="3" class="form-control" style="width: 100%; font-size: 12px; line-height: 1.5; padding: 8px 12px; border-radius: 6px;">A photorealistic beautiful Indian fashion model wearing this exact <?php echo htmlspecialchars($collection['title']); ?>. The background should have elegant royal palace with marble pillars and chandeliers. Shot type: full body head-to-toe shot showing the complete outfit look. Aspect ratio: 2:3 vertical fashion portrait format.</textarea>
                    </div>

                    <!-- Action Bar -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 14px; flex-wrap: wrap; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="font-size: 12px; font-weight: 600; color: #64748b;">Variations:</label>
                            <div style="display: flex; gap: 4px;">
                                <?php for ($n = 1; $n <= 4; $n++): ?>
                                <label class="ai-bg-picker">
                                    <input type="radio" name="ai_num_images" value="<?= $n ?>" <?= $n === 1 ? 'checked' : '' ?> class="ai-radio-hidden">
                                    <div class="ai-bg-pill"><?= $n ?> <?= $n === 1 ? 'Image' : 'Images' ?></div>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <button type="button" onclick="aiGenerateAdvancedImage()" id="aiImageBtn" class="button button-primary" style="background: #6366f1; border-color: #4f46e5; padding: 8px 18px; font-weight: 700; font-size: 13px;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Generate Model Image
                        </button>
                    </div>

                    <!-- Loading Indicator -->
                    <div id="aiImageLoading" style="display: none; align-items: center; gap: 10px; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; color: #334155;">
                        <i class="fa-solid fa-spinner fa-spin" style="font-size: 16px; color: #6366f1;"></i>
                        <span>AI is generating model image(s)... Please wait 15-20 seconds.</span>
                    </div>

                    <!-- Generated Results -->
                    <div id="aiImageResult" style="display: none; margin-top: 12px; padding-top: 14px; border-top: 1px solid #f1f5f9;">
                        <p style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 12px;">Generated Fashion Model Images:</p>
                        
                        <div id="aiImageGrid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 14px;"></div>
                        
                        <div style="display: flex; justify-content: center;">
                            <button type="button" onclick="resetAiImage()" class="button" style="font-size: 12px; padding: 6px 16px;">
                                <i class="fa-solid fa-rotate-left"></i> Clear &amp; Try Again
                            </button>
                        </div>
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

// -----------------------------------------------------------------------------
// Gemini AI Model Image Generator Functions
// -----------------------------------------------------------------------------
const collectionTitle = <?php echo json_encode($collection['title'] ?? 'outfit'); ?>;

function notifyMsg(type, title, text) {
    if (typeof toast !== 'undefined' && typeof toast[type] === 'function') {
        toast[type](title, text);
    } else {
        alert(title + ': ' + text);
    }
}

function updateFinalPrompt() {
    const faceInput = document.querySelector('input[name="ai_model_face"]:checked')?.value || '';
    const customBg = document.getElementById('ai_bg_custom')?.value.trim() || 'clean studio background';
    const shotType = document.querySelector('input[name="ai_shot_type"]:checked')?.value || '';
    const hairStyle = document.querySelector('input[name="ai_hair_style"]:checked')?.value || '';

    let promptParts = [
        `A photorealistic beautiful Indian fashion model wearing this exact ${collectionTitle}.`,
        `The background should have ${customBg}.`,
        `Shot type: ${shotType}.`,
        `Do not change the outfit details, embroidery, silhouette or color scheme.`,
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

async function aiGenerateAdvancedImage() {
    const btn = document.getElementById('aiImageBtn');
    const faceInput = document.querySelector('input[name="ai_model_face"]:checked')?.value || '';
    const finalPrompt = document.getElementById('ai_final_prompt')?.value.trim() || '';
    const numImages = document.querySelector('input[name="ai_num_images"]:checked')?.value || 1;

    if (!btn) return;
    btn.disabled = true;
    document.getElementById('aiImageLoading').style.display = 'flex';
    document.getElementById('aiImageResult').style.display = 'none';
    document.getElementById('aiImageGrid').innerHTML = '';

    try {
        const response = await fetch(`api/ai_product_api.php?action=ai_generate_model_image&collection_id=${collectionId}`, {
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
                    <div style="display: flex; flex-direction: column; gap: 8px; background: #ffffff; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div style="width: 100%; aspect-ratio: 2/3; overflow: hidden; border-radius: 6px; background: #f8fafc;">
                            <img src="data:image/jpeg;base64,${b64}" alt="Generated Model" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <button type="button" onclick="saveAiGeneratedImage(this, '${b64}')" class="button button-primary" style="width: 100%; justify-content: center; padding: 7px; font-size: 12px; font-weight: 700; background: #059669; border-color: #047857;">
                            <i class="fa-solid fa-floppy-disk"></i> Save to Media Gallery
                        </button>
                    </div>
                `;
            });
            document.getElementById('aiImageResult').style.display = 'block';
            notifyMsg('success', 'Images Generated', 'Review the generated photos and save them to the Photoshoot Media gallery.');
        } else {
            const msg = data.error || 'Failed to generate model images';
            notifyMsg('error', 'Image Generation Failed', msg);
        }
    } catch (err) {
        console.error(err);
        notifyMsg('error', 'Network Error', 'A network error occurred while generating images.');
    } finally {
        btn.disabled = false;
        document.getElementById('aiImageLoading').style.display = 'none';
    }
}

function resetAiImage() {
    document.getElementById('aiImageResult').style.display = 'none';
    document.getElementById('aiImageGrid').innerHTML = '';
    document.getElementById('ai_final_prompt')?.focus();
}

async function saveAiGeneratedImage(btn, base64Str) {
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;

    try {
        const response = await fetch(`api/ai_product_api.php?action=save_ai_image&collection_id=${collectionId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image_base64: base64Str })
        });
        const data = await response.json();

        if (data.success && data.path) {
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Saved!';
            btn.style.background = '#059669';
            btn.style.borderColor = '#047857';
            notifyMsg('success', 'Image Saved', 'AI model photo added to Photoshoot Media gallery.');
            // Reload photoshoot media angles gallery
            loadPhotos();
        } else {
            const msg = data.error || 'Unknown error';
            notifyMsg('error', 'Save Failed', msg);
            btn.innerHTML = origHTML;
            btn.disabled = false;
        }
    } catch (err) {
        console.error(err);
        notifyMsg('error', 'Network Error', 'Network error while saving AI image.');
        btn.innerHTML = origHTML;
        btn.disabled = false;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
