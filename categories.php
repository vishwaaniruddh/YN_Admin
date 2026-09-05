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

<div class="dashboard-header-banner" style="margin-bottom: 20px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting">
            <h1>Categories</h1>
            <span class="shadcn-badge shadcn-badge-amber" style="font-size: 11px; padding: 3px 8px;">
                <i class="fa-solid fa-folder-tree" style="margin-right: 4px;"></i> <?php echo count($raw_categories ?? []); ?> Categories
            </span>
        </div>
        <p class="dashboard-subtitle">
            Organize catalog hierarchy, manage parent/child categories, and assign showcase images.
        </p>
    </div>
    
    <!-- Search Box -->
    <form action="categories.php" method="GET" style="display: flex; gap: 8px; align-items: center;">
        <div style="position: relative;">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #a1a1aa; font-size: 11.5px; pointer-events: none;"></i>
            <input type="text" name="s" value="<?php echo sanitize_html($search ?? ''); ?>" placeholder="Search categories..." class="form-control" style="width: 220px; padding-left: 32px; height: 32px; font-size: 12.5px;">
        </div>
        <button type="submit" class="shadcn-btn shadcn-btn-primary" style="height: 32px; font-size: 12.5px;"><i class="fa-solid fa-filter"></i> Filter</button>
        <?php if (!empty($search)): ?>
            <a href="categories.php" class="shadcn-btn shadcn-btn-ghost" title="Clear Search" style="height: 32px; padding: 0 10px;"><i class="fa-solid fa-xmark"></i></a>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <i class="fa-solid fa-circle-info"></i>
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<div class="wp-editor-columns">
    
    <!-- Left Column: Add / Edit Form -->
    <div class="side-column" style="flex: 0 0 340px; width: 340px; max-width: 340px;">
        <div class="shadcn-card">
            <div class="shadcn-card-header">
                <div class="shadcn-card-title">
                    <?php echo $edit_mode ? '<i class="fa-solid fa-pen-to-square" style="color: #3b82f6;"></i> Edit Category' : '<i class="fa-solid fa-folder-plus" style="color: #f59e0b;"></i> Add New Category'; ?>
                </div>
            </div>
            
            <div class="shadcn-card-padded">
                <form action="categories.php<?php echo $edit_mode ? '?edit=' . $edit_category['id'] : ''; ?>" method="POST" enctype="multipart/form-data">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="action" value="edit">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="cat_name">Category Name</label>
                        <input type="text" name="name" id="cat_name" class="form-control" placeholder="e.g. Bridal Necklaces" value="<?php echo $edit_mode ? sanitize_html($edit_category['name']) : ''; ?>" required>
                        <p style="font-size: 11.5px; color: #71717a; margin-top: 4px;">Displayed title across website navigation.</p>
                    </div>

                    <div class="form-group">
                        <label for="cat_slug">URL Slug</label>
                        <input type="text" name="slug" id="cat_slug" class="form-control" placeholder="e.g. bridal-necklaces" value="<?php echo $edit_mode ? sanitize_html($edit_category['slug']) : ''; ?>">
                        <p style="font-size: 11.5px; color: #71717a; margin-top: 4px;">URL-friendly identifier. Auto-generated if blank.</p>
                    </div>

                    <div class="form-group">
                        <label for="cat_parent">Parent Category</label>
                        <select name="parent_id" id="cat_parent" class="form-control">
                            <option value="">None (Top Level Category)</option>
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
                        <p style="font-size: 11.5px; color: #71717a; margin-top: 4px;">Nest under a parent to create collections.</p>
                    </div>

                    <div class="form-group">
                        <label for="cat_desc">Description</label>
                        <textarea name="description" id="cat_desc" class="form-control" rows="3" placeholder="Category highlights..."><?php echo $edit_mode ? sanitize_html($edit_category['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="cat_image">Showcase Image</label>
                        <?php if ($edit_mode && !empty($edit_category['image_path'])): ?>
                            <div style="margin-bottom: 8px;">
                                <img id="cat_image_preview" src="<?php echo sanitize_html($edit_category['image_path']); ?>" alt="Current" style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #e4e4e7; display: block;" onerror="this.style.display='none';">
                            </div>
                        <?php else: ?>
                            <img id="cat_image_preview" src="" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #e4e4e7; display: none; margin-bottom: 8px;">
                        <?php endif; ?>
                        <input type="file" name="image" id="cat_image" class="form-control" accept="image/*">
                    </div>

                    <div style="margin-top: 22px; display: flex; gap: 8px;">
                        <button type="submit" class="shadcn-btn shadcn-btn-primary" style="flex: 1;">
                            <i class="fa-solid <?php echo $edit_mode ? 'fa-check' : 'fa-plus'; ?>"></i>
                            <?php echo $edit_mode ? 'Update Category' : 'Create Category'; ?>
                        </button>
                        <?php if ($edit_mode): ?>
                            <a href="categories.php" class="shadcn-btn shadcn-btn-outline">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Categories List -->
    <div class="main-column">
        <div class="shadcn-card">
            <div class="shadcn-card-header">
                <div class="shadcn-card-title">
                    <i class="fa-solid fa-list" style="color: #71717a;"></i> Category Hierarchy
                </div>
                <div style="font-size: 12px; color: #71717a;">
                    Click any category to edit properties
                </div>
            </div>
            
            <div class="shadcn-card-body">
                <table class="shadcn-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Image</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Parent Group</th>
                            <th style="width: 90px; text-align: center;">Products</th>
                            <th style="width: 80px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($display_categories)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #71717a; padding: 36px;">
                                    <i class="fa-solid fa-folder-open" style="font-size: 26px; display: block; margin-bottom: 8px; opacity: 0.4;"></i>
                                    No categories found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($display_categories as $cat): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($cat['image_path'])): ?>
                                            <img src="<?php echo sanitize_html($cat['image_path']); ?>" alt="" style="width: 36px; height: 36px; object-fit: cover; border-radius: 6px; border: 1px solid #e4e4e7; display: block;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div style="display: none; width: 36px; height: 36px; background: #f4f4f5; border-radius: 6px; border: 1px solid #e4e4e7; align-items: center; justify-content: center; color: #a1a1aa;">
                                                <i class="fa-solid fa-folder" style="font-size: 13px;"></i>
                                            </div>
                                        <?php else: ?>
                                            <div style="width: 36px; height: 36px; background: #f4f4f5; border-radius: 6px; border: 1px solid #e4e4e7; display: flex; align-items: center; justify-content: center; color: #a1a1aa;">
                                                <i class="fa-solid fa-folder" style="font-size: 13px;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="categories.php?edit=<?php echo $cat['id']; ?>" style="color: #09090b; text-decoration: none; font-size: 13px; font-weight: 500;">
                                            <?php 
                                            if (isset($cat['depth']) && $cat['depth'] > 0) {
                                                echo '<span style="color: #a1a1aa; margin-right: 4px;">' . str_repeat('— ', $cat['depth']) . '</span>';
                                            }
                                            echo sanitize_html($cat['name']); 
                                            ?>
                                        </a>
                                        <?php if (!empty($cat['description'])): ?>
                                            <div style="font-size: 12px; color: #71717a; margin-top: 2px; max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo sanitize_html($cat['description']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 11px; font-family: monospace; color: #71717a; background: #f4f4f5; padding: 2px 6px; border-radius: 4px; border: 1px solid #e4e4e7;">
                                            <?php echo sanitize_html($cat['slug']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($cat['parent_name'])): ?>
                                            <span style="font-size: 12px; color: #52525b; background: #f4f4f5; padding: 2px 7px; border-radius: 4px; border: 1px solid #e4e4e7;">
                                                <?php echo sanitize_html($cat['parent_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #a1a1aa; font-size: 12px;">Top Level</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="shadcn-badge shadcn-badge-sky">
                                            <?php echo (int)($cat['product_count'] ?? 0); ?> items
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: inline-flex; align-items: center; gap: 4px;">
                                            <a href="categories.php?edit=<?php echo $cat['id']; ?>" class="shadcn-btn-ghost" style="width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; color: #52525b;" title="Edit Category">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <?php if (current_user_can('delete_products')): ?>
                                                <a href="categories.php?delete=<?php echo $cat['id']; ?>" class="shadcn-btn-ghost delete-confirm" data-name="<?php echo sanitize_html($cat['name']); ?>" style="width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; color: #ef4444;" title="Delete Category">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
