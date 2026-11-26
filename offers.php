<?php
// admin/offers.php — Dynamic Offer Popups, 54 Design Templates & Website Simulator
$page_title = "Offer Popups & Modals";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/offers_helper.php';
require_once __DIR__ . '/includes/offer_templates.php';
require_once __DIR__ . '/includes/functions.php';

ensure_offer_popups_table($pdo);

// -------------------------------------------------------------------------
// 1. AJAX ENDPOINT: One-Click Push Active / Pause Toggle
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    require_once __DIR__ . '/includes/auth.php';
    header('Content-Type: application/json');

    $offerId = (int)($_POST['id'] ?? 0);
    $isActive = (int)($_POST['is_active'] ?? 0) ? 1 : 0;

    if ($offerId > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE offer_popups SET is_active = ? WHERE id = ?");
            $stmt->execute([$isActive, $offerId]);
            log_activity($pdo, 'toggle_offer_popup', 'offer_popup', $offerId, ($isActive ? 'Activated' : 'Paused') . " offer popup ID $offerId");
            purge_cache();
            echo json_encode(['success' => true, 'is_active' => $isActive]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Invalid offer ID.']);
    exit;
}

require_once __DIR__ . '/includes/auth.php';

$viewAction = $_GET['action'] ?? '';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$isEditorMode = ($viewAction === 'create' || $editId > 0);

$cardStyles = offer_card_style_options();
$allowedLayouts = ['top_banner', 'split_left', 'split_right', 'hero_overlay', 'banner_only', 'text_card'];

// -------------------------------------------------------------------------
// 2. FORM POST HANDLERS (Save / Duplicate / Delete)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save_offer') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $title = trim($_POST['heading'] ?? '') ?: 'Promotional Offer #' . date('His');
        }

        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $templateId = trim($_POST['template_id'] ?? '');
        $layoutType = $_POST['layout_type'] ?? 'top_banner';
        if (!in_array($layoutType, $allowedLayouts, true)) {
            $layoutType = 'top_banner';
        }
        $cardStyle = $_POST['card_style'] ?? 'classic';
        if (!isset($cardStyles[$cardStyle])) {
            $cardStyle = 'classic';
        }

        $badgeText = trim($_POST['badge_text'] ?? '');
        $heading = trim($_POST['heading'] ?? '');
        $subheading = trim($_POST['subheading'] ?? '');
        $bodyText = trim($_POST['body_text'] ?? '');
        $couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $couponNote = trim($_POST['coupon_note'] ?? '');
        $autoApplyCoupon = isset($_POST['auto_apply_coupon']) ? 1 : 0;
        $allowDontShowAgain = isset($_POST['allow_dont_show_again']) ? 1 : 0;
        $dontShowLabel = trim($_POST['dont_show_label'] ?? '') ?: "Don't show this again";
        $ctaLabel = trim($_POST['cta_label'] ?? '');
        $ctaUrl = trim($_POST['cta_url'] ?? '');

        $themePreset = $_POST['theme_preset'] ?? 'ivory_gold';
        $presets = offer_theme_presets();
        if (!isset($presets[$themePreset])) {
            $themePreset = 'custom';
        }
        $bgColor = preg_match('/^#[0-9A-Fa-f]{3,8}$/', $_POST['bg_color'] ?? '') ? $_POST['bg_color'] : $presets[$themePreset]['bg'];
        $secondaryColor = preg_match('/^#[0-9A-Fa-f]{3,8}$/', $_POST['secondary_color'] ?? '') ? $_POST['secondary_color'] : ($presets[$themePreset]['secondary'] ?? '#EFE6D5');
        $textColor = preg_match('/^#[0-9A-Fa-f]{3,8}$/', $_POST['text_color'] ?? '') ? $_POST['text_color'] : $presets[$themePreset]['text'];
        $accentColor = preg_match('/^#[0-9A-Fa-f]{3,8}$/', $_POST['accent_color'] ?? '') ? $_POST['accent_color'] : $presets[$themePreset]['accent'];

        // Page targeting
        $allowedPages = array_keys(offer_target_page_options());
        $postedPages = isset($_POST['target_pages']) && is_array($_POST['target_pages']) ? $_POST['target_pages'] : ['all'];
        $cleanPages = array_values(array_intersect($postedPages, $allowedPages));
        if (empty($cleanPages)) {
            $cleanPages = ['home'];
        }
        if (in_array('all', $cleanPages, true)) {
            $cleanPages = ['all'];
        }

        $postedCats = isset($_POST['target_category_slugs']) && is_array($_POST['target_category_slugs'])
            ? array_values(array_filter(array_map('trim', $_POST['target_category_slugs'])))
            : [];
        $customPatterns = trim($_POST['custom_url_patterns'] ?? '');

        $triggerType = $_POST['trigger_type'] ?? 'delay';
        if (!in_array($triggerType, ['delay', 'scroll', 'exit_intent'], true)) {
            $triggerType = 'delay';
        }
        $triggerDelay = max(0, (int)($_POST['trigger_delay_sec'] ?? 2));
        $triggerScroll = max(0, min(100, (int)($_POST['trigger_scroll_pct'] ?? 35)));
        $frequency = $_POST['frequency'] ?? 'once_per_session';
        if (!in_array($frequency, ['once_per_session', 'once_per_day', 'once_per_week', 'every_load'], true)) {
            $frequency = 'once_per_session';
        }

        $startAtRaw = trim($_POST['start_at'] ?? '');
        $endAtRaw = trim($_POST['end_at'] ?? '');
        $startAt = $startAtRaw !== '' ? date('Y-m-d H:i:s', strtotime($startAtRaw)) : null;
        $endAt = $endAtRaw !== '' ? date('Y-m-d H:i:s', strtotime($endAtRaw)) : null;
        $priority = (int)($_POST['priority'] ?? 10);

        // Banner Images Handling (File upload > URL field > existing)
        $bannerImage = trim($_POST['existing_banner_image'] ?? '');
        if (!empty($_POST['remove_banner_image'])) {
            $bannerImage = '';
        }
        $urlOverride = trim($_POST['banner_image_url'] ?? '');
        if ($urlOverride !== '' && empty($_POST['remove_banner_image'])) {
            $bannerImage = $urlOverride;
        }
        if (isset($_FILES['banner_image_file']) && $_FILES['banner_image_file']['error'] === UPLOAD_ERR_OK) {
            $up = upload_offer_banner($_FILES['banner_image_file'], 'desktop');
            if (is_array($up) && isset($up['filepath'])) {
                $bannerImage = $up['filepath'];
            } elseif (is_array($up) && isset($up['error'])) {
                $_SESSION['flash_message'] = $up['error'];
                $_SESSION['flash_type'] = 'error';
                header("Location: offers.php?" . ($id > 0 ? "edit=$id" : "action=create"));
                exit;
            }
        }

        $mobileBannerImage = trim($_POST['existing_mobile_banner_image'] ?? '');
        if (!empty($_POST['remove_mobile_banner_image'])) {
            $mobileBannerImage = '';
        }
        if (isset($_FILES['mobile_banner_image_file']) && $_FILES['mobile_banner_image_file']['error'] === UPLOAD_ERR_OK) {
            $upMob = upload_offer_banner($_FILES['mobile_banner_image_file'], 'mobile');
            if (is_array($upMob) && isset($upMob['filepath'])) {
                $mobileBannerImage = $upMob['filepath'];
            }
        }

        try {
            if ($id > 0) {
                $sql = "UPDATE offer_popups SET
                    title = ?, is_active = ?, template_id = ?, layout_type = ?, card_style = ?,
                    banner_image = ?, mobile_banner_image = ?,
                    badge_text = ?, heading = ?, subheading = ?, body_text = ?,
                    coupon_code = ?, coupon_note = ?, auto_apply_coupon = ?, allow_dont_show_again = ?, dont_show_label = ?,
                    cta_label = ?, cta_url = ?, theme_preset = ?, bg_color = ?, secondary_color = ?, text_color = ?, accent_color = ?,
                    target_pages = ?, target_category_slugs = ?, custom_url_patterns = ?,
                    trigger_type = ?, trigger_delay_sec = ?, trigger_scroll_pct = ?, frequency = ?,
                    start_at = ?, end_at = ?, priority = ?
                    WHERE id = ?";
                $pdo->prepare($sql)->execute([
                    $title, $isActive, $templateId ?: null, $layoutType, $cardStyle,
                    $bannerImage ?: null, $mobileBannerImage ?: null,
                    $badgeText ?: null, $heading ?: null, $subheading ?: null, $bodyText ?: null,
                    $couponCode ?: null, $couponNote ?: null, $autoApplyCoupon, $allowDontShowAgain, $dontShowLabel,
                    $ctaLabel ?: null, $ctaUrl ?: null, $themePreset, $bgColor, $secondaryColor, $textColor, $accentColor,
                    json_encode($cleanPages), json_encode($postedCats), $customPatterns ?: null,
                    $triggerType, $triggerDelay, $triggerScroll, $frequency,
                    $startAt, $endAt, $priority, $id
                ]);
                log_activity($pdo, 'update_offer_popup', 'offer_popup', $id, "Updated offer popup: $title");
                $_SESSION['flash_message'] = "Offer modal <strong>" . htmlspecialchars($title) . "</strong> updated" . ($isActive ? " and pushed LIVE to website." : " (saved as paused).");
            } else {
                $sql = "INSERT INTO offer_popups (
                    title, is_active, template_id, layout_type, card_style,
                    banner_image, mobile_banner_image,
                    badge_text, heading, subheading, body_text,
                    coupon_code, coupon_note, auto_apply_coupon, allow_dont_show_again, dont_show_label,
                    cta_label, cta_url, theme_preset, bg_color, secondary_color, text_color, accent_color,
                    target_pages, target_category_slugs, custom_url_patterns,
                    trigger_type, trigger_delay_sec, trigger_scroll_pct, frequency,
                    start_at, end_at, priority
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([
                    $title, $isActive, $templateId ?: null, $layoutType, $cardStyle,
                    $bannerImage ?: null, $mobileBannerImage ?: null,
                    $badgeText ?: null, $heading ?: null, $subheading ?: null, $bodyText ?: null,
                    $couponCode ?: null, $couponNote ?: null, $autoApplyCoupon, $allowDontShowAgain, $dontShowLabel,
                    $ctaLabel ?: null, $ctaUrl ?: null, $themePreset, $bgColor, $secondaryColor, $textColor, $accentColor,
                    json_encode($cleanPages), json_encode($postedCats), $customPatterns ?: null,
                    $triggerType, $triggerDelay, $triggerScroll, $frequency,
                    $startAt, $endAt, $priority
                ]);
                $id = (int)$pdo->lastInsertId();
                log_activity($pdo, 'create_offer_popup', 'offer_popup', $id, "Created offer popup: $title");
                $_SESSION['flash_message'] = "Offer modal <strong>" . htmlspecialchars($title) . "</strong> created" . ($isActive ? " and pushed LIVE to website." : " (saved as draft/paused).");
            }

            $_SESSION['flash_type'] = 'success';
            purge_cache();
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            header("Location: offers.php?" . ($id > 0 ? "edit=$id" : "action=create"));
            exit;
        }

        header("Location: offers.php");
        exit;
    }

    if ($postAction === 'duplicate') {
        $srcId = (int)($_POST['id'] ?? 0);
        if ($srcId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM offer_popups WHERE id = ?");
            $stmt->execute([$srcId]);
            $src = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($src) {
                $ins = $pdo->prepare("INSERT INTO offer_popups (
                    title, is_active, template_id, layout_type, card_style,
                    banner_image, mobile_banner_image,
                    badge_text, heading, subheading, body_text,
                    coupon_code, coupon_note, auto_apply_coupon, allow_dont_show_again, dont_show_label,
                    cta_label, cta_url, theme_preset, bg_color, secondary_color, text_color, accent_color,
                    target_pages, target_category_slugs, custom_url_patterns,
                    trigger_type, trigger_delay_sec, trigger_scroll_pct, frequency,
                    start_at, end_at, priority
                ) VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([
                    $src['title'] . ' (Copy)', $src['template_id'] ?? null, $src['layout_type'], $src['card_style'] ?? 'classic',
                    $src['banner_image'], $src['mobile_banner_image'],
                    $src['badge_text'], $src['heading'], $src['subheading'], $src['body_text'],
                    $src['coupon_code'], $src['coupon_note'], $src['auto_apply_coupon'],
                    $src['allow_dont_show_again'] ?? 1, $src['dont_show_label'] ?? "Don't show this again",
                    $src['cta_label'], $src['cta_url'], $src['theme_preset'],
                    $src['bg_color'], $src['secondary_color'] ?? '#EFE6D5', $src['text_color'], $src['accent_color'],
                    $src['target_pages'], $src['target_category_slugs'], $src['custom_url_patterns'],
                    $src['trigger_type'], $src['trigger_delay_sec'], $src['trigger_scroll_pct'], $src['frequency'],
                    $src['start_at'], $src['end_at'], $src['priority']
                ]);
                purge_cache();
                $_SESSION['flash_message'] = "Offer duplicated (created paused copy).";
                $_SESSION['flash_type'] = 'success';
            }
        }
        header("Location: offers.php");
        exit;
    }

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId > 0) {
            $stmt = $pdo->prepare("SELECT title, banner_image, mobile_banner_image FROM offer_popups WHERE id = ?");
            $stmt->execute([$delId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $pdo->prepare("DELETE FROM offer_popups WHERE id = ?")->execute([$delId]);

            if ($row) {
                foreach (['banner_image', 'mobile_banner_image'] as $imgCol) {
                    $path = $row[$imgCol] ?? '';
                    if ($path && strpos($path, 'uploads/offers/') === 0) {
                        $refStmt = $pdo->prepare("SELECT COUNT(*) FROM offer_popups WHERE banner_image = ? OR mobile_banner_image = ?");
                        $refStmt->execute([$path, $path]);
                        if ((int)$refStmt->fetchColumn() === 0) {
                            $abs = __DIR__ . '/../' . $path;
                            if (file_exists($abs)) @unlink($abs);
                        }
                    }
                }
                log_activity($pdo, 'delete_offer_popup', 'offer_popup', $delId, "Deleted offer popup: " . $row['title']);
            }
            purge_cache();
            $_SESSION['flash_message'] = "Offer popup deleted.";
            $_SESSION['flash_type'] = 'success';
        }
        header("Location: offers.php");
        exit;
    }
}

