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

        <div style="display: flex; gap: 6px; align-items: center;">
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
