<?php
// Steady Pay Planner. Turns irregular income, piece jobs, tips and commissions into a safe amount to spend.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/i18n.php';

require_auth();
$user = current_user();
$p = irregular_income_summary((int) $user['id']);

$chart = ['labels' => [], 'income' => [], 'expenses' => [], 'granularity' => 'month'];
foreach ($p['months'] as $m) {
    $chart['labels'][] = $m['label'];
    $chart['income'][] = $m['income'];
    $chart['expenses'][] = $m['expenses'];
}
$bufferPct = $p['buffer_target'] > 0 ? min(100, round($p['buffer_now'] / $p['buffer_target'] * 100)) : ($p['ready'] ? 100 : 0);
$overBy = $p['income_this_month'] - $p['steady_pay'];

$page_title = 'Income Plan';
$page_js = 'charts.js';
require __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">IRREGULAR INCOME, MADE SIMPLE</span>
        <h1>Income Plan</h1>
        <p>Some months are big, some are thin. This page turns your ups and downs into one number you can safely spend, and shows how big a cushion you need for the lean months.</p>
    </div>
</div>

<?php if (!$p['ready']) : ?>
    <section class="panel">
        <div class="empty-state">
            <div class="empty-state__mark">≈</div>
            <h3>We need at least 2 months of income to work this out</h3>
            <p>Record the money you earned in your last few months (even roughly). A weekend job, a tip, a grant or casual work all count. You have <?= (int) $p['months_with_income'] ?> month<?= $p['months_with_income'] === 1 ? '' : 's' ?> with income so far.</p>
            <a class="btn btn--primary btn--small" href="transactions.php?action=add&type=income">Record income</a>
        </div>
    </section>
<?php else : ?>
    <section class="hero-card" style="margin-bottom: 24px;">
        <div class="hero-card__row">
            <div>
                <span class="eyebrow" style="color: var(--marigold);">SAFE TO SPEND TODAY</span>
                <h2><?= money($p['safe_per_day']) ?></h2>
                <p>
                    <?php if ($p['safe_left'] > 0) : ?>
                        You have <strong><?= money($p['safe_left']) ?></strong> left of your steady pay this month, spread over <?= (int) $p['days_left'] ?> day<?= $p['days_left'] === 1 ? '' : 's' ?>.
                    <?php else : ?>
                        You have already spent your steady pay for this month. Hold off on anything that is not essential until next month.
                    <?php endif; ?>
                </p>
            </div>
            <div class="hero-card__metric">
                <span>Your steady monthly pay</span>
                <strong style="color: #10b981;"><?= money($p['steady_pay']) ?></strong>
                <small style="color: #94a3b8;">85% of your typical month</small>
            </div>
        </div>
    </section>

    <section class="stat-grid" style="margin-bottom: 24px;">
        <div class="stat-card stat-card--mint"><span class="stat-card__label">Typical month</span><strong class="stat-card__value"><?= money($p['average_income']) ?></strong><span class="stat-card__note">Average of your last <?= (int) $p['months_with_income'] ?> months</span></div>
        <div class="stat-card stat-card--rose"><span class="stat-card__label">Leanest month</span><strong class="stat-card__value"><?= money($p['lean_income']) ?></strong><span class="stat-card__note">Plan for months like this</span></div>
        <div class="stat-card stat-card--gold"><span class="stat-card__label">Income swing</span><strong class="stat-card__value"><?= (int) $p['swing_pct'] ?>%</strong><span class="stat-card__note"><?= $p['swing_pct'] >= 50 ? 'Big swings: a cushion matters a lot' : ($p['swing_pct'] >= 20 ? 'Moderate swings' : 'Fairly steady income') ?></span></div>
        <div class="stat-card stat-card--blue"><span class="stat-card__label">Spent this month</span><strong class="stat-card__value"><?= money($p['spent_this_month']) ?></strong><span class="stat-card__note">Earned so far: <?= money($p['income_this_month']) ?></span></div>
    </section>

    <div class="dashboard-grid">
        <section class="panel">
            <div class="panel__heading"><div><h2>Your last 6 months</h2><p>Money in vs money out. Hover a bar for exact amounts.</p></div></div>
            <div class="chart-frame" style="min-height: 240px; padding: 10px; position: relative;">
                <canvas data-cashflow-chart data-chart='<?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>' style="width: 100%; height: 240px;" aria-label="Income and expenses for the last six months"></canvas>
            </div>
        </section>

        <aside class="panel">
            <div class="panel__heading"><div><h2>Lean-month cushion</h2><p>Savings that cover a thin month</p></div></div>
            <?php if ($p['buffer_target'] <= 0) : ?>
                <p style="font-size: 13.5px; color: var(--ink-soft);">Your leanest month still covers your usual spending, so you do not need an extra cushion right now. Keep it that way.</p>
            <?php else : ?>
                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;"><span>Saved <strong><?= money($p['buffer_now']) ?></strong></span><span>Target <strong><?= money($p['buffer_target']) ?></strong></span></div>
                <div class="meter"><div style="width: <?= (int) $bufferPct ?>%; background: <?= $bufferPct >= 100 ? '#16a34a' : '#d97706' ?>;"></div></div>
                <p style="font-size: 13px; color: var(--ink-soft); margin: 12px 0 0;">
                    <?= $bufferPct >= 100 ? 'You can cover about 3 lean months. Well done.' : 'Target = 3 lean months of the gap between your spending and your leanest income. Add to it whenever a month beats your steady pay.' ?>
                </p>
            <?php endif; ?>
            <a class="btn btn--accent btn--small" href="savings.php" style="margin-top: 14px;">Add to savings</a>
        </aside>
    </div>

    <section class="panel" style="margin-top: 24px;">
        <div class="panel__heading"><div><h2>What to do this month</h2></div></div>
        <ul style="margin: 0; padding-left: 20px; line-height: 1.8; font-size: 14px; color: var(--ink);">
            <?php if ($overBy > 0) : ?>
                <li>You have earned <strong><?= money($overBy) ?></strong> more than your steady pay. Move some of it into your cushion before it gets spent.</li>
            <?php else : ?>
                <li>You are <strong><?= money(abs($overBy)) ?></strong> short of your steady pay so far this month. That is normal. Spend from your steady pay, not from hope.</li>
            <?php endif; ?>
            <li>Pay yourself the same amount every month, and keep the difference for the thin months.</li>
            <li>Before taking on more debt to cover a gap, review your current balances and protect your essentials first.</li>
        </ul>
    </section>

    <details class="panel" style="margin-top: 24px;">
        <summary style="cursor: pointer; font-weight: 700;">How we work this out (in plain language)</summary>
        <ol style="line-height: 1.8; font-size: 13.5px; color: var(--ink-soft);">
            <li><strong>Typical month</strong> = the average of your last 6 full months that had income.</li>
            <li><strong>Steady pay</strong> = 85% of that. The other 15% stays behind in good months to protect you in bad ones.</li>
            <li><strong>Cushion target</strong> = 3 x the gap between your usual spending and your leanest month.</li>
            <li><strong>Safe to spend today</strong> = (steady pay minus what you have already spent this month) divided by the days left.</li>
        </ol>
        <p style="font-size: 12px; color: var(--ink-faint);">This is a planning guide based only on what you have recorded. It is not financial advice.</p>
    </details>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
