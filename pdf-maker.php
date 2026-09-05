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
                <p class="header-sub">Filter luxury outfits and jewellery, select items across multiple categories, pick specific photo angles, and generate client catalogues.</p>
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
                <div class="step-title">Review &amp; Select Angles</div>
                <div class="step-desc"><span id="step2_item_count">0</span> items configured</div>
            </div>
        </div>
        <div class="step-divider"></div>
        <div class="step-item" id="step_tab_3" onclick="switchStep(3)">
            <div class="step-number">3</div>
            <div class="step-info">
                <div class="step-title">PDF Design &amp; Export</div>
                <div class="step-desc">Cover page, links &amp; download</div>
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
                <div class="selection-tags-wrap" id="selection_tags_wrap"></div>
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
        <div class="products-grid" id="products_grid"></div>

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
         STEP 2: REVIEW SELECTED PRODUCTS & SELECT IMAGES (ANGLES)
         =================================================================== -->
    <div class="step-content-pane" id="step_pane_2" style="display:none;">
        <div class="step-top-nav">
            <div>
                <h2>Review Products &amp; Select Image Angles</h2>
                <p class="section-desc">Select which photo angles (e.g. Angle 1, Angle 2) to include in the generated catalogue for each selected product.</p>
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
            <p>Loading full gallery image angles for selected products...</p>
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
        <div class="review-products-list" id="review_products_list"></div>

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
                <p class="section-desc">Configure cover page branding, product links, and layout options for YosshitaNeha Fashion Studio.</p>
            </div>
            <div class="nav-btn-group">
                <button type="button" class="btn-secondary-custom" onclick="switchStep(2)">
                    <i class="fa-solid fa-arrow-left"></i> Back to Angles Selection
                </button>
            </div>
        </div>

        <div class="export-layout-grid">
            <!-- Left: Settings Form -->
            <div class="export-settings-card">
                <h3 class="card-section-title"><i class="fa-solid fa-sliders"></i> Branding &amp; Cover Page</h3>

                <div class="form-group-custom">
                    <label class="toggle-checkbox-label" style="background:#f8fafc; font-size:13px; font-weight:600; margin-bottom:12px;">
                        <input type="checkbox" id="toggle_include_cover" checked onchange="updateStep3Summary()">
                        <span><i class="fa-solid fa-book-open"></i> Include Cover Page</span>
                    </label>
                </div>

                <div class="form-group-custom">
                    <label>Cover Page Brand Title</label>
                    <input type="text" id="pdf_brand_title" class="form-control" value="YosshitaNeha Fashion Studio">
                </div>

                <div class="form-group-custom">
                    <label>Cover Page Subtitle / Tagline</label>
                    <input type="text" id="pdf_brand_subtitle" class="form-control" value="The Ultimate Fashion Destination">
                </div>

                <div class="form-group-custom">
                    <label>Catalogue Title / Collection Name</label>
                    <input type="text" id="pdf_catalogue_title" class="form-control" value="Exclusive Collection Lookbook" placeholder="e.g. Exclusive Bridal & Festive Collection">
                </div>

                <div class="form-group-custom">
                    <label>Prepared For / Client Name <span class="optional-tag">(Optional)</span></label>
                    <input type="text" id="pdf_client_name" class="form-control" placeholder="e.g. Mrs. Priya Sharma">
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
                                <div class="layout-sub">2 Products per page &bull; Clean vertical lookbook with View link</div>
                            </div>
                        </label>

                        <label class="layout-option-card" onclick="selectLayout('hero')">
                            <input type="radio" name="pdf_layout_choice" value="hero">
                            <div class="layout-card-inner">
                                <div class="layout-icon"><i class="fa-solid fa-image"></i></div>
                                <div class="layout-label">Hero Lookbook</div>
                                <div class="layout-sub">1 Product per page &bull; Full-height hero photo &amp; gallery row</div>
                            </div>
                        </label>

                        <label class="layout-option-card" onclick="selectLayout('grid')">
                            <input type="radio" name="pdf_layout_choice" value="grid">
                            <div class="layout-card-inner">
                                <div class="layout-icon"><i class="fa-solid fa-table-cells-large"></i></div>
                                <div class="layout-label">Grid Catalogue</div>
                                <div class="layout-sub">4 Products per page &bull; Compact 2x2 grid overview</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Field Toggles -->
                <div class="form-group-custom">
                    <label>Product Information &amp; Links</label>
                    <div class="field-toggles-grid">
                        <label class="toggle-checkbox-label">
                            <input type="checkbox" id="toggle_show_product_link" checked>
                            <span><i class="fa-solid fa-link"></i> Clickable Product Link (View: SKU)</span>
                        </label>
                        <label class="toggle-checkbox-label">
                            <input type="checkbox" id="toggle_show_price" checked>
                            <span><i class="fa-solid fa-tag"></i> Show Price (₹)</span>
                        </label>
                        <label class="toggle-checkbox-label">
                            <input type="checkbox" id="toggle_show_sku" checked>
                            <span><i class="fa-solid fa-barcode"></i> Show Product SKU</span>
                        </label>
                        <label class="toggle-checkbox-label">
                            <input type="checkbox" id="toggle_show_category" checked>
                            <span><i class="fa-solid fa-folder"></i> Show Category Name</span>
                        </label>
                    </div>
                </div>

                <!-- Custom Studio Notes / T&C -->
                <div class="form-group-custom">
                    <label>Studio Notes / Contact Guidelines <span class="optional-tag">(Optional)</span></label>
                    <textarea id="pdf_custom_notes" class="form-control" rows="2" placeholder="e.g. For inquiries & orders, WhatsApp +91 98204 77798 with Product SKUs.">For inquiries, custom styling or bridal trial appointments, visit www.yosshitaneha.com or WhatsApp +91 98204 77798.</textarea>
                </div>
            </div>

            <!-- Right: Summary & Action Buttons -->
            <div class="export-action-card">
                <h3 class="card-section-title"><i class="fa-solid fa-file-export"></i> Summary &amp; Export</h3>
                
                <div class="export-stat-box">
                    <div class="stat-row">
                        <span>Brand Name:</span>
                        <strong>YosshitaNeha</strong>
                    </div>
                    <div class="stat-row">
                        <span>Selected Products:</span>
                        <strong id="final_product_count">0</strong>
                    </div>
                    <div class="stat-row">
                        <span>Total Angles Included:</span>
                        <strong id="final_image_count">0</strong>
                    </div>
                    <div class="stat-row">
                        <span>Estimated Pages:</span>
                        <strong id="final_page_est">1 Page</strong>
                    </div>
                    <div class="stat-row">
                        <span>Clickable Links:</span>
                        <span class="badge-active">Enabled</span>
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
                    High-resolution lookbook PDF with direct clickable links to yosshitaneha.com.
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
/* Modern ShadCN Light Styling */
.pdf-maker-container {
    max-width: 1400px;
    margin: 0 auto 24px auto;
    padding: 0 15px;
    font-family: inherit;
    color: #09090b;
}
.pdf-maker-header {
    background: #ffffff;
    color: #09090b;
    padding: 16px 20px;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid #e4e4e7;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    margin-bottom: 20px;
}
.header-left { display: flex; align-items: center; gap: 14px; }
.header-icon-box {
    width: 40px;
    height: 40px;
    background: rgba(239, 68, 68, 0.08);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.pdf-maker-header h1 { font-size: 18px; font-weight: 600; margin: 0; color: #09090b; letter-spacing: -0.02em; }
.header-sub { font-size: 12.5px; color: #71717a; margin: 3px 0 0 0; }
.selection-pill {
    background: #f4f4f5;
    border: 1px solid #e4e4e7;
    color: #09090b;
    padding: 6px 14px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 12.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pdf-stepper {
    display: flex;
    align-items: center;
    background: #ffffff;
    padding: 12px 20px;
    border-radius: 8px;
    border: 1px solid #e4e4e7;
    margin-bottom: 20px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.step-item {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 8px;
    transition: all 0.2s;
    user-select: none;
}
.step-item.active { background: #fdfaf3; }
.step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #64748b;
    font-weight: 700;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.step-item.active .step-number { background: #c8a55c; color: #ffffff; }
.step-item.completed .step-number { background: #10b981; color: #ffffff; }
.step-title { font-size: 13.5px; font-weight: 600; color: #334155; }
.step-item.active .step-title { color: #1e293b; font-weight: 700; }
.step-desc { font-size: 11px; color: #94a3b8; }
.step-divider { flex: 1; height: 2px; background: #e2e8f0; margin: 0 14px; }

.department-pills-bar { display: flex; gap: 10px; margin-bottom: 16px; }
.dept-pill {
    padding: 6px 14px;
    border-radius: 6px;
    border: 1px solid #e4e4e7;
    background: #ffffff;
    color: #71717a;
    font-size: 12.5px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    gap: 7px;
}
.dept-pill.active {
    background: #09090b;
    border-color: #09090b;
    color: #ffffff;
}
.dept-pill.active i { color: #ffffff; }

.filter-card {
    background: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 18px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1.8fr 1.2fr;
    gap: 14px;
    align-items: flex-end;
}
@media (max-width: 992px) { .filter-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 600px) { .filter-grid { grid-template-columns: 1fr; } }
.filter-col label {
    display: block;
    font-size: 11px;
    font-weight: 500;
    color: #71717a;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.search-input-wrap { position: relative; }
.clear-search-btn {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    background: none; border: none; font-size: 16px; color: #a1a1aa; cursor: pointer;
}
.price-input-group { display: flex; align-items: center; gap: 5px; }
.price-input { width: 85px; }
.price-dash { color: #a1a1aa; font-weight: bold; }
.btn-filter-apply {
    background: #09090b; color: #ffffff; border: none; width: 32px; height: 32px;
    border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.price-presets-row {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 12px;
    padding-top: 10px; border-top: 1px solid #f4f4f5;
}
.presets-label { font-size: 11.5px; font-weight: 500; color: #71717a; }
.preset-pill {
    background: #f4f4f5; border: 1px solid #e4e4e7; color: #52525b; font-size: 11px;
    padding: 2px 8px; border-radius: 4px; cursor: pointer;
}
.reset-pill { margin-left: auto; background: #fff; color: #ef4444; border-color: #fecaca; }

.selection-action-bar {
    position: sticky; top: 55px; z-index: 100; background: #ffffff; color: #09090b;
    padding: 10px 16px; border-radius: 8px; display: flex; justify-content: space-between;
    align-items: center; gap: 14px; margin-bottom: 18px; border: 1px solid #e4e4e7;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
.selection-summary { display: flex; align-items: center; gap: 12px; flex: 1; overflow: hidden; }
.selection-count-badge {
    background: #f4f4f5; color: #09090b; border: 1px solid #e4e4e7; padding: 4px 10px; border-radius: 6px;
    font-weight: 500; font-size: 12px; white-space: nowrap; display: flex; align-items: center; gap: 5px;
}
.selection-tags-wrap { display: flex; gap: 5px; overflow-x: auto; white-space: nowrap; max-width: 500px; }
.sel-chip {
    background: #f4f4f5; color: #09090b; border: 1px solid #e4e4e7; padding: 2px 7px; border-radius: 4px;
    font-size: 11px; display: inline-flex; align-items: center; gap: 4px;
}
.sel-chip-remove { cursor: pointer; font-weight: bold; }
.selection-buttons { display: flex; gap: 8px; align-items: center; }

.btn-gold-action {
    background: #09090b; color: #ffffff;
    border: none; padding: 0 14px; height: 32px; border-radius: 6px; font-weight: 500; font-size: 12.5px;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
}
.btn-gold-action:hover { background: #27272a; }
.btn-secondary-custom {
    background: #f4f4f5; color: #09090b; border: 1px solid #e4e4e7;
    padding: 0 12px; height: 32px; border-radius: 6px; font-weight: 500; font-size: 12px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 5px;
}
.btn-secondary-custom:hover { background: #e4e4e7; }
.step-content-pane .btn-secondary-custom { background: #f4f4f5; color: #09090b; border: 1px solid #e4e4e7; }
.btn-danger-custom { color: #ef4444; border-color: #fecaca; }

.results-meta-bar { display: flex; justify-content: space-between; font-size: 12.5px; color: #64748b; margin-bottom: 12px; }
.products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 15px; margin-bottom: 20px; }
.product-card {
    background: #ffffff; border: 2px solid #e2e8f0; border-radius: 10px; overflow: hidden;
    cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; position: relative;
}
.product-card.selected { border-color: #c8a55c; background: #fffdf9; }
.product-card-img-wrap { height: 185px; background: #fafafa; position: relative; display: flex; align-items: center; justify-content: center; }
.product-card-img { max-height: 100%; max-width: 100%; object-fit: cover; }
.card-select-checkbox {
    position: absolute; top: 8px; left: 8px; width: 22px; height: 22px; border-radius: 5px;
    background: rgba(255,255,255,0.9); border: 2px solid #cbd5e1; display: flex; align-items: center;
    justify-content: center; color: transparent; font-size: 12px;
}
.product-card.selected .card-select-checkbox { background: #c8a55c; border-color: #c8a55c; color: #ffffff; }
.gallery-count-badge {
    position: absolute; bottom: 6px; right: 6px; background: rgba(15, 23, 42, 0.75); color: #ffffff;
    font-size: 10px; padding: 2px 6px; border-radius: 10px;
}
.product-card-body { padding: 10px 12px; flex: 1; display: flex; flex-direction: column; }
.card-cat-badge { font-size: 10px; color: #a88438; font-weight: 600; text-transform: uppercase; }
.card-prod-title { font-size: 12px; font-weight: 600; color: #1e293b; height: 32px; overflow: hidden; margin: 3px 0; }
.card-prod-sku { font-size: 10.5px; color: #64748b; margin-bottom: 4px; }
.card-prod-price { font-size: 13.5px; font-weight: 700; color: #c8a55c; margin-top: auto; }
.card-prod-price .strike { font-size: 11px; color: #94a3b8; text-decoration: line-through; margin-left: 4px; }

.pagination-bar { display: flex; justify-content: center; gap: 6px; margin: 18px 0 30px 0; }
.page-nav-btn { padding: 5px 12px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 5px; cursor: pointer; }
.page-num { width: 32px; height: 32px; border-radius: 5px; border: 1px solid #cbd5e1; background: #ffffff; font-weight: 600; cursor: pointer; }
.page-num.active { background: #c8a55c; border-color: #c8a55c; color: #ffffff; }

.loading-state-box, .empty-state-box { text-align: center; padding: 50px 20px; background: #ffffff; border-radius: 10px; border: 1px dashed #cbd5e1; margin-bottom: 20px; }
.spinner-gold { width: 38px; height: 38px; border: 4px solid #f1f5f9; border-top-color: #c8a55c; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 12px auto; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ==========================================================================
   STEP 2: REVIEW CARDS & ANGLE SELECTOR (MATCHING SCREENSHOT 1)
   ========================================================================== */
.step-top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
.step-top-nav h2 { font-size: 19px; font-weight: 700; margin: 0; color: #1e293b; }

.review-item-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0;
    margin-bottom: 22px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    overflow: hidden;
}
.review-header-bar {
    background: #f1f5f9;
    padding: 12px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e8f0;
}
.review-sku-label {
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
}
.review-sku-label span {
    color: #64748b;
    font-weight: normal;
    font-size: 12.5px;
    margin-left: 4px;
}
.review-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.image-found-badge {
    background: #10b981;
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 4px;
    display: inline-block;
}
.review-card-body {
    padding: 16px 18px;
}
.angles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
    gap: 14px;
}
.angle-card {
    position: relative;
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    display: flex;
    flex-direction: column;
}
.angle-card:hover {
    border-color: #94a3b8;
    transform: translateY(-2px);
}
.angle-card.primary-active {
    border-color: #10b981; /* Green highlight for Main Image */
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2), 0 4px 12px rgba(16, 185, 129, 0.15);
}
.angle-card.active {
    border-color: #3b82f6; /* Blue highlight */
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2), 0 4px 12px rgba(59, 130, 246, 0.15);
}
.main-angle-badge {
    position: absolute;
    top: 7px;
    left: 7px;
    background: #10b981;
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.02em;
    padding: 2px 7px;
    border-radius: 9999px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.18);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    z-index: 2;
    pointer-events: none;
}
.angle-img-wrap {
    height: 165px;
    background: #fafafa;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
}
.angle-img-wrap img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
}
.angle-card-footer {
    padding: 8px 10px;
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    user-select: none;
}
.angle-radio-input {
    width: 15px;
    height: 15px;
    accent-color: #10b981;
    cursor: pointer;
    margin: 0;
}
.angle-card.active .angle-radio-input {
    accent-color: #3b82f6;
}
.angle-card.primary-active .angle-radio-input {
    accent-color: #10b981;
}
.selected-status-badge {
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.selected-status-badge.is-main {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.selected-status-badge.is-all {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
}
.selected-status-badge.is-other {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.btn-active-gold {
    background: #fef3c7 !important;
    border-color: #f59e0b !important;
    color: #b45309 !important;
    font-weight: 600 !important;
}

/* Step 3 Export Grid */
.export-layout-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
@media (max-width: 900px) { .export-layout-grid { grid-template-columns: 1fr; } }
.export-settings-card, .export-action-card {
    background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;
}
.card-section-title { font-size: 15px; font-weight: 700; color: #1e293b; margin: 0 0 16px 0; }
.form-group-custom { margin-bottom: 16px; }
.form-group-custom label { display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 5px; }
.optional-tag { font-size: 11px; color: #94a3b8; font-weight: normal; }
.layout-selector-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
@media (max-width: 768px) { .layout-selector-grid { grid-template-columns: 1fr; } }
.layout-option-card {
    border: 2px solid #e2e8f0; border-radius: 8px; padding: 12px 10px; cursor: pointer;
    background: #ffffff; display: block; position: relative;
}
.layout-option-card input { position: absolute; opacity: 0; }
.layout-option-card.active { border-color: #c8a55c; background: #fffdf9; }
.layout-icon { font-size: 20px; color: #64748b; margin-bottom: 4px; }
.layout-option-card.active .layout-icon { color: #c8a55c; }
.layout-label { font-size: 12.5px; font-weight: 700; color: #1e293b; }
.layout-sub { font-size: 10.5px; color: #64748b; }
.field-toggles-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.toggle-checkbox-label {
    display: flex; align-items: center; gap: 7px; padding: 7px 10px; background: #f8fafc;
    border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer;
}
.toggle-checkbox-label input { accent-color: #c8a55c; }
.export-stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 16px; }
.stat-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 12.5px; color: #475569; border-bottom: 1px solid #f1f5f9; }
.stat-row:last-child { border-bottom: none; }
.badge-active { background: #dcfce7; color: #15803d; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 600; }
.export-buttons-stack { display: flex; flex-direction: column; gap: 8px; }
.btn-large { width: 100%; justify-content: center; padding: 11px 18px; font-size: 13.5px; }
.export-note { font-size: 11px; color: #64748b; text-align: center; margin-top: 12px; }
</style>

<script>
// =============================================================================
// JAVASCRIPT APPLICATION LOGIC
// =============================================================================

const DEFAULT_PLACEHOLDER_IMG = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='300' height='360' viewBox='0 0 300 360'><rect fill='%23f8fafc' width='300' height='360'/><rect x='20' y='20' width='260' height='320' rx='8' fill='%23f1f5f9' stroke='%23e2e8f0' stroke-width='2'/><circle cx='150' cy='150' r='36' fill='%23e2e8f0'/><path d='M138 160l8-10 12 15 8-6 10 13H124z' fill='%2394a3b8'/><text fill='%2364748b' font-family='sans-serif' font-size='13' font-weight='bold' x='50%' y='235' text-anchor='middle'>Photo Not Available</text></svg>";

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
    selectedProductIds: new Set(),
    selectedProductsMap: new Map(),
    fullProductDetails: [],
    currentProductsList: []
};

let searchDebounceTimer = null;

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

document.addEventListener('DOMContentLoaded', () => {
    loadMetadata();
    loadProducts();
});

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

function renderCategoryDropdown() {
    const select = document.getElementById('filter_category');
    if (!select) return;

    let html = '<option value="">All Categories in Department</option>';
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
            if (grid) grid.innerHTML = `<div class="empty-state-box"><p>Failed to connect to product server.</p></div>`;
        });
}

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

function clearAllSelections() {
    if (state.selectedProductIds.size === 0) return;
    if (confirm('Are you sure you want to clear all selected products from your PDF tray?')) {
        state.selectedProductIds.clear();
        state.selectedProductsMap.clear();
        updateSelectionUI();
        renderProductsGrid();
    }
}

function updateSelectionUI() {
    const count = state.selectedProductIds.size;
    document.getElementById('header_selected_count').textContent = count;
    document.getElementById('bar_selected_count').textContent = count;
    document.getElementById('btn_selected_count').textContent = count;
    document.getElementById('step2_item_count').textContent = count;

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
// STEP 2: REVIEW SELECTION & ANGLE PICKER (MAIN PRODUCT IMAGE DEFAULT)
// =============================================================================
function ensureFullProductDetails() {
    if (state.selectedProductIds.size === 0) {
        state.fullProductDetails = [];
        return;
    }

    const currentMap = new Map(state.fullProductDetails.map(p => [p.id, p]));
    const updatedList = [];

    state.selectedProductsMap.forEach((p, id) => {
        if (currentMap.has(id)) {
            const existing = currentMap.get(id);
            if (!existing.selected_images || existing.selected_images.length === 0) {
                const main = (existing.all_images && existing.all_images[0]) ? existing.all_images[0].path : formatImageUrl(existing.display_image || existing.main_image);
                existing.selected_images = main ? [main] : [];
            }
            updatedList.push(existing);
        } else {
            const mainImg = formatImageUrl(p.display_image || p.main_image);
            updatedList.push({
                id: p.id,
                name: p.name,
                sku: p.sku,
                price: p.price,
                sale_price: p.sale_price,
                category_name: p.primary_category_name || '',
                main_image: p.main_image,
                display_image: p.display_image,
                selected_images: [mainImg], // Default: Main Product Image
                all_images: [{
                    id: 'main_' + p.id,
                    path: mainImg,
                    is_main: true,
                    thumb: mainImg
                }]
            });
        }
    });

    state.fullProductDetails = updatedList;
}

function goToStep2() {
    if (state.selectedProductIds.size === 0) {
        alert('Please select at least one product before proceeding to review.');
        return;
    }
    ensureFullProductDetails();
    switchStep(2);
}

function loadStep2Data() {
    const loading = document.getElementById('step2_loading');
    const emptyState = document.getElementById('step2_empty_state');
    const list = document.getElementById('review_products_list');

    if (state.selectedProductIds.size === 0) {
        if (emptyState) emptyState.style.display = 'block';
        if (list) list.innerHTML = '';
        state.fullProductDetails = [];
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
                    const allImgs = p.all_images || [];
                    const mainImgObj = allImgs.find(i => i.is_main) || allImgs[0];
                    const mainImgPath = mainImgObj ? mainImgObj.path : formatImageUrl(p.main_image);

                    if (existingMap.has(p.id)) {
                        const ex = existingMap.get(p.id);
                        p.name = ex.name || p.name;
                        // Retain existing custom selection only if valid and not empty; otherwise default to main image
                        p.selected_images = (ex.selected_images && ex.selected_images.length > 0) ? ex.selected_images : [mainImgPath];
                    } else {
                        // STRICT DEFAULT: Always the main product image (Angle 1)
                        p.selected_images = [mainImgPath];
                    }
                    return p;
                });
                renderReviewList();
            }
        })
        .catch(err => {
            if (loading) loading.style.display = 'none';
            console.error('Error fetching review images:', err);
            ensureFullProductDetails();
            renderReviewList();
        });
}

function renderReviewList() {
    const list = document.getElementById('review_products_list');
    if (!list) return;

    let html = '';
    state.fullProductDetails.forEach(prod => {
        const pId = prod.id;
        const selectedImgSet = new Set(prod.selected_images || []);
        const allImages = prod.all_images || [];
        const totalImagesCount = allImages.length;
        
        const mainImgObj = allImages.find(i => i.is_main) || allImages[0];
        const mainImgPath = mainImgObj ? mainImgObj.path : formatImageUrl(prod.main_image);

        const isMainSelected = selectedImgSet.has(mainImgPath) || selectedImgSet.has(formatImageUrl(mainImgPath));
        const isMainOnly = selectedImgSet.size === 1 && isMainSelected;
        const isAllSelected = selectedImgSet.size === totalImagesCount && totalImagesCount > 1;

        let statusBadgeHtml = '';
        if (isMainOnly) {
            statusBadgeHtml = `<span class="selected-status-badge is-main"><i class="fa-solid fa-star"></i> Main Image (Default)</span>`;
        } else if (isAllSelected) {
            statusBadgeHtml = `<span class="selected-status-badge is-all"><i class="fa-solid fa-layer-group"></i> All ${totalImagesCount} Angles Selected</span>`;
        } else {
            statusBadgeHtml = `<span class="selected-status-badge is-other"><i class="fa-solid fa-check"></i> ${selectedImgSet.size} Angle(s) Selected</span>`;
        }

        let anglesHtml = '';
        allImages.forEach((img, idx) => {
            const formattedImgUrl = formatImageUrl(img.path);
            const isImgSelected = selectedImgSet.has(img.path) || selectedImgSet.has(formattedImgUrl);
            const isMain = (idx === 0 || img.is_main);
            
            let activeClass = '';
            if (isImgSelected) {
                activeClass = isMain ? 'primary-active' : 'active';
            }

            const mainBadge = isMain ? `<div class="main-angle-badge"><i class="fa-solid fa-star"></i> Main Image</div>` : '';
            const angleLabel = isMain ? 'Angle 1 (Main Image)' : ('Angle ' + (idx + 1));

            anglesHtml += `
                <div class="angle-card ${activeClass}" onclick="selectProductAngle(${pId}, '${escapeJsString(img.path)}')">
                    ${mainBadge}
                    <div class="angle-img-wrap">
                        <img src="${formattedImgUrl}" alt="${angleLabel}" onerror="this.onerror=null; this.src=DEFAULT_PLACEHOLDER_IMG;">
                    </div>
                    <div class="angle-card-footer">
                        <input type="radio" 
                               class="angle-radio-input" 
                               name="angle_radio_${pId}" 
                               value="${escapeHtml(img.path)}" 
                               ${isImgSelected ? 'checked' : ''} 
                               onclick="event.stopPropagation(); selectProductAngle(${pId}, '${escapeJsString(img.path)}')">
                        <span>${angleLabel}</span>
                    </div>
                </div>
            `;
        });

        html += `
            <div class="review-item-card" id="review_card_${pId}">
                <!-- Header with clear indication of default main image -->
                <div class="review-header-bar">
                    <div class="review-sku-label">
                        SKU: <strong>${escapeHtml(prod.sku)}</strong> <span>(ID: ${pId})</span>
                    </div>
                    <div class="review-header-actions">
                        ${statusBadgeHtml}
                        <button type="button" class="btn-secondary-custom ${isMainOnly ? 'btn-active-gold' : ''}" style="padding: 4px 10px; font-size: 11.5px;" onclick="selectMainImageForProduct(${pId})">
                            <i class="fa-solid fa-star"></i> Main Image Only
                        </button>
                        <button type="button" class="btn-secondary-custom ${isAllSelected ? 'btn-active-gold' : ''}" style="padding: 4px 10px; font-size: 11.5px;" onclick="selectAllImagesForProduct(${pId})">
                            <i class="fa-solid fa-images"></i> Select All Angles
                        </button>
                        <button type="button" class="btn-secondary-custom btn-danger-custom" style="padding: 4px 10px; font-size: 11.5px;" onclick="removeProductFromReview(${pId})">
                            <i class="fa-solid fa-trash"></i> Remove
                        </button>
                    </div>
                </div>

                <div class="review-card-body">
                    <div class="angles-grid">
                        ${anglesHtml}
                    </div>
                </div>
            </div>
        `;
    });

    list.innerHTML = html;
}

// Single-angle selection (Radio behavior matching Sri Shringarr review_pdfmaker.php)
function selectProductAngle(productId, imgPath) {
    const prod = state.fullProductDetails.find(p => p.id === productId);
    if (!prod) return;
    prod.selected_images = [imgPath];
    renderReviewList();
    updateStep3Summary();
}

function selectMainImageForProduct(productId) {
    const prod = state.fullProductDetails.find(p => p.id === productId);
    if (!prod) return;
    const allImages = prod.all_images || [];
    const mainImg = allImages.find(i => i.is_main) || allImages[0];
    if (mainImg) {
        prod.selected_images = [mainImg.path];
        renderReviewList();
        updateStep3Summary();
    }
}

function selectAllImagesForProduct(productId) {
    const prod = state.fullProductDetails.find(p => p.id === productId);
    if (!prod) return;
    prod.selected_images = (prod.all_images || []).map(i => i.path);
    renderReviewList();
    updateStep3Summary();
}

function removeProductFromReview(productId) {
    state.selectedProductIds.delete(productId);
    state.selectedProductsMap.delete(productId);
    state.fullProductDetails = state.fullProductDetails.filter(p => p.id !== productId);
    updateSelectionUI();
    renderProductsGrid();
    renderReviewList();
    updateStep3Summary();
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
    ensureFullProductDetails();
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
    ensureFullProductDetails();
    const totalProds = state.fullProductDetails.length;
    let totalImgs = 0;
    state.fullProductDetails.forEach(p => {
        totalImgs += (p.selected_images || []).length;
    });

    const includeCover = document.getElementById('toggle_include_cover') ? document.getElementById('toggle_include_cover').checked : true;
    let coverPageCount = includeCover ? 1 : 0;

    let estPages = 1;
    if (currentLayout === 'hero') {
        estPages = coverPageCount + totalProds;
    } else if (currentLayout === 'showcase') {
        estPages = coverPageCount + Math.ceil(totalProds / 2);
    } else {
        estPages = coverPageCount + Math.ceil(totalProds / 4);
    }

    const countElem = document.getElementById('final_product_count');
    const imgElem = document.getElementById('final_image_count');
    const pageElem = document.getElementById('final_page_est');
    if (countElem) countElem.textContent = totalProds;
    if (imgElem) imgElem.textContent = totalImgs;
    if (pageElem) pageElem.textContent = estPages + ' Page' + (estPages > 1 ? 's' : '');
}

function generatePdf(outputMode = 'pdf') {
    ensureFullProductDetails();
    if (state.fullProductDetails.length === 0) {
        alert('No products selected to export.');
        return;
    }

    const payload = {
        brand_title: document.getElementById('pdf_brand_title').value.trim() || 'YosshitaNeha Fashion Studio',
        brand_subtitle: document.getElementById('pdf_brand_subtitle').value.trim() || 'The Ultimate Fashion Destination',
        catalogue_title: document.getElementById('pdf_catalogue_title').value.trim() || 'Exclusive Collection Lookbook',
        client_name: document.getElementById('pdf_client_name').value.trim(),
        include_cover: document.getElementById('toggle_include_cover').checked ? 1 : 0,
        show_product_link: document.getElementById('toggle_show_product_link').checked ? 1 : 0,
        layout: currentLayout,
        show_price: document.getElementById('toggle_show_price').checked ? 1 : 0,
        show_sku: document.getElementById('toggle_show_sku').checked ? 1 : 0,
        show_category: document.getElementById('toggle_show_category').checked ? 1 : 0,
        custom_notes: document.getElementById('pdf_custom_notes').value.trim(),
        output_mode: outputMode,
        products: state.fullProductDetails
    };

    const form = document.getElementById('pdf_export_form');
    const input = document.getElementById('pdf_payload_input');
    input.value = JSON.stringify(payload);

    form.submit();
}

function switchStep(stepNum) {
    state.currentStep = stepNum;

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

    if (stepNum === 2) {
        loadStep2Data();
    } else if (stepNum === 3) {
        ensureFullProductDetails();
        updateStep3Summary();
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

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
