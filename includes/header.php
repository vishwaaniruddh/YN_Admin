<?php
// admin/includes/header.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " &lsaquo; " : ""; ?>YosshitaNeha Fashion Studio Admin</title>
    
    <!-- FontAwesome for Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Admin Panel CSS -->
    <link rel="stylesheet" href="assets/css/admin-style.css">
</head>
<body>
    <div id="wpwrap">
        
        <!-- WordPress Top Admin Bar -->
        <header id="wpadminbar">
            <button id="mobile-menu-toggle" class="mobile-menu-toggle">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="adminbar-brand">
                <a href="index.php" style="color: #fff; display: flex; align-items: center;">
                    <i class="fa-solid fa-gem" style="color: #ffb900; margin-right: 8px;"></i>
                    <strong>YosshitaNeha Fashion Studio</strong>
                </a>
            </div>
            
            <div class="adminbar-user">
                <span style="color: #c3c4c7;">
                    Howdy, <strong><?php echo sanitize_html($_SESSION['admin_name']); ?></strong>
                </span>
                <a href="logout.php">
                    <i class="fa-solid fa-right-from-bracket"></i> Log Out
                </a>
            </div>
        </header>

        <div class="admin-main-container">