// -------------------------------------------------------------------------
// 3. FETCH DATA FOR LIST OR EDITOR
// -------------------------------------------------------------------------
$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$pageOptions = offer_target_page_options();
$themePresets = offer_theme_presets();
$designTemplates = get_offer_design_templates();

$editingOffer = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM offer_popups WHERE id = ?");
    $stmt->execute([$editId]);
    $editingOffer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editingOffer) {
        header("Location: offers.php");
        exit;
    }
}

$availableCoupons = [];
$availableCategories = [];
if ($isEditorMode) {
    try {
        $availableCoupons = $pdo->query("SELECT code, discount_type, coupon_amount, minimum_amount, status FROM coupons ORDER BY status ASC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
    try {
        $catRaw = $pdo->query("SELECT id, name, slug, parent_id FROM categories WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $availableCategories = get_category_tree($catRaw);
    } catch (PDOException $e) {}
}

$offersList = [];
$totalOffers = 0;
$activeOffers = 0;
$totalViews = 0;
$totalClicks = 0;
if (!$isEditorMode) {
    $offersList = $pdo->query("SELECT * FROM offer_popups ORDER BY is_active DESC, priority DESC, updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $totalOffers = count($offersList);
    foreach ($offersList as $of) {
        if ((int)$of['is_active'] === 1) $activeOffers++;
        $totalViews += (int)$of['view_count'];
        $totalClicks += (int)$of['click_count'];
    }
}

// Default sample image URL for template previews & website simulation when no banner uploaded yet
$defaultSampleBanner = 'uploads/offers/atelier_welcome_test.jpg';
if (!file_exists(__DIR__ . '/../' . $defaultSampleBanner)) {
    $defaultSampleBanner = '';
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$webBaseUrl = $isProduction ? 'https://yosshitaneha.com' : 'http://localhost:5173';
?>

<style>
.offer-switch {
    position: relative;
    display: inline-block;
    width: 38px;
    height: 21px;
    vertical-align: middle;
}
.offer-switch input { opacity: 0; width: 0; height: 0; }
.offer-switch-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: #d4d4d8;
    transition: .2s;
    border-radius: 999px;
}
.offer-switch-slider:before {
    position: absolute;
    content: "";
    height: 15px;
    width: 15px;
    left: 3px;
    bottom: 3px;
    background-color: #ffffff;
    transition: .2s;
    border-radius: 50%;
    box-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
.offer-switch input:checked + .offer-switch-slider { background-color: #10b981; }
.offer-switch input:checked + .offer-switch-slider:before { transform: translateX(17px); }

.layout-radio-card, .style-chip-card {
    border: 1.5px solid #e4e4e7;
    border-radius: 8px;
    padding: 9px 11px;
    cursor: pointer;
    background: #fff;
    transition: all 0.14s ease;
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.layout-radio-card:hover, .style-chip-card:hover { border-color: #a1a1aa; }
.layout-radio-card.selected, .style-chip-card.selected {
    border-color: #09090b;
    background: #fafafa;
    box-shadow: 0 0 0 1px #09090b;
}

.page-pill-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    border: 1px solid #e4e4e7;
    border-radius: 6px;
    background: #ffffff;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.12s ease;
    user-select: none;
}
.page-pill-toggle:hover { background: #f4f4f5; }
.page-pill-toggle.checked {
    background: #09090b;
    color: #ffffff;
    border-color: #09090b;
}
.page-pill-toggle.checked .pill-hint { color: #a1a1aa; }

.theme-swatch-btn {
    border: 1.5px solid #e4e4e7;
    border-radius: 8px;
    padding: 7px 10px;
    background: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.14s ease;
}
.theme-swatch-btn.active {
    border-color: #09090b;
    box-shadow: 0 0 0 1px #09090b;
}

/* Builder Dual Grid Above, Full-Width Preview Below */
.offer-builder-two-col {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    align-items: start;
}
@media (max-width: 1024px) {
    .offer-builder-two-col { grid-template-columns: 1fr; }
}

/* Native Dialog Modals in Admin */
dialog.admin-native-dialog:modal {
    overscroll-behavior: contain;
}
dialog.admin-native-dialog::backdrop {
    overflow: hidden;
    overscroll-behavior: contain;
    background: rgba(9, 9, 11, 0.65);
    backdrop-filter: blur(4px);
}

/* Template Gallery Grid with Subgrid Track Alignment */
.template-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(235px, 1fr));
    gap: 14px;
}
.template-card-item {
    border: 1.5px solid #e4e4e7;
    border-radius: 10px;
    background: #ffffff;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    cursor: pointer;
    transition: transform 0.14s ease, border-color 0.14s ease, box-shadow 0.14s ease;
}
.template-card-item:hover {
    transform: translateY(-2px);
    border-color: #71717a;
    box-shadow: 0 10px 20px -8px rgba(0, 0, 0, 0.12);
}
.template-card-item.active-template {
    border-color: #09090b;
    box-shadow: 0 0 0 2px #09090b;
}

/* Website Simulator Full-Screen Modal */
dialog#websiteSimulationDialog {
    margin: 0;
    padding: 0;
    border: none;
    width: 100%;
    max-width: none;
    height: 100dvh;
    max-height: none;
    background: #FAF7F3;
}
</style>

<div class="dashboard-header-banner" style="margin-bottom: 16px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting">
            <h1><?php echo $isEditorMode ? ($editId > 0 ? 'Edit Offer Popup' : 'Create Offer Popup') : 'Offer Popups & Promotional Modals'; ?></h1>
            <span class="shadcn-badge shadcn-badge-sky" style="font-size: 11px; padding: 2px 8px;">
                <i class="fa-solid fa-swatchbook" style="margin-right: 4px;"></i> <?php echo count($designTemplates); ?> Design Templates
            </span>
        </div>
        <p class="dashboard-subtitle">
            <?php echo $isEditorMode
                ? 'Pick from ' . count($designTemplates) . ' crafted design templates, customize banners/coupons/pages, and inspect a true full-size website preview below.'
                : 'Create custom promotional modal popups with 54+ design templates, banners, coupon codes, and page targeting for web2.'; ?>
        </p>
    </div>
    <div class="dashboard-actions">
        <?php if ($isEditorMode): ?>
            <button type="button" onclick="openTemplateGalleryModal()" class="shadcn-btn shadcn-btn-outline" style="border-color: #b08d57; color: #78350f; background: #fffbeb;">
                <i class="fa-solid fa-swatchbook"></i> Browse <?php echo count($designTemplates); ?> Design Templates
            </button>
            <button type="button" onclick="openWebsiteSimulationFromForm()" class="shadcn-btn shadcn-btn-primary">
                <i class="fa-solid fa-desktop"></i> Show Like Website
            </button>
            <a href="offers.php" class="shadcn-btn shadcn-btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        <?php else: ?>
            <a href="ecommerce.php?tab=coupons" class="shadcn-btn shadcn-btn-outline">
                <i class="fa-solid fa-ticket"></i> Manage Coupons
            </a>
            <a href="offers.php?action=create" class="shadcn-btn shadcn-btn-primary">
                <i class="fa-solid fa-plus"></i> Create Offer From Templates
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($message)): ?>
<div class="notice notice-<?php echo htmlspecialchars($messageType); ?> auto-dismiss">
    <i class="fa-solid fa-circle-info"></i>
    <p><?php echo $message; ?></p>
</div>
<?php endif; ?>

<?php if (!$isEditorMode): ?>
    <!-- =================================================================== -->
    <!-- LIST VIEW -->
    <!-- =================================================================== -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 16px;">
        <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase;">Total Offers</span>
                <i class="fa-solid fa-bullhorn" style="color: #a1a1aa; font-size: 12px;"></i>
            </div>
            <div style="font-size: 18px; font-weight: 700; color: #09090b;"><?php echo number_format($totalOffers); ?></div>
            <div style="font-size: 11px; color: #71717a; margin-top: 2px;">Configured modal campaigns</div>
        </div>

        <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase;">Live on Website</span>
                <i class="fa-solid fa-tower-broadcast" style="color: #10b981; font-size: 12px;"></i>
            </div>
            <div style="font-size: 18px; font-weight: 700; color: #059669;" id="liveOffersCount"><?php echo number_format($activeOffers); ?></div>
            <div style="font-size: 11px; color: #71717a; margin-top: 2px;">Active popup modals</div>
        </div>

        <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase;">Popup Impressions</span>
                <i class="fa-solid fa-eye" style="color: #a1a1aa; font-size: 12px;"></i>
            </div>
            <div style="font-size: 18px; font-weight: 700; color: #09090b;"><?php echo number_format($totalViews); ?></div>
            <div style="font-size: 11px; color: #71717a; margin-top: 2px;">Times displayed to shoppers</div>
        </div>

        <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase;">CTA / Coupon Clicks</span>
                <i class="fa-solid fa-arrow-pointer" style="color: #a1a1aa; font-size: 12px;"></i>
            </div>
            <div style="font-size: 18px; font-weight: 700; color: #09090b;"><?php echo number_format($totalClicks); ?></div>
            <div style="font-size: 11px; color: #71717a; margin-top: 2px;">
                <?php echo $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 1) . '% overall engagement' : 'No views tracked yet'; ?>
            </div>
        </div>
    </div>

    <div class="shadcn-card">
        <div class="shadcn-card-header">
            <h2 class="shadcn-card-title">
                <i class="fa-solid fa-list-check" style="color: #71717a;"></i> Configured Offer Popups
            </h2>
            <span style="font-size: 11.5px; color: #71717a;">
                Toggle <strong>Push Active</strong> to publish immediately, or click <strong>Show Like Website</strong> to preview
            </span>
        </div>
        <div class="shadcn-card-body" style="overflow-x: auto;">
            <table class="shadcn-table">
                <thead>
                    <tr>
                        <th style="width: 72px;">Banner</th>
                        <th>Offer Details &amp; Design Style</th>
                        <th>Coupon Code</th>
                        <th>Target Pages (Where Shown)</th>
                        <th style="width: 110px; text-align: center;">Performance</th>
                        <th style="width: 110px; text-align: center;">Push Active</th>
                        <th style="width: 245px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($offersList)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px 20px;">
                                <div style="max-width: 420px; margin: 0 auto;">
                                    <div style="width: 46px; height: 46px; border-radius: 10px; background: #fffbeb; color: #b45309; border: 1px solid #fde68a; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; margin-bottom: 10px;">
                                        <i class="fa-solid fa-swatchbook"></i>
                                    </div>
                                    <div style="font-weight: 600; font-size: 14px; color: #09090b; margin-bottom: 4px;">No Offer Popups Created Yet</div>
                                    <p style="font-size: 12.5px; color: #71717a; margin-bottom: 14px;">
                                        Start from <?php echo count($designTemplates); ?> designer templates crafted for YosshitaNeha Fashion Studio.
                                    </p>
                                    <a href="offers.php?action=create" class="shadcn-btn shadcn-btn-primary">
                                        <i class="fa-solid fa-plus"></i> Create Offer From Templates
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($offersList as $of): ?>
                            <?php
                            $pages = json_decode($of['target_pages'] ?? '[]', true);
                            if (!is_array($pages) || empty($pages)) $pages = ['all'];
                            $layoutLabels = [
                                'top_banner' => 'Banner + Text',
                                'split_left' => 'Split Left Photo',
                                'split_right' => 'Split Right Photo',
                                'hero_overlay' => 'Hero Backdrop',
                                'banner_only' => 'Poster Only',
                                'text_card' => 'Typography Card',
                            ];
                            $ctr = $of['view_count'] > 0 ? round(($of['click_count'] / $of['view_count']) * 100, 1) : 0;
                            $thumbUrl = !empty($of['banner_image']) ? get_product_image_url($of['banner_image']) : '';
                            $styleName = $cardStyles[$of['card_style'] ?? 'classic'] ?? 'Classic Luxe';
                            ?>
                            <tr>
                                <td>
                                    <?php if ($thumbUrl): ?>
                                        <img src="<?php echo htmlspecialchars($thumbUrl); ?>" alt="" style="width: 56px; height: 42px; object-fit: cover; border-radius: 6px; border: 1px solid #e4e4e7;">
                                    <?php else: ?>
                                        <div style="width: 56px; height: 42px; border-radius: 6px; border: 1px solid #e4e4e7; background: <?php echo htmlspecialchars($of['bg_color']); ?>; color: <?php echo htmlspecialchars($of['accent_color']); ?>; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                            <i class="fa-solid fa-sparkles"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #09090b; font-size: 13px;">
                                        <?php echo htmlspecialchars($of['title']); ?>
                                    </div>
                                    <?php if (!empty($of['heading'])): ?>
                                        <div style="font-size: 11.5px; color: #52525b; margin-top: 1px;">
                                            <?php echo htmlspecialchars($of['heading']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="font-size: 11px; color: #71717a; margin-top: 3px; display: flex; gap: 6px; flex-wrap: wrap;">
                                        <span><?php echo htmlspecialchars($layoutLabels[$of['layout_type']] ?? $of['layout_type']); ?></span>
                                        <span>&bull;</span>
                                        <span><?php echo htmlspecialchars($styleName); ?></span>
                                        <span>&bull;</span>
                                        <span>Priority #<?php echo (int)$of['priority']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($of['coupon_code'])): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 5px; font-family: monospace; font-size: 11.5px; font-weight: 700; padding: 3px 8px; border-radius: 5px; background: #f4f4f5; border: 1px dashed #a1a1aa; color: #09090b;">
                                            <i class="fa-solid fa-ticket" style="color: #71717a; font-size: 10px;"></i>
                                            <?php echo htmlspecialchars($of['coupon_code']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 11.5px; color: #a1a1aa;">No coupon</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; flex-wrap: wrap; gap: 4px; max-width: 250px;">
                                        <?php foreach ($pages as $pg): ?>
                                            <span style="font-size: 10.5px; font-weight: 500; padding: 2px 7px; border-radius: 4px; background: #f4f4f5; color: #3f3f46; border: 1px solid #e4e4e7;">
                                                <?php echo htmlspecialchars($pageOptions[$pg]['label'] ?? ucfirst($pg)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <div style="font-size: 12px; font-weight: 600; color: #09090b;">
                                        <?php echo number_format($of['view_count']); ?> <span style="font-weight: 400; color: #71717a; font-size: 11px;">views</span>
                                    </div>
                                    <div style="font-size: 11px; color: #71717a;">
                                        <?php echo number_format($of['click_count']); ?> clicks (<?php echo $ctr; ?>%)
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: inline-flex; flex-direction: column; align-items: center; gap: 4px;">
                                        <label class="offer-switch" title="Push Active or Pause immediately">
                                            <input type="checkbox"
                                                   <?php echo ((int)$of['is_active'] === 1) ? 'checked' : ''; ?>
                                                   onchange="toggleOfferStatus(<?php echo (int)$of['id']; ?>, this)">
                                            <span class="offer-switch-slider"></span>
                                        </label>
                                        <span id="status-badge-<?php echo (int)$of['id']; ?>" style="font-size: 10px; font-weight: 600; letter-spacing: 0.03em; color: <?php echo ((int)$of['is_active'] === 1) ? '#059669' : '#71717a'; ?>;">
                                            <?php echo ((int)$of['is_active'] === 1) ? 'LIVE' : 'PAUSED'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; align-items: center; gap: 5px;">
                                        <button type="button"
                                                onclick='openWebsiteSimulationModal(<?php echo json_encode(normalize_offer_row($of), JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                                class="shadcn-btn shadcn-btn-outline" style="height: 28px; padding: 0 9px; font-size: 11px; border-color: #b08d57; color: #78350f; background: #fffbeb;" title="Show Like Website">
                                            <i class="fa-solid fa-desktop"></i> Like Site
                                        </button>
                                        <a href="offers.php?edit=<?php echo (int)$of['id']; ?>" class="shadcn-btn shadcn-btn-outline" style="height: 28px; padding: 0 9px; font-size: 11.5px;">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </a>
                                        <form method="POST" action="offers.php" style="display: inline; margin: 0;">
                                            <input type="hidden" name="action" value="duplicate">
                                            <input type="hidden" name="id" value="<?php echo (int)$of['id']; ?>">
                                            <button type="submit" class="shadcn-btn shadcn-btn-outline" style="height: 28px; padding: 0 8px; font-size: 11.5px;" title="Duplicate Offer">
                                                <i class="fa-regular fa-copy"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="offers.php" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this offer popup permanently?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$of['id']; ?>">
                                            <button type="submit" class="shadcn-btn shadcn-btn-danger" style="height: 28px; padding: 0 8px; font-size: 11.5px;" title="Delete Offer">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- =================================================================== -->
    <!-- CREATE / EDIT BUILDER WITH 54 TEMPLATES & FULL-WIDTH BOTTOM PREVIEW -->
    <!-- =================================================================== -->
    <?php
    $f = $editingOffer ?: [
        'id' => 0,
        'title' => '',
        'is_active' => 1,
        'template_id' => 'welc_first_10',
        'layout_type' => 'top_banner',
        'card_style' => 'classic',
        'banner_image' => '',
        'mobile_banner_image' => '',
        'badge_text' => 'EXCLUSIVE OFFER',
        'heading' => '',
        'subheading' => '',
        'body_text' => '',
        'coupon_code' => '',
        'coupon_note' => 'Apply code at checkout',
        'auto_apply_coupon' => 1,
        'allow_dont_show_again' => 1,
        'dont_show_label' => "Don't show this again",
        'cta_label' => 'Shop Collection',
        'cta_url' => '/collections/necklace-sets',
        'theme_preset' => 'ivory_gold',
        'bg_color' => '#FAF7F3',
        'secondary_color' => '#EFE6D5',
        'text_color' => '#18181B',
        'accent_color' => '#B08D57',
        'target_pages' => '["home","category","product","cart"]',
        'target_category_slugs' => '[]',
        'custom_url_patterns' => '',
        'trigger_type' => 'delay',
        'trigger_delay_sec' => 2,
        'trigger_scroll_pct' => 35,
        'frequency' => 'once_per_session',
        'start_at' => '',
        'end_at' => '',
        'priority' => 10,
    ];
    $selectedPages = json_decode($f['target_pages'] ?? '["home"]', true);
    if (!is_array($selectedPages)) $selectedPages = ['home'];
    $selectedCatSlugs = json_decode($f['target_category_slugs'] ?? '[]', true);
    if (!is_array($selectedCatSlugs)) $selectedCatSlugs = [];
    $existingBannerSrc = !empty($f['banner_image']) ? get_product_image_url($f['banner_image']) : '';
    $existingMobileSrc = !empty($f['mobile_banner_image']) ? get_product_image_url($f['mobile_banner_image']) : '';
    ?>

    <form method="POST" action="offers.php" enctype="multipart/form-data" id="offerBuilderForm">
        <input type="hidden" name="action" value="save_offer">
        <input type="hidden" name="id" value="<?php echo (int)$f['id']; ?>">
        <input type="hidden" name="template_id" id="template_id" value="<?php echo htmlspecialchars($f['template_id'] ?? ''); ?>">
        <input type="hidden" name="existing_banner_image" id="existing_banner_image" value="<?php echo htmlspecialchars($f['banner_image'] ?? ''); ?>">
        <input type="hidden" name="existing_mobile_banner_image" id="existing_mobile_banner_image" value="<?php echo htmlspecialchars($f['mobile_banner_image'] ?? ''); ?>">

        <!-- TOP TEMPLATE PICKER + PUBLISH STATUS BAR -->
        <div class="shadcn-card" style="margin-bottom: 16px; border-color: #d4d4d8;">
            <div class="shadcn-card-padded" style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 14px 18px;">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <button type="button" onclick="openTemplateGalleryModal()" class="shadcn-btn" style="height: 36px; padding: 0 14px; background: #fffbeb; border-color: #d97706; color: #92400e; font-weight: 600;">
                        <i class="fa-solid fa-swatchbook"></i> Choose From <?php echo count($designTemplates); ?> Design Templates
                    </button>
                    <div>
                        <div style="font-size: 11px; color: #71717a; text-transform: uppercase; font-weight: 600; letter-spacing: 0.03em;">Active Template</div>
                        <div id="activeTemplateNameLabel" style="font-size: 13px; font-weight: 600; color: #09090b;">
                            <?php
                            $foundTplName = 'Custom Configuration';
                            foreach ($designTemplates as $dt) {
                                if ($dt['id'] === ($f['template_id'] ?? '')) {
                                    $foundTplName = $dt['name'] . ' (' . $dt['category'] . ')';
                                    break;
                                }
                            }
                            echo htmlspecialchars($foundTplName);
                            ?>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 8px; background: #fafafa; border: 1px solid #e4e4e7; cursor: pointer;">
                        <span style="font-size: 12px; font-weight: 600; color: #09090b;">Push Active Live</span>
                        <span class="offer-switch">
                            <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo ((int)$f['is_active'] === 1) ? 'checked' : ''; ?>>
                            <span class="offer-switch-slider"></span>
                        </span>
                    </label>

                    <button type="submit" class="shadcn-btn shadcn-btn-primary" style="height: 36px; padding: 0 18px; font-weight: 600;">
                        <i class="fa-solid fa-floppy-disk"></i> <?php echo $editId > 0 ? 'Update Offer Popup' : 'Save & Publish Offer'; ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- SETTINGS GRID (2 Balanced Columns, Full-Width Bottom Preview Below) -->
        <div class="offer-builder-two-col" style="margin-bottom: 16px;">

            <!-- LEFT SETTINGS COLUMN -->
            <div style="display: flex; flex-direction: column; gap: 16px;">

                <!-- Card 1: Offer Name, Layout & Card Style -->
                <div class="shadcn-card" style="margin-bottom: 0;">
                    <div class="shadcn-card-header">
                        <h2 class="shadcn-card-title"><i class="fa-solid fa-layer-group" style="color: #71717a;"></i> 1. Offer Name, Layout &amp; Frame Style</h2>
                    </div>
                    <div class="shadcn-card-padded">
                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="offer_title">Internal Offer Name *</label>
                            <input type="text" id="offer_title" name="title" value="<?php echo htmlspecialchars($f['title'] ?? ''); ?>" required placeholder="e.g. Diwali Festive 15% Off Bridal Edit" class="form-control">
                        </div>

                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 8px;">Modal Layout Architecture (6 Layouts)</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(145px, 1fr)); gap: 8px; margin-bottom: 16px;">
                            <?php
                            $layouts = [
                                'top_banner'   => ['title' => 'Top Banner + Text', 'desc' => 'Wide image top, text below', 'icon' => 'fa-table-rows'],
                                'split_left'   => ['title' => 'Split Photo Left', 'desc' => 'Image left, copy right', 'icon' => 'fa-columns'],
                                'split_right'  => ['title' => 'Split Photo Right', 'desc' => 'Copy left, image right', 'icon' => 'fa-table-columns'],
                                'hero_overlay' => ['title' => 'Hero Overlay', 'desc' => 'Text over full photo', 'icon' => 'fa-wand-magic-sparkles'],
                                'banner_only'  => ['title' => 'Poster Banner Only', 'desc' => 'Clickable image poster', 'icon' => 'fa-image'],
                                'text_card'    => ['title' => 'Typography Card', 'desc' => 'Text & coupon, no image', 'icon' => 'fa-align-center'],
                            ];
                            foreach ($layouts as $lKey => $lInfo):
                                $isSel = ($f['layout_type'] === $lKey);
                            ?>
                                <label class="layout-radio-card <?php echo $isSel ? 'selected' : ''; ?>" data-layout-card="<?php echo $lKey; ?>">
                                    <input type="radio" name="layout_type" value="<?php echo $lKey; ?>" <?php echo $isSel ? 'checked' : ''; ?> style="display: none;" onchange="onLayoutChange('<?php echo $lKey; ?>')">
                                    <div style="display: flex; align-items: center; gap: 6px; font-weight: 600; font-size: 12px; color: #09090b;">
                                        <i class="fa-solid <?php echo $lInfo['icon']; ?>" style="color: #71717a;"></i>
                                        <span><?php echo $lInfo['title']; ?></span>
                                    </div>
                                    <div style="font-size: 10.5px; color: #71717a;"><?php echo $lInfo['desc']; ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 8px;">Visual Card Frame Treatment (10 Styles)</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(135px, 1fr)); gap: 7px;">
                            <?php foreach ($cardStyles as $sKey => $sLabel): ?>
                                <?php $isStyleSel = (($f['card_style'] ?? 'classic') === $sKey); ?>
                                <label class="style-chip-card <?php echo $isStyleSel ? 'selected' : ''; ?>" data-style-card="<?php echo htmlspecialchars($sKey); ?>" style="padding: 7px 9px;">
                                    <input type="radio" name="card_style" value="<?php echo htmlspecialchars($sKey); ?>" <?php echo $isStyleSel ? 'checked' : ''; ?> style="display: none;" onchange="onCardStyleChange('<?php echo htmlspecialchars($sKey); ?>')">
                                    <span style="font-size: 11.5px; font-weight: 600; color: #09090b;"><?php echo htmlspecialchars($sLabel); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Images / Banners & Custom Text Content -->
                <div class="shadcn-card" style="margin-bottom: 0;">
                    <div class="shadcn-card-header">
                        <h2 class="shadcn-card-title"><i class="fa-solid fa-image" style="color: #71717a;"></i> 2. Offer Banners &amp; Custom Text Content</h2>
                    </div>
                    <div class="shadcn-card-padded">
                        <div id="bannerUploadSection" style="margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid #f4f4f5;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px;">
                                <div>
                                    <label style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 6px;">Desktop / Main Banner Image</label>
                                    <div class="image-upload-wrapper" style="padding: 12px;">
                                        <input type="file" name="banner_image_file" id="banner_image_file" accept="image/*" onchange="handleBannerFileSelect(this, 'desktop')">
                                        <i class="fa-solid fa-cloud-arrow-up" style="font-size: 18px; margin-bottom: 4px;"></i>
                                        <p style="margin: 0; font-size: 11.5px;">Drop JPG, PNG, WEBP, or GIF banner</p>
                                    </div>
                                    <div style="margin-top: 7px;">
                                        <input type="text" name="banner_image_url" id="banner_image_url" value="<?php echo htmlspecialchars(str_starts_with($f['banner_image'] ?? '', 'http') ? $f['banner_image'] : ''); ?>" placeholder="Or paste image URL..." class="form-control" style="font-size: 12px;" oninput="updatePreview()">
                                    </div>
                                    <div id="desktopBannerActions" style="margin-top: 7px; display: <?php echo $existingBannerSrc ? 'flex' : 'none'; ?>; align-items: center; justify-content: space-between; background: #fafafa; padding: 5px 9px; border-radius: 6px; border: 1px solid #e4e4e7;">
                                        <span style="font-size: 11px; color: #52525b;"><i class="fa-solid fa-check-circle" style="color: #10b981;"></i> Banner attached</span>
                                        <label style="font-size: 11px; color: #ef4444; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; margin: 0;">
                                            <input type="checkbox" name="remove_banner_image" id="remove_banner_image" value="1" onchange="updatePreview()"> Remove
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 6px;">Mobile Banner Override <span style="color: #71717a; font-weight: 400;">(Optional)</span></label>
                                    <div class="image-upload-wrapper" style="padding: 12px;">
                                        <input type="file" name="mobile_banner_image_file" id="mobile_banner_image_file" accept="image/*" onchange="handleBannerFileSelect(this, 'mobile')">
                                        <i class="fa-solid fa-mobile-screen-button" style="font-size: 18px; margin-bottom: 4px;"></i>
                                        <p style="margin: 0; font-size: 11.5px;">Optional vertical/square mobile banner</p>
                                    </div>
                                    <?php if ($existingMobileSrc): ?>
                                        <div style="margin-top: 7px; display: flex; align-items: center; justify-content: space-between; background: #fafafa; padding: 5px 9px; border-radius: 6px; border: 1px solid #e4e4e7;">
                                            <span style="font-size: 11px; color: #52525b;"><i class="fa-solid fa-check-circle" style="color: #10b981;"></i> Mobile banner set</span>
                                            <label style="font-size: 11px; color: #ef4444; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; margin: 0;">
                                                <input type="checkbox" name="remove_mobile_banner_image" value="1"> Remove
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px; margin-bottom: 10px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="badge_text">Eyebrow Tag</label>
                                <input type="text" id="badge_text" name="badge_text" value="<?php echo htmlspecialchars($f['badge_text'] ?? ''); ?>" placeholder="e.g. FESTIVE SPECIAL" class="form-control" oninput="updatePreview()">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="heading">Offer Headline</label>
                                <input type="text" id="heading" name="heading" value="<?php echo htmlspecialchars($f['heading'] ?? ''); ?>" placeholder="e.g. Flat 15% Off Bridal Sets" class="form-control" oninput="updatePreview()">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 10px;">
                            <label for="subheading">Sub-Headline</label>
                            <input type="text" id="subheading" name="subheading" value="<?php echo htmlspecialchars($f['subheading'] ?? ''); ?>" placeholder="e.g. Handcrafted Kundan & Bespoke Couture" class="form-control" oninput="updatePreview()">
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="body_text">Offer Message / Copy</label>
                            <textarea id="body_text" name="body_text" rows="3" placeholder="Custom message, dates, or offer terms..." class="form-control" oninput="updatePreview()"><?php echo htmlspecialchars($f['body_text'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT SETTINGS COLUMN -->
            <div style="display: flex; flex-direction: column; gap: 16px;">

                <!-- Card 3: Coupon Code & CTA -->
                <div class="shadcn-card" style="margin-bottom: 0;">
                    <div class="shadcn-card-header">
                        <h2 class="shadcn-card-title"><i class="fa-solid fa-ticket" style="color: #71717a;"></i> 3. Coupon Code &amp; Call to Action</h2>
                    </div>
                    <div class="shadcn-card-padded">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="coupon_picker">Pick Store Coupon</label>
                                <select id="coupon_picker" class="form-control" onchange="pickStoreCoupon(this)">
                                    <option value="">-- Optional --</option>
                                    <?php foreach ($availableCoupons as $cp): ?>
                                        <?php
                                        $amtText = $cp['discount_type'] === 'percent' ? ((float)$cp['coupon_amount'] . '% OFF') : ('₹' . number_format($cp['coupon_amount'], 0) . ' OFF');
                                        $minText = $cp['minimum_amount'] ? ('Min ₹' . number_format($cp['minimum_amount'], 0)) : 'No min spend';
                                        ?>
                                        <option value="<?php echo htmlspecialchars($cp['code']); ?>" data-note="<?php echo htmlspecialchars($amtText . ' • ' . $minText); ?>">
                                            <?php echo htmlspecialchars($cp['code']); ?> (<?php echo $amtText; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="coupon_code">Coupon Code</label>
                                <input type="text" id="coupon_code" name="coupon_code" value="<?php echo htmlspecialchars($f['coupon_code'] ?? ''); ?>" placeholder="e.g. WELCOME10" class="form-control" style="text-transform: uppercase; font-weight: 700;" oninput="updatePreview()">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 10px; margin-bottom: 12px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="coupon_note">Coupon Helper Note</label>
                                <input type="text" id="coupon_note" name="coupon_note" value="<?php echo htmlspecialchars($f['coupon_note'] ?? ''); ?>" placeholder="e.g. Valid on orders above ₹1,000" class="form-control" oninput="updatePreview()">
                            </div>
                            <label style="display: flex; align-items: center; gap: 6px; font-size: 11.5px; padding-bottom: 6px; cursor: pointer;">
                                <input type="checkbox" name="auto_apply_coupon" id="auto_apply_coupon" value="1" <?php echo !empty($f['auto_apply_coupon']) ? 'checked' : ''; ?>>
                                <span>Auto-apply on click</span>
                            </label>
                        </div>

                        <div style="padding-top: 12px; border-top: 1px solid #f4f4f5; display: grid; grid-template-columns: 1fr 1.6fr; gap: 10px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="cta_label">Action Button Text</label>
                                <input type="text" id="cta_label" name="cta_label" value="<?php echo htmlspecialchars($f['cta_label'] ?? ''); ?>" placeholder="e.g. Shop Now" class="form-control" oninput="updatePreview()">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="cta_url">Button Destination Route</label>
                                <div style="display: flex; gap: 6px;">
                                    <input type="text" id="cta_url" name="cta_url" value="<?php echo htmlspecialchars($f['cta_url'] ?? ''); ?>" placeholder="/collections/necklace-sets" class="form-control" oninput="updatePreview()">
                                    <select class="form-control" style="width: 115px;" onchange="if(this.value){ document.getElementById('cta_url').value = this.value; updatePreview(); }">
                                        <option value="">Presets...</option>
                                        <option value="/">Home (/)</option>
                                        <option value="/collections/jewellery">Jewellery</option>
                                        <option value="/collections/outfit">Outfits</option>
                                        <option value="/collections/necklace-sets">Necklace Sets</option>
                                        <option value="/collections/earrings">Earrings</option>
                                        <option value="/blouse-studio">Blouse Studio</option>
                                        <option value="/cart">Cart</option>
                                        <option value="/checkout">Checkout</option>
                                        <option value="/consultation">Consultation</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Where to Show (Page Targeting) -->
                <div class="shadcn-card" style="margin-bottom: 0;">
                    <div class="shadcn-card-header">
                        <h2 class="shadcn-card-title"><i class="fa-solid fa-location-crosshairs" style="color: #71717a;"></i> 4. Where Should This Popup Show?</h2>
                    </div>
                    <div class="shadcn-card-padded">
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;" id="targetPagesWrap">
                            <?php foreach ($pageOptions as $pgKey => $pgMeta): ?>
                                <?php $isChecked = in_array($pgKey, $selectedPages, true); ?>
                                <label class="page-pill-toggle <?php echo $isChecked ? 'checked' : ''; ?>" data-page-key="<?php echo htmlspecialchars($pgKey); ?>">
                                    <input type="checkbox" name="target_pages[]" value="<?php echo htmlspecialchars($pgKey); ?>" <?php echo $isChecked ? 'checked' : ''; ?> style="display: none;" onchange="handlePageTargetToggle(this)">
                                    <i class="fa-solid <?php echo htmlspecialchars($pgMeta['icon']); ?>"></i>
                                    <span><?php echo htmlspecialchars($pgMeta['label']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div id="categoryFilterBox" style="margin-bottom: 10px; padding: 10px; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px;">
                            <label style="display: block; font-size: 11.5px; font-weight: 600; margin-bottom: 4px;">
                                Limit Collection Pages to Specific Categories <span style="font-weight: 400; color: #71717a;">(Optional)</span>
                            </label>
                            <select name="target_category_slugs[]" id="target_category_slugs" multiple class="form-control" style="height: 82px; font-size: 11.5px;">
                                <?php foreach ($availableCategories as $cat): ?>
                                    <?php $indent = str_repeat('— ', (int)($cat['depth'] ?? 0)); ?>
                                    <option value="<?php echo htmlspecialchars($cat['slug']); ?>" <?php echo in_array($cat['slug'], $selectedCatSlugs, true) ? 'selected' : ''; ?>>
                                        <?php echo $indent . htmlspecialchars($cat['name']) . ' (/' . htmlspecialchars($cat['slug']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="custom_url_patterns">Custom URL Patterns <span style="font-weight: 400; color: #71717a;">(Optional — e.g. /product/kundan-*)</span></label>
                            <input type="text" id="custom_url_patterns" name="custom_url_patterns" value="<?php echo htmlspecialchars($f['custom_url_patterns'] ?? ''); ?>" placeholder="/collections/bridal-sets, /product/kundan-*" class="form-control" style="font-family: monospace; font-size: 11.5px;">
                        </div>
                    </div>
                </div>

                <!-- Card 5: Color Palette, Timing, Schedule & Opt-Out -->
                <div class="shadcn-card" style="margin-bottom: 0;">
                    <div class="shadcn-card-header">
                        <h2 class="shadcn-card-title"><i class="fa-solid fa-palette" style="color: #71717a;"></i> 5. Colors, Triggers &amp; Anti-Irritation Opt-Out</h2>
                    </div>
                    <div class="shadcn-card-padded">
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px;">
                            <?php foreach ($themePresets as $tKey => $tVal): ?>
                                <button type="button"
                                        class="theme-swatch-btn <?php echo ($f['theme_preset'] === $tKey) ? 'active' : ''; ?>"
                                        data-theme="<?php echo $tKey; ?>"
                                        onclick="applyThemePreset('<?php echo $tKey; ?>', '<?php echo $tVal['bg']; ?>', '<?php echo $tVal['secondary']; ?>', '<?php echo $tVal['text']; ?>', '<?php echo $tVal['accent']; ?>')">
                                    <span style="display: inline-flex; width: 16px; height: 16px; border-radius: 3px; overflow: hidden; border: 1px solid #d4d4d8;">
                                        <span style="flex: 1; background: <?php echo $tVal['bg']; ?>;"></span>
                                        <span style="flex: 1; background: <?php echo $tVal['accent']; ?>;"></span>
                                    </span>
                                    <span><?php echo htmlspecialchars($tVal['label']); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="theme_preset" id="theme_preset" value="<?php echo htmlspecialchars($f['theme_preset'] ?? 'ivory_gold'); ?>">

                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #f4f4f5;">
                            <div>
                                <label style="display: block; font-size: 11px; font-weight: 500; margin-bottom: 3px;">Primary BG</label>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <input type="color" name="bg_color" id="bg_color" value="<?php echo htmlspecialchars($f['bg_color'] ?? '#FAF7F3'); ?>" style="width: 30px; height: 26px; border: 1px solid #e4e4e7; border-radius: 4px; padding: 1px;" oninput="onCustomColorInput()">
                                    <span id="bg_hex_label" style="font-family: monospace; font-size: 10.5px; color: #52525b;"><?php echo htmlspecialchars($f['bg_color'] ?? '#FAF7F3'); ?></span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 11px; font-weight: 500; margin-bottom: 3px;">Gradient BG 2</label>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <input type="color" name="secondary_color" id="secondary_color" value="<?php echo htmlspecialchars($f['secondary_color'] ?? '#EFE6D5'); ?>" style="width: 30px; height: 26px; border: 1px solid #e4e4e7; border-radius: 4px; padding: 1px;" oninput="onCustomColorInput()">
                                    <span id="sec_hex_label" style="font-family: monospace; font-size: 10.5px; color: #52525b;"><?php echo htmlspecialchars($f['secondary_color'] ?? '#EFE6D5'); ?></span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 11px; font-weight: 500; margin-bottom: 3px;">Text Color</label>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <input type="color" name="text_color" id="text_color" value="<?php echo htmlspecialchars($f['text_color'] ?? '#18181B'); ?>" style="width: 30px; height: 26px; border: 1px solid #e4e4e7; border-radius: 4px; padding: 1px;" oninput="onCustomColorInput()">
                                    <span id="text_hex_label" style="font-family: monospace; font-size: 10.5px; color: #52525b;"><?php echo htmlspecialchars($f['text_color'] ?? '#18181B'); ?></span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 11px; font-weight: 500; margin-bottom: 3px;">Accent / CTA</label>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <input type="color" name="accent_color" id="accent_color" value="<?php echo htmlspecialchars($f['accent_color'] ?? '#B08D57'); ?>" style="width: 30px; height: 26px; border: 1px solid #e4e4e7; border-radius: 4px; padding: 1px;" oninput="onCustomColorInput()">
                                    <span id="accent_hex_label" style="font-family: monospace; font-size: 10.5px; color: #52525b;"><?php echo htmlspecialchars($f['accent_color'] ?? '#B08D57'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 10px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="trigger_type">Trigger</label>
                                <select id="trigger_type" name="trigger_type" class="form-control" onchange="toggleTriggerInputs()">
                                    <option value="delay" <?php echo ($f['trigger_type'] === 'delay') ? 'selected' : ''; ?>>Time Delay</option>
                                    <option value="scroll" <?php echo ($f['trigger_type'] === 'scroll') ? 'selected' : ''; ?>>Scroll Depth</option>
                                    <option value="exit_intent" <?php echo ($f['trigger_type'] === 'exit_intent') ? 'selected' : ''; ?>>Exit Intent</option>
                                </select>
                            </div>
                            <div class="form-group" id="delayInputWrap" style="margin-bottom: 0;">
                                <label for="trigger_delay_sec">Delay (Sec)</label>
                                <input type="number" min="0" max="120" id="trigger_delay_sec" name="trigger_delay_sec" value="<?php echo (int)($f['trigger_delay_sec'] ?? 2); ?>" class="form-control">
                            </div>
                            <div class="form-group" id="scrollInputWrap" style="margin-bottom: 0; display: none;">
                                <label for="trigger_scroll_pct">Scroll (%)</label>
                                <input type="number" min="5" max="95" id="trigger_scroll_pct" name="trigger_scroll_pct" value="<?php echo (int)($f['trigger_scroll_pct'] ?? 35); ?>" class="form-control">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="frequency">Frequency</label>
                                <select id="frequency" name="frequency" class="form-control">
                                    <option value="once_per_session" <?php echo ($f['frequency'] === 'once_per_session') ? 'selected' : ''; ?>>Once per session</option>
                                    <option value="once_per_day" <?php echo ($f['frequency'] === 'once_per_day') ? 'selected' : ''; ?>>Once per 24h</option>
                                    <option value="once_per_week" <?php echo ($f['frequency'] === 'once_per_week') ? 'selected' : ''; ?>>Once per 7 days</option>
                                    <option value="every_load" <?php echo ($f['frequency'] === 'every_load') ? 'selected' : ''; ?>>Every matching visit</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 0.7fr 1fr 1fr; gap: 8px; margin-bottom: 12px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="priority">Priority</label>
                                <input type="number" id="priority" name="priority" value="<?php echo (int)($f['priority'] ?? 10); ?>" class="form-control">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="start_at">Start Date <span style="color:#71717a;font-weight:400;">(Opt.)</span></label>
                                <input type="datetime-local" id="start_at" name="start_at" value="<?php echo !empty($f['start_at']) ? date('Y-m-d\TH:i', strtotime($f['start_at'])) : ''; ?>" class="form-control" style="font-size: 11px;">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="end_at">End Date <span style="color:#71717a;font-weight:400;">(Opt.)</span></label>
                                <input type="datetime-local" id="end_at" name="end_at" value="<?php echo !empty($f['end_at']) ? date('Y-m-d\TH:i', strtotime($f['end_at'])) : ''; ?>" class="form-control" style="font-size: 11px;">
                            </div>
                        </div>

                        <div style="padding-top: 12px; border-top: 1px solid #f4f4f5; display: grid; grid-template-columns: 1fr 1.2fr; gap: 10px; align-items: end;">
                            <label style="display: flex; align-items: center; gap: 7px; font-size: 12px; padding-bottom: 6px; cursor: pointer; font-weight: 500;">
                                <input type="checkbox" name="allow_dont_show_again" id="allow_dont_show_again" value="1" <?php echo (!isset($f['allow_dont_show_again']) || !empty($f['allow_dont_show_again'])) ? 'checked' : ''; ?> onchange="updatePreview()">
                                <span>Show "Don't show this again"</span>
                            </label>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="dont_show_label">Opt-Out Link Text</label>
                                <input type="text" id="dont_show_label" name="dont_show_label" value="<?php echo htmlspecialchars($f['dont_show_label'] ?? "Don't show this again"); ?>" placeholder="Don't show this again" class="form-control" oninput="updatePreview()">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- =============================================================== -->
        <!-- FULL-WIDTH BOTTOM LIVE MODAL PREVIEW (NO LONGER NARROW/CRAMPED) -->
        <!-- =============================================================== -->
        <div class="shadcn-card" style="margin-bottom: 24px; border: 1.5px solid #d4d4d8;">
            <div class="shadcn-card-header" style="flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h2 class="shadcn-card-title">
                        <i class="fa-solid fa-eye" style="color: #71717a;"></i> Live Modal Preview (Full View)
                    </h2>
                    <span id="previewMetaBadge" class="shadcn-badge shadcn-badge-sky" style="font-size: 11px; padding: 2px 8px;"></span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <div style="display: inline-flex; background: #f4f4f5; padding: 2px; border-radius: 6px; border: 1px solid #e4e4e7;">
                        <button type="button" id="vpDesktopBtn" onclick="setPreviewViewport('desktop')" class="shadcn-btn shadcn-btn-primary" style="height: 26px; font-size: 11.5px; padding: 0 10px;">
                            <i class="fa-solid fa-desktop"></i> Desktop
                        </button>
                        <button type="button" id="vpMobileBtn" onclick="setPreviewViewport('mobile')" class="shadcn-btn shadcn-btn-ghost" style="height: 26px; font-size: 11.5px; padding: 0 10px;">
                            <i class="fa-solid fa-mobile-screen-button"></i> Mobile
                        </button>
                    </div>
                    <button type="button" onclick="openWebsiteSimulationFromForm()" class="shadcn-btn shadcn-btn-primary" style="height: 30px; font-size: 12px; padding: 0 14px;">
                        <i class="fa-solid fa-up-right-from-square"></i> Preview Like Website
                    </button>
                    <button type="submit" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 14px; font-weight: 600;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Offer
                    </button>
                </div>
            </div>

            <div class="shadcn-card-padded" style="background: radial-gradient(circle at top center, #3f3f46 0%, #18181b 100%); padding: 36px 20px; min-height: 440px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                <div id="liveOfferPreviewFrame" style="width: 100%; max-width: 760px; transition: max-width 0.2s ease;">
                    <div id="liveOfferPreview"></div>
                </div>
            </div>
        </div>
    </form>

    <!-- =================================================================== -->
    <!-- DIALOG 1: 54 DESIGN TEMPLATES GALLERY PICKER -->
    <!-- =================================================================== -->
    <dialog id="offerTemplateGalleryDialog" closedby="any" aria-labelledby="tplGalleryTitle" class="admin-native-dialog" style="margin: auto; border: none; border-radius: 14px; padding: 0; max-width: 1140px; width: calc(100% - 28px); max-height: 90dvh; overflow: hidden; background: #ffffff; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.45);">
        <div style="display: flex; flex-direction: column; max-height: 90dvh;">
            <!-- Header -->
            <div style="padding: 16px 20px; border-bottom: 1px solid #e4e4e7; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; background: #fafafa;">
                <div>
                    <h2 id="tplGalleryTitle" style="margin: 0; font-size: 17px; font-weight: 700; color: #09090b;">
                        <i class="fa-solid fa-swatchbook" style="color: #b08d57; margin-right: 6px;"></i> Choose From <?php echo count($designTemplates); ?> Design Templates
                    </h2>
                    <p style="margin: 2px 0 0; font-size: 12px; color: #71717a;">Click any design to apply its layout, frame style, and luxury color palette instantly.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                    <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; cursor: pointer; background: #fff; padding: 6px 10px; border: 1px solid #e4e4e7; border-radius: 6px;">
                        <input type="checkbox" id="applySampleCopyToggle" checked>
                        <span>Also fill sample headline &amp; CTA text</span>
                    </label>
                    <button type="button" onclick="document.getElementById('offerTemplateGalleryDialog').close()" aria-label="Close templates gallery" class="shadcn-btn shadcn-btn-outline" style="height: 32px;">
                        <i class="fa-solid fa-xmark"></i> Close
                    </button>
                </div>
            </div>

            <!-- Filter Bar -->
            <div style="padding: 12px 20px; border-bottom: 1px solid #f4f4f5; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; background: #ffffff;">
                <div id="tplCategoryPills" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <select id="tplLayoutFilter" class="form-control" style="height: 30px; font-size: 12px; width: 145px;" onchange="renderTemplateGalleryGrid()">
                        <option value="">All Layouts</option>
                        <option value="top_banner">Top Banner</option>
                        <option value="split_left">Split Left</option>
                        <option value="split_right">Split Right</option>
                        <option value="hero_overlay">Hero Overlay</option>
                        <option value="banner_only">Poster Banner</option>
                        <option value="text_card">Typography Card</option>
                    </select>
                    <input type="search" id="tplSearchInput" placeholder="Search 54 designs..." class="form-control" style="height: 30px; font-size: 12px; width: 175px;" oninput="renderTemplateGalleryGrid()">
                </div>
            </div>

            <!-- Scrollable Gallery Body -->
            <div style="padding: 18px 20px; overflow-y: auto; background: #f4f4f5; flex: 1;">
                <div id="templateGalleryGrid" class="template-gallery-grid"></div>
            </div>
        </div>
    </dialog>
<?php endif; ?>

<!-- =================================================================== -->
<!-- DIALOG 2: FULL-VIEWPORT "PREVIEW LIKE WEBSITE" SIMULATOR -->
<!-- =================================================================== -->
<dialog id="websiteSimulationDialog" closedby="any" aria-label="Website Modal Simulation Preview" class="admin-native-dialog">
    <div style="display: flex; flex-direction: column; height: 100dvh; overflow: hidden;">
        <!-- Floating Top Simulation Controls -->
        <div style="background: #09090b; color: #ffffff; padding: 10px 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; z-index: 30; border-bottom: 1px solid #27272a;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 12px; font-weight: 700; color: #D4AF37; letter-spacing: 0.08em; text-transform: uppercase;">
                    <i class="fa-solid fa-gem"></i> YosshitaNeha Storefront Preview
                </span>
                <span style="font-size: 11px; color: #a1a1aa; border-left: 1px solid #3f3f46; padding-left: 10px;">
                    Simulating live modal appearance on <strong>web2</strong>
                </span>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <!-- Simulated Website Route Switcher -->
                <div style="display: inline-flex; background: #18181b; border: 1px solid #27272a; border-radius: 6px; padding: 2px;">
                    <button type="button" class="sim-page-btn shadcn-btn" data-sim-page="home" onclick="switchSimulatedWebsitePage('home')" style="height: 26px; font-size: 11px; border: none; background: #B08D57; color: #fff;">Home</button>
                    <button type="button" class="sim-page-btn shadcn-btn" data-sim-page="category" onclick="switchSimulatedWebsitePage('category')" style="height: 26px; font-size: 11px; border: none; background: transparent; color: #d4d4d8;">Collections</button>
                    <button type="button" class="sim-page-btn shadcn-btn" data-sim-page="product" onclick="switchSimulatedWebsitePage('product')" style="height: 26px; font-size: 11px; border: none; background: transparent; color: #d4d4d8;">Product</button>
                    <button type="button" class="sim-page-btn shadcn-btn" data-sim-page="cart" onclick="switchSimulatedWebsitePage('cart')" style="height: 26px; font-size: 11px; border: none; background: transparent; color: #d4d4d8;">Cart</button>
                    <button type="button" class="sim-page-btn shadcn-btn" data-sim-page="checkout" onclick="switchSimulatedWebsitePage('checkout')" style="height: 26px; font-size: 11px; border: none; background: transparent; color: #d4d4d8;">Checkout</button>
                </div>

                <!-- Viewport Switcher -->
                <div style="display: inline-flex; background: #18181b; border: 1px solid #27272a; border-radius: 6px; padding: 2px;">
                    <button type="button" id="simDesktopBtn" onclick="setSimViewport('desktop')" class="shadcn-btn" style="height: 26px; font-size: 11px; border: none; background: #27272a; color: #fff;"><i class="fa-solid fa-desktop"></i></button>
                    <button type="button" id="simMobileBtn" onclick="setSimViewport('mobile')" class="shadcn-btn" style="height: 26px; font-size: 11px; border: none; background: transparent; color: #d4d4d8;"><i class="fa-solid fa-mobile-screen-button"></i></button>
                </div>

                <?php if ($editId > 0): ?>
                    <a href="<?php echo htmlspecialchars($webBaseUrl . '/?preview_offer=' . $editId); ?>" target="_blank" rel="noopener" class="shadcn-btn" style="height: 28px; font-size: 11.5px; background: #18181b; color: #fafafa; border-color: #3f3f46;">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Open on web2
                    </a>
                <?php endif; ?>

                <button type="button" onclick="document.getElementById('websiteSimulationDialog').close()" class="shadcn-btn" style="height: 28px; font-size: 11.5px; background: #ef4444; border-color: #ef4444; color: #fff;">
                    <i class="fa-solid fa-xmark"></i> Exit Website View
                </button>
            </div>
        </div>

        <!-- Viewport Frame Container -->
        <div style="flex: 1; background: #27272a; display: flex; justify-content: center; overflow: hidden; padding: 0;" id="simOuterWrap">
            <div id="simViewportFrame" style="position: relative; width: 100%; height: 100%; background: #FAF7F3; overflow: hidden; transition: width 0.25s ease, border-radius 0.25s ease;">
                <!-- Mock Storefront Page Background -->
                <div id="simWebsiteBg" style="position: absolute; inset: 0; overflow-y: auto; pointer-events: none; user-select: none;"></div>

                <!-- Backdrop Blur Layer & Interactive Modal Overlay -->
                <div id="simBackdropOverlay" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.62); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; padding: 16px; overflow-y: auto;">
                    <div id="simModalMount" style="width: 100%; max-width: 760px;"></div>
                </div>
            </div>
        </div>
    </div>
</dialog>

<script>
const DEFAULT_SAMPLE_BANNER = <?php echo json_encode($defaultSampleBanner); ?>;
const DESIGN_TEMPLATES = <?php echo json_encode($designTemplates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CARD_STYLE_LABELS = <?php echo json_encode($cardStyles, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

let previewViewportMode = 'desktop';
let simViewportMode = 'desktop';
let simCurrentPage = 'home';
let currentSimOfferCfg = null;
let selectedTplCategory = 'All';
let uploadedBannerDataUrl = <?php echo json_encode($existingBannerSrc ?? ''); ?>;

function escapeOfferHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function getCardFrameCSS(styleKey, bg, sec, text, accent) {
    const baseBg = (styleKey === 'gradient_festive')
        ? `linear-gradient(135deg, ${bg} 0%, ${sec} 100%)`
        : (styleKey === 'glass_frosted'
            ? `${bg}D9`
            : (styleKey === 'dark_luxe_glow'
                ? `radial-gradient(circle at top center, ${accent}28, transparent 62%), ${bg}`
                : bg));

    let borderRadius = '16px';
    let border = `1px solid ${accent}45`;
    let boxShadow = '0 25px 50px -12px rgba(0, 0, 0, 0.35)';
    let backdropFilter = '';
    let innerOverlayHtml = '';

    if (styleKey === 'double_gold_frame') {
        borderRadius = '12px';
        border = `2px solid ${accent}`;
        innerOverlayHtml = `<div style="position: absolute; inset: 8px; border: 1px solid ${accent}80; border-radius: 7px; pointer-events: none; z-index: 4;"></div>`;
    } else if (styleKey === 'editorial_sharp') {
        borderRadius = '0px';
        border = `1.5px solid ${accent}`;
    } else if (styleKey === 'bold_brutalist') {
        borderRadius = '10px';
        border = `3px solid ${accent}`;
        boxShadow = `8px 8px 0px ${accent}`;
    } else if (styleKey === 'glass_frosted') {
        border = `1px solid rgba(255,255,255,0.45)`;
        backdropFilter = 'backdrop-filter: blur(18px);';
    } else if (styleKey === 'dark_luxe_glow') {
        border = `1px solid ${accent}66`;
        boxShadow = `inset 0 1px 0 ${accent}55, 0 25px 55px rgba(0,0,0,0.5)`;
    } else if (styleKey === 'soft_pill_ticket') {
        borderRadius = '20px';
        border = `2px dashed ${accent}`;
        innerOverlayHtml = `
            <div style="position: absolute; left: -10px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; border-radius: 50%; background: rgba(15,23,42,0.65); z-index: 4;"></div>
            <div style="position: absolute; right: -10px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; border-radius: 50%; background: rgba(15,23,42,0.65); z-index: 4;"></div>
        `;
    } else if (styleKey === 'split_ribbon') {
        innerOverlayHtml = `<div style="position: absolute; top: 14px; left: -34px; width: 130px; text-align: center; background: ${accent}; color: #fff; font-size: 9px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; padding: 4px 0; transform: rotate(-38deg); z-index: 6; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">LIMITED</div>`;
    } else if (styleKey === 'arch_editorial') {
        borderRadius = '18px';
        border = `1.5px solid ${accent}55`;
    }

    return { baseBg, borderRadius, border, boxShadow, backdropFilter, innerOverlayHtml };
}

function buildOfferPreviewHTML(cfg, options = {}) {
    const compact = Boolean(options.compact);
    const interactive = Boolean(options.interactive);
    const bg = cfg.bg_color || '#FAF7F3';
    const sec = cfg.secondary_color || '#EFE6D5';
    const text = cfg.text_color || '#18181B';
    const accent = cfg.accent_color || '#B08D57';
    const layout = cfg.layout_type || 'top_banner';
    const cardStyle = cfg.card_style || 'classic';

    let imgSrc = cfg.banner_image || '';
    if (!imgSrc && options.fallbackSample && DEFAULT_SAMPLE_BANNER) {
        imgSrc = DEFAULT_SAMPLE_BANNER;
    }
    const hasImg = imgSrc.length > 0 && layout !== 'text_card';

    const badge = (cfg.badge_text || '').trim();
    const heading = (cfg.heading || '').trim();
    const subheading = (cfg.subheading || '').trim();
    const body = (cfg.body_text || '').trim();
    const coupon = (cfg.coupon_code || '').trim().toUpperCase();
    const couponNote = (cfg.coupon_note || '').trim();
    const cta = (cfg.cta_label || '').trim();
    const showOptOut = cfg.allow_dont_show_again !== false;
    const optOutLabel = (cfg.dont_show_label || "Don't show this again").trim();

    const frame = getCardFrameCSS(cardStyle, bg, sec, text, accent);
    const isSplit = (layout === 'split_left' || layout === 'split_right') && !options.forceMobile;
    const isLeftAlign = isSplit && hasImg;
    const btnRadius = (cardStyle === 'editorial_sharp') ? '0px' : '999px';
    const isHero = (layout === 'hero_overlay');
    const effText = isHero && hasImg ? '#FFFFFF' : text;
    const effSubText = isHero && hasImg ? '#FDE68A' : accent;

    const copyPad = (() => {
        if (compact) return '18px 16px';
        if (isHero) return '54px 30px 30px';
        if (layout === 'split_left' && isSplit && hasImg) return '46px 30px 28px';
        if (layout === 'split_right' && !isSplit) return '46px 24px 26px';
        if (layout === 'text_card' || !hasImg) return '46px 30px 28px';
        return '28px 30px';
    })();

    const textBlockHtml = `
        <div style="position: relative; z-index: 2; padding: ${copyPad}; text-align: ${isLeftAlign ? 'left' : 'center'}; display: flex; flex-direction: column; justify-content: center; align-items: ${isLeftAlign ? 'flex-start' : 'center'}; gap: ${compact ? '7px' : '11px'};">
            ${badge ? `<div style="display: inline-block; font-size: ${compact ? '9px' : '10.5px'}; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 11px; border-radius: ${btnRadius}; background: ${isHero ? 'rgba(255,255,255,0.16)' : `${accent}20`}; color: ${isHero ? '#FFFBEB' : accent}; border: 1px solid ${isHero ? 'rgba(255,255,255,0.35)' : `${accent}50`};">${escapeOfferHtml(badge)}</div>` : ''}
            ${heading ? `<h3 style="margin: 0; font-family: Georgia, 'Playfair Display', serif; font-size: ${compact ? '16px' : '26px'}; font-weight: 600; line-height: 1.22; color: ${effText};">${escapeOfferHtml(heading)}</h3>` : ''}
            ${subheading ? `<div style="font-size: ${compact ? '11.5px' : '14.5px'}; font-weight: 600; color: ${effSubText};">${escapeOfferHtml(subheading)}</div>` : ''}
            ${body ? `<div style="font-size: ${compact ? '11px' : '13px'}; line-height: 1.55; opacity: 0.9; color: ${effText}; white-space: pre-line; max-width: 44ch;">${escapeOfferHtml(body)}</div>` : ''}
            ${coupon ? `
                <div style="width: 100%; margin-top: 2px; padding: ${compact ? '7px 9px' : '11px 14px'}; border-radius: ${cardStyle === 'editorial_sharp' ? '0px' : '10px'}; border: 1.5px dashed ${isHero ? '#FDE68A' : accent}; background: ${isHero ? 'rgba(0,0,0,0.45)' : `${accent}14`}; display: flex; flex-direction: column; align-items: ${isLeftAlign ? 'flex-start' : 'center'}; gap: 4px; box-sizing: border-box;">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="font-family: monospace; font-size: ${compact ? '12.5px' : '16px'}; font-weight: 800; letter-spacing: 0.1em; color: ${effText};">${escapeOfferHtml(coupon)}</span>
                        <button type="button" ${interactive ? `onclick="simCopyCoupon('${escapeOfferHtml(coupon)}', this)"` : ''} style="border: none; cursor: ${interactive ? 'pointer' : 'default'}; font-size: ${compact ? '9.5px' : '10.5px'}; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: ${cardStyle === 'editorial_sharp' ? '0px' : '6px'}; background: ${accent}; color: #fff;">Copy Code</button>
                    </div>
                    ${couponNote ? `<div style="font-size: 11px; opacity: 0.8; color: ${effText};">${escapeOfferHtml(couponNote)}</div>` : ''}
                </div>
            ` : ''}
            ${cta ? `
                <div style="margin-top: 4px; width: ${isLeftAlign ? 'auto' : '100%'};">
                    <button type="button" ${interactive ? `onclick="simClickCta('${escapeOfferHtml(cfg.cta_url || '')}')"` : ''} style="border: none; cursor: ${interactive ? 'pointer' : 'default'}; width: ${isLeftAlign ? 'auto' : '100%'}; padding: ${compact ? '8px 14px' : '12px 24px'}; border-radius: ${btnRadius}; background: ${accent}; color: #ffffff; font-size: ${compact ? '10.5px' : '12px'}; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; box-shadow: 0 4px 14px ${accent}40;">
                        ${escapeOfferHtml(cta)} &rarr;
                    </button>
                </div>
            ` : ''}
        </div>
    `;

    const imgWrapStyle = (cardStyle === 'arch_editorial' && !isHero)
        ? `padding: 14px 14px 0; background: ${bg};`
        : 'background: #e4e4e7;';
    const imgStyle = (cardStyle === 'arch_editorial' && !isHero)
        ? `width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 999px 999px 10px 10px; border: 1.5px solid ${accent}70;`
        : 'width: 100%; height: 100%; object-fit: cover; display: block;';

    let innerHtml = '';
    if (layout === 'banner_only') {
        innerHtml = `
            <div>
                ${hasImg
                    ? `<img src="${escapeOfferHtml(imgSrc)}" alt="" style="width: 100%; max-height: ${compact ? '160px' : '420px'}; object-fit: cover; display: block;">`
                    : `<div style="height: 220px; display: flex; align-items: center; justify-content: center; color: ${text}; opacity: 0.55; font-size: 13px;">Upload a poster banner image to preview</div>`}
                ${(coupon || cta) ? `
                    <div style="padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; border-top: 1px solid ${accent}30;">
                        ${coupon ? `<span style="font-family: monospace; font-weight: 700; font-size: 13px; border: 1px dashed ${accent}; padding: 4px 10px; border-radius: 6px; color: ${text};">${escapeOfferHtml(coupon)}</span>` : '<span></span>'}
                        ${cta ? `<span style="margin-left: auto; padding: 8px 18px; border-radius: ${btnRadius}; background: ${accent}; color: #fff; font-size: 11px; font-weight: 700; text-transform: uppercase;">${escapeOfferHtml(cta)}</span>` : ''}
                    </div>
                ` : ''}
            </div>
        `;
    } else if (layout === 'hero_overlay') {
        innerHtml = `
            <div style="position: relative; min-height: ${compact ? '210px' : '380px'}; display: flex; align-items: center; justify-content: center;">
                ${hasImg ? `<img src="${escapeOfferHtml(imgSrc)}" alt="" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;">` : ''}
                <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(9,9,11,0.48) 0%, rgba(9,9,11,0.82) 100%);"></div>
                ${textBlockHtml}
            </div>
        `;
    } else if (layout === 'split_left' && isSplit && hasImg) {
        innerHtml = `
            <div style="display: grid; grid-template-columns: 44% 56%;">
                <div style="min-height: ${compact ? '210px' : '370px'}; ${imgWrapStyle}"><img src="${escapeOfferHtml(imgSrc)}" alt="" style="${imgStyle}"></div>
                ${textBlockHtml}
            </div>
        `;
    } else if (layout === 'split_right' && isSplit && hasImg) {
        innerHtml = `
            <div style="display: grid; grid-template-columns: 56% 44%;">
                ${textBlockHtml}
                <div style="min-height: ${compact ? '210px' : '370px'}; ${imgWrapStyle}"><img src="${escapeOfferHtml(imgSrc)}" alt="" style="${imgStyle}"></div>
            </div>
        `;
    } else {
        innerHtml = `
            <div>
                ${hasImg ? `<div style="max-height: ${compact ? '120px' : '220px'}; overflow: hidden; ${imgWrapStyle}"><img src="${escapeOfferHtml(imgSrc)}" alt="" style="${imgStyle} max-height: ${compact ? '120px' : '220px'};"></div>` : ''}
                ${textBlockHtml}
            </div>
        `;
    }

    return `
        <div style="position: relative; background: ${frame.baseBg}; color: ${text}; border-radius: ${frame.borderRadius}; border: ${frame.border}; box-shadow: ${frame.boxShadow}; ${frame.backdropFilter} overflow: clip;">
            ${frame.innerOverlayHtml}
            <div style="position: absolute; top: 10px; right: 10px; z-index: 15; display: flex; align-items: center; gap: 6px;">
                ${showOptOut ? `
                    <button type="button" ${interactive ? `onclick="simOptOut()"` : ''} style="border: 1px solid rgba(255,255,255,0.22); border-radius: ${btnRadius}; background: rgba(24,24,27,0.78); color: #ffffff; backdrop-filter: blur(8px); padding: 0 11px; min-height: 30px; font-size: 11px; font-weight: 500; white-space: nowrap; cursor: ${interactive ? 'pointer' : 'default'};">
                        ${escapeOfferHtml(optOutLabel)}
                    </button>
                ` : ''}
                <button type="button" class="offer-preview-close-btn" aria-label="Close offer preview" style="width: 30px; height: 30px; border: 1px solid rgba(255,255,255,0.22); border-radius: 50%; background: rgba(24,24,27,0.78); color: #fff; backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; font-size: 16px; cursor: pointer;">&times;</button>
            </div>
            ${innerHtml}
        </div>
    `;
}

// Gather current form state for live preview & website simulation
function collectFormPreviewConfig() {
    const layoutRadio = document.querySelector('input[name="layout_type"]:checked');
    const styleRadio = document.querySelector('input[name="card_style"]:checked');
    const urlOverride = (document.getElementById('banner_image_url')?.value || '').trim();
    const removed = document.getElementById('remove_banner_image')?.checked;

    let img = '';
    if (!removed) {
        img = urlOverride || uploadedBannerDataUrl || '';
    }

    return {
        layout_type: layoutRadio ? layoutRadio.value : 'top_banner',
        card_style: styleRadio ? styleRadio.value : 'classic',
        banner_image: img,
        badge_text: document.getElementById('badge_text')?.value || '',
        heading: document.getElementById('heading')?.value || 'Your Offer Headline Here',
        subheading: document.getElementById('subheading')?.value || '',
        body_text: document.getElementById('body_text')?.value || '',
        coupon_code: document.getElementById('coupon_code')?.value || '',
        coupon_note: document.getElementById('coupon_note')?.value || '',
        cta_label: document.getElementById('cta_label')?.value || '',
        cta_url: document.getElementById('cta_url')?.value || '',
        allow_dont_show_again: document.getElementById('allow_dont_show_again')?.checked ?? true,
        dont_show_label: document.getElementById('dont_show_label')?.value || "Don't show this again",
        bg_color: document.getElementById('bg_color')?.value || '#FAF7F3',
        secondary_color: document.getElementById('secondary_color')?.value || '#EFE6D5',
        text_color: document.getElementById('text_color')?.value || '#18181B',
        accent_color: document.getElementById('accent_color')?.value || '#B08D57',
    };
}

function updatePreview() {
    const previewEl = document.getElementById('liveOfferPreview');
    const frameEl = document.getElementById('liveOfferPreviewFrame');
    const metaBadge = document.getElementById('previewMetaBadge');
    if (!previewEl) return;

    const cfg = collectFormPreviewConfig();
    const isMobile = previewViewportMode === 'mobile';
    if (frameEl) {
        let maxW = isMobile ? '375px' : '760px';
        if (!isMobile && (cfg.layout_type === 'top_banner' || cfg.layout_type === 'text_card')) maxW = '540px';
        if (!isMobile && cfg.layout_type === 'banner_only') maxW = '640px';
        frameEl.style.maxWidth = maxW;
    }

    if (metaBadge) {
        metaBadge.textContent = `${cfg.layout_type.replace('_', ' ')} • ${CARD_STYLE_LABELS[cfg.card_style] || cfg.card_style}`;
    }

    previewEl.innerHTML = buildOfferPreviewHTML(cfg, { compact: false, forceMobile: isMobile, fallbackSample: false });
}

function setPreviewViewport(mode) {
    previewViewportMode = mode;
    const dBtn = document.getElementById('vpDesktopBtn');
    const mBtn = document.getElementById('vpMobileBtn');
    if (dBtn && mBtn) {
        dBtn.className = 'shadcn-btn ' + (mode === 'desktop' ? 'shadcn-btn-primary' : 'shadcn-btn-ghost');
        mBtn.className = 'shadcn-btn ' + (mode === 'mobile' ? 'shadcn-btn-primary' : 'shadcn-btn-ghost');
    }
    updatePreview();
}

// =========================================================================
// 54 DESIGN TEMPLATES GALLERY MODAL
// =========================================================================
function openTemplateGalleryModal() {
    const dlg = document.getElementById('offerTemplateGalleryDialog');
    if (!dlg) return;
    renderTemplateCategoryPills();
    renderTemplateGalleryGrid();
    dlg.showModal();
}

function renderTemplateCategoryPills() {
    const container = document.getElementById('tplCategoryPills');
    if (!container) return;

    const counts = { All: DESIGN_TEMPLATES.length };
    DESIGN_TEMPLATES.forEach(t => {
        counts[t.category] = (counts[t.category] || 0) + 1;
    });

    container.innerHTML = Object.keys(counts).map(cat => {
        const isActive = selectedTplCategory === cat;
        return `
            <button type="button" onclick="selectTemplateCategory('${escapeOfferHtml(cat)}')"
                    class="shadcn-btn ${isActive ? 'shadcn-btn-primary' : 'shadcn-btn-outline'}"
                    style="height: 28px; font-size: 11.5px; padding: 0 10px;">
                ${escapeOfferHtml(cat)} <span style="opacity: 0.7; font-size: 10.5px;">(${counts[cat]})</span>
            </button>
        `;
    }).join('');
}

function selectTemplateCategory(cat) {
    selectedTplCategory = cat;
    renderTemplateCategoryPills();
    renderTemplateGalleryGrid();
}

function renderTemplateGalleryGrid() {
    const grid = document.getElementById('templateGalleryGrid');
    if (!grid) return;

    const layoutFilter = document.getElementById('tplLayoutFilter')?.value || '';
    const q = (document.getElementById('tplSearchInput')?.value || '').trim().toLowerCase();
    const activeTplId = document.getElementById('template_id')?.value || '';

    const filtered = DESIGN_TEMPLATES.filter(t => {
        if (selectedTplCategory !== 'All' && t.category !== selectedTplCategory) return false;
        if (layoutFilter && t.layout_type !== layoutFilter) return false;
        if (q) {
            const hay = `${t.name} ${t.category} ${t.layout_type} ${t.card_style} ${t.heading}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    });

    if (!filtered.length) {
        grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #71717a;">No templates match your search filter.</div>';
        return;
    }

    grid.innerHTML = filtered.map(t => {
        const frame = getCardFrameCSS(t.card_style, t.bg_color, t.secondary_color, t.text_color, t.accent_color);
        const isActive = activeTplId === t.id;
        const imgTag = DEFAULT_SAMPLE_BANNER
            ? `<img src="${escapeOfferHtml(DEFAULT_SAMPLE_BANNER)}" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">`
            : `<div style="width:100%;height:100%;background:${t.accent_color}25;display:flex;align-items:center;justify-content:center;color:${t.accent_color};font-size:16px;"><i class="fa-solid fa-gem"></i></div>`;

        // Miniature visual thumb reflecting real layout architecture
        let thumbInner = '';
        if (t.layout_type === 'split_left') {
            thumbInner = `<div style="display:grid;grid-template-columns:42% 58%;height:100%;"><div style="overflow:hidden;">${imgTag}</div><div style="padding:10px;display:flex;flex-direction:column;justify-content:center;gap:4px;"><span style="font-size:7px;font-weight:700;letter-spacing:0.1em;color:${t.accent_color};text-transform:uppercase;">${escapeOfferHtml(t.badge_text)}</span><div style="font-family:Georgia,serif;font-size:11px;font-weight:700;line-height:1.15;color:${t.text_color};">${escapeOfferHtml(t.heading)}</div><span style="align-self:flex-start;margin-top:3px;padding:3px 7px;border-radius:99px;background:${t.accent_color};color:#fff;font-size:8px;font-weight:700;">CTA</span></div></div>`;
        } else if (t.layout_type === 'split_right') {
            thumbInner = `<div style="display:grid;grid-template-columns:58% 42%;height:100%;"><div style="padding:10px;display:flex;flex-direction:column;justify-content:center;gap:4px;"><span style="font-size:7px;font-weight:700;letter-spacing:0.1em;color:${t.accent_color};text-transform:uppercase;">${escapeOfferHtml(t.badge_text)}</span><div style="font-family:Georgia,serif;font-size:11px;font-weight:700;line-height:1.15;color:${t.text_color};">${escapeOfferHtml(t.heading)}</div><span style="align-self:flex-start;margin-top:3px;padding:3px 7px;border-radius:99px;background:${t.accent_color};color:#fff;font-size:8px;font-weight:700;">CTA</span></div><div style="overflow:hidden;">${imgTag}</div></div>`;
        } else if (t.layout_type === 'hero_overlay') {
            thumbInner = `<div style="position:relative;height:100%;display:flex;align-items:center;justify-content:center;text-align:center;padding:12px;">${imgTag ? `<div style="position:absolute;inset:0;overflow:hidden;">${imgTag}</div>` : ''}<div style="position:absolute;inset:0;background:rgba(9,9,11,0.62);"></div><div style="position:relative;z-index:2;color:#fff;"><div style="font-size:7px;font-weight:700;letter-spacing:0.1em;color:#FDE68A;">${escapeOfferHtml(t.badge_text)}</div><div style="font-family:Georgia,serif;font-size:12px;font-weight:700;margin-top:2px;">${escapeOfferHtml(t.heading)}</div></div></div>`;
        } else if (t.layout_type === 'banner_only') {
            thumbInner = `<div style="display:flex;flex-direction:column;height:100%;"><div style="flex:1;overflow:hidden;">${imgTag}</div><div style="padding:5px 8px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid ${t.accent_color}30;"><span style="font-family:monospace;font-size:8px;font-weight:700;color:${t.text_color};">COUPON</span><span style="padding:2px 6px;border-radius:99px;background:${t.accent_color};color:#fff;font-size:7.5px;font-weight:700;">SHOP</span></div></div>`;
        } else if (t.layout_type === 'text_card') {
            thumbInner = `<div style="height:100%;padding:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:4px;"><span style="font-size:7.5px;font-weight:700;letter-spacing:0.1em;color:${t.accent_color};">${escapeOfferHtml(t.badge_text)}</span><div style="font-family:Georgia,serif;font-size:12px;font-weight:700;line-height:1.15;color:${t.text_color};">${escapeOfferHtml(t.heading)}</div><span style="border:1px dashed ${t.accent_color};padding:2px 8px;font-family:monospace;font-size:8.5px;color:${t.text_color};margin-top:2px;">CODE</span></div>`;
        } else {
            thumbInner = `<div style="display:flex;flex-direction:column;height:100%;"><div style="height:56px;overflow:hidden;">${imgTag}</div><div style="padding:8px 10px;text-align:center;flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;"><div style="font-family:Georgia,serif;font-size:11px;font-weight:700;line-height:1.15;color:${t.text_color};">${escapeOfferHtml(t.heading)}</div><span style="margin-top:4px;padding:2px 8px;border-radius:99px;background:${t.accent_color};color:#fff;font-size:8px;font-weight:700;">CTA</span></div></div>`;
        }

        return `
            <div class="template-card-item ${isActive ? 'active-template' : ''}" onclick="applyOfferTemplate('${escapeOfferHtml(t.id)}')">
                <div style="background: #27272a; padding: 12px; height: 148px; display: flex; align-items: stretch;">
                    <div style="position: relative; width: 100%; background: ${frame.baseBg}; border: ${frame.border}; border-radius: ${frame.borderRadius}; overflow: hidden; box-shadow: 0 8px 16px rgba(0,0,0,0.3);">
                        ${frame.innerOverlayHtml}
                        ${thumbInner}
                    </div>
                </div>
                <div style="padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; flex: 1; justify-content: space-between;">
                    <div>
                        <div style="font-size: 12.5px; font-weight: 600; color: #09090b;">${escapeOfferHtml(t.name)}</div>
                        <div style="font-size: 10.5px; color: #71717a; margin-top: 2px;">
                            ${escapeOfferHtml(t.category)} &bull; ${escapeOfferHtml(CARD_STYLE_LABELS[t.card_style] || t.card_style)}
                        </div>
                    </div>
                    <button type="button" class="shadcn-btn ${isActive ? 'shadcn-btn-primary' : 'shadcn-btn-outline'}" style="width: 100%; height: 28px; font-size: 11.5px;">
                        ${isActive ? '<i class="fa-solid fa-check"></i> Applied' : 'Apply Design'}
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function applyOfferTemplate(tplId) {
    const tpl = DESIGN_TEMPLATES.find(t => t.id === tplId);
    if (!tpl) return;

    document.getElementById('template_id').value = tpl.id;
    document.getElementById('activeTemplateNameLabel').textContent = `${tpl.name} (${tpl.category})`;

    // Select layout radio
    const layoutInput = document.querySelector(`input[name="layout_type"][value="${tpl.layout_type}"]`);
    if (layoutInput) {
        layoutInput.checked = true;
        onLayoutChange(tpl.layout_type);
    }

    // Select card style radio
    const styleInput = document.querySelector(`input[name="card_style"][value="${tpl.card_style}"]`);
    if (styleInput) {
        styleInput.checked = true;
        onCardStyleChange(tpl.card_style);
    }

    // Set palette colors
    document.getElementById('theme_preset').value = 'custom';
    document.getElementById('bg_color').value = tpl.bg_color;
    document.getElementById('secondary_color').value = tpl.secondary_color;
    document.getElementById('text_color').value = tpl.text_color;
    document.getElementById('accent_color').value = tpl.accent_color;
    syncColorLabels();
    document.querySelectorAll('.theme-swatch-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-theme') === 'custom');
    });

    // Optional sample copy population
    const applySample = document.getElementById('applySampleCopyToggle')?.checked ?? true;
    if (applySample) {
        const titleEl = document.getElementById('offer_title');
        if (titleEl && (!titleEl.value.trim() || DESIGN_TEMPLATES.some(x => x.name === titleEl.value.trim()))) {
            titleEl.value = tpl.name;
        }
        document.getElementById('badge_text').value = tpl.badge_text || '';
        document.getElementById('heading').value = tpl.heading || '';
        document.getElementById('subheading').value = tpl.subheading || '';
        document.getElementById('body_text').value = tpl.body_text || '';
        document.getElementById('cta_label').value = tpl.cta_label || '';
        document.getElementById('cta_url').value = tpl.cta_url || '';
        const noteEl = document.getElementById('coupon_note');
        if (noteEl && tpl.coupon_note) noteEl.value = tpl.coupon_note;
    }

    updatePreview();
    document.getElementById('offerTemplateGalleryDialog')?.close();

    if (window.toast) {
        toast.success('Template Applied', `${tpl.name} loaded into Live Modal Preview.`);
    }
}

// =========================================================================
// "PREVIEW LIKE WEBSITE" FULLSCREEN STOREFRONT SIMULATOR
// =========================================================================
function buildSimulatedWebsiteHtml(page) {
    const navBar = `
        <div style="background: rgba(255,255,255,0.92); border-bottom: 1px solid #e7e5e4; padding: 14px 28px; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-family: Georgia, serif; font-size: 19px; letter-spacing: 0.14em; font-weight: 700; color: #18181B;">YOSSHITA NEHA</div>
            <div style="display: flex; gap: 22px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.1em; color: #52525b;">
                <span>Jewellery</span><span>Outfits</span><span>Blouse Studio</span><span>Lookbook</span>
            </div>
            <div style="display: flex; gap: 14px; font-size: 14px; color: #18181B;">
                <i class="fa-solid fa-magnifying-glass"></i><i class="fa-regular fa-heart"></i><i class="fa-solid fa-bag-shopping"></i>
            </div>
        </div>
    `;

    if (page === 'cart') {
        return `
            ${navBar}
            <div style="max-width: 1080px; margin: 36px auto; padding: 0 24px;">
                <h1 style="font-family: Georgia, serif; font-size: 28px; color: #18181B; margin-bottom: 24px;">Shopping Cart (2)</h1>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <div style="background: #fff; border: 1px solid #e7e5e4; border-radius: 16px; padding: 20px; display: flex; gap: 16px; align-items: center;">
                            <div style="width: 80px; height: 96px; border-radius: 10px; background: #f5f5f4;"></div>
                            <div style="flex: 1;"><div style="font-weight: 700; color: #18181B;">Royal Bridal Polki Choker Set</div><div style="font-size: 12px; color: #71717a; margin-top: 4px;">SKU: YNJ-4102 • Qty: 1</div></div>
                            <div style="font-weight: 700; color: #18181B;">₹8,400</div>
                        </div>
                        <div style="background: #fff; border: 1px solid #e7e5e4; border-radius: 16px; padding: 20px; display: flex; gap: 16px; align-items: center;">
                            <div style="width: 80px; height: 96px; border-radius: 10px; background: #f5f5f4;"></div>
                            <div style="flex: 1;"><div style="font-weight: 700; color: #18181B;">Zardozi Gold Velvet Designer Blouse</div><div style="font-size: 12px; color: #71717a; margin-top: 4px;">SKU: YNB-218 • Qty: 1</div></div>
                            <div style="font-weight: 700; color: #18181B;">₹3,800</div>
                        </div>
                    </div>
                    <div style="background: #fff; border: 1px solid #e7e5e4; border-radius: 18px; padding: 22px;">
                        <div style="font-weight: 700; font-size: 15px; margin-bottom: 14px;">Order Summary</div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;color:#52525b;margin-bottom:8px;"><span>Subtotal</span><span>₹12,200</span></div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;color:#52525b;margin-bottom:14px;"><span>Shipping</span><span>FREE</span></div>
                        <div style="border-top:1px solid #f4f4f5;padding-top:12px;display:flex;justify-content:space-between;font-weight:700;font-size:17px;"><span>Total</span><span>₹12,200</span></div>
                    </div>
                </div>
            </div>
        `;
    }

    if (page === 'category' || page === 'product' || page === 'checkout') {
        return `
            ${navBar}
            <div style="max-width: 1120px; margin: 32px auto; padding: 0 24px;">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.14em; color: #B08D57; font-weight: 700; margin-bottom: 6px;">YosshitaNeha Atelier • ${page.toUpperCase()} VIEW</div>
                <h1 style="font-family: Georgia, serif; font-size: 30px; color: #18181B; margin-bottom: 22px;">Heritage Jewellery &amp; Couture</h1>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px;">
                    ${[1,2,3,4].map(() => `<div style="background:#fff;border:1px solid #e7e5e4;border-radius:14px;overflow:hidden;padding:12px;"><div style="aspect-ratio:3/4;background:#f5f5f4;border-radius:10px;margin-bottom:10px;"></div><div style="height:12px;background:#e4e4e7;border-radius:4px;width:82%;margin-bottom:6px;"></div><div style="height:10px;background:#f4f4f5;border-radius:4px;width:45%;"></div></div>`).join('')}
                </div>
            </div>
        `;
    }

    // Home page mockup
    return `
        ${navBar}
        <div style="background: linear-gradient(120deg, #1c1917 0%, #3b2d1f 55%, #FAF7F3 100%); min-height: 440px; padding: 60px 36px; display: flex; align-items: center;">
            <div style="max-width: 600px; color: #FAF7F3;">
                <div style="font-size: 11px; letter-spacing: 0.25em; text-transform: uppercase; color: #D4AF37; font-weight: 700; margin-bottom: 12px;">YOSSHITANEHA ATELIER MUMBAI</div>
                <h1 style="font-family: Georgia, serif; font-size: 42px; line-height: 1.15; margin-bottom: 16px;">Crafted For Bridal Grandeur</h1>
                <p style="font-size: 15px; opacity: 0.85; line-height: 1.6;">Bespoke hand-embroidered couture blouses paired with heirloom Kundan, Polki &amp; Antique Jewellery.</p>
            </div>
        </div>
        <div style="max-width: 1120px; margin: 36px auto; padding: 0 24px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            ${[1,2,3].map(() => `<div style="height: 220px; background: #fff; border-radius: 16px; border: 1px solid #e7e5e4;"></div>`).join('')}
        </div>
    `;
}

function openWebsiteSimulationFromForm() {
    openWebsiteSimulationModal(collectFormPreviewConfig());
}

function openWebsiteSimulationModal(offerCfg) {
    currentSimOfferCfg = Object.assign({}, offerCfg);
    if (currentSimOfferCfg.banner_image && !currentSimOfferCfg.banner_image.startsWith('http') && !currentSimOfferCfg.banner_image.startsWith('data:')) {
        currentSimOfferCfg.banner_image = currentSimOfferCfg.banner_image.replace(/^\/+/, '');
    }

    const dlg = document.getElementById('websiteSimulationDialog');
    if (!dlg) return;

    renderWebsiteSimulation();
    dlg.showModal();
}

function switchSimulatedWebsitePage(page) {
    simCurrentPage = page;
    document.querySelectorAll('.sim-page-btn').forEach(btn => {
        const active = btn.getAttribute('data-sim-page') === page;
        btn.style.background = active ? '#B08D57' : 'transparent';
        btn.style.color = active ? '#fff' : '#d4d4d8';
    });
    const bgEl = document.getElementById('simWebsiteBg');
    if (bgEl) bgEl.innerHTML = buildSimulatedWebsiteHtml(simCurrentPage);
}

function setSimViewport(mode) {
    simViewportMode = mode;
    const frame = document.getElementById('simViewportFrame');
    const outer = document.getElementById('simOuterWrap');
    const dBtn = document.getElementById('simDesktopBtn');
    const mBtn = document.getElementById('simMobileBtn');
    if (frame && outer) {
        if (mode === 'mobile') {
            outer.style.padding = '16px';
            frame.style.width = '390px';
            frame.style.borderRadius = '26px';
            frame.style.border = '8px solid #18181b';
        } else {
            outer.style.padding = '0';
            frame.style.width = '100%';
            frame.style.borderRadius = '0';
            frame.style.border = 'none';
        }
    }
    if (dBtn && mBtn) {
        dBtn.style.background = mode === 'desktop' ? '#B08D57' : 'transparent';
        mBtn.style.background = mode === 'mobile' ? '#B08D57' : 'transparent';
    }
    renderWebsiteSimulation();
}

function renderWebsiteSimulation() {
    if (!currentSimOfferCfg) return;
    const bgEl = document.getElementById('simWebsiteBg');
    const mount = document.getElementById('simModalMount');
    if (bgEl) bgEl.innerHTML = buildSimulatedWebsiteHtml(simCurrentPage);
    if (mount) {
        const isMobile = simViewportMode === 'mobile';
        let maxW = isMobile ? '356px' : '760px';
        if (!isMobile && (currentSimOfferCfg.layout_type === 'top_banner' || currentSimOfferCfg.layout_type === 'text_card')) maxW = '520px';
        if (!isMobile && currentSimOfferCfg.layout_type === 'banner_only') maxW = '640px';
        mount.style.maxWidth = maxW;
        mount.innerHTML = buildOfferPreviewHTML(currentSimOfferCfg, { compact: false, forceMobile: isMobile, interactive: true, fallbackSample: true });
        const closeBtn = mount.querySelector('.offer-preview-close-btn');
        if (closeBtn) {
            closeBtn.onclick = () => document.getElementById('websiteSimulationDialog')?.close();
        }
    }
}

function simCopyCoupon(code, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).catch(() => {});
    }
    btn.textContent = 'Copied!';
    btn.style.background = '#059669';
    if (window.toast) toast.success('Copied in Preview', `Coupon "${code}" copied to clipboard.`);
    setTimeout(() => { btn.textContent = 'Copy Code'; }, 2000);
}

function simClickCta(url) {
    if (window.toast) toast.info('CTA Clicked (Simulation)', `On live web2, visitor navigates to: ${url || '(no URL set)'}`);
}

function simOptOut() {
    if (window.toast) toast.info("Opted Out (Simulation)", 'On live web2, this popup stays permanently hidden for that visitor.');
    document.getElementById('websiteSimulationDialog')?.close();
}

// =========================================================================
// LIST & FORM EVENT WIRING
// =========================================================================
function toggleOfferStatus(id, checkbox) {
    const isActive = checkbox.checked ? 1 : 0;
    const badge = document.getElementById('status-badge-' + id);
    const liveCountEl = document.getElementById('liveOffersCount');

    const body = new URLSearchParams();
    body.append('action', 'toggle_active');
    body.append('id', id);
    body.append('is_active', isActive);

    fetch('offers.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'xmlhttprequest'
        },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (badge) {
                badge.textContent = isActive ? 'LIVE' : 'PAUSED';
                badge.style.color = isActive ? '#059669' : '#71717a';
            }
            if (liveCountEl) {
                const current = parseInt(liveCountEl.textContent.replace(/,/g, ''), 10) || 0;
                liveCountEl.textContent = Math.max(0, current + (isActive ? 1 : -1)).toLocaleString();
            }
            if (window.toast) {
                if (isActive) toast.success('Offer pushed LIVE', 'Website visitors on selected pages will now see this modal.');
                else toast.info('Offer paused', 'This modal is now hidden from the website.');
            }
        } else {
            checkbox.checked = !checkbox.checked;
            if (window.toast) toast.error('Toggle failed', data.message || 'Could not update status.');
        }
    })
    .catch(() => {
        checkbox.checked = !checkbox.checked;
        if (window.toast) toast.error('Network error', 'Failed to contact server.');
    });
}

function handleBannerFileSelect(input, type) {
    if (type !== 'desktop') return;
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            uploadedBannerDataUrl = e.target.result;
            const rmCheck = document.getElementById('remove_banner_image');
            if (rmCheck) rmCheck.checked = false;
            const act = document.getElementById('desktopBannerActions');
            if (act) act.style.display = 'flex';
            updatePreview();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function onLayoutChange(layoutKey) {
    document.querySelectorAll('.layout-radio-card').forEach(el => {
        el.classList.toggle('selected', el.getAttribute('data-layout-card') === layoutKey);
    });
    const bannerSec = document.getElementById('bannerUploadSection');
    if (bannerSec) bannerSec.style.display = (layoutKey === 'text_card') ? 'none' : 'block';
    updatePreview();
}

function onCardStyleChange(styleKey) {
    document.querySelectorAll('.style-chip-card').forEach(el => {
        el.classList.toggle('selected', el.getAttribute('data-style-card') === styleKey);
    });
    updatePreview();
}

function pickStoreCoupon(selectEl) {
    const code = selectEl.value;
    if (!code) return;
    document.getElementById('coupon_code').value = code;
    const opt = selectEl.options[selectEl.selectedIndex];
    const note = opt.getAttribute('data-note');
    const noteInput = document.getElementById('coupon_note');
    if (note && noteInput && (!noteInput.value || noteInput.value === 'Apply code at checkout')) {
        noteInput.value = note;
    }
    updatePreview();
}

function handlePageTargetToggle(checkbox) {
    const key = checkbox.value;
    const allCheckbox = document.querySelector('#targetPagesWrap input[value="all"]');

    if (key === 'all' && checkbox.checked) {
        document.querySelectorAll('#targetPagesWrap input[type="checkbox"]').forEach(cb => {
            if (cb.value !== 'all') cb.checked = false;
        });
    } else if (key !== 'all' && checkbox.checked && allCheckbox) {
        allCheckbox.checked = false;
    }

    document.querySelectorAll('#targetPagesWrap .page-pill-toggle').forEach(pill => {
        const cb = pill.querySelector('input[type="checkbox"]');
        pill.classList.toggle('checked', cb && cb.checked);
    });

    const catChecked = document.querySelector('#targetPagesWrap input[value="category"]')?.checked;
    const prodChecked = document.querySelector('#targetPagesWrap input[value="product"]')?.checked;
    const allChecked = allCheckbox?.checked;
    const catBox = document.getElementById('categoryFilterBox');
    if (catBox) {
        catBox.style.display = (catChecked || prodChecked || allChecked) ? 'block' : 'none';
    }
}

function applyThemePreset(key, bg, sec, text, accent) {
    document.getElementById('theme_preset').value = key;
    document.getElementById('bg_color').value = bg;
    document.getElementById('secondary_color').value = sec;
    document.getElementById('text_color').value = text;
    document.getElementById('accent_color').value = accent;
    syncColorLabels();
    document.querySelectorAll('.theme-swatch-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-theme') === key);
    });
    updatePreview();
}

function onCustomColorInput() {
    document.getElementById('theme_preset').value = 'custom';
    document.querySelectorAll('.theme-swatch-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-theme') === 'custom');
    });
    syncColorLabels();
    updatePreview();
}

function syncColorLabels() {
    document.getElementById('bg_hex_label').textContent = document.getElementById('bg_color').value;
    document.getElementById('sec_hex_label').textContent = document.getElementById('secondary_color').value;
    document.getElementById('text_hex_label').textContent = document.getElementById('text_color').value;
    document.getElementById('accent_hex_label').textContent = document.getElementById('accent_color').value;
}

function toggleTriggerInputs() {
    const t = document.getElementById('trigger_type')?.value;
    const delayWrap = document.getElementById('delayInputWrap');
    const scrollWrap = document.getElementById('scrollInputWrap');
    if (delayWrap) delayWrap.style.display = (t === 'delay') ? 'block' : 'none';
    if (scrollWrap) scrollWrap.style.display = (t === 'scroll') ? 'block' : 'none';
}

// Light-dismiss fallback for native dialogs
(function() {
    if ('closedBy' in HTMLDialogElement.prototype) return;
    document.querySelectorAll('dialog.admin-native-dialog').forEach(dlg => {
        const outside = (e) => {
            const r = dlg.getBoundingClientRect();
            return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
        };
        let pressedOutside = false;
        dlg.addEventListener('pointerdown', (e) => { if (dlg.open) pressedOutside = outside(e); });
        dlg.addEventListener('pointerup', (e) => { if (dlg.open && pressedOutside && outside(e)) dlg.close(); });
    });
})();

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('offerBuilderForm')) {
        const activeLayout = document.querySelector('input[name="layout_type"]:checked')?.value || 'top_banner';
        onLayoutChange(activeLayout);
        toggleTriggerInputs();
        updatePreview();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
