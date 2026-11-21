<?php
$page_title = "Pages, URLs & Search Analytics";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* Page Type Tabs */
.page-type-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 12px;
}
.page-type-tab {
    padding: 3px 10px;
    border: 1px solid #e4e4e7;
    border-radius: 4px;
    font-size: 11.5px;
    font-weight: 500;
    cursor: pointer;
    background: #fff;
    color: #71717a;
    transition: all 0.15s;
    text-transform: capitalize;
}
.page-type-tab:hover { border-color: #a1a1aa; color: #09090b; }
.page-type-tab.active {
    background: #18181b;
    color: #fff;
    border-color: #18181b;
}

/* Table */
.pages-table {
    width: 100%;
    border-collapse: collapse;
}
.pages-table thead th {
    background: #f8fafc;
    border-bottom: 1px solid #e4e4e7;
    padding: 8px 14px;
    font-size: 11px;
    font-weight: 600;
    color: #71717a;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    white-space: nowrap;
}
.pages-table tbody td {
    padding: 8px 14px;
    font-size: 12px;
    color: #27272a;
    border-bottom: 1px solid #f4f4f5;
    vertical-align: middle;
}
.pages-table tbody tr:hover { background: #fafafa; }
.pages-table tbody tr:last-child td { border-bottom: none; }
.url-cell {
    max-width: 320px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: inherit;
    font-size: 12px;
    color: #09090b;
    cursor: default;
    position: relative;
}
.url-cell:hover { color: #2563eb; }
.url-tooltip {
    display: none;
    position: absolute;
    bottom: 100%;
    left: 0;
    background: #18181b;
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 100;
    pointer-events: none;
}
.url-cell:hover .url-tooltip { display: block; }

/* Badges */
.badge-page-type {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 10.5px;
    font-weight: 600;
    color: #fff;
    text-transform: capitalize;
}
.badge-source {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10.5px;
    font-weight: 500;
    background: #f4f4f5;
    color: #71717a;
    border: 1px solid #e4e4e7;
}
.views-col {
    font-weight: 700;
    color: #09090b;
}

/* Pagination */
.pages-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    margin: 12px 0 16px 0;
}
.pages-pagination button {
    min-width: 28px;
    height: 28px;
    padding: 0 8px;
    border: 1px solid #e4e4e7;
    border-radius: 4px;
    background: #fff;
    color: #71717a;
    font-size: 11.5px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
}
.pages-pagination button:hover { border-color: #09090b; color: #09090b; }
.pages-pagination button.active { background: #18181b; color: #fff; border-color: #18181b; }
.pages-pagination button:disabled { opacity: 0.35; cursor: default; }
.pages-pagination .pg-info {
    font-size: 11.5px;
    color: #71717a;
    margin: 0 8px;
}

/* Search Tables */
.search-table {
    width: 100%;
    border-collapse: collapse;
}
.search-table thead th {
    background: #f8fafc;
    padding: 8px 14px;
    font-size: 11px;
    font-weight: 600;
    color: #71717a;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    border-bottom: 1px solid #e4e4e7;
}
.search-table tbody td {
    padding: 8px 14px;
    font-size: 12px;
    color: #27272a;
    border-bottom: 1px solid #f4f4f5;
}
.search-table tbody tr:last-child td { border-bottom: none; }
.search-table tbody tr:hover { background: #fafafa; }
.search-term-cell {
    font-weight: 600;
    color: #09090b;
}
.zero-badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 10.5px;
    font-weight: 600;
    background: #fee2e2;
    color: #dc2626;
}
</style>

<div class="dashboard-header-banner" style="margin-bottom: 16px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting">
            <h1>Pages, URLs &amp; Search Analytics</h1>
            <span class="shadcn-badge shadcn-badge-sky" style="font-size: 11px; padding: 2px 8px;">
                URLs &amp; Searches
            </span>
        </div>
        <p class="dashboard-subtitle">
            Track page views, traffic per URL, most visited pages, and shopper search terms.
        </p>
    </div>

    <div class="dashboard-actions">
        <!-- Date Range Selector -->
        <div style="display: flex; background: #f4f4f5; border-radius: 6px; padding: 2px; border: 1px solid #e4e4e7;">
            <button class="date-range-btn shadcn-btn shadcn-btn-ghost" data-range="today" style="height: 28px; font-size: 12px; padding: 0 10px;">Today</button>
            <button class="date-range-btn shadcn-btn shadcn-btn-primary" data-range="7days" style="height: 28px; font-size: 12px; padding: 0 10px;">7 Days</button>
            <button class="date-range-btn shadcn-btn shadcn-btn-ghost" data-range="30days" style="height: 28px; font-size: 12px; padding: 0 10px;">30 Days</button>
            <button class="date-range-btn shadcn-btn shadcn-btn-ghost" data-range="90days" style="height: 28px; font-size: 12px; padding: 0 10px;">90 Days</button>
        </div>

        <!-- Export CSV -->
        <button onclick="exportCSV()" class="shadcn-btn shadcn-btn-outline" style="height: 32px; font-size: 12px;">
            <i class="fa-solid fa-file-csv" style="margin-right: 4px;"></i> Export CSV
        </button>
    </div>
</div>

<!-- Navigation Tabs -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
        <a href="analytics.php" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Overview
        </a>
        <a href="analytics-campaigns.php" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Facebook &amp; Campaigns
        </a>
        <a href="analytics-products.php" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Products
        </a>
        <a href="analytics-categories.php" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Categories
        </a>
        <a href="analytics-pages.php" class="shadcn-btn shadcn-btn-primary" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Pages &amp; Search
        </a>
    </div>
</div>

<!-- Compact Neutral KPI Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 16px;">
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Unique URLs</span>
            <i class="fa-solid fa-link" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiTotalUrls" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">—</div>
            <div class="shadcn-stat-note" style="font-size: 11px; color: #71717a; margin-top: 2px;">Tracked site paths</div>
        </div>
    </div>

    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Most Visited Page</span>
            <i class="fa-solid fa-trophy" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div style="min-width: 0; overflow: hidden;">
            <div class="shadcn-stat-value" id="kpiMostVisited" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">—</div>
            <div class="shadcn-stat-note" id="kpiMostVisitedUrl" style="font-size: 11px; color: #71717a; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
        </div>
    </div>

    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Top Traffic Source</span>
            <i class="fa-solid fa-arrow-up-right-from-square" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiTopSource" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">—</div>
            <div class="shadcn-stat-note" style="font-size: 11px; color: #71717a; margin-top: 2px;">Primary referrer channel</div>
        </div>
    </div>

    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Search Queries</span>
            <i class="fa-solid fa-magnifying-glass" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiSearchQueries" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">—</div>
            <div class="shadcn-stat-note" style="font-size: 11px; color: #71717a; margin-top: 2px;">Storefront search requests</div>
        </div>
    </div>
</div>

<!-- Page Type Filter Tabs -->
<div class="page-type-tabs" id="pageTypeTabs">
    <button class="page-type-tab active" data-type="all" onclick="filterPageType('all', this)">All</button>
    <button class="page-type-tab" data-type="home" onclick="filterPageType('home', this)">Home</button>
    <button class="page-type-tab" data-type="product" onclick="filterPageType('product', this)">Product</button>
    <button class="page-type-tab" data-type="category" onclick="filterPageType('category', this)">Category</button>
    <button class="page-type-tab" data-type="cart" onclick="filterPageType('cart', this)">Cart</button>
    <button class="page-type-tab" data-type="checkout" onclick="filterPageType('checkout', this)">Checkout</button>
    <button class="page-type-tab" data-type="auth" onclick="filterPageType('auth', this)">Auth</button>
    <button class="page-type-tab" data-type="search" onclick="filterPageType('search', this)">Search</button>
    <button class="page-type-tab" data-type="studio" onclick="filterPageType('studio', this)">Studio</button>
    <button class="page-type-tab" data-type="portfolio" onclick="filterPageType('portfolio', this)">Portfolio</button>
    <button class="page-type-tab" data-type="other" onclick="filterPageType('other', this)">Other</button>
</div>

<!-- URL Analytics Table Card -->
<div class="shadcn-card" style="margin-bottom: 16px;">
    <div class="shadcn-card-header" style="padding: 10px 14px; border-bottom: 1px solid #e4e4e7;">
        <h2 class="shadcn-card-title"><i class="fa-solid fa-table-list" style="margin-right: 6px; color: #71717a;"></i> Page &amp; URL Performance</h2>
    </div>
    <div class="shadcn-card-body" style="padding: 0;">
        <div id="pagesTableContainer">
            <div class="loading-state" id="pagesLoading" style="padding: 30px; text-align: center; color: #71717a;">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p style="margin: 4px 0 0; font-size: 12px;">Loading page analytics...</p>
            </div>
        </div>
    </div>
</div>

<!-- Pagination -->
<div class="pages-pagination" id="pagesPagination"></div>

<!-- Search Analytics Section -->
<div style="margin-top: 14px; margin-bottom: 10px;">
    <h3 style="font-size: 13px; font-weight: 600; color: #09090b; margin: 0; display: flex; align-items: center; gap: 6px;">
        <i class="fa-solid fa-magnifying-glass-chart" style="color: #71717a;"></i> Search Analytics
    </h3>
</div>

<div class="search-analytics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px; margin-bottom: 16px;">
    <!-- Top Search Terms -->
    <div class="shadcn-card" style="margin-bottom: 0;">
        <div class="shadcn-card-header" style="padding: 10px 14px; border-bottom: 1px solid #e4e4e7; display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-arrow-trend-up" style="color: #71717a; font-size: 12px;"></i>
            <h2 class="shadcn-card-title">Top Search Terms</h2>
        </div>
        <div class="shadcn-card-body" id="topSearchContainer" style="padding: 0;">
            <div class="loading-state" style="padding: 30px; text-align: center; color: #71717a;">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p style="margin: 4px 0 0; font-size: 12px;">Loading...</p>
            </div>
        </div>
    </div>

    <!-- Zero-Result Searches -->
    <div class="shadcn-card" style="margin-bottom: 0;">
        <div class="shadcn-card-header" style="padding: 10px 14px; border-bottom: 1px solid #e4e4e7; display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-triangle-exclamation" style="color: #dc2626; font-size: 12px;"></i>
            <h2 class="shadcn-card-title">Zero-Result Searches (Missed Demand)</h2>
        </div>
        <div class="shadcn-card-body" id="zeroResultsContainer" style="padding: 0;">
            <div class="loading-state" style="padding: 30px; text-align: center; color: #71717a;">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p style="margin: 4px 0 0; font-size: 12px;">Loading...</p>
            </div>
        </div>
    </div>
</div>

<script>
const PAGE_TYPE_COLORS = {
    home: '#059669',
    product: '#0284c7',
    category: '#7c3aed',
    cart: '#d97706',
    checkout: '#dc2626',
    auth: '#64748b',
    search: '#0891b2',
    studio: '#db2777',
    portfolio: '#8b5cf6',
    other: '#94a3b8'
};

let currentPage = 1;
let currentPageType = 'all';
let pagesData = null;
let searchesData = null;
let currentRange = '7days';

function getDateRange() {
    return currentRange;
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.date-range-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.date-range-btn').forEach(b => {
                b.classList.remove('shadcn-btn-primary');
                b.classList.add('shadcn-btn-ghost');
            });
            this.classList.remove('shadcn-btn-ghost');
            this.classList.add('shadcn-btn-primary');
            currentRange = this.getAttribute('data-range');
            loadAllData();
        });
    });

    loadAllData();
});

