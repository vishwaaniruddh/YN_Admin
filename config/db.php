<?php
// admin/config/db.php

$httpHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

// Environment Auto-Detection (Production vs Local)
$isProduction = (
    str_contains($httpHost, 'yosshitaneha.com') || 
    str_contains($docRoot, 'u464193275') ||
    (!str_contains($httpHost, 'localhost') && !str_contains($httpHost, '127.0.0.1') && !empty($httpHost))
);

if ($isProduction) {
    // Live Server Credentials
    $host = 'localhost';
    $user = 'u464193275_yosshitanehafs';
    $pass = 'AVav@@2026';
    $dbname = 'u464193275_yosshitanehafs';
} else {
    // Local Development Credentials
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'yosshitaneha_db';
}

// Allow local override if db_local.php exists
if (file_exists(__DIR__ . '/db_local.php')) {
    include __DIR__ . '/db_local.php';
}

// Set PHP Default Timezone to India Standard Time (GMT+5:30)
date_default_timezone_set('Asia/Kolkata');

try {
    // Attempt direct database connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Set MySQL Connection Timezone to India (+05:30)
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    // Local fallback: create database if missing on local development
    if (!$isProduction) {
        try {
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");
        } catch (PDOException $e2) {
            die("Database Connection failed: " . $e2->getMessage());
        }
    } else {
        die("Database Connection failed: " . $e->getMessage());
    }
}

try {

    // 4. Create Tables
    
    // Admins Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        full_name VARCHAR(100) DEFAULT NULL,
        role VARCHAR(50) DEFAULT 'editor',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Categories Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        parent_id INT DEFAULT NULL,
        view_count INT DEFAULT 0,
        image_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    // Products Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT DEFAULT NULL,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        sku VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        short_description TEXT,
        price DECIMAL(10,2) NOT NULL,
        sale_price DECIMAL(10,2) DEFAULT NULL,
        stock_qty INT NOT NULL DEFAULT 0,
        is_featured TINYINT(1) DEFAULT 0,
        status ENUM('draft', 'published') DEFAULT 'draft',
        main_image VARCHAR(255) DEFAULT NULL,
        view_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    // Product Gallery Images Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        thumb_path VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Activity Logs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        user_type ENUM('admin', 'customer', 'guest') DEFAULT 'guest',
        action VARCHAR(255) NOT NULL,
        entity_type VARCHAR(100) NULL,
        entity_id INT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Search Logs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS search_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        search_term VARCHAR(255) NOT NULL,
        results_count INT DEFAULT 0,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Cart Items Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS cart_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_token VARCHAR(255) NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY unique_cart_item (session_token, product_id)
    ) ENGINE=InnoDB");

    // Wishlist Items Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_token VARCHAR(255) NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY unique_wishlist_item (session_token, product_id)
    ) ENGINE=InnoDB");

    // Site Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Newsletters Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS newsletters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        status ENUM('subscribed', 'unsubscribed') DEFAULT 'subscribed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Blogs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS blogs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        main_image VARCHAR(255) DEFAULT NULL,
        banner_image VARCHAR(255) DEFAULT NULL,
        status ENUM('draft', 'published') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Blog Gallery Images Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blog_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        thumb_path VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Seed default settings
    $pdo->exec("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('theme_mode', 'dark')");

    // 5. Seed default admin if none exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() == 0) {
        $username = 'admin';
        $email = 'admin@yosshitaneha.com';
        $fullName = 'Yosshita Neha Admin';
        // Password hash for admin123
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        
        $insert = $pdo->prepare("INSERT INTO admins (username, password_hash, email, full_name) VALUES (?, ?, ?, ?)");
        $insert->execute([$username, $passwordHash, $email, $fullName]);
    }

} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>
