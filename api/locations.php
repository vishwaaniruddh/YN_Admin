<?php
// admin/api/locations.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once '../config/db.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'states') {
        $stmt = $pdo->query("SELECT id, name FROM states WHERE status = 'active' ORDER BY name ASC");
        $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'states' => $states]);
        exit;
    }
    
    elseif ($action === 'cities') {
        $stateId = (int)($_GET['state_id'] ?? 0);
        $stateName = trim($_GET['state_name'] ?? '');

        if ($stateId <= 0 && !empty($stateName)) {
            // Find state_id by name
            $stStmt = $pdo->prepare("SELECT id FROM states WHERE name LIKE ?");
            $stStmt->execute(["%$stateName%"]);
            $stRow = $stStmt->fetch();
            if ($stRow) {
                $stateId = (int)$stRow['id'];
            }
        }

        if ($stateId > 0) {
            $stmt = $pdo->prepare("SELECT id, name FROM cities WHERE state_id = ? AND status = 'active' ORDER BY name ASC");
            $stmt->execute([$stateId]);
            $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Return popular/all cities if no state filter
            $stmt = $pdo->query("SELECT id, name FROM cities WHERE status = 'active' ORDER BY name ASC LIMIT 100");
            $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['success' => true, 'cities' => $cities]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
