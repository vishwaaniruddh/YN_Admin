<?php
$page_title = "Category Analytics";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="content-area">
    <div class="content-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
        <div>
            <h1 style="margin:0; font-size:1.6rem; font-weight:700; color:#1e293b;">
                <i class="fa-solid fa-layer-group" style="color:#6366f1; margin-right:8px;"></i>Category Analytics
            </h1>
            <p style="margin:4px 0 0; color:#64748b; font-size:0.9rem;">Track visitor engagement across product categories</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <select id="dateRange" onchange="loadCategoryData()" style="padding:8px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.88rem; background:#fff; color:#334155; cursor:pointer;">
                <option value="today">Today</option>
                <option value="7days" selected>Last 7 Days</option>
                <option value="30days">Last 30 Days</option>
                <option value="90days">Last 90 Days</option>
            </select>
            <button onclick="exportCSV()" style="padding:8px 18px; background:#10b981; color:#fff; border:none; border-radius:8px; font-size:0.88rem; cursor:pointer; display:flex; align-items:center; gap:6px; font-weight:600; transition:background .2s;">
                <i class="fa-solid fa-file-csv"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards (Standard ShadCN Stat Cards) -->
    <div class="shadcn-stat-grid" id="kpiCards" style="margin-bottom: 24px;">
        <div class="shadcn-stat-card" style="min-width: 0; overflow: hidden;">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title">Total Category Views</span>
                <div class="shadcn-stat-icon-wrap" style="background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                    <i class="fa-solid fa-eye"></i>
                </div>
            </div>
            <div>
                <div class="shadcn-stat-value" id="kpiTotalViews">0</div>
                <div class="shadcn-stat-note" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">All category collections</div>
            </div>
        </div>

        <div class="shadcn-stat-card" style="min-width: 0; overflow: hidden;">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title">Most Visited Category</span>
                <div class="shadcn-stat-icon-wrap" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
                    <i class="fa-solid fa-crown"></i>
                </div>
            </div>
            <div style="min-width: 0; overflow: hidden;">
                <div class="shadcn-stat-value" id="kpiTopCategory" style="font-size: 18px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">—</div>
                <div class="shadcn-stat-note" id="kpiTopCategoryNote" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; color: #475569;">No visits in this period</div>
            </div>
        </div>

        <div class="shadcn-stat-card" style="min-width: 0; overflow: hidden;">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title">Categories with Traffic</span>
                <div class="shadcn-stat-icon-wrap" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
            <div>
                <div class="shadcn-stat-value" id="kpiWithTraffic">0</div>
                <div class="shadcn-stat-note" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Active collections viewed</div>
            </div>
        </div>

        <div class="shadcn-stat-card" style="min-width: 0; overflow: hidden;">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title">Avg Views per Category</span>
                <div class="shadcn-stat-icon-wrap" style="background: rgba(14, 165, 233, 0.12); color: #0284c7;">
                    <i class="fa-solid fa-calculator"></i>
                </div>
            </div>
            <div>
                <div class="shadcn-stat-value" id="kpiAvgViews">0</div>
                <div class="shadcn-stat-note" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Average hits per collection</div>
            </div>
        </div>
    </div>

    <!-- Category Visual Cards Grid -->
    <div class="postbox" style="border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:28px;">
        <div class="postbox-header" style="padding:16px 22px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0; font-size:1.05rem; font-weight:700; color:#1e293b;">
                <i class="fa-solid fa-grip" style="color:#6366f1; margin-right:8px;"></i>Category Performance
            </h2>
            <span id="categoryCount" style="font-size:0.82rem; color:#64748b; background:#f1f5f9; padding:4px 12px; border-radius:20px; font-weight:600;"></span>
        </div>
        <div class="postbox-body" style="padding:22px;">
            <div id="categoryCardsGrid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:18px;">
                <div style="text-align:center; padding:60px 20px; color:#94a3b8; grid-column:1/-1;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size:2rem; margin-bottom:12px; display:block;"></i>
                    Loading category data...
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Data Table -->
    <div class="postbox" style="border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:28px;">
        <div class="postbox-header" style="padding:16px 22px; border-bottom:1px solid #e2e8f0;">
            <h2 style="margin:0; font-size:1.05rem; font-weight:700; color:#1e293b;">
                <i class="fa-solid fa-table" style="color:#3b82f6; margin-right:8px;"></i>Detailed Category Data
            </h2>
        </div>
        <div class="postbox-body" style="padding:0; overflow-x:auto;">
            <table class="wp-list-table widefat fixed striped" id="categoryTable" style="border:none; border-collapse:collapse; width:100%;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:12px 16px; font-size:0.8rem; text-transform:uppercase; letter-spacing:.5px; color:#64748b; font-weight:700; text-align:left; border-bottom:2px solid #e2e8f0;">#</th>
                        <th style="padding:12px 16px; font-size:0.8rem; text-transform:uppercase; letter-spacing:.5px; color:#64748b; font-weight:700; text-align:left; border-bottom:2px solid #e2e8f0;">Category</th>
                        <th style="padding:12px 16px; font-size:0.8rem; text-transform:uppercase; letter-spacing:.5px; color:#64748b; font-weight:700; text-align:center; border-bottom:2px solid #e2e8f0;">Page Views</th>
                        <th style="padding:12px 16px; font-size:0.8rem; text-transform:uppercase; letter-spacing:.5px; color:#64748b; font-weight:700; text-align:center; border-bottom:2px solid #e2e8f0;">Log Views</th>
                        <th style="padding:12px 16px; font-size:0.8rem; text-transform:uppercase; letter-spacing:.5px; color:#64748b; font-weight:700; text-align:center; border-bottom:2px solid #e2e8f0;">Unique Visitors</th>
                        <th style="padding:12px 16px; font-size:0.8rem; text-transform:uppercase; letter-spacing:.5px; color:#64748b; font-weight:700; text-align:center; border-bottom:2px solid #e2e8f0;">Products</th>
                        <th style="padding:12px 16px; font-size:0.8rem; text-transform:uppercase; letter-spacing:.5px; color:#64748b; font-weight:700; text-align:left; border-bottom:2px solid #e2e8f0;">Top Product</th>
                    </tr>
                </thead>
                <tbody id="categoryTableBody">
                    <tr>
                        <td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">
                            <i class="fa-solid fa-spinner fa-spin"></i> Loading...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let categoriesData = [];

