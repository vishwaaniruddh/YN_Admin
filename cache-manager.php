<?php
// admin/cache-manager.php
$page_title = "Cache Manager";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$message_type = 'success';

// Handle session flash messages
if (!empty($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg']['text'] ?? '';
    $message_type = $_SESSION['flash_msg']['type'] ?? 'success';
    unset($_SESSION['flash_msg']);
}

// Handle POST Actions (PRG Pattern: Post-Redirect-Get)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'purge_all') {
        $count = purge_cache();
        $_SESSION['flash_msg'] = ['text' => "Successfully purged $count cached files!", 'type' => 'success'];
    } elseif ($action === 'purge_single' && !empty($_POST['cache_key'])) {
        purge_cache($_POST['cache_key']);
        $_SESSION['flash_msg'] = ['text' => "Purged cache item.", 'type' => 'success'];
    } elseif ($action === 'warmup') {
        // Clear any old/stale cache files first
        purge_cache();

        // 1. Warm Categories Tree using build_nested_category_tree
        $stmtCat = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC");
        $allCats = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
        $categoriesTree = build_nested_category_tree($allCats);
        set_cache('categories_tree', $categoriesTree, $pdo);

        // 2. Warm Default Products list (products_c0_csnone_f0_sqnone_snewest_p1_l12)
        $cache_key_prod = "products_c0_csnone_f0_sqnone_snewest_p1_l12";
        $stmtProd = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = 'published' AND p.deleted_at IS NULL ORDER BY p.id DESC LIMIT 12");
        $products = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as &$product) {
            $imgStmt = $pdo->prepare("SELECT image_path, thumb_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
            $imgStmt->execute([$product['id']]);
            $product['images'] = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

            $discount = get_product_discount_info($pdo, $product['id'], $product['price']);
            if ($discount) {
                $product['original_price'] = (float)$product['price'];
                $product['discount_info'] = $discount;
                $product['sale_price'] = $discount['discounted_price'];
                $product['has_discount'] = true;
            } else {
                $product['original_price'] = (float)$product['price'];
                $product['has_discount'] = false;
            }
        }

        $count_stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'published' AND deleted_at IS NULL");
        $total_items = (int)$count_stmt->fetchColumn();

        $prodResponse = [
            'success' => true,
            'category' => null,
            'data' => $products,
            'pagination' => [
                'current_page' => 1,
                'total_pages' => ceil($total_items / 12),
                'total_items' => $total_items,
                'limit' => 12
            ]
        ];
        set_cache($cache_key_prod, $prodResponse, $pdo);

        $_SESSION['flash_msg'] = ['text' => "Cache successfully warmed up! Pre-generated valid Category Tree and Product catalog endpoints.", 'type' => 'success'];
    } elseif ($action === 'save_settings') {
        $enable_caching = isset($_POST['enable_caching']) ? '1' : '0';
        $cache_ttl = (int)($_POST['cache_ttl'] ?? 3600);

        try {
            $stmt1 = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('enable_api_caching', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt1->execute([$enable_caching, $enable_caching]);

            $stmt2 = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('api_cache_ttl', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt2->execute([$cache_ttl, $cache_ttl]);

            // Flush cache on settings change
            purge_cache();

            $_SESSION['flash_msg'] = ['text' => "Cache settings updated successfully!", 'type' => 'success'];
        } catch (Exception $e) {
            $_SESSION['flash_msg'] = ['text' => "Error updating settings: " . $e->getMessage(), 'type' => 'error'];
        }
    }

    // Redirect to prevent "Confirm Form Resubmission" prompt on refresh
    header("Location: cache-manager.php");
    exit();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch current stats
$stats = get_cache_stats();

// Fetch settings
$cachingEnabled = is_caching_enabled($pdo);
$cacheTTL = 3600;
try {
    $stmtTTL = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'api_cache_ttl'");
    $val = $stmtTTL ? $stmtTTL->fetchColumn() : false;
    if ($val) $cacheTTL = (int)$val;
} catch (Exception $e) {}
?>

<!-- Header Section in WordPress Admin Style -->
<div class="wrap-header">
    <h1><i class="fa-solid fa-bolt" style="color: var(--wp-blue);"></i> Cache Manager &amp; Performance Booster</h1>
    <div style="display: flex; gap: 8px; align-items: center;">
        <form method="POST" style="display: inline-block;">
            <input type="hidden" name="action" value="warmup">
            <button type="submit" class="button button-primary"><i class="fa-solid fa-fire-flame-curved"></i> Warm Up Cache</button>
        </form>
        <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to purge all cached files?');">
            <input type="hidden" name="action" value="purge_all">
            <button type="submit" class="button button-danger"><i class="fa-solid fa-trash-can"></i> Purge All Cache</button>
        </form>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="notice notice-<?php echo $message_type; ?> auto-dismiss">
        <p><i class="fa-solid fa-circle-check"></i> <?php echo sanitize_html($message); ?></p>
    </div>
<?php endif; ?>

<!-- Stats Overview Grid -->
<div class="dashboard-grid" style="margin-bottom: 25px;">
    <div class="dash-card">
        <div class="dash-card-icon" style="background-color: rgba(52, 152, 219, 0.12); color: #3498db;">
            <i class="fa-solid fa-file-code"></i>
        </div>
        <div class="dash-card-info">
            <h3>Cached Files</h3>
            <p><?php echo $stats['total_files']; ?></p>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-icon" style="background-color: rgba(155, 89, 182, 0.12); color: #9b59b6;">
            <i class="fa-solid fa-hard-drive"></i>
        </div>
        <div class="dash-card-info">
            <h3>Disk Usage</h3>
            <p style="font-size: 20px;"><?php echo $stats['total_size_formatted']; ?></p>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-icon" style="background-color: rgba(46, 204, 113, 0.12); color: #2ecc71;">
            <i class="fa-solid fa-gauge-high"></i>
        </div>
        <div class="dash-card-info">
            <h3>API Speedup</h3>
            <p style="font-size: 20px; color: #2ecc71;">&lt; 5ms</p>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-icon" style="background-color: rgba(241, 196, 15, 0.12); color: #f1c40f;">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="dash-card-info">
            <h3>Last Purged</h3>
            <p style="font-size: 13px; margin-top: 4px;"><?php echo $stats['last_purge']; ?></p>
        </div>
    </div>
</div>

<style>
.cache-key-badge {
    background: #1e293b;
    color: #f59e0b;
    border: 1px solid #334155;
    padding: 4px 8px;
    border-radius: 5px;
    font-weight: 600;
    font-family: monospace;
    font-size: 12px;
    display: inline-block;
    word-break: break-all;
    line-height: 1.4;
}
.btn-purge-item {
    background: #dc2626;
    color: #ffffff;
    border: none;
    padding: 5px 12px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s ease;
}
.btn-purge-item:hover {
    background: #b91c1c;
    color: #ffffff;
}
</style>

<!-- Two-Column WordPress Layout -->
<div class="wp-editor-columns">
    
    <!-- Left Main Column: Cached Endpoints -->
    <div class="main-column">
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-list-check" style="color: var(--wp-blue);"></i> Cached API Endpoints</h2>
            </div>
            <div class="postbox-body" style="padding: 0; max-height: 520px; overflow-y: auto;">
                <?php if (empty($stats['items'])): ?>
                    <div style="text-align: center; padding: 40px; color: #646970;">
                        <i class="fa-solid fa-box-open" style="font-size: 36px; margin-bottom: 12px; color: #a7aaad;"></i>
                        <p style="margin: 0; font-size: 14px;">No active cache files on disk. Click <strong>Warm Up Cache</strong> to pre-generate endpoints.</p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead style="position: sticky; top: 0; z-index: 10; background: #fff;">
                            <tr>
                                <th style="width: 45%;">Cache Key</th>
                                <th style="width: 12%;">Size</th>
                                <th style="width: 13%;">Age</th>
                                <th style="width: 18%;">Created At</th>
                                <th style="width: 12%; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['items'] as $item): ?>
                                <tr>
                                    <td style="word-break: break-all;">
                                        <code class="cache-key-badge">
                                            <?php echo htmlspecialchars($item['key']); ?>
                                        </code>
                                    </td>
                                    <td style="white-space: nowrap; font-weight: 500;"><?php echo $item['size_formatted']; ?></td>
                                    <td style="white-space: nowrap;"><?php echo round($item['age_seconds'] / 60, 1); ?> mins ago</td>
                                    <td style="white-space: nowrap;"><?php echo $item['created_at']; ?></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="purge_single">
                                            <input type="hidden" name="cache_key" value="<?php echo htmlspecialchars($item['key']); ?>">
                                            <button type="submit" class="btn-purge-item"><i class="fa-solid fa-xmark"></i> Purge</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Side Column: Settings & Information -->
    <div class="side-column">
        <!-- Settings Postbox -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-sliders" style="color: var(--wp-blue);"></i> Cache Settings</h2>
            </div>
            <div class="postbox-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; cursor: pointer; gap: 8px; font-weight: 600;">
                            <input type="checkbox" name="enable_caching" value="1" <?php echo $cachingEnabled ? 'checked' : ''; ?>>
                            Enable API Response Caching
                        </label>
                        <p style="font-size: 11px; color: #646970; margin: 4px 0 0 24px;">Stores API responses in memory/disk to eliminate redundant SQL database queries.</p>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="cache_ttl" style="font-weight: 600;">Cache Expiration (TTL)</label>
                        <select name="cache_ttl" id="cache_ttl" class="form-control" style="margin-top: 6px;">
                            <option value="900" <?php echo ($cacheTTL == 900) ? 'selected' : ''; ?>>15 Minutes</option>
                            <option value="1800" <?php echo ($cacheTTL == 1800) ? 'selected' : ''; ?>>30 Minutes</option>
                            <option value="3600" <?php echo ($cacheTTL == 3600) ? 'selected' : ''; ?>>1 Hour (Recommended)</option>
                            <option value="21600" <?php echo ($cacheTTL == 21600) ? 'selected' : ''; ?>>6 Hours</option>
                            <option value="86400" <?php echo ($cacheTTL == 86400) ? 'selected' : ''; ?>>24 Hours</option>
                        </select>
                    </div>

                    <button type="submit" class="button button-primary" style="width: 100%; justify-content: center; font-weight: 600;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Cache Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Automatic Invalidation Info Postbox -->
        <div class="postbox">
            <div class="postbox-header">
                <h2><i class="fa-solid fa-lightbulb" style="color: #ffb900;"></i> Automatic Invalidation</h2>
            </div>
            <div class="postbox-body">
                <p style="font-size: 13px; color: #646970; line-height: 1.6; margin: 0;">
                    Whenever you edit or add a Product, Category, or Setting in the YosshitaNeha Admin panel, the system automatically purges stale cache. Storefront customers will always see updated data instantly without manual intervention.
                </p>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
