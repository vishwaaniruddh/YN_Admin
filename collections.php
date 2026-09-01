<?php
// admin/collections.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Collections & Lookbook Manager";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
:root {
    --lookbook-accent: #6366f1;
    --lookbook-accent-dark: #4f46e5;
    --lookbook-bg: #f8fafc;
    --lookbook-card-border: #e2e8f0;
}

/* Page Header & AI Callout */
.lookbook-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
}

.lookbook-title-group h1 {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.lookbook-subtitle {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

.ai-banner-strip {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    border-radius: 10px;
    padding: 16px 20px;
    color: #ffffff;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.25);
}

.ai-banner-content {
    display: flex;
    align-items: center;
    gap: 14px;
}

.ai-banner-icon {
    width: 44px;
    height: 44px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

/* Monitor Bar */
.api-monitor-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 18px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    flex-wrap: wrap;
    gap: 12px;
}

.api-stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #475569;
}

.api-stat-item strong {
    color: #0f172a;
    font-weight: 700;
}

.api-badge-live {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.api-badge-live .pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    animation: pulseGlow 1.5s infinite;
}

@keyframes pulseGlow {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.5; }
}

/* Category Filter Tabs */
.cat-tabs-container {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 8px;
    margin-bottom: 16px;
    scrollbar-width: thin;
}

.cat-tab-btn {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 20px;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.cat-tab-btn:hover {
    border-color: var(--lookbook-accent);
    color: var(--lookbook-accent-dark);
}

.cat-tab-btn.active {
    background: #0f172a;
    border-color: #0f172a;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
}

.cat-tab-count {
    background: rgba(0, 0, 0, 0.08);
    padding: 1px 6px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
}

.cat-tab-btn.active .cat-tab-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Toolbar */
.lookbook-toolbar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}

.toolbar-left, .toolbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.search-input-box {
    position: relative;
    min-width: 260px;
}

.search-input-box input {
    width: 100%;
    padding: 6px 12px 6px 32px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
}

.search-input-box i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 13px;
}

/* Grid Layout */
.collection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 22px;
    margin-top: 10px;
}

.collection-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s;
}

.collection-card:hover {
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    transform: translateY(-3px);
    border-color: #cbd5e1;
}

.card-media-wrap {
    position: relative;
    aspect-ratio: 16 / 11;
    background: #f1f5f9;
    overflow: hidden;
}

.card-media-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.5s ease;
}

.collection-card:hover .card-media-img {
    transform: scale(1.04);
}

.card-cat-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(15, 23, 42, 0.85);
    color: #f8fafc;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    backdrop-filter: blur(4px);
}

.card-photos-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(255, 255, 255, 0.95);
    color: #0f172a;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 5px;
}

.card-star-btn {
    position: absolute;
    bottom: 10px;
    right: 10px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #ffffff;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #cbd5e1;
    cursor: pointer;
    transition: transform 0.2s, color 0.2s;
}

.card-star-btn.active {
    color: #eab308;
}

.card-star-btn:hover {
    transform: scale(1.15);
}

/* Multi-Angle Thumbnails */
.card-thumbs-strip {
    display: flex;
    gap: 4px;
    padding: 6px 10px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
    min-height: 46px;
}

.card-mini-thumb {
    flex: 1;
    height: 40px;
    border-radius: 4px;
    overflow: hidden;
    background: #e2e8f0;
}

.card-mini-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-info {
    padding: 14px 16px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.card-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 4px 0;
    line-height: 1.35;
}

.card-title a {
    color: inherit;
    text-decoration: none;
}

.card-title a:hover {
    color: var(--lookbook-accent);
}

.card-tags-row {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.card-tag-pill {
    background: #f1f5f9;
    color: #475569;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 4px;
}

.card-desc {
    font-size: 12px;
    color: #64748b;
    margin: 0 0 12px 0;
    line-height: 1.45;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-footer-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid #f1f5f9;
    padding-top: 12px;
    margin-top: auto;
}

.status-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    cursor: pointer;
    border: none;
    transition: opacity 0.2s;
}

.status-pill.published {
    background: #ecfdf5;
    color: #047857;
}

.status-pill.draft {
    background: #f1f5f9;
    color: #64748b;
}

.card-actions {
    display: flex;
    gap: 6px;
}

.action-icon-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    text-decoration: none;
    transition: all 0.2s;
}

.action-icon-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}

.action-icon-btn.delete:hover {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #dc2626;
}
</style>

