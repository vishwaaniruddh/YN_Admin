<?php
// admin/categories.php
$page_title = "Categories";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$message = '';
$message_type = 'success'; // success, error, warning

// 1. Handle Delete Request (Soft Delete)
if (isset($_GET['delete'])) {
    if (!current_user_can('delete_products')) {
        $message = "You do not have permission to delete categories.";
        $message_type = "error";
    } else {
        $delete_id = (int)$_GET['delete'];
        try {
        $stmt = $pdo->prepare("UPDATE categories SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$delete_id]);
        log_activity($pdo, 'delete_category', 'category', $delete_id, "Deleted category ID $delete_id");
        $message = "Category successfully soft-deleted.";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting category: " . $e->getMessage();
        $message_type = "error";
    }
  }
}

// 2. Handle Add / Edit Form Submission
$edit_mode = false;
$edit_category = null;

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_category = $stmt->fetch();
    if ($edit_category) {
        $edit_mode = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (empty($name)) {
        $message = "Category Name is required.";
        $message_type = "error";
    } else {
        if (empty($slug)) {
            $slug = generate_slug($name);
        } else {
            $slug = generate_slug($slug);
        }

        try {
            if (isset($_POST['action']) && $_POST['action'] === 'edit' && $edit_mode) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $description, $parent_id, $edit_category['id']]);
                
                // Handle Image Upload
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload = upload_image($_FILES['image'], 'uploads/category', $slug . '-' . $edit_category['id']);
                    if (is_array($upload) && isset($upload['filepath'])) {
                        $pdo->prepare("UPDATE categories SET image_path = ? WHERE id = ?")->execute([$upload['filepath'], $edit_category['id']]);
                    }
                }

                log_activity($pdo, 'update_category', 'category', $edit_category['id'], "Updated category: $name");
                $message = "Category successfully updated.";
                $message_type = "success";
                
                // Clear query and reload edited item details
                redirect('categories.php?message=updated');
            } else {
                // INSERT
                // Check if slug is unique
                $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
                $check->execute([$slug]);
                if ($check->fetchColumn() > 0) {
                    $slug .= '-' . time();
                }

                $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, parent_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $description, $parent_id]);
                $new_id = $pdo->lastInsertId();

                // Handle Image Upload
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload = upload_image($_FILES['image'], 'uploads/category', $slug . '-' . $new_id);
                    if (is_array($upload) && isset($upload['filepath'])) {
                        $pdo->prepare("UPDATE categories SET image_path = ? WHERE id = ?")->execute([$upload['filepath'], $new_id]);
                    }
                }

                log_activity($pdo, 'create_category', 'category', $new_id, "Created category: $name");
                redirect('categories.php?message=added');
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Check for redirect message
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'updated') {
        $message = "Category successfully updated.";
        $message_type = "success";
    } elseif ($_GET['message'] === 'added') {
        $message = "Category successfully added.";
        $message_type = "success";
    }
}

// 3. Fetch categories for display and drop-downs
try {
    $search = isset($_GET['s']) ? trim($_GET['s']) : '';
    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT c1.*, c2.name as parent_name, (SELECT COUNT(DISTINCT p.id) FROM products p LEFT JOIN product_categories pc ON pc.product_id = p.id WHERE (p.category_id = c1.id OR pc.category_id = c1.id) AND p.deleted_at IS NULL) as product_count FROM categories c1 LEFT JOIN categories c2 ON c1.parent_id = c2.id WHERE c1.deleted_at IS NULL AND (c1.name LIKE ? OR c1.description LIKE ?) ORDER BY c1.name ASC");
        $stmt->execute(["%$search%", "%$search%"]);
        $raw_categories = $stmt->fetchAll();
        $display_categories = $raw_categories; // search bypasses tree formatting
    } else {
        $stmt = $pdo->query("SELECT c1.*, c2.name as parent_name, (SELECT COUNT(DISTINCT p.id) FROM products p LEFT JOIN product_categories pc ON pc.product_id = p.id WHERE (p.category_id = c1.id OR pc.category_id = c1.id) AND p.deleted_at IS NULL) as product_count FROM categories c1 LEFT JOIN categories c2 ON c1.parent_id = c2.id WHERE c1.deleted_at IS NULL ORDER BY c1.name ASC");
        $raw_categories = $stmt->fetchAll();
        $display_categories = get_category_tree($raw_categories);
    }
} catch (PDOException $e) {
    $message = "Error fetching categories: " . $e->getMessage();
    $message_type = "error";
}
?>

