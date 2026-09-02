<?php
// admin/includes/sidebar.php
$current_script = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar Overlay Backdrop for Mobile -->
<div id="sidebar-backdrop" class="sidebar-backdrop"></div>

<!-- Modern Administrative Sidebar -->
<aside id="adminmenuwrap">


    <ul id="adminmenu">
        <!-- Overview Group -->
        <li class="menu-section-label">Overview</li>
        
        <li class="menu-item <?php echo ($current_script == 'index.php') ? 'active' : ''; ?>">
            <a href="index.php">
                <i class="fa-solid fa-gauge-high"></i> 
                <span>Dashboard</span>
            </a>
        </li>
        
        <!-- Management Group -->
        <li class="menu-section-label">Management</li>
        
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
                        <i class="fa-solid fa-shirt"></i> All Products
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'product-add.php') ? 'active' : ''; ?>">
                    <a href="product-add.php">
                        <i class="fa-solid fa-circle-plus"></i> Add Product
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'categories.php') ? 'active' : ''; ?>">
                    <a href="categories.php">
                        <i class="fa-solid fa-folder-tree"></i> Categories
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
                <i class="fa-solid fa-box-open"></i>
                <span>Orders</span>
            </a>
        </li>

        <!-- Collections & Lookbook Group -->
        <?php 
        $collections_active = in_array($current_script, ['collections.php', 'collection-add.php', 'collection-edit.php', 'collection-ai-sorter.php']);
        ?>
        <li class="menu-item has-submenu <?php echo $collections_active ? 'open active-parent' : ''; ?>">
            <a href="javascript:void(0);" class="submenu-toggle">
                <i class="fa-solid fa-camera-retro"></i> 
                <span>Collections &amp; Lookbook</span>
                <i class="fa-solid fa-chevron-right submenu-arrow"></i>
            </a>
            <ul class="submenu">
                <li class="<?php echo ($current_script == 'collections.php' || $current_script == 'collection-edit.php') ? 'active' : ''; ?>">
                    <a href="collections.php">
                        <i class="fa-solid fa-vest-patches"></i> Outfit Styles
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'collection-add.php') ? 'active' : ''; ?>">
                    <a href="collection-add.php">
                        <i class="fa-solid fa-circle-plus"></i> Add Outfit Style
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'collection-ai-sorter.php') ? 'active' : ''; ?>">
                    <a href="collection-ai-sorter.php">
                        <i class="fa-solid fa-wand-magic-sparkles" style="color:#a855f7;"></i> AI Outfit Sorter <span class="badge" style="background:#6366f1;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:4px;">Gemini</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Modules Group -->
        <li class="menu-section-label">Modules</li>
        <?php 
        $modules_active = in_array($current_script, ['pdf-maker.php']);
        ?>
        <li class="menu-item has-submenu <?php echo $modules_active ? 'open active-parent' : ''; ?>">
            <a href="javascript:void(0);" class="submenu-toggle">
                <i class="fa-solid fa-cubes"></i> 
                <span>Modules</span>
                <i class="fa-solid fa-chevron-right submenu-arrow"></i>
            </a>
            <ul class="submenu">
                <li class="<?php echo ($current_script == 'pdf-maker.php') ? 'active' : ''; ?>">
                    <a href="pdf-maker.php">
                        <i class="fa-solid fa-file-pdf"></i> PDF Maker
                    </a>
                </li>
            </ul>
        </li>

        <!-- Tools Group -->
        <?php 
        $tools_active = in_array($current_script, ['desc-corrector.php', 'analytics.php', 'cache-manager.php', 'generate-yn-products-excel.php', 'sku-lookup.php', 'import_archive.php', 'pos-price-sync.php', 'sync_db.php', 'bulk-ai-writer.php']);
        ?>
        <li class="menu-item has-submenu <?php echo $tools_active ? 'open active-parent' : ''; ?>">
            <a href="javascript:void(0);" class="submenu-toggle">
                <i class="fa-solid fa-screwdriver-wrench"></i> 
                <span>Tools &amp; Data</span>
                <i class="fa-solid fa-chevron-right submenu-arrow"></i>
            </a>
            <ul class="submenu">
                <li class="<?php echo ($current_script == 'bulk-ai-writer.php') ? 'active' : ''; ?>">
                    <a href="bulk-ai-writer.php" style="font-weight: 700; color: #a855f7;">
                        <i class="fa-solid fa-wand-magic-sparkles" style="color: #a855f7;"></i> Bulk AI Writer <span class="badge" style="background:#7c3aed;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:4px;">Gemini</span>
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'pos-price-sync.php') ? 'active' : ''; ?>">
                    <a href="pos-price-sync.php">
                        <i class="fa-solid fa-tags" style="color: #6366f1;"></i> POS Price Sync
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'import_archive.php') ? 'active' : ''; ?>">
                    <a href="import_archive.php">
                        <i class="fa-solid fa-folder-tree"></i> Archive Importer
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'generate-yn-products-excel.php') ? 'active' : ''; ?>">
                    <a href="generate-yn-products-excel.php">
                        <i class="fa-solid fa-file-excel"></i> YN Products Excel
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'sku-lookup.php') ? 'active' : ''; ?>">
                    <a href="sku-lookup.php">
                        <i class="fa-solid fa-magnifying-glass"></i> SKU Lookup
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'desc-corrector.php') ? 'active' : ''; ?>">
                    <a href="desc-corrector.php">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Description Corrector
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'analytics.php') ? 'active' : ''; ?>">
                    <a href="analytics.php">
                        <i class="fa-solid fa-chart-line"></i> Analytics &amp; Traffic
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'cache-manager.php') ? 'active' : ''; ?>">
                    <a href="cache-manager.php">
                        <i class="fa-solid fa-bolt"></i> Cache Manager
                    </a>
                </li>
                <li class="<?php echo ($current_script == 'sync_db.php') ? 'active' : ''; ?>">
                    <a href="sync_db.php">
                        <i class="fa-solid fa-database" style="color: #0ea5e9;"></i> Database Sync
                    </a>
                </li>
            </ul>
        </li>

        <!-- Settings Group -->
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
                        <i class="fa-solid fa-robot"></i> AI Chatbot
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
        <li class="menu-section-label">Marketing</li>
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

    <div class="sidebar-footer">
        <span>YosshitaNeha Fashion</span>
        <span class="version">v2.0.0 Pro</span>
    </div>
</aside>

<main id="wpcontent">
