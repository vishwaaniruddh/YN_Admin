<?php
// admin/collection-ai-sorter.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Gemini AI Outfit Sorter & Organizer";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$secretsFile = __DIR__ . '/config/secrets.php';
$hasApiKey = false;
if (file_exists($secretsFile)) {
    $sec = include($secretsFile);
    $hasApiKey = !empty($sec['GEMINI_API_KEY']);
}
?>

<style>
:root {
    --ai-purple: #6366f1;
    --ai-purple-light: #eef2ff;
    --ai-purple-dark: #4f46e5;
    --ai-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #db2777 100%);
}

.ai-hero-banner {
    background: var(--ai-gradient);
    border-radius: 12px;
    padding: 24px 28px;
    color: #ffffff;
    margin-bottom: 24px;
    box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.3);
    position: relative;
    overflow: hidden;
}

.ai-hero-banner::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.ai-hero-title {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.ai-hero-subtitle {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.9);
    max-width: 800px;
    line-height: 1.5;
}

.ai-steps-nav {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 18px;
    flex-wrap: wrap;
}

.ai-step-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #ffffff;
}

.ai-step-badge.active {
    background: #ffffff;
    color: var(--ai-purple-dark);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Category Grid Selector */
.cat-selector-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.cat-select-card {
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.cat-select-card:hover {
    border-color: var(--ai-purple);
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -4px rgba(99, 102, 241, 0.15);
}

.cat-select-card.selected {
    border-color: var(--ai-purple);
    background: #faf5ff;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.cat-thumb-box {
    height: 110px;
    background: #f1f5f9;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cat-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cat-card-title {
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.cat-badge-unorg {
    background: #fee2e2;
    color: #991b1b;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
}

.cat-badge-clean {
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
}

/* Studio Workspace Container */
.studio-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    margin-bottom: 24px;
}

.panel-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 16px;
    margin-bottom: 20px;
}

.panel-title {
    font-size: 17px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Cluster Outfit Cards */
.outfit-clusters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 24px;
}

.cluster-card {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    display: flex;
    flex-direction: column;
}

.cluster-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}

.cluster-card-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.cluster-card-body {
    padding: 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.cluster-photos-strip {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 10px;
    background: #f8fafc;
    padding: 10px;
    border-radius: 8px;
    border: 1px dashed #cbd5e1;
    min-height: 110px;
}

.cluster-photo-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: 6px;
    overflow: hidden;
    background: #e2e8f0;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.cluster-photo-item.is-cover {
    border-color: #eab308;
    box-shadow: 0 0 0 2px rgba(234, 179, 8, 0.4);
}

.cluster-photo-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-angle-tag {
    position: absolute;
    bottom: 3px;
    left: 3px;
    right: 3px;
    background: rgba(15, 23, 42, 0.8);
    color: #ffffff;
    font-size: 9px;
    font-weight: 600;
    padding: 2px 4px;
    border-radius: 3px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.photo-cover-badge {
    position: absolute;
    top: 3px;
    left: 3px;
    background: #eab308;
    color: #000;
    font-size: 8px;
    font-weight: 800;
    padding: 1px 4px;
    border-radius: 3px;
    text-transform: uppercase;
}

.photo-action-btn {
    position: absolute;
    top: 3px;
    right: 3px;
    width: 20px;
    height: 20px;
    background: rgba(0,0,0,0.6);
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 9px;
    border: none;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.2s;
}

.cluster-photo-item:hover .photo-action-btn {
    opacity: 1;
}

.photo-action-btn:hover {
    background: #ef4444;
}

.form-label-xs {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 4px;
    display: block;
}

.form-input-sm {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    transition: border-color 0.2s;
}

.form-input-sm:focus {
    border-color: var(--ai-purple);
    outline: none;
}

/* Pulsing AI Processing Animation */
.ai-processing-state {
    text-align: center;
    padding: 60px 20px;
}

.ai-pulse-orb {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: var(--ai-gradient);
    margin: 0 auto 20px auto;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 28px;
    box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7);
    animation: orbPulse 1.8s infinite cubic-bezier(0.66, 0, 0, 1);
}

