<?php
// admin/cache-manager.php
$page_title = "Cache Manager";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$message = '';
$message_type = 'success';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'purge_all') {
        $count = purge_cache();
        $message = "Successfully purged $count cached files!";
        $message_type = "success";
    } elseif ($action === 'purge_single' && !empty($_POST['cache_key'])) {
        purge_cache($_POST['cache_key']);
        $message = "Purged cache item.";
        $message_type = "success";
    } elseif ($action === 'warmup') {
        // Pre-warm key caches
        init_cache_dir();
        
        // 1. Warm Categories Tree
        $stmtCat = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC");
        $cats = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
        set_cache('categories_tree', get_category_tree($cats), $pdo);

        // 2. Warm Site Settings
        $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        $settingsRows = $stmtSettings->fetchAll(PDO::FETCH_ASSOC);
        $settingsMap = [];
        foreach ($settingsRows as $row) {
            $settingsMap[$row['setting_key']] = $row['setting_value'];
        }
        set_cache('site_settings', $settingsMap, $pdo);

        // 3. Warm Popular Products
        $stmtProd = $pdo->query("SELECT id, name, slug, sku, price, sale_price, stock_qty, main_image, category_id FROM products WHERE status = 'published' AND deleted_at IS NULL ORDER BY id DESC LIMIT 12");
        $prods = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
        set_cache('popular_products_12', $prods, $pdo);

        $message = "Cache successfully warmed up! Pre-generated 3 core endpoints.";
        $message_type = "success";
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

            $message = "Cache settings updated successfully!";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Error updating settings: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

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

<style>
.cache-container {
    padding: 25px 30px;
    color: #e2e8f0;
}
.cache-header-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1e1e1e;
    padding: 24px 30px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    margin-bottom: 25px;
}
.cache-header-card h1 {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 6px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.cache-header-card p {
    font-size: 13px;
    color: #94a3b8;
    margin: 0;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}
.stat-card {
    background: #181818;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: rgba(200, 165, 92, 0.12);
    color: #c8a55c;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.stat-info h4 {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 2px 0;
}
.stat-info p {
    font-size: 12px;
    color: #94a3b8;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}
.cache-grid-columns {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
}
.card-box {
    background: #181818;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 25px;
}
.card-box h3 {
    font-size: 16px;
    font-weight: 600;
    color: #fff;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.cache-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.cache-table th {
    text-align: left;
    padding: 10px 14px;
    background: #101010;
    color: #94a3b8;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.cache-table td {
    padding: 12px 14px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: #cbd5e1;
}
.cache-key-badge {
    background: rgba(200, 165, 92, 0.15);
    color: #c8a55c;
    padding: 3px 8px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    font-weight: 600;
}
.btn-purge {
    background: #d63638;
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.2s;
}
.btn-purge:hover { background: #b32d2e; }
.btn-warm {
    background: #c8a55c;
    color: #000;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.2s;
}
.btn-warm:hover { background: #dfb96c; }
.btn-sm-danger {
    background: rgba(214, 54, 56, 0.2);
    color: #f87171;
    border: 1px solid rgba(214, 54, 56, 0.3);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    cursor: pointer;
}
.btn-sm-danger:hover { background: #d63638; color: #fff; }
</style>

<div class="cache-container">

    <!-- Header Section -->
    <div class="cache-header-card">
        <div>
            <h1><i class="fa-solid fa-bolt" style="color: #ffb900;"></i> Cache Manager &amp; Performance Booster</h1>
            <p>Accelerate customer page loads by serving pre-computed JSON responses directly in &lt; 5ms.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <form method="POST">
                <input type="hidden" name="action" value="warmup">
                <button type="submit" class="btn-warm" title="Pre-generate core cache files">
                    <i class="fa-solid fa-fire-flame-curved"></i> Warm Up Cache
                </button>
            </form>
            <form method="POST" onsubmit="return confirm('Are you sure you want to purge all cached files?');">
                <input type="hidden" name="action" value="purge_all">
                <button type="submit" class="btn-purge">
                    <i class="fa-solid fa-trash-can"></i> Purge All Cache
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="notice notice-<?php echo $message_type; ?> auto-dismiss" style="margin-bottom: 25px;">
            <p><?php echo sanitize_html($message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Metrics Stat Bar -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-file-code"></i></div>
            <div class="stat-info">
                <h4><?php echo $stats['total_files']; ?></h4>
                <p>Cached Files</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-hard-drive"></i></div>
            <div class="stat-info">
                <h4><?php echo $stats['total_size_formatted']; ?></h4>
                <p>Disk Usage</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="color: #4ab866; background: rgba(74, 184, 102, 0.12);"><i class="fa-solid fa-gauge-high"></i></div>
            <div class="stat-info">
                <h4 style="color: #4ab866;">&lt; 5ms</h4>
                <p>API Speedup</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="stat-info">
                <h4 style="font-size: 14px; margin-top: 4px;"><?php echo $stats['last_purge']; ?></h4>
                <p>Last Purged</p>
            </div>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="cache-grid-columns">
        
        <!-- Left: Cached Items Listing -->
        <div class="card-box">
            <h3><i class="fa-solid fa-list-check" style="color: #c8a55c;"></i> Cached API Endpoints</h3>
            <?php if (empty($stats['items'])): ?>
                <div style="text-align: center; padding: 40px; color: #94a3b8;">
                    <i class="fa-solid fa-box-open" style="font-size: 36px; margin-bottom: 12px; color: #475569;"></i>
                    <p style="margin: 0; font-size: 14px;">No active cache files on disk. Click <strong>Warm Up Cache</strong> to pre-generate endpoints.</p>
                </div>
            <?php else: ?>
                <table class="cache-table">
                    <thead>
                        <tr>
                            <th>Cache Key</th>
                            <th>Size</th>
                            <th>Age</th>
                            <th>Created At</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['items'] as $item): ?>
                            <tr>
                                <td><span class="cache-key-badge"><?php echo htmlspecialchars($item['key']); ?></span></td>
                                <td><?php echo $item['size_formatted']; ?></td>
                                <td><?php echo round($item['age_seconds'] / 60, 1); ?> mins ago</td>
                                <td><?php echo $item['created_at']; ?></td>
                                <td style="text-align: right;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="purge_single">
                                        <input type="hidden" name="cache_key" value="<?php echo htmlspecialchars($item['key']); ?>">
                                        <button type="submit" class="btn-sm-danger"><i class="fa-solid fa-xmark"></i> Purge</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Right: Settings & Information -->
        <div>
            <div class="card-box">
                <h3><i class="fa-solid fa-sliders" style="color: #c8a55c;"></i> Cache Settings</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">

                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; cursor: pointer; gap: 10px; font-weight: 600; font-size: 14px; color: #fff;">
                            <input type="checkbox" name="enable_caching" value="1" <?php echo $cachingEnabled ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #c8a55c;">
                            Enable API Response Caching
                        </label>
                        <p style="font-size: 12px; color: #94a3b8; margin: 4px 0 0 28px;">Stores API responses in memory/disk to eliminate redundant SQL database queries.</p>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 6px;">Cache Expiration (TTL)</label>
                        <select name="cache_ttl" style="width: 100%; background: #090909; border: 1px solid rgba(255,255,255,0.15); border-radius: 6px; color: #fff; padding: 10px; font-size: 13px;">
                            <option value="900" <?php echo ($cacheTTL == 900) ? 'selected' : ''; ?>>15 Minutes</option>
                            <option value="1800" <?php echo ($cacheTTL == 1800) ? 'selected' : ''; ?>>30 Minutes</option>
                            <option value="3600" <?php echo ($cacheTTL == 3600) ? 'selected' : ''; ?>>1 Hour (Recommended)</option>
                            <option value="21600" <?php echo ($cacheTTL == 21600) ? 'selected' : ''; ?>>6 Hours</option>
                            <option value="86400" <?php echo ($cacheTTL == 86400) ? 'selected' : ''; ?>>24 Hours</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-warm" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Cache Settings
                    </button>
                </form>
            </div>

            <div class="card-box" style="background: rgba(200, 165, 92, 0.05); border-color: rgba(200, 165, 92, 0.2);">
                <h3 style="color: #c8a55c;"><i class="fa-solid fa-lightbulb"></i> How Automatic Invalidation Works</h3>
                <p style="font-size: 12px; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    Whenever you edit or add a Product, Category, or Setting in the YosshitaNeha Admin panel, the system automatically purges stale cache. Customers will always see updated data instantly without manual intervention.
                </p>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
