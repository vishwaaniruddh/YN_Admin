/**
 * ShadCN UI Component Kit for YosshitaNeha Admin
 * Provides Sonner-style Toasts and Modal Alert Dialogs
 */
(function() {
    'use strict';

    // =========================================================================
    // 1. TOAST SYSTEM (Sonner style)
    // =========================================================================
    let toastContainer = null;

    function getToastContainer() {
        if (!toastContainer || !document.body.contains(toastContainer)) {
            toastContainer = document.getElementById('shadcn-toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'shadcn-toast-container';
                document.body.appendChild(toastContainer);
            }
        }
        return toastContainer;
    }

    const ICONS = {
        success: 'fa-solid fa-circle-check',
        error: 'fa-solid fa-circle-exclamation',
        warning: 'fa-solid fa-triangle-exclamation',
        info: 'fa-solid fa-circle-info'
    };

    function showToast(title, description, type, duration) {
        if (!type) type = 'info';
        if (!duration) duration = type === 'error' ? 5000 : 4000;
        if (description === undefined || description === null) description = '';

        const container = getToastContainer();
        const toastEl = document.createElement('div');
        toastEl.className = 'shadcn-toast';

        const iconClass = ICONS[type] || ICONS.info;

        toastEl.innerHTML = `
            <div class="shadcn-toast-icon ${type}">
                <i class="${iconClass}"></i>
            </div>
            <div class="shadcn-toast-body">
                <p class="shadcn-toast-title">${title || ''}</p>
                ${description ? `<p class="shadcn-toast-description">${description}</p>` : ''}
            </div>
            <button type="button" class="shadcn-toast-close" aria-label="Close notification">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

        const closeBtn = toastEl.querySelector('.shadcn-toast-close');
        let dismissTimer;

        const dismiss = () => {
            clearTimeout(dismissTimer);
            toastEl.classList.remove('show');
            toastEl.classList.add('hide');
            setTimeout(() => {
                if (toastEl.parentElement) {
                    toastEl.parentElement.removeChild(toastEl);
                }
            }, 220);
        };

        if (closeBtn) closeBtn.addEventListener('click', dismiss);

        // Pause on hover
        toastEl.addEventListener('mouseenter', () => clearTimeout(dismissTimer));
        toastEl.addEventListener('mouseleave', () => {
            dismissTimer = setTimeout(dismiss, 1500);
        });

        container.appendChild(toastEl);

        // Trigger smooth entrance animation
        requestAnimationFrame(() => {
            toastEl.classList.add('show');
        });

        dismissTimer = setTimeout(dismiss, duration);
        return toastEl;
    }

    const toast = function(title, description) {
        return showToast(title, description, 'info');
    };

    toast.success = (title, description, duration) => showToast(title, description, 'success', duration);
    toast.error = (title, description, duration) => showToast(title, description, 'error', duration);
    toast.warning = (title, description, duration) => showToast(title, description, 'warning', duration);
    toast.info = (title, description, duration) => showToast(title, description, 'info', duration);

    window.toast = toast;

    // =========================================================================
    // 2. OVERRIDE NATIVE WINDOW.ALERT WITH SHADCN TOAST
    // =========================================================================
    window.alert = function(message) {
        if (!message && message !== 0) return;
        const msgStr = String(message);
        const lower = msgStr.toLowerCase();
        
        if (lower.includes('error') || lower.includes('failed')) {
            toast.error('Notice', msgStr);
        } else if (lower.includes('warning') || lower.includes('caution') || lower.includes('danger')) {
            toast.warning('Warning', msgStr);
        } else if (lower.includes('success') || lower.includes('complete') || lower.includes('saved') || lower.includes('synced')) {
            toast.success('Success', msgStr);
        } else {
            toast.info('Notification', msgStr);
        }
    };

    // =========================================================================
    // 3. SHADCN ALERT DIALOG (MODAL CONFIRMATION)
    // =========================================================================
    let alertDialogBackdrop = null;

    function getAlertDialog() {
        if (!alertDialogBackdrop || !document.body.contains(alertDialogBackdrop)) {
            alertDialogBackdrop = document.getElementById('shadcn-alert-dialog-backdrop');
            if (!alertDialogBackdrop) {
                alertDialogBackdrop = document.createElement('div');
                alertDialogBackdrop.id = 'shadcn-alert-dialog-backdrop';
                alertDialogBackdrop.innerHTML = `
                    <div class="shadcn-alert-dialog" role="alertdialog" aria-modal="true">
                        <div class="shadcn-alert-dialog-header">
                            <h3 class="shadcn-alert-dialog-title" id="shadcn-dialog-title"></h3>
                            <p class="shadcn-alert-dialog-desc" id="shadcn-dialog-desc"></p>
                        </div>
                        <div class="shadcn-alert-dialog-footer">
                            <button type="button" class="shadcn-btn shadcn-btn-outline" id="shadcn-dialog-cancel" style="height: 36px; padding: 0 16px; font-size: 13px; font-weight: 500;">Cancel</button>
                            <button type="button" class="shadcn-btn" id="shadcn-dialog-confirm" style="height: 36px; padding: 0 16px; font-size: 13px; font-weight: 500;"></button>
                        </div>
                    </div>
                `;
                document.body.appendChild(alertDialogBackdrop);
            }
        }
        return alertDialogBackdrop;
    }

    function shadcnConfirm(options) {
        if (!options) options = {};
        if (typeof options === 'string') {
            options = { description: options };
        }

        return new Promise((resolve) => {
            const backdrop = getAlertDialog();
            const titleEl = document.getElementById('shadcn-dialog-title');
            const descEl = document.getElementById('shadcn-dialog-desc');
            const cancelBtn = document.getElementById('shadcn-dialog-cancel');
            const confirmBtn = document.getElementById('shadcn-dialog-confirm');

            const title = options.title || 'Are you absolutely sure?';
            const description = options.description || options.message || 'This action cannot be undone.';
            const confirmText = options.confirmText || (options.variant === 'destructive' ? 'Delete' : 'Continue');
            const cancelText = options.cancelText || 'Cancel';
            const variant = options.variant || 'destructive';

            titleEl.textContent = title;
            descEl.textContent = description;
            cancelBtn.textContent = cancelText;
            confirmBtn.textContent = confirmText;

            if (variant === 'destructive') {
                confirmBtn.className = 'shadcn-btn shadcn-btn-danger';
                confirmBtn.style.background = '#ef4444';
                confirmBtn.style.color = '#ffffff';
                confirmBtn.style.borderColor = '#ef4444';
            } else {
                confirmBtn.className = 'shadcn-btn shadcn-btn-primary';
                confirmBtn.style.background = '#09090b';
                confirmBtn.style.color = '#ffffff';
                confirmBtn.style.borderColor = '#09090b';
            }

            const closeDialog = (result) => {
                backdrop.classList.remove('open');
                cleanup();
                resolve(result);
            };

            const onCancel = () => closeDialog(false);
            const onConfirm = () => closeDialog(true);
            const onBackdropClick = (e) => {
                if (e.target === backdrop) closeDialog(false);
            };
            const onKeyDown = (e) => {
                if (e.key === 'Escape') closeDialog(false);
            };

            function cleanup() {
                cancelBtn.removeEventListener('click', onCancel);
                confirmBtn.removeEventListener('click', onConfirm);
                backdrop.removeEventListener('click', onBackdropClick);
                document.removeEventListener('keydown', onKeyDown);
            }

            cancelBtn.addEventListener('click', onCancel);
            confirmBtn.addEventListener('click', onConfirm);
            backdrop.addEventListener('click', onBackdropClick);
            document.addEventListener('keydown', onKeyDown);

            backdrop.classList.add('open');
            confirmBtn.focus();
        });
    }

    window.shadcnConfirm = shadcnConfirm;

    // =========================================================================
    // 4. AUTOMATIC CLICK & SUBMIT INTERCEPTION
    // =========================================================================
    function initInterceptors() {
        // Intercept a.delete-confirm or elements with delete-confirm class
        document.addEventListener('click', async function(e) {
            const deleteLink = e.target.closest('a.delete-confirm, button.delete-confirm, .js-delete-confirm');
            if (deleteLink) {
                e.preventDefault();
                e.stopImmediatePropagation();

                const name = deleteLink.getAttribute('data-name') || deleteLink.getAttribute('title') || 'this item';
                const customTitle = deleteLink.getAttribute('data-title') || 'Delete Confirmation';
                const customMsg = deleteLink.getAttribute('data-message') || `Are you sure you want to delete ${name}? This action cannot be undone.`;

                const confirmed = await shadcnConfirm({
                    title: customTitle,
                    description: customMsg,
                    confirmText: 'Delete',
                    cancelText: 'Cancel',
                    variant: 'destructive'
                });

                if (confirmed) {
                    if (deleteLink.tagName === 'A' && deleteLink.href) {
                        window.location.href = deleteLink.href;
                    } else if (deleteLink.form) {
                        deleteLink.form.submit();
                    }
                }
            }
        }, true);

        // Modernize inline onsubmit="return confirm('...')" & onclick="return confirm('...')"
        function modernizeInlineConfirms() {
            document.querySelectorAll('form[onsubmit*="confirm"], a[onclick*="confirm"], button[onclick*="confirm"]').forEach(el => {
                const isForm = el.tagName === 'FORM';
                const attrName = isForm ? 'onsubmit' : 'onclick';
                const attrVal = el.getAttribute(attrName);
                if (!attrVal || !attrVal.includes('confirm(')) return;

                const match = attrVal.match(/confirm\s*\(\s*(['"`])(.*?)\1\s*\)/);
                const message = match ? match[2] : 'Are you sure you want to proceed?';

                // Remove the native handler
                el.removeAttribute(attrName);
                el.setAttribute('data-shadcn-confirm', message);

                if (isForm) {
                    el.addEventListener('submit', async function(ev) {
                        ev.preventDefault();
                        const isDestructive = message.toLowerCase().includes('delete') || 
                                              message.toLowerCase().includes('danger') || 
                                              message.toLowerCase().includes('overwrite') || 
                                              message.toLowerCase().includes('purge');

                        const isConfirmed = await shadcnConfirm({
                            title: 'Are you sure?',
                            description: message,
                            confirmText: isDestructive ? 'Yes, Proceed' : 'Confirm',
                            cancelText: 'Cancel',
                            variant: isDestructive ? 'destructive' : 'primary'
                        });
                        if (isConfirmed) {
                            HTMLFormElement.prototype.submit.call(el);
                        }
                    });
                } else if (el.tagName === 'A') {
                    el.addEventListener('click', async function(ev) {
                        ev.preventDefault();
                        const isDestructive = message.toLowerCase().includes('delete');
                        const isConfirmed = await shadcnConfirm({
                            title: 'Are you sure?',
                            description: message,
                            confirmText: isDestructive ? 'Delete' : 'Proceed',
                            cancelText: 'Cancel',
                            variant: isDestructive ? 'destructive' : 'primary'
                        });
                        if (isConfirmed && el.href) {
                            window.location.href = el.href;
                        }
                    });
                }
            });
        }

        modernizeInlineConfirms();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInterceptors);
    } else {
        initInterceptors();
    }

    // 5. Automatic image fallback to production server (https://yosshitaneha.com/admin/)
    window.addEventListener('error', function(e) {
        if (e.target && e.target.tagName === 'IMG') {
            const img = e.target;
            if (img.dataset.fallbackTried) return;

            const currentSrc = img.src || '';
            const baseUrl = 'https://yosshitaneha.com/admin/';

            if (currentSrc.includes('uploads/products/') && !currentSrc.startsWith(baseUrl)) {
                const idx = currentSrc.indexOf('uploads/products/');
                if (idx !== -1) {
                    img.dataset.fallbackTried = 'true';
                    img.src = baseUrl + currentSrc.substring(idx);
                }
            } else if (currentSrc.includes('uploads/') && !currentSrc.startsWith(baseUrl)) {
                const idx = currentSrc.indexOf('uploads/');
                if (idx !== -1) {
                    img.dataset.fallbackTried = 'true';
                    img.src = baseUrl + currentSrc.substring(idx);
                }
            }
        }
    }, true);
})();
