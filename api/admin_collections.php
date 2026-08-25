<?php
// admin/api/admin_collections.php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$startTime = microtime(true);

try {
    $action = $_GET['action'] ?? 'list';

    // 1. Toggle Featured Action
    if ($action === 'toggle_featured' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }
        $pdo->prepare("UPDATE collections SET is_featured = 1 - is_featured WHERE id = ?")->execute([$id]);
        $newVal = $pdo->query("SELECT is_featured FROM collections WHERE id = $id")->fetchColumn();
        echo json_encode([
            'success' => true, 
            'is_featured' => (int)$newVal,
            'message' => $newVal ? 'Collection marked as Featured' : 'Collection removed from Featured'
        ]);
        exit();
    }

    // 2. Toggle Status Action (published/draft)
    if ($action === 'toggle_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }
        $pdo->prepare("UPDATE collections SET status = IF(status='published','draft','published') WHERE id = ?")->execute([$id]);
        $newStatus = $pdo->query("SELECT status FROM collections WHERE id = $id")->fetchColumn();
        echo json_encode([
            'success' => true, 
            'status' => $newStatus,
            'message' => 'Status updated to ' . ucfirst($newStatus)
        ]);
        exit();
    }

    // 3. Delete Collection
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }
        $stmt = $pdo->prepare("SELECT title FROM collections WHERE id = ?");
        $stmt->execute([$id]);
        $coll = $stmt->fetch();
        if ($coll) {
            $pdo->prepare("DELETE FROM collections WHERE id = ?")->execute([$id]);
            log_activity($pdo, 'delete_collection', 'collection', $id, "Deleted collection: {$coll['title']}");
            echo json_encode(['success' => true, 'message' => 'Collection deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Collection not found']);
        }
        exit();
    }

    // 4. List Collections with Server-Side Pagination, Search & Category Filters
    $search = trim($_GET['s'] ?? '');
    $catFilter = trim($_GET['cat'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 18)));
    $offset = ($page - 1) * $perPage;

    $where = ["1=1"];
    $params = [];

    if ($search !== '') {
        $where[] = "(c.title LIKE ? OR c.subtitle LIKE ? OR c.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($catFilter !== '' && $catFilter !== 'All') {
        $where[] = "c.category = ?";
        $params[] = $catFilter;
    }

    if ($statusFilter !== '' && $statusFilter !== 'all') {
        $where[] = "c.status = ?";
        $params[] = $statusFilter;
    }

    $whereSql = implode(' AND ', $where);

    // Count Total Matching Records
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM collections c WHERE $whereSql");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);

    // Fetch Paginated Collections with total images count and subquery for first 4 thumbs
    $sql = "SELECT c.*, 
            COUNT(ci.id) as total_images
            FROM collections c
            LEFT JOIN collection_images ci ON c.id = ci.collection_id
            WHERE $whereSql
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.id DESC
            LIMIT $perPage OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Efficiently batch fetch preview images for the current page only
    if (!empty($collections)) {
        $collIds = array_column($collections, 'id');
        $inPlaceholders = implode(',', array_fill(0, count($collIds), '?'));
        
        $prevSql = "SELECT collection_id, image_path 
                    FROM collection_images 
                    WHERE collection_id IN ($inPlaceholders) 
                    ORDER BY sort_order ASC, id ASC";
        $prevStmt = $pdo->prepare($prevSql);
        $prevStmt->execute($collIds);
        $allThumbs = $prevStmt->fetchAll(PDO::FETCH_ASSOC);

        $thumbsMap = [];
        foreach ($allThumbs as $t) {
            $cid = $t['collection_id'];
            if (!isset($thumbsMap[$cid])) $thumbsMap[$cid] = [];
            if (count($thumbsMap[$cid]) < 4) {
                $thumbsMap[$cid][] = get_collection_image_url($t['image_path']);
            }
        }

        foreach ($collections as &$c) {
            $c['cover_url'] = get_collection_image_url($c['cover_image']);
            $c['preview_images'] = $thumbsMap[$c['id']] ?? [];
            $c['edit_url'] = "collection-edit.php?id=" . $c['id'];
        }
        unset($c);
    }

    // Category Counts Breakdown
    $catCounts = $pdo->query("SELECT category, COUNT(*) as count FROM collections WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY category ASC")->fetchAll(PDO::FETCH_ASSOC);
    $totalAllCollections = $pdo->query("SELECT COUNT(*) FROM collections")->fetchColumn();
    $totalPhotosInDb = $pdo->query("SELECT COUNT(*) FROM collection_images")->fetchColumn();

    $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

    echo json_encode([
        'success' => true,
        'data' => $collections,
        'pagination' => [
            'total' => $totalRecords,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ],
        'categories' => $catCounts,
        'stats' => [
            'total_collections' => (int)$totalAllCollections,
            'total_photos' => (int)$totalPhotosInDb,
            'query_time_ms' => $executionTimeMs
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'API error: ' . $e->getMessage(),
        'error_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
    ]);
}
