<?php
// admin/pdf-maker.php
$page_title = "PDF Maker";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap pdf-maker-container">
    <!-- Header Section -->
    <div class="pdf-maker-header">
        <div class="header-left">
            <div class="header-icon-box">
                <i class="fa-solid fa-file-pdf"></i>
            </div>
            <div>
                <h1 class="wp-heading-inline">Product PDF Maker &amp; Lookbook Creator</h1>
                <p class="header-sub">Filter luxury outfits and jewellery, select items across multiple categories, customize images, and generate client catalogues.</p>
            </div>
        </div>
        <div class="header-right">
            <div class="selection-pill" id="header_selection_pill">
                <i class="fa-solid fa-layer-group"></i> 
                <span id="header_selected_count">0</span> Products in Tray
            </div>
        </div>
    </div>

    <!-- Stepper Navigation -->
    <div class="pdf-stepper">
        <div class="step-item active" id="step_tab_1" onclick="switchStep(1)">
            <div class="step-number">1</div>
            <div class="step-info">
                <div class="step-title">Filter &amp; Select</div>
                <div class="step-desc">Pick Outfits &amp; Jewellery</div>
            </div>
        </div>
        <div class="step-divider"></div>
        <div class="step-item" id="step_tab_2" onclick="switchStep(2)">
            <div class="step-number">2</div>
            <div class="step-info">
                <div class="step-title">Review &amp; Select Images</div>
                <div class="step-desc"><span id="step2_item_count">0</span> items configured</div>
            </div>
        </div>
        <div class="step-divider"></div>
        <div class="step-item" id="step_tab_3" onclick="switchStep(3)">
            <div class="step-number">3</div>
            <div class="step-info">
                <div class="step-title">PDF Design &amp; Export</div>
                <div class="step-desc">Layout, branding &amp; download</div>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         STEP 1: FILTER & SELECT PRODUCTS
         =================================================================== -->
    <div class="step-content-pane" id="step_pane_1">
        
        <!-- Top Department Filter Switcher -->
        <div class="department-pills-bar">
            <button type="button" class="dept-pill active" data-dept="all" onclick="setDepartment('all')">
                <i class="fa-solid fa-boxes-stacked"></i> All Collections
            </button>
            <button type="button" class="dept-pill" data-dept="outfit" onclick="setDepartment('outfit')">
                <i class="fa-solid fa-shirt"></i> Outfits &amp; Couture
            </button>
            <button type="button" class="dept-pill" data-dept="jewellery" onclick="setDepartment('jewellery')">
                <i class="fa-solid fa-gem"></i> Jewellery &amp; Bridal Sets
            </button>
        </div>

        <!-- Filter Control Bar -->
        <div class="filter-card">
            <div class="filter-grid">
                <!-- Search Input -->
                <div class="filter-col search-col">
                    <label><i class="fa-solid fa-magnifying-glass"></i> Search Products</label>
                    <div class="search-input-wrap">
                        <input type="text" id="filter_search" class="form-control" placeholder="Search by SKU, Name or keyword..." oninput="onSearchInput(this.value)">
                        <button type="button" id="search_clear_btn" class="clear-search-btn" style="display:none;" onclick="clearSearch()">&times;</button>
                    </div>
                </div>

                <!-- Category Dropdown -->
                <div class="filter-col">
                    <label><i class="fa-solid fa-folder-tree"></i> Subcategory</label>
                    <select id="filter_category" class="form-control" onchange="onCategoryChange(this.value)">
                        <option value="">All Categories in Department</option>
                    </select>
                </div>

                <!-- Price Range Inputs -->
                <div class="filter-col price-col">
                    <label><i class="fa-solid fa-indian-rupee-sign"></i> Price Range (₹)</label>
                    <div class="price-input-group">
                        <input type="number" id="filter_min_price" class="form-control price-input" placeholder="Min" min="0" step="500" onchange="onPriceChange()">
                        <span class="price-dash">&ndash;</span>
                        <input type="number" id="filter_max_price" class="form-control price-input" placeholder="Max" min="0" step="500" onchange="onPriceChange()">
                        <button type="button" class="btn-filter-apply" onclick="applyFilters()" title="Apply Price Filter">
                            <i class="fa-solid fa-check"></i>
                        </button>
                    </div>
                </div>

                <!-- Sort Dropdown -->
                <div class="filter-col sort-col">
                    <label><i class="fa-solid fa-arrow-down-short-wide"></i> Sort By</label>
                    <select id="filter_sort" class="form-control" onchange="onSortChange(this.value)">
                        <option value="newest">Newest First</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="sku_asc">SKU (A to Z)</option>
                        <option value="name_asc">Name (A to Z)</option>
                    </select>
                </div>
            </div>

            <!-- Quick Price Preset Pills -->
            <div class="price-presets-row">
                <span class="presets-label">Quick Price:</span>
                <button type="button" class="preset-pill" onclick="setPricePreset(0, 3000)">Under ₹3,000</button>
                <button type="button" class="preset-pill" onclick="setPricePreset(3000, 7000)">₹3,000 - ₹7,000</button>
                <button type="button" class="preset-pill" onclick="setPricePreset(7000, 15000)">₹7,000 - ₹15,000</button>
                <button type="button" class="preset-pill" onclick="setPricePreset(15000, 35000)">₹15,000 - ₹35,000</button>
                <button type="button" class="preset-pill" onclick="setPricePreset(35000, 0)">₹35,000+</button>
                <button type="button" class="preset-pill reset-pill" onclick="resetFilters()">
                    <i class="fa-solid fa-rotate-left"></i> Reset Filters
                </button>
            </div>
        </div>

        <!-- Sticky Floating Selection Tray Bar -->
        <div class="selection-action-bar" id="selection_action_bar">
            <div class="selection-summary">
                <div class="selection-count-badge">
                    <i class="fa-solid fa-circle-check"></i>
                    <strong id="bar_selected_count">0</strong> Selected
                </div>
                <div class="selection-tags-wrap" id="selection_tags_wrap">
                    <!-- Selected item chips rendered here -->
                </div>
            </div>
            <div class="selection-buttons">
                <button type="button" class="btn-secondary-custom" onclick="toggleSelectAllFiltered()">
                    <i class="fa-solid fa-check-double"></i> Select All on Page
                </button>
                <button type="button" class="btn-secondary-custom btn-danger-custom" onclick="clearAllSelections()">
                    <i class="fa-solid fa-trash-can"></i> Clear All
                </button>
                <button type="button" class="btn-gold-action" onclick="goToStep2()">
                    Proceed to Review (<span id="btn_selected_count">0</span>) <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Results Info & Loading State -->
        <div class="results-meta-bar">
            <div id="results_count_label">Loading products...</div>
            <div class="view-toggle">
                <span id="active_filter_summary" class="active-filter-badge"></span>
            </div>
        </div>

        <div id="products_loading" class="loading-state-box" style="display:none;">
            <div class="spinner-gold"></div>
            <p>Fetching products from catalog...</p>
        </div>

        <!-- Product Cards Grid -->
        <div class="products-grid" id="products_grid">
            <!-- Rendered by JavaScript -->
        </div>

        <!-- Pagination Bar -->
        <div class="pagination-bar" id="pagination_bar" style="display:none;">
            <button type="button" class="page-nav-btn" id="prev_page_btn" onclick="changePage(-1)">
                <i class="fa-solid fa-chevron-left"></i> Previous
            </button>
            <div class="page-numbers" id="page_numbers"></div>
            <button type="button" class="page-nav-btn" id="next_page_btn" onclick="changePage(1)">
                Next <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <!-- ===================================================================
         STEP 2: REVIEW SELECTED PRODUCTS & SELECT IMAGES
         =================================================================== -->
    <div class="step-content-pane" id="step_pane_2" style="display:none;">
        <div class="step-top-nav">
            <div>
                <h2>Review Selected Products &amp; Images</h2>
                <p class="section-desc">Choose which photos to include for each product in your catalogue. You can select single or multiple looks per item.</p>
            </div>
            <div class="nav-btn-group">
                <button type="button" class="btn-secondary-custom" onclick="switchStep(1)">
                    <i class="fa-solid fa-arrow-left"></i> Back to Filters
                </button>
                <button type="button" class="btn-gold-action" onclick="goToStep3()">
                    Continue to PDF Design <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <div id="step2_loading" class="loading-state-box" style="display:none;">
            <div class="spinner-gold"></div>
            <p>Loading full gallery images for selected products...</p>
        </div>

        <div id="step2_empty_state" class="empty-state-box" style="display:none;">
            <i class="fa-solid fa-box-open empty-icon"></i>
            <h3>No products selected yet</h3>
            <p>Please return to Step 1 and select products to add to your PDF catalogue.</p>
            <button type="button" class="btn-gold-action" onclick="switchStep(1)">
                <i class="fa-solid fa-plus"></i> Select Products
            </button>
        </div>

        <!-- Review Product Cards List -->
        <div class="review-products-list" id="review_products_list">
            <!-- Rendered by JavaScript -->
        </div>

        <div class="step-bottom-nav">
            <button type="button" class="btn-secondary-custom" onclick="switchStep(1)">
                <i class="fa-solid fa-arrow-left"></i> Back to Filters
            </button>
            <button type="button" class="btn-gold-action" onclick="goToStep3()">
                Continue to PDF Design &amp; Export <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </div>

    <!-- ===================================================================
         STEP 3: PDF DESIGN & EXPORT
         =================================================================== -->
    <div class="step-content-pane" id="step_pane_3" style="display:none;">
        <div class="step-top-nav">
            <div>
                <h2>Customise PDF Design &amp; Export</h2>
                <p class="section-desc">Set client names, choose layout styles, toggle product fields, and generate your high-resolution PDF catalogue.</p>
            </div>
            <div class="nav-btn-group">
                <button type="button" class="btn-secondary-custom" onclick="switchStep(2)">
                    <i class="fa-solid fa-arrow-left"></i> Back to Image Selection
                </button>
            </div>
        </div>

        <div class="export-layout-grid">
            <!-- Left: Settings Form -->
            <div class="export-settings-card">
                <h3 class="card-section-title"><i class="fa-solid fa-sliders"></i> Catalogue &amp; Branding Settings</h3>

                <div class="form-group-custom">
                    <label>Catalogue Title</label>
                    <input type="text" id="pdf_catalogue_title" class="form-control" value="Exclusive Bridal &amp; Festive Collection" placeholder="e.g. Exclusive Bridal & Festive Collection">
                </div>

                <div class="form-group-custom">
                    <label>Prepared For / Client Name <span class="optional-tag">(Optional)</span></label>
                    <input type="text" id="pdf_client_name" class="form-control" placeholder="e.g. Mrs. Priya Sharma / Luxury Lookbook">
                </div>

                <!-- Layout Style Selector -->
                <div class="form-group-custom">
                    <label>Catalogue Layout Style</label>
                    <div class="layout-selector-grid">
                        <label class="layout-option-card active" onclick="selectLayout('showcase')">
                            <input type="radio" name="pdf_layout_choice" value="showcase" checked>
                            <div class="layout-card-inner">
                                <div class="layout-icon"><i class="fa-solid fa-table-list"></i></div>
                                <div class="layout-label">Showcase</div>
                                <div class="layout-sub">2 Products per page &bull; Balanced photos &amp; details</div>
                            </div>
                        </label>

                        <label class="layout-option-card" onclick="selectLayout('hero')">
                            <input type="radio" name="pdf_layout_choice" value="hero">
                            <div class="layout-card-inner">
                                <div class="layout-icon"><i class="fa-solid fa-image"></i></div>
                                <div class="layout-label">Luxury Lookbook</div>
                                <div class="layout-sub">1 Product per page &bull; Hero photo &amp; gallery collage</div>
                            </div>
                        </label>

                        <label class="layout-option-card" onclick="selectLayout('grid')">
                            <input type="radio" name="pdf_layout_choice" value="grid">
                            <div class="layout-card-inner">
                                <div class="layout-icon"><i class="fa-solid fa-table-cells-large"></i></div>
                                <div class="layout-label">Grid Catalogue</div>
                                <div class="layout-sub">4 Products per page &bull; Compact 2x2 line overview</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Field Toggles -->
                <div class="form-group-custom">
                    <label>Product Information to Display</label>
                    <div class="field-toggles-grid">
                        <label class="toggle-checkbox-label">
                            <input type="checkbox" id="toggle_show_price" checked>
                            <span><i class="fa-solid fa-tag"></i> Show Price &amp; Sale Price</span>
                        </label>
                        <label class="toggle-checkbox-label">
                            <input type="checkbox" id="toggle_show_sku" checked>
                            <span><i class="fa-solid fa-barcode"></i> Show Product SKU</span>
                        </label>
                        <label class="toggle-checkbox-label">
                            <input type="checkbox" id="toggle_show_category" checked>
                            <span><i class="fa-solid fa-folder"></i> Show Category Badge</span>
                        </label>
                        <label class="toggle-checkbox-label">
                            <input type="checkbox" id="toggle_show_description" checked>
                            <span><i class="fa-solid fa-align-left"></i> Show Description / Notes</span>
                        </label>
                    </div>
                </div>

                <!-- Custom Studio Notes / T&C -->
                <div class="form-group-custom">
                    <label>Studio Notes / WhatsApp Order Guidelines <span class="optional-tag">(Optional)</span></label>
                    <textarea id="pdf_custom_notes" class="form-control" rows="3" placeholder="e.g. All designs are handcrafted. Custom blouse stitching & jewellery trial appointments available at our studio. To order, WhatsApp +91 98204 77798 with Product SKUs.">All designs are handcrafted. Custom styling & trial appointments available at our Mumbai studio.