<div class="wrap">
    <!-- Header -->
    <div class="lookbook-page-header">
        <div class="lookbook-title-group">
            <h1>
                <i class="fa-solid fa-camera-retro" style="color: var(--lookbook-accent);"></i>
                Collections &amp; Lookbook Manager
            </h1>
            <p class="lookbook-subtitle">
                Manage designer outfit styles, multi-angle photoshoot media, and lookbook gallery items.
            </p>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="collection-ai-sorter.php" class="button button-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border: none; padding: 6px 16px; font-weight: 700;">
                <i class="fa-solid fa-wand-magic-sparkles"></i> AI Outfit Sorter <span class="badge" style="background: rgba(255,255,255,0.25); color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 10px; margin-left: 4px;">Gemini</span>
            </a>
            <a href="collection-add.php" class="button button-primary" style="background: #0f172a; border-color: #0f172a; padding: 6px 16px; font-weight: 700;">
                <i class="fa-solid fa-circle-plus"></i> Add Outfit Style
            </a>
        </div>
    </div>

    <!-- AI Callout Banner -->
    <div class="ai-banner-strip">
        <div class="ai-banner-content">
            <div class="ai-banner-icon">
                <i class="fa-solid fa-brain"></i>
            </div>
            <div>
                <strong style="font-size: 15px;">Gemini AI Automated Image Sorter Available</strong>
                <div style="font-size: 12px; color: rgba(255,255,255,0.9); margin-top: 2px;">
                    Have hundreds of mixed photoshoot images? The AI analyzes fabrics, embroidery, colors, and cuts to group matching photos into distinct Outfit Styles and create organized subfolders.
                </div>
            </div>
        </div>
        <div>
            <a href="collection-ai-sorter.php" class="button" style="background: #ffffff; color: #4f46e5; border: none; font-weight: 800; padding: 6px 16px;">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Open AI Sorter Studio
            </a>
        </div>
    </div>

    <!-- API Live Monitor Bar -->
    <div class="api-monitor-bar">
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div class="api-stat-item">
                <i class="fa-solid fa-vest-patches" style="color: var(--lookbook-accent);"></i>
                <span>Outfits in DB: <strong id="statTotalOutfits">0</strong></span>
            </div>
            <div class="api-stat-item">
                <i class="fa-solid fa-images" style="color: #059669;"></i>
                <span>Total Media: <strong id="statTotalPhotos">0</strong></span>
            </div>
            <div class="api-stat-item">
                <i class="fa-solid fa-gauge-high" style="color: #d97706;"></i>
                <span>API Speed: <strong id="statQueryTime">0 ms</strong></span>
            </div>
        </div>
        <div class="api-badge-live">
            <span class="pulse-dot"></span> AJAX Engine Connected
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="cat-tabs-container" id="categoryTabsContainer">
        <button type="button" class="cat-tab-btn active" data-cat="All" onclick="setCategoryFilter('All')">
            All Styles <span class="cat-tab-count" id="countAll">0</span>
        </button>
        <!-- Dynamically populated categories -->
    </div>

    <!-- Toolbar -->
    <div class="lookbook-toolbar">
        <div class="toolbar-left">
            <div class="search-input-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Search by title, SKU, fabric, color..." onkeyup="handleSearch(event)">
            </div>
            <select id="statusFilter" class="form-input-sm" style="width: 130px;" onchange="loadCollections(1)">
                <option value="all">All Statuses</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </select>
        </div>

        <div class="toolbar-right">
            <label style="font-size: 12px; color: #64748b;">Per page:</label>
            <select id="perPageSelect" class="form-input-sm" style="width: 70px;" onchange="loadCollections(1)">
                <option value="12">12</option>
                <option value="18" selected>18</option>
                <option value="36">36</option>
                <option value="60">60</option>
            </select>
            <button type="button" class="button" onclick="loadCollections(currentPage)"><i class="fa-solid fa-rotate"></i></button>
        </div>
    </div>

    <!-- Outfits Grid Container -->
    <div id="collectionsGrid" class="collection-grid">
        <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: #94a3b8;">
            <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
            <div style="margin-top: 10px;">Loading outfit styles...</div>
        </div>
    </div>

    <!-- Pagination Container -->
    <div id="paginationWrap" style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding: 14px 0; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 12px;">
        <div style="font-size: 13px; color: #64748b;" id="paginationInfo">Showing 0 of 0</div>
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
    }, 350);
}

function setCategoryFilter(cat) {
    currentCategory = cat;
    document.querySelectorAll('.cat-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-cat') === cat);
    });
    loadCollections(1);
}

async function loadCollections(page = 1) {
    currentPage = page;
    const grid = document.getElementById('collectionsGrid');
    grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: #94a3b8;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><div style="margin-top: 10px;">Loading outfit styles...</div></div>`;

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
    const existingButtons = container.querySelectorAll('.cat-tab-btn:not([data-cat="All"])');
    existingButtons.forEach(b => b.remove());

    categories.forEach(cat => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `cat-tab-btn ${currentCategory === cat.category ? 'active' : ''}`;
        btn.setAttribute('data-cat', cat.category);
        btn.onclick = () => setCategoryFilter(cat.category);
        btn.innerHTML = `${escapeHtml(cat.category)} <span class="cat-tab-count">${cat.count}</span>`;
        container.appendChild(btn);
    });
}

