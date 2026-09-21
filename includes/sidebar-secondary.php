<?php
// admin/includes/sidebar-secondary.php
// New Secondary Admin Menu Architecture
$current_script = basename($_SERVER['PHP_SELF']);
$current_tab = strtolower($_GET['tab'] ?? '');
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['username'] ?? 'Admin';
$admin_initials = strtoupper(substr($admin_name, 0, 2));
?>
<!-- Sidebar Overlay Backdrop for Mobile -->
<div id="sidebar-backdrop" class="sidebar-backdrop"></div>

<!-- Modern Administrative Sidebar - Secondary Menu -->
<aside id="adminmenuwrap" class="shadcn-sidebar">
    
    <!-- Workspace Brand Header -->
    <div class="sidebar-header-workspace">
        <a href="index.php" class="workspace-brand">
            <div class="workspace-icon">
                <i class="fa-solid fa-gem"></i>
            </div>
            <div class="workspace-meta">
                <span class="workspace-title">YosshitaNeha</span>
                <span class="workspace-tag">Fashion Studio &bull; Pro</span>
            </div>
        </a>
        <button type="button" id="sidebar-close-btn" class="sidebar-close-mobile-btn" aria-label="Close Sidebar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Scrollable Navigation Area -->
    <div class="sidebar-scroll-container">
        <ul id="adminmenu">
            
            <!-- ==================== PLATFORM ==================== -->
            <li class="menu-section-label">Platform</li>
            
            <li class="menu-item <?php echo ($current_script == 'index.php') ? 'active' : ''; ?>">
                <a href="index.php">
                    <i class="fa-solid fa-chart-pie"></i> 
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="menu-item <?php echo ($current_script == 'analytics.php') ? 'active' : ''; ?>">
                <a href="analytics.php">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Analytics &amp; Reports</span>
                </a>
            </li>
            
            <!-- ==================== CATALOG & INVENTORY ==================== -->
            <li class="menu-section-label">Catalog &amp; Inventory</li>
            
            <!-- Products Submenu -->
            <?php 
            $products_active = in_array($current_script, [
                'products.php', 'product-add.php', 'product-edit.php', 
                'categories.php', 'pos-price-sync.php', 'sku-lookup.php', 
                'import_archive.php', 'product-import.php'
            ]);
            ?>
            <li class="menu-item has-submenu <?php echo $products_active ? 'open active-parent' : ''; ?>">
                <a href="javascript:void(0);" class="submenu-toggle">
                    <i class="fa-solid fa-boxes-stacked"></i> 
                    <span>Products</span>
                    <i class="fa-solid fa-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_script == 'products.php' || $current_script == 'product-edit.php') ? 'active' : ''; ?>">
                        <a href="products.php">
                            <i class="fa-solid fa-box"></i> All Products
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'product-add.php') ? 'active' : ''; ?>">
                        <a href="product-add.php">
                            <i class="fa-solid fa-plus"></i> Add New Product
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'categories.php') ? 'active' : ''; ?>">
                        <a href="categories.php">
                            <i class="fa-solid fa-tags"></i> Categories
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'pos-price-sync.php') ? 'active' : ''; ?>">
                        <a href="pos-price-sync.php">
                            <i class="fa-solid fa-arrow-rotate-right"></i> POS Price &amp; Stock Sync
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'sku-lookup.php') ? 'active' : ''; ?>">
                        <a href="sku-lookup.php">
                            <i class="fa-solid fa-magnifying-glass"></i> Quick SKU Lookup
                        </a>
                    </li>
                    <li class="<?php echo in_array($current_script, ['import_archive.php', 'product-import.php']) ? 'active' : ''; ?>">
                        <a href="import_archive.php">
                            <i class="fa-solid fa-file-import"></i> Bulk Import &amp; Archive
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Outfit Styles / Lookbook Submenu -->
            <?php 
            $collections_active = in_array($current_script, [
                'collections.php', 'collection-add.php', 'collection-edit.php', 
                'collection-ai-sorter.php'
            ]);
            ?>
            <li class="menu-item has-submenu <?php echo $collections_active ? 'open active-parent' : ''; ?>">
                <a href="javascript:void(0);" class="submenu-toggle">
                    <i class="fa-solid fa-vest-patches"></i> 
                    <span>Outfit Styles / Lookbook</span>
                    <i class="fa-solid fa-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_script == 'collections.php' || $current_script == 'collection-edit.php') ? 'active' : ''; ?>">
                        <a href="collections.php">
                            <i class="fa-solid fa-vest"></i> All Outfits
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'collection-add.php') ? 'active' : ''; ?>">
                        <a href="collection-add.php">
                            <i class="fa-solid fa-plus"></i> Add Outfit Style
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'collection-ai-sorter.php') ? 'active' : ''; ?>">
                        <a href="collection-ai-sorter.php">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> AI Outfit Sorter
                            <span class="shadcn-badge" style="margin-left: auto;">AI</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ==================== SALES & CUSTOMERS ==================== -->
            <li class="menu-section-label">Sales &amp; Customers</li>

            <li class="menu-item <?php echo in_array($current_script, ['orders.php', 'order-detail.php']) ? 'active' : ''; ?>">
                <a href="orders.php">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Orders</span>
                </a>
            </li>

            <!-- Store Rules & Discounts Submenu -->
            <?php 
            $ecommerce_active = ($current_script == 'ecommerce.php');
            ?>
            <li class="menu-item has-submenu <?php echo $ecommerce_active ? 'open active-parent' : ''; ?>">
                <a href="javascript:void(0);" class="submenu-toggle">
                    <i class="fa-solid fa-tags"></i>
                    <span>Store Rules &amp; Discounts</span>
                    <i class="fa-solid fa-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_script == 'ecommerce.php' && ($current_tab == 'shipping' || !$current_tab)) ? 'active' : ''; ?>">
                        <a href="ecommerce.php?tab=shipping">
                            <i class="fa-solid fa-truck-fast"></i> Shipping Rules
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'ecommerce.php' && $current_tab == 'payment') ? 'active' : ''; ?>">
                        <a href="ecommerce.php?tab=payment">
                            <i class="fa-solid fa-credit-card"></i> Payment Gateways
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'ecommerce.php' && in_array($current_tab, ['discounts', 'coupons'])) ? 'active' : ''; ?>">
                        <a href="ecommerce.php?tab=discounts">
                            <i class="fa-solid fa-percent"></i> Coupons &amp; Discounts
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ==================== AI STUDIO & CREATIVE ==================== -->
            <li class="menu-section-label">AI Studio &amp; Creative</li>

            <?php 
            $ai_studio_active = in_array($current_script, [
                'bulk-ai-writer.php', 'desc-corrector.php', 'chatbot-settings.php', 
                'pdf-maker.php', 'generate-yn-products-excel.php'
            ]);
            ?>
            <li class="menu-item has-submenu <?php echo $ai_studio_active ? 'open active-parent' : ''; ?>">
                <a href="javascript:void(0);" class="submenu-toggle">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>AI Content Studio</span>
                    <i class="fa-solid fa-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_script == 'bulk-ai-writer.php') ? 'active' : ''; ?>">
                        <a href="bulk-ai-writer.php">
                            <i class="fa-solid fa-bolt-lightning"></i> Bulk AI Writer
                            <span class="shadcn-badge" style="margin-left: auto;">AI</span>
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'desc-corrector.php') ? 'active' : ''; ?>">
                        <a href="desc-corrector.php">
                            <i class="fa-solid fa-pen-fancy"></i> Description Corrector
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'chatbot-settings.php') ? 'active' : ''; ?>">
                        <a href="chatbot-settings.php">
                            <i class="fa-solid fa-robot"></i> AI Chatbot Assistant
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'pdf-maker.php') ? 'active' : ''; ?>">
                        <a href="pdf-maker.php">
                            <i class="fa-solid fa-file-pdf"></i> Digital PDF Catalog Maker
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'generate-yn-products-excel.php') ? 'active' : ''; ?>">
                        <a href="generate-yn-products-excel.php">
                            <i class="fa-solid fa-file-excel"></i> Export Products to Excel
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ==================== CONTENT & MARKETING ==================== -->
            <li class="menu-section-label">Content &amp; Marketing</li>

            <?php 
            $content_active = in_array($current_script, ['blogs.php', 'blog-add.php', 'blog-edit.php', 'newsletters.php']);
            ?>
            <li class="menu-item has-submenu <?php echo $content_active ? 'open active-parent' : ''; ?>">
                <a href="javascript:void(0);" class="submenu-toggle">
                    <i class="fa-solid fa-bullhorn"></i>
                    <span>Content &amp; Outreach</span>
                    <i class="fa-solid fa-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_script == 'blogs.php' || $current_script == 'blog-edit.php') ? 'active' : ''; ?>">
                        <a href="blogs.php">
                            <i class="fa-solid fa-newspaper"></i> Journal / Blogs
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'blog-add.php') ? 'active' : ''; ?>">
                        <a href="blog-add.php">
                            <i class="fa-solid fa-pen-to-square"></i> Add New Post
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'newsletters.php') ? 'active' : ''; ?>">
                        <a href="newsletters.php">
                            <i class="fa-solid fa-envelope-open-text"></i> Newsletter Subscribers
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ==================== SYSTEM & SETTINGS ==================== -->
            <li class="menu-section-label">System &amp; Settings</li>

            <?php 
            $settings_active = in_array($current_script, [
                'settings.php', 'mail-settings.php', 'masters.php', 
                'users.php', 'user-add.php', 'user-edit.php', 
                'cache-manager.php', 'sync_db.php'
            ]);
            ?>
            <li class="menu-item has-submenu <?php echo $settings_active ? 'open active-parent' : ''; ?>">
                <a href="javascript:void(0);" class="submenu-toggle">
                    <i class="fa-solid fa-gears"></i> 
                    <span>Settings</span>
                    <i class="fa-solid fa-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_script == 'settings.php') ? 'active' : ''; ?>">
                        <a href="settings.php">
                            <i class="fa-solid fa-sliders"></i> General Settings
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'mail-settings.php') ? 'active' : ''; ?>">
                        <a href="mail-settings.php">
                            <i class="fa-solid fa-envelope"></i> Mail &amp; Notifications
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'masters.php') ? 'active' : ''; ?>">
                        <a href="masters.php">
                            <i class="fa-solid fa-layer-group"></i> Masters (Locations/Taxes)
                        </a>
                    </li>
                    <?php if (current_user_can('manage_users')): ?>
                    <li class="<?php echo in_array($current_script, ['users.php', 'user-add.php', 'user-edit.php']) ? 'active' : ''; ?>">
                        <a href="users.php">
                            <i class="fa-solid fa-users"></i> User Management
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="<?php echo ($current_script == 'cache-manager.php') ? 'active' : ''; ?>">
                        <a href="cache-manager.php">
                            <i class="fa-solid fa-bolt"></i> Cache Manager
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'sync_db.php') ? 'active' : ''; ?>">
                        <a href="sync_db.php">
                            <i class="fa-solid fa-database"></i> Database Sync
                        </a>
                    </li>
                </ul>
            </li>
            
        </ul>
    </div>

    <!-- Sidebar User Footer -->
    <div class="sidebar-user-footer">
        <div class="user-profile-badge">
            <div class="user-avatar-pill">
                <?php echo sanitize_html($admin_initials); ?>
            </div>
            <div class="user-meta">
                <span class="user-name"><?php echo sanitize_html($admin_name); ?></span>
                <span class="user-role">Administrator</span>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout-btn" title="Log Out">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
        </a>
    </div>

</aside>

<main id="wpcontent">
