<?php
// admin/api/offers.php — Public storefront active offer feed & view/click tracker
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/cache.php';
require_once __DIR__ . '/../includes/offers_helper.php';

// Ensure admin push-active / pause changes reflect immediately without stale browser HTTP cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

ensure_offer_popups_table($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    try {
        // Optional single preview mode by ID (even if draft/scheduled)
        $previewId = isset($_GET['preview_id']) ? (int)$_GET['preview_id'] : 0;
        if ($previewId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM offer_popups WHERE id = ? LIMIT 1");
            $stmt->execute([$previewId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                echo json_encode([
                    'success' => true,
                    'data' => [normalize_offer_row($row)],
                    'is_preview' => true
                ]);
                exit;
            }
        }

        $cacheKey = 'active_offer_popups_v1';
        $offers = get_cache($cacheKey, 180, $pdo);
        if ($offers === false) {
            $offers = get_active_offers_payload($pdo);
            set_cache($cacheKey, $offers, $pdo);
        }

        echo json_encode([
            'success' => true,
            'data' => $offers,
            'count' => count($offers)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load active offers']);
    }
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = strtolower(trim($input['action'] ?? ($_GET['action'] ?? 'track')));
    if ($action === 'track') {
        $offerId = (int)($input['id'] ?? 0);
        $event = strtolower(trim($input['event'] ?? 'view'));

        if ($offerId > 0 && in_array($event, ['view', 'click'], true)) {
            $col = ($event === 'click') ? 'click_count' : 'view_count';
            try {
                $stmt = $pdo->prepare("UPDATE offer_popups SET $col = $col + 1 WHERE id = ?");
                $stmt->execute([$offerId]);
                echo json_encode(['success' => true]);
                exit;
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false]);
                exit;
            }
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