function loadCategoryData() {
    const range = document.getElementById('dateRange').value;
    const url = 'api/analytics-data.php?action=categories&range=' + encodeURIComponent(range);

    // Show loading state
    document.getElementById('categoryCardsGrid').innerHTML =
        '<div style="text-align:center; padding:60px 20px; color:#94a3b8; grid-column:1/-1;">' +
        '<i class="fa-solid fa-spinner fa-spin" style="font-size:2rem; margin-bottom:12px; display:block;"></i>' +
        'Loading category data...</div>';
    document.getElementById('categoryTableBody').innerHTML =
        '<tr><td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</td></tr>';

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
        '<div style="text-align:center; padding:60px 20px; color:#94a3b8; grid-column:1/-1;">' +
        '<i class="fa-solid fa-box-open" style="font-size:2.5rem; margin-bottom:12px; display:block;"></i>' +
        '<div style="font-weight:600;">No category data available</div>' +
        '<div style="font-size:0.85rem; margin-top:4px;">Try selecting a different date range</div></div>';
    document.getElementById('categoryTableBody').innerHTML =
        '<tr><td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">No data found</td></tr>';
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
    const gradients = [
        'linear-gradient(135deg,#6366f1,#818cf8)',
        'linear-gradient(135deg,#10b981,#34d399)',
        'linear-gradient(135deg,#f59e0b,#fbbf24)',
        'linear-gradient(135deg,#3b82f6,#60a5fa)',
        'linear-gradient(135deg,#ec4899,#f472b6)',
        'linear-gradient(135deg,#8b5cf6,#a78bfa)',
        'linear-gradient(135deg,#14b8a6,#2dd4bf)',
        'linear-gradient(135deg,#f43f5e,#fb7185)'
    ];
    const barColors = ['#6366f1','#10b981','#f59e0b','#3b82f6','#ec4899','#8b5cf6','#14b8a6','#f43f5e'];

    let html = '';
    const sorted = [...cats].sort((a, b) => (b.view_count || 0) - (a.view_count || 0));

    sorted.forEach((cat, i) => {
        const pct = maxViews > 0 ? Math.round(((cat.view_count || 0) / maxViews) * 100) : 0;
        const grad = gradients[i % gradients.length];
        const barColor = barColors[i % barColors.length];
        const imgSrc = cat.image_path ? cat.image_path : '';

        const imageBlock = imgSrc
            ? '<img src="/' + escapeHtml(imgSrc) + '" alt="' + escapeHtml(cat.name) + '" style="width:100%; height:140px; object-fit:cover; border-radius:10px 10px 0 0;">'
            : '<div style="width:100%; height:140px; border-radius:10px 10px 0 0; background:' + grad + '; display:flex; align-items:center; justify-content:center;">' +
              '<i class="fa-solid fa-layer-group" style="font-size:3rem; color:rgba(255,255,255,.7);"></i></div>';

        html += '<div style="background:#fff; border-radius:10px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.05); transition:transform .2s, box-shadow .2s; cursor:default;" onmouseenter="this.style.transform=\'translateY(-3px)\';this.style.boxShadow=\'0 6px 20px rgba(0,0,0,.1)\'" onmouseleave="this.style.transform=\'none\';this.style.boxShadow=\'0 2px 8px rgba(0,0,0,.05)\'">' +
            imageBlock +
            '<div style="padding:16px 18px 18px;">' +
                '<h3 style="margin:0 0 10px; font-size:1.05rem; font-weight:700; color:#1e293b;">' + escapeHtml(cat.name) + '</h3>' +
                '<div style="display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; margin-bottom:14px;">' +
                    '<div>' +
                        '<div style="font-size:0.72rem; color:#94a3b8; text-transform:uppercase; letter-spacing:.4px; font-weight:600;">Page Views</div>' +
                        '<div style="font-size:1.3rem; font-weight:800; color:#1e293b;">' + (cat.view_count || 0).toLocaleString() + '</div>' +
                    '</div>' +
                    '<div>' +
                        '<div style="font-size:0.72rem; color:#94a3b8; text-transform:uppercase; letter-spacing:.4px; font-weight:600;">Unique Visitors</div>' +
                        '<div style="font-size:1.3rem; font-weight:800; color:#1e293b;">' + (cat.unique_visitors || 0).toLocaleString() + '</div>' +
                    '</div>' +
                    '<div>' +
                        '<div style="font-size:0.72rem; color:#94a3b8; text-transform:uppercase; letter-spacing:.4px; font-weight:600;">Products</div>' +
                        '<div style="font-size:1rem; font-weight:700; color:#334155;">' + (cat.product_count || 0) + '</div>' +
                    '</div>' +
                    '<div>' +
                        '<div style="font-size:0.72rem; color:#94a3b8; text-transform:uppercase; letter-spacing:.4px; font-weight:600;">Top Product</div>' +
                        '<div style="font-size:0.85rem; font-weight:600; color:#334155; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' + escapeHtml(cat.top_product || 'N/A') + '">' + escapeHtml(cat.top_product || 'N/A') + '</div>' +
                    '</div>' +
                '</div>' +
                '<div style="margin-top:4px;">' +
                    '<div style="display:flex; justify-content:space-between; margin-bottom:4px;">' +
                        '<span style="font-size:0.72rem; color:#94a3b8; font-weight:600;">Traffic Share</span>' +
                        '<span style="font-size:0.72rem; color:#64748b; font-weight:700;">' + pct + '%</span>' +
                    '</div>' +
                    '<div style="width:100%; height:6px; background:#f1f5f9; border-radius:3px; overflow:hidden;">' +
                        '<div style="width:' + pct + '%; height:100%; background:' + barColor + '; border-radius:3px; transition:width .6s ease;"></div>' +
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
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">No data found</td></tr>';
        return;
    }

    const sorted = [...cats].sort((a, b) => (b.view_count || 0) - (a.view_count || 0));
    let html = '';

    sorted.forEach((cat, i) => {
        html += '<tr style="border-bottom:1px solid #f1f5f9;">' +
            '<td style="padding:12px 16px; color:#64748b; font-weight:600; font-size:0.88rem;">' + (i + 1) + '</td>' +
            '<td style="padding:12px 16px;">' +
                '<div style="display:flex; align-items:center; gap:10px;">' +
                    (cat.image_path
                        ? '<img src="/' + escapeHtml(cat.image_path) + '" style="width:32px; height:32px; border-radius:6px; object-fit:cover;">'
                        : '<div style="width:32px; height:32px; border-radius:6px; background:#e2e8f0; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-layer-group" style="color:#94a3b8; font-size:0.75rem;"></i></div>'
                    ) +
                    '<span style="font-weight:600; color:#1e293b;">' + escapeHtml(cat.name) + '</span>' +
                '</div>' +
            '</td>' +
            '<td style="padding:12px 16px; text-align:center; font-weight:700; color:#1e293b;">' + (cat.view_count || 0).toLocaleString() + '</td>' +
            '<td style="padding:12px 16px; text-align:center; color:#64748b;">' + (cat.log_views || 0).toLocaleString() + '</td>' +
            '<td style="padding:12px 16px; text-align:center; color:#64748b;">' + (cat.unique_visitors || 0).toLocaleString() + '</td>' +
            '<td style="padding:12px 16px; text-align:center; color:#64748b;">' + (cat.product_count || 0) + '</td>' +
            '<td style="padding:12px 16px; color:#334155; font-size:0.88rem;">' + escapeHtml(cat.top_product || '—') + '</td>' +
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
    const range = document.getElementById('dateRange').value;
    link.download = 'category-analytics-' + range + '-' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text || ''));
    return div.innerHTML;
}

// Initial load
document.addEventListener('DOMContentLoaded', loadCategoryData);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
