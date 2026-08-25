<?php
// admin/collections.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Collections & Lookbook Manager";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* Modern API-Powered Collections Manager Styles */
.api-monitor-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    padding: 10px 16px;
    margin: 14px 0 16px 0;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    flex-wrap: wrap;
    gap: 12px;
}
.api-stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #50575e;
}
.api-stat-item strong {
    color: #1d2327;
    font-weight: 700;
}
.api-badge-live {
    background: #e7f7ed;
    color: #008a20;
    border: 1px solid #9ee2b2;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.api-badge-live .pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #008a20;
    animation: pulseGlow 1.5s infinite;
}
@keyframes pulseGlow {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.5; }
}

/* Grid Layout */
.collection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-top: 16px;
}

.collection-card {
    background: #ffffff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    overflow: hidden;
    display: flex;
    flex-col;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s;
}
.collection-card:hover {
    box-shadow: 0 6px 18px rgba(0,0,0,0.1);
    transform: translateY(-2px);
    border-color: #2271b1;
}

.card-media-wrap {
    position: relative;
    aspect-ratio: 16 / 10;
    background: #f0f0f1;
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
    transform: scale(1.03);
}

.card-cat-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(17, 24, 39, 0.85);
    color: #f3f4f6;
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
    color: #1d2327;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 4px;
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
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c3c4c7;
    cursor: pointer;
    transition: transform 0.2s, color 0.2s;
}
.card-star-btn.active {
    color: #dba617;
}
.card-star-btn:hover {
    transform: scale(1.15);
}

.card-thumbs-strip {
    display: flex;
    gap: 4px;
    padding: 6px 10px;
    background: #f6f7f7;
    border-top: 1px solid #f0f0f1;
    border-bottom: 1px solid #f0f0f1;
    min-height: 48px;
}
.card-mini-thumb {
    flex: 1;
    height: 42px;
    border-radius: 4px;
    overflow: hidden;
    background: #e2e4e7;
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
    color: #1d2327;
    margin: 0 0 4px 0;
    line-height: 1.35;
}
.card-title a {
    color: inherit;
    text-decoration: none;
}
.card-title a:hover {
    color: #2271b1;
}
.card-subtitle {
    font-size: 12px;
    color: #646970;
    margin: 0 0 8px 0;
}
.card-desc {
    font-size: 12px;
    color: #50575e;
    line-height: 1.45;
    margin-bottom: 12px;
}

.card-actions-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 10px;
    border-top: 1px solid #f0f0f1;
    margin-top: auto;
}

/* Skeleton Loading Animation */
.skeleton-card {
    background: #ffffff;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    overflow: hidden;
    min-height: 320px;
}
.skeleton-box {
    background: linear-gradient(90deg, #f0f0f1 25%, #f6f7f7 50%, #f0f0f1 75%);
    background-size: 200% 100%;
    animation: skeletonShimmer 1.5s infinite;
}
@keyframes skeletonShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Toast Notifications */
.ajax-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #1d2327;
    color: #ffffff;
    padding: 12px 20px;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    font-size: 13px;
    font-weight: 600;
    z-index: 99999;
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.3s, transform 0.3s;
    pointer-events: none;
}
.ajax-toast.show {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}
</style>

