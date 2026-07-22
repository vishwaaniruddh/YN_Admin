<?php
// admin/blog-add.php
$page_title = "Add New Blog";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    
    // Generate slug from title
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    
    if (empty($title)) {
        $message = "Title is required.";
        $message_type = "error";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check for duplicate slug
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM blogs WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetchColumn() > 0) {
                $slug .= '-' . time();
            }

            // Handle Main Image Upload
            $main_image_path = null;
            if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                $upload_main = upload_image($_FILES['main_image'], 'uploads/blogs/' . $slug, 'main');
                if (is_array($upload_main) && isset($upload_main['filepath'])) {
                    $main_image_path = $upload_main['filepath'];
                }
            }

            // Handle Banner Image Upload
            $banner_image_path = null;
            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                $upload_banner = upload_image($_FILES['banner_image'], 'uploads/blogs/' . $slug, 'banner');
                if (is_array($upload_banner) && isset($upload_banner['filepath'])) {
                    $banner_image_path = $upload_banner['filepath'];
                }
            }

            // Insert into blogs
            $stmt = $pdo->prepare("INSERT INTO blogs (title, slug, description, main_image, banner_image, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $description, $main_image_path, $banner_image_path, $status]);
            $blog_id = $pdo->lastInsertId();

            // Handle Gallery Images Upload
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
                        $upload_gal = upload_image($single_file, 'uploads/blogs/' . $slug, 'gallery_' . $i);
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
            
            // Output JavaScript redirect to avoid headers already sent issues if they happen, 
            // though proper structure should use headers. We'll use redirect helper if available.
            echo "<script>window.location.href='blogs.php?message=added';</script>";
            exit;
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}
?>

<!-- Include TinyMCE -->
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
    <h1><i class="fa-solid fa-plus"></i> Add New Blog</h1>
    <a href="blogs.php" class="btn btn-secondary">Back to Blogs</a>
</div>

<?php if ($message): ?>
<div class="notice notice-<?php echo $message_type; ?>"><p><?php echo htmlspecialchars($message); ?></p></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="add-product-form">
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <!-- Left Column -->
        <div class="wp-editor-style">
            <div style="margin-bottom: 20px;">
                <input type="text" name="title" id="title" required placeholder="Add title" style="width: 100%; font-size: 2em; padding: 10px 15px; border: 1px solid #ccd0d4; box-shadow: inset 0 1px 2px rgba(0,0,0,.07); background-color: #fff; color: #32373c; outline: 0; transition: .05s border-color ease-in-out;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <textarea name="description" id="description" rows="15"></textarea>
            </div>

            <div class="card">
                <h2>Gallery Images</h2>
                <p class="description">Upload multiple images for the blog gallery. (Optional)</p>
                <div class="form-group">
                    <input type="file" name="gallery_images[]" id="gallery_images" multiple accept="image/*">
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="wp-sidebar-style">
            <div class="card" style="margin-top: 0;">
                <h2 style="font-size: 14px; margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Publish</h2>
                <div class="form-group" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                    <label for="status" style="margin: 0; font-weight: 600;"><i class="fa-solid fa-map-pin"></i> Status:</label>
                    <select name="status" id="status" style="width: auto; padding: 4px 8px;">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
                <div style="background: #f6f7f7; padding: 10px -20px; border-top: 1px solid #dfdfdf; margin: 0 -20px -20px -20px; padding: 15px 20px; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Blog</button>
                </div>
            </div>

            <div class="card">
                <h2>Featured Images</h2>
                <div class="form-group">
                    <label for="main_image">Main/Thumbnail Image</label>
                    <input type="file" name="main_image" id="main_image" accept="image/*">
                    <p class="description">Used in blog lists and cards.</p>
                </div>
                <hr>
                <div class="form-group">
                    <label for="banner_image">Banner Image</label>
                    <input type="file" name="banner_image" id="banner_image" accept="image/*">
                    <p class="description">Used as the header image on the blog post page.</p>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
