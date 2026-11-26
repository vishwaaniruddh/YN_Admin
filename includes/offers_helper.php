<?php
// admin/includes/offers_helper.php
// Schema initialization, shared presets, and active-offer query helpers

/**
 * Ensures the `offer_popups` table exists (runs DDL once per schema version).
 */
function ensure_offer_popups_table($pdo) {
    if (!$pdo) return false;
    static $checked = false;
    if ($checked) return true;

    $cacheDir = __DIR__ . '/../cache';
    $flagFile = $cacheDir . '/.offer_popups_schema_v3';

    if (file_exists($flagFile)) {
        $checked = true;
        return true;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS offer_popups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            template_id VARCHAR(64) DEFAULT NULL,
            layout_type ENUM('top_banner', 'split_left', 'split_right', 'banner_only', 'text_card', 'hero_overlay') NOT NULL DEFAULT 'top_banner',
            card_style VARCHAR(40) NOT NULL DEFAULT 'classic',
            banner_image VARCHAR(500) DEFAULT NULL,
            mobile_banner_image VARCHAR(500) DEFAULT NULL,
            badge_text VARCHAR(120) DEFAULT NULL,
            heading VARCHAR(255) DEFAULT NULL,
            subheading VARCHAR(255) DEFAULT NULL,
            body_text TEXT DEFAULT NULL,
            coupon_code VARCHAR(100) DEFAULT NULL,
            coupon_note VARCHAR(255) DEFAULT NULL,
            auto_apply_coupon TINYINT(1) NOT NULL DEFAULT 1,
            allow_dont_show_again TINYINT(1) NOT NULL DEFAULT 1,
            dont_show_label VARCHAR(120) DEFAULT \"Don't show this again\",
            cta_label VARCHAR(120) DEFAULT NULL,
            cta_url VARCHAR(500) DEFAULT NULL,
            theme_preset VARCHAR(50) NOT NULL DEFAULT 'ivory_gold',
            bg_color VARCHAR(20) NOT NULL DEFAULT '#FAF7F3',
            secondary_color VARCHAR(20) NOT NULL DEFAULT '#EFE6D5',
            text_color VARCHAR(20) NOT NULL DEFAULT '#18181B',
            accent_color VARCHAR(20) NOT NULL DEFAULT '#B08D57',
            target_pages TEXT NOT NULL,
            target_category_slugs TEXT DEFAULT NULL,
            custom_url_patterns TEXT DEFAULT NULL,
            trigger_type ENUM('delay', 'scroll', 'exit_intent') NOT NULL DEFAULT 'delay',
            trigger_delay_sec INT NOT NULL DEFAULT 2,
            trigger_scroll_pct INT NOT NULL DEFAULT 35,
            frequency ENUM('once_per_session', 'once_per_day', 'once_per_week', 'every_load') NOT NULL DEFAULT 'once_per_session',
            start_at DATETIME DEFAULT NULL,
            end_at DATETIME DEFAULT NULL,
            priority INT NOT NULL DEFAULT 10,
            view_count INT NOT NULL DEFAULT 0,
            click_count INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_offer_active_priority (is_active, priority, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Upgrade path if v1/v2 table already existed
        $pdo->exec("ALTER TABLE offer_popups ADD COLUMN IF NOT EXISTS allow_dont_show_again TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_apply_coupon");
        $pdo->exec("ALTER TABLE offer_popups ADD COLUMN IF NOT EXISTS dont_show_label VARCHAR(120) DEFAULT \"Don't show this again\" AFTER allow_dont_show_again");
        $pdo->exec("ALTER TABLE offer_popups ADD COLUMN IF NOT EXISTS template_id VARCHAR(64) DEFAULT NULL AFTER is_active");
        $pdo->exec("ALTER TABLE offer_popups ADD COLUMN IF NOT EXISTS card_style VARCHAR(40) NOT NULL DEFAULT 'classic' AFTER layout_type");
        $pdo->exec("ALTER TABLE offer_popups ADD COLUMN IF NOT EXISTS secondary_color VARCHAR(20) NOT NULL DEFAULT '#EFE6D5' AFTER bg_color");
        $pdo->exec("ALTER TABLE offer_popups MODIFY COLUMN layout_type ENUM('top_banner', 'split_left', 'split_right', 'banner_only', 'text_card', 'hero_overlay') NOT NULL DEFAULT 'top_banner'");

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @file_put_contents($flagFile, date('Y-m-d H:i:s'));
        $checked = true;
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Targetable website page keys for web2.
 */
function offer_target_page_options() {
    return [
        'all' => ['label' => 'All Website Pages', 'hint' => 'Every route', 'icon' => 'fa-globe'],
        'home' => ['label' => 'Home Page', 'hint' => '/', 'icon' => 'fa-house'],
        'category' => ['label' => 'Category / Collections', 'hint' => '/collections/...', 'icon' => 'fa-layer-group'],
        'product' => ['label' => 'Product Pages', 'hint' => '/product/...', 'icon' => 'fa-gem'],
        'cart' => ['label' => 'Shopping Cart', 'hint' => '/cart', 'icon' => 'fa-bag-shopping'],
        'checkout' => ['label' => 'Checkout Page', 'hint' => '/checkout', 'icon' => 'fa-credit-card'],
        'search' => ['label' => 'Search Results', 'hint' => '/search', 'icon' => 'fa-magnifying-glass'],
        'lookbook' => ['label' => 'Lookbook & Client Diaries', 'hint' => '/portfolio, /lookbook', 'icon' => 'fa-images'],
        'blouse_studio' => ['label' => 'Blouse Studio / AI Try-On', 'hint' => '/blouse-studio', 'icon' => 'fa-wand-magic-sparkles'],
        'consultation' => ['label' => 'Consultation Page', 'hint' => '/consultation', 'icon' => 'fa-calendar-check'],
        'account' => ['label' => 'Account & Orders', 'hint' => '/account, /order/...', 'icon' => 'fa-user'],
    ];
}

/**
 * Curated color themes aligned with YosshitaNeha Atelier.
 */
function offer_theme_presets() {
    return [
        'ivory_gold' => [
            'label' => 'Atelier Ivory & Gold',
            'bg' => '#FAF7F3',
            'secondary' => '#EFE6D5',
            'text' => '#18181B',
            'accent' => '#B08D57',
        ],
        'obsidian_gold' => [
            'label' => 'Midnight Obsidian & Gold',
            'bg' => '#141416',
            'secondary' => '#27272A',
            'text' => '#FAF7F3',
            'accent' => '#D4AF37',
        ],
        'rose_velvet' => [
            'label' => 'Bridal Rose & Wine',
            'bg' => '#FFF7F8',
            'secondary' => '#FCE7F3',
            'text' => '#2A1215',
            'accent' => '#9F1239',
        ],
        'emerald_royal' => [
            'label' => 'Royal Emerald & Ivory',
            'bg' => '#06281E',
            'secondary' => '#064E3B',
            'text' => '#FAF7F3',
            'accent' => '#D4AF37',
        ],
        'custom' => [
            'label' => 'Custom Palette',
            'bg' => '#FFFFFF',
            'secondary' => '#F4F4F5',
            'text' => '#09090B',
            'accent' => '#B08D57',
        ],
    ];
}

/**
 * Uploads an offer banner image directly without square thumbnail cropping.
 */
function upload_offer_banner($file_array, $prefix = 'offer') {
    if (!isset($file_array) || ($file_array['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $info = @getimagesize($file_array['tmp_name']);
    if (!$info || !in_array($info['mime'], $allowed_mimes, true)) {
        return ['error' => 'Invalid banner format. Allowed formats: JPG, PNG, GIF, WEBP.'];
    }

    $relativeDir = 'uploads/offers';
    $targetDir = __DIR__ . '/../' . $relativeDir;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $extMap[$info['mime']] ?? strtolower(pathinfo($file_array['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix) ?: 'offer';
    $filename = $safePrefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $targetDir . '/' . $filename;

    if (move_uploaded_file($file_array['tmp_name'], $dest)) {
        return ['filepath' => $relativeDir . '/' . $filename];
    }

    return ['error' => 'Failed to save uploaded banner image.'];
}

/**
 * Decodes and normalizes a raw `offer_popups` DB row for API consumption.
 */
function normalize_offer_row($row, $couponMap = []) {
    $pages = json_decode($row['target_pages'] ?? '[]', true);
    if (!is_array($pages) || empty($pages)) {
        $pages = ['all'];
    }

    $catSlugs = json_decode($row['target_category_slugs'] ?? '[]', true);
    if (!is_array($catSlugs)) {
        $catSlugs = [];
    }

    $customPatterns = [];
    if (!empty($row['custom_url_patterns'])) {
        $parts = preg_split('/[\r\n,]+/', $row['custom_url_patterns']);
        foreach ($parts as $p) {
            $trimmed = trim($p);
            if ($trimmed !== '') {
                $customPatterns[] = $trimmed;
            }
        }
    }

    $codeUpper = strtoupper(trim((string)($row['coupon_code'] ?? '')));
    $couponMeta = ($codeUpper !== '' && isset($couponMap[$codeUpper])) ? $couponMap[$codeUpper] : null;

    return [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'template_id' => $row['template_id'] ?? '',
        'layout_type' => $row['layout_type'] ?: 'top_banner',
        'card_style' => $row['card_style'] ?? 'classic',
        'banner_image' => $row['banner_image'] ?: '',
        'mobile_banner_image' => $row['mobile_banner_image'] ?: '',
        'badge_text' => $row['badge_text'] ?: '',
        'heading' => $row['heading'] ?: '',
        'subheading' => $row['subheading'] ?: '',
        'body_text' => $row['body_text'] ?: '',
        'coupon_code' => $codeUpper,
        'coupon_note' => $row['coupon_note'] ?: '',
        'auto_apply_coupon' => (bool)$row['auto_apply_coupon'],
        'allow_dont_show_again' => isset($row['allow_dont_show_again']) ? (bool)$row['allow_dont_show_again'] : true,
        'dont_show_label' => trim((string)($row['dont_show_label'] ?? '')) ?: "Don't show this again",
        'coupon_meta' => $couponMeta,
        'cta_label' => $row['cta_label'] ?: '',
        'cta_url' => $row['cta_url'] ?: '',
        'theme_preset' => $row['theme_preset'] ?: 'ivory_gold',
        'bg_color' => $row['bg_color'] ?: '#FAF7F3',
        'secondary_color' => $row['secondary_color'] ?? '#EFE6D5',
        'text_color' => $row['text_color'] ?: '#18181B',
        'accent_color' => $row['accent_color'] ?: '#B08D57',
        'target_pages' => array_values($pages),
        'target_category_slugs' => array_values($catSlugs),
        'custom_url_patterns' => $customPatterns,
        'trigger_type' => $row['trigger_type'] ?: 'delay',
        'trigger_delay_sec' => max(0, (int)$row['trigger_delay_sec']),
        'trigger_scroll_pct' => max(0, min(100, (int)$row['trigger_scroll_pct'])),
        'frequency' => $row['frequency'] ?: 'once_per_session',
        'priority' => (int)$row['priority'],
        'updated_at' => $row['updated_at'] ?? '',
    ];
}

/**
 * Fetches all currently active & in-schedule offers for the storefront API.
 */
function get_active_offers_payload($pdo) {
    ensure_offer_popups_table($pdo);

    $stmt = $pdo->query("
        SELECT *
        FROM offer_popups
        WHERE is_active = 1
          AND (start_at IS NULL OR start_at <= NOW())
          AND (end_at IS NULL OR end_at >= NOW())
        ORDER BY priority DESC, updated_at DESC, id DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        return [];
    }

    // Attach optional metadata for matching active coupon codes
    $codes = [];
    foreach ($rows as $r) {
        $c = strtoupper(trim((string)($r['coupon_code'] ?? '')));
        if ($c !== '') $codes[] = $c;
    }

    $couponMap = [];
    if (!empty($codes)) {
        $uniqueCodes = array_values(array_unique($codes));
        $placeholders = implode(',', array_fill(0, count($uniqueCodes), '?'));
        try {
            $cStmt = $pdo->prepare("SELECT code, discount_type, coupon_amount, minimum_amount, expiry_date, status FROM coupons WHERE UPPER(code) IN ($placeholders)");
            $cStmt->execute($uniqueCodes);
            foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $cp) {
                $couponMap[strtoupper($cp['code'])] = [
                    'discount_type' => $cp['discount_type'],
                    'coupon_amount' => (float)$cp['coupon_amount'],
                    'minimum_amount' => $cp['minimum_amount'] !== null ? (float)$cp['minimum_amount'] : null,
                    'expiry_date' => $cp['expiry_date'],
                    'status' => $cp['status'],
                ];
            }
        } catch (PDOException $e) {}
    }

    $result = [];
    foreach ($rows as $row) {
        $result[] = normalize_offer_row($row, $couponMap);
    }
    return $result;
}
