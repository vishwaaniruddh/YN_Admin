<?php
// admin/includes/cache.php
// High-Performance File-Based Caching System for YosshitaNeha Fashion Studio

define('CACHE_DIR', __DIR__ . '/../cache/');
define('DEFAULT_CACHE_TTL', 3600); // 1 hour default

/**
 * Ensure cache directory exists and is writable
 */
function init_cache_dir() {
    if (!is_dir(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents(CACHE_DIR . '.htaccess', "Order deny,allow\nDeny from all");
        @file_put_contents(CACHE_DIR . 'index.php', "<?php // Silence is golden");
    }
}

/**
 * Generate secure cache filename from key
 */
function get_cache_filepath($key) {
    return CACHE_DIR . 'cache_' . md5($key) . '.json';
}

/**
 * Check if caching is enabled globally in site_settings
 */
function is_caching_enabled($pdo = null) {
    static $enabled = null;
    if ($enabled !== null) return $enabled;

    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'enable_api_caching'");
            $val = $stmt ? $stmt->fetchColumn() : '1';
            $enabled = ($val !== '0');
            return $enabled;
        } catch (Exception $e) {}
    }
    $enabled = true;
    return $enabled;
}

/**
 * Retrieve cached data by key
 */
function get_cache($key, $ttl = DEFAULT_CACHE_TTL, $pdo = null) {
    if (!is_caching_enabled($pdo)) return false;

    init_cache_dir();
    $file = get_cache_filepath($key);

    if (!file_exists($file)) {
        return false;
    }

    $mtime = @filemtime($file);
    if ($mtime === false || (time() - $mtime) > $ttl) {
        @unlink($file); // Expired cache
        return false;
    }

    $content = @file_get_contents($file);
    if (empty($content)) return false;

    $json = @json_decode($content, true);
    return is_array($json) && isset($json['__data']) ? $json['__data'] : false;
}

/**
 * Save data into cache file
 */
function set_cache($key, $data, $pdo = null) {
    if (!is_caching_enabled($pdo)) return false;

    init_cache_dir();
    $file = get_cache_filepath($key);

    $payload = json_encode([
        '__key' => $key,
        '__created' => time(),
        '__data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return @file_put_contents($file, $payload, LOCK_EX) !== false;
}

/**
 * Purge all or matching cache files
 */
function purge_cache($pattern = null) {
    init_cache_dir();
    $files = glob(CACHE_DIR . 'cache_*.json');
    $purgedCount = 0;

    if (is_array($files)) {
        foreach ($files as $file) {
            if ($pattern === null || str_contains($file, md5($pattern))) {
                if (@unlink($file)) {
                    $purgedCount++;
                }
            }
        }
    }

    // Save last purge timestamp
    @file_put_contents(CACHE_DIR . 'last_purge.txt', time());
    return $purgedCount;
}

/**
 * Get detailed cache performance stats for Admin Panel
 */
function get_cache_stats() {
    init_cache_dir();
    $files = glob(CACHE_DIR . 'cache_*.json');
    
    $totalFiles = is_array($files) ? count($files) : 0;
    $totalSize = 0;
    $items = [];

    if (is_array($files)) {
        foreach ($files as $file) {
            $size = @filesize($file) ?: 0;
            $totalSize += $size;
            $mtime = @filemtime($file) ?: 0;
            
            $content = @file_get_contents($file);
            $json = @json_decode($content, true);
            $key = $json['__key'] ?? basename($file);

            $items[] = [
                'file' => basename($file),
                'key' => $key,
                'size_bytes' => $size,
                'size_formatted' => round($size / 1024, 2) . ' KB',
                'created_at' => date('Y-m-d H:i:s', $mtime),
                'age_seconds' => time() - $mtime
            ];
        }
    }

    $lastPurgeTime = @file_get_contents(CACHE_DIR . 'last_purge.txt');
    $lastPurgeFormatted = $lastPurgeTime ? date('d M Y, h:i A', (int)$lastPurgeTime) : 'Never';

    return [
        'total_files' => $totalFiles,
        'total_size_bytes' => $totalSize,
        'total_size_formatted' => $totalSize > 1048576 
            ? round($totalSize / 1048576, 2) . ' MB' 
            : round($totalSize / 1024, 2) . ' KB',
        'last_purge' => $lastPurgeFormatted,
        'items' => $items
    ];
}
