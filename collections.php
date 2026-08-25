<?php
// admin/collections.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$message_type = 'success';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated') {
        $message = "Collection updated successfully.";
    } elseif ($_GET['msg'] === 'created') {
        $message = "Collection created successfully.";
    } elseif ($_GET['msg'] === 'deleted') {
        $message = "Collection deleted successfully.";
    }
}

// 1. Handle Deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT title FROM collections WHERE id = ?");
        $stmt->execute([$del_id]);
        $coll = $stmt->fetch();
        if ($coll) {
            $pdo->prepare("DELETE FROM collections WHERE id = ?")->execute([$del_id]);
            log_activity($pdo, 'delete_collection', 'collection', $del_id, "Deleted collection: {$coll['title']}");
            header("Location: collections.php?msg=deleted");
            exit();
        }
    } catch (PDOException $e) {
        $message = "Error deleting collection: " . $e->getMessage();
        $message_type = "error";
    }
}

// 2. Handle Status or Featured Toggle
if (isset($_GET['toggle_featured']) && is_numeric($_GET['toggle_featured'])) {
    $toggle_id = (int)$_GET['toggle_featured'];
    $pdo->prepare("UPDATE collections SET is_featured = 1 - is_featured WHERE id = ?")->execute([$toggle_id]);
    header("Location: collections.php?msg=updated");
    exit();
}

if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $toggle_id = (int)$_GET['toggle_status'];
    $pdo->prepare("UPDATE collections SET status = IF(status='published','draft','published') WHERE id = ?")->execute([$toggle_id]);
    header("Location: collections.php?msg=updated");
    exit();
}

// 3. Filters & Queries
$search = trim($_GET['s'] ?? '');
$filter_cat = trim($_GET['cat'] ?? '');
$view_mode = trim($_GET['view'] ?? 'masonry'); // 'masonry' or 'table'

$query = "SELECT c.*, COUNT(ci.id) as total_images 
          FROM collections c 
          LEFT JOIN collection_images ci ON c.id = ci.collection_id 
          WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (c.title LIKE ? OR c.subtitle LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_cat !== '') {
    $query .= " AND c.category = ?";
    $params[] = $filter_cat;
}

$query .= " GROUP BY c.id ORDER BY c.sort_order ASC, c.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch preview images (up to 4) for each collection to show multi-photo preview
$previewStmt = $pdo->prepare("SELECT image_path FROM collection_images WHERE collection_id = ? ORDER BY sort_order ASC LIMIT 4");
foreach ($collections as &$c) {
    $previewStmt->execute([$c['id']]);
    $c['preview_images'] = $previewStmt->fetchAll(PDO::FETCH_COLUMN);
}
unset($c);

// Fetch category counts for WordPress subsubsub bar
$catCounts = $pdo->query("SELECT category, COUNT(*) as count FROM collections WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY category ASC")->fetchAll(PDO::FETCH_ASSOC);
$totalCollectionsCount = $pdo->query("SELECT COUNT(*) FROM collections")->fetchColumn();
$totalPhotosCount = $pdo->query("SELECT COUNT(*) FROM collection_images")->fetchColumn();

// Include layout headers after redirect processing
$page_title = "Collections & Lookbook";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* Progressive Image Loading & Masonry Styling */
.wp-collection-masonry {
    column-count: 1;
    column-gap: 20px;
    margin-top: 15px;
}
@media (min-width: 640px) {
    .wp-collection-masonry { column-count: 2; }
}
@media (min-width: 1024px) {
    .wp-collection-masonry { column-count: 3; }
}
@media (min-width: 1400px) {
    .wp-collection-masonry { column-count: 3; }
}

