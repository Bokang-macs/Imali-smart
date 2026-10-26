/**
 * Imali Smart - Settings: language picker.
 * Picking a card switches the whole app straight away. If the form has unsaved edits,
 * the choice waits for the Save button so nothing the user typed is lost.
 */
(() => {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('language');
    if (!panel) return;
    const form = panel.closest('form');
    const cards = [...panel.querySelectorAll('[data-lang-card]')];
    const note = document.getElementById('lang-dirty-note');
    let dirty = false;

    if (form) {
      form.addEventListener('input', (e) => { if (!panel.contains(e.target)) dirty = true; });
      form.addEventListener('change', (e) => { if (!panel.contains(e.target)) dirty = true; });
    }

    panel.addEventListener('change', (e) => {
      const input = e.target.closest('input[name="preferred_language"]');
      if (!input) return;
      cards.forEach(c => c.classList.toggle('is-selected', c.contains(input)));
      const badge = document.getElementById('lang-current-badge');
      const label = input.closest('[data-lang-card]').querySelector('strong');
      if (badge && label) badge.textContent = label.textContent;
      const now = document.getElementById('lang-now-name'); if (now && label) now.textContent = label.textContent;
      if (dirty) { if (note) note.hidden = false; return; }
      if (window.ImaliTheme && window.ImaliTheme.toast) window.ImaliTheme.toast('Switching language…');
      window.location.href = input.dataset.langSwitch;
    });

    // Live search
    const search = document.getElementById('lang-search');
    const empty = document.getElementById('lang-empty');
    if (search) {
      search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        cards.forEach(c => { const hit = !q || c.dataset.search.includes(q); c.hidden = !hit; if (hit) shown++; });
        panel.querySelectorAll('[data-lang-group]').forEach(g => { g.hidden = !g.querySelector('[data-lang-card]:not([hidden])'); });
        if (empty) empty.hidden = shown !== 0;
      });
      search.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
    }

    // Open straight at the language section when linked with #language.
    if (location.hash === '#language') panel.scrollIntoView({ block: 'start' });
  });
})();
