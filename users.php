<?php
// admin/users.php
$page_title = "Users";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Access Control
if (!current_user_can('manage_users')) {
    redirect('index.php?error=unauthorized');
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$message = '';
$message_type = 'success';

// Handle Delete Request
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Prevent self-deletion
    if ($delete_id === $_SESSION['admin_id']) {
        $message = "You cannot delete your own account.";
        $message_type = "error";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
            $stmt->execute([$delete_id]);
            log_activity($pdo, 'delete_user', 'admin', $delete_id, "Deleted user ID $delete_id");
            $message = "User successfully deleted.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error deleting user: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

if (isset($_GET['message']) && $_GET['message'] === 'added') {
    $message = "User successfully added.";
    $message_type = "success";
} elseif (isset($_GET['message']) && $_GET['message'] === 'updated') {
    $message = "User successfully updated.";
    $message_type = "success";
}

// Fetch users
try {
    $search = isset($_GET['s']) ? trim($_GET['s']) : '';
    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ? ORDER BY created_at DESC");
        $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT * FROM admins ORDER BY created_at DESC");
    }
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = "error";
}
?>

<div class="wrap-header">
    <h1>Users</h1>
    <a href="user-add.php" class="button button-primary"><i class="fa-solid fa-user-plus"></i> Add New</a>
    
    <!-- Search Box -->
    <form action="users.php" method="GET" style="display: flex; gap: 8px; float: right;">
        <input type="text" name="s" value="<?php echo sanitize_html($search ?? ''); ?>" placeholder="Search users..." class="form-control" style="width: 200px; padding: 4px 8px;">
        <button type="submit" class="button">Search</button>
        <?php if (!empty($search)): ?>
            <a href="users.php" class="button" title="Clear Search"><i class="fa-solid fa-xmark"></i></a>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<table class="wp-list-table">
    <thead>
        <tr>
            <th>Username</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Date Registered</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($users)): ?>
            <tr>
                <td colspan="5" style="text-align: center; padding: 20px;">No users found.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <strong><a href="user-edit.php?id=<?php echo $user['id']; ?>"><?php echo sanitize_html($user['username']); ?></a></strong>
                        <div class="column-actions">
                            <a href="user-edit.php?id=<?php echo $user['id']; ?>">Edit</a>
                            <?php if ($user['id'] !== $_SESSION['admin_id']): ?>
                                | <a href="users.php?delete=<?php echo $user['id']; ?>" class="delete delete-confirm" data-name="<?php echo sanitize_html($user['username']); ?>">Delete</a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo sanitize_html($user['full_name'] ?: '—'); ?></td>
                    <td><a href="mailto:<?php echo sanitize_html($user['email']); ?>"><?php echo sanitize_html($user['email']); ?></a></td>
                    <td>
                        <?php 
                        $role_display = [
                            'administrator' => 'Administrator',
                            'shop_manager' => 'Shop Manager',
                            'editor' => 'Editor'
                        ];
                        echo $role_display[$user['role']] ?? sanitize_html($user['role']); 
                        ?>
                    </td>
                    <td style="color: #646970; font-size: 13px;">
                        <?php echo date('Y/m/d H:i', strtotime($user['created_at'])); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
