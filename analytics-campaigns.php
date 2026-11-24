<?php
// admin/analytics-campaigns.php
// Dedicated Facebook Ads, Google & Campaign Traffic Analytics Monitor
$page_title = "Campaigns & Traffic Sources";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="dashboard-header-banner" style="margin-bottom: 16px;">
    <div class="dashboard-header-info">
        <div class="dashboard-greeting">
            <h1>Campaigns &amp; Traffic Sources</h1>
            <span class="shadcn-badge shadcn-badge-sky" style="font-size: 11px; padding: 2px 8px;">
                Meta &amp; Google Ads
            </span>
        </div>
        <p class="dashboard-subtitle">
            Track visitors and engagement from Facebook Ads, Instagram, Google, and UTM marketing links.
        </p>
    </div>

    <div class="dashboard-actions">
        <!-- Date Range Selector -->
        <div style="display: flex; background: #f4f4f5; border-radius: 6px; padding: 2px; border: 1px solid #e4e4e7;">
            <button class="date-range-btn shadcn-btn shadcn-btn-ghost" data-range="today" onclick="changeRange('today', this)" style="height: 28px; font-size: 12px; padding: 0 10px;">Today</button>
            <button class="date-range-btn shadcn-btn shadcn-btn-primary" data-range="7days" onclick="changeRange('7days', this)" style="height: 28px; font-size: 12px; padding: 0 10px;">7 Days</button>
            <button class="date-range-btn shadcn-btn shadcn-btn-ghost" data-range="30days" onclick="changeRange('30days', this)" style="height: 28px; font-size: 12px; padding: 0 10px;">30 Days</button>
            <button class="date-range-btn shadcn-btn shadcn-btn-ghost" data-range="90days" onclick="changeRange('90days', this)" style="height: 28px; font-size: 12px; padding: 0 10px;">90 Days</button>
        </div>

        <!-- Campaign URL Builder Modal Trigger -->
        <button onclick="openUrlBuilderModal()" class="shadcn-btn shadcn-btn-outline" style="height: 32px; font-size: 12px;">
            <i class="fa-solid fa-link" style="margin-right: 4px;"></i> URL Generator
        </button>

        <!-- Export CSV -->
        <button id="exportCsvBtn" onclick="exportCampaignsCSV()" class="shadcn-btn shadcn-btn-outline" style="height: 32px; font-size: 12px;">
            <i class="fa-solid fa-file-csv" style="margin-right: 4px;"></i> Export CSV
        </button>
    </div>
</div>

<!-- Navigation Tabs (Aligned with products.php tab bar) -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
        <a href="analytics.php?range=7days" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Overview
        </a>
        <a href="analytics-campaigns.php?range=7days" class="shadcn-btn shadcn-btn-primary" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Facebook &amp; Campaigns
        </a>
        <a href="analytics-products.php?range=7days" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Products
        </a>
        <a href="analytics-categories.php?range=7days" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Categories
        </a>
        <a href="analytics-pages.php?range=7days" class="shadcn-btn shadcn-btn-outline" style="height: 30px; font-size: 12px; padding: 0 12px; font-weight: 500; text-decoration: none;">
            Pages &amp; Search
        </a>
    </div>
</div>