To place an order or check custom sizing, WhatsApp +91 98204 77798 with the Product SKU.</textarea>
                </div>
            </div>

            <!-- Right: Summary & Action Buttons -->
            <div class="export-action-card">
                <h3 class="card-section-title"><i class="fa-solid fa-file-export"></i> Summary &amp; Export</h3>
                
                <div class="export-stat-box">
                    <div class="stat-row">
                        <span>Selected Products:</span>
                        <strong id="final_product_count">0</strong>
                    </div>
                    <div class="stat-row">
                        <span>Total Images Included:</span>
                        <strong id="final_image_count">0</strong>
                    </div>
                    <div class="stat-row">
                        <span>Estimated Pages:</span>
                        <strong id="final_page_est">1</strong>
                    </div>
                    <div class="stat-row">
                        <span>Branding &amp; Logo:</span>
                        <span class="badge-active">Included</span>
                    </div>
                </div>

                <div class="export-buttons-stack">
                    <button type="button" class="btn-gold-action btn-large" onclick="generatePdf('pdf')">
                        <i class="fa-solid fa-download"></i> Generate &amp; Download PDF
                    </button>
                    <button type="button" class="btn-secondary-custom btn-large" onclick="generatePdf('preview')">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Preview PDF in New Tab
                    </button>
                    <button type="button" class="btn-secondary-custom btn-large" onclick="generatePdf('html')">
                        <i class="fa-solid fa-print"></i> Printable HTML View
                    </button>
                </div>

                <div class="export-note">
                    <i class="fa-solid fa-shield-halved"></i> 
                    High-resolution vector PDF powered by Dompdf. Ready to share on WhatsApp or Email.
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Hidden Form for Submitting PDF Generation Request -->
<form id="pdf_export_form" action="generate-catalogue-pdf.php" method="POST" target="_blank" style="display:none;">
    <input type="hidden" name="pdf_payload" id="pdf_payload_input">
