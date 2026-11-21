<?php
$page_title = "Category Analytics";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="dashboard-header-banner" style="margin-bottom: 16px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting">
            <h1>Category Analytics</h1>
            <span class="shadcn-badge shadcn-badge-sky" style="font-size: 11px; padding: 2px 8px;">
                Collections &amp; Categories
            </span>
        </div>
        <p class="dashboard-subtitle">
            Track visitor engagement and views across product categories.
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
        <a href="analytics-categories.php" class="shadcn-btn shadcn-btn-primary" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Categories
        </a>
        <a href="analytics-pages.php" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Pages &amp; Search
        </a>
    </div>
</div>

<!-- Compact Neutral KPI Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 16px;">
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Total Category Views</span>
            <i class="fa-solid fa-eye" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiTotalViews" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">0</div>
            <div class="shadcn-stat-note" style="font-size: 11px; color: #71717a; margin-top: 2px;">All category collections</div>
        </div>
    </div>

    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Most Visited Category</span>
            <i class="fa-solid fa-crown" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div style="min-width: 0; overflow: hidden;">
            <div class="shadcn-stat-value" id="kpiTopCategory" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">—</div>
            <div class="shadcn-stat-note" id="kpiTopCategoryNote" style="font-size: 11px; color: #71717a; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">No visits in this period</div>
        </div>
    </div>

    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Categories with Traffic</span>
            <i class="fa-solid fa-chart-line" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiWithTraffic" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">0</div>
            <div class="shadcn-stat-note" style="font-size: 11px; color: #71717a; margin-top: 2px;">Active collections viewed</div>
        </div>
    </div>

    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Avg Views per Category</span>
            <i class="fa-solid fa-calculator" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiAvgViews" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">0</div>
            <div class="shadcn-stat-note" style="font-size: 11px; color: #71717a; margin-top: 2px;">Average hits per collection</div>
        </div>
    </div>
</div>

<!-- Category Performance Grid Card -->
<div class="shadcn-card" style="margin-bottom: 16px;">
    <div class="shadcn-card-header" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid #e4e4e7;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <h2 class="shadcn-card-title"><i class="fa-solid fa-layer-group" style="margin-right: 6px; color: #71717a;"></i> Category Cards</h2>
            <span id="categoryCount" class="shadcn-badge shadcn-badge-sky" style="font-size: 10.5px; padding: 1px 6px;"></span>
        </div>
    </div>
    <div class="shadcn-card-body" style="padding: 14px;">
        <div id="categoryCardsGrid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:12px;">
            <div style="text-align:center; padding:40px 20px; color:#71717a; grid-column:1/-1;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size:1.5rem; margin-bottom:8px; display:block;"></i>
                Loading category data...
            </div>
        </div>
    </div>
</div>

<!-- Detailed Data Table Card -->
<div class="shadcn-card" style="margin-bottom: 16px;">
    <div class="shadcn-card-header" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid #e4e4e7;">
        <h2 class="shadcn-card-title"><i class="fa-solid fa-table" style="margin-right: 6px; color: #71717a;"></i> Detailed Category Breakdown</h2>
    </div>
    <div class="shadcn-card-body" style="padding: 0; overflow-x: auto;">
        <table class="wp-list-table widefat fixed striped" id="categoryTable" style="border:none; border-collapse:collapse; width:100%; margin: 0;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="padding:10px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#71717a; font-weight:600; text-align:left; border-bottom:1px solid #e4e4e7; width:45px;">#</th>
                    <th style="padding:10px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#71717a; font-weight:600; text-align:left; border-bottom:1px solid #e4e4e7;">Category</th>
                    <th style="padding:10px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#71717a; font-weight:600; text-align:center; border-bottom:1px solid #e4e4e7;">Page Views</th>
                    <th style="padding:10px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#71717a; font-weight:600; text-align:center; border-bottom:1px solid #e4e4e7;">Log Views</th>
                    <th style="padding:10px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#71717a; font-weight:600; text-align:center; border-bottom:1px solid #e4e4e7;">Unique Visitors</th>
                    <th style="padding:10px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#71717a; font-weight:600; text-align:center; border-bottom:1px solid #e4e4e7;">Products</th>
                    <th style="padding:10px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#71717a; font-weight:600; text-align:left; border-bottom:1px solid #e4e4e7;">Top Product</th>
                </tr>
            </thead>
            <tbody id="categoryTableBody">
                <tr>
                    <td colspan="7" style="text-align:center; padding:30px; color:#71717a;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Loading...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
let categoriesData = [];
let currentRange = '7days';

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
            loadCategoryData();
        });
    });

    loadCategoryData();
});