<!-- Compact Neutral KPI Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 16px;">
    <!-- Facebook Ads -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Facebook Ads</span>
            <i class="fa-brands fa-facebook-f" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiFbAdsViews" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">0</div>
            <div class="shadcn-stat-note" id="kpiFbAdsVisitors" style="font-size: 11px; color: #71717a; margin-top: 2px;">0 unique shoppers from ads</div>
        </div>
    </div>

    <!-- Instagram -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Instagram Traffic</span>
            <i class="fa-brands fa-instagram" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiIgViews" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">0</div>
            <div class="shadcn-stat-note" id="kpiIgVisitors" style="font-size: 11px; color: #71717a; margin-top: 2px;">0 profile &amp; ad visitors</div>
        </div>
    </div>

    <!-- Google Traffic -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Google Traffic</span>
            <i class="fa-brands fa-google" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiGoogViews" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">0</div>
            <div class="shadcn-stat-note" id="kpiGoogVisitors" style="font-size: 11px; color: #71717a; margin-top: 2px;">0 search &amp; ad clicks</div>
        </div>
    </div>

    <!-- Top Campaign -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Top Campaign</span>
            <i class="fa-solid fa-trophy" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiTopCampaign" style="font-size: 15px; font-weight: 700; color: #09090b; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">None</div>
            <div class="shadcn-stat-note" style="font-size: 11px; color: #71717a; margin-top: 2px;">Highest volume campaign</div>
        </div>
    </div>

    <!-- All Sessions -->
    <div class="shadcn-card" style="padding: 12px 14px; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.02em;">Total Sessions</span>
            <i class="fa-solid fa-users" style="color: #a1a1aa; font-size: 12px;"></i>
        </div>
        <div>
            <div class="shadcn-stat-value" id="kpiTotalSessions" style="font-size: 18px; font-weight: 700; color: #09090b; line-height: 1.2;">0</div>
            <div class="shadcn-stat-note" style="font-size: 11px; color: #71717a; margin-top: 2px;">Across channels in period</div>
        </div>
    </div>
</div>

<!-- Active Marketing Campaigns Table -->
<div class="shadcn-card" style="margin-bottom: 16px;">
    <div class="shadcn-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <h2 class="shadcn-card-title"><i class="fa-solid fa-bullseye" style="color: #71717a; margin-right: 6px;"></i> Campaign Performance</h2>
            <span id="campaignsCountBadge" class="shadcn-badge shadcn-badge-sky" style="font-size: 10.5px; padding: 1px 6px;">0</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <input type="text" id="campaignSearch" onkeyup="filterCampaignsTable()" placeholder="Search campaigns..." class="form-control" style="height: 30px; font-size: 12px; width: 200px;">
        </div>
    </div>

    <div class="shadcn-card-body">
        <table class="wp-list-table widefat fixed striped" style="margin: 0; font-size: 12px;">
            <thead>
                <tr>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; width: 28%;">Campaign Name</th>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; width: 14%;">Source / Medium</th>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; text-align: right; width: 10%;">Visitors</th>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; text-align: right; width: 10%;">Views</th>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; text-align: right; width: 10%;">Sessions</th>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; text-align: right; width: 10%;">Product Views</th>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; width: 18%;">Top Landing Page</th>
                </tr>
            </thead>
            <tbody id="campaignsTableBody">
                <tr>
                    <td colspan="7" style="text-align: center; padding: 24px; color: #71717a; font-size: 12.5px;">
                        <i class="fa-solid fa-spinner fa-spin" style="margin-right: 6px;"></i> Loading campaign data...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Two-Column Layout: All Traffic Sources & Facebook Campaign Guide -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">

    <!-- Left: All Traffic Sources Breakdown -->
    <div class="shadcn-card" style="margin-bottom: 0;">
        <div class="shadcn-card-header">
            <h2 class="shadcn-card-title"><i class="fa-solid fa-chart-pie" style="color: #71717a; margin-right: 6px;"></i> Traffic Channels</h2>
            <span style="font-size: 11px; color: #71717a;">Acquisition share</span>
        </div>
        <div class="shadcn-card-body" style="padding: 14px;" id="sourcesListContainer">
            <p style="text-align: center; color: #71717a; font-size: 12.5px; margin: 0;">Loading sources...</p>
        </div>
    </div>

    <!-- Right: Facebook Ads Setup & URL Generator Advice Card -->
    <div class="shadcn-card" style="margin-bottom: 0;">
        <div class="shadcn-card-header">
            <h2 class="shadcn-card-title"><i class="fa-brands fa-facebook" style="color: #71717a; margin-right: 6px;"></i> Automated Ad Tracking</h2>
        </div>
        <div class="shadcn-card-body" style="padding: 14px;">
            <p style="margin: 0 0 10px; font-size: 12px; color: #71717a; line-height: 1.5;">
                Every tap on your Facebook or Instagram ad sends an automated click ID (<code>fbclid</code>). Our tracker locks the campaign to the visitor's session across all pages they browse.
            </p>
            <div style="background: #f4f4f5; border: 1px solid #e4e4e7; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px;">
                <div style="font-size: 11px; font-weight: 600; color: #09090b; margin-bottom: 3px;">Meta Ads Manager URL Parameter:</div>
                <code style="font-size: 10.5px; color: #09090b; word-break: break-all;">utm_source=facebook&amp;utm_medium=paid&amp;utm_campaign={{campaign.name}}</code>
            </div>
            <button type="button" onclick="openUrlBuilderModal()" class="shadcn-btn shadcn-btn-outline" style="width: 100%; justify-content: center; height: 32px; font-size: 12px;">
                <i class="fa-solid fa-plus-circle" style="margin-right: 5px;"></i> Generate Custom Campaign Link
            </button>
        </div>
    </div>

