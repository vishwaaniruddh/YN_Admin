<?php
// admin/api/collections.php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';

try {
    if (isset($_GET['slug']) || isset($_GET['id'])) {
        // Fetch single collection with full gallery
        $isSlug = isset($_GET['slug']);
        $identifier = $isSlug ? $_GET['slug'] : (int)$_GET['id'];
        
        $sql = "SELECT * FROM collections WHERE " . ($isSlug ? "slug = ?" : "id = ?");
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$identifier]);
        $collection = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$collection) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Collection not found']);
            exit();
        }

        // Fetch gallery images
        $img_stmt = $pdo->prepare("SELECT id, image_path, thumb_path, caption, outfit_type, sort_order FROM collection_images WHERE collection_id = ? ORDER BY sort_order ASC, id ASC");
        $img_stmt->execute([$collection['id']]);
        $gallery = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

        $collection['gallery'] = $gallery;
        $collection['total_images'] = count($gallery);

        echo json_encode([
            'success' => true,
            'data' => $collection
        ]);
        exit();
    }

    if (isset($_GET['all_photos']) && $_GET['all_photos'] == '1') {
        // Fetch all individual shoot photos across published collections
        $catFilter = trim($_GET['category'] ?? '');
        $sql = "SELECT ci.id, ci.image_path, ci.caption, ci.outfit_type, ci.collection_id, c.title as collection_title, c.slug as collection_slug, c.category 
                FROM collection_images ci 
                JOIN collections c ON ci.collection_id = c.id 
                WHERE c.status = 'published'";
        $params = [];
        if ($catFilter !== '' && $catFilter !== 'All') {
            $sql .= " AND c.category = ?";
            $params[] = $catFilter;
        }
        $sql .= " ORDER BY c.sort_order ASC, ci.sort_order ASC, ci.id ASC";
        
        if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
            $sql .= " LIMIT " . (int)$_GET['limit'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $categories = $pdo->query("SELECT DISTINCT category FROM collections WHERE status = 'published' AND category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'success' => true,
            'data' => $photos,
            'categories' => $categories,
            'count' => count($photos)
        ]);
        exit();
    }
    $where = ["status = 'published'"];
    $params = [];

    if (isset($_GET['featured']) && $_GET['featured'] == '1') {
        $where[] = "is_featured = 1";
    }

    if (!empty($_GET['category'])) {
        $where[] = "category = ?";
        $params[] = $_GET['category'];
    }

    $where_sql = implode(' AND ', $where);
    $limit_sql = "";
    if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
        $limit_sql = " LIMIT " . (int)$_GET['limit'];
    }

    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM collection_images ci WHERE ci.collection_id = c.id) as total_images,
            (SELECT GROUP_CONCAT(ci.image_path SEPARATOR '||') FROM (SELECT image_path, collection_id FROM collection_images ORDER BY sort_order ASC LIMIT 4) ci WHERE ci.collection_id = c.id) as preview_thumbs
            FROM collections c 
            WHERE $where_sql 
            ORDER BY c.sort_order ASC, c.id DESC $limit_sql";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($collections as &$c) {
        $c['preview_images'] = !empty($c['preview_thumbs']) ? explode('||', $c['preview_thumbs']) : [];
        unset($c['preview_thumbs']);
    }

    // Fetch active categories for tab filters
    $categories = $pdo->query("SELECT DISTINCT category FROM collections WHERE status = 'published' AND category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'data' => $collections,
        'categories' => $categories,
        'count' => count($collections)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
