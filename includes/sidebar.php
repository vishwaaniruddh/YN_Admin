<?php
// admin/includes/sidebar.php
$current_script = basename($_SERVER['PHP_SELF']);
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['username'] ?? 'Admin';
$admin_initials = strtoupper(substr($admin_name, 0, 2));
?>
<!-- Sidebar Overlay Backdrop for Mobile -->
<div id="sidebar-backdrop" class="sidebar-backdrop"></div>

<!-- Modern Administrative Sidebar - ShadCN Style -->
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
            <!-- Platform Group -->
            <li class="menu-section-label">Platform</li>
            
            <li class="menu-item <?php echo ($current_script == 'index.php') ? 'active' : ''; ?>">
                <a href="index.php">
                    <i class="fa-solid fa-chart-pie"></i> 
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Catalog Group -->
            <?php 
            $catalog_active = in_array($current_script, ['products.php', 'product-add.php', 'product-edit.php', 'categories.php', 'product-import.php', 'import_archive.php']);
            ?>
            <li class="menu-item has-submenu <?php echo $catalog_active ? 'open active-parent' : ''; ?>">
                <a href="javascript:void(0);" class="submenu-toggle">
                    <i class="fa-solid fa-store"></i> 
                    <span>Catalog</span>
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
                            <i class="fa-solid fa-plus"></i> Add Product
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'categories.php') ? 'active' : ''; ?>">
                        <a href="categories.php">
                            <i class="fa-solid fa-tags"></i> Categories
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'import_archive.php') ? 'active' : ''; ?>">
                        <a href="import_archive.php">
                            <i class="fa-solid fa-file-import"></i> Archive / Folder Import
                        </a>
                    </li>
                </ul>
            </li>

            <li class="menu-item <?php echo ($current_script == 'ecommerce.php') ? 'active' : ''; ?>">
                <a href="ecommerce.php">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Ecommerce</span>
                </a>
            </li>

            <li class="menu-item <?php echo ($current_script == 'orders.php') ? 'active' : ''; ?>">
                <a href="orders.php">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Orders</span>
                </a>
            </li>

            <!-- Collections & Lookbook Group -->
            <li class="menu-section-label">Collections</li>

            <?php 
            $collections_active = in_array($current_script, ['collections.php', 'collection-add.php', 'collection-edit.php', 'collection-ai-sorter.php', 'sync-sold-out-collections.php']);
            ?>
            <li class="menu-item has-submenu <?php echo $collections_active ? 'open active-parent' : ''; ?>">
                <a href="javascript:void(0);" class="submenu-toggle">
                    <i class="fa-solid fa-camera-retro"></i> 
                    <span>Outfit Styles</span>
                    <i class="fa-solid fa-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_script == 'collections.php' || $current_script == 'collection-edit.php') ? 'active' : ''; ?>">
                        <a href="collections.php">
                            <i class="fa-solid fa-vest"></i> All Outfit Styles
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'collection-add.php') ? 'active' : ''; ?>">
                        <a href="collection-add.php">
                            <i class="fa-solid fa-plus"></i> Add Outfit Style
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'sync-sold-out-collections.php') ? 'active' : ''; ?>">
                        <a href="sync-sold-out-collections.php">
                            <i class="fa-solid fa-boxes-packing" style="color: #f59e0b;"></i> Sync Sold Out
                            <span class="shadcn-badge shadcn-badge-amber" style="margin-left: auto;">POS</span>
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'collection-ai-sorter.php') ? 'active' : ''; ?>">
                        <a href="collection-ai-sorter.php">
                            <i class="fa-solid fa-wand-magic-sparkles" style="color: #c084fc;"></i> AI Sorter
                            <span class="shadcn-badge shadcn-badge-gemini" style="margin-left: auto;">Gemini</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- AI & Tools Group -->
            <li class="menu-section-label">AI &amp; Tools</li>

            <?php 
            $tools_active = in_array($current_script, ['desc-corrector.php', 'analytics.php', 'cache-manager.php', 'generate-yn-products-excel.php', 'sku-lookup.php', 'pos-price-sync.php', 'sync_db.php', 'bulk-ai-writer.php', 'pdf-maker.php']);
            ?>
            <li class="menu-item has-submenu <?php echo $tools_active ? 'open active-parent' : ''; ?>">
                <a href="javascript:void(0);" class="submenu-toggle">
                    <i class="fa-solid fa-screwdriver-wrench"></i> 
                    <span>Tools &amp; AI</span>
                    <i class="fa-solid fa-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_script == 'bulk-ai-writer.php') ? 'active' : ''; ?>">
                        <a href="bulk-ai-writer.php">
                            <i class="fa-solid fa-wand-magic-sparkles" style="color: #c084fc;"></i> Bulk AI Writer
                            <span class="shadcn-badge shadcn-badge-gemini" style="margin-left: auto;">AI</span>
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'pos-price-sync.php') ? 'active' : ''; ?>">
                        <a href="pos-price-sync.php">
                            <i class="fa-solid fa-arrow-rotate-right" style="color: #6366f1;"></i> POS Price Sync
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'generate-yn-products-excel.php') ? 'active' : ''; ?>">
                        <a href="generate-yn-products-excel.php">
                            <i class="fa-solid fa-file-excel" style="color: #10b981;"></i> Products Excel
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'sku-lookup.php') ? 'active' : ''; ?>">
                        <a href="sku-lookup.php">
                            <i class="fa-solid fa-magnifying-glass"></i> SKU Lookup
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'desc-corrector.php') ? 'active' : ''; ?>">
                        <a href="desc-corrector.php">
                            <i class="fa-solid fa-pen-fancy"></i> Description Corrector
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'pdf-maker.php') ? 'active' : ''; ?>">
                        <a href="pdf-maker.php">
                            <i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i> PDF Maker
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'analytics.php') ? 'active' : ''; ?>">
                        <a href="analytics.php">
                            <i class="fa-solid fa-chart-line"></i> Analytics &amp; Traffic
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'cache-manager.php') ? 'active' : ''; ?>">
                        <a href="cache-manager.php">
                            <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i> Cache Manager
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'sync_db.php') ? 'active' : ''; ?>">
                        <a href="sync_db.php">
                            <i class="fa-solid fa-database" style="color: #0ea5e9;"></i> Database Sync
                        </a>
                    </li>
                </ul>
            </li>

            <!-- System & Configuration -->
            <li class="menu-section-label">System</li>

            <?php 
            $settings_active = in_array($current_script, ['settings.php', 'mail-settings.php', 'masters.php', 'chatbot-settings.php']);
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
                            <i class="fa-solid fa-sliders"></i> General
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'chatbot-settings.php') ? 'active' : ''; ?>">
                        <a href="chatbot-settings.php">
                            <i class="fa-solid fa-robot" style="color: #c084fc;"></i> AI Chatbot
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'mail-settings.php') ? 'active' : ''; ?>">
                        <a href="mail-settings.php">
                            <i class="fa-solid fa-envelope"></i> Mail
                        </a>
                    </li>
                    <li class="<?php echo ($current_script == 'masters.php') ? 'active' : ''; ?>">
                        <a href="masters.php">
                            <i class="fa-solid fa-layer-group"></i> Masters
                        </a>
                    </li>
                </ul>
            </li>
            
            <!-- Marketing Group -->
            <li class="menu-item <?php echo ($current_script == 'newsletters.php') ? 'active' : ''; ?>">
                <a href="newsletters.php">
                    <i class="fa-solid fa-envelope-open-text"></i>
                    <span>Newsletters</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($current_script == 'blogs.php' || $current_script == 'blog-add.php' || $current_script == 'blog-edit.php') ? 'active' : ''; ?>">
                <a href="blogs.php">
                    <i class="fa-solid fa-newspaper"></i>
                    <span>Blogs</span>
                </a>
            </li>
            
            <?php if (current_user_can('manage_users')): ?>
            <li class="menu-item <?php echo ($current_script == 'users.php' || $current_script == 'user-add.php' || $current_script == 'user-edit.php') ? 'active' : ''; ?>">
                <a href="users.php">
                    <i class="fa-solid fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <?php endif; ?>
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