function renderCollectionCards(items) {
    const grid = document.getElementById('collectionsGrid');
    if (!items.length) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; background: #ffffff; border: 1.5px dashed #cbd5e1; border-radius: 12px;">
                <div style="width: 60px; height: 60px; background: #eef2ff; border-radius: 50%; color: #6366f1; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 14px;">
                    <i class="fa-solid fa-vest-patches"></i>
                </div>
                <h3 style="font-size: 17px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">No Outfit Styles Found</h3>
                <p style="font-size: 13px; color: #64748b; max-width: 450px; margin: 0 auto 16px auto;">
                    ${currentCategory !== 'All' 
                        ? `There are no outfits registered under <strong>${escapeHtml(currentCategory)}</strong> yet. Run the AI Sorter on your raw photoshoot folder!` 
                        : 'No outfits match the current filters.'}
                </p>
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <a href="collection-ai-sorter.php" class="button button-primary" style="background: var(--lookbook-accent); border: none; font-weight: 700;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Run Gemini AI Sorter
                    </a>
                    <a href="collection-add.php" class="button">
                        <i class="fa-solid fa-circle-plus"></i> Add Manually
                    </a>
                </div>
            </div>
        `;
        return;
    }

    grid.innerHTML = items.map(c => {
        const coverSrc = c.cover_url || 'assets/images/placeholder.svg';
        const thumbs = c.preview_images || [];
        const isFeatured = parseInt(c.is_featured) === 1;
        const isPublished = c.status === 'published';

        return `
            <div class="collection-card" id="card_${c.id}">
                <div class="card-media-wrap">
                    <img src="${coverSrc}" alt="${escapeHtml(c.title)}" class="card-media-img" onerror="this.onerror=null; this.src='assets/images/placeholder.svg';">
                    <span class="card-cat-badge">${escapeHtml(c.category || 'Collection')}</span>
                    <span class="card-photos-badge"><i class="fa-solid fa-image"></i> ${c.total_images || 0}</span>
                    <button type="button" class="card-star-btn ${isFeatured ? 'active' : ''}" onclick="toggleFeatured(${c.id})" title="${isFeatured ? 'Remove Featured' : 'Mark as Featured'}">
                        <i class="fa-solid fa-star"></i>
                    </button>
                </div>

                ${thumbs.length > 0 ? `
                    <div class="card-thumbs-strip">
                        ${thumbs.map(t => `
                            <div class="card-mini-thumb">
                                <img src="${t}" alt="Angle" onerror="this.onerror=null; this.src='assets/images/placeholder.svg';">
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                <div class="card-info">
                    <div>
                        <div class="card-tags-row">
                            ${c.sku ? `<span class="card-tag-pill" style="background: #e0e7ff; color: #3730a3; font-weight: 700;">${escapeHtml(c.sku)}</span>` : ''}
                            ${c.fabric ? `<span class="card-tag-pill">${escapeHtml(c.fabric)}</span>` : ''}
                            ${c.color ? `<span class="card-tag-pill">${escapeHtml(c.color)}</span>` : ''}
                        </div>
                        <h3 class="card-title">
                            <a href="${c.edit_url}">${escapeHtml(c.title)}</a>
                        </h3>
                        <p class="card-desc">${escapeHtml(c.description || c.subtitle || 'Designer lookbook piece.')}</p>
                    </div>

                    <div class="card-footer-bar">
                        <button type="button" class="status-pill ${isPublished ? 'published' : 'draft'}" onclick="toggleStatus(${c.id})">
                            <i class="fa-solid fa-circle" style="font-size: 7px; vertical-align: middle; margin-right: 3px;"></i>
                            ${isPublished ? 'Published' : 'Draft'}
                        </button>

                        <div class="card-actions">
                            <a href="${c.edit_url}" class="action-icon-btn" title="Edit Outfit & Media">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <button type="button" class="action-icon-btn delete" onclick="deleteCollection(${c.id}, '${escapeHtml(c.title)}')" title="Delete Outfit">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderPagination(p) {
    if (!p) return;
    document.getElementById('paginationInfo').textContent = `Showing page ${p.page} of ${p.total_pages || 1} (${p.total} total outfits)`;

    const btnsWrap = document.getElementById('paginationBtns');
    let html = '';

    if (p.has_prev) {
        html += `<button type="button" class="button" onclick="loadCollections(${p.page - 1})"><i class="fa-solid fa-chevron-left"></i> Prev</button>`;
    }
    if (p.has_next) {
        html += `<button type="button" class="button" onclick="loadCollections(${p.page + 1})">Next <i class="fa-solid fa-chevron-right"></i></button>`;
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
            const star = card.querySelector('.card-star-btn');
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
            const btn = card.querySelector('.status-pill');
            const isPub = data.status === 'published';
            btn.className = `status-pill ${isPub ? 'published' : 'draft'}`;
            btn.innerHTML = `<i class="fa-solid fa-circle" style="font-size: 7px; vertical-align: middle; margin-right: 3px;"></i> ${isPub ? 'Published' : 'Draft'}`;
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

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
