/**
 * Imali Smart - Theme Manager & One-Click Social Sharing
 * Switches between Dark, Light and System themes right away
 * Lets the user share their profile with one click
 */
(() => {
  // Theme Manager
  const THEME_KEY = 'imali_theme_v1';
  const THEME_NAMES = { light: 'Light mode on', dark: 'Dark mode on', default: 'Auto mode: follows your device' };
  
  // Returns the saved theme, or the system theme.
  function getPreferredTheme() {
    const saved = localStorage.getItem(THEME_KEY);
    if (saved && ['dark', 'light', 'default'].includes(saved)) {
      return saved;
    }
    return 'default';
  }

  // Applies a theme to the page.
  function applyTheme(theme) {
    if (theme === 'default') {
      const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    } else {
      document.documentElement.setAttribute('data-theme', theme);
    }
    
    // Update theme toggle UI buttons if present
    document.querySelectorAll('[data-theme-btn]').forEach(btn => {
      const btnTheme = btn.dataset.themeBtn;
      const isActive = (btnTheme === theme);
      btn.classList.toggle('is-active', isActive);
      btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    // Mark the chosen mode on every theme button (header, account menu and sidebar).
    document.querySelectorAll('[data-set-theme]').forEach(btn => {
      const on = btn.dataset.setTheme === theme;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });

    // Update select element if present
    const select = document.querySelector('[data-theme-select]');
    if (select && select.value !== theme) {
      select.value = theme;
    }
  }

  // Changes the theme and saves the choice.
  function setTheme(theme) {
    localStorage.setItem(THEME_KEY, theme);
    applyTheme(theme);
    try {
      fetch('api/profile/theme.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'theme=' + encodeURIComponent(theme)
      }).catch(() => {});
    } catch (e) {}
  }

  // Initial theme application
  const currentTheme = getPreferredTheme();
  applyTheme(currentTheme);

  // Listen to system dark mode preference change
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (getPreferredTheme() === 'default') {
      applyTheme('default');
    }
  });

  // Wire up theme toggles on DOM ready
  document.addEventListener('DOMContentLoaded', () => {
    applyTheme(getPreferredTheme());

    // Account menu in the header.
    const menu = document.querySelector('[data-user-menu]');
    if (menu) {
      const toggle = menu.querySelector('[data-user-menu-toggle]');
      const panel = menu.querySelector('[data-user-menu-panel]');
      const close = () => { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); };
      toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = panel.hidden;
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      document.addEventListener('click', (e) => { if (!menu.contains(e.target)) close(); });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { close(); toggle.focus(); } });
    }

    document.querySelectorAll('[data-set-theme]').forEach(el => {
      el.addEventListener('click', (e) => {
        e.preventDefault();
        const theme = el.dataset.setTheme;
        setTheme(theme);
        showToast(THEME_NAMES[theme] || 'Theme updated');
      });
    });

    const themeSelect = document.querySelector('[data-theme-select]');
    if (themeSelect) {
      themeSelect.value = getPreferredTheme();
      themeSelect.addEventListener('change', (e) => {
        setTheme(e.target.value);
        showToast(THEME_NAMES[e.target.value] || 'Theme updated');
      });
    }

    // Quick toggle buttons (moon/sun) in topbar or navbar
    document.querySelectorAll('[data-theme-toggle]').forEach(quickToggle => {
      quickToggle.addEventListener('click', (e) => {
        e.preventDefault();
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        setTheme(next);
        showToast(THEME_NAMES[next]);
      });
    });
  });

  // Toast Notification System
  function showToast(message, duration = 3000) {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      container.setAttribute('role', 'status');
      container.setAttribute('aria-live', 'polite');
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'imali-toast';
    toast.innerHTML = `<span class="toast-icon">✓</span> <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('is-visible');
    }, 10);

    setTimeout(() => {
      toast.classList.remove('is-visible');
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }

  window.ImaliTheme = {
    get: getPreferredTheme,
    set: setTheme,
    apply: applyTheme,
    toast: showToast
  };
})();
