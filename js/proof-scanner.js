(() => {
  const form = document.querySelector('[data-proof-form]');
  if (!form) return;
  const status = document.querySelector('[data-proof-status]');
  const results = document.querySelector('[data-proof-results]');
  const button = form.querySelector('button[type="submit"]');
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const labels = {document_type:'Document type', account_holder:'Account holder', payer_name:'Payer name', recipient_name:'Recipient name', amount:'Amount', currency:'Currency', payment_date:'Payment date', reference:'Reference', bank_or_provider:'Bank / provider', description:'Description', confidence:'AI confidence', warnings:'Warnings'};
  // Today in South African time as YYYY-MM-DD. toISOString() gives the UTC date, which is still "yesterday" until 02:00 here.
  const saToday = () => {
    try { return new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date()); }
    catch (_) { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  };
  const money = (n) => 'R ' + Number(n || 0).toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  const showAlert = (data) => {
    const sum = data.summary || {};
    const links = data.links || {};
    const inC = Number(sum.income_count || 0), exC = Number(sum.expense_count || 0);
    const rows = Array.isArray(data.rows) ? data.rows : [];
    const hasTarget = !!links.transaction;
    const ok = Number(data.imported || 0) > 0;
    const lines = [];
    if (inC) lines.push(`<div style="padding:10px 12px;border-radius:10px;background:#dcfce7;color:#166534;margin-bottom:8px"><strong>INCOME recorded:</strong> ${inC} transaction${inC === 1 ? '' : 's'} · +${money(sum.income_total)}<br><span style="font-size:13px">Added to your Income records.</span></div>`);
    if (exC) lines.push(`<div style="padding:10px 12px;border-radius:10px;background:#fee2e2;color:#991b1b;margin-bottom:8px"><strong>EXPENSE recorded:</strong> ${exC} transaction${exC === 1 ? '' : 's'} · -${money(sum.expense_total)}<br><span style="font-size:13px">Added to your Expense records.</span></div>`);
    if (!ok && hasTarget) lines.push(`<div style="padding:10px 12px;border-radius:10px;background:#fef3c7;color:#92400e;margin-bottom:8px">This document was already recorded earlier. I will show you where it is.</div>`);
    if (!ok && !hasTarget) lines.push(`<div style="padding:10px 12px;border-radius:10px;background:#fef3c7;color:#92400e">${escapeHtml(data.message || 'Nothing was recorded.')}</div>`);
    const withId = rows.filter(r => r.id);
    const freshRows = withId.some(r => r.status === 'imported') ? withId.filter(r => r.status === 'imported') : withId;
    const guessedRows = freshRows.filter(r => r.date_estimated);
    const fixRows = guessedRows.length ? guessedRows : freshRows; // never touch records that were already in the ledger
    const ids = fixRows.map(r => r.id);
    const docDates = [...new Set(fixRows.map(r => r.date))].sort();
    if (data.date_notice) lines.push(`<div style="padding:10px 12px;border-radius:10px;background:#fef3c7;color:#92400e;margin-bottom:8px;font-size:13px">${escapeHtml(data.date_notice)}</div>`);
    if (hasTarget) {
      const today = data.today || saToday();
      lines.push(`<div style="margin:8px 0 4px;padding:10px 12px;border:1px solid var(--line,#e2e8f0);border-radius:10px"><label style="display:block;font-size:13px" class="subtle">${docDates.length > 1 ? 'Dates on the document: ' + escapeHtml(docDates[0]) + ' to ' + escapeHtml(docDates[docDates.length - 1]) : (guessedRows.length ? 'No readable date on the document: recorded under this date' : 'Date on the document (recorded under this date)')}</label>${docDates.length === 1 ? `<div style="display:flex;gap:8px;align-items:center;margin-top:6px;flex-wrap:wrap"><input type="date" data-date value="${escapeHtml(docDates[0])}" max="${today}" style="padding:6px 8px;border-radius:8px;border:1px solid var(--line,#cbd5e1)"><button type="button" class="btn btn--quiet btn--small" data-save-date hidden>Save new date</button><span data-date-note class="subtle" style="font-size:13px"></span></div>` : ''}<p class="subtle" style="font-size:12px;margin:6px 0 0">Records are placed by this date. Wrong year on the receipt? Change it here and the record moves to the right place.</p></div>`);
    }
    const box = document.createElement('div');
    box.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;padding:16px';
    const split = (links.income_view || links.expense_view) ? `<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">${links.income_view ? `<a class="btn btn--quiet btn--small" data-split="income_view" href="${escapeHtml(links.income_view)}">View income</a>` : ''}${links.expense_view ? `<a class="btn btn--quiet btn--small" data-split="expense_view" href="${escapeHtml(links.expense_view)}">View expenses</a>` : ''}</div>` : '';
    const nav = hasTarget ? split + `<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"><a class="btn btn--quiet btn--small" href="${escapeHtml(links.dashboard)}">Dashboard</a><a class="btn btn--quiet btn--small" href="${escapeHtml(links.cashflow)}">Cash flow</a><a class="btn btn--quiet btn--small" href="${escapeHtml(links.analytics)}">Analytics</a><a class="btn btn--quiet btn--small" href="${escapeHtml(links.reports)}">Reports</a></div>` : '';
    box.innerHTML = `<div role="alertdialog" aria-modal="true" style="background:var(--panel,#fff);color:var(--ink,#172033);border-radius:14px;max-width:440px;width:100%;padding:22px;box-shadow:0 20px 50px rgba(0,0,0,.3)"><h2 style="margin:0 0 12px">${ok ? 'Scan successful' : 'Scan finished'}</h2>${lines.join('')}${nav}<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;align-items:center">${hasTarget ? '<span class="subtle" data-count style="margin-right:auto;font-size:13px"></span><a class="btn btn--primary" data-go href="' + escapeHtml(links.transaction) + '">Open transaction now</a>' : ''}<button type="button" class="btn btn--quiet" data-close>Close</button></div></div>`;
    let timer = null;
    const stop = () => { if (timer) { clearInterval(timer); timer = null; } };
    box.addEventListener('click', (e) => { if (e.target === box || e.target.closest('[data-close]')) { stop(); box.remove(); } });
    document.body.appendChild(box);
    if (hasTarget) {
      const dateInput = box.querySelector('[data-date]');
      const saveBtn = box.querySelector('[data-save-date]');
      const note = box.querySelector('[data-date-note]');
      if (dateInput) {
        const original = dateInput.value;
        dateInput.addEventListener('focus', stop);
        dateInput.addEventListener('input', () => { stop(); saveBtn.hidden = dateInput.value === original; const c = box.querySelector('[data-count]'); if (c) c.textContent = ''; });
        saveBtn.addEventListener('click', async () => {
          saveBtn.disabled = true; note.textContent = 'Saving…';
          try {
            const fd = new FormData(); fd.append('csrf_token', form.csrf_token.value); fd.append('ids', ids.join(',')); fd.append('date', dateInput.value);
            const res = await fetch('api/transactions/set-date.php', {method: 'POST', body: fd, credentials: 'same-origin'});
            const out = await res.json();
            if (!res.ok || !out.ok) throw new Error(out.error || 'The date could not be changed.');
            const swap = (u) => { const url = new URL(u, location.href); url.searchParams.set('from', out.period.from); url.searchParams.set('to', out.period.to); return url.pathname.split('/').pop() + url.search; };
            ['transaction', 'income_view', 'expense_view', 'dashboard', 'cashflow', 'analytics', 'reports'].forEach(k => { if (links[k]) links[k] = swap(links[k]); });
            box.querySelectorAll('[data-split]').forEach(a => { a.href = links[a.dataset.split]; });
            box.querySelector('[data-go]').href = links.transaction;
            box.querySelectorAll('a.btn--quiet:not([data-split])').forEach((a, i) => { a.href = links[['dashboard', 'cashflow', 'analytics', 'reports'][i]]; });
            note.textContent = 'Date updated. Opening…'; saveBtn.hidden = true;
            setTimeout(() => { window.location.href = links.transaction; }, 700);
          } catch (err) { note.textContent = err.message; saveBtn.disabled = false; }
        });
      }
      let left = 4;
      const label = box.querySelector('[data-count]');
      const tick = () => { label.textContent = `Opening in ${left}s…`; if (left-- <= 0) { stop(); window.location.href = links.transaction; } };
      tick(); timer = setInterval(tick, 1000);
    }
  };
  const renderResult = (data) => {
    const fields = data.fields || {};
    const valid = data.valid_document === true;
    const imported = Number(data.imported || 0);
    const skipped = Number(data.skipped || 0);
    const summary = Object.keys(labels).filter(key => fields[key] !== null && fields[key] !== undefined && fields[key] !== '').map(key => `<div style="display:flex;justify-content:space-between;gap:16px;padding:8px 0;border-bottom:1px solid var(--line)"><span class="subtle">${labels[key]}</span><strong style="text-align:right;max-width:60%">${escapeHtml(Array.isArray(fields[key]) ? fields[key].join(', ') : fields[key])}</strong></div>`).join('');
    const importedRows = Array.isArray(data.rows) ? data.rows : [];
    const rows = importedRows.map(row => `<div style="display:grid;grid-template-columns:1fr auto;gap:10px;padding:10px;border-bottom:1px solid var(--line);align-items:start"><span><strong><span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:12px;margin-right:6px;color:#fff;background:${row.type === 'income' ? '#16a34a' : '#dc2626'}">${row.type === 'income' ? 'INCOME' : 'EXPENSE'}</span>${escapeHtml(row.date || '')} · ${row.type === 'income' ? '+' : '-'}R ${escapeHtml(Number(row.amount || 0).toFixed(2))}</strong><br><span class="subtle">${escapeHtml(row.description || 'No description')}${row.reference ? ' · ' + escapeHtml(row.reference) : ''}</span></span><span class="badge ${row.status === 'imported' ? 'badge--success' : 'badge--warning'}">${row.status === 'imported' ? 'Imported' : 'Already in ledger'}</span></div>`).join('');
    const unavailable = data.scan_status === 'scan_unavailable';
    const colour = (!valid || unavailable) ? '#dc2626' : '#16a34a';
    const heading = unavailable ? 'Scan unavailable' : !valid ? 'Invalid document' : (imported > 0 ? `Scan successful · ${imported} transaction${imported === 1 ? '' : 's'} imported` : 'Valid financial document detected');
    const note = unavailable ? escapeHtml(data.message || 'The AI scan could not run.') : !valid ? 'Gemini could not confirm that this is a readable bank statement or proof of payment. Nothing was imported.' : escapeHtml(data.message || '');
    results.innerHTML = `<div class="callout" style="border-left:4px solid ${colour}"><strong style="color:${colour}">${heading}</strong><p style="margin:5px 0 10px">${note}</p>${data.date_notice ? `<p style="margin:0 0 10px;padding:8px 10px;border-radius:8px;background:#fef3c7;color:#92400e;font-size:13px">${escapeHtml(data.date_notice)}</p>` : ''}${summary || '<span class="subtle">No readable document details were found.</span>'}${rows ? `<h3 style="margin:18px 0 4px">Transactions recorded from this document</h3><p class="subtle">${imported} added${skipped ? `, ${skipped} skipped as duplicates` : ''}. Analytics and graphs now include them.</p><div style="border:1px solid var(--line);border-radius:10px;overflow:hidden">${rows}</div>` : ''}${(data.links && data.links.transaction) ? '<a class="btn btn--primary" href="' + escapeHtml(data.links.transaction) + '" style="margin-top:12px;display:inline-flex">Open this transaction →</a>' : ''}</div>`;
    results.hidden = false;
  };
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.proof.files.length) return;
    button.disabled = true; button.textContent = 'Scanning…'; status.textContent = 'Uploading securely and asking Gemini to read the document and import its transactions…'; results.hidden = true; results.innerHTML = '';
    try {
      const response = await fetch('api/proofs/upload.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
      const rawText = await response.text();
      let data;
      try {
        data = JSON.parse(rawText);
      } catch (parseErr) {
        throw new Error('Server returned an unexpected response. Please try again.');
      }
      if (!response.ok || !data.ok) throw new Error(data.error || 'The document could not be scanned.');
      status.textContent = data.message || 'Scan completed.';
      renderResult(data);
      if (data.valid_document && (Number(data.imported || 0) > 0 || (data.links && data.links.transaction))) showAlert(data);
    } catch (error) {
      status.textContent = error.message || 'The upload failed. Please try again.';
    } finally {
      button.disabled = false;
      button.textContent = 'Scan document';
    }
  });
})();
