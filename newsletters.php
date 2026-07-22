<?php
// admin/newsletters.php
$page_title = "Newsletters";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch newsletter subscribers
$subscribers = [];
try {
    $stmt = $pdo->query("SELECT id, email, status, created_at FROM newsletters ORDER BY created_at DESC");
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Database error: " . $e->getMessage();
}
?>

<div class="wrap-header" style="display: flex; justify-content: space-between; align-items: center;">
    <h1><i class="fa-solid fa-envelope-open-text"></i> Newsletter Subscribers</h1>
    <span class="badge" style="background: var(--gold); color: #000; padding: 4px 12px; border-radius: 20px; font-weight: 600;">
        Total: <?php echo count($subscribers); ?>
    </span>
</div>

<?php if (isset($error_msg)): ?>
<div class="notice notice-error"><p><?php echo $error_msg; ?></p></div>
<?php endif; ?>

<div class="card" style="padding: 0;">
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" id="id" class="manage-column column-id" style="width: 60px;">ID</th>
                <th scope="col" id="email" class="manage-column column-email">Email Address</th>
                <th scope="col" id="status" class="manage-column column-status" style="width: 150px;">Status</th>
                <th scope="col" id="date" class="manage-column column-date" style="width: 200px;">Subscribed On</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($subscribers)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 30px;">No subscribers found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($subscribers as $sub): ?>
                <tr>
                    <td><?php echo $sub['id']; ?></td>
                    <td><strong><?php echo sanitize_html($sub['email']); ?></strong></td>
                    <td>
                        <?php if ($sub['status'] === 'subscribed'): ?>
                            <span class="badge" style="background: rgba(46, 204, 113, 0.15); color: #2ecc71; padding: 4px 10px; border-radius: 12px; font-size: 11px;">Subscribed</span>
                        <?php else: ?>
                            <span class="badge" style="background: rgba(231, 76, 60, 0.15); color: #e74c3c; padding: 4px 10px; border-radius: 12px; font-size: 11px;">Unsubscribed</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y h:i A', strtotime($sub['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