.wp-masonry-card {
    break-inside: avoid;
    margin-bottom: 24px;
    background: #ffffff;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.wp-masonry-card:hover {
    box-shadow: 0 6px 16px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.wp-prog-img-wrap {
    position: relative;
    background: #f0f0f1;
    overflow: hidden;
    min-height: 180px;
}
.wp-prog-img {
    width: 100%;
    height: auto;
    display: block;
    opacity: 0;
    transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s ease;
}
.wp-prog-img.loaded {
    opacity: 1;
}
.wp-masonry-card:hover .wp-prog-img.loaded {
    transform: scale(1.02);
}

.wp-collection-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(17, 24, 39, 0.85);
    color: #f3f4f6;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.wp-photos-count {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ffffff;
    color: #1d2327;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 4px;
}

.wp-featured-star {
    position: absolute;
    bottom: 10px;
    right: 10px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #dcdcde;
    text-decoration: none;
    transition: transform 0.2s;
}
.wp-featured-star.active {
    color: #dba617;
}
.wp-featured-star:hover {
    transform: scale(1.1);
}

.wp-mini-thumbs-strip {
    display: flex;
    gap: 4px;
    padding: 6px;
    background: #f6f7f7;
    border-top: 1px solid #dcdcde;
    border-bottom: 1px solid #dcdcde;
}
.wp-mini-thumb {
    flex: 1;
    height: 50px;
    border-radius: 3px;
    overflow: hidden;
    background: #e2e4e7;
}
.wp-mini-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0;
    transition: opacity 0.3s;
}
.wp-mini-thumb img.loaded {
    opacity: 1;
}

.wp-collection-body {
    padding: 14px 16px;
}
.wp-collection-title {
    font-size: 15px;
    font-weight: 700;
    color: #1d2327;
    margin: 0 0 4px 0;
    line-height: 1.35;
}
.wp-collection-subtitle {
    font-size: 12px;
    color: #646970;
    margin: 0 0 10px 0;
    line-height: 1.4;
}
.wp-collection-desc {
    font-size: 12px;
    color: #50575e;
    line-height: 1.5;
    margin: 0 0 12px 0;
}
.wp-collection-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #f0f0f1;
}
</style>

