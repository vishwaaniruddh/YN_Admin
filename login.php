<?php
// admin/login.php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    redirect('index.php');
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_email = trim($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_email) || empty($password)) {
        $error_message = 'Please enter both username/email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username_email, $username_email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Set session
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'] ?: $admin['username'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_logged_in'] = true;

                // Optional: Store a JWT token in cookie or session for future React frontend API calls
                $jwt = generate_jwt($admin['id'], $admin['username'], $admin['role']);
                $_SESSION['admin_jwt'] = $jwt;
                setcookie('yn_admin_token', $jwt, time() + (3600 * 24), "/", "", false, true);

                redirect('index.php');
            } else {
                $error_message = 'Invalid username/email or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>Log In &lsaquo; YosshitaNeha Fashion Studio</title>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- WP Admin CSS -->
    <link rel="stylesheet" href="assets/css/admin-style.css">
</head>
<body class="login-body">

    <div class="login-wrapper">
        <div class="login-logo">
            <h1><i class="fa-solid fa-gem" style="color: #ffb900;"></i> YosshitaNeha</h1>
            <p>Fashion Studio Admin</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="notice notice-error" style="margin-bottom: 15px;">
                <p><?php echo sanitize_html($error_message); ?></p>
            </div>
        <?php endif; ?>

        <div class="login-form-card">
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="user_login">Username or Email Address</label>
                    <input type="text" name="username_email" id="user_login" class="form-control" required autofocus autocomplete="username">
                </div>
                
                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="user_pass">Password</label>
                    <input type="password" name="password" id="user_pass" class="form-control" required autocomplete="current-password">
                </div>

                <div class="form-submit">
                    <button type="submit" class="button button-primary" style="width: 100%; padding: 8px 12px; font-size: 14px;">
                        Log In
                    </button>
                </div>
            </form>
        </div>
        
        <p style="text-align: center; margin-top: 20px; font-size: 12px; color: #646970;">
            <a href="../index.html" style="color: #646970;"><i class="fa-solid fa-arrow-left"></i> Back to YosshitaNeha Studio</a>
        </p>
    </div>

</body>
</html>
