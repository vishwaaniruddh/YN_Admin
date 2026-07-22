<?php
// admin/includes/header.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " &lsaquo; " : ""; ?>YosshitaNeha Fashion Studio Admin</title>
    
    <!-- FontAwesome for Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Admin Panel CSS -->
    <link rel="stylesheet" href="assets/css/admin-style.css">
</head>
<body>
    <div id="wpwrap">
        
        <!-- WordPress Top Admin Bar -->
        <header id="wpadminbar">
            <button id="mobile-menu-toggle" class="mobile-menu-toggle">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="adminbar-brand">
                <a href="index.php" style="color: #fff; display: flex; align-items: center;">
                    <i class="fa-solid fa-gem" style="color: #ffb900; margin-right: 8px;"></i>
                    <strong>YosshitaNeha Fashion Studio</strong>
                </a>
            </div>

            <!-- Header Product Search Bar -->
            <div class="adminbar-search-container">
                <form action="products.php" method="GET" class="adminbar-search-form" id="header_search_form">
                    <div class="adminbar-search-input-wrap">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" name="s" id="header_search_input" placeholder="Search product by SKU, Name or ID..." autocomplete="off" value="<?php echo isset($_GET['s']) ? htmlspecialchars($_GET['s']) : ''; ?>">
                        <button type="button" id="header_search_clear" class="search-clear-btn" style="display: none;" onclick="clearHeaderSearch()">&times;</button>
                    </div>
                    <button type="submit" class="adminbar-search-btn"><i class="fa-solid fa-magnifying-glass"></i> <span class="btn-text">Search</span></button>
                </form>
                
                <!-- Auto-complete Live Results Dropdown -->
                <div id="header_search_results" class="header-search-results-dropdown" style="display: none;"></div>
            </div>
            
            <div class="adminbar-user">
                <span style="color: #c3c4c7;">
                    Howdy, <strong><?php echo sanitize_html($_SESSION['admin_name']); ?></strong>
                </span>
                <a href="logout.php">
                    <i class="fa-solid fa-right-from-bracket"></i> Log Out
                </a>
            </div>
        </header>

        <script>
        let headerSearchTimer = null;

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('header_search_input');
            const clearBtn = document.getElementById('header_search_clear');
            const resultsDropdown = document.getElementById('header_search_results');

            if (!searchInput) return;

            function toggleClearBtn() {
                if (clearBtn) {
                    clearBtn.style.display = searchInput.value.trim().length > 0 ? 'block' : 'none';
                }
            }
            toggleClearBtn();

            searchInput.addEventListener('input', function() {
                toggleClearBtn();
                const query = this.value.trim();

                if (headerSearchTimer) clearTimeout(headerSearchTimer);

                if (query.length < 2) {
                    resultsDropdown.style.display = 'none';
                    return;
                }

                headerSearchTimer = setTimeout(() => {
                    fetch(`api/products.php?search=${encodeURIComponent(query)}&limit=6`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.data && data.data.length > 0) {
                                let html = '';
                                data.data.forEach(p => {
                                    const mainImg = p.main_image ? (p.main_image.startsWith('http') ? p.main_image : p.main_image) : 'https://placehold.co/80x100/1A1A1A/D4AF37?text=No+Img';
                                    const price = '₹' + Number(p.sale_price || p.price).toLocaleString('en-IN');
                                    html += `
                                        <a href="product-edit.php?id=${p.id}" class="header-search-item">
                                            <img src="${mainImg}" alt="">
                                            <div class="header-search-info">
                                                <div class="header-search-title">${p.name}</div>
                                                <div class="header-search-meta">
                                                    <span class="header-search-sku">${p.sku || 'N/A'}</span>
                                                    <span class="header-search-price">${price}</span>
                                                </div>
                                            </div>
                                        </a>
                                    `;
                                });
                                html += `
                                    <a href="products.php?s=${encodeURIComponent(query)}" style="display: block; text-align: center; padding: 10px; font-size: 12px; font-weight: 600; color: #c8a55c; background: #14181b; text-decoration: none; border-top: 1px solid #2c3338;">
                                        View all matching products &rarr;
                                    </a>
                                `;
                                resultsDropdown.innerHTML = html;
                                resultsDropdown.style.display = 'block';
                            } else {
                                resultsDropdown.innerHTML = '<div class="header-search-no-results"><i class="fa-solid fa-magnifying-glass" style="margin-right: 6px;"></i> No products found matching "' + query + '"</div>';
                                resultsDropdown.style.display = 'block';
                            }
                        })
                        .catch(() => {
                            resultsDropdown.style.display = 'none';
                        });
                }, 250);
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.adminbar-search-container')) {
                    if (resultsDropdown) resultsDropdown.style.display = 'none';
                }
            });

            searchInput.addEventListener('focus', function() {
                if (this.value.trim().length >= 2 && resultsDropdown.innerHTML.trim() !== '') {
                    resultsDropdown.style.display = 'block';
                }
            });
        });

        function clearHeaderSearch() {
            const input = document.getElementById('header_search_input');
            const clearBtn = document.getElementById('header_search_clear');
            const results = document.getElementById('header_search_results');
            if (input) input.value = '';
            if (clearBtn) clearBtn.style.display = 'none';
            if (results) results.style.display = 'none';
        }
        </script>

        <div class="admin-main-container">
