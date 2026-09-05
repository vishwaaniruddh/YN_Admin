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
    
    <!-- Google Fonts Inter (ShadCN UI Standard) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Admin Stylesheet -->
    <link rel="stylesheet" href="assets/css/admin-style.css?v=2.1">
</head>
<body class="login-body">

    <div class="login-wrapper">
        <div class="login-form-card">
            <div class="login-logo">
                <div class="login-brand-icon">
                    <i class="fa-solid fa-gem"></i>
                </div>
                <h1>YosshitaNeha Studio</h1>
                <p>Enter your credentials to access the admin workspace</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="login-alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo sanitize_html($error_message); ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="user_login">Username or Email</label>
                    <div class="login-input-wrap">
                        <i class="fa-regular fa-user login-input-icon"></i>
                        <input type="text" name="username_email" id="user_login" class="form-control" placeholder="admin or email address" required autofocus autocomplete="username" value="<?php echo isset($_POST['username_email']) ? htmlspecialchars($_POST['username_email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="user_pass">
                        <span>Password</span>
                    </label>
                    <div class="login-input-wrap">
                        <i class="fa-solid fa-lock login-input-icon"></i>
                        <input type="password" name="password" id="user_pass" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" id="togglePasswordBtn" class="password-toggle-btn" aria-label="Toggle password visibility">
                            <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-btn-submit">
                    <span>Sign In</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        </div>
        
        <div class="login-footer-back">
            <a href="../index.html">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to YosshitaNeha Store</span>
            </a>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passInput = document.getElementById('user_pass');
        const passIcon = document.getElementById('togglePasswordIcon');

        if (toggleBtn && passInput && passIcon) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (passInput.type === 'password') {
                    passInput.type = 'text';
                    passIcon.classList.remove('fa-eye');
                    passIcon.classList.add('fa-eye-slash');
                } else {
                    passInput.type = 'password';
                    passIcon.classList.remove('fa-eye-slash');
                    passIcon.classList.add('fa-eye');
                }
            });
        }
    });
    </script>
</body>
</html>