function loadAllData() {
    currentPage = 1;
    loadPages();
    loadSearches();
}

// ── Pages Data ──
function loadPages() {
    const container = document.getElementById('pagesTableContainer');
    container.innerHTML = '<div class="loading-state"><i class="fa-solid fa-spinner"></i><p>Loading page analytics...</p></div>';
    document.getElementById('pagesPagination').innerHTML = '';

    let url = `api/analytics-data.php?action=pages&range=${getDateRange()}&page=${currentPage}&per_page=25`;
    if (currentPageType !== 'all') {
        url += `&page_type=${currentPageType}`;
    }

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error('Failed to load data');
            pagesData = data;
            renderKPIs(data);
            renderPagesTable(data);
            renderPagination(data);
        })
        .catch(err => {
            container.innerHTML = `<div class="empty-state"><i class="fa-solid fa-circle-exclamation"></i><p>Failed to load page data.</p><p style="font-size:12px;">${err.message}</p></div>`;
        });
}

function renderKPIs(data) {
    document.getElementById('kpiTotalUrls').textContent = (data.total || 0).toLocaleString();

    if (data.pages && data.pages.length > 0) {
        const sorted = [...data.pages].sort((a, b) => b.views - a.views);
        const top = sorted[0];
        document.getElementById('kpiMostVisited').textContent = top.views.toLocaleString() + ' views';
        document.getElementById('kpiMostVisitedUrl').textContent = top.page_url;
        document.getElementById('kpiMostVisitedUrl').title = top.page_url;

        // Top source across all pages
        const sourceCounts = {};
        data.pages.forEach(p => {
            if (p.top_source) {
                sourceCounts[p.top_source] = (sourceCounts[p.top_source] || 0) + 1;
            }
        });
        const topSource = Object.entries(sourceCounts).sort((a, b) => b[1] - a[1])[0];
        document.getElementById('kpiTopSource').textContent = topSource ? topSource[0] : '—';
    } else {
        document.getElementById('kpiMostVisited').textContent = '—';
        document.getElementById('kpiMostVisitedUrl').textContent = '';
        document.getElementById('kpiTopSource').textContent = '—';
    }
}

