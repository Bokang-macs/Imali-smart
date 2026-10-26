/**
 * Imali Smart - Dashboard Interactive Controller
 * Handles the cash-movement chart switcher (bars, trend, share) and the period form.
 */
(() => {
    'use strict';

    const MODE_KEY = 'imali_chart_mode';
    const MODES = ['bar', 'line', 'pie'];
    const store = {
        get() { try { return localStorage.getItem(MODE_KEY); } catch (e) { return null; } },
        set(v) { try { localStorage.setItem(MODE_KEY, v); } catch (e) {} }
    };

    document.addEventListener('DOMContentLoaded', () => {
        // Lift quick-action chips on hover.
        document.querySelectorAll('.dashboard-chip').forEach(chip => {
            chip.addEventListener('mouseenter', () => { chip.style.transform = 'translateY(-2px)'; });
            chip.addEventListener('mouseleave', () => { chip.style.transform = 'translateY(0)'; });
        });

        // Cash movement chart: bars by default, with a trend line and a share view.
        const canvas = document.querySelector('canvas[data-cashflow-chart][data-mode-switchable]');
        const secondary = document.querySelectorAll('canvas[data-secondary-chart]');
        const buttons = document.querySelectorAll('[data-chart-mode-btn]');
        const hint = document.querySelector('.chart-legend em');
        const hints = { bar: 'Hover or tap a bar for exact amounts', line: 'Hover or tap the line for exact amounts', pie: 'Hover or tap a slice for exact amounts' };

        const apply = (mode) => {
            if (!canvas) return;
            if (mode === 'graph') mode = 'bar';
            if (!MODES.includes(mode)) mode = 'bar';
            canvas.dataset.chartMode = mode;
            buttons.forEach(b => b.setAttribute('aria-pressed', b.dataset.chartModeBtn === mode ? 'true' : 'false'));
            if (hint) hint.textContent = hints[mode];
            if (window.ImaliCharts) window.ImaliCharts.drawCashflow(canvas);
        };

        // Choosing a style here also saves it as the person's chart style for the whole app.
        buttons.forEach(b => b.addEventListener('click', () => {
            const mode = b.dataset.chartModeBtn;
            store.set(mode);
            apply(mode);
            document.documentElement.setAttribute('data-chart-type', mode);
            try {
                fetch('api/profile/chart-type.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'chart_type=' + encodeURIComponent(mode)
                }).catch(() => {});
            } catch (e) {}
        }));
        apply(document.documentElement.getAttribute('data-chart-type') || store.get() || 'bar');
        secondary.forEach(c => { if (window.ImaliCharts) window.ImaliCharts.drawCashflow(c); });

        // Period filter: submit as soon as both dates are chosen.
        const periodForm = document.querySelector('.period-inline');
        if (periodForm) {
            periodForm.querySelectorAll('input[type="date"]').forEach(inp => {
                inp.addEventListener('change', () => {
                    const from = periodForm.querySelector('#from')?.value;
                    const to = periodForm.querySelector('#to')?.value;
                    if (from && to && from <= to) periodForm.submit();
                });
            });
        }
    });
})();
