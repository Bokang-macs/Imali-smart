/* Text size and high-contrast controls. Saved on this device only. */
(function () {
  'use strict';
  var root = document.documentElement;
  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  function apply() {
    var size = get('imali_text_v1') || 'md', contrast = get('imali_contrast_v1') === 'high';
    if (size === 'md') root.removeAttribute('data-text'); else root.setAttribute('data-text', size);
    if (contrast) root.setAttribute('data-contrast', 'high'); else root.removeAttribute('data-contrast');
    document.querySelectorAll('[data-a11y-text]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-a11y-text') === size)); });
    document.querySelectorAll('[data-a11y-contrast]').forEach(function (b) { b.setAttribute('aria-pressed', String(contrast)); });
  }

  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-a11y-text]'); if (t) { set('imali_text_v1', t.getAttribute('data-a11y-text')); apply(); return; }
    var c = ev.target.closest('[data-a11y-contrast]'); if (c) { set('imali_contrast_v1', get('imali_contrast_v1') === 'high' ? 'normal' : 'high'); apply(); }
  });
  document.addEventListener('DOMContentLoaded', apply);
  apply();
})();
