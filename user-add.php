<?php
// admin/user-add.php
$page_title = "Add New User";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Access Control
if (!current_user_can('manage_users')) {
    redirect('index.php?error=unauthorized');
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'editor';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $message = "Username, email, and password are required.";
        $message_type = "error";
    } else {
        // Check uniqueness
        $check = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
        if ($check->fetchColumn() > 0) {
            $message = "Username or Email already exists.";
            $message_type = "error";
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (username, email, full_name, role, password_hash) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $full_name, $role, $password_hash]);
                
                $new_id = $pdo->lastInsertId();
                log_activity($pdo, 'add_user', 'admin', $new_id, "Added new user: $username ($role)");
                
                redirect('users.php?message=added');
            } catch (PDOException $e) {
                $message = "Database Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap-header">
    <h1>Add New User</h1>
    <a href="users.php" class="button">Back to Users</a>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<div class="postbox" style="max-width: 600px;">
    <div class="postbox-body">
        <form action="user-add.php" method="POST">
            <div class="form-group">
                <label for="username">Username (required)</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email (required)</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" name="full_name" id="full_name" class="form-control">
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select name="role" id="role" class="form-control">
                    <option value="administrator">Administrator</option>
                    <option value="shop_manager">Shop Manager</option>
                    <option value="editor" selected>Editor</option>
                </select>
                <p style="font-size: 11px; color: #646970; margin-top: 4px;">Administrators have full access. Shop Managers can manage products and view logs. Editors can only manage products.</p>
            </div>
            
            <div class="form-group">
                <label for="password">Password (required)</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="button button-primary">Add New User</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