</form>

<style>
/* ==========================================================================
   MODERN LUXURY PDF MAKER STYLING
   ========================================================================== */
.pdf-maker-container {
    max-width: 1400px;
    margin: 20px auto;
    padding: 0 15px;
    font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #1e293b;
}

/* Header */
.pdf-maker-header {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    color: #ffffff;
    padding: 24px 28px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);
    margin-bottom: 24px;
}
.header-left {
    display: flex;
    align-items: center;
    gap: 18px;
}
.header-icon-box {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, #c8a55c 0%, #e9d5a1 100%);
    color: #1e293b;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 4px 10px rgba(200, 165, 92, 0.3);
}
.pdf-maker-header h1 {
    font-size: 22px;
    font-weight: 700;
    margin: 0;
    color: #ffffff;
    line-height: 1.2;
}
.header-sub {
    font-size: 13px;
    color: #94a3b8;
    margin-top: 4px;
    margin-bottom: 0;
}
.selection-pill {
    background: rgba(200, 165, 92, 0.18);
    border: 1px solid #c8a55c;
    color: #e9d5a1;
    padding: 8px 16px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 13.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Stepper */
.pdf-stepper {
    display: flex;
    align-items: center;
    background: #ffffff;
    padding: 14px 24px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.step-item {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 6px 14px;
    border-radius: 8px;
    transition: all 0.2s;
    user-select: none;
}
.step-item:hover {
    background: #f8fafc;
}
.step-item.active {
    background: #fdfaf3;
}
.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #64748b;
    font-weight: 700;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.step-item.active .step-number {
    background: #c8a55c;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(200, 165, 92, 0.4);
}
.step-item.completed .step-number {
    background: #10b981;
    color: #ffffff;
}
.step-title {
    font-size: 14px;
    font-weight: 600;
    color: #334155;
}
.step-item.active .step-title {
    color: #1e293b;
    font-weight: 700;
}
.step-desc {
    font-size: 11.5px;
    color: #94a3b8;
}
.step-divider {
    flex: 1;
    height: 2px;
    background: #e2e8f0;
    margin: 0 16px;
}

/* Department Pills */
.department-pills-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 18px;
}
.dept-pill {
    padding: 10px 22px;
    border-radius: 30px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dept-pill:hover {
    border-color: #c8a55c;
    color: #a88438;
}
.dept-pill.active {
    background: #1e293b;
    border-color: #1e293b;
    color: #f8fafc;
    box-shadow: 0 2px 8px rgba(30, 41, 59, 0.2);
}
.dept-pill.active i {
    color: #c8a55c;
}

/* Filter Card */
.filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1.8fr 1.2fr;
    gap: 16px;
    align-items: flex-end;
}
@media (max-width: 992px) {
    .filter-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
    .filter-grid { grid-template-columns: 1fr; }
}
.filter-col label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.search-input-wrap {
    position: relative;
}
.clear-search-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 18px;
    color: #94a3b8;
    cursor: pointer;
}
.price-input-group {
    display: flex;
    align-items: center;
    gap: 6px;
}
.price-input {
    width: 90px;
}
.price-dash {
    color: #94a3b8;
    font-weight: bold;
}
.btn-filter-apply {
    background: #c8a55c;
    color: #ffffff;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.btn-filter-apply:hover {
    background: #a88438;
}
.price-presets-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
}
.presets-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}
.preset-pill {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-size: 11.5px;
    font-weight: 500;
    padding: 3px 10px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.2s;
}
.preset-pill:hover, .preset-pill.active {
    background: #fdfaf3;
    border-color: #c8a55c;
    color: #a88438;
    font-weight: 600;
}
.reset-pill {
    margin-left: auto;
    background: #fff;
    color: #ef4444;
    border-color: #fecaca;
}
.reset-pill:hover {
    background: #fef2f2;
    border-color: #ef4444;
    color: #dc2626;
}