function loadCategoryData() {
    const url = 'api/analytics-data.php?action=categories&range=' + encodeURIComponent(currentRange);

    // Show loading state
    document.getElementById('categoryCardsGrid').innerHTML =
        '<div style="text-align:center; padding:40px 20px; color:#71717a; grid-column:1/-1;">' +
        '<i class="fa-solid fa-spinner fa-spin" style="font-size:1.5rem; margin-bottom:8px; display:block;"></i>' +
        'Loading category data...</div>';
    document.getElementById('categoryTableBody').innerHTML =
        '<tr><td colspan="7" style="text-align:center; padding:30px; color:#71717a;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</td></tr>';

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.categories) {
                categoriesData = data.categories;
                renderKPIs(categoriesData);
                renderCards(categoriesData);
                renderTable(categoriesData);
            } else {
                showEmpty();
            }
        })
        .catch(err => {
            console.error('Failed to load category analytics:', err);
            showEmpty();
        });
}

function showEmpty() {
    document.getElementById('categoryCardsGrid').innerHTML =
        '<div style="text-align:center; padding:40px 20px; color:#71717a; grid-column:1/-1;">' +
        '<i class="fa-solid fa-box-open" style="font-size:2rem; margin-bottom:8px; display:block;"></i>' +
        '<div style="font-weight:600; font-size:13px; color:#09090b;">No category data available</div>' +
        '<div style="font-size:11px; margin-top:2px;">Try selecting a different date range</div></div>';
    document.getElementById('categoryTableBody').innerHTML =
        '<tr><td colspan="7" style="text-align:center; padding:30px; color:#71717a;">No data found</td></tr>';
    document.getElementById('categoryCount').textContent = '0 categories';
    ['kpiTotalViews','kpiAvgViews','kpiWithTraffic'].forEach(id => document.getElementById(id).textContent = '0');
    document.getElementById('kpiTopCategory').textContent = '—';
}

function renderKPIs(cats) {
    const totalViews = cats.reduce((s, c) => s + (parseInt(c.log_views) || parseInt(c.view_count) || 0), 0);
    const withTraffic = cats.filter(c => (parseInt(c.log_views) || parseInt(c.view_count) || 0) > 0).length;
    const avgViews = cats.length > 0 ? Math.round(totalViews / cats.length) : 0;

    let topCat = '—';
    let topCatNote = 'No visits recorded yet';
    if (cats.length > 0) {
        const sorted = [...cats].sort((a, b) => ((parseInt(b.log_views) || parseInt(b.view_count) || 0) - (parseInt(a.log_views) || parseInt(a.view_count) || 0)));
        const topVal = parseInt(sorted[0].log_views) || parseInt(sorted[0].view_count) || 0;
        if (topVal > 0) {
            topCat = sorted[0].name || '—';
            topCatNote = topVal.toLocaleString() + ' views in period';
        }
    }

    document.getElementById('kpiTotalViews').textContent = totalViews.toLocaleString();
    document.getElementById('kpiTopCategory').textContent = topCat;
    const noteEl = document.getElementById('kpiTopCategoryNote');
    if (noteEl) noteEl.textContent = topCatNote;
    document.getElementById('kpiWithTraffic').textContent = withTraffic + ' / ' + cats.length;
    document.getElementById('kpiAvgViews').textContent = avgViews.toLocaleString();
    document.getElementById('categoryCount').textContent = cats.length + ' categories';
}

