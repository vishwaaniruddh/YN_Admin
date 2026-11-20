<?php
$page_title = "Pages, URLs & Search Analytics";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* KPI Cards */
.pages-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.pages-kpi-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 22px 24px;
    display: flex;
    align-items: center;
    gap: 18px;
    transition: box-shadow 0.2s, transform 0.15s;
}
.pages-kpi-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}
.pages-kpi-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: #fff;
    flex-shrink: 0;
}
.pages-kpi-icon.blue { background: linear-gradient(135deg, #0284c7, #0ea5e9); }
.pages-kpi-icon.emerald { background: linear-gradient(135deg, #059669, #10b981); }
.pages-kpi-icon.amber { background: linear-gradient(135deg, #d97706, #f59e0b); }
.pages-kpi-icon.rose { background: linear-gradient(135deg, #e11d48, #f43f5e); }
.pages-kpi-info h4 {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
    margin: 0 0 4px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.pages-kpi-info .kpi-value {
    font-size: 26px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    line-height: 1.1;
}
.pages-kpi-info .kpi-sub {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

/* Controls Row */
.pages-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.pages-controls-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.date-range-select {
    padding: 8px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    color: #334155;
    background: #fff;
    cursor: pointer;
    outline: none;
    transition: border-color 0.2s;
}
.date-range-select:focus { border-color: #0284c7; }
.btn-export-csv {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    background: #059669;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-export-csv:hover { background: #047857; }

/* Page Type Tabs */
.page-type-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 20px;
}
.page-type-tab {
    padding: 6px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    background: #fff;
    color: #64748b;
    transition: all 0.2s;
    text-transform: capitalize;
}
.page-type-tab:hover { border-color: #94a3b8; color: #334155; }
.page-type-tab.active {
    background: #0284c7;
    color: #fff;
    border-color: #0284c7;
}

/* Table */
.pages-table-wrap {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    margin-bottom: 24px;
}
.pages-table {
    width: 100%;
    border-collapse: collapse;
}
.pages-table thead th {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    white-space: nowrap;
}
.pages-table tbody td {
    padding: 12px 16px;
    font-size: 13.5px;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.pages-table tbody tr:hover { background: #f8fafc; }
.pages-table tbody tr:last-child td { border-bottom: none; }
.url-cell {
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: 'Courier New', monospace;
    font-size: 12.5px;
    color: #0284c7;
    cursor: default;
    position: relative;
}
.url-cell:hover { color: #0369a1; }
.url-tooltip {
    display: none;
    position: absolute;
    bottom: 100%;
    left: 0;
    background: #1e293b;
    color: #fff;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 100;
    pointer-events: none;
}
.url-cell:hover .url-tooltip { display: block; }

/* Badges */
.badge-page-type {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
    text-transform: capitalize;
}
.badge-source {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    background: #f1f5f9;
    color: #475569;
}
.views-col {
    font-weight: 700;
    color: #1e293b;
}

/* Pagination */
.pages-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    margin-bottom: 32px;
}
.pages-pagination button {
    min-width: 36px;
    height: 36px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #475569;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.pages-pagination button:hover { border-color: #0284c7; color: #0284c7; }
.pages-pagination button.active { background: #0284c7; color: #fff; border-color: #0284c7; }
.pages-pagination button:disabled { opacity: 0.4; cursor: default; }
.pages-pagination .pg-info {
    font-size: 12px;
    color: #94a3b8;
    margin: 0 10px;
}

/* Search Analytics Section */
.search-analytics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}
@media (max-width: 900px) {
    .search-analytics-grid { grid-template-columns: 1fr; }
}
.search-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}
.search-card.zero-results { border-color: #fcd34d; }
.search-card-header {
    padding: 16px 20px;
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.search-card.zero-results .search-card-header {
    background: linear-gradient(90deg, #fffbeb, #fff1f2);
    color: #92400e;
    border-bottom-color: #fcd34d;
}
.search-card-body { padding: 0; }
.search-table {
    width: 100%;
    border-collapse: collapse;
}
.search-table thead th {
    background: #f8fafc;
    padding: 10px 16px;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}
.search-table tbody td {
    padding: 10px 16px;
    font-size: 13px;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
}
.search-table tbody tr:last-child td { border-bottom: none; }
.search-table tbody tr:hover { background: #f8fafc; }
.search-term-cell {
    font-weight: 600;
    color: #1e293b;
}
.zero-results .search-term-cell { color: #dc2626; }
.zero-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    background: #fee2e2;
    color: #dc2626;
}

/* Loading / Empty */
.loading-state, .empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
}
.loading-state i { font-size: 32px; margin-bottom: 12px; animation: spin 1s linear infinite; }
.empty-state i { font-size: 40px; margin-bottom: 12px; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<div class="content-area">
    <!-- Page Header -->
    <div class="content-header">
        <h1><i class="fa-solid fa-globe" style="color:#0284c7;margin-right:8px;"></i> Pages, URLs & Search Analytics</h1>
    </div>

    <!-- Controls -->
    <div class="pages-controls">
        <div class="pages-controls-left">
            <select class="date-range-select" id="dateRange" onchange="loadAllData()">
                <option value="today">Today</option>
                <option value="7days" selected>Last 7 Days</option>
                <option value="30days">Last 30 Days</option>
                <option value="90days">Last 90 Days</option>
            </select>
        </div>
        <button class="btn-export-csv" onclick="exportCSV()">
            <i class="fa-solid fa-file-csv"></i> Export CSV
        </button>
    </div>

    <!-- KPI Cards -->
    <div class="pages-kpi-grid">
        <div class="pages-kpi-card">
            <div class="pages-kpi-icon blue"><i class="fa-solid fa-link"></i></div>
            <div class="pages-kpi-info">
                <h4>Unique URLs Visited</h4>
                <div class="kpi-value" id="kpiTotalUrls">—</div>
            </div>
        </div>
        <div class="pages-kpi-card">
            <div class="pages-kpi-icon emerald"><i class="fa-solid fa-trophy"></i></div>
            <div class="pages-kpi-info">
                <h4>Most Visited Page</h4>
                <div class="kpi-value" id="kpiMostVisited">—</div>
                <div class="kpi-sub" id="kpiMostVisitedUrl"></div>
            </div>
        </div>
        <div class="pages-kpi-card">
            <div class="pages-kpi-icon amber"><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
            <div class="pages-kpi-info">
                <h4>Top Traffic Source</h4>
                <div class="kpi-value" id="kpiTopSource">—</div>
            </div>
        </div>
        <div class="pages-kpi-card">
            <div class="pages-kpi-icon rose"><i class="fa-solid fa-magnifying-glass"></i></div>
            <div class="pages-kpi-info">
                <h4>Total Search Queries</h4>
                <div class="kpi-value" id="kpiSearchQueries">—</div>
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

    <!-- URL Analytics Table -->
    <div class="postbox">
        <div class="postbox-header">
            <h2><i class="fa-solid fa-table-list"></i> Page & URL Performance</h2>
        </div>
        <div class="postbox-body" style="padding:0;">
            <div id="pagesTableContainer">
                <div class="loading-state" id="pagesLoading">
                    <i class="fa-solid fa-spinner"></i>
                    <p>Loading page analytics...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="pages-pagination" id="pagesPagination"></div>

    <!-- Search Analytics Section -->
    <div class="content-header" style="margin-top:12px;">
        <h2><i class="fa-solid fa-magnifying-glass-chart" style="color:#e11d48;margin-right:8px;"></i> Search Analytics</h2>
    </div>

    <div class="search-analytics-grid">
        <!-- Top Search Terms -->
        <div class="search-card">
            <div class="search-card-header">
                <i class="fa-solid fa-arrow-trend-up" style="color:#059669;"></i> Top Search Terms
            </div>
            <div class="search-card-body" id="topSearchContainer">
                <div class="loading-state">
                    <i class="fa-solid fa-spinner"></i>
                    <p>Loading...</p>
                </div>
            </div>
        </div>

        <!-- Zero-Result Searches -->
        <div class="search-card zero-results">
            <div class="search-card-header">
                <i class="fa-solid fa-triangle-exclamation" style="color:#dc2626;"></i> Zero-Result Searches (Missed Opportunities)
            </div>
            <div class="search-card-body" id="zeroResultsContainer">
                <div class="loading-state">
                    <i class="fa-solid fa-spinner"></i>
                    <p>Loading...</p>
                </div>
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

function getDateRange() {
    return document.getElementById('dateRange').value;
}

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

// ── Init ──
document.addEventListener('DOMContentLoaded', loadAllData);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
