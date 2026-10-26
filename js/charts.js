/**
 * Imali Smart - Pure Vanilla HTML5 Canvas Chart Engine
 * Simple chart drawing with no outside libraries. Works with light and dark themes.
 */
(() => {
    'use strict';

    // Returns true if the dark theme is on.
    function isDarkTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark';
    }

    // Colours for category charts (same order as the legend on the Analytics page).
    const PALETTE = ['#f59e0b', '#16a34a', '#0284c7', '#8b5cf6', '#10b981', '#f43f5e', '#ea580c', '#64748b', '#84cc16', '#14b8a6'];

    // The chart style the person chose in Settings (bar, line or pie). Older saved values are mapped.
    function normalizeType(v) {
        v = String(v || '').toLowerCase();
        if (v === 'graph' || v === 'bars') return 'bar';
        if (v === 'trend') return 'line';
        if (v === 'share' || v === 'donut') return 'pie';
        return (v === 'bar' || v === 'line' || v === 'pie') ? v : 'bar';
    }
    function preferredType() {
        return normalizeType(document.documentElement.getAttribute('data-chart-type'));
    }

    // Short rand amounts for the axis: R 50, R 5k, R 250k, R 1.5M, R 25M, R 1.2B.
    function compactRand(v) {
        if (!isFinite(v) || isNaN(v)) return 'R 0';
        const abs = Math.abs(v);
        const sign = v < 0 ? '-' : '';
        if (abs >= 1e12) return sign + 'R ' + (abs / 1e12).toFixed(1).replace(/\.0$/, '') + 'T';
        if (abs >= 1e9)  return sign + 'R ' + (abs / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
        if (abs >= 1e6)  return sign + 'R ' + (abs / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
        if (abs >= 1e3)  return sign + 'R ' + (abs / 1e3).toFixed(1).replace(/\.0$/, '') + 'k';
        return sign + 'R ' + Math.round(abs).toLocaleString('en-ZA');
    }

    // Full exact rand amounts for tooltips and inspect states.
    function fullRand(v) {
        if (!isFinite(v) || isNaN(v)) return 'R 0.00';
        const abs = Math.abs(v);
        const sign = v < 0 ? '-' : '';
        return sign + 'R ' + abs.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Calculates a clean, human-friendly ceiling for chart Y-axes (e.g. 50, 5k, 250k, 1.5M, 25M).
    function calculateNiceMax(rawMax) {
        if (rawMax <= 0) return 100;
        const target = rawMax * 1.12;
        const exponent = Math.floor(Math.log10(target));
        const fraction = target / Math.pow(10, exponent);
        let niceFraction;
        if (fraction <= 1.0) niceFraction = 1;
        else if (fraction <= 1.5) niceFraction = 1.5;
        else if (fraction <= 2.0) niceFraction = 2;
        else if (fraction <= 2.5) niceFraction = 2.5;
        else if (fraction <= 5.0) niceFraction = 5;
        else niceFraction = 10;
        return niceFraction * Math.pow(10, exponent);
    }

    // Hover tooltip shared by the bar charts.
    function tipFor(canvas) {
        let tip = canvas.parentElement.querySelector('.chart-tip');
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'chart-tip';
            tip.style.cssText = 'position:absolute;pointer-events:none;display:none;z-index:20;padding:10px 14px;border-radius:10px;font:12px/1.55 var(--font-body, system-ui, sans-serif);background:#0b1220;color:#f1f5f9;box-shadow:0 10px 25px rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.12);white-space:nowrap;backdrop-filter:blur(8px);';
            canvas.parentElement.appendChild(tip);
        }
        return tip;
    }
    function hideTip(canvas) { const t = canvas.parentElement && canvas.parentElement.querySelector('.chart-tip'); if (t) t.style.display = 'none'; }

    // ---------------------------------------------------------------------------------------------
    // Live inspector: crosshair that follows the cursor, rich tooltip, readout strip and AI insight.
    // All figures come from the server-supplied `data.details` (real transactions), never made up here.
    // ---------------------------------------------------------------------------------------------
    function fmtFor(data) {
        if (data && data.unit === 'count') { const f = v => String(Math.round(Number(v) || 0)); return { axis: f, full: f, count: true }; }
        return { axis: compactRand, full: fullRand, count: false };
    }
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    const clip = (s, n) => { s = String(s || ''); return s.length > n ? s.slice(0, n - 1) + '\u2026' : s; };
    function shortDate(iso) { const d = new Date(String(iso) + 'T00:00:00'); return isNaN(d.getTime()) ? String(iso) : d.toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' }); }
    function rangeText(d) { return d.from === d.to ? shortDate(d.from) : shortDate(d.from) + ' \u2013 ' + shortDate(d.to); }
    function pctChip(p, upIsBad) {
        if (p === null || p === undefined) return '';
        const up = p >= 0, bad = upIsBad ? up : !up;
        return '<span style="color:' + (p === 0 ? '#94a3b8' : (bad ? '#f87171' : '#4ade80')) + '">' + (up ? '\u25B2 ' : '\u25BC ') + Math.abs(p).toFixed(1).replace(/\.0$/, '') + '%</span>';
    }

    // Y value of the smooth (bezier) line at a given x, so the live dots ride the curve exactly.
    function curveY(pts, px) {
        if (!pts.length) return 0;
        if (px <= pts[0][0]) return pts[0][1];
        const last = pts[pts.length - 1];
        if (px >= last[0]) return last[1];
        let i = 1; while (i < pts.length && pts[i][0] < px) i++;
        const x0 = pts[i - 1][0], y0 = pts[i - 1][1], x1 = pts[i][0], y1 = pts[i][1], mx = (x0 + x1) / 2;
        let lo = 0, hi = 1;
        for (let k = 0; k < 24; k++) { const t = (lo + hi) / 2, u = 1 - t; const x = u * u * u * x0 + 3 * u * u * t * mx + 3 * u * t * t * mx + t * t * t * x1; if (x < px) lo = t; else hi = t; }
        const t = (lo + hi) / 2, u = 1 - t;
        return u * u * u * y0 + 3 * u * u * t * y0 + 3 * u * t * t * y1 + t * t * t * y1;
    }

    // Horizontal guide that follows the cursor and shows the value under it on the Y axis.
    function drawYGuide(ctx, st, plot, maxVal, F, dark) {
        if (!st || st.y == null || st.y < plot.top || st.y > plot.bottom) return;
        const val = maxVal * (1 - (st.y - plot.top) / (plot.bottom - plot.top));
        ctx.save();
        ctx.strokeStyle = dark ? 'rgba(148,163,184,.55)' : 'rgba(71,85,105,.45)'; ctx.lineWidth = 1; ctx.setLineDash([2, 4]);
        ctx.beginPath(); ctx.moveTo(plot.left, st.y); ctx.lineTo(plot.right, st.y); ctx.stroke(); ctx.setLineDash([]);
        const txt = F.axis(val); ctx.font = '10px monospace'; const tw = ctx.measureText(txt).width + 10;
        ctx.fillStyle = dark ? '#e2e8f0' : '#0f172a'; ctx.fillRect(plot.left - tw - 2, st.y - 9, tw, 18);
        ctx.fillStyle = dark ? '#0f172a' : '#f8fafc'; ctx.textAlign = 'right'; ctx.fillText(txt, plot.left - 7, st.y + 3.5);
        ctx.restore();
    }

    function inspectBoxFor(canvas) {
        const host = canvas.closest('.chart-frame') || canvas.parentElement;
        let box = host.nextElementSibling && host.nextElementSibling.classList.contains('chart-inspect') ? host.nextElementSibling : null;
        if (!box) {
            box = document.createElement('div');
            box.className = 'chart-inspect';
            box.innerHTML = '<div class="ci-read" data-ci-read aria-live="polite"></div><div class="ci-ai"><button type="button" class="btn btn--small btn--quiet" data-ci-ask></button><div class="ci-ai__text" data-ci-out role="status" aria-live="polite"></div></div>';
            host.insertAdjacentElement('afterend', box);
            box.querySelector('[data-ci-ask]').addEventListener('click', () => { const c = box._ctx; if (c) c.ask(); });
        }
        return box;
    }
    function setInspectVisible(canvas, visible) {
        const host = canvas.closest('.chart-frame') || canvas.parentElement;
        const box = host && host.nextElementSibling;
        if (box && box.classList && box.classList.contains('chart-inspect')) box.hidden = !visible;
    }

    function chip(label, value, tone) { return '<div class="ci-chip' + (tone ? ' ci-chip--' + tone : '') + '"><span>' + esc(label) + '</span><strong>' + value + '</strong></div>'; }

    function readoutHtml(data, idx, F) {
        const det = data.details || [];
        if (idx >= 0 && det[idx]) {
            const d = det[idx], l = (data.labels || [])[idx] || '';
            return '<div class="ci-head"><strong>' + esc(l) + '</strong><span>' + esc(rangeText(d)) + '</span></div><div class="ci-grid">' +
                chip('Income', fullRand(d.income), 'in') + chip('Expenses', fullRand(d.expenses), 'out') +
                chip('Net', (d.net < 0 ? '-' : '') + fullRand(Math.abs(d.net)), d.net >= 0 ? 'in' : 'out') + chip('Balance', fullRand(d.balance)) +
                chip('Transactions', d.count + ' <small>(' + d.income_count + ' in \u00B7 ' + d.expense_count + ' out)</small>') +
                chip('Top spending', d.top_category ? esc(clip(d.top_category.name, 22)) + ' <small>' + fullRand(d.top_category.amount) + ' \u00B7 ' + d.top_category.share + '%</small>' : '<small>No expenses</small>') +
                chip('Largest expense', d.largest ? fullRand(d.largest.amount) + ' <small>' + esc(clip(d.largest.description, 24)) + '</small>' : '<small>None</small>') +
                chip('Spending vs previous', d.expense_change_pct === null ? '<small>n/a</small>' : pctChip(d.expense_change_pct, true)) + '</div>';
        }
        const sm = data.summary; if (!sm) return '';
        const lbl = data.labels || [];
        return '<div class="ci-head"><strong>Whole period</strong><span>' + esc(rangeText(sm)) + ' \u00B7 move over the graph to inspect a date</span></div><div class="ci-grid">' +
            chip('Income', fullRand(sm.income), 'in') + chip('Expenses', fullRand(sm.expenses), 'out') +
            chip('Net', (sm.net < 0 ? '-' : '') + fullRand(Math.abs(sm.net)), sm.net >= 0 ? 'in' : 'out') + chip('Closing balance', fullRand(sm.balance)) +
            chip('Transactions', String(sm.count)) +
            chip('Best period', esc(lbl[sm.best_index] || '')) + chip('Toughest period', esc(lbl[sm.worst_index] || '')) + '</div>';
    }

    function tipHtml(o, idx) {
        const data = o.data, d = (data.details || [])[idx];
        const sub = d ? rangeText(d) : ((data.ranges || [])[idx] || '');
        let h = '<div style="font-weight:700;margin-bottom:2px;">' + esc((data.labels || [])[idx] || '') + '</div>' +
            (sub ? '<div style="font-size:11px;color:#cbd5e1;margin-bottom:6px;border-bottom:1px solid rgba(255,255,255,0.15);padding-bottom:4px;">' + esc(sub) + '</div>' : '');
        o.rows(idx).forEach((r, k) => {
            h += '<div style="display:flex;justify-content:space-between;gap:16px;' + (k === 2 ? 'border-top:1px dashed rgba(255,255,255,0.15);margin-top:4px;padding-top:3px;' : '') + '"><span style="color:' + r[0] + '">\u25CF ' + esc(r[1]) + '</span><strong>' + r[2] + '</strong></div>';
        });
        if (d) {
            h += '<div style="margin-top:6px;padding-top:5px;border-top:1px solid rgba(255,255,255,0.15);font-size:11.5px;color:#cbd5e1;display:grid;gap:2px;">' +
                '<div style="display:flex;justify-content:space-between;gap:16px;"><span>Balance</span><strong style="color:#f1f5f9">' + fullRand(d.balance) + '</strong></div>' +
                '<div style="display:flex;justify-content:space-between;gap:16px;"><span>Transactions</span><strong style="color:#f1f5f9">' + d.count + ' (' + d.income_count + ' in \u00B7 ' + d.expense_count + ' out)</strong></div>' +
                (d.top_category ? '<div style="display:flex;justify-content:space-between;gap:16px;"><span>Top spending</span><strong style="color:#f1f5f9">' + esc(clip(d.top_category.name, 20)) + ' \u00B7 ' + fullRand(d.top_category.amount) + '</strong></div>' : '') +
                (d.largest ? '<div style="display:flex;justify-content:space-between;gap:16px;"><span>Largest expense</span><strong style="color:#f1f5f9">' + esc(clip(d.largest.description, 20)) + ' \u00B7 ' + fullRand(d.largest.amount) + '</strong></div>' : '') +
                (d.expense_change_pct !== null && d.expense_change_pct !== undefined ? '<div style="display:flex;justify-content:space-between;gap:16px;"><span>Spending vs previous</span><strong>' + pctChip(d.expense_change_pct, true) + '</strong></div>' : '') + '</div>';
            if (data.ai) h += '<div style="margin-top:7px;font-size:11px;color:#fbbf24;">Click for an AI explanation</div>';
        }
        return h;
    }

    // Wires mouse, touch and keyboard to a chart. `o` describes the chart geometry; `o.paint(state)` redraws it.
    function attachInspector(canvas, o) {
        const data = o.data, det = data.details || null, n = o.n;
        const tip = tipFor(canvas);
        const box = det ? inspectBoxFor(canvas) : null;
        let hover = -1, pinned = -1;
        const label = i => (data.labels || [])[i] || '';
        const showing = () => (hover >= 0 ? hover : pinned);

        const updateBox = () => {
            if (!box) return;
            box.hidden = false;
            const idx = showing();
            box.querySelector('[data-ci-read]').innerHTML = readoutHtml(data, idx, o.F);
            const btn = box.querySelector('[data-ci-ask]');
            btn.textContent = data.ai ? (idx >= 0 ? '\u2728 Explain ' + label(idx) + ' with AI' : '\u2728 Explain this period with AI') : '';
            btn.hidden = !data.ai;
        };
        const askFor = idx => {
            if (!box || !data.ai) return;
            const target = idx >= 0 && det[idx] ? det[idx] : data.summary;
            if (!target) return;
            askInsight(box, data, target.from, target.to, idx >= 0 ? label(idx) : 'This period');
        };
        if (box) box._ctx = { ask: () => askFor(pinned >= 0 ? pinned : (hover >= 0 ? hover : -1)) };

        const point = ev => {
            const r = canvas.getBoundingClientRect();
            const t = ev.touches && ev.touches[0] ? ev.touches[0] : ev;
            return { x: (t.clientX || 0) - r.left, y: (t.clientY || 0) - r.top };
        };
        const nearest = px => {
            let best = 0, bd = Infinity;
            for (let i = 0; i < n; i++) { const d = Math.abs(o.centerX(i) - px); if (d < bd) { bd = d; best = i; } }
            return best;
        };
        const place = (px, py) => {
            tip.style.display = 'block';
            const tw = tip.offsetWidth, th = tip.offsetHeight, cw = canvas.clientWidth, chh = canvas.clientHeight;
            let left = px + 18; if (left + tw > cw - 4) left = px - tw - 18;
            left = Math.max(4, left);
            const top = Math.min(Math.max(4, py - th / 2), Math.max(4, chh - th - 4));
            tip.style.left = (left + canvas.offsetLeft) + 'px'; tip.style.top = (top + canvas.offsetTop) + 'px';
        };
        const inspect = (px, py) => {
            const cx = Math.min(Math.max(px, o.plot.left), o.plot.right);
            hover = nearest(cx);
            o.paint({ idx: hover, x: cx, y: py });
            tip.innerHTML = tipHtml(o, hover);
            place(px, py);
            updateBox();
        };
        const leave = () => {
            hover = -1; tip.style.display = 'none';
            o.paint(pinned >= 0 ? { idx: pinned, x: o.centerX(pinned), y: null } : null);
            updateBox();
        };

        canvas.style.cursor = 'crosshair';
        canvas.onmousemove = ev => { const p = point(ev); inspect(p.x, p.y); };
        canvas.ontouchstart = ev => { const p = point(ev); inspect(p.x, p.y); };
        canvas.ontouchmove = ev => { const p = point(ev); inspect(p.x, p.y); };
        canvas.onmouseleave = leave;
        canvas.onclick = ev => {
            const p = point(ev); const idx = nearest(Math.min(Math.max(p.x, o.plot.left), o.plot.right));
            pinned = idx;
            if (o.selectable) canvas.dispatchEvent(new CustomEvent('chart-select', { detail: { index: idx }, bubbles: true }));
            inspect(p.x, p.y);
            askFor(idx);
        };
        canvas.tabIndex = canvas.tabIndex >= 0 ? canvas.tabIndex : 0;
        canvas.onkeydown = ev => {
            if (!['ArrowRight', 'ArrowLeft', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'Enter', ' '].includes(ev.key)) return;
            ev.preventDefault();
            let idx = pinned >= 0 ? pinned : (hover >= 0 ? hover : -1);
            if (ev.key === 'Enter' || ev.key === ' ') { if (idx >= 0) askFor(idx); return; }
            if (ev.key === 'Home') idx = 0; else if (ev.key === 'End') idx = n - 1;
            else idx = Math.max(0, Math.min(n - 1, (idx < 0 ? 0 : idx) + ((ev.key === 'ArrowRight' || ev.key === 'ArrowDown') ? 1 : -1)));
            pinned = idx; hover = -1;
            const cx = o.centerX(idx), cy = o.plot.top + (o.plot.bottom - o.plot.top) * 0.35;
            o.paint({ idx, x: cx, y: null });
            tip.innerHTML = tipHtml(o, idx); place(cx, cy); updateBox();
        };
        canvas.onblur = () => { if (hover < 0) tip.style.display = 'none'; };
        updateBox();
    }

    async function askInsight(box, data, from, to, label) {
        const out = box.querySelector('[data-ci-out]');
        const key = from + '|' + to;
        if (box._aiKey === key && box._aiBusy) return;
        box._aiKey = key; box._aiBusy = true;
        out.innerHTML = '<span class="ci-loading">\u2728 Asking AI about ' + esc(label) + ' \u2026</span>';
        try {
            const res = await fetch(data.ai.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.ai.csrf }, body: JSON.stringify({ from, to, csrf_token: data.ai.csrf }) });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) throw new Error(json.error || 'The AI insight is unavailable right now.');
            if (box._aiKey !== key) return;
            out.innerHTML = '<div class="ci-ai__head"><strong>' + esc(label) + '</strong><span class="ci-badge">' + (json.source === 'ai' ? 'AI insight' : 'Smart summary') + '</span></div><p>' + esc(json.insight) + '</p>';
        } catch (e) {
            out.innerHTML = '<span class="ci-error">' + esc(e.message || 'The AI insight is unavailable right now.') + '</span>';
        } finally { if (box._aiKey === key) box._aiBusy = false; }
    }


    // ---------------------------------------------------------------------------------------------
    // Spinnable pie/donut: drag to rotate (with momentum), hover pops a slice, click selects it,
    // arrow keys spin or step through slices. Real values only; the centre shows the selected slice.
    // ---------------------------------------------------------------------------------------------
    function spinChart(canvas, cfg) {
        const ctx = cfg.ctx, w = cfg.w, h = cfg.h, cx = cfg.cx, cy = cfg.cy, inner = cfg.inner, outer = cfg.outer;
        const total = cfg.items.reduce((t, i) => t + i.value, 0);
        const TAU = Math.PI * 2;
        const slices = []; let a = -Math.PI / 2;
        cfg.items.forEach((it, i) => { const ang = (it.value / total) * TAU; slices.push({ i, it, a0: a, a1: a + ang, pct: Math.round((it.value / total) * 100) }); a += ang; });
        let rot = canvas._spinRot || 0, hover = -1, sel = (typeof canvas._spinSel === 'number' && canvas._spinSel < slices.length) ? canvas._spinSel : -1;
        let dragging = false, lastAng = 0, vel = 0, lastT = 0, raf = 0, moved = 0, startPt = null;
        const tip = tipFor(canvas);

        const paint = () => {
            ctx.clearRect(0, 0, w, h);
            slices.forEach((s, i) => {
                const active = i === hover || i === sel;
                const mid = (s.a0 + s.a1) / 2 + rot, pop = active ? 7 : 0;
                const ox = Math.cos(mid) * pop, oy = Math.sin(mid) * pop;
                ctx.beginPath();
                ctx.arc(cx + ox, cy + oy, outer, s.a0 + rot, s.a1 + rot);
                if (inner > 0) ctx.arc(cx + ox, cy + oy, inner, s.a1 + rot, s.a0 + rot, true); else ctx.lineTo(cx + ox, cy + oy);
                ctx.closePath();
                ctx.globalAlpha = (sel >= 0 && i !== sel) ? 0.55 : 1;
                ctx.fillStyle = s.it.color; ctx.fill();
                ctx.globalAlpha = 1;
                ctx.lineWidth = 2; ctx.strokeStyle = cfg.dark ? '#0f172a' : '#ffffff'; ctx.stroke();
            });
            cfg.drawCenter(ctx, sel >= 0 ? slices[sel] : null);
            if (cfg.drawExtra) cfg.drawExtra(ctx, slices, sel, hover);
        };

        const point = ev => { const r = canvas.getBoundingClientRect(); const t = ev.touches && ev.touches[0] ? ev.touches[0] : (ev.changedTouches && ev.changedTouches[0] ? ev.changedTouches[0] : ev); return { x: (t.clientX || 0) - r.left, y: (t.clientY || 0) - r.top }; };
        const inside = p => { const d = Math.hypot(p.x - cx, p.y - cy); return d <= outer + 10 && d >= inner; };
        const hitAt = p => {
            if (!inside(p)) return -1;
            let ang = Math.atan2(p.y - cy, p.x - cx) - rot;
            while (ang < -Math.PI / 2) ang += TAU; while (ang >= Math.PI * 1.5) ang -= TAU;
            return slices.findIndex(s => ang >= s.a0 && ang < s.a1);
        };
        const showTip = (p, idx) => {
            if (idx < 0) { tip.style.display = 'none'; return; }
            tip.innerHTML = cfg.tipHtml(slices[idx]);
            tip.style.display = 'block';
            const tw = tip.offsetWidth, th = tip.offsetHeight;
            let left = p.x + 16; if (left + tw > canvas.clientWidth - 4) left = p.x - tw - 16;
            tip.style.left = (Math.max(4, left) + canvas.offsetLeft) + 'px';
            tip.style.top = (Math.min(Math.max(4, p.y - th / 2), Math.max(4, canvas.clientHeight - th - 4)) + canvas.offsetTop) + 'px';
        };
        const keep = () => { canvas._spinRot = rot; canvas._spinSel = sel; };
        const stopSpin = () => { if (raf) cancelAnimationFrame(raf); raf = 0; };
        const coast = (t) => {
            const dt = Math.min(48, t - lastT); lastT = t;
            rot += vel * dt; vel *= Math.pow(0.94, dt / 16); keep(); paint();
            if (Math.abs(vel) > 0.00015) raf = requestAnimationFrame(coast); else raf = 0;
        };

        const down = ev => {
            const p = point(ev); if (!inside(p) && !(ev.touches)) return;
            if (ev.touches && !inside(p)) return;
            stopSpin(); dragging = true; moved = 0; vel = 0; startPt = p; lastAng = Math.atan2(p.y - cy, p.x - cx); lastT = performance.now();
            canvas.style.cursor = 'grabbing'; tip.style.display = 'none';
            if (ev.cancelable && ev.touches) ev.preventDefault();
        };
        const move = ev => {
            const p = point(ev);
            if (dragging) {
                const ang = Math.atan2(p.y - cy, p.x - cx);
                let d = ang - lastAng; if (d > Math.PI) d -= TAU; if (d < -Math.PI) d += TAU;
                const now = performance.now(), dt = Math.max(1, now - lastT);
                rot += d; vel = d / dt; lastAng = ang; lastT = now; moved += Math.abs(d) * outer;
                keep(); paint();
                if (ev.cancelable && ev.touches) ev.preventDefault();
                return;
            }
            const idx = hitAt(p); if (idx !== hover) { hover = idx; paint(); }
            canvas.style.cursor = idx >= 0 ? 'grab' : 'default'; showTip(p, idx);
        };
        const up = ev => {
            if (!dragging) return;
            dragging = false; canvas.style.cursor = 'grab';
            const p = point(ev);
            if (moved < 5) { // a click, not a spin
                const idx = hitAt(startPt || p);
                sel = (idx === sel) ? -1 : idx; hover = idx; keep(); paint(); showTip(p, idx);
            } else if (Math.abs(vel) > 0.0004) { lastT = performance.now(); raf = requestAnimationFrame(coast); }
            else { hover = -1; paint(); }
        };

        canvas.onmousedown = ev => { down(ev); if (dragging) { const mm = e => move(e), mu = e => { document.removeEventListener('mousemove', mm); document.removeEventListener('mouseup', mu); up(e); }; document.addEventListener('mousemove', mm); document.addEventListener('mouseup', mu); } };
        canvas.onmousemove = ev => { if (!dragging) move(ev); };
        canvas.onmouseleave = () => { if (dragging) return; hover = -1; tip.style.display = 'none'; paint(); };
        canvas.ontouchstart = down; canvas.ontouchmove = move; canvas.ontouchend = up; canvas.ontouchcancel = up;
        canvas.ondblclick = () => { stopSpin(); rot = 0; sel = -1; hover = -1; vel = 0; keep(); paint(); };
        canvas.tabIndex = canvas.tabIndex >= 0 ? canvas.tabIndex : 0;
        canvas.onkeydown = ev => {
            if (ev.key === 'ArrowLeft' || ev.key === 'ArrowRight') { ev.preventDefault(); rot += (ev.key === 'ArrowRight' ? 1 : -1) * 0.2; }
            else if (ev.key === 'ArrowUp' || ev.key === 'ArrowDown') { ev.preventDefault(); sel = ((sel < 0 ? (ev.key === 'ArrowDown' ? -1 : 0) : sel) + (ev.key === 'ArrowDown' ? 1 : -1) + slices.length) % slices.length; }
            else if (ev.key === 'Escape') { sel = -1; }
            else return;
            keep(); paint();
        };
        canvas.style.cursor = 'grab';
        canvas.removeAttribute && canvas.removeAttribute('title');
        paint();
    }

    // Draws a bar chart on a canvas with full dynamic scaling and large amount support.
    function drawBarChart(canvas, data) {
        if (!canvas || !canvas.getContext) return;
        const F = fmtFor(data);
        setInspectVisible(canvas, true);
        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        const width = rect.width || 500;
        const height = rect.height || 260;

        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.scale(dpr, dpr);

        ctx.clearRect(0, 0, width, height);

        const isDark = isDarkTheme();
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.10)' : 'rgba(18, 48, 90, 0.12)';
        const textMuted = isDark ? '#94a3b8' : '#475569';
        const labelText = isDark ? '#f1f5f9' : '#172033';

        const labels = data.labels || [];
        const series1 = data.income || [];
        const series2 = data.expenses || [];
        const count = labels.length;

        const hasData = series1.some(v => v > 0) || series2.some(v => v > 0);
        if (count === 0 || !hasData) {
            ctx.fillStyle = textMuted;
            ctx.font = '13px var(--font-body, system-ui, sans-serif)';
            ctx.textAlign = 'center';
            ctx.fillText('No income or expenses recorded in this period yet.', width / 2, height / 2);
            hideTip(canvas);
            return;
        }

        const series3 = data.profit || [];
        const ranges = data.ranges || null;
        const lg = Object.assign({ income: 'Income', expenses: 'Expenses', profit: 'Profit' }, data.legend || {});
        const selected = canvas.dataset.selected !== undefined && canvas.dataset.selected !== '' ? Number(canvas.dataset.selected) : -1;
        const selectable = canvas.hasAttribute('data-selectable');

        const rawMax = Math.max(...series1, ...series2, ...series3.map(v => Math.max(v, 0)), 10);
        const maxVal = calculateNiceMax(rawMax);
        const steps = 4;

        // Measure widest Y-axis label to dynamically compute left padding so large amounts (e.g. R 25M) never clip
        ctx.font = '10px monospace';
        let maxYWidth = 36;
        for (let i = 0; i <= steps; i++) {
            const tickVal = maxVal - (maxVal / steps) * i;
            const str = F.axis(tickVal);
            const w = ctx.measureText(str).width;
            if (w > maxYWidth) maxYWidth = w;
        }

        const padding = { top: 24, right: 16, bottom: ranges ? 56 : 40, left: Math.max(54, Math.ceil(maxYWidth + 16)) };
        const chartW = Math.max(100, width - padding.left - padding.right);
        const chartH = Math.max(80, height - padding.top - padding.bottom);

        const groupW = chartW / count;
        const hits = [];
        const plot = { left: padding.left, right: width - padding.right, top: padding.top, bottom: padding.top + chartH };
        const paint = (st) => {
        const hov = st && st.idx != null ? st.idx : -1;
        ctx.clearRect(0, 0, width, height);
        // Draw grid lines & scale labels
        ctx.strokeStyle = gridColor;
        ctx.lineWidth = 1;
        for (let i = 0; i <= steps; i++) {
            const y = padding.top + (chartH / steps) * i;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(width - padding.right, y);
            ctx.stroke();

            const val = maxVal - (maxVal / steps) * i;
            ctx.fillStyle = textMuted;
            ctx.font = '10px monospace';
            ctx.textAlign = 'right';
            ctx.fillText(F.axis(val), padding.left - 8, y + 3.5);
        }

        // Draw bars: Income (green), Expenses (red), Profit (gold) side by side for each time slice
        const nBars = series3.length ? 3 : 2;
        const gap = Math.max(1, Math.min(3, groupW * 0.03));
        const barW = Math.max(3, Math.min(32, (groupW - (groupW > 60 ? 18 : 6) - gap * (nBars - 1)) / nBars));
        const blockW = barW * nBars + gap * (nBars - 1);
        const showAmounts = count <= 8 && groupW >= 90 && barW >= 14;

        const bar = (x, val, color) => {
            const clamped = Math.max(0, val);
            const h = (clamped / maxVal) * chartH;
            const y = padding.top + chartH - h;
            ctx.fillStyle = color;
            ctx.beginPath();
            if (ctx.roundRect) { ctx.roundRect(x, y, barW, h, [4, 4, 0, 0]); } else { ctx.rect(x, y, barW, h); }
            ctx.fill();
            return y;
        };
        const amount = (txt, x, y, color) => {
            ctx.fillStyle = color; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(txt, x, Math.max(padding.top + 8, y - 5));
        };

        const labelEvery = Math.max(1, Math.ceil(count / Math.max(2, Math.floor(chartW / 68))));
        labels.forEach((lbl, idx) => {
            const gx0 = padding.left + groupW * idx;
            const groupX = gx0 + groupW / 2;
            const x0 = groupX - blockW / 2;

            if (idx === selected) {
                ctx.fillStyle = isDark ? 'rgba(232,163,58,0.12)' : 'rgba(232,163,58,0.16)';
                ctx.fillRect(gx0 + 2, padding.top - 6, groupW - 4, chartH + 6 + (ranges ? 50 : 34));
            }

            if (idx === hov && idx !== selected) {
                ctx.fillStyle = isDark ? 'rgba(148,163,184,0.14)' : 'rgba(15,23,42,0.07)';
                ctx.fillRect(gx0 + 2, padding.top - 6, groupW - 4, chartH + 6 + (ranges ? 50 : 34));
            }

            const incVal = series1[idx] || 0;
            const expVal = series2[idx] || 0;
            const profVal = series3.length ? (series3[idx] || 0) : (incVal - expVal);

            const incY = bar(x0, incVal, '#16a34a');
            const expY = bar(x0 + barW + gap, expVal, '#dc2626');
            let profY = padding.top + chartH;
            if (series3.length) profY = bar(x0 + (barW + gap) * 2, Math.max(profVal, 0), '#f59e0b');

            if (showAmounts) {
                if (incVal > 0) amount(F.axis(incVal), x0 + barW / 2, incY, isDark ? '#4ade80' : '#15803d');
                if (expVal > 0) amount(F.axis(expVal), x0 + barW + gap + barW / 2, expY, isDark ? '#f87171' : '#b91c1c');
                if (series3.length && profVal !== 0) {
                    amount((profVal < 0 ? '-' : '') + F.axis(Math.abs(profVal)), x0 + (barW + gap) * 2 + barW / 2, profVal < 0 ? padding.top + chartH : profY, profVal < 0 ? (isDark ? '#f87171' : '#b91c1c') : (isDark ? '#fbbf24' : '#b45309'));
                }
            }

            // Labels (skipped on crowded charts so they never overlap)
            if (idx % labelEvery === 0) {
                ctx.textAlign = 'center';
                ctx.fillStyle = labelText;
                ctx.font = (idx === selected ? 'bold ' : '') + '11.5px sans-serif';
                ctx.fillText(lbl, groupX, height - padding.bottom + 20);
                if (ranges && ranges[idx]) {
                    ctx.fillStyle = textMuted;
                    ctx.font = '10px sans-serif';
                    ctx.fillText(ranges[idx], groupX, height - padding.bottom + 34);
                }
            }

            hits.push({ idx, x0: gx0, x1: gx0 + groupW, label: ranges && ranges[idx] ? lbl + ' (' + ranges[idx] + ')' : lbl, inc: incVal, exp: expVal, profit: profVal });
        });

        drawYGuide(ctx, st, plot, maxVal, F, isDark);
        };
        paint(null);

        attachInspector(canvas, {
            data, n: count, F, plot, selectable, paint,
            centerX: i => padding.left + groupW * (i + 0.5),
            rows: i => {
                const inc = series1[i] || 0, exp = series2[i] || 0, pr = series3.length ? (series3[i] || 0) : inc - exp;
                return [['#4ade80', lg.income, F.full(inc)], ['#f87171', lg.expenses, F.full(exp)], ['#fbbf24', lg.profit, (pr < 0 ? '-' : '') + F.full(Math.abs(pr))]];
            }
        });
    }

    function drawDonutChart(canvas, data, opts) {
        const asPie = !!(opts && opts.pie);
        if (!canvas || !canvas.getContext) return;
        setInspectVisible(canvas, false);
        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        const size = Math.min(rect.width || 240, rect.height || 240);
        canvas.width = size * dpr; canvas.height = size * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        const isDark = isDarkTheme();
        const centerTextColor = isDark ? '#f1f5f9' : '#172033';
        const items = (data.items || []).map((item, idx) => ({ name: item.name || item.label || 'Category', value: Math.max(0, Number(item.value || 0)), color: PALETTE[idx % PALETTE.length] })).filter(i => i.value > 0);
        const total = items.reduce((sum, item) => sum + item.value, 0);
        const cx = size / 2, cy = size / 2, outerR = size * 0.40, innerR = asPie ? 0 : size * 0.26;

        if (total === 0) {
            ctx.fillStyle = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(18,48,90,0.08)';
            ctx.beginPath(); ctx.arc(cx, cy, outerR, 0, Math.PI * 2); ctx.arc(cx, cy, innerR, 0, Math.PI * 2, true); ctx.fill();
            return;
        }
        spinChart(canvas, {
            ctx, w: size, h: size, cx, cy, inner: innerR, outer: outerR, items, dark: isDark,
            drawCenter: (c, s) => {
                if (asPie && !s) return;
                c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillStyle = asPie ? '#fff' : centerTextColor;
                const money = v => v < 1e6 ? 'R ' + Math.round(v).toLocaleString('en-ZA') : compactRand(v);
                if (s) {
                    c.font = '10.5px sans-serif'; c.globalAlpha = 0.8; c.fillText(clip(s.it.name, 16), cx, cy - 12); c.globalAlpha = 1;
                    const txt = money(s.it.value); c.font = 'bold ' + (txt.length > 9 ? '12px' : '14px') + ' sans-serif'; c.fillText(txt, cx, cy + 4);
                    c.font = '10.5px sans-serif'; c.globalAlpha = 0.8; c.fillText(s.pct + '% of total', cx, cy + 19); c.globalAlpha = 1;
                } else {
                    c.font = '10.5px sans-serif'; c.globalAlpha = 0.72; c.fillText('Total spent', cx, cy - 10); c.globalAlpha = 1;
                    const txt = money(total); c.font = 'bold ' + (txt.length > 10 ? '11px' : (txt.length > 7 ? '12.5px' : '14px')) + ' sans-serif'; c.fillText(txt, cx, cy + 9);
                }
                c.textBaseline = 'alphabetic';
            },
            tipHtml: s => '<div style="font-weight:700;margin-bottom:3px;color:' + s.it.color + '">\u25CF ' + esc(s.it.name) + '</div>' +
                '<div style="display:flex;justify-content:space-between;gap:12px;"><span>Amount:</span> <strong>' + fullRand(s.it.value) + '</strong></div>' +
                '<div style="display:flex;justify-content:space-between;gap:12px;"><span style="color:#94a3b8">Share:</span> <strong>' + s.pct + '% of total</strong></div>' +
                '<div style="margin-top:5px;font-size:11px;color:#fbbf24;">Drag to spin \u00B7 click to select</div>'
        });
        canvas.setAttribute('aria-label', 'Spinnable chart. Total ' + fullRand(total) + '. ' + items.map(i => i.name + ' ' + fullRand(i.value)).join(', '));
    }


    // ---- Shared helpers for the line and pie views ----------------------------------
    function prepCanvas(canvas) {
        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        const width = rect.width || 500;
        const height = rect.height || 290;
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);
        const dark = isDarkTheme();
        return {
            ctx, width, height, dark,
            grid: dark ? 'rgba(255,255,255,0.10)' : 'rgba(18,48,90,0.12)',
            muted: dark ? '#94a3b8' : '#475569',
            text: dark ? '#f1f5f9' : '#172033',
            green: dark ? '#34d399' : '#16a34a',
            red: dark ? '#fb7185' : '#dc2626'
        };
    }

    function emptyMessage(c, canvas) {
        c.ctx.fillStyle = c.muted;
        c.ctx.font = '13px system-ui, sans-serif';
        c.ctx.textAlign = 'center';
        c.ctx.fillText('No income or expenses recorded in this period yet.', c.width / 2, c.height / 2);
        hideTip(canvas);
    }

    function placeTip(canvas, tip, x, y) {
        const left = Math.min(Math.max(4, x + 14), canvas.clientWidth - tip.offsetWidth - 4);
        tip.style.left = (left + canvas.offsetLeft) + 'px';
        tip.style.top = Math.max(4, y + canvas.offsetTop) + 'px';
    }

    // Trend view: two smooth lines (income and expenses) with soft filled areas.
    function drawLineChart(canvas, data) {
        if (!canvas || !canvas.getContext) return;
        const F = fmtFor(data);
        setInspectVisible(canvas, true);
        const c = prepCanvas(canvas);
        const ctx = c.ctx;
        const labels = data.labels || [];
        const ranges = data.ranges || [];
        const inc = (data.income || []).map(Number);
        const exp = (data.expenses || []).map(Number);
        if (!labels.length || !(inc.some(v => v > 0) || exp.some(v => v > 0))) { emptyMessage(c, canvas); return; }

        const lg = Object.assign({ income: 'Income', expenses: 'Expenses', profit: 'Profit' }, data.legend || {});
        const maxVal = calculateNiceMax(Math.max(...inc, ...exp, 10));
        const steps = 4;
        ctx.font = '10px monospace';
        let yw = 36;
        for (let i = 0; i <= steps; i++) yw = Math.max(yw, ctx.measureText(F.axis(maxVal - (maxVal / steps) * i)).width);
        const pad = { top: 24, right: 22, bottom: 40, left: Math.max(54, Math.ceil(yw + 16)) };
        const w = Math.max(100, c.width - pad.left - pad.right);
        const h = Math.max(80, c.height - pad.top - pad.bottom);
        const n = labels.length;
        const xAt = i => pad.left + (w / n) * (i + 0.5);
        const yAt = v => pad.top + h - (Math.max(0, v) / maxVal) * h;

        const plot = { left: pad.left, right: pad.left + w, top: pad.top, bottom: pad.top + h };
        const render = (st) => {
            const hover = st && st.idx != null ? st.idx : -1;
            ctx.clearRect(0, 0, c.width, c.height);
            ctx.strokeStyle = c.grid; ctx.lineWidth = 1;
            for (let i = 0; i <= steps; i++) {
                const y = pad.top + (h / steps) * i;
                ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(c.width - pad.right, y); ctx.stroke();
                ctx.fillStyle = c.muted; ctx.font = '10px monospace'; ctx.textAlign = 'right';
                ctx.fillText(F.axis(maxVal - (maxVal / steps) * i), pad.left - 8, y + 3.5);
            }
            const every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(w / 68))));
            ctx.fillStyle = c.text; ctx.font = '11.5px system-ui, sans-serif'; ctx.textAlign = 'center';
            labels.forEach((l, i) => { if (i % every === 0) ctx.fillText(l, xAt(i), c.height - pad.bottom + 22); });

            const series = [[exp, c.red], [inc, c.green]];
            series.forEach(([vals, color]) => {
                // Smooth curve through the points.
                const pts = vals.map((v, i) => [xAt(i), yAt(v)]);
                const path = () => {
                    ctx.beginPath(); ctx.moveTo(pts[0][0], pts[0][1]);
                    for (let i = 1; i < pts.length; i++) {
                        const mx = (pts[i - 1][0] + pts[i][0]) / 2;
                        ctx.bezierCurveTo(mx, pts[i - 1][1], mx, pts[i][1], pts[i][0], pts[i][1]);
                    }
                };
                if (pts.length > 1) {
                    path(); ctx.lineTo(pts[pts.length - 1][0], pad.top + h); ctx.lineTo(pts[0][0], pad.top + h); ctx.closePath();
                    const g = ctx.createLinearGradient(0, pad.top, 0, pad.top + h);
                    g.addColorStop(0, color + '55'); g.addColorStop(1, color + '05');
                    ctx.fillStyle = g; ctx.fill();
                    path(); ctx.strokeStyle = color; ctx.lineWidth = 2.5; ctx.lineJoin = 'round'; ctx.stroke();
                }
                pts.forEach(([x, y], i) => {
                    ctx.beginPath(); ctx.arc(x, y, i === hover ? 6 : 3.5, 0, Math.PI * 2);
                    ctx.fillStyle = c.dark ? '#0b1220' : '#ffffff'; ctx.fill();
                    ctx.lineWidth = 2.5; ctx.strokeStyle = color; ctx.stroke();
                });
            });
            if (st && st.x != null) {
                const cx = Math.min(Math.max(st.x, pad.left), pad.left + w);
                ctx.save(); ctx.strokeStyle = c.muted; ctx.lineWidth = 1; ctx.setLineDash([4, 4]);
                ctx.beginPath(); ctx.moveTo(cx, pad.top); ctx.lineTo(cx, pad.top + h); ctx.stroke(); ctx.restore();
                [[exp, c.red], [inc, c.green]].forEach(([vals, color]) => {
                    const pts = vals.map((v, i) => [xAt(i), yAt(v)]);
                    ctx.beginPath(); ctx.arc(cx, curveY(pts, cx), 5.5, 0, Math.PI * 2);
                    ctx.fillStyle = color; ctx.fill(); ctx.lineWidth = 2; ctx.strokeStyle = c.dark ? '#0b1220' : '#ffffff'; ctx.stroke();
                });
                // Date chip sitting on the X axis under the crosshair.
                const chipText = (ranges[hover] || labels[hover] || '');
                ctx.font = '600 11px system-ui, sans-serif'; const tw = ctx.measureText(chipText).width + 14;
                const chipX = Math.min(Math.max(cx - tw / 2, pad.left - 4), c.width - pad.right - tw + 4);
                ctx.fillStyle = c.dark ? '#e2e8f0' : '#0f172a'; ctx.fillRect(chipX, pad.top + h + 8, tw, 20);
                ctx.fillStyle = c.dark ? '#0f172a' : '#f8fafc'; ctx.textAlign = 'center'; ctx.fillText(chipText, chipX + tw / 2, pad.top + h + 22);
            }
            drawYGuide(ctx, st, plot, maxVal, F, c.dark);
        };
        render(null);

        attachInspector(canvas, {
            data, n, F, plot, paint: render,
            centerX: xAt,
            rows: i => {
                const pr = (data.profit && data.profit[i] !== undefined && data.profit[i] !== null) ? Number(data.profit[i]) : inc[i] - exp[i];
                return [['#4ade80', lg.income, F.full(inc[i])], ['#f87171', lg.expenses, F.full(exp[i])], ['#fbbf24', lg.profit, (pr < 0 ? '-' : '') + F.full(Math.abs(pr))]];
            }
        });
    }

    function drawPieChart(canvas, data) {
        if (!canvas || !canvas.getContext) return;
        const c = prepCanvas(canvas);
        const ctx = c.ctx;
        const items = (data.items || []).map((i, k) => ({ name: i.label || i.name || '', value: Math.max(0, Number(i.value || 0)), color: [c.green, c.red][k % 2] })).filter(i => i.value > 0);
        const total = items.reduce((a, i) => a + i.value, 0);
        if (!total) { emptyMessage(c, canvas); return; }
        const wide = c.width >= 420;
        const outer = Math.min(wide ? c.height / 2 - 22 : c.height / 2 - 52, 120);
        const inner = outer * 0.62;
        const cx = wide ? Math.max(outer + 24, c.width * 0.28) : c.width / 2;
        const cy = wide ? c.height / 2 : outer + 18;
        const all = data.items || [];
        const income = all[0] ? Math.max(0, Number(all[0].value || 0)) : 0;
        const spent = all[1] ? Math.max(0, Number(all[1].value || 0)) : 0;
        const kept = income > 0 ? Math.round(((income - spent) / income) * 100) : 0;

        spinChart(canvas, {
            ctx, w: c.width, h: c.height, cx, cy, inner, outer, items, dark: c.dark,
            drawCenter: (x, s) => {
                x.textAlign = 'center'; x.textBaseline = 'middle';
                if (s) {
                    x.fillStyle = c.muted; x.font = '11px system-ui, sans-serif'; x.fillText(clip(s.it.name, 14), cx, cy - outer * 0.24);
                    x.fillStyle = c.text; x.font = '800 ' + Math.round(outer * 0.17) + 'px system-ui, sans-serif'; x.fillText(compactRand(s.it.value), cx, cy);
                    x.fillStyle = c.muted; x.font = '11px system-ui, sans-serif'; x.fillText(s.pct + '% of the total', cx, cy + outer * 0.24);
                } else {
                    x.fillStyle = income > 0 && kept < 0 ? c.red : c.text;
                    x.font = '800 ' + Math.round(outer * 0.3) + 'px system-ui, sans-serif'; x.fillText((income > 0 ? kept : 0) + '%', cx, cy - 6);
                    x.fillStyle = c.muted; x.font = '11px system-ui, sans-serif'; x.fillText(kept >= 0 ? 'of income kept' : 'overspent', cx, cy + outer * 0.2);
                }
                x.textBaseline = 'alphabetic';
            },
            drawExtra: (x, slices, sel, hover) => {
                x.textAlign = 'left';
                slices.forEach((s, i) => {
                    const lx = wide ? cx + outer + 36 : Math.max(12, c.width / 2 - 120);
                    const ly = wide ? cy - 22 + i * 50 : cy + outer + 30 + i * 22;
                    const on = i === sel || i === hover;
                    x.fillStyle = s.it.color; x.beginPath(); x.arc(lx + 5, ly - 4, on ? 6.5 : 5, 0, Math.PI * 2); x.fill();
                    x.fillStyle = c.text; x.font = (on ? '800 ' : '600 ') + '13px system-ui, sans-serif';
                    x.fillText(s.it.name + ' \u00B7 ' + s.pct + '%', lx + 18, ly);
                    if (wide) { x.fillStyle = c.muted; x.font = '12px system-ui, sans-serif'; x.fillText(fullRand(s.it.value), lx + 18, ly + 18); }
                });
            },
            tipHtml: s => '<div style="font-weight:700;margin-bottom:3px;color:' + s.it.color + '">\u25CF ' + esc(s.it.name) + '</div>' +
                '<div style="display:flex;justify-content:space-between;gap:12px;"><span>Amount:</span> <strong>' + fullRand(s.it.value) + '</strong></div>' +
                '<div style="display:flex;justify-content:space-between;gap:12px;"><span style="color:#94a3b8">Share:</span> <strong>' + s.pct + '%</strong></div>' +
                '<div style="margin-top:5px;font-size:11px;color:#fbbf24;">Drag to spin \u00B7 click to select</div>'
        });
        canvas.setAttribute('aria-label', 'Spinnable chart. ' + items.map(i => i.name + ' ' + fullRand(i.value)).join(', '));
    }


    // Horizontal bars for category breakdowns (used when the chosen style is bars or line).
    function drawCategoryBars(canvas, data) {
        const items = (data.items || []).filter(i => Number(i.value) > 0);
        const rowH = 34;
        canvas.style.width = '100%';
        canvas.style.height = Math.max(120, items.length * rowH + 16) + 'px';
        const c = prepCanvas(canvas);
        const ctx = c.ctx;
        if (!items.length) { emptyMessage(c, canvas); return; }
        const total = items.reduce((a, i) => a + Number(i.value), 0);
        const max = Math.max(...items.map(i => Number(i.value)));
        const labelW = Math.min(150, Math.round(c.width * 0.34));
        const valueW = 92;
        const barX = labelW + 10;
        const barW = Math.max(40, c.width - barX - valueW - 6);
        ctx.textBaseline = 'middle';
        items.forEach((it, i) => {
            const y = 8 + i * rowH + rowH / 2;
            const v = Number(it.value);
            let name = String(it.name || it.label || 'Category');
            ctx.font = '600 12px system-ui, sans-serif';
            while (name.length > 3 && ctx.measureText(name).width > labelW) name = name.slice(0, -2);
            if (name !== String(it.name || it.label || 'Category')) name += '…';
            ctx.fillStyle = c.text; ctx.textAlign = 'left'; ctx.fillText(name, 0, y);
            ctx.fillStyle = c.dark ? 'rgba(255,255,255,0.08)' : 'rgba(18,48,90,0.07)';
            ctx.fillRect(barX, y - 7, barW, 14);
            ctx.fillStyle = PALETTE[i % PALETTE.length];
            ctx.fillRect(barX, y - 7, Math.max(3, (v / max) * barW), 14);
            ctx.fillStyle = c.muted; ctx.font = '11px system-ui, sans-serif'; ctx.textAlign = 'right';
            ctx.fillText(compactRand(v) + ' · ' + Math.round((v / total) * 100) + '%', c.width, y);
        });
        canvas.setAttribute('title', items.map(i => (i.name || i.label) + ': ' + fullRand(Number(i.value))).join('\n'));
    }

    // Picks the right view for a category breakdown (donut canvases) from the chosen chart style.
    function drawCategory(canvas) {
        let data = {};
        try { data = JSON.parse(canvas.dataset.chart || '{}'); } catch (e) { return; }
        const mode = canvas.dataset.chartMode ? normalizeType(canvas.dataset.chartMode) : preferredType();
        if (mode === 'pie') {
            canvas.style.width = '220px'; canvas.style.height = '220px';
            drawDonutChart(canvas, data, { pie: true });
        } else if (mode === 'line') {
            canvas.style.width = '220px'; canvas.style.height = '220px';
            drawDonutChart(canvas, data);
        } else {
            drawCategoryBars(canvas, data);
        }
    }

    // Picks the right view for a cash-movement canvas (bars, trend line or share).
    function drawCashflow(canvas) {
        let data = {};
        try { data = JSON.parse(canvas.dataset.chart || '{}'); } catch (e) { return; }
        const mode = canvas.dataset.chartMode ? normalizeType(canvas.dataset.chartMode) : preferredType();
        if (mode === 'pie') {
            setInspectVisible(canvas, false);
            const sum = a => (a || []).reduce((t, v) => t + Number(v || 0), 0);
            drawPieChart(canvas, { items: [{ label: (data.legend && data.legend.income) || 'Income', value: sum(data.income) }, { label: (data.legend && data.legend.expenses) || 'Expenses', value: sum(data.expenses) }] });
        } else if (mode === 'line') {
            drawLineChart(canvas, data);
        } else {
            drawBarChart(canvas, data);
        }
    }

    // Draws every chart on the page.
    function renderAllCharts() {
        document.querySelectorAll('canvas[data-cashflow-chart]').forEach(c => {
            try {
                drawCashflow(c);
            } catch (e) {
                console.error('Error drawing bar chart', e);
            }
        });

        document.querySelectorAll('canvas[data-donut-chart]').forEach(c => {
            try {
                drawCategory(c);
            } catch (e) {
                console.error('Error drawing donut chart', e);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderAllCharts();

        let rt;
        window.addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(renderAllCharts, 200); });

        // Redraw the charts when the theme changes
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.attributeName === 'data-theme' || mutation.attributeName === 'data-chart-type') {
                    renderAllCharts();
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true });
    });

    window.ImaliCharts = { drawBarChart, drawDonutChart, drawPieChart, drawLineChart, drawCashflow, drawCategory, renderAllCharts, normalizeType, preferredType };
})();
