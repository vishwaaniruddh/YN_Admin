<?php
// admin/api/blogs.php

// Handle preflight CORS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    exit(0);
}

// Add CORS headers for actual requests
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

try {
    if (isset($_GET['slug'])) {
        // Fetch single blog
        $slug = $_GET['slug'];
        
        $stmt = $pdo->prepare("SELECT * FROM blogs WHERE slug = ? AND status = 'published'");
        $stmt->execute([$slug]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($blog) {
            // Get gallery images
            $img_stmt = $pdo->prepare("SELECT image_path, thumb_path FROM blog_images WHERE blog_id = ? ORDER BY sort_order ASC");
            $img_stmt->execute([$blog['id']]);
            $gallery = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $blog['gallery'] = $gallery;
            
            echo json_encode(['success' => true, 'data' => $blog]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Blog not found']);
        }
    } else {
        // Fetch all published blogs
        $stmt = $pdo->query("SELECT id, title, slug, description, main_image, created_at FROM blogs WHERE status = 'published' ORDER BY created_at DESC");
        $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Truncate description for the list view and remove HTML tags
        foreach ($blogs as &$blog) {
            $clean_text = strip_tags($blog['description']);
            $blog['excerpt'] = strlen($clean_text) > 150 ? substr($clean_text, 0, 150) . '...' : $clean_text;
            unset($blog['description']); // Don't send full HTML description in list view to save bandwidth
        }
        
        echo json_encode(['success' => true, 'data' => $blogs]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
