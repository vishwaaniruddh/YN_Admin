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

<div class="content-area" style="padding: 24px;">

    <!-- Page Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 44px; height: 44px; background: linear-gradient(135deg, #1877f2, #0d5cb6); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(24, 119, 242, 0.25);">
                <i class="fa-solid fa-bullhorn" style="color: #fff; font-size: 19px;"></i>
            </div>
            <div>
                <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    Campaigns &amp; Traffic Sources
                    <span style="background: #e0f2fe; color: #0284c7; font-size: 11px; padding: 2px 8px; border-radius: 9999px; font-weight: 700;">Meta &amp; Google Ads</span>
                </h1>
                <p style="margin: 2px 0 0; font-size: 13px; color: #64748b;">Monitor traffic from Facebook Ads, Google, Instagram campaigns &amp; direct visitors in real-time</p>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <!-- Date Range Selector -->
            <div style="display: flex; background: #f1f5f9; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
                <button class="date-range-btn" data-range="today" onclick="changeRange('today', this)" style="padding: 8px 14px; font-size: 13px; font-weight: 500; border: none; cursor: pointer; transition: all .2s; background: transparent; color: #475569;">Today</button>
                <button class="date-range-btn active" data-range="7days" onclick="changeRange('7days', this)" style="padding: 8px 14px; font-size: 13px; font-weight: 500; border: none; cursor: pointer; transition: all .2s; background: #1877f2; color: #fff;">7 Days</button>
                <button class="date-range-btn" data-range="30days" onclick="changeRange('30days', this)" style="padding: 8px 14px; font-size: 13px; font-weight: 500; border: none; cursor: pointer; transition: all .2s; background: transparent; color: #475569;">30 Days</button>
                <button class="date-range-btn" data-range="90days" onclick="changeRange('90days', this)" style="padding: 8px 14px; font-size: 13px; font-weight: 500; border: none; cursor: pointer; transition: all .2s; background: transparent; color: #475569;">90 Days</button>
            </div>

            <!-- Campaign URL Builder Modal Trigger -->
            <button onclick="openUrlBuilderModal()" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 13px; font-weight: 500; color: #1e293b; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; transition: all .2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.borderColor='#1877f2';this.style.color='#1877f2'" onmouseout="this.style.borderColor='#cbd5e1';this.style.color='#1e293b'">
                <i class="fa-solid fa-link" style="color: #1877f2;"></i> Campaign URL Generator
            </button>

            <!-- Export CSV -->
            <button id="exportCsvBtn" onclick="exportCampaignsCSV()" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 13px; font-weight: 500; color: #fff; background: #10b981; border: none; border-radius: 8px; cursor: pointer; transition: background .2s;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">
                <i class="fa-solid fa-file-csv"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Quick Navigation to other Analytics pages -->
    <div style="display: flex; gap: 8px; margin-bottom: 24px; overflow-x: auto; padding-bottom: 4px;">
        <a href="analytics.php" style="padding: 6px 14px; border-radius: 6px; font-size: 12.5px; font-weight: 600; text-decoration: none; background: #fff; border: 1px solid #e2e8f0; color: #475569;">
            <i class="fa-solid fa-chart-line" style="margin-right: 4px;"></i> Overview
        </a>
        <a href="analytics-campaigns.php" style="padding: 6px 14px; border-radius: 6px; font-size: 12.5px; font-weight: 600; text-decoration: none; background: #1877f2; border: 1px solid #1877f2; color: #fff;">
            <i class="fa-solid fa-bullhorn" style="margin-right: 4px;"></i> Facebook &amp; Campaigns
        </a>
        <a href="analytics-products.php" style="padding: 6px 14px; border-radius: 6px; font-size: 12.5px; font-weight: 600; text-decoration: none; background: #fff; border: 1px solid #e2e8f0; color: #475569;">
            <i class="fa-solid fa-box" style="margin-right: 4px;"></i> Products
        </a>
        <a href="analytics-categories.php" style="padding: 6px 14px; border-radius: 6px; font-size: 12.5px; font-weight: 600; text-decoration: none; background: #fff; border: 1px solid #e2e8f0; color: #475569;">
            <i class="fa-solid fa-layer-group" style="margin-right: 4px;"></i> Categories
        </a>
        <a href="analytics-pages.php" style="padding: 6px 14px; border-radius: 6px; font-size: 12.5px; font-weight: 600; text-decoration: none; background: #fff; border: 1px solid #e2e8f0; color: #475569;">
            <i class="fa-solid fa-globe" style="margin-right: 4px;"></i> Pages &amp; Search
        </a>
    </div>

    <!-- KPI Cards Grid -->
    <div class="shadcn-stat-grid" style="margin-bottom: 24px;">
        <!-- Facebook Ads Traffic -->
        <div class="shadcn-stat-card" style="min-width: 0; overflow: hidden; border-left: 3px solid #1877f2;">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title">Facebook Ads Traffic</span>
                <div class="shadcn-stat-icon-wrap" style="background: rgba(24, 119, 242, 0.12); color: #1877f2;">
                    <i class="fa-brands fa-facebook-f"></i>
                </div>
            </div>
            <div>
                <div class="shadcn-stat-value" id="kpiFbAdsViews" style="color: #1877f2;">0</div>
                <div class="shadcn-stat-note" id="kpiFbAdsVisitors">0 unique shoppers from Facebook ads</div>
            </div>
        </div>

        <!-- Instagram Traffic -->
        <div class="shadcn-stat-card" style="min-width: 0; overflow: hidden; border-left: 3px solid #e1306c;">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title">Instagram Traffic</span>
                <div class="shadcn-stat-icon-wrap" style="background: rgba(225, 48, 108, 0.12); color: #e1306c;">
                    <i class="fa-brands fa-instagram"></i>
                </div>
            </div>
            <div>
                <div class="shadcn-stat-value" id="kpiIgViews" style="color: #e1306c;">0</div>
                <div class="shadcn-stat-note" id="kpiIgVisitors">0 visitors from Instagram profile &amp; ads</div>
            </div>
        </div>

        <!-- Google Traffic -->
        <div class="shadcn-stat-card" style="min-width: 0; overflow: hidden; border-left: 3px solid #ea4335;">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title">Google Traffic</span>
                <div class="shadcn-stat-icon-wrap" style="background: rgba(234, 67, 53, 0.12); color: #ea4335;">
                    <i class="fa-brands fa-google"></i>
                </div>
            </div>
            <div>
                <div class="shadcn-stat-value" id="kpiGoogViews" style="color: #ea4335;">0</div>
                <div class="shadcn-stat-note" id="kpiGoogVisitors">0 search &amp; ad clicks</div>
            </div>
        </div>

        <!-- Top Performing Campaign -->
        <div class="shadcn-stat-card" style="min-width: 0; overflow: hidden; border-left: 3px solid #8b5cf6;">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title">Top Campaign</span>
                <div class="shadcn-stat-icon-wrap" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6;">
                    <i class="fa-solid fa-trophy"></i>
                </div>
            </div>
            <div>
                <div class="shadcn-stat-value" id="kpiTopCampaign" style="font-size: 16px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.4; color: #8b5cf6;">None</div>
                <div class="shadcn-stat-note">Highest traffic campaign</div>
            </div>
        </div>

        <!-- Total Sessions from Campaigns -->
        <div class="shadcn-stat-card" style="min-width: 0; overflow: hidden; border-left: 3px solid #10b981;">
            <div class="shadcn-stat-top">
                <span class="shadcn-stat-title">All Marketing Sessions</span>
                <div class="shadcn-stat-icon-wrap" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <div>
                <div class="shadcn-stat-value" id="kpiTotalSessions" style="color: #10b981;">0</div>
                <div class="shadcn-stat-note">Across all sources in period</div>
            </div>
        </div>
    </div>

    <!-- Active Marketing Campaigns Table -->
    <div class="shadcn-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 24px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="padding: 18px 22px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #09090b; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-bullseye" style="color: #1877f2;"></i> Campaign Performance
                    <span id="campaignsCountBadge" style="background: #f1f5f9; color: #475569; font-size: 11px; padding: 2px 8px; border-radius: 9999px;">0</span>
                </h3>
                <p style="margin: 3px 0 0; font-size: 12px; color: #64748b;">Direct breakdown of traffic by UTM Campaign, Facebook Ad click, and medium</p>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <input type="text" id="campaignSearch" onkeyup="filterCampaignsTable()" placeholder="Search campaigns..." class="shadcn-input" style="height: 36px; font-size: 12.5px; width: 220px;">
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="wp-list-table widefat fixed striped" style="margin: 0; border: none;">
                <thead>
                    <tr style="background: #fafafa; border-bottom: 1px solid #e2e8f0;">
                        <th style="font-weight: 700; font-size: 12px; color: #475569; width: 26%;">Campaign Name</th>
                        <th style="font-weight: 700; font-size: 12px; color: #475569; width: 14%;">Source / Medium</th>
                        <th style="font-weight: 700; font-size: 12px; color: #475569; text-align: right; width: 10%;">Visitors</th>
                        <th style="font-weight: 700; font-size: 12px; color: #475569; text-align: right; width: 10%;">Pageviews</th>
                        <th style="font-weight: 700; font-size: 12px; color: #475569; text-align: right; width: 10%;">Sessions</th>
                        <th style="font-weight: 700; font-size: 12px; color: #475569; text-align: right; width: 12%;">Product Visits</th>
                        <th style="font-weight: 700; font-size: 12px; color: #475569; width: 18%;">Top Landing Page</th>
                    </tr>
                </thead>
                <tbody id="campaignsTableBody">
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 32px; color: #64748b; font-size: 13px;">
                            <i class="fa-solid fa-spinner fa-spin" style="margin-right: 6px;"></i> Loading campaign data...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Two-Column Layout: All Traffic Sources & Facebook Campaign Guide -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">

        <!-- Left: All Traffic Sources Breakdown -->
        <div class="shadcn-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #09090b; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-chart-pie" style="color: #6366f1;"></i> Traffic Sources Distribution
                </h3>
                <span style="font-size: 11.5px; color: #64748b;">All acquisition channels</span>
            </div>
            <div style="padding: 20px;" id="sourcesListContainer">
                <p style="text-align: center; color: #64748b; font-size: 13px; margin: 0;">Loading sources...</p>
            </div>
        </div>

        <!-- Right: Facebook Ads Setup & URL Generator Advice Card -->
        <div class="shadcn-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #09090b; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-brands fa-facebook" style="color: #1877f2;"></i> How Facebook Campaign Tracking Works
                </h3>
            </div>
            <div style="padding: 20px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                    <div style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                        <i class="fa-solid fa-circle-check" style="color: #10b981; margin-right: 6px;"></i> Automated Detection
                    </div>
                    <p style="margin: 0; font-size: 12px; color: #475569; line-height: 1.5;">
                        Whenever anyone taps on your Facebook or Instagram ad, Facebook automatically passes <code>fbclid</code> and referrer headers. Our system automatically captures these and attributes all pages they visit!
                    </p>
                </div>

                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                    <div style="font-size: 13px; font-weight: 700; color: #166534; margin-bottom: 4px;">
                        <i class="fa-solid fa-wand-magic-sparkles" style="color: #16a34a; margin-right: 6px;"></i> Pro-Tip for Meta Ads Manager
                    </div>
                    <p style="margin: 0; font-size: 12px; color: #166534; line-height: 1.5;">
                        In your Facebook Ads Manager, paste this into the <strong>URL Parameters</strong> box:
                        <br><code style="background: #fff; border: 1px solid #86efac; padding: 3px 6px; border-radius: 4px; display: inline-block; margin-top: 5px; font-size: 11px;">utm_source=facebook&amp;utm_medium=paid&amp;utm_campaign={{campaign.name}}&amp;utm_content={{ad.name}}</code>
                        <br>Meta will automatically replace <code>{{campaign.name}}</code> with your actual ad campaign name!
                    </p>
                </div>

                <button type="button" onclick="openUrlBuilderModal()" class="shadcn-btn shadcn-btn-primary" style="width: 100%; justify-content: center; background: #1877f2; border-color: #1877f2;">
                    <i class="fa-solid fa-plus-circle" style="margin-right: 6px;"></i> Generate Custom Facebook Ad Link
                </button>
            </div>
        </div>

    </div>

    <!-- Live Campaign Visitor Stream -->
    <div class="shadcn-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #09090b; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-wave-square" style="color: #10b981;"></i> Recent Campaign &amp; Social Visits
            </h3>
            <span style="font-size: 11.5px; color: #64748b;">Latest clicks arriving from Facebook, Google, and Ads</span>
        </div>
        <div style="overflow-x: auto;">
            <table class="wp-list-table widefat fixed striped" style="margin: 0; border: none;">
                <thead>
                    <tr style="background: #fafafa; border-bottom: 1px solid #e2e8f0;">
                        <th style="font-weight: 700; font-size: 12px; color: #475569; width: 14%;">Time</th>
                        <th style="font-weight: 700; font-size: 12px; color: #475569; width: 16%;">Traffic Source</th>
                        <th style="font-weight: 700; font-size: 12px; color: #475569; width: 22%;">Campaign</th>
                        <th style="font-weight: 700; font-size: 12px; color: #475569; width: 28%;">Page Visited</th>
                        <th style="font-weight: 700; font-size: 12px; color: #475569; width: 10%;">Device</th>
                        <th style="font-weight: 700; font-size: 12px; color: #475569; width: 10%;">Visitor IP</th>
                    </tr>
                </thead>
                <tbody id="recentClicksTableBody">
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 24px; color: #64748b; font-size: 13px;">
                            <i class="fa-solid fa-spinner fa-spin" style="margin-right: 6px;"></i> Loading recent clicks...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
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
        let badgeColor = '#64748b';
        let badgeIcon = 'fa-solid fa-globe';
        const srcLower = (c.source || '').toLowerCase();

        if (srcLower.includes('facebook') || srcLower.includes('fb')) {
            badgeColor = '#1877f2';
            badgeIcon = 'fa-brands fa-facebook';
        } else if (srcLower.includes('instagram')) {
            badgeColor = '#e1306c';
            badgeIcon = 'fa-brands fa-instagram';
        } else if (srcLower.includes('google')) {
            badgeColor = '#ea4335';
            badgeIcon = 'fa-brands fa-google';
        } else if (srcLower.includes('whatsapp')) {
            badgeColor = '#25d366';
            badgeIcon = 'fa-brands fa-whatsapp';
        }

        const landingUrl = c.landing_page || '/';

        html += `
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="font-weight: 700; color: #0f172a; font-size: 13px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="color: ${badgeColor}; font-size: 14px;"><i class="${badgeIcon}"></i></span>
                        <span>${escapeHtml(c.campaign_name)}</span>
                    </div>
                </td>
                <td>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #334155; text-transform: uppercase;">
                        ${escapeHtml(c.source)} / ${escapeHtml(c.medium)}
                    </span>
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
    const filtered = campaignsData.filter(c => 
        (c.campaign_name && c.campaign_name.toLowerCase().includes(q)) ||
        (c.source && c.source.toLowerCase().includes(q)) ||
        (c.medium && c.medium.toLowerCase().includes(q)) ||
        (c.landing_page && c.landing_page.toLowerCase().includes(q))
    );
    renderCampaignsTable(filtered);
}

function renderSourcesDistribution(sources, totalViews) {
    const container = document.getElementById('sourcesListContainer');
    if (!sources || sources.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #64748b; font-size: 13px;">No traffic sources recorded yet.</p>';
        return;
    }

    let html = '<div style="display: flex; flex-direction: column; gap: 14px;">';
    sources.forEach(s => {
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
        else if (s.traffic_source === 'Googlebot / Crawler') { barColor = '#6366f1'; icon = 'fa-solid fa-robot'; }

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
    if (!logs || logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 20px; color:#64748b;">No recent campaign visits found.</td></tr>';
        return;
    }

    let html = '';
    logs.forEach(l => {
        let badgeColor = '#64748b';
        if ((l.traffic_source || '').includes('Facebook')) badgeColor = '#1877f2';
        else if ((l.traffic_source || '').includes('Instagram')) badgeColor = '#e1306c';
        else if ((l.traffic_source || '').includes('Google')) badgeColor = '#ea4335';

        const camp = l.utm_campaign || (l.fbclid ? 'Facebook Ad (fbclid)' : 'General Visitor');

        html += `
            <tr style="border-bottom: 1px solid #f1f5f9; font-size: 12.5px;">
                <td style="color: #64748b;">${escapeHtml(l.created_at)}</td>
                <td>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; color: #fff; background: ${badgeColor};">
                        ${escapeHtml(l.traffic_source || 'Unknown')}
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