<div class="wrap-header">
    <h1>Categories</h1>
    
    <!-- Search Box -->
    <form action="categories.php" method="GET" style="display: flex; gap: 8px;">
        <input type="text" name="s" value="<?php echo sanitize_html($search ?? ''); ?>" placeholder="Search categories..." class="form-control" style="width: 200px; padding: 4px 8px;">
        <button type="submit" class="button"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        <?php if (!empty($search)): ?>
            <a href="categories.php" class="button" title="Clear Search"><i class="fa-solid fa-xmark"></i></a>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<div class="wp-editor-columns">
    
    <!-- Left Column: Add / Edit Form -->
    <div class="side-column" style="flex: 0 0 320px;">
        <div class="postbox">
            <div class="postbox-header">
                <h2><?php echo $edit_mode ? '<i class="fa-solid fa-pen-to-square"></i> Edit Category' : '<i class="fa-solid fa-folder-plus"></i> Add New Category'; ?></h2>
            </div>
            
            <div class="postbox-body">
                <form action="categories.php<?php echo $edit_mode ? '?edit=' . $edit_category['id'] : ''; ?>" method="POST" enctype="multipart/form-data">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="action" value="edit">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="cat_name">Name</label>
                        <input type="text" name="name" id="cat_name" class="form-control" value="<?php echo $edit_mode ? sanitize_html($edit_category['name']) : ''; ?>" required>
                        <p style="font-size: 11px; color: #646970; margin-top: 4px;">The name is how it appears on your site.</p>
                    </div>

                    <div class="form-group">
                        <label for="cat_slug">Slug</label>
                        <input type="text" name="slug" id="cat_slug" class="form-control" value="<?php echo $edit_mode ? sanitize_html($edit_category['slug']) : ''; ?>">
                        <p style="font-size: 11px; color: #646970; margin-top: 4px;">The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.</p>
                    </div>

                    <div class="form-group">
                        <label for="cat_parent">Parent Category</label>
                        <select name="parent_id" id="cat_parent" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($raw_categories as $drop_cat): ?>
                                <?php 
                                // Prevent setting current category or child as parent in edit mode
                                if ($edit_mode && ($drop_cat['id'] == $edit_category['id'] || $drop_cat['parent_id'] == $edit_category['id'])) {
                                    continue;
                                }
                                $selected = ($edit_mode && $edit_category['parent_id'] == $drop_cat['id']) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $drop_cat['id']; ?>" <?php echo $selected; ?>><?php echo sanitize_html($drop_cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p style="font-size: 11px; color: #646970; margin-top: 4px;">Assign a parent category to create a hierarchy (e.g. Jewellery &rarr; Bridal Necklaces).</p>
                    </div>

                    <div class="form-group">
                        <label for="cat_desc">Description</label>
                        <textarea name="description" id="cat_desc" class="form-control" rows="4"><?php echo $edit_mode ? sanitize_html($edit_category['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="cat_image">Category Image</label>
                        <div style="margin-bottom: 10px;">
                            <?php if ($edit_mode && !empty($edit_category['image_path'])): ?>
                                <img id="cat_image_preview" src="<?php echo sanitize_html($edit_category['image_path']); ?>" alt="Current Image" style="max-width: 100px; max-height: 100px; border-radius: 4px; display: block;">
                            <?php else: ?>
                                <img id="cat_image_preview" src="" alt="Image Preview" style="max-width: 100px; max-height: 100px; border-radius: 4px; display: none;">
                            <?php endif; ?>
                        </div>
                        <input type="file" name="image" id="cat_image" class="form-control" accept="image/*">
                        <p style="font-size: 11px; color: #646970; margin-top: 4px;">Upload an image for this category.</p>
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="submit" class="button button-primary">
                            <?php echo $edit_mode ? 'Update Category' : 'Add New Category'; ?>
                        </button>
                        <?php if ($edit_mode): ?>
                            <a href="categories.php" class="button" style="margin-left: 8px;">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Categories List -->
    <div class="main-column">
        <table class="wp-list-table">
            <thead>
                <tr>
                    <th style="width: 60px;">Image</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Slug</th>
                    <th>Parent</th>
                    <th style="width: 80px; text-align: center;">Count</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($display_categories)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #646970; padding: 20px;">No categories found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($display_categories as $cat): ?>
                        <tr>
                            <td>
                                <?php if (!empty($cat['image_path'])): ?>
                                    <img src="<?php echo sanitize_html($cat['image_path']); ?>" alt="Img" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; background: #e2e8f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #94a3b8;"><i class="fa-solid fa-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>
                                    <a href="categories.php?edit=<?php echo $cat['id']; ?>">
                                        <?php 
                                        if (isset($cat['depth']) && $cat['depth'] > 0) {
                                            echo '<span class="parent-indicator">' . str_repeat('— ', $cat['depth']) . '</span>';
                                        }
                                        echo sanitize_html($cat['name']); 
                                        ?>
                                    </a>
                                </strong>
                                <div class="column-actions">
                                    <a href="categories.php?edit=<?php echo $cat['id']; ?>">Edit</a> 
                                    <?php if (current_user_can('delete_products')): ?>
                                    | <a href="categories.php?delete=<?php echo $cat['id']; ?>" class="delete delete-confirm" data-name="<?php echo sanitize_html($cat['name']); ?>">Delete</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="color: #646970; font-size: 13px;">
                                <?php echo sanitize_html($cat['description'] ?: '—'); ?>
                            </td>
                            <td><?php echo sanitize_html($cat['slug']); ?></td>
                            <td><?php echo sanitize_html($cat['parent_name'] ?: '—'); ?></td>
                            <td style="text-align: center; font-weight: bold; color: #2271b1;">
                                <?php echo (int)($cat['product_count'] ?? 0); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
