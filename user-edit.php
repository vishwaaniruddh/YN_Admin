<?php
// admin/user-edit.php
$page_title = "Edit User";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Access Control
if (!current_user_can('manage_users') && $_SESSION['admin_id'] !== $edit_id) {
    redirect('index.php?error=unauthorized');
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = current_user_can('manage_users') ? ($_POST['role'] ?? 'editor') : null;
    $password = $_POST['password'] ?? '';

    if (empty($email)) {
        $message = "Email is required.";
        $message_type = "error";
    } else {
        // Check uniqueness of email for other users
        $check = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ? AND id != ?");
        $check->execute([$email, $edit_id]);
        if ($check->fetchColumn() > 0) {
            $message = "Email already exists for another user.";
            $message_type = "error";
        } else {
            try {
                $query = "UPDATE admins SET email = ?, full_name = ?";
                $params = [$email, $full_name];

                if ($role) {
                    // Prevent changing own role unless it's administrator keeping administrator? Actually, it's safer to just allow administrators to change roles.
                    if ($edit_id === $_SESSION['admin_id'] && $_SESSION['admin_role'] === 'administrator' && $role !== 'administrator') {
                        $message = "You cannot demote yourself.";
                        $message_type = "error";
                        $role = 'administrator'; // Fallback
                    }
                    $query .= ", role = ?";
                    $params[] = $role;
                }

                if (!empty($password)) {
                    $query .= ", password_hash = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }

                $query .= " WHERE id = ?";
                $params[] = $edit_id;

                if (empty($message_type) || $message_type === 'success') {
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    
                    log_activity($pdo, 'edit_user', 'admin', $edit_id, "Updated user details for ID $edit_id");
                    
                    if (current_user_can('manage_users')) {
                        redirect('users.php?message=updated');
                    } else {
                        $message = "Profile successfully updated.";
                        $message_type = "success";
                    }
                }
            } catch (PDOException $e) {
                $message = "Database Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$edit_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap-header">
    <h1>Edit User</h1>
    <?php if (current_user_can('manage_users')): ?>
        <a href="users.php" class="button">Back to Users</a>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<div class="postbox" style="max-width: 600px;">
    <div class="postbox-body">
        <form action="user-edit.php?id=<?php echo $edit_id; ?>" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" class="form-control" value="<?php echo sanitize_html($user['username']); ?>" disabled>
                <p style="font-size: 11px; color: #646970; margin-top: 4px;">Usernames cannot be changed.</p>
            </div>
            
            <div class="form-group">
                <label for="email">Email (required)</label>
                <input type="email" name="email" id="email" class="form-control" value="<?php echo sanitize_html($user['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo sanitize_html($user['full_name']); ?>">
            </div>

            <?php if (current_user_can('manage_users')): ?>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select name="role" id="role" class="form-control">
                        <option value="administrator" <?php echo $user['role'] === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                        <option value="shop_manager" <?php echo $user['role'] === 'shop_manager' ? 'selected' : ''; ?>>Shop Manager</option>
                        <option value="editor" <?php echo $user['role'] === 'editor' ? 'selected' : ''; ?>>Editor</option>
                    </select>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label>Role</label>
                    <input type="text" class="form-control" value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>" disabled>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" name="password" id="password" class="form-control">
                <p style="font-size: 11px; color: #646970; margin-top: 4px;">If you would like to change the password type a new one. Otherwise leave this blank.</p>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="button button-primary">Update Profile</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