<div class="wrap">
    
    <!-- Header -->
    <h1 class="wp-heading-inline">
        <i class="fa-solid fa-camera-retro" style="color: #2271b1; margin-right: 6px;"></i> Collections &amp; Lookbook Manager
    </h1>
    <a href="collection-add.php" class="page-title-action">Add New Collection</a>
    <hr class="wp-header-end">

    <!-- Real-Time API Monitor Bar -->
    <div class="api-monitor-bar">
        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <div class="api-stat-item">
                <span class="api-badge-live"><span class="pulse-dot"></span> API Engine Active</span>
            </div>
            <div class="api-stat-item">
                <span>⚡ Latency:</span> <strong id="statLatency">0 ms</strong>
            </div>
            <div class="api-stat-item">
                <span>📁 Collections:</span> <strong id="statCollections">0</strong>
            </div>
            <div class="api-stat-item">
                <span>📸 Shoot Photos:</span> <strong id="statPhotos">0</strong>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
            <button onclick="refreshData()" class="button button-small" title="Refresh via API">
                <i class="fa-solid fa-arrows-rotate" id="refreshIcon"></i> Sync Data
            </button>
        </div>
    </div>

    <!-- Category Tabs Navigation -->
    <ul class="subsubsub" id="categoryTabsContainer" style="margin-bottom: 14px;">
        <li class="all">
            <a href="javascript:void(0)" onclick="setCategoryFilter('')" class="current" id="tab-all">
                All Collections <span class="count" id="count-all">(0)</span>
            </a> |
        </li>
    </ul>

    <!-- Filter & Search Controls Bar -->
    <div class="tablenav top" style="clear: both; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
        <div class="alignleft actions" style="display: flex; gap: 8px; align-items: center;">
            <div style="position: relative;">
                <input type="search" id="searchInput" placeholder="Search collections (live API)..." style="width: 240px; padding-left: 28px;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 9px; color: #8c8f94; font-size: 12px;"></i>
            </div>
            
            <select id="statusFilter" onchange="onStatusFilterChange()">
                <option value="all">All Statuses</option>
                <option value="published">Published Only</option>
                <option value="draft">Drafts Only</option>
            </select>

            <button type="button" onclick="resetFilters()" class="button" id="resetBtn" style="display: none;">Reset</button>
        </div>

        <!-- View Mode Switcher -->
        <div class="alignright actions">
            <button onclick="setViewMode('grid')" class="button button-primary" id="btnViewGrid" title="Visual Cards Grid">
                <i class="fa-solid fa-table-cells"></i> Visual Grid
            </button>
            <button onclick="setViewMode('table')" class="button" id="btnViewTable" title="Table List">
                <i class="fa-solid fa-list"></i> Table List
            </button>
        </div>
    </div>

    <!-- Dynamic Container: Grid or Table View -->
    <div id="collectionsContainer">
        <!-- Rendered via JavaScript API Call -->
    </div>

    <!-- Pagination Controls -->
    <div class="tablenav bottom" id="paginationContainer" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 8px 0;">
        <!-- Filled dynamically -->
    </div>

</div>

<!-- Ajax Toast Alert -->
<div id="ajaxToast" class="ajax-toast">
    <i class="fa-solid fa-circle-check" style="color: #46b450;"></i>
    <span id="toastMessage">Success</span>
</div>

<script>
let currentCat = '';
let currentSearch = '';
let currentStatus = 'all';
let currentPage = 1;
let currentView = 'grid';
let searchTimeout = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Check URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('cat')) currentCat = urlParams.get('cat');
    if (urlParams.get('s')) currentSearch = urlParams.get('s');
    if (urlParams.get('view')) currentView = urlParams.get('view');
    
    if (currentSearch) {
        document.getElementById('searchInput').value = currentSearch;
        document.getElementById('resetBtn').style.display = 'inline-block';
    }

    updateViewButtons();
    loadCollections();

    // Debounced search input handler
    document.getElementById('searchInput').addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        currentSearch = e.target.value.trim();
        currentPage = 1;
        document.getElementById('resetBtn').style.display = currentSearch || currentCat || currentStatus !== 'all' ? 'inline-block' : 'none';
        
        searchTimeout = setTimeout(() => {
            loadCollections();
        }, 250);
    });
});

// Toast notification helper
function showToast(msg, isSuccess = true) {
    const toast = document.getElementById('ajaxToast');
    const msgEl = document.getElementById('toastMessage');
    msgEl.innerText = msg;
    toast.className = 'ajax-toast show';
    setTimeout(() => {
        toast.className = 'ajax-toast';
    }, 2800);
}

// Set View Mode
function setViewMode(mode) {
    currentView = mode;
    updateViewButtons();
    renderData();
}

function updateViewButtons() {
    const gridBtn = document.getElementById('btnViewGrid');
    const tblBtn = document.getElementById('btnViewTable');
    if (currentView === 'grid') {
        gridBtn.className = 'button button-primary';
        tblBtn.className = 'button';
    } else {
        gridBtn.className = 'button';
        tblBtn.className = 'button button-primary';
    }
}

// Category filter
function setCategoryFilter(cat) {
    currentCat = cat;
    currentPage = 1;
    document.getElementById('resetBtn').style.display = currentSearch || currentCat || currentStatus !== 'all' ? 'inline-block' : 'none';
    loadCollections();
}