<div class="wrap">
    
    <!-- WordPress Standard Header -->
    <h1 class="wp-heading-inline">
        <i class="fa-solid fa-camera-retro" style="color: #2271b1; margin-right: 6px;"></i> Collections &amp; Lookbook
    </h1>
    <a href="collection-add.php" class="page-title-action">Add New Collection</a>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible" style="margin: 15px 0;">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <!-- WordPress Standard Subsubsub Category Tabs -->
    <ul class="subsubsub" style="margin-bottom: 12px;">
        <li class="all">
            <a href="collections.php" class="<?php echo ($filter_cat === '') ? 'current' : ''; ?>">
                All Collections <span class="count">(<?php echo $totalCollectionsCount; ?>)</span>
            </a> |
        </li>
        <?php foreach ($catCounts as $idx => $cc): ?>
            <li class="<?php echo generate_slug($cc['category']); ?>">
                <a href="collections.php?cat=<?php echo urlencode($cc['category']); ?>" class="<?php echo ($filter_cat === $cc['category']) ? 'current' : ''; ?>">
                    <?php echo htmlspecialchars($cc['category']); ?> <span class="count">(<?php echo $cc['count']; ?>)</span>
                </a> <?php echo ($idx < count($catCounts) - 1) ? '|' : ''; ?>
            </li>
        <?php endforeach; ?>
        <li style="float: right; margin-left: 10px; color: #646970;">
            <strong><?php echo number_format($totalPhotosCount); ?></strong> total shoot photos linked in database
        </li>
    </ul>

    <!-- Tablenav Top Filters -->
    <div class="tablenav top" style="clear: both; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <form method="GET" action="collections.php" class="alignleft actions" style="display: flex; gap: 8px; align-items: center;">
            <input type="search" name="s" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search collections..." style="width: 220px;">
            
            <select name="cat">
                <option value="">All Categories</option>
                <?php foreach ($catCounts as $cc): ?>
                    <option value="<?php echo htmlspecialchars($cc['category']); ?>" <?php echo ($filter_cat === $cc['category']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cc['category']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
            <button type="submit" class="button">Filter</button>
            <?php if ($search !== '' || $filter_cat !== ''): ?>
                <a href="collections.php?view=<?php echo $view_mode; ?>" class="button">Reset</a>
            <?php endif; ?>
        </form>

        <!-- View Switcher (Masonry vs List Table) -->
        <div class="alignright actions">
            <a href="collections.php?view=masonry<?php echo $filter_cat ? '&cat='.urlencode($filter_cat) : ''; ?>" class="button <?php echo ($view_mode === 'masonry') ? 'button-primary' : ''; ?>" title="Progressive Masonry View">
                <i class="fa-solid fa-table-cells"></i> Full Image Masonry
            </a>
            <a href="collections.php?view=table<?php echo $filter_cat ? '&cat='.urlencode($filter_cat) : ''; ?>" class="button <?php echo ($view_mode === 'table') ? 'button-primary' : ''; ?>" title="Table View">
                <i class="fa-solid fa-list"></i> Table List
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT: Progressive Masonry vs Table List -->
    <?php if (empty($collections)): ?>
        <div class="card" style="padding: 40px; text-align: center; margin-top: 20px;">
            <i class="fa-solid fa-camera" style="font-size: 36px; color: #8c8f94; margin-bottom: 12px;"></i>
            <h2>No collections found matching your criteria.</h2>
            <p><a href="collection-add.php" class="button button-primary">Create Your First Collection</a></p>
        </div>
    <?php elseif ($view_mode === 'table'): ?>
        <!-- Standard WordPress Table View -->
        <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th scope="col" style="width: 90px;">Cover</th>
                    <th scope="col">Title &amp; Subtitle</th>
                    <th scope="col" style="width: 140px;">Category</th>
                    <th scope="col" style="width: 100px; text-align: center;">Photos</th>
                    <th scope="col" style="width: 90px; text-align: center;">Featured</th>
                    <th scope="col" style="width: 100px;">Status</th>
                    <th scope="col" style="width: 160px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($collections as $c): 
                    $coverUrl = get_collection_image_url($c['cover_image']);
                ?>
                <tr>
                    <td>
                        <img src="<?php echo htmlspecialchars($coverUrl); ?>" alt="Cover" style="width: 70px; height: 70px; object-fit: cover; border-radius: 4px; border: 1px solid #dcdcde;">
                    </td>
                    <td>
                        <strong>
                            <a href="collection-edit.php?id=<?php echo $c['id']; ?>" class="row-title" style="font-size: 14px;">
                                <?php echo htmlspecialchars($c['title']); ?>
                            </a>
                        </strong>
                        <?php if ($c['subtitle']): ?>
                            <div style="color: #646970; font-size: 12px; margin-top: 2px;"><?php echo htmlspecialchars($c['subtitle']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge" style="background: #f0f0f1; border: 1px solid #c3c4c7; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                            <?php echo htmlspecialchars($c['category']); ?>
                        </span>
                    </td>
                    <td style="text-align: center;">
                        <strong style="color: #2271b1;"><?php echo $c['total_images']; ?></strong> photos
                    </td>
                    <td style="text-align: center;">
                        <a href="collections.php?toggle_featured=<?php echo $c['id']; ?>" title="Toggle Featured" style="font-size: 16px; color: <?php echo $c['is_featured'] ? '#dba617' : '#c3c4c7'; ?>;">
                            <i class="fa-solid fa-star"></i>
                        </a>
                    </td>
                    <td>
                        <?php if ($c['status'] === 'published'): ?>
                            <span style="color: #008a20; font-weight: 600; font-size: 12px;">Published</span>
                        <?php else: ?>
                            <span style="color: #d63638; font-weight: 600; font-size: 12px;">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <a href="collection-edit.php?id=<?php echo $c['id']; ?>" class="button button-small"><i class="fa-solid fa-pen"></i> Edit Photos</a>
                        <a href="collections.php?delete=<?php echo $c['id']; ?>" class="button button-small button-link-delete" onclick="return confirm('Delete this collection?');" style="color: #b32d2e;"><i class="fa-solid fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
        <!-- PROGRESSIVE FULL-IMAGE MASONRY VIEW (Editorial / Pinterest Style) -->
        <div class="wp-collection-masonry">
            <?php foreach ($collections as $c): 
                $coverUrl = get_collection_image_url($c['cover_image']);
            ?>
            <div class="wp-masonry-card">
                
                <!-- Main Full-Image Progressive Cover Container -->
                <div class="wp-prog-img-wrap">
                    <img 
                        src="<?php echo htmlspecialchars($coverUrl); ?>" 
                        alt="<?php echo htmlspecialchars($c['title']); ?>" 
                        class="wp-prog-img"
                        loading="lazy"
                        onload="this.classList.add('loaded')"
                    >

                    <!-- Category Badge -->
                    <span class="wp-collection-badge">
                        <?php echo htmlspecialchars($c['category']); ?>
                    </span>

                    <!-- Total Photos Count Pill -->
                    <span class="wp-photos-count">
                        <i class="fa-solid fa-images" style="color: #2271b1;"></i> <?php echo $c['total_images']; ?> Photos
                    </span>

                    <!-- Featured Star Toggle -->
                    <a href="collections.php?toggle_featured=<?php echo $c['id']; ?>" class="wp-featured-star <?php echo $c['is_featured'] ? 'active' : ''; ?>" title="<?php echo $c['is_featured'] ? 'Featured on Homepage (Click to Unfeature)' : 'Click to Feature on Homepage'; ?>">
                        <i class="fa-solid fa-star"></i>
                    </a>
                </div>

                <!-- Preview Mini Thumbnails Strip (Up to 4 shoot frames) -->
                <?php if (!empty($c['preview_images']) && count($c['preview_images']) > 1): ?>
                <div class="wp-mini-thumbs-strip">
                    <?php foreach ($c['preview_images'] as $pImg): 
                        $pUrl = get_collection_image_url($pImg);
                    ?>
                    <div class="wp-mini-thumb">
                        <img src="<?php echo htmlspecialchars($pUrl); ?>" alt="Preview" loading="lazy" onload="this.classList.add('loaded')">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Card Content -->
                <div class="wp-collection-body">
                    <h3 class="wp-collection-title">
                        <a href="collection-edit.php?id=<?php echo $c['id']; ?>" style="text-decoration: none; color: inherit;">
                            <?php echo htmlspecialchars($c['title']); ?>
                        </a>
                    </h3>

                    <?php if ($c['subtitle']): ?>
                        <div class="wp-collection-subtitle">
                            <?php echo htmlspecialchars($c['subtitle']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($c['description']): ?>
                        <div class="wp-collection-desc">
                            <?php echo htmlspecialchars(mb_strimwidth($c['description'], 0, 110, '...')); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Footer Actions -->
                    <div class="wp-collection-footer">
                        <div>
                            <a href="collections.php?toggle_status=<?php echo $c['id']; ?>" style="text-decoration: none;">
                                <?php if ($c['status'] === 'published'): ?>
                                    <span style="color: #008a20; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-circle" style="font-size: 8px;"></i> Published
                                    </span>
                                <?php else: ?>
                                    <span style="color: #646970; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-regular fa-circle" style="font-size: 8px;"></i> Draft
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>

                        <div style="display: flex; gap: 6px;">
                            <a href="collection-edit.php?id=<?php echo $c['id']; ?>" class="button button-primary button-small" style="font-weight: 600;">
                                <i class="fa-solid fa-pen-to-square"></i> Manage Photos (<?php echo $c['total_images']; ?>)
                            </a>
                            <a href="collections.php?delete=<?php echo $c['id']; ?>" class="button button-small" style="color: #b32d2e;" onclick="return confirm('Are you sure you want to delete this collection?');" title="Delete Collection">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
// Auto progressive image load fallback for cached images
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.wp-prog-img, .wp-mini-thumb img').forEach(function(img) {
        if (img.complete) {
            img.classList.add('loaded');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
