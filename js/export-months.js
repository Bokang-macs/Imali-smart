// Asks "how many months?" before any report export is downloaded, then exports exactly that period.
(() => {
  const SELECTOR = 'a[href*="api/reports/download.php"]';
  const DATED = ['statement', 'transactions', 'income', 'expenses'];
  const MAX_MONTHS = 60;
  const monthName = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  // "Today" is South African time, the same clock the server uses, so the preview always matches the file that downloads.
  const saNow = () => {
    try {
      const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
      const get = (type) => Number(parts.find((p) => p.type === type).value);
      return new Date(get('year'), get('month') - 1, get('day'));
    } catch (_) { return new Date(); }
  };
  const rangeFor = (n) => {
    const now = saNow();
    const start = new Date(now.getFullYear(), now.getMonth() - (n - 1), 1);
    return `${start.getDate()} ${monthName[start.getMonth()]} ${start.getFullYear()} to ${now.getDate()} ${monthName[now.getMonth()]} ${now.getFullYear()}`;
  };

  let overlay, input, preview, pending;
  const build = () => {
    overlay = document.createElement('div');
    // Visibility is controlled with style.display (see open/close below): the `hidden` attribute cannot hide an element
    // whose inline style says display:flex, which is why the box used to stay on screen after Cancel / Download.
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:16px';
    overlay.innerHTML = `
      <form role="dialog" aria-modal="true" aria-labelledby="export-months-title" style="background:var(--panel,#fff);color:var(--ink,#172033);border-radius:14px;max-width:420px;width:100%;padding:22px;box-shadow:0 20px 50px rgba(0,0,0,.3)">
        <h2 id="export-months-title" style="margin:0 0 6px">How many months?</h2>
        <p class="subtle" style="margin:0 0 14px">Choose how many months of records to include in this export, counting back from today.</p>
        <div data-chips style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px"></div>
        <label class="form-field"><span>Number of months (1–${MAX_MONTHS})</span><input type="number" min="1" max="${MAX_MONTHS}" step="1" value="1" required></label>
        <p data-preview class="subtle" style="margin:10px 0 16px"></p>
        <div style="display:flex;gap:10px;justify-content:flex-end"><button type="button" class="btn btn--quiet" data-cancel>Cancel</button><button type="submit" class="btn btn--primary">Download</button></div>
      </form>`;
    document.body.appendChild(overlay);
    input = overlay.querySelector('input');
    preview = overlay.querySelector('[data-preview]');
    const chips = overlay.querySelector('[data-chips]');
    [1, 3, 6, 12].forEach((n) => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'btn btn--quiet btn--small'; b.textContent = n === 1 ? '1 month' : `${n} months`;
      b.addEventListener('click', () => { input.value = n; update(); });
      chips.appendChild(b);
    });
    input.addEventListener('input', update);
    overlay.querySelector('[data-cancel]').addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && isOpen()) close(); });
    overlay.querySelector('form').addEventListener('submit', (e) => {
      e.preventDefault();
      const n = Math.floor(Number(input.value));
      if (!(n >= 1 && n <= MAX_MONTHS)) { input.focus(); return; }
      const url = new URL(pending.href, location.href);
      url.searchParams.delete('from'); url.searchParams.delete('to');
      url.searchParams.set('months', String(n));
      const target = pending.getAttribute('target');
      close(); // one click closes the box straight away, then the download starts
      if (target === '_blank') {
        const a = document.createElement('a');
        a.href = url.toString();
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.style.display = 'none';
        a.dataset.exportGo = '1';
        document.body.appendChild(a);
        a.click();
        a.remove();
        return;
      }
      const a = document.createElement('a'); // a temporary link downloads the file without leaving or reloading this page
      a.href = url.toString(); a.download = ''; a.style.display = 'none'; a.dataset.exportGo = '1';
      document.body.appendChild(a); a.click(); a.remove();
    });
  };
  const update = () => {
    const n = Math.floor(Number(input.value));
    preview.textContent = n >= 1 && n <= MAX_MONTHS ? `Period: ${rangeFor(n)}` : `Enter a whole number from 1 to ${MAX_MONTHS}.`;
  };
  const isOpen = () => !!overlay && overlay.style.display !== 'none';
  const close = () => { if (overlay) overlay.style.display = 'none'; pending = null; };

  document.addEventListener('click', (e) => {
    const link = e.target.closest(SELECTOR);
    if (!link || link.dataset.exportGo) return; // the temporary download link must not reopen the box
    const report = new URL(link.href, location.href).searchParams.get('report') || 'statement';
    if (!DATED.includes(report)) return; // budgets and savings reports are not date-based
    e.preventDefault();
    if (!overlay) build();
    pending = link; input.value = 1; update(); overlay.style.display = 'flex'; input.focus(); input.select();
  });
})();