function onStatusFilterChange() {
    currentStatus = document.getElementById('statusFilter').value;
    currentPage = 1;
    document.getElementById('resetBtn').style.display = currentSearch || currentCat || currentStatus !== 'all' ? 'inline-block' : 'none';
    loadCollections();
}

function resetFilters() {
    currentCat = '';
    currentSearch = '';
    currentStatus = 'all';
    currentPage = 1;
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('resetBtn').style.display = 'none';
    loadCollections();
}

function refreshData() {
    const icon = document.getElementById('refreshIcon');
    icon.classList.add('fa-spin');
    loadCollections(() => {
        icon.classList.remove('fa-spin');
        showToast('Data synced from server!');
    });
}

// Store last API response
let lastApiResponse = null;

// Core API Fetch Function
function loadCollections(onComplete) {
    renderSkeletons();

    const startTime = performance.now();
    const params = new URLSearchParams({
        action: 'list',
        page: currentPage,
        per_page: currentView === 'grid' ? 18 : 25,
        s: currentSearch,
        cat: currentCat,
        status: currentStatus
    });

    fetch(`api/admin_collections.php?${params.toString()}`)
        .then(res => res.json())
        .then(json => {
            const latency = Math.round(performance.now() - startTime);
            document.getElementById('statLatency').innerText = `${latency} ms (DB: ${json.stats ? json.stats.query_time_ms : 0}ms)`;
            
            if (json.stats) {
                document.getElementById('statCollections').innerText = json.stats.total_collections;
                document.getElementById('statPhotos').innerText = json.stats.total_photos.toLocaleString();
            }

            if (json.success) {
                lastApiResponse = json;
                updateCategoryTabs(json.categories, json.stats.total_collections);
                renderData();
                renderPagination(json.pagination);
            } else {
                document.getElementById('collectionsContainer').innerHTML = `
                    <div class="notice notice-error"><p>${json.message || 'Error loading collections'}</p></div>
                `;
            }
        })
        .catch(err => {
            console.error("API error:", err);
            document.getElementById('collectionsContainer').innerHTML = `
                <div class="notice notice-error"><p>Network error connecting to API: ${err.message}</p></div>
            `;
        })
        .finally(() => {
            if (onComplete) onComplete();
        });
}

// Render Skeletons during fetch
function renderSkeletons() {
    const container = document.getElementById('collectionsContainer');
    if (currentView === 'grid') {
        container.innerHTML = `
            <div class="collection-grid">
                ${Array.from({length: 6}).map(() => `
                    <div class="skeleton-card">
                        <div class="skeleton-box" style="height: 180px; width: 100%;"></div>
                        <div style="padding: 14px;">
                            <div class="skeleton-box" style="height: 16px; width: 70%; margin-bottom: 8px; border-radius: 4px;"></div>
                            <div class="skeleton-box" style="height: 12px; width: 40%; margin-bottom: 12px; border-radius: 4px;"></div>
                            <div class="skeleton-box" style="height: 12px; width: 90%; margin-bottom: 6px; border-radius: 4px;"></div>
                            <div class="skeleton-box" style="height: 12px; width: 80%; border-radius: 4px;"></div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    } else {
        container.innerHTML = `
            <div style="padding: 20px; text-align: center; color: #646970;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 8px;"></i>
                <p>Loading table records via API...</p>
            </div>
        `;
    }
}

// Update Category Tabs HTML
function updateCategoryTabs(categories, totalAll) {
    const container = document.getElementById('categoryTabsContainer');
    if (!categories) return;

    let html = `
        <li class="all">
            <a href="javascript:void(0)" onclick="setCategoryFilter('')" class="${currentCat === '' ? 'current' : ''}">
                All Collections <span class="count">(${totalAll})</span>
            </a> |
        </li>
    `;

    categories.forEach((c, idx) => {
        const isSelected = currentCat === c.category;
        const isLast = idx === categories.length - 1;
        html += `
            <li>
                <a href="javascript:void(0)" onclick="setCategoryFilter('${encodeURIComponent(c.category)}')" class="${isSelected ? 'current' : ''}">
                    ${escapeHtml(c.category)} <span class="count">(${c.count})</span>
                </a> ${isLast ? '' : '|'}
            </li>
        `;
    });

    container.innerHTML = html;
}

