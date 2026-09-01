<?php
// admin/api/admin_collection_photos.php
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$startTime = microtime(true);

try {
    $action = $_GET['action'] ?? 'list';
    $collection_id = (int)($_GET['collection_id'] ?? $_POST['collection_id'] ?? 0);

    if ($collection_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid collection ID']);
        exit();
    }

    // 1. Delete Photo via AJAX
    if ($action === 'delete_photo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $photo_id = (int)($_POST['photo_id'] ?? 0);
        if ($photo_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid photo ID']);
            exit();
        }
        $pdo->prepare("DELETE FROM collection_images WHERE id = ? AND collection_id = ?")->execute([$photo_id, $collection_id]);
        $remainingCount = $pdo->query("SELECT COUNT(*) FROM collection_images WHERE collection_id = $collection_id")->fetchColumn();
        echo json_encode([
            'success' => true, 
            'message' => 'Photo deleted successfully',
            'remaining_count' => (int)$remainingCount
        ]);
        exit();
    }

    // 2. Set as Cover via AJAX
    if ($action === 'set_cover' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $photo_id = (int)($_POST['photo_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT image_path FROM collection_images WHERE id = ? AND collection_id = ?");
        $stmt->execute([$photo_id, $collection_id]);
        $img = $stmt->fetch();
        if ($img) {
            $pdo->prepare("UPDATE collections SET cover_image = ? WHERE id = ?")->execute([$img['image_path'], $collection_id]);
            echo json_encode([
                'success' => true, 
                'cover_image' => $img['image_path'],
                'message' => 'Collection cover photo updated'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Photo not found']);
        }
        exit();
    }

    // 3. Update Caption via AJAX
    if ($action === 'update_caption' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $photo_id = (int)($_POST['photo_id'] ?? 0);
        $caption = trim($_POST['caption'] ?? '');
        $pdo->prepare("UPDATE collection_images SET caption = ? WHERE id = ? AND collection_id = ?")->execute([$caption, $photo_id, $collection_id]);
        echo json_encode(['success' => true, 'message' => 'Caption saved']);
        exit();
    }

    // 3b. Update Angle Type via AJAX
    if ($action === 'update_angle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $photo_id = (int)($_POST['photo_id'] ?? 0);
        $angle = trim($_POST['angle_type'] ?? 'Front View');
        $pdo->prepare("UPDATE collection_images SET angle_type = ? WHERE id = ? AND collection_id = ?")->execute([$angle, $photo_id, $collection_id]);
        echo json_encode(['success' => true, 'message' => 'Angle updated']);
        exit();
    }

    // 4. Batch Upload Photos via AJAX
    if ($action === 'upload_photos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->prepare("SELECT slug, category FROM collections WHERE id = ?");
        $stmt->execute([$collection_id]);
        $coll = $stmt->fetch();
        if (!$coll) {
            echo json_encode(['success' => false, 'message' => 'Collection not found']);
            exit();
        }

        $uploaded = [];
        if (!empty($_FILES['files']['name'][0])) {
            $files = $_FILES['files'];
            $count = count($files['name']);
            $maxOrder = (int)$pdo->query("SELECT IFNULL(MAX(sort_order), 0) FROM collection_images WHERE collection_id = $collection_id")->fetchColumn();
            $insStmt = $pdo->prepare("INSERT INTO collection_images (collection_id, image_path, caption, outfit_type, sort_order) VALUES (?, ?, ?, ?, ?)");

            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $single = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    $res = upload_image($single, 'uploads/collections/' . $coll['slug'], 'photo_' . time() . '_' . $i);
                    if (is_array($res) && isset($res['filepath'])) {
                        $cap = pathinfo($files['name'][$i], PATHINFO_FILENAME);
                        $insStmt->execute([$collection_id, $res['filepath'], $cap, $coll['category'], ++$maxOrder]);
                        $newId = $pdo->lastInsertId();
                        $uploaded[] = [
                            'id' => $newId,
                            'image_path' => $res['filepath'],
                            'image_url' => get_collection_image_url($res['filepath']),
                            'caption' => $cap
                        ];
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'uploaded_count' => count($uploaded),
            'uploaded' => $uploaded,
            'message' => count($uploaded) . ' photo(s) uploaded successfully'
        ]);
        exit();
    }

    // 5. Progressive Paginated Fetch of Collection Photos
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(6, min(60, (int)($_GET['per_page'] ?? 24)));
    $offset = ($page - 1) * $perPage;
    $search = trim($_GET['s'] ?? '');

    $where = ["collection_id = ?"];
    $params = [$collection_id];

    if ($search !== '') {
        $where[] = "(caption LIKE ? OR image_path LIKE ? OR outfit_type LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereSql = implode(' AND ', $where);

    // Total Count
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM collection_images WHERE $whereSql");
    $cntStmt->execute($params);
    $totalCount = (int)$cntStmt->fetchColumn();
    $totalPages = ceil($totalCount / $perPage);

    // Fetch batch
    $sql = "SELECT id, collection_id, image_path, thumb_path, caption, outfit_type, angle_type, media_type, is_cover, sort_order 
            FROM collection_images 
            WHERE $whereSql 
            ORDER BY sort_order ASC, id ASC 
            LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get current cover image
    $coverImage = $pdo->query("SELECT cover_image FROM collections WHERE id = $collection_id")->fetchColumn();

    foreach ($photos as &$p) {
        $p['image_url'] = get_collection_image_url($p['image_path']);
        $p['is_cover'] = ($p['image_path'] === $coverImage);
        if (empty($p['angle_type'])) {
            $p['angle_type'] = 'Angle View';
        }
    }
    unset($p);

    $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

    echo json_encode([
        'success' => true,
        'data' => $photos,
        'pagination' => [
            'total' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'has_more' => $page < $totalPages
        ],
        'cover_image' => $coverImage,
        'query_time_ms' => $executionTimeMs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'API error: ' . $e->getMessage()
    ]);
}