@keyframes orbPulse {
    to {
        box-shadow: 0 0 0 30px rgba(99, 102, 241, 0);
    }
}
</style>

<div class="wrap">
    <!-- Hero Banner -->
    <div class="ai-hero-banner">
        <div class="ai-hero-title">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            Gemini AI Outfit Sorter &amp; Subfolder Auto-Organizer
        </div>
        <div class="ai-hero-subtitle">
            Instantly cluster hundreds of mixed photoshoot images by outfit design. Gemini recognizes front/back views, embroidery details, and fabrics, automatically grouping them into distinct Outfit Styles, creating subfolders on the server, and linking them to your Lookbook database.
        </div>

        <div class="ai-steps-nav">
            <div class="ai-step-badge active" id="badgeStep1">
                <i class="fa-solid fa-folder-open"></i> 1. Select Category
            </div>
            <div class="ai-step-badge" id="badgeStep2">
                <i class="fa-solid fa-microchip"></i> 2. AI Clustering
            </div>
            <div class="ai-step-badge" id="badgeStep3">
                <i class="fa-solid fa-check-double"></i> 3. Review &amp; Organize
            </div>
            <div class="ai-step-badge" id="badgeStep4">
                <i class="fa-solid fa-database"></i> 4. Database Sync
            </div>
        </div>
    </div>

    <?php if (!$hasApiKey): ?>
    <div class="notice notice-warning" style="padding: 12px 16px; border-left-color: #f59e0b; margin-bottom: 20px; border-radius: 6px;">
        <strong><i class="fa-solid fa-triangle-exclamation"></i> Gemini API Key Notice:</strong> 
        Please make sure your <code>GEMINI_API_KEY</code> is set in <code>config/secrets.php</code> or in System Settings.
    </div>
    <?php endif; ?>

    <!-- STEP 1: CATEGORY SELECTION -->
    <div id="step1Section" class="studio-panel">
        <div class="panel-header-bar">
            <div>
                <div class="panel-title"><i class="fa-solid fa-images" style="color: var(--ai-purple);"></i> Step 1: Select Collection Category</div>
                <div style="font-size: 13px; color: #64748b; margin-top: 2px;">Select a category folder containing raw/unorganized client photoshoot images.</div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="button" onclick="loadCategories()"><i class="fa-solid fa-rotate"></i> Refresh</button>
                <a href="collections.php" class="button"><i class="fa-solid fa-arrow-left"></i> Back to Collections</a>
            </div>
        </div>

        <div id="categoriesGrid" class="cat-selector-grid">
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #94a3b8;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
                <div style="margin-top: 8px;">Loading collection categories...</div>
            </div>
        </div>

        <!-- Selected Category Action Bar -->
        <div id="categorySelectedBar" style="display: none; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="font-size: 16px; font-weight: 700; color: #0f172a;">
                    Selected: <span id="selCatName" style="color: var(--ai-purple);">Blouse</span> 
                    <span id="selCatCountBadge" class="cat-badge-unorg" style="margin-left: 8px;">261 unorganized images</span>
                </div>
                <div style="font-size: 13px; color: #64748b; margin-top: 4px;">
                    Choose batch size to process with Gemini Vision (recommended: 15–20 photos per batch for maximum grouping accuracy).
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <div>
                    <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 2px;">Batch Size:</label>
                    <select id="batchSizeSelect" class="form-input-sm" style="width: 160px;">
                        <option value="18">18 images / pass</option>
                        <option value="24">24 images / pass</option>
                        <option value="36">36 images / pass</option>
                        <option value="50">50 images / pass</option>
                        <option value="72" selected>72 images / pass</option>
                        <option value="100">100 images / pass</option>
                    </select>
                </div>

                <div style="padding-top: 14px;">
                    <button type="button" class="button button-primary" id="btnStartAiClustering" onclick="startClustering()" style="background: var(--ai-purple); border-color: var(--ai-purple-dark); padding: 6px 18px; font-weight: 700;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Run Gemini AI Sorter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 2: PROCESSING ANIMATION STATE -->
    <div id="step2Processing" class="studio-panel ai-processing-state" style="display: none;">
        <div class="ai-pulse-orb">
            <i class="fa-solid fa-brain"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 6px;">Gemini Vision is Analyzing Outfit Photos...</h3>
        <p style="font-size: 14px; color: #64748b; max-width: 600px; margin: 0 auto 16px auto;" id="aiProcessStatusText">
            Detecting matching fabric patterns, embroidery colors, front/back angles, and model poses...
        </p>
        <div style="max-width: 400px; height: 8px; background: #e2e8f0; border-radius: 4px; margin: 0 auto; overflow: hidden;">
            <div id="aiProgressBar" style="width: 30%; height: 100%; background: var(--ai-gradient); transition: width 0.3s;"></div>
        </div>
    </div>

    <!-- STEP 3: CLUSTER REVIEW STUDIO -->
    <div id="step3Studio" class="studio-panel" style="display: none;">
        <div class="panel-header-bar">
            <div>
                <div class="panel-title">
                    <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--ai-purple);"></i>
                    AI Outfit Clusters Review &amp; Approval Studio
                </div>
                <div style="font-size: 13px; color: #64748b; margin-top: 2px;">
                    Gemini grouped the batch into <strong id="clusterCountBadge" style="color: var(--ai-purple-dark);">0 Outfits</strong>. Review details, adjust names, or click to set cover photos.
                </div>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="button" onclick="cancelToCategories()"><i class="fa-solid fa-arrow-left"></i> Change Category</button>
                <button type="button" class="button button-primary" onclick="commitAndSaveOutfits()" id="btnSaveOutfits" style="background: #059669; border-color: #047857; font-weight: 700;">
                    <i class="fa-solid fa-folder-tree"></i> 1-Click Organize Subfolders &amp; Save Outfits
                </button>
            </div>
        </div>

        <!-- Options bar for saving -->
        <div style="background: #f1f5f9; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 13px;">
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="checkbox" id="chkCreateSubfolders" checked> <strong>Create Organized Subfolders on Server</strong> (e.g. <code>uploads/collections/Blouse/royal-wine-raw-silk-zardozi-bridal-blouse/</code>)
                </label>
                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="radio" name="fileActionRadio" value="move" checked> Move Files
                </label>
                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="radio" name="fileActionRadio" value="copy"> Copy Files (keep raw backup)
                </label>
            </div>
            <div>
                <span class="badge" style="background: var(--ai-purple); color: #ffffff; padding: 3px 8px; border-radius: 4px; font-size: 11px;">Gemini Vision Active</span>
            </div>
        </div>

        <!-- Outfits Grid -->
        <div id="clustersGrid" class="outfit-clusters-grid">
            <!-- Dynamically populated -->
        </div>

        <div style="margin-top: 28px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 20px;">
            <button type="button" class="button button-primary button-hero" onclick="commitAndSaveOutfits()" style="background: #059669; border-color: #047857; font-size: 15px; padding: 10px 28px; font-weight: 800;">
                <i class="fa-solid fa-check-circle"></i> Approve &amp; Save All Outfits to Lookbook
            </button>
        </div>
    </div>
