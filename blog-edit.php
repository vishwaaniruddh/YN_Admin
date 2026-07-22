<?php
// admin/blog-edit.php
$page_title = "Edit Blog";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$message = '';
$message_type = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='wrap'><h2>Invalid Blog ID</h2></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$blog_id = (int)$_GET['id'];

// Handle image deletion from gallery
if (isset($_GET['delete_image'])) {
    $img_id = (int)$_GET['delete_image'];
    try {
        $stmt = $pdo->prepare("SELECT image_path, thumb_path FROM blog_images WHERE id = ? AND blog_id = ?");
        $stmt->execute([$img_id, $blog_id]);
        $img = $stmt->fetch();
        if ($img) {
            if (file_exists(__DIR__ . '/../' . $img['image_path'])) unlink(__DIR__ . '/../' . $img['image_path']);
            if (file_exists(__DIR__ . '/../' . $img['thumb_path'])) unlink(__DIR__ . '/../' . $img['thumb_path']);
            $pdo->prepare("DELETE FROM blog_images WHERE id = ?")->execute([$img_id]);
            $message = "Image deleted successfully.";
            $message_type = "success";
        }
    } catch (PDOException $e) {
        $message = "Error deleting image.";
        $message_type = "error";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    
    if (empty($title)) {
        $message = "Title is required.";
        $message_type = "error";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Fetch current blog to keep existing images if not replaced
            $stmt = $pdo->prepare("SELECT slug, main_image, banner_image FROM blogs WHERE id = ?");
            $stmt->execute([$blog_id]);
            $current_blog = $stmt->fetch();
            $slug = $current_blog['slug'];
            
            $main_image_path = $current_blog['main_image'];
            $banner_image_path = $current_blog['banner_image'];

            // Handle Main Image Update
            if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                $upload_main = upload_image($_FILES['main_image'], 'uploads/blogs/' . $slug, 'main_' . time());
                if (is_array($upload_main) && isset($upload_main['filepath'])) {
                    if ($main_image_path && file_exists(__DIR__ . '/../' . $main_image_path)) unlink(__DIR__ . '/../' . $main_image_path);
                    $main_image_path = $upload_main['filepath'];
                }
            }

            // Handle Banner Image Update
            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                $upload_banner = upload_image($_FILES['banner_image'], 'uploads/blogs/' . $slug, 'banner_' . time());
                if (is_array($upload_banner) && isset($upload_banner['filepath'])) {
                    if ($banner_image_path && file_exists(__DIR__ . '/../' . $banner_image_path)) unlink(__DIR__ . '/../' . $banner_image_path);
                    $banner_image_path = $upload_banner['filepath'];
                }
            }

            // Update blogs
            $stmt = $pdo->prepare("UPDATE blogs SET title = ?, description = ?, main_image = ?, banner_image = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $description, $main_image_path, $banner_image_path, $status, $blog_id]);

            // Handle New Gallery Images
            if (isset($_FILES['gallery_images']) && !empty($_FILES['gallery_images']['name'][0])) {
                $gallery_files = $_FILES['gallery_images'];
                $file_count = count($gallery_files['name']);
                
                $ins_gallery = $pdo->prepare("INSERT INTO blog_images (blog_id, image_path, thumb_path, sort_order) VALUES (?, ?, ?, ?)");
                
                for ($i = 0; $i < $file_count; $i++) {
                    $single_file = [
                        'name' => $gallery_files['name'][$i],
                        'type' => $gallery_files['type'][$i],
                        'tmp_name' => $gallery_files['tmp_name'][$i],
                        'error' => $gallery_files['error'][$i],
                        'size' => $gallery_files['size'][$i]
                    ];

                    if ($single_file['error'] === UPLOAD_ERR_OK) {
                        $upload_gal = upload_image($single_file, 'uploads/blogs/' . $slug, 'gallery_' . time() . '_' . $i);
                        if (is_array($upload_gal) && isset($upload_gal['filepath'])) {
                            $ins_gallery->execute([
                                $blog_id,
                                $upload_gal['filepath'],
                                $upload_gal['thumbpath'],
                                $i
                            ]);
                        }
                    }
                }
            }

            $pdo->commit();
            $message = "Blog updated successfully.";
            $message_type = "success";
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Fetch current data for form
try {
    $stmt = $pdo->prepare("SELECT * FROM blogs WHERE id = ?");
    $stmt->execute([$blog_id]);
    $blog = $stmt->fetch();
    
    if (!$blog) {
        echo "<div class='wrap'><h2>Blog not found.</h2></div>";
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
    
    $img_stmt = $pdo->prepare("SELECT * FROM blog_images WHERE blog_id = ? ORDER BY sort_order ASC");
    $img_stmt->execute([$blog_id]);
    $gallery_images = $img_stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error fetching blog: " . $e->getMessage();
    exit;
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: '#description',
    height: 400,
    plugins: 'advlist autolink lists link image charmap preview anchor pagebreak searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking table emoticons template help',
    toolbar: 'undo redo | styles | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | print preview media | forecolor backcolor emoticons',
    menubar: 'file edit view insert format tools table help',
    content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:16px }'
  });
</script>

<div class="wrap-header" style="display: flex; justify-content: space-between; align-items: center;">
    <h1><i class="fa-solid fa-pen"></i> Edit Blog</h1>
    <a href="blogs.php" class="btn btn-secondary">Back to Blogs</a>
</div>

<?php if ($message): ?>
<div class="notice notice-<?php echo $message_type; ?>"><p><?php echo htmlspecialchars($message); ?></p></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="add-product-form">
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <div class="wp-editor-style">
            <div style="margin-bottom: 20px;">
                <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($blog['title']); ?>" required placeholder="Add title" style="width: 100%; font-size: 2em; padding: 10px 15px; border: 1px solid #ccd0d4; box-shadow: inset 0 1px 2px rgba(0,0,0,.07); background-color: #fff; color: #32373c; outline: 0; transition: .05s border-color ease-in-out;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <textarea name="description" id="description" rows="15"><?php echo htmlspecialchars($blog['description']); ?></textarea>
            </div>

            <div class="card">
                <h2>Gallery Images</h2>
                <?php if (!empty($gallery_images)): ?>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                        <?php foreach ($gallery_images as $img): ?>
                            <div style="position: relative; width: 100px; height: 100px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                                <img src="<?php echo htmlspecialchars($img['thumb_path'] ?: $img['image_path']); ?>" alt="Gallery Image" style="width: 100%; height: 100%; object-fit: cover;">
                                <a href="blog-edit.php?id=<?php echo $blog_id; ?>&delete_image=<?php echo $img['id']; ?>" onclick="return confirm('Delete this image?');" style="position: absolute; top: 2px; right: 2px; background: rgba(231,76,60,0.9); color: #fff; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 12px;"><i class="fa-solid fa-times"></i></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <p class="description">Upload more images for the blog gallery.</p>
                <div class="form-group">
                    <input type="file" name="gallery_images[]" id="gallery_images" multiple accept="image/*">
                </div>
            </div>
        </div>

        <div class="wp-sidebar-style">
            <div class="card" style="margin-top: 0;">
                <h2 style="font-size: 14px; margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Publish</h2>
                <div class="form-group" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                    <label for="status" style="margin: 0; font-weight: 600;"><i class="fa-solid fa-map-pin"></i> Status:</label>
                    <select name="status" id="status" style="width: auto; padding: 4px 8px;">
                        <option value="draft" <?php echo $blog['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $blog['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>
                <div style="background: #f6f7f7; padding: 10px -20px; border-top: 1px solid #dfdfdf; margin: 0 -20px -20px -20px; padding: 15px 20px; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Update Blog</button>
                </div>
            </div>

            <div class="card">
                <h2>Featured Images</h2>
                <div class="form-group">
                    <label for="main_image">Main/Thumbnail Image</label>
                    <?php if ($blog['main_image']): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?php echo htmlspecialchars($blog['main_image']); ?>" alt="Main Image" style="max-width: 100%; border-radius: 4px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="main_image" id="main_image" accept="image/*">
                    <p class="description">Leave blank to keep existing.</p>
                </div>
                <hr>
                <div class="form-group">
                    <label for="banner_image">Banner Image</label>
                    <?php if ($blog['banner_image']): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?php echo htmlspecialchars($blog['banner_image']); ?>" alt="Banner Image" style="max-width: 100%; border-radius: 4px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="banner_image" id="banner_image" accept="image/*">
                    <p class="description">Leave blank to keep existing.</p>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
