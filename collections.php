<?php
// admin/collections.php - Minimalist 4-Column Shadcn UI Lookbook & Collections Manager
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Collections & Lookbook Manager";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Inter Font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ==========================================================================
   MINIMALIST SHADCN UI DESIGN (HIGH-DENSITY 4-COLUMN DASHBOARD)
   ========================================================================== */
.shadcn-wrap {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #09090b;
    background-color: #fafafa;
    min-height: calc(100vh - 65px);
    padding: 16px 24px 48px 24px;
}

/* Minimal Single-Row Header */
.shadcn-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e4e4e7;
}

.shadcn-title-group {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.header-title {
    font-size: 16px;
    font-weight: 600;
    color: #09090b;
    letter-spacing: -0.02em;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.header-count-badge {
    background: #f4f4f5;
    color: #52525b;
    border: 1px solid #e4e4e7;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 9999px;
}

.header-meta-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #71717a;
}
.header-meta-sep {
    color: #d4d4d8;
    font-size: 10px;
}
.header-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    display: inline-block;
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(1.3); }
}

/* Minimal Shadcn Action Buttons */
.btn-shadcn-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    height: 32px;
    padding: 0 10px;
    border-radius: 6px;
    transition: all 0.15s ease;
    cursor: pointer;
    text-decoration: none;
    line-height: 1;
    white-space: nowrap;
}

.btn-shadcn-primary {
    background: #09090b;
    color: #ffffff;
    border: 1px solid #09090b;
}
.btn-shadcn-primary:hover {
    background: #27272a;
    color: #ffffff;
}

.btn-shadcn-outline {
    background: #ffffff;
    color: #09090b;
    border: 1px solid #e4e4e7;
}
.btn-shadcn-outline:hover {
    background: #f4f4f5;
    border-color: #d4d4d8;
    color: #09090b;
}

.btn-shadcn-secondary {
    background: #f4f4f5;
    color: #18181b;
    border: 1px solid transparent;
}
.btn-shadcn-secondary:hover {
    background: #e4e4e7;
}

.tag-sold-mini {
    font-size: 9.5px;
    font-weight: 700;
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fde68a;
    padding: 1px 5px;
    border-radius: 4px;
}

/* Compact Category Filter Tabs */
.shadcn-tabs-track {
    background: #f4f4f5;
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e4e4e7;
    display: flex;
    gap: 2px;
    overflow-x: auto;
    margin-bottom: 12px;
    scrollbar-width: thin;
}

.shadcn-tab-item {
    background: transparent;
    border: none;
    padding: 4px 10px;
    font-size: 11.5px;
    font-weight: 500;
    color: #71717a;
    border-radius: 6px;
    cursor: pointer;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s ease;
}
.shadcn-tab-item:hover {
    color: #09090b;
}
.shadcn-tab-item.active {
    background: #ffffff;
    color: #09090b;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.shadcn-tab-count {
    background: #e4e4e7;
    color: #52525b;
    padding: 0 5px;
    border-radius: 9999px;
    font-size: 9.5px;
    font-weight: 700;
}
.shadcn-tab-item.active .shadcn-tab-count {
    background: #f4f4f5;
    color: #09090b;
}

/* Compact Toolbar */
.shadcn-toolbar {
    background: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 8px;
    padding: 8px 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
}

.shadcn-search-input-wrap {
    position: relative;
    width: 280px;
    max-width: 100%;
}
.shadcn-search-input-wrap i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #a1a1aa;
    font-size: 11px;
}
.shadcn-search-input-wrap input {
    width: 100%;
    height: 30px;
    padding: 0 10px 0 28px;
    background: #fafafa;
    border: 1px solid #e4e4e7;
    border-radius: 6px;
    font-size: 12px;
    color: #09090b;
    transition: all 0.15s;
}
.shadcn-search-input-wrap input:focus {
    background: #ffffff;
    outline: none;
    border-color: #09090b;
}

.shadcn-select {
    height: 30px;
    padding: 0 8px;
    border: 1px solid #e4e4e7;
    border-radius: 6px;
    background: #ffffff;
    font-size: 11.5px;
    color: #09090b;
    font-weight: 500;
    cursor: pointer;
}
.shadcn-select:focus {
    outline: none;
    border-color: #09090b;
}

/* ==========================================================================
   4-COLUMN CARD GRID
   ========================================================================== */
.shadcn-grid-4col {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

@media (max-width: 1360px) {
    .shadcn-grid-4col {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 960px) {
    .shadcn-grid-4col {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 580px) {
    .shadcn-grid-4col {
        grid-template-columns: 1fr;
    }
}

/* High-Density Shadcn Card */
.shadcn-card {
    background: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.02);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}
.shadcn-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 14px -3px rgba(0, 0, 0, 0.06);
    transform: translateY(-2px);
}

.shadcn-card-media {
    position: relative;
    aspect-ratio: 4 / 3;
    background: #f4f4f5;
    overflow: hidden;
}

.shadcn-card-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease, opacity 0.2s ease;
}
.shadcn-card:hover .shadcn-card-img {
    transform: scale(1.03);
}