function renderPagesTable(data) {
    const container = document.getElementById('pagesTableContainer');

    if (!data.pages || data.pages.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-folder-open"></i><p>No page data found for the selected filters.</p></div>';
        return;
    }

    let html = `
        <div class="pages-table-wrap" style="border:none;border-radius:0;">
            <table class="pages-table">
                <thead>
                    <tr>
                        <th style="width:35%;">Page URL</th>
                        <th>Type</th>
                        <th>Views</th>
                        <th>Unique Visitors</th>
                        <th>Top Source</th>
                        <th>Last Visit</th>
                    </tr>
                </thead>
                <tbody>`;

    data.pages.forEach(page => {
        const typeColor = PAGE_TYPE_COLORS[page.page_type] || PAGE_TYPE_COLORS.other;
        const lastVisit = formatDate(page.last_visit);

        html += `
            <tr>
                <td>
                    <div class="url-cell" title="${escapeHtml(page.page_url)}">
                        ${escapeHtml(page.page_url)}
                        <span class="url-tooltip">${escapeHtml(page.page_url)}</span>
                    </div>
                </td>
                <td><span class="badge-page-type" style="background:${typeColor}">${escapeHtml(page.page_type)}</span></td>
                <td class="views-col">${page.views.toLocaleString()}</td>
                <td>${page.unique_ips.toLocaleString()}</td>
                <td><span class="badge-source">${escapeHtml(page.top_source || '—')}</span></td>
                <td style="color:#64748b;font-size:12.5px;">${lastVisit}</td>
            </tr>`;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function renderPagination(data) {
    const container = document.getElementById('pagesPagination');
    const totalPages = Math.ceil(data.total / data.per_page);

    if (totalPages <= 1) { container.innerHTML = ''; return; }

    let html = '';
    html += `<button ${currentPage <= 1 ? 'disabled' : ''} onclick="goToPage(${currentPage - 1})"><i class="fa-solid fa-chevron-left"></i></button>`;

    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
        html += `<button onclick="goToPage(1)">1</button>`;
        if (startPage > 2) html += `<span class="pg-info">…</span>`;
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<span class="pg-info">…</span>`;
        html += `<button onclick="goToPage(${totalPages})">${totalPages}</button>`;
    }

    html += `<button ${currentPage >= totalPages ? 'disabled' : ''} onclick="goToPage(${currentPage + 1})"><i class="fa-solid fa-chevron-right"></i></button>`;
    html += `<span class="pg-info">Page ${currentPage} of ${totalPages} (${data.total} URLs)</span>`;

    container.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    loadPages();
    document.querySelector('.postbox').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function filterPageType(type, btn) {
    currentPageType = type;
    currentPage = 1;
    document.querySelectorAll('.page-type-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    loadPages();
}

// ── Search Data ──
function loadSearches() {
    const topContainer = document.getElementById('topSearchContainer');
    const zeroContainer = document.getElementById('zeroResultsContainer');
    topContainer.innerHTML = '<div class="loading-state"><i class="fa-solid fa-spinner"></i><p>Loading...</p></div>';
    zeroContainer.innerHTML = '<div class="loading-state"><i class="fa-solid fa-spinner"></i><p>Loading...</p></div>';

    fetch(`api/analytics-data.php?action=searches&range=${getDateRange()}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error('Failed');
            searchesData = data;
            renderTopSearches(data.searches || []);
            renderZeroResults(data.zero_results || []);
            updateSearchKPI(data);
        })
        .catch(err => {
            topContainer.innerHTML = '<div class="empty-state"><i class="fa-solid fa-circle-exclamation"></i><p>Failed to load.</p></div>';
            zeroContainer.innerHTML = '<div class="empty-state"><i class="fa-solid fa-circle-exclamation"></i><p>Failed to load.</p></div>';
        });
}

function updateSearchKPI(data) {
    let totalQueries = 0;
    if (data.searches) totalQueries += data.searches.reduce((s, r) => s + (r.search_count || 0), 0);
    if (data.zero_results) totalQueries += data.zero_results.reduce((s, r) => s + (r.search_count || 0), 0);
    document.getElementById('kpiSearchQueries').textContent = totalQueries.toLocaleString();
}

function renderTopSearches(searches) {
    const container = document.getElementById('topSearchContainer');

    if (searches.length === 0) {
        container.innerHTML = '<div class="empty-state" style="padding:40px 20px;"><i class="fa-solid fa-magnifying-glass"></i><p>No search data yet.</p></div>';
        return;
    }

    let html = `
        <table class="search-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Search Term</th>
                    <th>Searches</th>
                    <th>Avg Results</th>
                </tr>
            </thead>
            <tbody>`;

    searches.forEach((s, i) => {
        html += `
            <tr>
                <td style="color:#94a3b8;font-weight:700;">${i + 1}</td>
                <td class="search-term-cell">${escapeHtml(s.search_term)}</td>
                <td><strong>${s.search_count.toLocaleString()}</strong></td>
                <td style="color:#64748b;">${s.avg_results !== undefined ? parseFloat(s.avg_results).toFixed(1) : '—'}</td>
            </tr>`;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

function renderZeroResults(results) {
    const container = document.getElementById('zeroResultsContainer');

    if (results.length === 0) {
        container.innerHTML = '<div class="empty-state" style="padding:40px 20px;"><i class="fa-solid fa-check-circle" style="color:#059669;"></i><p style="color:#059669;">No zero-result searches — great coverage!</p></div>';
        return;
    }

    let html = `
        <table class="search-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Search Term</th>
                    <th>Attempts</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>`;

    results.forEach((s, i) => {
        html += `
            <tr>
                <td style="color:#94a3b8;font-weight:700;">${i + 1}</td>
                <td class="search-term-cell">${escapeHtml(s.search_term)}</td>
                <td><strong>${s.search_count.toLocaleString()}</strong></td>
                <td><span class="zero-badge">0 results</span></td>
            </tr>`;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

// ── Export CSV ──
function exportCSV() {
    if (!pagesData || !pagesData.pages || pagesData.pages.length === 0) {
        alert('No data to export.');
        return;
    }

    const headers = ['Page URL', 'Page Type', 'Views', 'Unique Visitors', 'Top Source', 'Last Visit'];
    const rows = pagesData.pages.map(p => [
        '"' + (p.page_url || '').replace(/"/g, '""') + '"',
        p.page_type,
        p.views,
        p.unique_ips,
        p.top_source || '',
        p.last_visit || ''
    ]);

    let csv = headers.join(',') + '\n';
    rows.forEach(r => csv += r.join(',') + '\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `page-analytics-${getDateRange()}-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// ── Helpers ──
function escapeHtml(str) {
    if (!str) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(str).replace(/[&<>"']/g, c => map[c]);
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const day = d.getDate();
    const month = months[d.getMonth()];
    const year = d.getFullYear();
    let hours = d.getHours();
    const mins = String(d.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    return `${day} ${month} ${year}, ${hours}:${mins} ${ampm}`;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
