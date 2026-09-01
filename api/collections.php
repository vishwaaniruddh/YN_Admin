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
        $img_stmt = $pdo->prepare("SELECT id, image_path, thumb_path, caption, angle_type, media_type, is_cover, sort_order FROM collection_images WHERE collection_id = ? ORDER BY sort_order ASC, id ASC");
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
        // Fetch all individual shoot photos across published collections with angle tags & outfit info
        $catFilter = trim($_GET['category'] ?? '');
        $sql = "SELECT ci.id, ci.image_path, ci.caption, ci.angle_type, ci.media_type, ci.is_cover, ci.collection_id, 
                       c.title as collection_title, c.slug as collection_slug, c.category, c.fabric, c.work_type, c.color, c.sku
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

    if (!empty($_GET['category']) && $_GET['category'] !== 'All') {
        $where[] = "category = ?";
        $params[] = $_GET['category'];
    }

    $where_sql = implode(' AND ', $where);
    $limit_sql = "";
    if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
        $limit_sql = " LIMIT " . (int)$_GET['limit'];
    }

    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM collection_images ci WHERE ci.collection_id = c.id) as total_images
            FROM collections c 
            WHERE $where_sql 
            ORDER BY c.sort_order ASC, c.id DESC $limit_sql";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Batch fetch all preview photos for each outfit
    if (!empty($collections)) {
        $collIds = array_column($collections, 'id');
        $inPlaceholders = implode(',', array_fill(0, count($collIds), '?'));
        
        $prevSql = "SELECT id, collection_id, image_path, caption, angle_type, is_cover, media_type 
                    FROM collection_images 
                    WHERE collection_id IN ($inPlaceholders) 
                    ORDER BY sort_order ASC, id ASC";
        $prevStmt = $pdo->prepare($prevSql);
        $prevStmt->execute($collIds);
        $allMedia = $prevStmt->fetchAll(PDO::FETCH_ASSOC);

        $mediaMap = [];
        foreach ($allMedia as $m) {
            $cid = $m['collection_id'];
            if (!isset($mediaMap[$cid])) $mediaMap[$cid] = [];
            $mediaMap[$cid][] = $m;
        }

        foreach ($collections as &$c) {
            $c['gallery'] = $mediaMap[$c['id']] ?? [];
            $c['preview_images'] = array_column(array_slice($c['gallery'], 0, 4), 'image_path');
        }
        unset($c);
    }

    // Fetch active categories with counts
    $catCounts = $pdo->query("SELECT category, COUNT(*) as count FROM collections WHERE status = 'published' AND category IS NOT NULL AND category != '' GROUP BY category ORDER BY category ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $collections,
        'categories' => array_column($catCounts, 'category'),
        'category_counts' => $catCounts,
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
