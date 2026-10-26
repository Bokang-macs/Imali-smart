<?php
// Shows charts and totals of income and spending for a chosen period.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/chart_details.php';

require_auth();
$user = current_user();
[$from, $to] = selected_period();
[$from, $to] = normalize_period($from, $to);
$groupBy = in_array((string)($_GET['group_by'] ?? 'auto'), ['auto', 'week', 'month'], true) ? (string)($_GET['group_by'] ?? 'auto') : 'auto';

$analytics = [
    'income_total' => 0.0,
    'expense_total' => 0.0,
    'net_savings' => 0.0,
    'savings_rate' => 0.0,
    'spend_rate' => 0.0,
    'largest_expense' => null,
    'category_breakdown' => [],
];
$trend = build_cashflow_series([], $from, $to, $groupBy);
$activity = ['labels' => [], 'ranges' => [], 'income' => [], 'expenses' => [], 'profit' => [], 'legend' => ['income' => 'Income entries', 'expenses' => 'Expense entries', 'profit' => 'Total entries'], 'unit' => 'count'];
$netTrend = ['labels' => [], 'ranges' => [], 'income' => [], 'expenses' => [], 'profit' => [], 'legend' => ['income' => 'Positive net flow', 'expenses' => 'Negative net flow', 'profit' => 'Net flow']];