// Render Data (Grid or Table)
function renderData() {
    if (!lastApiResponse || !lastApiResponse.data) return;
    const items = lastApiResponse.data;
    const container = document.getElementById('collectionsContainer');

    if (items.length === 0) {
        container.innerHTML = `
            <div class="card" style="padding: 40px; text-align: center; margin-top: 15px;">
                <i class="fa-solid fa-camera" style="font-size: 36px; color: #8c8f94; margin-bottom: 12px;"></i>
                <h2>No collections found matching your criteria.</h2>
                <p><a href="collection-add.php" class="button button-primary">Create New Collection</a></p>
            </div>
        `;
        return;
    }

    if (currentView === 'grid') {
        let gridHtml = '<div class="collection-grid">';
        items.forEach(c => {
            const isFeatured = parseInt(c.is_featured) === 1;
            const isPublished = c.status === 'published';
            const thumbs = c.preview_images || [];

            gridHtml += `
                <div class="collection-card" id="card-${c.id}">
                    <div class="card-media-wrap">
                        <img src="${escapeHtml(c.cover_url)}" alt="${escapeHtml(c.title)}" class="card-media-img" loading="lazy">
                        
                        <span class="card-cat-badge">${escapeHtml(c.category || 'Collection')}</span>
                        <span class="card-photos-badge"><i class="fa-solid fa-images" style="color: #2271b1;"></i> ${c.total_images}</span>
                        
                        <button class="card-star-btn ${isFeatured ? 'active' : ''}" onclick="toggleFeatured(${c.id})" title="${isFeatured ? 'Featured (Click to Remove)' : 'Click to Feature'}">
                            <i class="fa-solid fa-star"></i>
                        </button>
                    </div>

                    ${thumbs.length > 1 ? `
                        <div class="card-thumbs-strip">
                            ${thumbs.map(tUrl => `
                                <div class="card-mini-thumb">
                                    <img src="${escapeHtml(tUrl)}" alt="Thumb" loading="lazy">
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}

                    <div class="card-info">
                        <div>
                            <h3 class="card-title">
                                <a href="${c.edit_url}">${escapeHtml(c.title)}</a>
                            </h3>
                            ${c.subtitle ? `<div class="card-subtitle">${escapeHtml(c.subtitle)}</div>` : ''}
                            ${c.description ? `<div class="card-desc">${escapeHtml(c.description.substring(0, 95))}...</div>` : ''}
                        </div>

                        <div class="card-actions-bar">
                            <button onclick="toggleStatus(${c.id})" class="button button-small" style="font-size: 11px; font-weight: 600;">
                                ${isPublished ? '<span style="color: #008a20;"><i class="fa-solid fa-circle" style="font-size: 8px;"></i> Published</span>' : '<span style="color: #646970;"><i class="fa-regular fa-circle" style="font-size: 8px;"></i> Draft</span>'}
                            </button>

                            <div style="display: flex; gap: 6px;">
                                <a href="${c.edit_url}" class="button button-primary button-small">
                                    <i class="fa-solid fa-pen-to-square"></i> Manage (${c.total_images})
                                </a>
                                <button onclick="deleteCollection(${c.id}, '${escapeHtml(c.title)}')" class="button button-small" style="color: #b32d2e;" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        gridHtml += '</div>';
        container.innerHTML = gridHtml;
    } else {
        // Table View
        let tblHtml = `
            <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th scope="col" style="width: 80px;">Cover</th>
                        <th scope="col">Title &amp; Subtitle</th>
                        <th scope="col" style="width: 140px;">Category</th>
                        <th scope="col" style="width: 100px; text-align: center;">Photos</th>
                        <th scope="col" style="width: 90px; text-align: center;">Featured</th>
                        <th scope="col" style="width: 110px;">Status</th>
                        <th scope="col" style="width: 160px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        items.forEach(c => {
            const isFeatured = parseInt(c.is_featured) === 1;
            const isPublished = c.status === 'published';

            tblHtml += `
                <tr id="row-${c.id}">
                    <td>
                        <img src="${escapeHtml(c.cover_url)}" alt="Cover" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #dcdcde;" loading="lazy">
                    </td>
                    <td>
                        <strong><a href="${c.edit_url}" style="font-size: 14px;">${escapeHtml(c.title)}</a></strong>
                        ${c.subtitle ? `<div style="color: #646970; font-size: 12px; margin-top: 2px;">${escapeHtml(c.subtitle)}</div>` : ''}
                    </td>
                    <td>
                        <span class="badge" style="background: #f0f0f1; border: 1px solid #c3c4c7; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                            ${escapeHtml(c.category || 'General')}
                        </span>
                    </td>
                    <td style="text-align: center;">
                        <strong style="color: #2271b1;">${c.total_images}</strong> photos
                    </td>
                    <td style="text-align: center;">
                        <button onclick="toggleFeatured(${c.id})" style="background: none; border: none; font-size: 16px; cursor: pointer; color: ${isFeatured ? '#dba617' : '#c3c4c7'};">
                            <i class="fa-solid fa-star"></i>
                        </button>
                    </td>
                    <td>
                        <button onclick="toggleStatus(${c.id})" class="button button-small" style="font-size: 11px; font-weight: 600;">
                            ${isPublished ? '<span style="color: #008a20;">Published</span>' : '<span style="color: #646970;">Draft</span>'}
                        </button>
                    </td>
                    <td style="text-align: right;">
                        <a href="${c.edit_url}" class="button button-small"><i class="fa-solid fa-pen"></i> Edit</a>
                        <button onclick="deleteCollection(${c.id}, '${escapeHtml(c.title)}')" class="button button-small" style="color: #b32d2e;"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        tblHtml += '</tbody></table>';
        container.innerHTML = tblHtml;
    }
}

// Render Pagination
function renderPagination(pg) {
    const container = document.getElementById('paginationContainer');
    if (!pg || pg.total_pages <= 1) {
        container.innerHTML = `<span class="displaying-num">${pg ? pg.total : 0} items</span>`;
        return;
    }

    let html = `
        <span class="displaying-num">${pg.total} items (Page ${pg.page} of ${pg.total_pages})</span>
        <div class="tablenav-pages" style="display: flex; gap: 4px; align-items: center;">
            <button class="button ${pg.has_prev ? '' : 'disabled'}" ${pg.has_prev ? `onclick="goToPage(${pg.page - 1})"` : 'disabled'}>
                &laquo; Prev
            </button>
    `;

    for (let p = 1; p <= pg.total_pages; p++) {
        if (p === 1 || p === pg.total_pages || Math.abs(p - pg.page) <= 2) {
            html += `
                <button class="button ${p === pg.page ? 'button-primary' : ''}" onclick="goToPage(${p})">
                    ${p}
                </button>
            `;
        } else if (Math.abs(p - pg.page) === 3) {
            html += `<span style="padding: 0 4px;">...</span>`;
        }
    }

    html += `
            <button class="button ${pg.has_next ? '' : 'disabled'}" ${pg.has_next ? `onclick="goToPage(${pg.page + 1})"` : 'disabled'}>
                Next &raquo;
            </button>
        </div>
    `;

    container.innerHTML = html;
}

function goToPage(p) {
    currentPage = p;
    loadCollections();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Toggle Featured via AJAX
function toggleFeatured(id) {
    const fd = new FormData();
    fd.append('id', id);

    fetch('api/admin_collections.php?action=toggle_featured', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            loadCollections();
        } else {
            alert(data.message || 'Error updating featured status');
        }
    })
    .catch(err => alert('Network error: ' + err.message));
}

// Toggle Status via AJAX
function toggleStatus(id) {
    const fd = new FormData();
    fd.append('id', id);

    fetch('api/admin_collections.php?action=toggle_status', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            loadCollections();
        } else {
            alert(data.message || 'Error updating status');
        }
    })
    .catch(err => alert('Network error: ' + err.message));
}

// Delete Collection via AJAX
function deleteCollection(id, title) {
    if (!confirm(`Are you sure you want to delete collection "${title}" and its linked photos?`)) return;

    const fd = new FormData();
    fd.append('id', id);

    fetch('api/admin_collections.php?action=delete', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            loadCollections();
        } else {
            alert(data.message || 'Error deleting collection');
        }
    })
    .catch(err => alert('Network error: ' + err.message));
}

// Helper to escape HTML safely
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
