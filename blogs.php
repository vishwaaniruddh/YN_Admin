<?php
// admin/blogs.php
$page_title = "Blogs";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    try {
        // Fetch images to delete from filesystem
        $stmt = $pdo->prepare("SELECT main_image, banner_image FROM blogs WHERE id = ?");
        $stmt->execute([$del_id]);
        $blog = $stmt->fetch();
        
        if ($blog) {
            if ($blog['main_image'] && file_exists(__DIR__ . '/../' . $blog['main_image'])) unlink(__DIR__ . '/../' . $blog['main_image']);
            if ($blog['banner_image'] && file_exists(__DIR__ . '/../' . $blog['banner_image'])) unlink(__DIR__ . '/../' . $blog['banner_image']);
            
            $img_stmt = $pdo->prepare("SELECT image_path, thumb_path FROM blog_images WHERE blog_id = ?");
            $img_stmt->execute([$del_id]);
            $images = $img_stmt->fetchAll();
            foreach ($images as $img) {
                if (file_exists(__DIR__ . '/../' . $img['image_path'])) unlink(__DIR__ . '/../' . $img['image_path']);
                if (file_exists(__DIR__ . '/../' . $img['thumb_path'])) unlink(__DIR__ . '/../' . $img['thumb_path']);
            }
            
            $pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$del_id]);
            $message = "Blog deleted successfully.";
            $message_type = "success";
        }
    } catch (PDOException $e) {
        $message = "Error deleting blog: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch all blogs
$blogs = [];
try {
    $stmt = $pdo->query("SELECT * FROM blogs ORDER BY created_at DESC");
    $blogs = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = "error";
}
?>

<div class="wrap-header" style="display: flex; justify-content: space-between; align-items: center;">
    <h1><i class="fa-solid fa-newspaper"></i> Blogs</h1>
    <a href="blog-add.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add New Blog</a>
</div>

<?php if (isset($message)): ?>
<div class="notice notice-<?php echo $message_type; ?>"><p><?php echo htmlspecialchars($message); ?></p></div>
<?php endif; ?>

<div class="card" style="padding: 0;">
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" id="image" class="manage-column" style="width: 80px;">Image</th>
                <th scope="col" id="title" class="manage-column">Title</th>
                <th scope="col" id="status" class="manage-column" style="width: 120px;">Status</th>
                <th scope="col" id="date" class="manage-column" style="width: 180px;">Date</th>
                <th scope="col" id="actions" class="manage-column" style="width: 150px; text-align: center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($blogs)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 30px;">No blogs found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($blogs as $blog): ?>
                <tr>
                    <td>
                        <?php if ($blog['main_image']): ?>
                            <img src="<?php echo htmlspecialchars($blog['main_image']); ?>" alt="Img" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; background: #333; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #666;"><i class="fa-solid fa-image"></i></div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo sanitize_html($blog['title']); ?></strong></td>
                    <td>
                        <?php if ($blog['status'] === 'published'): ?>
                            <span class="badge" style="background: rgba(46, 204, 113, 0.15); color: #2ecc71; padding: 4px 10px; border-radius: 12px; font-size: 11px;">Published</span>
                        <?php else: ?>
                            <span class="badge" style="background: rgba(241, 196, 15, 0.15); color: #f1c40f; padding: 4px 10px; border-radius: 12px; font-size: 11px;">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($blog['created_at'])); ?></td>
                    <td style="text-align: center;">
                        <a href="blog-edit.php?id=<?php echo $blog['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px;"><i class="fa-solid fa-pen"></i></a>
                        <a href="blogs.php?delete=<?php echo $blog['id']; ?>" class="btn btn-danger btn-sm" style="padding: 4px 8px;" onclick="return confirm('Are you sure you want to delete this blog?');"><i class="fa-solid fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