</div>

<script>
let allCategories = [];
let selectedCategory = null;
let currentUnorganizedImages = [];
let currentClusters = [];

document.addEventListener('DOMContentLoaded', () => {
    loadCategories();
});

// Load category folders list from server
async function loadCategories() {
    const grid = document.getElementById('categoriesGrid');
    grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #94a3b8;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><div style="margin-top: 8px;">Scanning collection folders...</div></div>`;
    
    try {
        const res = await fetch('api/ai_collection_sorter.php?action=list_categories');
        const data = await res.json();
        
        if (!data.success) {
            grid.innerHTML = `<div style="grid-column: 1/-1; color: #ef4444; padding: 20px;">Error: ${data.error || 'Failed to load categories'}</div>`;
            return;
        }

        allCategories = data.categories || [];
        renderCategoryCards();
    } catch (err) {
        grid.innerHTML = `<div style="grid-column: 1/-1; color: #ef4444; padding: 20px;">Network error: ${err.message}</div>`;
    }
}

function renderCategoryCards() {
    const grid = document.getElementById('categoriesGrid');
    if (!allCategories.length) {
        grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">No category folders found in <code>uploads/collections/</code>.</div>`;
        return;
    }

    grid.innerHTML = allCategories.map(cat => {
        const hasUnorg = cat.unorganized_count > 0;
        const previewSrc = cat.preview_image;
        const isSelected = selectedCategory && selectedCategory.folder_name === cat.folder_name;

        return `
            <div class="cat-select-card ${isSelected ? 'selected' : ''}" onclick="selectCategory('${cat.folder_name}')">
                <div class="cat-thumb-box">
                    ${previewSrc 
                        ? `<img src="${previewSrc}" alt="${cat.category_name}" class="cat-thumb-img" onerror="this.onerror=null; this.src='assets/images/placeholder.svg';">` 
                        : `<div style="display: flex; flex-direction: column; align-items: center; color: #94a3b8; font-size: 11px;"><i class="fa-solid fa-folder-open fa-2x" style="margin-bottom: 4px; color: #cbd5e1;"></i><span>Empty</span></div>`
                    }
                </div>
                <div>
                    <div class="cat-card-title">
                        <span>${cat.category_name}</span>
                        ${hasUnorg 
                            ? `<span class="cat-badge-unorg">${cat.unorganized_count} raw</span>` 
                            : `<span class="cat-badge-clean"><i class="fa-solid fa-check"></i> ${cat.subfolders_count} styles</span>`
                        }
                    </div>
                    <div style="font-size: 12px; color: #64748b;">
                        ${cat.subfolders_count} subfolders &bull; ${cat.db_outfits_count} DB outfits
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function selectCategory(folderName) {
    selectedCategory = allCategories.find(c => c.folder_name === folderName);
    renderCategoryCards();

    const bar = document.getElementById('categorySelectedBar');
    if (selectedCategory) {
        bar.style.display = 'flex';
        document.getElementById('selCatName').textContent = selectedCategory.category_name;
        document.getElementById('selCatCountBadge').textContent = `${selectedCategory.unorganized_count} unorganized images`;
        document.getElementById('selCatCountBadge').className = selectedCategory.unorganized_count > 0 ? 'cat-badge-unorg' : 'cat-badge-clean';
        bar.scrollIntoView({ behavior: 'smooth' });
    } else {
        bar.style.display = 'none';
    }
}

// Start AI clustering
async function startClustering() {
    if (!selectedCategory) return;

    // Scan the folder first
    document.getElementById('step1Section').style.display = 'none';
    document.getElementById('step2Processing').style.display = 'block';
    document.getElementById('step3Studio').style.display = 'none';

    document.getElementById('badgeStep1').classList.remove('active');
    document.getElementById('badgeStep2').classList.add('active');
    document.getElementById('badgeStep3').classList.remove('active');

    document.getElementById('aiProgressBar').style.width = '20%';
    document.getElementById('aiProcessStatusText').textContent = `Scanning raw images in ${selectedCategory.category_name} folder...`;

    try {
        const scanRes = await fetch(`api/ai_collection_sorter.php?action=scan_folder&folder=${encodeURIComponent(selectedCategory.folder_name)}`);
        const scanData = await scanRes.json();

        if (!scanData.success || !scanData.images || !scanData.images.length) {
            alert(scanData.error || `No unorganized raw images found in ${selectedCategory.category_name} folder.`);
            cancelToCategories();
            return;
        }

        currentUnorganizedImages = scanData.images;
        const batchSize = parseInt(document.getElementById('batchSizeSelect').value) || 18;
        const batchToProcess = currentUnorganizedImages.slice(0, batchSize).map(img => img.filename);

        document.getElementById('aiProgressBar').style.width = '55%';
        document.getElementById('aiProcessStatusText').textContent = `Gemini Vision is analyzing batch of ${batchToProcess.length} images and identifying matching outfits...`;

        const clusterRes = await fetch('api/ai_collection_sorter.php?action=ai_cluster_batch', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                folder: selectedCategory.folder_name,
                category_context: selectedCategory.category_name,
                filenames: batchToProcess
            })
        });

        const clusterData = await clusterRes.json();

        if (!clusterData.success || !clusterData.outfits || !clusterData.outfits.length) {
            alert(clusterData.error || 'Gemini clustering returned no valid outfits.');
            cancelToCategories();
            return;
        }

        currentClusters = clusterData.outfits;
        document.getElementById('aiProgressBar').style.width = '100%';

        setTimeout(() => {
            renderStudio();
        }, 400);

    } catch (err) {
        alert('AI Clustering failed: ' + err.message);
        cancelToCategories();
    }
}

function renderStudio() {
    document.getElementById('step2Processing').style.display = 'none';
    document.getElementById('step3Studio').style.display = 'block';

    document.getElementById('badgeStep2').classList.remove('active');
    document.getElementById('badgeStep3').classList.add('active');

    document.getElementById('clusterCountBadge').textContent = `${currentClusters.length} Outfits`;

    const grid = document.getElementById('clustersGrid');
    grid.innerHTML = currentClusters.map((outfit, cIdx) => {
        return `
            <div class="cluster-card" id="clusterCard_${cIdx}">
                <div class="cluster-card-header">
                    <div>
                        <span style="font-size: 11px; font-weight: 800; color: var(--ai-purple); text-transform: uppercase;">Outfit #${cIdx + 1}</span>
                        <span style="font-size: 12px; color: #64748b; margin-left: 6px;">(${outfit.images.length} photos)</span>
                    </div>
                    <div>
                        <span class="badge" style="background: #e0e7ff; color: #3730a3; font-size: 11px; padding: 2px 6px; border-radius: 4px;">${outfit.style_code}</span>
                    </div>
                </div>

                <div class="cluster-card-body">
                    <div>
                        <label class="form-label-xs">Outfit Title</label>
                        <input type="text" class="form-input-sm" value="${escapeHtml(outfit.outfit_title)}" onchange="updateOutfitField(${cIdx}, 'outfit_title', this.value)" style="font-weight: 700; color: #0f172a;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div>
                            <label class="form-label-xs">Fabric</label>
                            <input type="text" class="form-input-sm" value="${escapeHtml(outfit.fabric)}" onchange="updateOutfitField(${cIdx}, 'fabric', this.value)">
                        </div>
                        <div>
                            <label class="form-label-xs">Work / Embroidery</label>
                            <input type="text" class="form-input-sm" value="${escapeHtml(outfit.work_type)}" onchange="updateOutfitField(${cIdx}, 'work_type', this.value)">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div>
                            <label class="form-label-xs">Color</label>
                            <input type="text" class="form-input-sm" value="${escapeHtml(outfit.color)}" onchange="updateOutfitField(${cIdx}, 'color', this.value)">
                        </div>
                        <div>
                            <label class="form-label-xs">Subfolder Name</label>
                            <input type="text" class="form-input-sm" value="${escapeHtml(outfit.folder_slug)}" onchange="updateOutfitField(${cIdx}, 'folder_slug', this.value)" style="font-family: monospace; font-size: 11px;">
                        </div>
                    </div>

                    <div>
                        <label class="form-label-xs">Lookbook Description</label>
                        <textarea class="form-input-sm" rows="2" style="font-size: 12px;" onchange="updateOutfitField(${cIdx}, 'description', this.value)">${escapeHtml(outfit.description)}</textarea>
                    </div>

                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="form-label-xs" style="margin-bottom: 0;">Outfit Photos &amp; Angles</label>
                            <span style="font-size: 10px; color: #94a3b8;">Click photo to set as Cover</span>
                        </div>
                        <div class="cluster-photos-strip">
                            ${outfit.images.map((img, iIdx) => {
                                const isCover = outfit.cover_image === img.filename || (!outfit.cover_image && iIdx === 0);
                                return `
                                    <div class="cluster-photo-item ${isCover ? 'is-cover' : ''}" onclick="setCoverPhoto(${cIdx}, '${img.filename}')" title="Click to set as Cover">
                                        <img src="${img.url}" alt="${img.filename}" class="cluster-photo-img" onerror="this.onerror=null; this.src='assets/images/placeholder.svg';">
                                        ${isCover ? `<span class="photo-cover-badge"><i class="fa-solid fa-star"></i> Cover</span>` : ''}
                                        <span class="photo-angle-tag">${img.angle_type || 'Angle'}</span>
                                        <button type="button" class="photo-action-btn" onclick="event.stopPropagation(); removePhotoFromCluster(${cIdx}, ${iIdx});" title="Remove Photo">&times;</button>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function updateOutfitField(clusterIdx, field, val) {
    if (currentClusters[clusterIdx]) {
        currentClusters[clusterIdx][field] = val;
    }
}

function setCoverPhoto(clusterIdx, filename) {
    if (currentClusters[clusterIdx]) {
        currentClusters[clusterIdx].cover_image = filename;
        renderStudio();
    }
}

function removePhotoFromCluster(clusterIdx, imgIdx) {
    if (currentClusters[clusterIdx]) {
        currentClusters[clusterIdx].images.splice(imgIdx, 1);
        if (!currentClusters[clusterIdx].images.length) {
            currentClusters.splice(clusterIdx, 1);
        }
        renderStudio();
    }
}

function cancelToCategories() {
    document.getElementById('step1Section').style.display = 'block';
    document.getElementById('step2Processing').style.display = 'none';
    document.getElementById('step3Studio').style.display = 'none';

    document.getElementById('badgeStep1').classList.add('active');
    document.getElementById('badgeStep2').classList.remove('active');
    document.getElementById('badgeStep3').classList.remove('active');

    loadCategories();
}

async function commitAndSaveOutfits() {
    if (!currentClusters || !currentClusters.length) {
        alert('No outfits to save.');
        return;
    }

    const btn = document.getElementById('btnSaveOutfits');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Organizing Folders &amp; Saving to Database...`;

    const createSubfolders = document.getElementById('chkCreateSubfolders').checked;
    const fileAction = document.querySelector('input[name="fileActionRadio"]:checked')?.value || 'move';

    try {
        const res = await fetch('api/ai_collection_sorter.php?action=commit_outfits', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                folder: selectedCategory.folder_name,
                outfits: currentClusters,
                create_subfolders: createSubfolders,
                file_action: fileAction,
                save_db: true
            })
        });

        const data = await res.json();

        if (data.success) {
            alert(`🎉 Success!\n\n${data.message}\n\nYou can now view and edit the newly organized outfits in the Collections & Lookbook Manager.`);
            window.location.href = 'collections.php?cat=' + encodeURIComponent(selectedCategory.category_name);
        } else {
            alert('Error saving outfits: ' + (data.error || 'Unknown error'));
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    } catch (err) {
        alert('Network error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