/* Sticky Action Bar */
.selection-action-bar {
    position: sticky;
    top: 45px;
    z-index: 100;
    background: #1e293b;
    color: #ffffff;
    padding: 12px 20px;
    border-radius: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.18);
}
.selection-summary {
    display: flex;
    align-items: center;
    gap: 14px;
    flex: 1;
    overflow: hidden;
}
.selection-count-badge {
    background: #c8a55c;
    color: #1e293b;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 13px;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
}
.selection-tags-wrap {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    white-space: nowrap;
    max-width: 500px;
    padding-bottom: 2px;
}
.sel-chip {
    background: rgba(255,255,255,0.12);
    color: #f8fafc;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.sel-chip-remove {
    color: #cbd5e1;
    cursor: pointer;
    font-weight: bold;
}
.sel-chip-remove:hover {
    color: #ef4444;
}
.selection-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
    white-space: nowrap;
}

/* Custom Buttons */
.btn-gold-action {
    background: linear-gradient(135deg, #c8a55c 0%, #b38e44 100%);
    color: #ffffff;
    border: none;
    padding: 8px 18px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 13.5px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 2px 6px rgba(200, 165, 92, 0.35);
}
.btn-gold-action:hover {
    background: linear-gradient(135deg, #b38e44 0%, #9c7833 100%);
    box-shadow: 0 4px 12px rgba(200, 165, 92, 0.45);
}
.btn-secondary-custom {
    background: rgba(255,255,255,0.1);
    color: #f8fafc;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 14px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-secondary-custom:hover {
    background: rgba(255,255,255,0.18);
    color: #ffffff;
}
.btn-danger-custom {
    color: #fca5a5;
    border-color: rgba(239, 68, 68, 0.4);
}
.btn-danger-custom:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #ffffff;
}
.step-content-pane .btn-secondary-custom {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}
.step-content-pane .btn-secondary-custom:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* Results Bar */
.results-meta-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #64748b;
    margin-bottom: 14px;
}
.active-filter-badge {
    color: #a88438;
    font-weight: 500;
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.product-card {
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    position: relative;
}
.product-card:hover {
    border-color: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}
.product-card.selected {
    border-color: #c8a55c;
    background: #fffdf9;
    box-shadow: 0 4px 14px rgba(200, 165, 92, 0.25);
}
.product-card-img-wrap {
    height: 190px;
    background: #fafafa;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.product-card-img {
    max-height: 100%;
    max-width: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}
.product-card:hover .product-card-img {
    transform: scale(1.04);
}
.card-select-checkbox {
    position: absolute;
    top: 10px;
    left: 10px;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: rgba(255,255,255,0.9);
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: transparent;
    font-size: 13px;
    transition: all 0.2s;
}
.product-card.selected .card-select-checkbox {
    background: #c8a55c;
    border-color: #c8a55c;
    color: #ffffff;
}
.gallery-count-badge {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(15, 23, 42, 0.75);
    color: #ffffff;
    font-size: 10.5px;
    padding: 2px 7px;
    border-radius: 12px;
    backdrop-filter: blur(4px);
}
.product-card-body {
    padding: 12px;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.card-cat-badge {
    font-size: 10.5px;
    color: #a88438;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 2px;
}
.card-prod-title {
    font-size: 12.5px;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.35;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    height: 34px;
}
.card-prod-sku {
    font-size: 11px;
    color: #64748b;
    margin-bottom: 6px;
}
.card-prod-price {
    font-size: 14px;
    font-weight: 700;
    color: #c8a55c;
    margin-top: auto;
}
.card-prod-price .strike {
    font-size: 11.5px;
    color: #94a3b8;
    text-decoration: line-through;
    font-weight: normal;
    margin-left: 4px;
}

/* Pagination */
.pagination-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin: 20px 0 40px 0;
}
.page-nav-btn {
    padding: 6px 14px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
}
.page-nav-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.page-num {
    width: 34px;
    height: 34px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.page-num.active {
    background: #c8a55c;
    border-color: #c8a55c;
    color: #ffffff;
}

/* Loading & Empty States */
.loading-state-box, .empty-state-box {
    text-align: center;
    padding: 60px 20px;
    background: #ffffff;
    border-radius: 10px;
    border: 1px dashed #cbd5e1;
    margin-bottom: 24px;
}
.spinner-gold {
    width: 42px;
    height: 42px;
    border: 4px solid #f1f5f9;
    border-top-color: #c8a55c;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px auto;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.empty-icon {
    font-size: 48px;
    color: #cbd5e1;
    margin-bottom: 12px;
}

/* STEP 2: Review List */
.step-top-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.step-top-nav h2 {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    color: #1e293b;
}
.section-desc {
    font-size: 13px;
    color: #64748b;
    margin: 4px 0 0 0;
}
.nav-btn-group {
    display: flex;
    gap: 10px;
}
.review-item-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.review-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 12px;
    margin-bottom: 14px;
}
.review-header-info {
    display: flex;
    gap: 14px;
    align-items: center;
}
.review-item-thumb {
    width: 54px;
    height: 54px;
    border-radius: 6px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
}
.review-item-title-input {
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
    border: 1px solid transparent;
    padding: 4px 8px;
    border-radius: 4px;
    width: 360px;
    background: #f8fafc;
}
.review-item-title-input:focus {
    background: #fff;
    border-color: #c8a55c;
    outline: none;
}
.review-item-meta {
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}
.review-item-meta strong {
    color: #c8a55c;
}
.review-images-grid {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.image-pick-card {
    position: relative;
    width: 105px;
    height: 125px;
    border-radius: 6px;
    border: 2px solid #e2e8f0;
    background: #fafafa;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.image-pick-card:hover {
    border-color: #cbd5e1;
}
.image-pick-card.active {
    border-color: #c8a55c;
    box-shadow: 0 2px 8px rgba(200, 165, 92, 0.3);
}
.image-pick-card img {
    max-width: 100%;
    max-height: 100%;
    object-fit: cover;
}
.img-pick-checkbox {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 20px;
    height: 20px;
    border-radius: 4px;
    background: rgba(255,255,255,0.9);
    border: 1.5px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: transparent;
    font-size: 11px;
}
.image-pick-card.active .img-pick-checkbox {
    background: #c8a55c;
    border-color: #c8a55c;
    color: #ffffff;
}
.primary-tag {
    position: absolute;
    bottom: 4px;
    left: 4px;
    background: rgba(15, 23, 42, 0.75);
    color: #e9d5a1;
    font-size: 9px;
    padding: 1px 5px;
    border-radius: 3px;
}
.step-bottom-nav {
    display: flex;
    justify-content: space-between;
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid #e2e8f0;
}

/* STEP 3: Export Grid */
.export-layout-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}
@media (max-width: 900px) {
    .export-layout-grid { grid-template-columns: 1fr; }
}
.export-settings-card, .export-action-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 22px 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.card-section-title {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 18px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.card-section-title i {
    color: #c8a55c;
}
.form-group-custom {
    margin-bottom: 20px;
}
.form-group-custom label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}
.optional-tag {
    font-size: 11px;
    color: #94a3b8;
    font-weight: normal;
}
.layout-selector-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
@media (max-width: 768px) {
    .layout-selector-grid { grid-template-columns: 1fr; }
}
.layout-option-card {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 12px;
    cursor: pointer;
    transition: all 0.2s;
    background: #ffffff;
    display: block;
    position: relative;
}
.layout-option-card input {
    position: absolute;
    opacity: 0;
}
.layout-option-card.active {
    border-color: #c8a55c;
    background: #fffdf9;
    box-shadow: 0 2px 8px rgba(200, 165, 92, 0.2);
}
.layout-icon {
    font-size: 24px;
    color: #64748b;
    margin-bottom: 6px;
}
.layout-option-card.active .layout-icon {
    color: #c8a55c;
}
.layout-label {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 3px;
}
.layout-sub {
    font-size: 11px;
    color: #64748b;
    line-height: 1.3;
}
.field-toggles-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.toggle-checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 500;
    cursor: pointer;
}
.toggle-checkbox-label input {
    accent-color: #c8a55c;
}
.export-stat-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
}
.stat-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 13px;
    color: #475569;
    border-bottom: 1px solid #f1f5f9;
}
.stat-row:last-child {
    border-bottom: none;
}
.stat-row strong {
    color: #0f172a;
}
.badge-active {
    background: #dcfce7;
    color: #15803d;
    font-size: 11px;
    padding: 1px 8px;
    border-radius: 12px;
    font-weight: 600;
}
.export-buttons-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.btn-large {
    width: 100%;
    justify-content: center;
    padding: 12px 20px;
    font-size: 14px;
}
.export-note {
    font-size: 11.5px;
    color: #64748b;
    text-align: center;
    margin-top: 14px;
    line-height: 1.4;
}
</style>

