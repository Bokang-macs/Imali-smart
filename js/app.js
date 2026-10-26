/**
 * Imali Smart - Pure Vanilla JS Core Application
 * Combines navigation, accessible drawer, draft auto-saving, theme controls, and service worker.
 */
(() => {
    'use strict';

    const q = (sel, root = document) => root.querySelector(sel);
    const qa = (sel, root = document) => [...root.querySelectorAll(sel)];

    // Mobile Navigation Drawer (signed-in pages)
    const sidebar = q('[data-sidebar]');
    const scrim = q('.sidebar-scrim');
    const drawerButtons = qa('.mobile-menu-button');
    const desktopQuery = window.matchMedia('(min-width: 821px)');

    const setDrawer = (open, returnFocus = false) => {
        if (!sidebar) return;
        sidebar.classList.toggle('is-open', open);
        scrim?.classList.toggle('is-visible', open);
        document.body.classList.toggle('menu-open', open);
        drawerButtons.forEach(btn => {
            btn.setAttribute('aria-expanded', String(open));
            btn.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        });
        if (open) q('.sidebar-close', sidebar)?.focus();
        else if (returnFocus) drawerButtons[0]?.focus();
    };

    // The close button and the dark backdrop both carry data-mobile-menu too, so one listener covers all three.
    qa('[data-mobile-menu]').forEach(btn => {
        btn.addEventListener('click', () => setDrawer(!sidebar?.classList.contains('is-open'), true));
    });
    qa('.sidebar a').forEach(link => link.addEventListener('click', () => setDrawer(false)));

    // Public menu (signed-out pages and the landing page)
    const publicButton = q('[data-public-menu]');
    const publicNav = q('[data-public-nav]');
    const setPublicMenu = (open, returnFocus = false) => {
        if (!publicButton || !publicNav) return;
        publicNav.classList.toggle('is-open', open);
        publicButton.setAttribute('aria-expanded', String(open));
        publicButton.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        if (!open && returnFocus) publicButton.focus();
    };
    publicButton?.addEventListener('click', (e) => {
        e.stopPropagation();
        setPublicMenu(!publicNav.classList.contains('is-open'));
    });
    publicNav?.addEventListener('click', (e) => {
        // Close after choosing a link (but not when using the language list or theme button).
        if (e.target.closest('a')) setPublicMenu(false);
    });
    document.addEventListener('click', (e) => {
        if (publicNav?.classList.contains('is-open') && !e.target.closest('[data-public-nav], [data-public-menu]')) {
            setPublicMenu(false);
        }
    });

    // Escape closes whichever menu is open; growing the window past the breakpoint resets them.
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (sidebar?.classList.contains('is-open')) setDrawer(false, true);
        if (publicNav?.classList.contains('is-open')) setPublicMenu(false, true);
    });
    const resetMenus = () => {
        if (window.innerWidth > 1100) setPublicMenu(false);
        if (desktopQuery.matches) setDrawer(false);
    };
    window.addEventListener('resize', resetMenus);
    window.addEventListener('orientationchange', resetMenus);

    // Confirmation Prompts
    qa('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            const message = el.dataset.confirm || 'Are you sure you want to perform this action?';
            if (!window.confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Auto-dismiss Alerts
    qa('[data-auto-dismiss]').forEach(alert => {
        window.setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-4px)';
            window.setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Form Client Validation Hook
    qa('form[data-validate]').forEach(form => {
        form.addEventListener('submit', (e) => {
            if (!form.checkValidity()) {
                e.preventDefault();
                form.reportValidity();
            }
        });
    });

    // Stop double submissions on forms marked data-once. The button is disabled only after the browser
    // has accepted the submit, so a failed validation never leaves it stuck.
    qa('form[data-once]').forEach(form => {
        form.addEventListener('submit', (e) => {
            const btn = form.querySelector('button[type="submit"]');
            if (!btn) return;
            setTimeout(() => {
                if (e.defaultPrevented) return;
                btn.dataset.label = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'Saving…';
            }, 0);
        });
    });
    window.addEventListener('pageshow', () => {
        qa('form[data-once] button[type="submit"]').forEach(btn => {
            btn.disabled = false;
            if (btn.dataset.label) btn.textContent = btn.dataset.label;
        });
    });

    // Dark/light toggling lives in theme-and-share.js. Binding it here as well made every click flip the
    // theme twice, so the button appeared to do nothing.

    // Local Draft Auto-Saving for Transaction Forms
    if (window.crypto?.subtle) {
        const textEncoder = new TextEncoder();
        const textDecoder = new TextDecoder();
        const keyStorage = 'imali-draft-key-v1';
        const draftStorage = 'imali-transaction-draft-v1';

        const getKey = async () => {
            let raw = sessionStorage.getItem(keyStorage);
            if (!raw) {
                const key = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
                raw = btoa(String.fromCharCode(...new Uint8Array(await crypto.subtle.exportKey('raw', key))));
                sessionStorage.setItem(keyStorage, raw);
                return key;
            }
            const bytes = Uint8Array.from(atob(raw), c => c.charCodeAt(0));
            return crypto.subtle.importKey('raw', bytes, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
        };

        const encryptDraft = async (data) => {
            try {
                const key = await getKey();
                if (!key) return null;
                const iv = crypto.getRandomValues(new Uint8Array(12));
                const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, textEncoder.encode(JSON.stringify(data)));
                return JSON.stringify({
                    iv: btoa(String.fromCharCode(...iv)),
                    data: btoa(String.fromCharCode(...new Uint8Array(cipher))),
                    savedAt: new Date().toISOString()
                });
            } catch {
                return null;
            }
        };

        const decryptDraft = async (payload) => {
            try {
                const key = await getKey();
                if (!key) return null;
                const parsed = JSON.parse(payload);
                const iv = Uint8Array.from(atob(parsed.iv), c => c.charCodeAt(0));
                const data = Uint8Array.from(atob(parsed.data), c => c.charCodeAt(0));
                return JSON.parse(textDecoder.decode(await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, data)));
            } catch {
                return null;
            }
        };

        qa('form').filter(form => form.querySelector('input[name="action"][value="create"]') && form.querySelector('input[name="amount"]')).forEach(form => {
            let status = document.createElement('small');
            status.className = 'subtle';
            status.textContent = 'Drafts stay encrypted in this tab until submitted.';
            form.prepend(status);

            const save = async () => {
                const fields = {};
                form.querySelectorAll('input,select,textarea').forEach(input => {
                    if (input.name && input.name !== 'csrf_token') fields[input.name] = input.value;
                });
                const payload = await encryptDraft(fields);
                if (payload) {
                    localStorage.setItem(draftStorage, payload);
                    status.textContent = 'Local draft saved.';
                }
            };

            let timer;
            form.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(save, 700);
            });
            form.addEventListener('submit', () => localStorage.removeItem(draftStorage));

            decryptDraft(localStorage.getItem(draftStorage) || '').then(draft => {
                if (!draft) return;
                Object.entries(draft).forEach(([name, value]) => {
                    const input = form.elements[name];
                    if (input && input.value === '') input.value = value;
                });
                status.textContent = 'Encrypted local draft restored.';
            });
        });
    }

    // Register Service Worker
    if ('serviceWorker' in navigator && document.body.dataset.disableSw !== 'true') {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('service-worker.js').catch(() => {});
        });
    }

    // Flash Toast Helper
    window.showToast = (message, type = 'info') => {
        let toast = q('.global-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'global-toast';
            toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:10px;font-weight:600;font-size:14px;box-shadow:0 10px 25px rgba(0,0,0,0.3);background:#18221f;color:#fff;border:1px solid rgba(255,255,255,0.1);transition:all 0.3s ease;';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
        }, 3500);
    };

    window.Imali = { q, qa };
})();
