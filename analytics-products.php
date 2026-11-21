<?php
$page_title = "Product Analytics";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="dashboard-header-banner" style="margin-bottom: 16px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting">
            <h1>Product Analytics</h1>
            <span class="shadcn-badge shadcn-badge-sky" style="font-size: 11px; padding: 2px 8px;">
                Views &amp; Conversions
            </span>
        </div>
        <p class="dashboard-subtitle">
            Track product page views, unique shoppers, conversion rates, and purchase intent.
        </p>
    </div>

    <div class="dashboard-actions">
        <!-- Date Range Selector -->
        <div style="display: flex; background: #f4f4f5; border-radius: 6px; padding: 2px; border: 1px solid #e4e4e7;">
            <button class="date-range-btn shadcn-btn shadcn-btn-primary" data-range="today" style="height: 28px; font-size: 12px; padding: 0 10px;">Today</button>
            <button class="date-range-btn shadcn-btn shadcn-btn-ghost" data-range="7days" style="height: 28px; font-size: 12px; padding: 0 10px;">7 Days</button>
            <button class="date-range-btn shadcn-btn shadcn-btn-ghost" data-range="30days" style="height: 28px; font-size: 12px; padding: 0 10px;">30 Days</button>
            <button class="date-range-btn shadcn-btn shadcn-btn-ghost" data-range="90days" style="height: 28px; font-size: 12px; padding: 0 10px;">90 Days</button>
        </div>

        <!-- Export CSV -->
        <button id="exportCsvBtn" onclick="exportCSV()" class="shadcn-btn shadcn-btn-outline" style="height: 32px; font-size: 12px;">
            <i class="fa-solid fa-file-csv" style="margin-right: 4px;"></i> Export CSV
        </button>
    </div>
</div>

<!-- Navigation Tabs (Aligned with products.php tab bar) -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
        <a href="analytics.php" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Overview
        </a>
        <a href="analytics-campaigns.php" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Facebook &amp; Campaigns
        </a>
        <a href="analytics-products.php" class="shadcn-btn shadcn-btn-primary" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Products
        </a>
        <a href="analytics-categories.php" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Categories
        </a>
        <a href="analytics-pages.php" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Pages &amp; Search
        </a>
    </div>
</div>

<!-- Compact Neutral KPI Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 16px;">
    <!-- Total Page Views -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Total Product Views</span>
            <i class="fa-solid fa-eye" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiTotalViews" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">0</div>
            <div class="shadcn-stat-note" style="font-size: 11px; color: #71717a; margin-top: 2px;">Catalog visits in period</div>
        </div>
    </div>

    <!-- Most Viewed Product -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Most Viewed Item</span>
            <i class="fa-solid fa-fire" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div style="min-width: 0; overflow: hidden;">
            <div class="shadcn-stat-value" id="kpiMostViewedCount" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">0 views</div>
            <div class="shadcn-stat-note" id="kpiMostViewed" style="font-size: 11px; color: #71717a; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="No product views yet">No views recorded yet</div>
        </div>
    </div>

    <!-- Highest Conversion -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Top Conversion</span>
            <i class="fa-solid fa-arrow-trend-up" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div style="min-width: 0; overflow: hidden;">
            <div class="shadcn-stat-value" id="kpiHighestConversionRate" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">0.00%</div>
            <div class="shadcn-stat-note" id="kpiHighestConversion" style="font-size: 11px; color: #71717a; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="No orders yet">No orders recorded yet</div>
        </div>
    </div>

    <!-- Total Orders from Views -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Orders from Views</span>
            <i class="fa-solid fa-bag-shopping" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiTotalOrders" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">0</div>
            <div class="shadcn-stat-note" style="font-size: 11px; color: #71717a; margin-top: 2px;">Converted store purchases</div>
        </div>
    </div>
</div>

