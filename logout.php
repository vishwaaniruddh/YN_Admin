<?php
// admin/logout.php
session_start();

// Unset all session values
$_SESSION = [];

// Destroy session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear JWT Cookie
setcookie('yn_admin_token', '', time() - 3600, "/");

// Destroy session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
