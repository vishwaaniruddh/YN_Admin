<?php
// admin/masters.php
$page_title = "System Masters";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$tab = strtolower($_GET['tab'] ?? 'city');
if (!in_array($tab, ['city', 'state', 'logistics'])) {
    $tab = 'city';
}

$message = '';
$message_type = 'success';

if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // --- CITY CRUD ---
    if ($tab === 'city') {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $state_id = (int)($_POST['state_id'] ?? 0);
            $status = $_POST['status'] ?? 'active';

            if (empty($name) || $state_id <= 0) {
                $_SESSION['flash_message'] = "City Name and State are required.";
                $_SESSION['flash_type'] = "error";
            } else {
                $stmt = $pdo->prepare("INSERT INTO cities (name, state_id, country_id, status) VALUES (?, ?, 1, ?)");
                $stmt->execute([$name, $state_id, $status]);
                $_SESSION['flash_message'] = "City '<strong>" . htmlspecialchars($name) . "</strong>' added successfully.";
                $_SESSION['flash_type'] = "success";
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $state_id = (int)($_POST['state_id'] ?? 0);
            $status = $_POST['status'] ?? 'active';

            if ($id > 0 && !empty($name) && $state_id > 0) {
                $stmt = $pdo->prepare("UPDATE cities SET name = ?, state_id = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $state_id, $status, $id]);
                $_SESSION['flash_message'] = "City updated successfully.";
                $_SESSION['flash_type'] = "success";
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM cities WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_message'] = "City deleted successfully.";
                $_SESSION['flash_type'] = "success";
            }
        }
    }
    // --- STATE CRUD ---
    elseif ($tab === 'state') {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $status = $_POST['status'] ?? 'active';

            if (empty($name)) {
                $_SESSION['flash_message'] = "State Name is required.";
                $_SESSION['flash_type'] = "error";
            } else {
                $stmt = $pdo->prepare("INSERT INTO states (name, country_id, status) VALUES (?, 1, ?)");
                $stmt->execute([$name, $status]);
                $_SESSION['flash_message'] = "State '<strong>" . htmlspecialchars($name) . "</strong>' added successfully.";
                $_SESSION['flash_type'] = "success";
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $status = $_POST['status'] ?? 'active';

            if ($id > 0 && !empty($name)) {
                $stmt = $pdo->prepare("UPDATE states SET name = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $status, $id]);
                $_SESSION['flash_message'] = "State updated successfully.";
                $_SESSION['flash_type'] = "success";
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM states WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_message'] = "State deleted successfully.";
                $_SESSION['flash_type'] = "success";
            }
        }
    }
    // --- LOGISTICS CRUD ---
    elseif ($tab === 'logistics') {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $tracking_url = trim($_POST['tracking_url'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $status = $_POST['status'] ?? 'active';

            if (empty($name)) {
                $_SESSION['flash_message'] = "Logistics / Courier Name is required.";
                $_SESSION['flash_type'] = "error";
            } else {
                $stmt = $pdo->prepare("INSERT INTO logistics (name, tracking_url, contact_number, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $tracking_url, $contact_number, $status]);
                $_SESSION['flash_message'] = "Logistics partner '<strong>" . htmlspecialchars($name) . "</strong>' added successfully.";
                $_SESSION['flash_type'] = "success";
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $tracking_url = trim($_POST['tracking_url'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $status = $_POST['status'] ?? 'active';

            if ($id > 0 && !empty($name)) {
                $stmt = $pdo->prepare("UPDATE logistics SET name = ?, tracking_url = ?, contact_number = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $tracking_url, $contact_number, $status, $id]);
                $_SESSION['flash_message'] = "Logistics partner updated successfully.";
                $_SESSION['flash_type'] = "success";
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM logistics WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_message'] = "Logistics partner deleted successfully.";
                $_SESSION['flash_type'] = "success";
            }
        }
    }
    header("Location: masters.php?tab=" . urlencode($tab));
    exit();
}

// Fetch states for city form drop-downs
$all_states = $pdo->query("SELECT * FROM states ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle Edit Mode Query
$edit_item = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($tab === 'city') {
        $stmt = $pdo->prepare("SELECT * FROM cities WHERE id = ?");
    } elseif ($tab === 'state') {
        $stmt = $pdo->prepare("SELECT * FROM states WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM logistics WHERE id = ?");
    }
    $stmt->execute([$edit_id]);
    $edit_item = $stmt->fetch();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap-header">
    <h1><i class="fa-solid fa-layer-group" style="color: var(--wp-blue);"></i> System Masters</h1>
</div>

<?php if (!empty($message)): ?>
<div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
    <p><?php echo $message; ?></p>
</div>
<?php endif; ?>

<!-- WordPress Tab Navigation Bar -->
<div style="border-bottom: 1px solid #ccc; margin-bottom: 20px; display: flex; gap: 5px;">
    <a href="masters.php?tab=city" class="button <?php echo $tab === 'city' ? 'button-primary' : 'button-secondary'; ?>" style="font-weight: 600; padding: 6px 16px;">
        <i class="fa-solid fa-city"></i> Cities
    </a>
    <a href="masters.php?tab=state" class="button <?php echo $tab === 'state' ? 'button-primary' : 'button-secondary'; ?>" style="font-weight: 600; padding: 6px 16px;">
        <i class="fa-solid fa-map-location-dot"></i> States
    </a>
    <a href="masters.php?tab=logistics" class="button <?php echo $tab === 'logistics' ? 'button-primary' : 'button-secondary'; ?>" style="font-weight: 600; padding: 6px 16px;">
        <i class="fa-solid fa-truck-fast"></i> Logistics &amp; Couriers
    </a>
</div>

<!-- 2-Column Layout: Form (Left) & List (Right) -->
<div class="wp-editor-columns" style="display: flex; gap: 20px; flex-wrap: wrap;">

    <!-- Left Column: Add / Edit Master Form -->
    <div class="side-column" style="flex: 0 0 340px;">
        <div class="postbox">
            <div class="postbox-header">
                <h2>
                    <?php if ($edit_item): ?>
                        <i class="fa-solid fa-pen-to-square" style="color: var(--wp-blue);"></i> Edit <?php echo ucfirst($tab); ?>
                    <?php else: ?>
                        <i class="fa-solid fa-circle-plus" style="color: var(--wp-blue);"></i> Add New <?php echo ucfirst($tab); ?>
                    <?php endif; ?>
                </h2>
            </div>
            <div class="postbox-body" style="padding: 16px;">
                
                <form method="POST" action="masters.php?tab=<?php echo $tab; ?>">
                    <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
                    <?php if ($edit_item): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_item['id']; ?>">
                    <?php endif; ?>

                    <?php if ($tab === 'city'): ?>
                        
                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="city_name">City Name *</label>
                            <input type="text" id="city_name" name="name" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required placeholder="e.g. Mumbai" class="form-control">
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="state_id">State *</label>
                            <select id="state_id" name="state_id" class="form-control" required>
                                <option value="">-- Select State --</option>
                                <?php foreach ($all_states as $st): ?>
                                    <option value="<?php echo $st['id']; ?>" <?php echo ($edit_item && $edit_item['state_id'] == $st['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($st['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    <?php elseif ($tab === 'state'): ?>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="state_name">State Name *</label>
                            <input type="text" id="state_name" name="name" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required placeholder="e.g. Maharashtra" class="form-control">
                        </div>

                    <?php elseif ($tab === 'logistics'): ?>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="log_name">Courier / Partner Name *</label>
                            <input type="text" id="log_name" name="name" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required placeholder="e.g. Blue Dart" class="form-control">
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="tracking_url">Tracking URL Template</label>
                            <input type="text" id="tracking_url" name="tracking_url" value="<?php echo htmlspecialchars($edit_item['tracking_url'] ?? ''); ?>" placeholder="https://site.com/track?no={TRACKING_NO}" class="form-control">
                            <p style="font-size: 11px; color: #646970; margin-top: 4px;">Use <code>{TRACKING_NO}</code> as placeholder for tracking number.</p>
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="contact_number">Support Contact Number</label>
                            <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($edit_item['contact_number'] ?? ''); ?>" placeholder="1860 233 1234" class="form-control">
                        </div>

                    <?php endif; ?>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="active" <?php echo ($edit_item && $edit_item['status'] === 'active') || !$edit_item ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($edit_item && $edit_item['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="button button-primary">
                            <?php echo $edit_item ? '<i class="fa-solid fa-floppy-disk"></i> Update ' . ucfirst($tab) : '<i class="fa-solid fa-plus"></i> Save ' . ucfirst($tab); ?>
                        </button>
                        <?php if ($edit_item): ?>
                            <a href="masters.php?tab=<?php echo $tab; ?>" class="button button-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- Right Column: Masters Table -->
    <div class="main-column" style="flex: 1; min-width: 450px;">
        <div class="postbox">
            <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2><i class="fa-solid fa-list" style="color: var(--wp-blue);"></i> <?php echo ucfirst($tab); ?> Masters List</h2>
            </div>
            <div class="postbox-body" style="padding: 16px;">
                
                <?php if ($tab === 'city'): ?>
                    <?php
                    $stmt = $pdo->query("SELECT c.*, s.name as state_name FROM cities c LEFT JOIN states s ON c.state_id = s.id ORDER BY c.name ASC");
                    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <table class="wp-list-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>City Name</th>
                                <th>State Name</th>
                                <th style="width: 80px;">Status</th>
                                <th style="width: 120px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cities)): ?>
                                <tr><td colspan="5" style="text-align: center; color: #888; padding: 20px;">No cities found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cities as $c): ?>
                                    <tr>
                                        <td>#<?php echo $c['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($c['state_name'] ?: 'N/A'); ?></td>
                                        <td>
                                            <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; background: <?php echo $c['status'] === 'active' ? '#e6f4ea; color: #137333;' : '#fce8e6; color: #c5221f;'; ?>">
                                                <?php echo ucfirst($c['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="masters.php?tab=city&edit=<?php echo $c['id']; ?>" class="button button-secondary" style="font-size: 11px; padding: 2px 8px;"><i class="fa-solid fa-pen"></i> Edit</a>
                                            <form method="POST" action="masters.php?tab=city" style="display: inline;" onsubmit="return confirm('Delete this city?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                <button type="submit" class="button" style="font-size: 11px; padding: 2px 8px; color: #c5221f;"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                <?php elseif ($tab === 'state'): ?>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM states ORDER BY name ASC");
                    $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <table class="wp-list-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>State Name</th>
                                <th style="width: 80px;">Status</th>
                                <th style="width: 120px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($states)): ?>
                                <tr><td colspan="4" style="text-align: center; color: #888; padding: 20px;">No states found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($states as $s): ?>
                                    <tr>
                                        <td>#<?php echo $s['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                        <td>
                                            <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; background: <?php echo $s['status'] === 'active' ? '#e6f4ea; color: #137333;' : '#fce8e6; color: #c5221f;'; ?>">
                                                <?php echo ucfirst($s['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="masters.php?tab=state&edit=<?php echo $s['id']; ?>" class="button button-secondary" style="font-size: 11px; padding: 2px 8px;"><i class="fa-solid fa-pen"></i> Edit</a>
                                            <form method="POST" action="masters.php?tab=state" style="display: inline;" onsubmit="return confirm('Delete this state?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                <button type="submit" class="button" style="font-size: 11px; padding: 2px 8px; color: #c5221f;"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                <?php elseif ($tab === 'logistics'): ?>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM logistics ORDER BY name ASC");
                    $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <table class="wp-list-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>Courier / Partner Name</th>
                                <th>Contact Support</th>
                                <th style="width: 80px;">Status</th>
                                <th style="width: 120px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($partners)): ?>
                                <tr><td colspan="5" style="text-align: center; color: #888; padding: 20px;">No logistics partners found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($partners as $l): ?>
                                    <tr>
                                        <td>#<?php echo $l['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($l['name']); ?></strong>
                                            <?php if (!empty($l['tracking_url'])): ?>
                                                <div style="font-size: 11px; color: #646970;">URL: <code><?php echo htmlspecialchars($l['tracking_url']); ?></code></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($l['contact_number'] ?: 'N/A'); ?></td>
                                        <td>
                                            <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; background: <?php echo $l['status'] === 'active' ? '#e6f4ea; color: #137333;' : '#fce8e6; color: #c5221f;'; ?>">
                                                <?php echo ucfirst($l['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="masters.php?tab=logistics&edit=<?php echo $l['id']; ?>" class="button button-secondary" style="font-size: 11px; padding: 2px 8px;"><i class="fa-solid fa-pen"></i> Edit</a>
                                            <form method="POST" action="masters.php?tab=logistics" style="display: inline;" onsubmit="return confirm('Delete this logistics partner?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                                <button type="submit" class="button" style="font-size: 11px; padding: 2px 8px; color: #c5221f;"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