<script>
// =============================================================================
// PDF MAKER CLIENT-SIDE APPLICATION LOGIC
// =============================================================================

// Universal SVG Placeholder to completely prevent infinite error loops
const DEFAULT_PLACEHOLDER_IMG = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='300' height='360' viewBox='0 0 300 360'><rect fill='%23f8fafc' width='300' height='360'/><rect x='20' y='20' width='260' height='320' rx='8' fill='%23f1f5f9' stroke='%23e2e8f0' stroke-width='2'/><circle cx='150' cy='150' r='36' fill='%23e2e8f0'/><path d='M138 160l8-10 12 15 8-6 10 13H124z' fill='%2394a3b8'/><text fill='%2364748b' font-family='sans-serif' font-size='13' font-weight='bold' x='50%' y='235' text-anchor='middle'>Photo Not Available</text></svg>";

// State Management
const state = {
    currentStep: 1,
    department: 'all',
    categoryId: '',
    search: '',
    minPrice: '',
    maxPrice: '',
    sort: 'newest',
    page: 1,
    limit: 24,
    categoriesTree: [],
    priceStats: { min_price: 0, max_price: 50000 },
    // Multi-Selection State (Persists across category / filter searches)
    selectedProductIds: new Set(),
    selectedProductsMap: new Map(), // id -> product summary
    fullProductDetails: [], // List for Step 2 & 3
    currentProductsList: []  // Current query results
};

let searchDebounceTimer = null;

// Format image URL with live server base
function formatImageUrl(path) {
    if (!path) return DEFAULT_PLACEHOLDER_IMG;
    let clean = path.toString().trim();
    if (clean.includes('localhost')) {
        clean = clean.replace(/^https?:\/\/localhost(:[0-9]+)?(\/yn)?\/admin\/?/i, '');
        clean = clean.replace(/^https?:\/\/localhost(:[0-9]+)?\/?/i, '');
    }
    if (clean.startsWith('https://yosshitaneha.com')) return clean;
    if (clean.startsWith('http://') || clean.startsWith('https://')) return clean;
    
    clean = clean.replace(/^\/+/, '');
    if (clean.startsWith('admin/')) {
        return 'https://yosshitaneha.com/' + clean;
    }
    return 'https://yosshitaneha.com/admin/' + clean;
}

// Initialize on Load
document.addEventListener('DOMContentLoaded', () => {
    loadMetadata();
    loadProducts();
});

// 1. Fetch Metadata (Categories Tree & Price Stats)
function loadMetadata() {
    fetch('api/pdf-maker-data.php?action=get_meta')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                state.categoriesTree = data.categories || [];
                state.priceStats = data.stats || { min_price: 0, max_price: 50000 };
                renderCategoryDropdown();
            }
        })
        .catch(err => console.error('Error fetching metadata:', err));
}

// 2. Render Category Dropdown filtered by Department
function renderCategoryDropdown() {
    const select = document.getElementById('filter_category');
    if (!select) return;

    let html = '<option value="">All Categories in Department</option>';

    // Filter categories relevant to current department
    let targetCats = state.categoriesTree;
    if (state.department === 'jewellery') {
        const jRoot = state.categoriesTree.find(c => c.slug === 'jewellery' || (c.name.toLowerCase().includes('jewellery') && !c.parent_id));
        const jRootId = jRoot ? jRoot.id : 1;
        targetCats = state.categoriesTree.filter(c => c.id == jRootId || c.parent_id == jRootId || (c.parent_id && state.categoriesTree.some(p => p.id == c.parent_id && (p.parent_id == jRootId || p.id == jRootId))));
    } else if (state.department === 'outfit') {
        const oRoot = state.categoriesTree.find(c => c.slug === 'outfit' || (c.name.toLowerCase().includes('outfit') && !c.parent_id));
        const oRootId = oRoot ? oRoot.id : 26;
        targetCats = state.categoriesTree.filter(c => c.id == oRootId || c.parent_id == oRootId || (c.parent_id && state.categoriesTree.some(p => p.id == c.parent_id && (p.parent_id == oRootId || p.id == oRootId))));
    }

    targetCats.forEach(cat => {
        const prefix = cat.parent_id ? '&nbsp;&nbsp;&bull; ' : '';
        const countText = cat.product_count > 0 ? ` (${cat.product_count})` : '';
        html += `<option value="${cat.id}">${prefix}${cat.name}${countText}</option>`;
    });

    select.innerHTML = html;
    select.value = state.categoryId;
}