<!-- Products Table Card -->
<div class="shadcn-card" style="margin-bottom: 16px;">
    <div class="shadcn-card-header" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; flex-wrap: wrap; gap: 8px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <h2 class="shadcn-card-title"><i class="fa-solid fa-box-open" style="margin-right: 6px; color: #71717a;"></i> Product Performance</h2>
            <span id="productCount" class="shadcn-badge shadcn-badge-sky" style="font-size: 10.5px; padding: 1px 6px;"></span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <input type="text" id="searchInput" placeholder="Search by name or SKU..." class="form-control" style="height: 30px; font-size: 12px; width: 220px;">
        </div>
    </div>
    <div class="shadcn-card-body">
            <!-- Loading State -->
            <div id="loadingState" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; gap: 12px;">
                <div style="width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                <p style="margin: 0; font-size: 14px; color: #64748b;">Loading product analytics...</p>
            </div>

            <!-- Empty State -->
            <div id="emptyState" style="display: none; text-align: center; padding: 60px 20px;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: #f1f5f9; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                    <i class="fa-solid fa-box-open" style="font-size: 28px; color: #94a3b8;"></i>
                </div>
                <h3 style="margin: 0 0 6px; font-size: 16px; font-weight: 600; color: #334155;">No Products Found</h3>
                <p id="emptyMessage" style="margin: 0; font-size: 14px; color: #64748b;">There are no products with analytics data for the selected period.</p>
            </div>

            <!-- Error State -->
            <div id="errorState" style="display: none; text-align: center; padding: 60px 20px;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: #fef2f2; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 28px; color: #ef4444;"></i>
                </div>
                <h3 style="margin: 0 0 6px; font-size: 16px; font-weight: 600; color: #334155;">Failed to Load Data</h3>
                <p id="errorMessage" style="margin: 0 0 16px; font-size: 14px; color: #64748b;">Something went wrong while fetching analytics.</p>
                <button onclick="loadProducts()" style="padding: 8px 20px; font-size: 13px; font-weight: 500; color: #fff; background: #3b82f6; border: none; border-radius: 8px; cursor: pointer;">Try Again</button>
            </div>

            <!-- Table -->
            <div id="tableWrapper" style="display: none; overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped" style="border: none; border-collapse: collapse; width: 100%;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e2e8f0; width: 50px; cursor: pointer; user-select: none;" data-sort="rank" onclick="sortTable('rank')"># <i class="fa-solid fa-sort" style="font-size: 10px; margin-left: 2px; opacity: .4;"></i></th>
                            <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e2e8f0; width: 60px;">Image</th>
                            <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e2e8f0; cursor: pointer; user-select: none;" data-sort="name" onclick="sortTable('name')">Product Name <i class="fa-solid fa-sort" style="font-size: 10px; margin-left: 2px; opacity: .4;"></i></th>
                            <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e2e8f0; width: 100px; cursor: pointer; user-select: none;" data-sort="sku" onclick="sortTable('sku')">SKU <i class="fa-solid fa-sort" style="font-size: 10px; margin-left: 2px; opacity: .4;"></i></th>
                            <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e2e8f0; width: 100px; cursor: pointer; user-select: none; text-align: right;" data-sort="price" onclick="sortTable('price')">Price <i class="fa-solid fa-sort" style="font-size: 10px; margin-left: 2px; opacity: .4;"></i></th>
                            <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e2e8f0; width: 100px; cursor: pointer; user-select: none; text-align: right;" data-sort="log_views" onclick="sortTable('log_views')">Page Views <i class="fa-solid fa-sort" style="font-size: 10px; margin-left: 2px; opacity: .4;"></i></th>
                            <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e2e8f0; width: 110px; cursor: pointer; user-select: none; text-align: right;" data-sort="unique_visitors" onclick="sortTable('unique_visitors')">Unique Visitors <i class="fa-solid fa-sort" style="font-size: 10px; margin-left: 2px; opacity: .4;"></i></th>
                            <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e2e8f0; width: 80px; cursor: pointer; user-select: none; text-align: right;" data-sort="orders_count" onclick="sortTable('orders_count')">Orders <i class="fa-solid fa-sort" style="font-size: 10px; margin-left: 2px; opacity: .4;"></i></th>
                            <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e2e8f0; width: 120px; cursor: pointer; user-select: none; text-align: right;" data-sort="conversion_rate" onclick="sortTable('conversion_rate')">Conversion <i class="fa-solid fa-sort" style="font-size: 10px; margin-left: 2px; opacity: .4;"></i></th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody"></tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="paginationWrapper" style="display: none; padding: 16px 20px; border-top: 1px solid #e2e8f0; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <p id="paginationInfo" style="margin: 0; font-size: 13px; color: #64748b;"></p>
                <div id="paginationControls" style="display: flex; align-items: center; gap: 4px;"></div>
            </div>
        </div>
    </div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
