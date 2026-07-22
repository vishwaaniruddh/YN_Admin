// admin/assets/js/admin.js

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Main image preview when a file is selected
    const mainImageInput = document.getElementById('main_image_input');
    const mainImagePreview = document.getElementById('main_image_preview');
    
    if (mainImageInput && mainImagePreview) {
        mainImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    mainImagePreview.src = evt.target.result;
                    mainImagePreview.style.display = 'block';
                    
                    // Remove "No image chosen" placeholder text if any
                    const placeholder = document.getElementById('main_image_placeholder');
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // 1b. Category image preview
    const catImageInput = document.getElementById('cat_image');
    const catImagePreview = document.getElementById('cat_image_preview');
    
    if (catImageInput && catImagePreview) {
        catImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    catImagePreview.src = evt.target.result;
                    catImagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // 2. Gallery images preview when multiple files are selected
    const galleryInput = document.getElementById('gallery_input');
    const galleryPreviewGrid = document.getElementById('gallery_preview_grid');
    
    if (galleryInput && galleryPreviewGrid) {
        galleryInput.addEventListener('change', function(e) {
            // Keep original files or clear? Usually browser multiple files input replaces list.
            // Let's clear preview grid of any dynamically added preview items first.
            const tempPreviews = galleryPreviewGrid.querySelectorAll('.temp-preview');
            tempPreviews.forEach(item => item.remove());

            const files = e.target.files;
            if (files && files.length > 0) {
                Array.from(files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'gallery-item temp-preview';
                        
                        const img = document.createElement('img');
                        img.src = evt.target.result;
                        img.alt = 'Preview';
                        
                        itemDiv.appendChild(img);
                        galleryPreviewGrid.appendChild(itemDiv);
                    };
                    reader.readAsDataURL(file);
                });
            }
        });
    }

    // 3. Featured star toggle via AJAX in products list
    const featuredToggles = document.querySelectorAll('.star-icon.ajax-toggle');
    featuredToggles.forEach(star => {
        star.addEventListener('click', function(e) {
            const productId = this.getAttribute('data-product-id');
            const isCurrentlyFeatured = this.classList.contains('featured') ? 1 : 0;
            const newFeaturedStatus = isCurrentlyFeatured ? 0 : 1;
            
            // Set temporary opacity/loading style
            this.style.opacity = '0.5';
            
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('is_featured', newFeaturedStatus);
            formData.append('action', 'toggle_featured');

            fetch('products.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                this.style.opacity = '1';
                if (data.success) {
                    if (newFeaturedStatus === 1) {
                        this.classList.remove('not-featured');
                        this.classList.add('featured');
                        this.innerHTML = '&#9733;'; // filled star
                    } else {
                        this.classList.remove('featured');
                        this.classList.add('not-featured');
                        this.innerHTML = '&#9734;'; // empty star
                    }
                } else {
                    alert('Error updating featured status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                this.style.opacity = '1';
                console.error('Error:', error);
                alert('Connection error. Could not update featured status.');
            });
        });
    });

    // 4. Auto-hide notice messages after 5 seconds
    const notices = document.querySelectorAll('.notice.auto-dismiss');
    notices.forEach(notice => {
        setTimeout(() => {
            notice.style.transition = 'opacity 0.5s ease-out';
            notice.style.opacity = '0';
            setTimeout(() => notice.remove(), 500);
        }, 5000);
    });

    // 5. Delete category/product verification link checks
    const deleteLinks = document.querySelectorAll('a.delete-confirm');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const name = this.getAttribute('data-name') || 'this item';
            if (!confirm('Are you sure you want to delete ' + name + '? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // 6. Sidebar Submenu Toggle (Accordion behavior)
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parentLi = this.parentElement;
            
            // Accordion behavior: close all other open menu items at the same level
            const siblingItems = parentLi.parentElement.querySelectorAll('.menu-item.has-submenu');
            siblingItems.forEach(sibling => {
                if (sibling !== parentLi) {
                    sibling.classList.remove('open');
                }
            });
            
            // Toggle the clicked menu item
            parentLi.classList.toggle('open');
        });
    });

    // 7. Mobile Sidebar Drawer Controls
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const adminMenuWrap = document.getElementById('adminmenuwrap');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');

    if (mobileMenuToggle && adminMenuWrap && sidebarBackdrop) {
        // Toggle mobile drawer on button click
        mobileMenuToggle.addEventListener('click', function() {
            adminMenuWrap.classList.toggle('mobile-open');
            sidebarBackdrop.classList.toggle('active');
        });

        // Close mobile drawer when clicking the backdrop overlay
        sidebarBackdrop.addEventListener('click', function() {
            adminMenuWrap.classList.remove('mobile-open');
            sidebarBackdrop.classList.remove('active');
        });
    }

    // 8. Auto-generate Slug from Name
    const slugInputs = [
        { nameId: 'cat_name', slugId: 'cat_slug' },
        { nameId: 'p_name', slugId: 'p_slug' }
    ];

    slugInputs.forEach(pair => {
        const nameInput = document.getElementById(pair.nameId);
        const slugInput = document.getElementById(pair.slugId);
        
        if (nameInput && slugInput) {
            let slugManuallyChanged = slugInput.value.trim() !== '';
            
            slugInput.addEventListener('input', function() {
                slugManuallyChanged = true;
                if (this.value.trim() === '') {
                    slugManuallyChanged = false; // Reset if they clear it
                }
            });

            nameInput.addEventListener('input', function() {
                if (!slugManuallyChanged) {
                    let slug = this.value.toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '') // remove invalid chars
                        .replace(/[\s_]+/g, '-')      // replace spaces and underscores with hyphens
                        .replace(/-+/g, '-')          // collapse multiple hyphens
                        .replace(/^-+|-+$/g, '');     // trim hyphens
                    
                    slugInput.value = slug;
                }
            });
        }
    });
});