/* Over-Image Floating Badges */
.badge-category {
    position: absolute;
    top: 8px;
    left: 8px;
    background: rgba(9, 9, 11, 0.85);
    backdrop-filter: blur(4px);
    color: #ffffff;
    font-size: 9.5px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 9999px;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.badge-angles {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(4px);
    color: #18181b;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 9999px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 3px;
}

.btn-favorite-star {
    position: absolute;
    bottom: 8px;
    right: 8px;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #ffffff;
    border: 1px solid #e4e4e7;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a1a1aa;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-favorite-star.active {
    color: #eab308;
    background: #fefce8;
    border-color: #fef08a;
}
.btn-favorite-star:hover {
    transform: scale(1.1);
}

/* Mini Thumbnails Strip */
.shadcn-thumb-strip {
    display: flex;
    gap: 4px;
    padding: 6px 10px;
    background: #fafafa;
    border-top: 1px solid #f4f4f5;
    border-bottom: 1px solid #f4f4f5;
    min-height: 38px;
}

.shadcn-mini-thumb {
    width: 30px;
    height: 30px;
    border-radius: 4px;
    overflow: hidden;
    background: #e4e4e7;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.15s;
    flex-shrink: 0;
}
.shadcn-mini-thumb:hover, .shadcn-mini-thumb.active {
    border-color: #09090b;
    transform: scale(1.05);
}
.shadcn-mini-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Card Body Content */
.shadcn-card-body {
    padding: 12px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.shadcn-tag-row {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
    margin-bottom: 6px;
}

.tag-sku {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 10px;
    font-weight: 700;
    color: #18181b;
    background: #f4f4f5;
    border: 1px solid #e4e4e7;
    padding: 1px 5px;
    border-radius: 4px;
}

.tag-sold {
    font-size: 10px;
    font-weight: 600;
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fde68a;
    padding: 1px 6px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.tag-attr {
    font-size: 10px;
    font-weight: 500;
    color: #71717a;
    background: #fafafa;
    border: 1px solid #e4e4e7;
    padding: 1px 5px;
    border-radius: 4px;
}

.shadcn-card-title {
    font-size: 13px;
    font-weight: 600;
    color: #09090b;
    line-height: 1.35;
    margin: 0 0 3px 0;
}
.shadcn-card-title a {
    color: #09090b;
    text-decoration: none;
    transition: color 0.15s;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.shadcn-card-title a:hover {
    color: #4f46e5;
}

.shadcn-card-desc {
    font-size: 11px;
    color: #71717a;
    line-height: 1.4;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Card Footer */
.shadcn-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    background: #fafafa;
    border-top: 1px solid #f4f4f5;
}

.status-badge-toggle {
    font-size: 10.5px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 9999px;
    border: 1px solid;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: transparent;
    transition: all 0.15s;
}
.status-badge-toggle.published {
    background: #ecfdf5;
    color: #065f46;
    border-color: #a7f3d0;
}
.status-badge-toggle.published:hover {
    background: #d1fae5;
}
.status-badge-toggle.draft {
    background: #f4f4f5;
    color: #71717a;
    border-color: #e4e4e7;
}
.status-badge-toggle.draft:hover {
    background: #e4e4e7;
}

.shadcn-actions-group {
    display: flex;
    align-items: center;
    gap: 4px;
}

.icon-action-btn {
    width: 26px;
    height: 26px;
    border-radius: 5px;
    border: 1px solid #e4e4e7;
    background: #ffffff;
    color: #52525b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s;
}
.icon-action-btn:hover {
    background: #f4f4f5;
    border-color: #d4d4d8;
    color: #09090b;
}
.icon-action-btn.delete:hover {
    background: #fef2f2;
    border-color: #fecaca;
    color: #ef4444;
}

/* ==========================================================================
   IMAGE SHORTENER & OPTIMIZER MODAL STYLES (SHADCN DESIGN)
   ========================================================================== */
.img-opt-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(9, 9, 11, 0.65);
    backdrop-filter: blur(4px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.img-opt-modal-dialog {
    background: #ffffff;
    width: 100%;
    max-width: 1160px;
    height: 92vh;
    max-height: 880px;
    border-radius: 14px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: modalPopIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPopIn {
    from { opacity: 0; transform: scale(0.97) translateY(8px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.img-opt-header {
    padding: 16px 22px;
    border-bottom: 1px solid #e4e4e7;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
}

.img-opt-title-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}

.img-opt-icon-box {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
}

.img-opt-title {
    font-size: 16px;
    font-weight: 700;
    color: #09090b;
    margin: 0;
    letter-spacing: -0.01em;
}

.img-opt-sub {
    font-size: 12px;
    color: #71717a;
    margin: 2px 0 0 0;
}

.img-opt-close-btn {
    border: none;
    background: #f4f4f5;
    color: #71717a;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}
.img-opt-close-btn:hover {
    background: #e4e4e7;
    color: #09090b;
}

/* Modal Body */
.img-opt-body {
    flex: 1;
    overflow: hidden;
    padding: 16px 22px;
    background: #fafafa;
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 0;
}

/* Metric Cards */
.img-opt-metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
@media (max-width: 860px) {
    .img-opt-metrics-grid { grid-template-columns: repeat(2, 1fr); }
}

.img-opt-metric-card {
    background: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    padding: 12px 14px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.img-opt-metric-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #71717a;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.img-opt-metric-val {
    font-size: 18px;
    font-weight: 700;
    color: #09090b;
    line-height: 1.2;
}

.img-opt-metric-sub {
    font-size: 11px;
    color: #a1a1aa;
    margin-top: 2px;
}

/* Settings Toolbar */
.img-opt-toolbar {
    background: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}

.img-opt-toolbar-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.img-opt-mode-toggle {
    display: inline-flex;
    background: #f4f4f5;
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e4e4e7;
}

.img-opt-mode-btn {
    border: none;
    background: transparent;
    font-size: 12px;
    font-weight: 600;
    color: #52525b;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
}

.img-opt-mode-btn.active {
    background: #ffffff;
    color: #09090b;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.img-opt-mode-btn.active.lossy {
    color: #4f46e5;
}
.img-opt-mode-btn.active.lossless {
    color: #0284c7;
}

/* Batch Progress Banner */
.img-opt-progress-banner {
    display: none;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 10px;
    padding: 12px 16px;
}

.img-opt-progress-track {
    background: #e0e7ff;
    height: 8px;
    border-radius: 9999px;
    overflow: hidden;
    margin: 8px 0;
}

.img-opt-progress-fill {
    background: linear-gradient(90deg, #4f46e5, #7c3aed);
    height: 100%;
    width: 0%;
    transition: width 0.25s ease;
    border-radius: 9999px;
}

/* Image Table */
.img-opt-table-wrap {
    background: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.img-opt-table-scroll {
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}

.img-opt-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    text-align: left;
}

.img-opt-table th {
    background: #fafafa;
    color: #71717a;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 8px 12px;
    border-bottom: 1px solid #e4e4e7;
    position: sticky;
    top: 0;
    z-index: 10;
}

.img-opt-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #f4f4f5;
    vertical-align: middle;
}

.img-opt-table tr:hover td {
    background: #fbfbfe;
}

.img-opt-table tr.just-compressed td {
    background: #ecfdf5 !important;
    transition: background 1s ease;
}

.badge-size-danger {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    font-weight: 700;
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-block;
}

.badge-size-warning {
    background: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
    font-weight: 600;
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-block;
}

.badge-size-info {
    background: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
    font-weight: 600;
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-block;
}

.badge-savings-success {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
    font-weight: 700;
    font-size: 11.5px;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.img-opt-footer {
    padding: 12px 22px;
    border-top: 1px solid #e4e4e7;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
</style>

<div class="shadcn-wrap">
    
    <!-- 1. Minimal Header Row -->
    <div class="shadcn-header">
        <div class="shadcn-title-group">
            <h1 class="header-title">
                <i class="fa-solid fa-camera-retro" style="color: #4f46e5; font-size: 15px;"></i>
                Lookbook &amp; Outfits
            </h1>
            <span class="header-count-badge"><span id="statTotalOutfits">0</span> outfits</span>
            
            <div class="header-meta-row">
                <span class="header-meta-item"><i class="fa-solid fa-sparkles text-amber-500" style="color: #d97706; font-size: 11px;"></i> <strong id="statSoldBridged" style="color: #92400e;">0</strong> in diary</span>
                <span class="header-meta-sep">•</span>
                <span class="header-meta-item"><i class="fa-solid fa-images text-emerald-600" style="color: #059669; font-size: 11px;"></i> <strong id="statTotalPhotos">0</strong> media</span>
                <span class="header-meta-sep">•</span>
                <span class="header-meta-item" style="color: #059669; font-size: 11.5px; font-family: ui-monospace, monospace;"><span class="pulse-dot"></span> <span id="statQueryTime">0 ms</span></span>
            </div>
        </div>

        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
            <button type="button" class="btn-shadcn-sm btn-shadcn-outline" onclick="openImageShortenerModal()" id="btnImageShortener" title="Automated Collection Image Shortener & Optimizer" style="border-color: #c7d2fe; background: #f5f3ff;">
                <i class="fa-solid fa-compress" style="color: #4f46e5;"></i>
                <span style="font-weight: 600; color: #4338ca;">Image Shortener</span>
                <span class="tag-sold-mini" style="background:#e0e7ff; color:#3730a3; border-color:#c7d2fe;">Optimizer</span>
            </button>
            <button type="button" class="btn-shadcn-sm btn-shadcn-outline" onclick="syncSoldOutfits()" id="btnSyncSold" title="Sync Sold Outfits to Client Diary">
                <i class="fa-solid fa-arrows-rotate text-emerald-600" style="color: #059669;"></i>
                <span>Sync Sold</span>
                <span class="tag-sold-mini">Diary</span>
            </button>
            <a href="collection-ai-sorter.php" class="btn-shadcn-sm btn-shadcn-secondary" title="Open AI Sorter Studio">
                <i class="fa-solid fa-wand-magic-sparkles text-indigo-600" style="color: #4f46e5;"></i>
                <span>AI Sorter</span>
            </a>
            <a href="collection-add.php" class="btn-shadcn-sm btn-shadcn-primary">
                <i class="fa-solid fa-plus"></i>
                <span>Add Outfit</span>
            </a>
        </div>
    </div>

    <!-- 2. Compact Category Tabs Track -->
    <div class="shadcn-tabs-track" id="categoryTabsContainer">
        <button type="button" class="shadcn-tab-item active" data-cat="All" onclick="setCategoryFilter('All')">
            <span>All Styles</span>
            <span class="shadcn-tab-count" id="countAll">0</span>
        </button>
        <!-- Categories populated via JS -->
    </div>

    <!-- 3. Minimal Toolbar -->
    <div class="shadcn-toolbar">
        <div class="shadcn-search-input-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchInput" placeholder="Search by SKU (e.g. YNB049), title, color..." onkeyup="handleSearch(event)">
        </div>

        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            <select id="statusFilter" class="shadcn-select" onchange="loadCollections(1)">
                <option value="all">All Statuses</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </select>

            <span style="font-size: 11.5px; color: #71717a; margin-left: 4px;">Show:</span>
            <select id="perPageSelect" class="shadcn-select" style="width: 60px;" onchange="loadCollections(1)">
                <option value="16" selected>16</option>
                <option value="24">24</option>
                <option value="36">36</option>
                <option value="48">48</option>
            </select>

            <button type="button" class="btn-shadcn-sm btn-shadcn-outline" style="width: 30px; height: 30px; padding: 0;" onclick="loadCollections(currentPage)" title="Refresh">
                <i class="fa-solid fa-rotate" style="font-size: 11px;"></i>
            </button>
        </div>
    </div>

    <!-- 4. 4-Column Card Grid -->
    <div id="collectionsGrid" class="shadcn-grid-4col">
        <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: #a1a1aa;">
            <i class="fa-solid fa-circle-notch fa-spin fa-2x" style="color: #4f46e5;"></i>
            <div style="margin-top: 10px; font-size: 13px; font-weight: 500;">Loading outfits with WebP acceleration...</div>
        </div>
    </div>

    <!-- 5. Minimal Pagination -->
    <div id="paginationWrap" style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 14px; border-top: 1px solid #e4e4e7; flex-wrap: wrap; gap: 8px;">
        <div style="font-size: 12px; color: #71717a;" id="paginationInfo">Showing 0 of 0</div>
        <div style="display: flex; gap: 6px;" id="paginationBtns"></div>
    </div>

</div>

<!-- ========================================================================= -->
<!-- Collections Image Shortener & Optimizer Modal                             -->
<!-- ========================================================================= -->
<div id="imageShortenerModal" class="img-opt-modal-overlay">
    <div class="img-opt-modal-dialog">
        <!-- 1. Header -->
        <div class="img-opt-header">
            <div class="img-opt-title-wrap">
                <div class="img-opt-icon-box">
                    <i class="fa-solid fa-compress"></i>
                </div>
                <div>
                    <h2 class="img-opt-title">Collections Image Shortener &amp; Optimizer</h2>
                    <p class="img-opt-sub"><i class="fa-regular fa-folder-open"></i> admin/uploads/collections &bull; Automated High-Res Scanner &amp; Compression</p>
                </div>
            </div>
            <button type="button" class="img-opt-close-btn" onclick="closeImageShortenerModal()" title="Close (Esc)">&times;</button>
        </div>

        <!-- 2. Body -->
        <div class="img-opt-body">
            <!-- 2.1 Metric Summary Cards -->
            <div class="img-opt-metrics-grid">
                <div class="img-opt-metric-card">
                    <div class="img-opt-metric-label">
                        <span>Total Collection Images</span>
                        <i class="fa-regular fa-images" style="color: #6366f1;"></i>
                    </div>
                    <div class="img-opt-metric-val" id="optStatTotalFiles"><i class="fa-solid fa-spinner fa-spin" style="font-size: 15px;"></i></div>
                    <div class="img-opt-metric-sub" id="optStatTotalSize">Scanning folder...</div>
                </div>

                <div class="img-opt-metric-card">
                    <div class="img-opt-metric-label">
                        <span>Heavy Images (&gt; 1 MB)</span>
                        <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b;"></i>
                    </div>
                    <div class="img-opt-metric-val" id="optStatHeavyFiles"><i class="fa-solid fa-spinner fa-spin" style="font-size: 15px;"></i></div>
                    <div class="img-opt-metric-sub" id="optStatHeavySize">Needs compression</div>
                </div>

                <div class="img-opt-metric-card">
                    <div class="img-opt-metric-label">
                        <span>Potential Space Recovery</span>
                        <i class="fa-solid fa-bolt" style="color: #10b981;"></i>
                    </div>
                    <div class="img-opt-metric-val" id="optStatEstSavings" style="color: #059669;"><i class="fa-solid fa-spinner fa-spin" style="font-size: 15px;"></i></div>
                    <div class="img-opt-metric-sub" id="optStatEstSavingsSub">~85% - 97% space reduction</div>
                </div>

                <div class="img-opt-metric-card">
                    <div class="img-opt-metric-label">
                        <span>Active View List</span>
                        <i class="fa-solid fa-list-check" style="color: #64748b;"></i>
                    </div>
                    <div class="img-opt-metric-val" id="optStatFilteredCount">0</div>
                    <div class="img-opt-metric-sub" id="optStatFilteredInfo">Sorted by largest size first</div>
                </div>
            </div>

            <!-- 2.2 Controls & Settings Toolbar -->
            <div class="img-opt-toolbar">
                <div class="img-opt-toolbar-top">
                    <!-- Mode Selector -->
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 12px; font-weight: 600; color: #3f3f46;">Mode:</span>
                        <div class="img-opt-mode-toggle">
                            <button type="button" class="img-opt-mode-btn active lossy" id="btnModeLossy" onclick="setImageOptMode('lossy')" title="Smart resize to max HD + 82% quality. 70-97% size reduction. Visually identical.">
                                <i class="fa-solid fa-gauge-high"></i>
                                <span>Lossy (Recommended)</span>
                            </button>
                            <button type="button" class="img-opt-mode-btn" id="btnModeLossless" onclick="setImageOptMode('lossless')" title="Preserve exact 100% original dimensions, clean camera EXIF and Huffman tables. 20-65% reduction.">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span>Lossless (Exact Pixels)</span>
                            </button>
                        </div>
                    </div>

                    <!-- Dimension selector (for Lossy) -->
                    <div id="lossySettingsWrap" style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 12px; color: #71717a;">Max Dimension:</span>
                        <select id="optMaxDimension" class="shadcn-select" style="height: 32px; font-size: 12px;">
                            <option value="1800" selected>1800 px (Retina Web - Recommended)</option>
                            <option value="1920">1920 px (Full HD 1080p)</option>
                            <option value="1600">1600 px (Fast Web)</option>
                            <option value="1200">1200 px (Compact Mobile)</option>
                        </select>
                    </div>

                    <!-- Size Filter Threshold -->
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 12px; color: #71717a;">Filter:</span>
                        <select id="optSizeFilter" class="shadcn-select" style="height: 32px; font-size: 12px;" onchange="applyImageFilter()">
                            <option value="1024" selected>Files &gt; 1 MB (Recommended)</option>
                            <option value="2048">Files &gt; 2 MB</option>
                            <option value="5120">Files &gt; 5 MB</option>
                            <option value="10240">Files &gt; 10 MB</option>
                            <option value="500">Files &gt; 500 KB</option>
                            <option value="0">All Images</option>
                        </select>
                    </div>
                </div>

                <!-- Secondary Search & Action Row -->
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; padding-top: 8px; border-top: 1px dashed #e4e4e7;">
                    <div style="display: flex; align-items: center; gap: 8px; flex: 1; max-width: 360px;">
                        <div class="shadcn-search-input-wrap" style="width: 100%;">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="optSearchInput" placeholder="Filter by folder or image name..." onkeyup="handleImageSearch()">
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="btn-shadcn-sm btn-shadcn-outline" onclick="rescanImagesFolder()" id="btnRescanImages" title="Rescan admin/uploads/collections directory">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            <span>Rescan</span>
                        </button>
                        
                        <button type="button" class="btn-shadcn-sm btn-shadcn-outline" onclick="compressSelectedImages()" id="btnCompressSelected" style="border-color: #cbd5e1;">
                            <i class="fa-solid fa-check-double text-indigo-600"></i>
                            <span>Compress Selected (<span id="optSelectedCount">0</span>)</span>
                        </button>

                        <button type="button" class="btn-shadcn-sm btn-shadcn-primary" onclick="compressAllFilteredImages()" id="btnCompressAllFiltered" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border: none; box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3);">
                            <i class="fa-solid fa-bolt"></i>
                            <span>Compress All Filtered (<span id="optBatchCount">0</span>)</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 2.3 Live Batch Progress Bar (Appears during batch execution) -->
            <div id="optProgressBanner" class="img-opt-progress-banner">
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-spinner fa-spin text-indigo-600" id="optProgressSpinner"></i>
                        <span style="font-weight: 600; color: #1e1b4b;" id="optProgressText">Compressing images...</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 12px; font-weight: 700; color: #059669;" id="optProgressSavedText">Saved: 0 MB</span>
                        <span style="font-size: 12px; font-weight: 700; color: #1e1b4b;" id="optProgressPercent">0%</span>
                        <button type="button" class="btn-shadcn-sm btn-shadcn-outline" onclick="stopBatchCompression()" style="height: 26px; padding: 0 8px; font-size: 11px; background: #ffffff; color: #dc2626; border-color: #fca5a5;">
                            <i class="fa-solid fa-stop"></i> Stop
                        </button>
                    </div>
                </div>
                <div class="img-opt-progress-track">
                    <div id="optProgressBar" class="img-opt-progress-fill"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 11px; color: #4338ca;" id="optProgressSubtext">
                    <span>Batch optimization running safely in chunks...</span>
                    <span id="optProgressCounter">0 / 0</span>
                </div>
            </div>

            <!-- 2.4 Image List Table -->
            <div class="img-opt-table-wrap">
                <div class="img-opt-table-scroll">
                    <table class="img-opt-table">
                        <thead>
                            <tr>
                                <th style="width: 36px; text-align: center;">
                                    <input type="checkbox" id="optSelectAllCheckbox" onchange="toggleSelectAllImages(this.checked)" style="cursor: pointer;">
                                </th>
                                <th style="width: 54px; text-align: center;">Preview</th>
                                <th>File &amp; Subfolder</th>
                                <th style="width: 120px;">Dimensions</th>
                                <th style="width: 110px;">Current Size</th>
                                <th style="width: 180px;">Optimization Status</th>
                                <th style="width: 200px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="optImagesTableBody">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #a1a1aa;">
                                    <i class="fa-solid fa-circle-notch fa-spin fa-2x" style="color: #4f46e5;"></i>
                                    <div style="margin-top: 10px; font-weight: 500;">Scanning admin/uploads/collections for high-res images...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Pagination Bar -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #fafafa; border-top: 1px solid #e4e4e7; font-size: 12px; color: #71717a;">
                    <div id="optTablePaginationInfo">Showing 0 of 0</div>
                    <div style="display: flex; gap: 6px; align-items: center;" id="optTablePaginationBtns"></div>
                </div>
            </div>
        </div>

        <!-- 3. Footer -->
        <div class="img-opt-footer">
            <div style="font-size: 12px; color: #71717a;">
                <i class="fa-solid fa-shield-check" style="color: #059669;"></i>
                <span>Safe In-Place Replacement: Images are only overwritten if the compressed version is valid and smaller.</span>
            </div>
            <button type="button" class="btn-shadcn-sm btn-shadcn-outline" onclick="closeImageShortenerModal()">
                Close
            </button>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let currentCategory = 'All';
let searchTimeout = null;

// Read category from URL if present
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('cat')) {
    currentCategory = urlParams.get('cat');
}

document.addEventListener('DOMContentLoaded', () => {
    loadCollections(1);
});

function handleSearch(e) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadCollections(1);
    }, 280);
}

function setCategoryFilter(cat) {
    currentCategory = cat;
    document.querySelectorAll('.shadcn-tab-item').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-cat') === cat);
    });
    loadCollections(1);
}

async function loadCollections(page = 1) {
    currentPage = page;
    const grid = document.getElementById('collectionsGrid');
    grid.innerHTML = `
        <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: #a1a1aa;">
            <i class="fa-solid fa-circle-notch fa-spin fa-2x" style="color: #4f46e5;"></i>
            <div style="margin-top: 10px; font-size: 13px; font-weight: 500;">Loading outfits...</div>
        </div>
    `;

    const search = document.getElementById('searchInput').value.trim();
    const status = document.getElementById('statusFilter').value;
    const perPage = document.getElementById('perPageSelect').value;

    let url = `api/admin_collections.php?action=list&page=${page}&per_page=${perPage}&status=${encodeURIComponent(status)}`;
    if (search) url += `&s=${encodeURIComponent(search)}`;
    if (currentCategory && currentCategory !== 'All') url += `&cat=${encodeURIComponent(currentCategory)}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (!data.success) {
            grid.innerHTML = `<div style="grid-column: 1/-1; color: #ef4444; padding: 40px; text-align: center;">Error: ${data.message || 'Failed to fetch outfits'}</div>`;
            return;
        }

        // Update stats
        if (data.stats) {
            document.getElementById('statTotalOutfits').textContent = data.stats.total_collections || 0;
            if (document.getElementById('statSoldBridged')) {
                document.getElementById('statSoldBridged').textContent = data.stats.total_sold_bridged || 0;
            }
            document.getElementById('statTotalPhotos').textContent = data.stats.total_photos || 0;
            document.getElementById('statQueryTime').textContent = `${data.stats.query_time_ms} ms`;
            document.getElementById('countAll').textContent = data.stats.total_collections || 0;
        }

        // Render categories tabs if available
        if (data.categories) {
            renderCategoryTabs(data.categories);
        }

        // Render Outfits
        renderCollectionCards(data.data || []);

        // Render Pagination
        renderPagination(data.pagination);

    } catch (err) {
        grid.innerHTML = `<div style="grid-column: 1/-1; color: #ef4444; padding: 40px; text-align: center;">Network Error: ${err.message}</div>`;
    }
}

function renderCategoryTabs(categories) {
    const container = document.getElementById('categoryTabsContainer');
    const existingButtons = container.querySelectorAll('.shadcn-tab-item:not([data-cat="All"])');
    existingButtons.forEach(b => b.remove());

    categories.forEach(cat => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `shadcn-tab-item ${currentCategory === cat.category ? 'active' : ''}`;
        btn.setAttribute('data-cat', cat.category);
        btn.onclick = () => setCategoryFilter(cat.category);
        btn.innerHTML = `<span>${escapeHtml(cat.category)}</span> <span class="shadcn-tab-count">${cat.count}</span>`;
        container.appendChild(btn);
    });
}

function renderCollectionCards(items) {
    const grid = document.getElementById('collectionsGrid');
    if (!items.length) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 50px 20px; background: #ffffff; border: 1.5px dashed #e4e4e7; border-radius: 10px;">
                <div style="width: 44px; height: 44px; background: #f4f4f5; border-radius: 50%; color: #71717a; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; margin-bottom: 10px;">
                    <i class="fa-solid fa-vest-patches"></i>
                </div>
                <h3 style="font-size: 15px; font-weight: 600; color: #09090b; margin-bottom: 2px;">No Outfits Found</h3>
                <p style="font-size: 12px; color: #71717a; max-width: 380px; margin: 0 auto 12px auto;">
                    ${currentCategory !== 'All' 
                        ? `No outfits under <strong>${escapeHtml(currentCategory)}</strong>.` 
                        : 'No outfits match the current search or filters.'}
                </p>
                <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                    <a href="collection-ai-sorter.php" class="btn-shadcn-sm btn-shadcn-secondary">
                        <i class="fa-solid fa-wand-magic-sparkles text-indigo-600"></i> AI Sorter
                    </a>
                    <a href="collection-add.php" class="btn-shadcn-sm btn-shadcn-outline">
                        <i class="fa-solid fa-plus"></i> Add Manually
                    </a>
                </div>
            </div>
        `;
        return;
    }

    grid.innerHTML = items.map(c => {
        // High-speed WebP thumbnail with full server URL fallback on error
        const coverSrc = c.cover_thumb || c.cover_url || 'assets/images/placeholder.svg';
        const fallbackSrc = c.cover_url || 'assets/images/placeholder.svg';
        const thumbs = c.preview_images || [];
        const isFeatured = parseInt(c.is_featured) === 1;
        const isPublished = c.status === 'published';
        const isSold = parseInt(c.is_sold) === 1;

        return `
            <div class="shadcn-card" id="card_${c.id}">
                <div>
                    <!-- Hero Image Media Wrap -->
                    <div class="shadcn-card-media" id="media_${c.id}">
                        <img 
                            src="${coverSrc}" 
                            alt="${escapeHtml(c.title)}" 
                            class="shadcn-card-img" 
                            id="main_img_${c.id}"
                            loading="lazy"
                            decoding="async"
                            onerror="this.onerror=null; this.src='${fallbackSrc}';"
                        >
                        <span class="badge-category">${escapeHtml(c.category || 'Atelier')}</span>
                        <span class="badge-angles"><i class="fa-solid fa-camera" style="font-size: 9px;"></i> ${c.total_images || 0}</span>
                        <button type="button" class="btn-favorite-star ${isFeatured ? 'active' : ''}" onclick="toggleFeatured(${c.id})" title="${isFeatured ? 'Remove Featured' : 'Mark as Featured'}">
                            <i class="fa-solid fa-star" style="font-size: 11px;"></i>
                        </button>
                    </div>

                    <!-- Multi-Angle Quick Switcher Strip -->
                    ${thumbs.length > 0 ? `
                        <div class="shadcn-thumb-strip">
                            ${thumbs.map(t => {
                                const thumbUrl = (typeof t === 'object' && t !== null) ? (t.thumb || t.original) : t;
                                const originalUrl = (typeof t === 'object' && t !== null) ? (t.original || t.thumb) : t;
                                return `
                                    <div class="shadcn-mini-thumb" 
                                         onclick="swapCardMainImage(${c.id}, '${thumbUrl}', '${originalUrl}')"
                                         title="Preview Angle">
                                        <img src="${thumbUrl}" alt="Angle" loading="lazy" decoding="async" onerror="this.onerror=null; this.src='${originalUrl}';">
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    ` : ''}

                    <!-- Card Body -->
                    <div class="shadcn-card-body">
                        <div>
                            <div class="shadcn-tag-row">
                                ${c.sku ? `<span class="tag-sku">${escapeHtml(c.sku)}</span>` : ''}
                                ${isSold ? `<span class="tag-sold"><i class="fa-solid fa-sparkles"></i> Sold Diary</span>` : ''}
                                ${c.fabric ? `<span class="tag-attr">${escapeHtml(c.fabric)}</span>` : ''}
                                ${c.color ? `<span class="tag-attr">${escapeHtml(c.color)}</span>` : ''}
                            </div>
                            <h3 class="shadcn-card-title">
                                <a href="${c.edit_url}" title="${escapeHtml(c.title)}">${escapeHtml(c.title)}</a>
                            </h3>
                            <p class="shadcn-card-desc">${escapeHtml(c.description || c.subtitle || 'Designer handcrafted ensemble.')}</p>
                        </div>
                    </div>
                </div>

                <!-- Card Footer -->
                <div class="shadcn-card-footer">
                    <button type="button" class="status-badge-toggle ${isPublished ? 'published' : 'draft'}" onclick="toggleStatus(${c.id})">
                        <i class="fa-solid fa-circle" style="font-size: 5px;"></i>
                        <span>${isPublished ? 'Published' : 'Draft'}</span>
                    </button>

                    <div class="shadcn-actions-group">
                        ${c.product_id ? `
                            <a href="product-edit.php?id=${c.product_id}" class="icon-action-btn" title="View Source Product" target="_blank" style="color: #4f46e5;">
                                <i class="fa-solid fa-bag-shopping"></i>
                            </a>
                        ` : ''}
                        <a href="${c.edit_url}" class="icon-action-btn" title="Edit Outfit">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <button type="button" class="icon-action-btn delete" onclick="deleteCollection(${c.id}, '${escapeHtml(c.title)}')" title="Delete Outfit">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function swapCardMainImage(cardId, thumbUrl, originalUrl) {
    const mainImg = document.getElementById(`main_img_${cardId}`);
    if (mainImg) {
        mainImg.style.opacity = '0.5';
        mainImg.src = thumbUrl;
        mainImg.onerror = function() {
            this.onerror = null;
            this.src = originalUrl;
        };
        mainImg.onload = function() {
            this.style.opacity = '1';
        };
    }
}

function renderPagination(p) {
    if (!p) return;
    document.getElementById('paginationInfo').textContent = `Page ${p.page} of ${p.total_pages || 1} (${p.total} outfits)`;

    const btnsWrap = document.getElementById('paginationBtns');
    let html = '';

    if (p.has_prev) {
        html += `<button type="button" class="btn-shadcn-sm btn-shadcn-outline" onclick="loadCollections(${p.page - 1})"><i class="fa-solid fa-chevron-left"></i> Prev</button>`;
    }
    if (p.has_next) {
        html += `<button type="button" class="btn-shadcn-sm btn-shadcn-outline" onclick="loadCollections(${p.page + 1})">Next <i class="fa-solid fa-chevron-right"></i></button>`;
    }

    btnsWrap.innerHTML = html;
}

async function toggleFeatured(id) {
    const formData = new FormData();
    formData.append('id', id);

    try {
        const res = await fetch('api/admin_collections.php?action=toggle_featured', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            const card = document.getElementById(`card_${id}`);
            const star = card.querySelector('.btn-favorite-star');
            star.classList.toggle('active', data.is_featured === 1);
        }
    } catch (e) {
        console.error(e);
    }
}

async function toggleStatus(id) {
    const formData = new FormData();
    formData.append('id', id);

    try {
        const res = await fetch('api/admin_collections.php?action=toggle_status', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            const card = document.getElementById(`card_${id}`);
            const btn = card.querySelector('.status-badge-toggle');
            const isPub = data.status === 'published';
            btn.className = `status-badge-toggle ${isPub ? 'published' : 'draft'}`;
            btn.innerHTML = `<i class="fa-solid fa-circle" style="font-size: 5px;"></i> <span>${isPub ? 'Published' : 'Draft'}</span>`;
        }
    } catch (e) {
        console.error(e);
    }
}

async function deleteCollection(id, title) {
    if (!confirm(`Are you sure you want to delete "${title}"?`)) return;

    const formData = new FormData();
    formData.append('id', id);

    try {
        const res = await fetch('api/admin_collections.php?action=delete', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            loadCollections(currentPage);
        } else {
            alert(data.message || 'Could not delete outfit.');
        }
    } catch (e) {
        alert('Network error: ' + e.message);
    }
}

async function syncSoldOutfits() {
    const btn = document.getElementById('btnSyncSold');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Syncing...';
    try {
        const res = await fetch('api/admin_collections.php?action=sync_sold_products', {
            method: 'POST'
        });
        const data = await res.json();
        if (data.success) {
            alert('🎉 ' + data.message);
            loadCollections(currentPage);
        } else {
            alert('⚠️ ' + (data.message || 'Failed to sync sold outfits.'));
        }
    } catch (err) {
        alert('❌ Error syncing sold outfits: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// =========================================================================
// COLLECTIONS IMAGE SHORTENER & OPTIMIZER CLIENT ENGINE
// =========================================================================
let optAllImages = [];
let optFilteredImages = [];
let optSelectedSet = new Set();
let optCurrentMode = 'lossy'; // 'lossy' | 'lossless'
let optCurrentPage = 1;
const optPerPage = 50;
let optBatchActive = false;
let optBatchStopRequested = false;
let optTotalBytesSavedInSession = 0;
let optSearchDebounceTimer = null;

function openImageShortenerModal() {
    document.getElementById('imageShortenerModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    if (optAllImages.length === 0) {
        fetchImageOptimizationData();
    }
}

function closeImageShortenerModal() {
    if (optBatchActive) {
        if (!confirm('Batch optimization is currently running. Are you sure you want to close? It will safely stop after the current chunk.')) {
            return;
        }
        optBatchStopRequested = true;
    }
    document.getElementById('imageShortenerModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Close on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('imageShortenerModal');
        if (modal && modal.style.display === 'flex') {
            closeImageShortenerModal();
        }
    }
});

function setImageOptMode(mode) {
    optCurrentMode = mode;
    const btnLossy = document.getElementById('btnModeLossy');
    const btnLossless = document.getElementById('btnModeLossless');
    const lossyWrap = document.getElementById('lossySettingsWrap');
    
    if (mode === 'lossy') {
        btnLossy.className = 'img-opt-mode-btn active lossy';
        btnLossless.className = 'img-opt-mode-btn';
        if (lossyWrap) lossyWrap.style.display = 'flex';
    } else {
        btnLossy.className = 'img-opt-mode-btn';
        btnLossless.className = 'img-opt-mode-btn active lossless';
        if (lossyWrap) lossyWrap.style.display = 'none';
    }
}

async function fetchImageOptimizationData() {
    const tableBody = document.getElementById('optImagesTableBody');
    tableBody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align: center; padding: 40px; color: #a1a1aa;">
                <i class="fa-solid fa-circle-notch fa-spin fa-2x" style="color: #4f46e5;"></i>
                <div style="margin-top: 10px; font-weight: 500;">Scanning admin/uploads/collections for high-resolution images...</div>
            </td>
        </tr>
    `;

    try {
        const res = await fetch('api/compress_collections.php?action=scan');
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Scan failed');
        }

        optAllImages = data.files || [];
        
        // Populate Summary KPIs
        const sum = data.summary || {};
        document.getElementById('optStatTotalFiles').textContent = (sum.total_files || optAllImages.length).toLocaleString();
        document.getElementById('optStatTotalSize').textContent = sum.total_size_fmt || '0 MB';
        
        document.getElementById('optStatHeavyFiles').textContent = (sum.count_gt_1mb || 0).toLocaleString() + ' files';
        document.getElementById('optStatHeavySize').textContent = sum.heavy_size_fmt || '0 MB';
        
        document.getElementById('optStatEstSavings').textContent = '~' + (sum.est_savings_fmt || '0 MB');
        
        applyImageFilter();

    } catch (err) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 30px; color: #ef4444;">
                    <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
                    <div style="margin-top: 8px; font-weight: 600;">Scan Failed: ${escapeHtml(err.message)}</div>
                    <button type="button" class="btn-shadcn-sm btn-shadcn-outline" style="margin-top: 10px;" onclick="fetchImageOptimizationData()">Retry Scan</button>
                </td>
            </tr>
        `;
    }
}

function rescanImagesFolder() {
    optSelectedSet.clear();
    updateSelectedCount();
    fetchImageOptimizationData();
}

function handleImageSearch() {
    clearTimeout(optSearchDebounceTimer);
    optSearchDebounceTimer = setTimeout(() => {
        applyImageFilter();
    }, 250);
}

function applyImageFilter() {
    const thresholdKb = parseInt(document.getElementById('optSizeFilter').value, 10) || 0;
    const thresholdBytes = thresholdKb * 1024;
    const searchTerm = (document.getElementById('optSearchInput').value || '').trim().toLowerCase();

    optFilteredImages = optAllImages.filter(img => {
        if (img.size < thresholdBytes) return false;
        if (searchTerm !== '') {
            const pathMatch = img.rel_path.toLowerCase().includes(searchTerm);
            const folderMatch = (img.folder || '').toLowerCase().includes(searchTerm);
            if (!pathMatch && !folderMatch) return false;
        }
        return true;
    });

    optCurrentPage = 1;
    document.getElementById('optBatchCount').textContent = optFilteredImages.length.toLocaleString();
    document.getElementById('optStatFilteredCount').textContent = optFilteredImages.length.toLocaleString();
    document.getElementById('optStatFilteredInfo').textContent = `Showing files ≥ ${thresholdKb > 0 ? (thresholdKb >= 1024 ? (thresholdKb/1024)+' MB' : thresholdKb+' KB') : '0 KB'}`;

    renderOptImagesTable();
}

function renderOptImagesTable() {
    const tableBody = document.getElementById('optImagesTableBody');
    if (optFilteredImages.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 36px; color: #71717a;">
                    <i class="fa-regular fa-folder-open fa-2x" style="color: #cbd5e1; margin-bottom: 8px;"></i>
                    <div style="font-weight: 600; color: #09090b;">No images match current filter criteria</div>
                    <div style="font-size: 12px; margin-top: 4px;">Try selecting a lower threshold or clearing the search box.</div>
                </td>
            </tr>
        `;
        document.getElementById('optTablePaginationInfo').textContent = 'Showing 0 of 0';
        document.getElementById('optTablePaginationBtns').innerHTML = '';
        return;
    }

    const total = optFilteredImages.length;
    const totalPages = Math.ceil(total / optPerPage);
    if (optCurrentPage > totalPages) optCurrentPage = totalPages;
    const startIdx = (optCurrentPage - 1) * optPerPage;
    const pageItems = optFilteredImages.slice(startIdx, startIdx + optPerPage);

    let html = '';
    pageItems.forEach(img => {
        const isSelected = optSelectedSet.has(img.id);
        const encodedRel = encodeURIComponent(img.rel_path).replace(/%2F/g, '/');
        const imgUrl = `uploads/collections/${encodedRel}`;

        let sizeBadgeClass = 'badge-size-info';
        if (img.size > 5 * 1048576) sizeBadgeClass = 'badge-size-danger';
        else if (img.size > 1048576) sizeBadgeClass = 'badge-size-warning';

        let statusHtml = '<span style="color: #71717a; font-size: 11.5px;"><i class="fa-regular fa-clock" style="font-size: 10px;"></i> Pending</span>';
        if (img.isOptimizing) {
            statusHtml = '<span style="color: #4f46e5; font-weight: 600; font-size: 11.5px;"><i class="fa-solid fa-spinner fa-spin"></i> Compressing...</span>';
        } else if (img.optimized) {
            if (img.unchanged) {
                statusHtml = '<span style="color: #52525b; font-size: 11.5px;"><i class="fa-solid fa-circle-check" style="color: #10b981;"></i> Already optimal</span>';
            } else {
                statusHtml = `<span class="badge-savings-success"><i class="fa-solid fa-arrow-down"></i> ${img.size_fmt} <span style="opacity:0.8;">(-${img.savings_percent}%)</span></span>`;
            }
        }

        html += `
            <tr id="row_${img.id}" class="${img.justCompressed ? 'just-compressed' : ''}">
                <td style="text-align: center;">
                    <input type="checkbox" class="opt-row-checkbox" data-id="${img.id}" ${isSelected ? 'checked' : ''} onchange="toggleSelectImage('${img.id}', this.checked)" style="cursor: pointer;">
                </td>
                <td style="text-align: center;">
                    <img src="${imgUrl}" alt="" style="width: 42px; height: 42px; object-fit: cover; border-radius: 6px; border: 1px solid #e4e4e7; background: #f4f4f5; cursor: pointer;" onclick="window.open('${imgUrl}', '_blank')" loading="lazy" onerror="this.src='assets/images/placeholder.svg'">
                </td>
                <td>
                    <div style="font-weight: 600; color: #09090b; word-break: break-all; max-width: 320px;">
                        ${escapeHtml(img.filename)}
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; margin-top: 3px; font-size: 11px; color: #71717a;">
                        <span style="background: #f4f4f5; padding: 1px 6px; border-radius: 4px; font-family: ui-monospace, monospace; border: 1px solid #e4e4e7;">${escapeHtml(img.folder)}</span>
                        <span>&bull;</span>
                        <span>${escapeHtml(img.date_fmt)}</span>
                    </div>
                </td>
                <td style="font-family: ui-monospace, monospace; color: #52525b; font-size: 11.5px;">
                    ${img.dimensions || '—'}
                </td>
                <td>
                    <span class="${sizeBadgeClass}">${img.size_fmt}</span>
                </td>
                <td id="status_${img.id}">
                    ${statusHtml}
                </td>
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 4px; align-items: center;">
                        <button type="button" class="btn-shadcn-sm btn-shadcn-outline" style="height: 26px; padding: 0 8px; font-size: 11px;" onclick="compressSingleImageRow('${img.id}', 'lossy')" title="Compress Lossy (Smart resize + 82% quality)">
                            <i class="fa-solid fa-gauge-high text-indigo-600"></i> Lossy
                        </button>
                        <button type="button" class="btn-shadcn-sm btn-shadcn-outline" style="height: 26px; padding: 0 8px; font-size: 11px;" onclick="compressSingleImageRow('${img.id}', 'lossless')" title="Compress Lossless (Exact pixels + clean EXIF)">
                            <i class="fa-solid fa-shield-halved text-sky-600"></i> Lossless
                        </button>
                        <a href="${imgUrl}" target="_blank" class="btn-shadcn-sm btn-shadcn-outline" style="height: 26px; width: 26px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="View Full Original">
                            <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 10px;"></i>
                        </a>
                    </div>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;

    // Pagination info
    const endIdx = Math.min(startIdx + optPerPage, total);
    document.getElementById('optTablePaginationInfo').textContent = `Showing ${startIdx + 1}–${endIdx} of ${total.toLocaleString()} images`;

    // Pagination Buttons
    let btnsHtml = '';
    btnsHtml += `<button type="button" class="btn-shadcn-sm btn-shadcn-outline" style="height: 26px; padding: 0 8px; font-size: 11px;" ${optCurrentPage <= 1 ? 'disabled style="opacity:0.4;"' : ''} onclick="goToOptPage(${optCurrentPage - 1})"><i class="fa-solid fa-chevron-left"></i> Prev</button>`;
    btnsHtml += `<span style="font-size: 11.5px; font-weight: 600; padding: 0 6px;">Page ${optCurrentPage} of ${totalPages}</span>`;
    btnsHtml += `<button type="button" class="btn-shadcn-sm btn-shadcn-outline" style="height: 26px; padding: 0 8px; font-size: 11px;" ${optCurrentPage >= totalPages ? 'disabled style="opacity:0.4;"' : ''} onclick="goToOptPage(${optCurrentPage + 1})">Next <i class="fa-solid fa-chevron-right"></i></button>`;
    document.getElementById('optTablePaginationBtns').innerHTML = btnsHtml;

    // Sync select-all checkbox
    const allOnPageSelected = pageItems.length > 0 && pageItems.every(i => optSelectedSet.has(i.id));
    document.getElementById('optSelectAllCheckbox').checked = allOnPageSelected;
}

function goToOptPage(page) {
    optCurrentPage = page;
    renderOptImagesTable();
}

function toggleSelectImage(id, checked) {
    if (checked) {
        optSelectedSet.add(id);
    } else {
        optSelectedSet.delete(id);
    }
    updateSelectedCount();
}

function toggleSelectAllImages(checked) {
    const total = optFilteredImages.length;
    const startIdx = (optCurrentPage - 1) * optPerPage;
    const pageItems = optFilteredImages.slice(startIdx, startIdx + optPerPage);

    pageItems.forEach(img => {
        if (checked) optSelectedSet.add(img.id);
        else optSelectedSet.delete(img.id);
    });

    document.querySelectorAll('.opt-row-checkbox').forEach(cb => {
        cb.checked = checked;
    });

    updateSelectedCount();
}

function updateSelectedCount() {
    const cnt = optSelectedSet.size;
    document.getElementById('optSelectedCount').textContent = cnt;
}

async function compressSingleImageRow(id, mode = null) {
    const img = optAllImages.find(i => i.id === id);
    if (!img) return;

    const useMode = mode || optCurrentMode;
    const maxDim = parseInt(document.getElementById('optMaxDimension').value, 10) || 1800;

    img.isOptimizing = true;
    renderStatusCell(img);

    try {
        const res = await fetch('api/compress_collections.php?action=compress_single', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                file: img.rel_path,
                mode: useMode,
                max_dimension: maxDim,
                quality: useMode === 'lossy' ? 82 : 92
            })
        });

        const data = await res.json();
        img.isOptimizing = false;

        if (data.success) {
            img.optimized = true;
            img.unchanged = !!data.unchanged;
            img.size = data.new_size;
            img.size_fmt = data.new_size_fmt;
            img.savings_percent = data.savings_percent;
            img.dimensions = data.dimensions;
            img.justCompressed = true;
            img.opt_message = data.message;

            if (data.saved_bytes > 0) {
                optTotalBytesSavedInSession += data.saved_bytes;
            }

            renderOptImagesTable();
            setTimeout(() => {
                img.justCompressed = false;
                const row = document.getElementById(`row_${img.id}`);
                if (row) row.classList.remove('just-compressed');
            }, 2500);
        } else {
            alert('Compression failed for ' + img.filename + ': ' + (data.message || 'Error'));
            renderStatusCell(img);
        }
    } catch (err) {
        img.isOptimizing = false;
        alert('Error: ' + err.message);
        renderStatusCell(img);
    }
}

function renderStatusCell(img) {
    const el = document.getElementById(`status_${img.id}`);
    if (!el) return;
    if (img.isOptimizing) {
        el.innerHTML = '<span style="color: #4f46e5; font-weight: 600; font-size: 11.5px;"><i class="fa-solid fa-spinner fa-spin"></i> Compressing...</span>';
    } else if (img.optimized) {
        if (img.unchanged) {
            el.innerHTML = '<span style="color: #52525b; font-size: 11.5px;"><i class="fa-solid fa-circle-check" style="color: #10b981;"></i> Already optimal</span>';
        } else {
            el.innerHTML = `<span class="badge-savings-success"><i class="fa-solid fa-arrow-down"></i> ${img.size_fmt} <span style="opacity:0.8;">(-${img.savings_percent}%)</span></span>`;
        }
    } else {
        el.innerHTML = '<span style="color: #71717a; font-size: 11.5px;"><i class="fa-regular fa-clock" style="font-size: 10px;"></i> Pending</span>';
    }
}

// Batch Compression Runner
async function compressAllFilteredImages() {
    if (optFilteredImages.length === 0) {
        alert('No images match current filter criteria to compress.');
        return;
    }
    const modeLabel = optCurrentMode === 'lossy' ? 'LOSSY (Max HD, 82% quality)' : 'LOSSLESS (Original pixels, strip EXIF metadata)';
    const msg = `Start batch compression on ${optFilteredImages.length.toLocaleString()} images in ${modeLabel} mode?\n\nThis runs safely in chunks with live progress.`;
    if (!confirm(msg)) return;

    startBatchOptimizationQueue(optFilteredImages);
}

async function compressSelectedImages() {
    if (optSelectedSet.size === 0) {
        alert('Please select at least one image using the row checkboxes.');
        return;
    }
    const targets = optAllImages.filter(i => optSelectedSet.has(i.id));
    const modeLabel = optCurrentMode === 'lossy' ? 'LOSSY' : 'LOSSLESS';
    if (!confirm(`Compress ${targets.length} selected images in ${modeLabel} mode?`)) return;

    startBatchOptimizationQueue(targets);
}

async function startBatchOptimizationQueue(queueList) {
    if (optBatchActive) {
        alert('A batch process is already active.');
        return;
    }

    optBatchActive = true;
    optBatchStopRequested = false;

    const banner = document.getElementById('optProgressBanner');
    banner.style.display = 'block';

    const progressBar = document.getElementById('optProgressBar');
    const progressText = document.getElementById('optProgressText');
    const progressPercent = document.getElementById('optProgressPercent');
    const progressCounter = document.getElementById('optProgressCounter');
    const progressSaved = document.getElementById('optProgressSavedText');

    const totalCount = queueList.length;
    let completedCount = 0;
    let totalSavedBytes = 0;
    const chunkSize = 3; // Safe chunk size to avoid Apache execution timeouts

    const maxDim = parseInt(document.getElementById('optMaxDimension').value, 10) || 1800;
    const quality = optCurrentMode === 'lossy' ? 82 : 92;

    for (let i = 0; i < totalCount; i += chunkSize) {
        if (optBatchStopRequested) {
            progressText.textContent = 'Optimization stopped by user.';
            break;
        }

        const chunk = queueList.slice(i, i + chunkSize);
        const chunkPaths = chunk.map(c => c.rel_path);

        // Mark items as optimizing
        chunk.forEach(img => {
            img.isOptimizing = true;
            renderStatusCell(img);
        });

        progressText.textContent = `Optimizing: ${chunk[0].filename} (Chunk ${Math.floor(i / chunkSize) + 1} of ${Math.ceil(totalCount / chunkSize)})...`;

        try {
            const res = await fetch('api/compress_collections.php?action=compress_batch', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    files: chunkPaths,
                    mode: optCurrentMode,
                    max_dimension: maxDim,
                    quality: quality
                })
            });

            const data = await res.json();
            if (data.success && Array.isArray(data.results)) {
                data.results.forEach(resItem => {
                    const matchedImg = queueList.find(img => img.id === resItem.file_id || img.rel_path === resItem.rel_path);
                    if (matchedImg) {
                        matchedImg.isOptimizing = false;
                        if (resItem.success) {
                            matchedImg.optimized = true;
                            matchedImg.unchanged = !!resItem.unchanged;
                            matchedImg.size = resItem.new_size;
                            matchedImg.size_fmt = resItem.new_size_fmt;
                            matchedImg.savings_percent = resItem.savings_percent;
                            matchedImg.dimensions = resItem.dimensions;
                            matchedImg.justCompressed = true;
                            matchedImg.opt_message = resItem.message;
                            if (resItem.saved_bytes > 0) {
                                totalSavedBytes += resItem.saved_bytes;
                            }
                        }
                    }
                });
            }
        } catch (err) {
            console.error('Chunk error:', err);
        }

        completedCount += chunk.length;
        const pct = Math.min(100, Math.round((completedCount / totalCount) * 100));

        progressBar.style.width = pct + '%';
        progressPercent.textContent = pct + '%';
        progressCounter.textContent = `${Math.min(completedCount, totalCount)} / ${totalCount}`;

        const savedFmt = formatBytesClient(totalSavedBytes);
        progressSaved.textContent = `Saved: ${savedFmt}`;

        // Re-render table page to show updated badges
        renderOptImagesTable();
    }

    optBatchActive = false;
    document.getElementById('optProgressSpinner').className = 'fa-solid fa-circle-check text-emerald-600';
    if (!optBatchStopRequested) {
        progressText.innerHTML = `<span style="color: #059669;"><i class="fa-solid fa-check"></i> Batch Optimization Complete! Recovered ${formatBytesClient(totalSavedBytes)} space.</span>`;
    }

    setTimeout(() => {
        // Refresh scan stats
        fetchImageOptimizationData();
    }, 2000);
}

function stopBatchCompression() {
    optBatchStopRequested = true;
    document.getElementById('optProgressText').textContent = 'Stopping batch after current chunk...';
}

function formatBytesClient(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