</div>

<!-- Live Campaign Visitor Stream -->
<div class="shadcn-card" style="margin-bottom: 16px;">
    <div class="shadcn-card-header">
        <h2 class="shadcn-card-title"><i class="fa-solid fa-wave-square" style="color: #71717a; margin-right: 6px;"></i> Recent Campaign &amp; Social Visits</h2>
        <span style="font-size: 11px; color: #71717a;">Real-time clicks</span>
    </div>
    <div class="shadcn-card-body" style="overflow-x: auto;">
        <table class="wp-list-table widefat fixed striped" style="margin: 0; font-size: 12px;">
            <thead>
                <tr>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; width: 14%;">Time</th>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; width: 16%;">Source</th>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; width: 22%;">Campaign</th>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; width: 28%;">Page Visited</th>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; width: 10%;">Device</th>
                    <th style="font-weight: 600; font-size: 11.5px; color: #71717a; width: 10%;">Visitor IP</th>
                </tr>
            </thead>
            <tbody id="recentClicksTableBody">
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px; color: #71717a; font-size: 12.5px;">
                        <i class="fa-solid fa-spinner fa-spin" style="margin-right: 6px;"></i> Loading recent clicks...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Campaign URL Builder Modal -->
<div id="urlBuilderModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0, 0, 0, 0.55); backdrop-filter: blur(2px); align-items: center; justify-content: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); width: 100%; max-width: 540px; overflow: hidden; border: 1px solid #e4e4e7;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #f4f4f5; display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #09090b; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-link" style="color: #1877f2;"></i> Campaign Link Generator
                </h3>
                <p style="margin: 2px 0 0; font-size: 12px; color: #71717a;">Create tagged URLs for your Facebook Ads, Instagram posts, or influencer campaigns.</p>
            </div>
            <button type="button" onclick="closeUrlBuilderModal()" style="background: none; border: none; font-size: 18px; color: #71717a; cursor: pointer; padding: 4px 8px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div style="padding: 20px;">
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #09090b; margin-bottom: 4px;">Target Website URL</label>
                <input type="text" id="builder_base_url" value="https://yosshitaneha.com/" placeholder="e.g. https://yosshitaneha.com/product/champagne-gold-blouse" class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13px;" oninput="generateBuiltUrl()">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #09090b; margin-bottom: 4px;">Platform / Source</label>
                    <select id="builder_source" class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13px;" onchange="generateBuiltUrl()">
                        <option value="facebook" selected>Facebook</option>
                        <option value="instagram">Instagram</option>
                        <option value="google">Google</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="newsletter">Newsletter / Email</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #09090b; margin-bottom: 4px;">Medium</label>
                    <select id="builder_medium" class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13px;" onchange="generateBuiltUrl()">
                        <option value="paid" selected>Paid / Ad (cpc)</option>
                        <option value="story">Instagram / FB Story</option>
                        <option value="reels">Reels / Video</option>
                        <option value="post">Organic Post / Bio</option>
                        <option value="influencer">Influencer Collaboration</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #09090b; margin-bottom: 4px;">Campaign Name <span style="color: #e11d48;">*</span></label>
                <input type="text" id="builder_campaign" placeholder="e.g. bridal_blouses_nov26 or diwali_sale" class="shadcn-input" style="width: 100%; box-sizing: border-box; font-size: 13px;" oninput="generateBuiltUrl()">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #09090b; margin-bottom: 4px;">Generated Campaign URL</label>
                <textarea id="builder_output_url" readonly rows="3" style="width: 100%; box-sizing: border-box; font-family: monospace; font-size: 12px; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; color: #0f172a; resize: none;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeUrlBuilderModal()" class="shadcn-btn shadcn-btn-outline" style="font-size: 13px;">Close</button>
                <button type="button" onclick="copyBuiltUrl()" class="shadcn-btn shadcn-btn-primary" style="background: #1877f2; border-color: #1877f2; color: #fff; font-size: 13px;">
                    <i class="fa-solid fa-copy" style="margin-right: 5px;"></i> Copy URL
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentRange = '7days';
let campaignsData = [];