(function() {
    // State
    let allProducts = [];
    let filteredProducts = [];
    let currentPage = 1;
    let perPage = 25;
    let totalFromApi = 0;
    let currentRange = 'today';
    let currentSearch = '';
    let sortColumn = 'log_views';
    let sortDirection = 'desc';
    let searchTimeout = null;

    // DOM refs
    const loadingEl = document.getElementById('loadingState');
    const emptyEl = document.getElementById('emptyState');
    const emptyMsgEl = document.getElementById('emptyMessage');
    const errorEl = document.getElementById('errorState');
    const errorMsgEl = document.getElementById('errorMessage');
    const tableWrapperEl = document.getElementById('tableWrapper');
    const tbodyEl = document.getElementById('productsTableBody');
    const paginationWrapperEl = document.getElementById('paginationWrapper');
    const paginationInfoEl = document.getElementById('paginationInfo');
    const paginationControlsEl = document.getElementById('paginationControls');
    const productCountEl = document.getElementById('productCount');
    const searchInputEl = document.getElementById('searchInput');

    // KPI refs
    const kpiTotalViews = document.getElementById('kpiTotalViews');
    const kpiMostViewed = document.getElementById('kpiMostViewed');
    const kpiMostViewedCount = document.getElementById('kpiMostViewedCount');
    const kpiHighestConversion = document.getElementById('kpiHighestConversion');
    const kpiHighestConversionRate = document.getElementById('kpiHighestConversionRate');
    const kpiTotalOrders = document.getElementById('kpiTotalOrders');

    // Format number with commas
    function formatNumber(n) {
        return Number(n || 0).toLocaleString('en-IN');
    }

    // Format price
    function formatPrice(price) {
        let p = parseFloat(price);
        if (isNaN(p)) return '₹0';
        return '₹' + p.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    // Conversion rate badge color
    function conversionColor(rate) {
        let r = parseFloat(rate);
        if (r >= 2) return { bg: '#f0fdf4', text: '#16a34a' };
        if (r >= 1) return { bg: '#fefce8', text: '#d97706' };
        return { bg: '#fef2f2', text: '#dc2626' };
    }

    // Show state
    function showState(state) {
        loadingEl.style.display = 'none';
        emptyEl.style.display = 'none';
        errorEl.style.display = 'none';
        tableWrapperEl.style.display = 'none';
        paginationWrapperEl.style.display = 'none';

        if (state === 'loading') loadingEl.style.display = 'flex';
        else if (state === 'empty') emptyEl.style.display = 'block';
        else if (state === 'error') errorEl.style.display = 'block';
        else if (state === 'table') {
            tableWrapperEl.style.display = 'block';
            paginationWrapperEl.style.display = 'flex';
        }
    }

    // Update KPI cards
    function updateKPIs(products) {
        if (!products || products.length === 0) {
            kpiTotalViews.textContent = '0';
            if (kpiMostViewedCount) kpiMostViewedCount.textContent = '0 views';
            kpiMostViewed.textContent = 'No product views recorded';
            kpiMostViewed.title = '';
            if (kpiHighestConversionRate) kpiHighestConversionRate.textContent = '0.00%';
            kpiHighestConversion.textContent = 'No orders recorded';
            kpiHighestConversion.title = '';
            kpiTotalOrders.textContent = '0';
            return;
        }

        let totalViews = 0, totalOrders = 0;
        let mostViewed = null;
        let highestConv = null;

        products.forEach(function(p) {
            let v = parseInt(p.log_views) || 0;
            let o = parseInt(p.orders_count) || 0;
            let c = parseFloat(p.conversion_rate) || 0;

            totalViews += v;
            totalOrders += o;

            if (v > 0) {
                if (!mostViewed || v > (parseInt(mostViewed.log_views) || 0)) {
                    mostViewed = p;
                }
            }

            if (c > 0 && o > 0) {
                if (!highestConv || c > (parseFloat(highestConv.conversion_rate) || 0)) {
                    highestConv = p;
                }
            }
        });

        kpiTotalViews.textContent = formatNumber(totalViews);
        kpiTotalOrders.textContent = formatNumber(totalOrders);

        if (mostViewed && (parseInt(mostViewed.log_views) || 0) > 0) {
            let mvViews = parseInt(mostViewed.log_views) || 0;
            if (kpiMostViewedCount) kpiMostViewedCount.textContent = formatNumber(mvViews) + ' views';
            kpiMostViewed.textContent = mostViewed.name || '—';
            kpiMostViewed.title = (mostViewed.name || '') + ' (' + formatNumber(mvViews) + ' views)';
        } else {
            if (kpiMostViewedCount) kpiMostViewedCount.textContent = '0 views';
            kpiMostViewed.textContent = 'No views recorded in period';
            kpiMostViewed.title = 'No products were viewed in this date range';
        }

        if (highestConv && (parseFloat(highestConv.conversion_rate) || 0) > 0) {
            let rate = parseFloat(highestConv.conversion_rate).toFixed(2);
            if (kpiHighestConversionRate) kpiHighestConversionRate.textContent = rate + '%';
            kpiHighestConversion.textContent = highestConv.name || '—';
            kpiHighestConversion.title = (highestConv.name || '') + ' (' + rate + '% conversion)';
        } else {
            if (kpiHighestConversionRate) kpiHighestConversionRate.textContent = '0.00%';
            kpiHighestConversion.textContent = 'No converted orders yet';
            kpiHighestConversion.title = 'No purchases recorded in this date range';
        }
    }

    // Sort products
    function doSort(products) {
        let col = sortColumn;
        let dir = sortDirection === 'asc' ? 1 : -1;

        return products.slice().sort(function(a, b) {
            let va, vb;
            if (col === 'name' || col === 'sku') {
                va = (a[col] || '').toLowerCase();
                vb = (b[col] || '').toLowerCase();
                return va < vb ? -dir : va > vb ? dir : 0;
            } else if (col === 'rank') {
                // rank by log_views desc, so sort ascending by index
                return 0;
            } else {
                va = parseFloat(a[col]) || 0;
                vb = parseFloat(b[col]) || 0;
                return (va - vb) * dir;
            }
        });
    }

    // Filter products
    function filterProducts() {
        let query = currentSearch.toLowerCase().trim();
        if (!query) {
            filteredProducts = allProducts.slice();
        } else {
            filteredProducts = allProducts.filter(function(p) {
                return (p.name && p.name.toLowerCase().indexOf(query) !== -1) ||
                       (p.sku && p.sku.toLowerCase().indexOf(query) !== -1);
            });
        }
        filteredProducts = doSort(filteredProducts);
    }

    // Render table
    function renderTable() {
        filterProducts();

        if (filteredProducts.length === 0) {
            if (currentSearch) {
                emptyMsgEl.textContent = 'No products match "' + currentSearch + '". Try a different search term.';
            } else {
                emptyMsgEl.textContent = 'There are no products with analytics data for the selected period.';
            }
            showState('empty');
            productCountEl.textContent = '(0)';
            return;
        }

        productCountEl.textContent = '(' + filteredProducts.length + ')';

        let totalPages = Math.ceil(filteredProducts.length / perPage);
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        let startIdx = (currentPage - 1) * perPage;
        let endIdx = Math.min(startIdx + perPage, filteredProducts.length);
        let pageProducts = filteredProducts.slice(startIdx, endIdx);

        let html = '';
        pageProducts.forEach(function(p, idx) {
            let rank = startIdx + idx + 1;
            let cc = conversionColor(p.conversion_rate);
            let salePrice = parseFloat(p.sale_price) || 0;
            let price = parseFloat(p.price) || 0;
            let priceDisplay = salePrice > 0
                ? '<span style="text-decoration: line-through; color: #94a3b8; font-size: 11px;">' + formatPrice(price) + '</span> ' + formatPrice(salePrice)
                : formatPrice(price);

            let imgSrc = p.main_image || '';
            let imgHtml = imgSrc
                ? '<img src="' + imgSrc + '" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0;">'
                : '<div style="width: 40px; height: 40px; border-radius: 6px; background: #f1f5f9; display: flex; align-items: center; justify-content: center;"><i class="fa-solid fa-image" style="color: #cbd5e1; font-size: 14px;"></i></div>';

            html += '<tr style="border-bottom: 1px solid #f1f5f9; transition: background .15s;" onmouseover="this.style.background=\'#f8fafc\'" onmouseout="this.style.background=\'transparent\'">';
            html += '<td style="padding: 12px 16px; font-size: 13px; color: #94a3b8; font-weight: 600;">' + rank + '</td>';
            html += '<td style="padding: 12px 16px;">' + imgHtml + '</td>';
            html += '<td style="padding: 12px 16px;"><a href="product-edit.php?id=' + p.id + '" style="font-size: 13px; font-weight: 600; color: #1e293b; text-decoration: none; transition: color .15s;" onmouseover="this.style.color=\'#3b82f6\'" onmouseout="this.style.color=\'#1e293b\'">' + escapeHtml(p.name) + '</a></td>';
            html += '<td style="padding: 12px 16px; font-size: 13px; color: #64748b; font-family: monospace;">' + escapeHtml(p.sku || '—') + '</td>';
            html += '<td style="padding: 12px 16px; font-size: 13px; color: #334155; font-weight: 500; text-align: right;">' + priceDisplay + '</td>';
            html += '<td style="padding: 12px 16px; font-size: 13px; color: #334155; font-weight: 600; text-align: right;">' + formatNumber(p.log_views) + '</td>';
            html += '<td style="padding: 12px 16px; font-size: 13px; color: #334155; text-align: right;">' + formatNumber(p.unique_visitors) + '</td>';
            html += '<td style="padding: 12px 16px; font-size: 13px; color: #334155; font-weight: 600; text-align: right;">' + formatNumber(p.orders_count) + '</td>';
            html += '<td style="padding: 12px 16px; text-align: right;"><span style="display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; background: ' + cc.bg + '; color: ' + cc.text + ';">' + parseFloat(p.conversion_rate).toFixed(2) + '%</span></td>';
            html += '</tr>';
        });

        tbodyEl.innerHTML = html;
        renderPagination(totalPages);
        showState('table');
    }

    // Render pagination
    function renderPagination(totalPages) {
        let startIdx = (currentPage - 1) * perPage + 1;
        let endIdx = Math.min(currentPage * perPage, filteredProducts.length);
        paginationInfoEl.textContent = 'Showing ' + startIdx + '–' + endIdx + ' of ' + filteredProducts.length + ' products';

        let html = '';
        let btnBase = 'padding: 6px 12px; font-size: 13px; border: 1px solid #e2e8f0; cursor: pointer; transition: all .15s; border-radius: 6px; font-weight: 500;';

        // Prev
        if (currentPage > 1) {
            html += '<button onclick="window._paNav(' + (currentPage - 1) + ')" style="' + btnBase + ' background: #fff; color: #334155;" onmouseover="this.style.background=\'#f1f5f9\'" onmouseout="this.style.background=\'#fff\'"><i class="fa-solid fa-chevron-left" style="font-size: 11px;"></i></button>';
        }

        // Page numbers
        let pages = getPaginationRange(currentPage, totalPages);
        pages.forEach(function(pg) {
            if (pg === '...') {
                html += '<span style="padding: 6px 8px; font-size: 13px; color: #94a3b8;">…</span>';
            } else if (pg === currentPage) {
                html += '<button style="' + btnBase + ' background: #3b82f6; color: #fff; border-color: #3b82f6;" disabled>' + pg + '</button>';
            } else {
                html += '<button onclick="window._paNav(' + pg + ')" style="' + btnBase + ' background: #fff; color: #334155;" onmouseover="this.style.background=\'#f1f5f9\'" onmouseout="this.style.background=\'#fff\'">' + pg + '</button>';
            }
        });

        // Next
        if (currentPage < totalPages) {
            html += '<button onclick="window._paNav(' + (currentPage + 1) + ')" style="' + btnBase + ' background: #fff; color: #334155;" onmouseover="this.style.background=\'#f1f5f9\'" onmouseout="this.style.background=\'#fff\'"><i class="fa-solid fa-chevron-right" style="font-size: 11px;"></i></button>';
        }

        paginationControlsEl.innerHTML = html;
    }

    // Pagination range helper
    function getPaginationRange(current, total) {
        if (total <= 7) {
            let arr = [];
            for (let i = 1; i <= total; i++) arr.push(i);
            return arr;
        }
        let pages = [];
        pages.push(1);
        if (current > 3) pages.push('...');
        for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
            pages.push(i);
        }
        if (current < total - 2) pages.push('...');
        pages.push(total);
        return pages;
    }

    // Escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        let div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Load products from API
    function loadProducts() {
        showState('loading');
        currentPage = 1;

        let url = 'api/analytics-data.php?action=products&range=' + encodeURIComponent(currentRange);

        fetch(url)
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                if (!data.success) {
                    throw new Error(data.message || 'API returned an error');
                }

                allProducts = data.products || [];
                totalFromApi = data.total || allProducts.length;

                updateKPIs(allProducts);
                renderTable();
            })
            .catch(function(err) {
                errorMsgEl.textContent = err.message || 'Something went wrong while fetching analytics.';
                showState('error');
                updateKPIs([]);
                productCountEl.textContent = '';
            });
    }
    window.loadProducts = loadProducts;

    // Sort table
    window.sortTable = function(col) {
        if (sortColumn === col) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = col;
            sortDirection = (col === 'name' || col === 'sku') ? 'asc' : 'desc';
        }
        currentPage = 1;
        renderTable();
    };

    // Navigate page
    window._paNav = function(page) {
        currentPage = page;
        renderTable();
        // Scroll to table top
        tableWrapperEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // Export CSV
    window.exportCSV = function() {
        if (!filteredProducts || filteredProducts.length === 0) {
            alert('No data to export.');
            return;
        }

        let headers = ['Rank', 'Product Name', 'SKU', 'Price', 'Sale Price', 'Page Views', 'Unique Visitors', 'Orders', 'Conversion Rate (%)'];
        let rows = [headers.join(',')];

        filteredProducts.forEach(function(p, idx) {
            let row = [
                idx + 1,
                '"' + (p.name || '').replace(/"/g, '""') + '"',
                '"' + (p.sku || '').replace(/"/g, '""') + '"',
                p.price,
                p.sale_price,
                p.log_views,
                p.unique_visitors,
                p.orders_count,
                parseFloat(p.conversion_rate).toFixed(2)
            ];
            rows.push(row.join(','));
        });

        let csvContent = rows.join('\n');
        let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        let url = URL.createObjectURL(blob);
        let link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', 'product-analytics-' + currentRange + '-' + new Date().toISOString().slice(0, 10) + '.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    // Date range buttons
    document.querySelectorAll('.date-range-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.date-range-btn').forEach(function(b) {
                b.className = 'date-range-btn shadcn-btn shadcn-btn-ghost';
                b.style.height = '28px';
                b.style.fontSize = '12px';
                b.style.padding = '0 10px';
            });
            this.className = 'date-range-btn shadcn-btn shadcn-btn-primary';
            this.style.height = '28px';
            this.style.fontSize = '12px';
            this.style.padding = '0 10px';
            currentRange = this.getAttribute('data-range');
            currentSearch = '';
            searchInputEl.value = '';
            loadProducts();
        });
    });

    // Search input with debounce
    searchInputEl.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        let val = this.value;
        searchTimeout = setTimeout(function() {
            currentSearch = val;
            currentPage = 1;
            renderTable();
        }, 300);
    });

    // Initial load
    loadProducts();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