function renderCards(cats) {
    const grid = document.getElementById('categoryCardsGrid');
    if (!cats.length) { showEmpty(); return; }

    const maxViews = Math.max(...cats.map(c => c.view_count || 0), 1);
    let html = '';
    const sorted = [...cats].sort((a, b) => (b.view_count || 0) - (a.view_count || 0));

    sorted.forEach((cat) => {
        const pct = maxViews > 0 ? Math.round(((cat.view_count || 0) / maxViews) * 100) : 0;
        const imgSrc = cat.image_path ? cat.image_path : '';

        const imageBlock = imgSrc
            ? '<img src="/' + escapeHtml(imgSrc) + '" alt="' + escapeHtml(cat.name) + '" style="width:100%; height:110px; object-fit:cover; border-bottom:1px solid #e4e4e7;">'
            : '<div style="width:100%; height:110px; background:#f4f4f5; border-bottom:1px solid #e4e4e7; display:flex; align-items:center; justify-content:center;">' +
              '<i class="fa-solid fa-layer-group" style="font-size:2rem; color:#a1a1aa;"></i></div>';

        html += '<div class="shadcn-card" style="padding:0; overflow:hidden; margin-bottom:0; transition:border-color 0.15s;">' +
            imageBlock +
            '<div style="padding:10px 12px 12px;">' +
                '<h3 style="margin:0 0 8px; font-size:13px; font-weight:600; color:#09090b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' + escapeHtml(cat.name) + '">' + escapeHtml(cat.name) + '</h3>' +
                '<div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 10px; margin-bottom:10px;">' +
                    '<div>' +
                        '<div style="font-size:10px; color:#71717a; text-transform:uppercase; font-weight:600;">Page Views</div>' +
                        '<div style="font-size:15px; font-weight:700; color:#09090b;">' + (cat.view_count || 0).toLocaleString() + '</div>' +
                    '</div>' +
                    '<div>' +
                        '<div style="font-size:10px; color:#71717a; text-transform:uppercase; font-weight:600;">Unique Visitors</div>' +
                        '<div style="font-size:15px; font-weight:700; color:#09090b;">' + (cat.unique_visitors || 0).toLocaleString() + '</div>' +
                    '</div>' +
                    '<div>' +
                        '<div style="font-size:10px; color:#71717a; text-transform:uppercase; font-weight:600;">Products</div>' +
                        '<div style="font-size:12px; font-weight:600; color:#27272a;">' + (cat.product_count || 0) + '</div>' +
                    '</div>' +
                    '<div>' +
                        '<div style="font-size:10px; color:#71717a; text-transform:uppercase; font-weight:600;">Top Product</div>' +
                        '<div style="font-size:11px; font-weight:500; color:#27272a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' + escapeHtml(cat.top_product || 'N/A') + '">' + escapeHtml(cat.top_product || 'N/A') + '</div>' +
                    '</div>' +
                '</div>' +
                '<div>' +
                    '<div style="display:flex; justify-content:space-between; margin-bottom:3px;">' +
                        '<span style="font-size:10px; color:#71717a; font-weight:500;">Traffic Share</span>' +
                        '<span style="font-size:10px; color:#09090b; font-weight:600;">' + pct + '%</span>' +
                    '</div>' +
                    '<div style="width:100%; height:4px; background:#f4f4f5; border-radius:2px; overflow:hidden;">' +
                        '<div style="width:' + pct + '%; height:100%; background:#18181b; border-radius:2px;"></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';
    });

    grid.innerHTML = html;
}

function renderTable(cats) {
    const tbody = document.getElementById('categoryTableBody');
    if (!cats.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:#71717a;">No data found</td></tr>';
        return;
    }

    const sorted = [...cats].sort((a, b) => (b.view_count || 0) - (a.view_count || 0));
    let html = '';

    sorted.forEach((cat, i) => {
        html += '<tr style="border-bottom:1px solid #f4f4f5;">' +
            '<td style="padding:8px 14px; color:#71717a; font-weight:500; font-size:12px;">' + (i + 1) + '</td>' +
            '<td style="padding:8px 14px;">' +
                '<div style="display:flex; align-items:center; gap:8px;">' +
                    (cat.image_path
                        ? '<img src="/' + escapeHtml(cat.image_path) + '" style="width:28px; height:28px; border-radius:4px; object-fit:cover; border:1px solid #e4e4e7;">'
                        : '<div style="width:28px; height:28px; border-radius:4px; background:#f4f4f5; border:1px solid #e4e4e7; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-layer-group" style="color:#a1a1aa; font-size:11px;"></i></div>'
                    ) +
                    '<span style="font-weight:600; font-size:12px; color:#09090b;">' + escapeHtml(cat.name) + '</span>' +
                '</div>' +
            '</td>' +
            '<td style="padding:8px 14px; text-align:center; font-weight:700; font-size:12px; color:#09090b;">' + (cat.view_count || 0).toLocaleString() + '</td>' +
            '<td style="padding:8px 14px; text-align:center; font-size:12px; color:#71717a;">' + (cat.log_views || 0).toLocaleString() + '</td>' +
            '<td style="padding:8px 14px; text-align:center; font-size:12px; color:#71717a;">' + (cat.unique_visitors || 0).toLocaleString() + '</td>' +
            '<td style="padding:8px 14px; text-align:center; font-size:12px; color:#71717a;">' + (cat.product_count || 0) + '</td>' +
            '<td style="padding:8px 14px; color:#27272a; font-size:12px;">' + escapeHtml(cat.top_product || '—') + '</td>' +
        '</tr>';
    });

    tbody.innerHTML = html;
}

function exportCSV() {
    if (!categoriesData.length) {
        alert('No data to export. Please wait for data to load.');
        return;
    }

    const headers = ['Category', 'Slug', 'Page Views', 'Log Views', 'Unique Visitors', 'Products', 'Top Product'];
    const rows = categoriesData.map(c => [
        '"' + (c.name || '').replace(/"/g, '""') + '"',
        '"' + (c.slug || '').replace(/"/g, '""') + '"',
        c.view_count || 0,
        c.log_views || 0,
        c.unique_visitors || 0,
        c.product_count || 0,
        '"' + (c.top_product || '').replace(/"/g, '""') + '"'
    ]);

    let csv = headers.join(',') + '\n';
    rows.forEach(r => csv += r.join(',') + '\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'category-analytics-' + currentRange + '-' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text || ''));
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