function changeRange(range, btn) {
    currentRange = range;
    document.querySelectorAll('.date-range-btn').forEach(b => {
        b.style.background = 'transparent';
        b.style.color = '#475569';
    });
    btn.style.background = '#1877f2';
    btn.style.color = '#fff';
    loadCampaignsData();
}

function loadCampaignsData() {
    fetch(`api/analytics-data.php?action=campaigns&range=${currentRange}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            // 1. Update KPIs
            const s = data.summary;
            document.getElementById('kpiFbAdsViews').textContent = Number(s.fb_ads_views || 0).toLocaleString();
            document.getElementById('kpiFbAdsVisitors').textContent = `${Number(s.fb_ads_visitors || 0).toLocaleString()} unique shoppers from Facebook ads`;

            document.getElementById('kpiIgViews').textContent = Number(s.ig_total_views || 0).toLocaleString();
            document.getElementById('kpiIgVisitors').textContent = `${Number(s.ig_total_visitors || 0).toLocaleString()} visitors from Instagram`;

            document.getElementById('kpiGoogViews').textContent = Number(s.goog_total_views || 0).toLocaleString();
            document.getElementById('kpiGoogVisitors').textContent = `${Number(s.goog_total_visitors || 0).toLocaleString()} search & ad visitors`;

            document.getElementById('kpiTopCampaign').textContent = s.top_campaign || 'None';
            document.getElementById('kpiTotalSessions').textContent = Number(s.total_sessions || 0).toLocaleString();

            // 2. Render Campaigns Table
            campaignsData = data.campaigns || [];
            document.getElementById('campaignsCountBadge').textContent = campaignsData.length;
            renderCampaignsTable(campaignsData);

            // 3. Render Sources Breakdown
            renderSourcesDistribution(data.sources || [], s.total_views || 1);

            // 4. Render Recent Clicks Stream
            renderRecentClicks(data.recent_logs || []);
        })
        .catch(err => {
            console.error('Error fetching campaign analytics:', err);
        });
}

function formatSourceMedium(source, medium) {
    const s = (source || '').toLowerCase().trim();
    const m = (medium || '').toLowerCase().trim();

    let friendlySource = source || 'Direct';
    let icon = 'fa-solid fa-globe';
    let color = '#475569';
    let bg = '#f1f5f9';

    if (s === 'an' || s === 'audience_network' || s.includes('audience')) {
        friendlySource = 'Audience Network';
        icon = 'fa-solid fa-network-wired';
        color = '#7c3aed';
        bg = '#ede9fe';
    } else if (s === 'ig' || s.includes('instagram')) {
        friendlySource = 'Instagram Ads';
        icon = 'fa-brands fa-instagram';
        color = '#db2777';
        bg = '#fce7f3';
    } else if (s === 'fb' || s.includes('facebook')) {
        friendlySource = 'Facebook Ads';
        icon = 'fa-brands fa-facebook';
        color = '#1877f2';
        bg = '#dbeafe';
    } else if (s === 'wa' || s.includes('whatsapp')) {
        friendlySource = 'WhatsApp';
        icon = 'fa-brands fa-whatsapp';
        color = '#16a34a';
        bg = '#dcfce7';
    } else if (s.includes('google')) {
        friendlySource = (m.includes('cpc') || m.includes('paid') || m.includes('ads')) ? 'Google Ads' : 'Google Search';
        icon = 'fa-brands fa-google';
        color = '#ea4335';
        bg = '#fee2e2';
    } else if (s.includes('youtube')) {
        friendlySource = 'YouTube';
        icon = 'fa-brands fa-youtube';
        color = '#dc2626';
        bg = '#fee2e2';
    }

    let friendlyMedium = medium || '';
    if (m === 'paid' || m === 'cpc' || m === 'ads' || m === 'ad') {
        friendlyMedium = 'Paid';
    } else if (m === 'social' || m === 'organic' || m === 'feed') {
        friendlyMedium = 'Organic';
    } else if (m === 'story' || m === 'stories') {
        friendlyMedium = 'Story';
    } else if (m === 'reels' || m === 'reel') {
        friendlyMedium = 'Reels';
    } else if (m) {
        friendlyMedium = m.charAt(0).toUpperCase() + m.slice(1);
    }

    return {
        sourceLabel: friendlySource,
        mediumLabel: friendlyMedium,
        icon: icon,
        color: color,
        bg: bg,
        raw: `${source || ''} / ${medium || ''}`
    };
}

function renderCampaignsTable(list) {
    const tbody = document.getElementById('campaignsTableBody');
    if (!list || list.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px 20px; color: #64748b;">
                    <div style="font-size: 32px; color: #cbd5e1; margin-bottom: 8px;"><i class="fa-solid fa-bullhorn"></i></div>
                    <div style="font-weight: 600; font-size: 14px; color: #1e293b; margin-bottom: 4px;">No Tagged Campaigns Found in this Period</div>
                    <div style="font-size: 12.5px; max-width: 480px; margin: 0 auto; color: #64748b; line-height: 1.5;">
                        Tagged clicks with <code>utm_campaign</code> or Meta <code>fbclid</code> will automatically show here. Use the <strong>Campaign URL Generator</strong> above to tag your Facebook ads.
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    list.forEach(c => {
        const fmt = formatSourceMedium(c.source, c.medium);
        const landingUrl = c.landing_page || '/';

        html += `
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="font-weight: 700; color: #0f172a; font-size: 13px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="color: ${fmt.color}; font-size: 14px;"><i class="${fmt.icon}"></i></span>
                        <span>${escapeHtml(c.campaign_name)}</span>
                    </div>
                </td>
                <td>
                    <div style="display: inline-flex; align-items: center; gap: 6px; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; background: ${fmt.bg}; color: ${fmt.color};">
                        <i class="${fmt.icon}"></i>
                        <span>${escapeHtml(fmt.sourceLabel)}</span>
                        ${fmt.mediumLabel ? `<span style="opacity: 0.7; font-size: 10px;">• ${escapeHtml(fmt.mediumLabel)}</span>` : ''}
                    </div>
                    <div style="font-size: 10px; color: #94a3b8; font-family: monospace; margin-top: 2px;">
                        ${escapeHtml(c.source)} / ${escapeHtml(c.medium)}
                    </div>
                </td>
                <td style="text-align: right; font-weight: 700; color: #0284c7; font-size: 13px;">
                    ${Number(c.visitors || 0).toLocaleString()}
                </td>
                <td style="text-align: right; font-weight: 700; color: #1e293b; font-size: 13px;">
                    ${Number(c.views || 0).toLocaleString()}
                </td>
                <td style="text-align: right; font-weight: 600; color: #475569; font-size: 13px;">
                    ${Number(c.sessions || 0).toLocaleString()}
                </td>
                <td style="text-align: right; font-weight: 600; color: #10b981; font-size: 13px;">
                    ${Number(c.product_visits || 0).toLocaleString()}
                </td>
                <td style="font-size: 12px; color: #64748b; font-family: monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;">
                    <a href="${escapeHtml(landingUrl)}" target="_blank" style="color: #4338ca; text-decoration: none;" title="${escapeHtml(landingUrl)}">
                        ${escapeHtml(landingUrl)} <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 10px;"></i>
                    </a>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function filterCampaignsTable() {
    const q = document.getElementById('campaignSearch').value.toLowerCase();
    const filtered = campaignsData.filter(c => {
        const fmt = formatSourceMedium(c.source, c.medium);
        return (c.campaign_name && c.campaign_name.toLowerCase().includes(q)) ||
            (c.source && c.source.toLowerCase().includes(q)) ||
            (c.medium && c.medium.toLowerCase().includes(q)) ||
            (fmt.sourceLabel && fmt.sourceLabel.toLowerCase().includes(q)) ||
            (fmt.mediumLabel && fmt.mediumLabel.toLowerCase().includes(q)) ||
            (c.landing_page && c.landing_page.toLowerCase().includes(q));
    });
    renderCampaignsTable(filtered);
}

function renderSourcesDistribution(sources, totalViews) {
    const container = document.getElementById('sourcesListContainer');
    // Filter out any crawler/bot entries
    const humanSources = (sources || []).filter(s => 
        s.traffic_source !== 'Googlebot / Crawler' && 
        !(s.traffic_source || '').toLowerCase().includes('bot') && 
        !(s.traffic_source || '').toLowerCase().includes('crawler')
    );

    if (!humanSources.length) {
        container.innerHTML = '<p style="text-align: center; color: #64748b; font-size: 13px;">No traffic sources recorded yet.</p>';
        return;
    }

    let html = '<div style="display: flex; flex-direction: column; gap: 14px;">';
    humanSources.forEach(s => {
        const pct = Math.max(0, parseFloat(s.percentage || 0));
        let barColor = '#64748b';
        let icon = 'fa-solid fa-arrow-pointer';

        if (s.traffic_source === 'Facebook Ads') { barColor = '#1877f2'; icon = 'fa-brands fa-facebook'; }
        else if (s.traffic_source === 'Facebook (Organic)') { barColor = '#3b82f6'; icon = 'fa-brands fa-facebook'; }
        else if (s.traffic_source === 'Instagram Ads' || s.traffic_source === 'Instagram (Organic)') { barColor = '#e1306c'; icon = 'fa-brands fa-instagram'; }
        else if (s.traffic_source === 'Google Ads') { barColor = '#ea4335'; icon = 'fa-brands fa-google'; }
        else if (s.traffic_source === 'Google Search') { barColor = '#f59e0b'; icon = 'fa-brands fa-google'; }
        else if (s.traffic_source === 'WhatsApp') { barColor = '#25d366'; icon = 'fa-brands fa-whatsapp'; }
        else if (s.traffic_source === 'Direct') { barColor = '#10b981'; icon = 'fa-solid fa-bolt'; }

        html += `
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; margin-bottom: 5px;">
                    <div style="font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                        <span style="color: ${barColor}; width: 16px; text-align: center;"><i class="${icon}"></i></span>
                        <span>${escapeHtml(s.traffic_source)}</span>
                    </div>
                    <div style="font-size: 12px; font-weight: 700; color: #475569;">
                        ${Number(s.views).toLocaleString()} views <span style="font-weight: 500; color: #94a3b8;">(${pct}%)</span>
                    </div>
                </div>
                <div style="width: 100%; height: 7px; background: #f1f5f9; border-radius: 9999px; overflow: hidden;">
                    <div style="width: ${pct}%; height: 100%; background: ${barColor}; border-radius: 9999px; transition: width 0.3s;"></div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

function renderRecentClicks(logs) {
    const tbody = document.getElementById('recentClicksTableBody');
    // Strictly filter out any bots/crawlers
    const humanLogs = (logs || []).filter(l => 
        l.traffic_source !== 'Googlebot / Crawler' && 
        l.device_type !== 'Search Bot' && 
        !(l.traffic_source || '').toLowerCase().includes('bot') && 
        !(l.traffic_source || '').toLowerCase().includes('crawler')
    );

    if (!humanLogs.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 20px; color:#64748b;">No recent campaign visits found.</td></tr>';
        return;
    }

    let html = '';
    humanLogs.forEach(l => {
        const fmt = formatSourceMedium(l.utm_source || l.traffic_source, l.utm_medium);
        const camp = l.utm_campaign || (l.fbclid ? 'Facebook Ad (fbclid)' : 'General Visitor');

        html += `
            <tr style="border-bottom: 1px solid #f1f5f9; font-size: 12.5px;">
                <td style="color: #64748b;">${escapeHtml(l.created_at)}</td>
                <td>
                    <span style="display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: ${fmt.bg}; color: ${fmt.color};">
                        <i class="${fmt.icon}"></i> ${escapeHtml(fmt.sourceLabel)}
                    </span>
                </td>
                <td style="font-weight: 600; color: #0f172a;">${escapeHtml(camp)}</td>
                <td style="font-family: monospace; font-size: 11.5px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">
                    ${escapeHtml(l.page_url)}
                </td>
                <td><span style="font-size: 11.5px; color: #64748b;">${escapeHtml(l.device_type || 'Desktop')}</span></td>
                <td><code style="font-size: 11px; color: #475569;">${escapeHtml(l.ip_address)}</code></td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

// URL Builder Helper
function openUrlBuilderModal() {
    document.getElementById('urlBuilderModal').style.display = 'flex';
    generateBuiltUrl();
}

function closeUrlBuilderModal() {
    document.getElementById('urlBuilderModal').style.display = 'none';
}

function generateBuiltUrl() {
    let base = document.getElementById('builder_base_url').value.trim() || 'https://yosshitaneha.com/';
    const source = document.getElementById('builder_source').value;
    const medium = document.getElementById('builder_medium').value;
    const campaign = encodeURIComponent(document.getElementById('builder_campaign').value.trim() || 'summer_campaign');

    const sep = base.includes('?') ? '&' : '?';
    const finalUrl = `${base}${sep}utm_source=${source}&utm_medium=${medium}&utm_campaign=${campaign}`;
    document.getElementById('builder_output_url').value = finalUrl;
}

function copyBuiltUrl() {
    const el = document.getElementById('builder_output_url');
    el.select();
    navigator.clipboard.writeText(el.value).then(() => {
        alert("Campaign URL copied to clipboard!\nYou can now paste this directly as the Destination URL in your Facebook Ad.");
    });
}

function exportCampaignsCSV() {
    if (!campaignsData.length) {
        alert("No campaign data to export.");
        return;
    }
    let csv = "Campaign Name,Source,Medium,Visitors,Pageviews,Sessions,Product Visits,Top Landing Page\n";
    campaignsData.forEach(c => {
        csv += `"${(c.campaign_name||'').replace(/"/g, '""')}","${c.source||''}","${c.medium||''}",${c.visitors||0},${c.views||0},${c.sessions||0},${c.product_visits||0},"${(c.landing_page||'').replace(/"/g, '""')}"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `campaign_analytics_${currentRange}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', () => {
    loadCampaignsData();
    // Auto-refresh every 30 seconds
    setInterval(loadCampaignsData, 30000);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