// 3. Load Products from AJAX
function loadProducts() {
    const grid = document.getElementById('products_grid');
    const loading = document.getElementById('products_loading');
    const metaLabel = document.getElementById('results_count_label');
    const paginationBar = document.getElementById('pagination_bar');

    if (loading) loading.style.display = 'block';
    if (grid) grid.innerHTML = '';
    if (paginationBar) paginationBar.style.display = 'none';

    const params = new URLSearchParams({
        action: 'search_products',
        department: state.department,
        category_id: state.categoryId,
        search: state.search,
        min_price: state.minPrice,
        max_price: state.maxPrice,
        sort: state.sort,
        page: state.page,
        limit: state.limit
    });

    fetch(`api/pdf-maker-data.php?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            if (loading) loading.style.display = 'none';

            if (!data.success) {
                if (grid) grid.innerHTML = `<div class="empty-state-box"><p>${data.message || 'Error loading products.'}</p></div>`;
                return;
            }

            state.currentProductsList = data.products || [];
            const pagination = data.pagination;

            if (metaLabel) {
                metaLabel.innerHTML = `Showing <strong>${state.currentProductsList.length}</strong> of <strong>${pagination.total}</strong> products`;
            }

            renderProductsGrid();
            renderPagination(pagination);
        })
        .catch(err => {
            if (loading) loading.style.display = 'none';
            console.error('Error loading products:', err);
            if (grid) grid.innerHTML = `<div class="empty-state-box"><p>Failed to connect to product server.</p></div>`;
        });
}

// 4. Render Product Cards Grid
function renderProductsGrid() {
    const grid = document.getElementById('products_grid');
    if (!grid) return;

    if (state.currentProductsList.length === 0) {
        grid.innerHTML = `
            <div class="empty-state-box" style="grid-column: 1 / -1;">
                <i class="fa-solid fa-filter-circle-xmark empty-icon"></i>
                <h3>No products match your filters</h3>
                <p>Try resetting the price range, search query, or switching departments.</p>
                <button type="button" class="btn-gold-action" onclick="resetFilters()">Reset All Filters</button>
            </div>
        `;
        return;
    }

    let html = '';
    state.currentProductsList.forEach(prod => {
        const isSelected = state.selectedProductIds.has(prod.id);
        const selectedClass = isSelected ? 'selected' : '';
        const checkIcon = isSelected ? '<i class="fa-solid fa-check"></i>' : '';
        const galleryCount = prod.gallery_count > 0 ? `<div class="gallery-count-badge"><i class="fa-solid fa-images"></i> ${prod.gallery_count + 1}</div>` : '';
        const catBadge = prod.primary_category_name ? `<div class="card-cat-badge">${escapeHtml(prod.primary_category_name)}</div>` : '';
        const imgUrl = formatImageUrl(prod.display_image || prod.main_image);

        let priceHtml = `<div class="card-prod-price">${prod.effective_price_formatted}`;
        if (prod.sale_price && prod.sale_price < prod.price) {
            priceHtml += `<span class="strike">${prod.price_formatted}</span>`;
        }
        priceHtml += `</div>`;

        html += `
            <div class="product-card ${selectedClass}" id="prod_card_${prod.id}" onclick="toggleProductSelect(${prod.id})">
                <div class="product-card-img-wrap">
                    <img src="${imgUrl}" class="product-card-img" alt="${escapeHtml(prod.name)}" loading="lazy" onerror="this.onerror=null; this.src=DEFAULT_PLACEHOLDER_IMG;">
                    <div class="card-select-checkbox">${checkIcon}</div>
                    ${galleryCount}
                </div>
                <div class="product-card-body">
                    ${catBadge}
                    <div class="card-prod-title" title="${escapeHtml(prod.name)}">${escapeHtml(prod.name)}</div>
                    <div class="card-prod-sku"><i class="fa-solid fa-barcode"></i> ${escapeHtml(prod.sku)}</div>
                    ${priceHtml}
                </div>
            </div>
        `;
    });

    grid.innerHTML = html;
}

// 5. Toggle Single Product Selection
function toggleProductSelect(productId) {
    productId = parseInt(productId);
    const prod = state.currentProductsList.find(p => p.id === productId) || state.selectedProductsMap.get(productId);

    if (state.selectedProductIds.has(productId)) {
        state.selectedProductIds.delete(productId);
        state.selectedProductsMap.delete(productId);
    } else {
        if (prod) {
            state.selectedProductIds.add(productId);
            state.selectedProductsMap.set(productId, prod);
        }
    }

    updateSelectionUI();
    
    // Update card visual
    const card = document.getElementById(`prod_card_${productId}`);
    if (card) {
        if (state.selectedProductIds.has(productId)) {
            card.classList.add('selected');
            card.querySelector('.card-select-checkbox').innerHTML = '<i class="fa-solid fa-check"></i>';
        } else {
            card.classList.remove('selected');
            card.querySelector('.card-select-checkbox').innerHTML = '';
        }
    }
}

// 6. Select / Deselect All on current page
function toggleSelectAllFiltered() {
    const allSelectedOnPage = state.currentProductsList.every(p => state.selectedProductIds.has(p.id));

    if (allSelectedOnPage) {
        state.currentProductsList.forEach(p => {
            state.selectedProductIds.delete(p.id);
            state.selectedProductsMap.delete(p.id);
        });
    } else {
        state.currentProductsList.forEach(p => {
            state.selectedProductIds.add(p.id);
            state.selectedProductsMap.set(p.id, p);
        });
    }

    updateSelectionUI();
    renderProductsGrid();
}

// 7. Clear All Selected Items
function clearAllSelections() {
    if (state.selectedProductIds.size === 0) return;
    if (confirm('Are you sure you want to clear all selected products from your PDF tray?')) {
        state.selectedProductIds.clear();
        state.selectedProductsMap.clear();
        updateSelectionUI();
        renderProductsGrid();
    }
}

// 8. Update Selection Badges & Sticky Bar
function updateSelectionUI() {
    const count = state.selectedProductIds.size;

    document.getElementById('header_selected_count').textContent = count;
    document.getElementById('bar_selected_count').textContent = count;
    document.getElementById('btn_selected_count').textContent = count;
    document.getElementById('step2_item_count').textContent = count;

    // Render chips
    const chipWrap = document.getElementById('selection_tags_wrap');
    if (chipWrap) {
        let chipsHtml = '';
        let countShown = 0;
        state.selectedProductsMap.forEach((prod, id) => {
            if (countShown < 10) {
                chipsHtml += `
                    <span class="sel-chip">
                        ${escapeHtml(prod.sku || prod.name)}
                        <span class="sel-chip-remove" onclick="event.stopPropagation(); toggleProductSelect(${id})">&times;</span>
                    </span>
                `;
                countShown++;
            }
        });
        if (state.selectedProductsMap.size > 10) {
            chipsHtml += `<span class="sel-chip">+${state.selectedProductsMap.size - 10} more</span>`;
        }
        chipWrap.innerHTML = chipsHtml;
    }
}

// 9. Filter Action Handlers
function setDepartment(dept) {
    state.department = dept;
    state.categoryId = '';
    state.page = 1;

    document.querySelectorAll('.dept-pill').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.dept === dept);
    });

    renderCategoryDropdown();
    loadProducts();
}

function onCategoryChange(catId) {
    state.categoryId = catId;
    state.page = 1;
    loadProducts();
}

function onSearchInput(val) {
    const clearBtn = document.getElementById('search_clear_btn');
    if (clearBtn) clearBtn.style.display = val.trim().length > 0 ? 'block' : 'none';

    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        state.search = val.trim();
        state.page = 1;
        loadProducts();
    }, 350);
}

function clearSearch() {
    const input = document.getElementById('filter_search');
    if (input) input.value = '';
    onSearchInput('');
}

function onPriceChange() {
    state.minPrice = document.getElementById('filter_min_price').value;
    state.maxPrice = document.getElementById('filter_max_price').value;
}

function applyFilters() {
    onPriceChange();
    state.page = 1;
    loadProducts();
}

function setPricePreset(min, max) {
    document.getElementById('filter_min_price').value = min > 0 ? min : '';
    document.getElementById('filter_max_price').value = max > 0 ? max : '';
    state.minPrice = min > 0 ? min : '';
    state.maxPrice = max > 0 ? max : '';
    state.page = 1;
    loadProducts();
}

function onSortChange(sortVal) {
    state.sort = sortVal;
    state.page = 1;
    loadProducts();
}

function resetFilters() {
    state.department = 'all';
    state.categoryId = '';
    state.search = '';
    state.minPrice = '';
    state.maxPrice = '';
    state.sort = 'newest';
    state.page = 1;

    document.getElementById('filter_search').value = '';
    document.getElementById('filter_min_price').value = '';
    document.getElementById('filter_max_price').value = '';
    document.getElementById('filter_sort').value = 'newest';
    document.getElementById('search_clear_btn').style.display = 'none';

    document.querySelectorAll('.dept-pill').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.dept === 'all');
    });

    renderCategoryDropdown();
    loadProducts();
}

// 10. Pagination Render & Handlers
function renderPagination(pagination) {
    const bar = document.getElementById('pagination_bar');
    const numbers = document.getElementById('page_numbers');
    const prevBtn = document.getElementById('prev_page_btn');
    const nextBtn = document.getElementById('next_page_btn');

    if (!bar || !numbers || pagination.total_pages <= 1) {
        if (bar) bar.style.display = 'none';
        return;
    }

    bar.style.display = 'flex';
    prevBtn.disabled = pagination.page <= 1;
    nextBtn.disabled = pagination.page >= pagination.total_pages;

    let html = '';
    const total = pagination.total_pages;
    const cur = pagination.page;

    let start = Math.max(1, cur - 2);
    let end = Math.min(total, cur + 2);

    for (let p = start; p <= end; p++) {
        html += `<button type="button" class="page-num ${p === cur ? 'active' : ''}" onclick="goToPage(${p})">${p}</button>`;
    }

    numbers.innerHTML = html;
}

function changePage(delta) {
    state.page = Math.max(1, state.page + delta);
    loadProducts();
    window.scrollTo({ top: 200, behavior: 'smooth' });
}

function goToPage(p) {
    state.page = p;
    loadProducts();
    window.scrollTo({ top: 200, behavior: 'smooth' });
}

// =============================================================================
// STEP 2: REVIEW SELECTION & MULTI-IMAGE PICKER
// =============================================================================
function goToStep2() {
    if (state.selectedProductIds.size === 0) {
        alert('Please select at least one product before proceeding to review.');
        return;
    }
    switchStep(2);
}

function loadStep2Data() {
    const loading = document.getElementById('step2_loading');
    const emptyState = document.getElementById('step2_empty_state');
    const list = document.getElementById('review_products_list');

    if (state.selectedProductIds.size === 0) {
        if (emptyState) emptyState.style.display = 'block';
        if (list) list.innerHTML = '';
        return;
    }

    if (emptyState) emptyState.style.display = 'none';
    if (loading) loading.style.display = 'block';

    const ids = Array.from(state.selectedProductIds).join(',');

    fetch(`api/pdf-maker-data.php?action=get_product_images&product_ids=${encodeURIComponent(ids)}`)
        .then(res => res.json())
        .then(data => {
            if (loading) loading.style.display = 'none';
            if (data.success && data.products) {
                const existingMap = new Map(state.fullProductDetails.map(p => [p.id, p]));
                state.fullProductDetails = data.products.map(p => {
                    if (existingMap.has(p.id)) {
                        const ex = existingMap.get(p.id);
                        p.name = ex.name || p.name;
                        p.selected_images = ex.selected_images || p.selected_images;
                    }
                    return p;
                });

                renderReviewList();
            }
        })
        .catch(err => {
            if (loading) loading.style.display = 'none';
            console.error('Error fetching review images:', err);
        });
}

function renderReviewList() {
    const list = document.getElementById('review_products_list');
    if (!list) return;

    let html = '';
    state.fullProductDetails.forEach(prod => {
        const pId = prod.id;
        const selectedImgSet = new Set(prod.selected_images || []);
        const mainThumb = formatImageUrl(prod.main_image);

        let imagesHtml = '';
        (prod.all_images || []).forEach((img, idx) => {
            const formattedImgUrl = formatImageUrl(img.path);
            const isImgSelected = selectedImgSet.has(img.path) || selectedImgSet.has(formattedImgUrl);
            const activeClass = isImgSelected ? 'active' : '';
            const checkIcon = isImgSelected ? '<i class="fa-solid fa-check"></i>' : '';
            const primaryTag = idx === 0 ? '<span class="primary-tag">Primary</span>' : '';

            imagesHtml += `
                <div class="image-pick-card ${activeClass}" onclick="toggleProductImage(${pId}, '${escapeJsString(img.path)}')">
                    <img src="${formattedImgUrl}" alt="" onerror="this.onerror=null; this.src=DEFAULT_PLACEHOLDER_IMG;">
                    <div class="img-pick-checkbox">${checkIcon}</div>
                    ${primaryTag}
                </div>
            `;
        });

        html += `
            <div class="review-item-card" id="review_card_${pId}">
                <div class="review-item-header">
                    <div class="review-header-info">
                        <img src="${mainThumb}" class="review-item-thumb" alt="" onerror="this.onerror=null; this.src=DEFAULT_PLACEHOLDER_IMG;">
                        <div>
                            <input type="text" class="review-item-title-input" value="${escapeHtml(prod.name)}" onchange="updateProductCustomTitle(${pId}, this.value)" placeholder="Product title in PDF">
                            <div class="review-item-meta">
                                SKU: <strong>${escapeHtml(prod.sku)}</strong> &bull; 
                                Category: ${escapeHtml(prod.category_name || 'Jewellery & Outfits')} &bull; 
                                Price: <strong>${prod.effective_price_formatted}</strong>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; gap: 8px;">
                        <button type="button" class="btn-secondary-custom" style="padding: 4px 10px; font-size: 11.5px;" onclick="selectAllImagesForProduct(${pId})">
                            <i class="fa-solid fa-images"></i> Select All Images
                        </button>
                        <button type="button" class="btn-secondary-custom btn-danger-custom" style="padding: 4px 10px; font-size: 11.5px;" onclick="removeProductFromReview(${pId})">
                            <i class="fa-solid fa-trash"></i> Remove
                        </button>
                    </div>
                </div>

                <div style="margin-bottom: 8px; font-size: 12px; color: #475569; font-weight: 600;">
                    Select images to include in lookbook (<span id="img_count_badge_${pId}">${selectedImgSet.size}</span> selected):
                </div>

                <div class="review-images-grid">
                    ${imagesHtml}
                </div>
            </div>
        `;
    });

    list.innerHTML = html;
}

function toggleProductImage(productId, imgPath) {
    const prod = state.fullProductDetails.find(p => p.id === productId);
    if (!prod) return;

    let sel = prod.selected_images || [];
    const formatted = formatImageUrl(imgPath);
    
    let idx = sel.indexOf(imgPath);
    if (idx === -1) idx = sel.indexOf(formatted);

    if (idx !== -1) {
        if (sel.length === 1) {
            alert('Each product in the catalogue must have at least 1 selected image.');
            return;
        }
        sel.splice(idx, 1);
    } else {
        sel.push(imgPath);
    }
    prod.selected_images = sel;
    renderReviewList();
}

function selectAllImagesForProduct(productId) {
    const prod = state.fullProductDetails.find(p => p.id === productId);
    if (!prod) return;
    prod.selected_images = (prod.all_images || []).map(i => i.path);
    renderReviewList();
}

function updateProductCustomTitle(productId, newTitle) {
    const prod = state.fullProductDetails.find(p => p.id === productId);
    if (prod) {
        prod.name = newTitle.trim();
    }
}

function removeProductFromReview(productId) {
    state.selectedProductIds.delete(productId);
    state.selectedProductsMap.delete(productId);
    state.fullProductDetails = state.fullProductDetails.filter(p => p.id !== productId);
    updateSelectionUI();
    renderProductsGrid();
    renderReviewList();
}

// =============================================================================
// STEP 3: PDF DESIGN, PREVIEW & EXPORT
// =============================================================================
let currentLayout = 'showcase';

function goToStep3() {
    if (state.selectedProductIds.size === 0) {
        alert('Please select at least one product before proceeding.');
        return;
    }
    switchStep(3);
    updateStep3Summary();
}

function selectLayout(layoutKey) {
    currentLayout = layoutKey;
    document.querySelectorAll('.layout-option-card').forEach(card => {
        const input = card.querySelector('input');
        if (input && input.value === layoutKey) {
            card.classList.add('active');
            input.checked = true;
        } else {
            card.classList.remove('active');
        }
    });
    updateStep3Summary();
}

function updateStep3Summary() {
    const totalProds = state.fullProductDetails.length;
    let totalImgs = 0;
    state.fullProductDetails.forEach(p => {
        totalImgs += (p.selected_images || []).length;
    });

    let estPages = 1;
    if (currentLayout === 'hero') {
        estPages = totalProds;
    } else if (currentLayout === 'showcase') {
        estPages = Math.ceil(totalProds / 2);
    } else {
        estPages = Math.ceil(totalProds / 4);
    }

    document.getElementById('final_product_count').textContent = totalProds;
    document.getElementById('final_image_count').textContent = totalImgs;
    document.getElementById('final_page_est').textContent = estPages + ' Page' + (estPages > 1 ? 's' : '');
}

function generatePdf(outputMode = 'pdf') {
    if (state.fullProductDetails.length === 0) {
        alert('No products selected to export.');
        return;
    }

    const payload = {
        catalogue_title: document.getElementById('pdf_catalogue_title').value.trim() || 'Product Catalogue',
        client_name: document.getElementById('pdf_client_name').value.trim(),
        layout: currentLayout,
        show_price: document.getElementById('toggle_show_price').checked ? 1 : 0,
        show_sku: document.getElementById('toggle_show_sku').checked ? 1 : 0,
        show_category: document.getElementById('toggle_show_category').checked ? 1 : 0,
        show_description: document.getElementById('toggle_show_description').checked ? 1 : 0,
        custom_notes: document.getElementById('pdf_custom_notes').value.trim(),
        output_mode: outputMode,
        products: state.fullProductDetails
    };

    const form = document.getElementById('pdf_export_form');
    const input = document.getElementById('pdf_payload_input');
    input.value = JSON.stringify(payload);

    form.submit();
}

// Stepper Tab Switcher
function switchStep(stepNum) {
    state.currentStep = stepNum;

    // Update Tab Classes
    for (let i = 1; i <= 3; i++) {
        const tab = document.getElementById(`step_tab_${i}`);
        const pane = document.getElementById(`step_pane_${i}`);
        if (tab) {
            tab.classList.toggle('active', i === stepNum);
            tab.classList.toggle('completed', i < stepNum);
        }
        if (pane) {
            pane.style.display = (i === stepNum) ? 'block' : 'none';
        }
    }

    // Trigger step-specific loaders
    if (stepNum === 2) {
        loadStep2Data();
    } else if (stepNum === 3) {
        updateStep3Summary();
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Helper: Escape HTML & JS
function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function escapeJsString(str) {
    if (!str) return '';
    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