if (database_ready()) {
    try {
        $stmt = db()->prepare("
            SELECT t.transaction_date, t.transaction_type, t.amount, t.description,
                   COALESCE(NULLIF(c.name, ''), 'Uncategorised') AS category_name
            FROM transactions t
            LEFT JOIN expense_categories c ON c.id = t.category_id
            WHERE t.user_id = :user_id AND t.deleted_at IS NULL
              AND t.transaction_type IN ('income', 'expense')
              AND t.transaction_date BETWEEN :from AND :to
        ");
        $stmt->execute(['user_id' => $user['id'], 'from' => $from, 'to' => $to]);
        $rows = $stmt->fetchAll();

        $catMap = [];
        $perDay = [];
        foreach ($rows as $row) {
            $amt = (float) $row['amount'];
            $key = $row['transaction_date'] . '|' . $row['transaction_type'];
            $perDay[$key] = ($perDay[$key] ?? 0.0) + $amt;
            if ($row['transaction_type'] === 'income') {
                $analytics['income_total'] += $amt;
            } else {
                $analytics['expense_total'] += $amt;
                $catMap[$row['category_name']] = ($catMap[$row['category_name']] ?? 0.0) + $amt;
                if (!$analytics['largest_expense'] || $amt > $analytics['largest_expense']['amount']) {
                    $analytics['largest_expense'] = ['amount' => $amt, 'description' => $row['description']];
                }
            }
        }

        $analytics['net_savings'] = $analytics['income_total'] - $analytics['expense_total'];
        if ($analytics['income_total'] > 0) {
            $analytics['savings_rate'] = round(max(0, $analytics['net_savings']) / $analytics['income_total'] * 100, 1);
            $analytics['spend_rate'] = round($analytics['expense_total'] / $analytics['income_total'] * 100, 1);
        }

        arsort($catMap);
        foreach ($catMap as $cName => $cVal) {
            $analytics['category_breakdown'][] = ['name' => $cName, 'value' => round($cVal, 2)];
        }

        $daily = [];
        foreach ($perDay as $k => $v) {
            [$d, $ty] = explode('|', $k);
            $daily[] = ['transaction_date' => $d, 'transaction_type' => $ty, 'total' => $v];
        }
        $trend = build_cashflow_series($daily, $from, $to, $groupBy);
        $incomeCounts = array_fill(0, count($trend['labels']), 0);
        $expenseCounts = array_fill(0, count($trend['labels']), 0);
        foreach ($rows as $row) {
            foreach ($trend['from'] as $idx => $bucketFrom) {
                if ((string)$row['transaction_date'] >= $bucketFrom && (string)$row['transaction_date'] <= $trend['to'][$idx]) {
                    if ($row['transaction_type'] === 'income') $incomeCounts[$idx]++;
                    else $expenseCounts[$idx]++;
                    break;
                }
            }
        }
        $activity = ['labels' => $trend['labels'], 'ranges' => $trend['ranges'], 'income' => $incomeCounts, 'expenses' => $expenseCounts, 'profit' => array_map(static fn($i, $e) => $i + $e, $incomeCounts, $expenseCounts), 'legend' => ['income' => 'Income entries', 'expenses' => 'Expense entries', 'profit' => 'Total entries'], 'unit' => 'count'];
        $net = array_map(static fn($i, $e) => round((float)$i - (float)$e, 2), $trend['income'], $trend['expenses']);
        $netTrend = ['labels' => $trend['labels'], 'ranges' => $trend['ranges'], 'income' => array_map(static fn($v) => max(0, $v), $net), 'expenses' => array_map(static fn($v) => max(0, -$v), $net), 'profit' => $net, 'legend' => ['income' => 'Positive net flow', 'expenses' => 'Negative net flow', 'profit' => 'Net flow']];
        // Real per-period detail (dates, balance, categories, biggest items) for the interactive graphs and the AI insight.
        try {
            $detail = chart_bucket_details(db(), (int)$user['id'], $trend, (float)($user['opening_balance'] ?? 0));
            $trend = chart_attach_details($trend, $detail);
            $netTrend = chart_attach_details($netTrend, $detail);
            $activity = chart_attach_details($activity, $detail);
        } catch (Throwable $e) { error_log('[Imali Smart] Chart details failed: ' . $e->getMessage()); }
    } catch (Throwable $e) {}
}

$donutColors = ['#f59e0b', '#16a34a', '#0284c7', '#8b5cf6', '#10b981', '#f43f5e', '#ea580c'];
$page_title = 'Financial Analytics';
$page_js = 'charts.js';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">INTELLIGENCE &amp; TRENDS</span>
        <h1>Analytics</h1>
        <p>See how much came in, where it went, and how much you kept.</p>
    </div>
</div>

<?php render_period_bar('analytics.php', $from, $to, '', $groupBy); ?>

<div class="stat-grid" style="margin-bottom: 24px;">
    <div class="stat-card stat-card--mint">
        <span class="stat-card__label">Savings Rate</span>
        <strong class="stat-card__value"><?= $analytics['savings_rate'] ?>%</strong>
        <span class="stat-card__note"><?= $analytics['income_total'] <= 0 ? 'No income recorded in this period' : ($analytics['savings_rate'] >= 20 ? 'Healthy: you keep 20% or more' : 'Aim to keep at least 20% of income') ?></span>
    </div>
    <div class="stat-card <?= $analytics['net_savings'] >= 0 ? 'stat-card--gold' : 'stat-card--rose' ?>">
        <span class="stat-card__label">Net Surplus</span>
        <strong class="stat-card__value"><?= ($analytics['net_savings'] < 0 ? '-' : '') . money(abs($analytics['net_savings'])) ?></strong>
        <span class="stat-card__note">Income minus expenses</span>
    </div>
    <div class="stat-card stat-card--rose">
        <span class="stat-card__label">Biggest Single Expense</span>
        <strong class="stat-card__value"><?= $analytics['largest_expense'] ? money($analytics['largest_expense']['amount']) : '—' ?></strong>
        <span class="stat-card__note"><?= $analytics['largest_expense'] ? e($analytics['largest_expense']['description']) : 'None recorded' ?></span>
    </div>
</div>

<section class="panel" style="margin-bottom: 24px;">
    <div class="panel__heading">
        <div>
            <h2>Income vs Expenses</h2>
            <p>Per <?= $trend['granularity'] ?> · move over the chart for dates, amounts and more; click a period for an AI explanation</p>
        </div>
    </div>
    <div class="chart-frame" style="min-height: 240px; padding: 10px; position: relative;">
        <canvas data-cashflow-chart data-chart='<?= json_encode($trend, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>' style="width: 100%; height: 240px;" aria-label="Income versus expenses"></canvas>
    </div>
    <div style="display: flex; gap: 22px; justify-content: center; flex-wrap: wrap; padding-top: 10px; font-size: 12px; color: var(--ink-soft);">
        <span><i class="chart-legend__dot" style="background:#16a34a;display:inline-block"></i> Income <strong style="color:var(--ink)"><?= money($analytics['income_total']) ?></strong></span>
        <span><i class="chart-legend__dot" style="background:#dc2626;display:inline-block"></i> Expenses <strong style="color:var(--ink)"><?= money($analytics['expense_total']) ?></strong></span>
    </div>
</section>

<div class="dashboard-grid" style="margin-bottom:24px;">
    <section class="panel">
        <div class="panel__heading"><div><h2>Net Flow Momentum</h2><p>Surplus and deficit for each selected <?= e($trend['granularity']) ?> bucket. Hover to see the exact date range.</p></div></div>
        <div class="chart-frame" style="min-height:240px;padding:10px;position:relative;"><canvas data-cashflow-chart data-chart='<?= json_encode($netTrend, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>' style="width:100%;height:240px" aria-label="Net flow momentum"></canvas></div>
    </section>
    <section class="panel">
        <div class="panel__heading"><div><h2>Transaction Activity</h2><p>How many income and expense records were added in each <?= e($trend['granularity']) ?> bucket.</p></div></div>
        <div class="chart-frame" style="min-height:240px;padding:10px;position:relative;"><canvas data-cashflow-chart data-chart='<?= json_encode($activity, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>' style="width:100%;height:240px" aria-label="Transaction activity"></canvas></div>
    </section>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel__heading">
            <div>
                <h2>Where Your Money Went</h2>
                <p>Expenses by category</p>
            </div>
        </div>
        <?php if (!empty($analytics['category_breakdown'])) : ?>
            <div style="display: flex; justify-content: center; align-items: center; padding: 12px;">
                <canvas data-donut-chart data-chart='<?= json_encode(['items' => $analytics['category_breakdown'], 'total' => $analytics['expense_total']], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>' style="width: 220px; height: 220px;" aria-label="Expense breakdown by category"></canvas>
            </div>
            <div class="chart-legend">
                <?php foreach ($analytics['category_breakdown'] as $idx => $cat) :
                    $pct = $analytics['expense_total'] > 0 ? round($cat['value'] / $analytics['expense_total'] * 100) : 0; ?>
                    <div class="chart-legend__row">
                        <span class="chart-legend__dot" style="background: <?= e($donutColors[$idx % count($donutColors)]) ?>;"></span>
                        <span><?= e($cat['name']) ?> <small><?= $pct ?>%</small></span>
                        <b><?= money($cat['value']) ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="chart-empty">
                <span style="font-size: 32px; display: block; margin-bottom: 12px;">▥</span>
                <p style="margin: 0 0 12px; font-weight: 600; font-size: 14px; color: var(--ink);">No expenses in this period</p>
                <p style="margin: 0 0 16px; font-size: 13px;">Try a wider date range, or record an expense.</p>
                <a href="transactions.php?action=add&type=expense" class="btn btn--small btn--accent">Record Expense</a>
            </div>
        <?php endif; ?>
    </section>

    <aside class="panel">
        <div class="panel__heading">
            <div>
                <h2>How You Are Doing</h2>
                <p>Compared with the 80 / 20 rule: spend up to 80%, keep at least 20%</p>
            </div>
        </div>
        <?php if ($analytics['income_total'] > 0) :
            $spendOk = $analytics['spend_rate'] <= 80; $saveOk = $analytics['savings_rate'] >= 20; ?>
            <div style="display: flex; flex-direction: column; gap: 20px; margin-top: 10px;">
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                        <span>Spent of income <small style="color: var(--ink-faint);">(target ≤ 80%)</small></span>
                        <strong style="color: <?= $spendOk ? '#16a34a' : '#dc2626' ?>;"><?= $analytics['spend_rate'] ?>%</strong>
                    </div>
                    <div class="meter"><div style="width: <?= min(100, $analytics['spend_rate']) ?>%; background: <?= $spendOk ? '#16a34a' : '#dc2626' ?>;"></div></div>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                        <span>Kept as savings <small style="color: var(--ink-faint);">(target ≥ 20%)</small></span>
                        <strong style="color: <?= $saveOk ? '#16a34a' : '#d97706' ?>;"><?= $analytics['savings_rate'] ?>%</strong>
                    </div>
                    <div class="meter"><div style="width: <?= min(100, $analytics['savings_rate']) ?>%; background: <?= $saveOk ? '#16a34a' : '#d97706' ?>;"></div></div>
                </div>
                <p style="font-size: 13px; color: var(--ink-soft); margin: 0;">
                    <?= $spendOk && $saveOk ? 'Well done: you are spending within your means and saving at a healthy rate.' : ($analytics['spend_rate'] > 100 ? 'You spent more than you earned in this period. Look at the biggest categories on the left first.' : 'Try to trim your largest category a little so more of your income is kept.') ?>
                </p>
            </div>
        <?php else : ?>
            <div class="chart-empty">No income recorded in this period, so there is nothing to compare yet.</div>
        <?php endif; ?>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
