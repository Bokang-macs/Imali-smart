<?php
// Cash flow page. Shows money coming in and going out over time.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_auth();
$user = current_user();
[$from, $to] = selected_period();
[$from, $to] = normalize_period($from, $to);
$groupBy = in_array((string)($_GET['group_by'] ?? 'auto'), ['auto', 'week', 'month'], true) ? (string)($_GET['group_by'] ?? 'auto') : 'auto';
$trend = build_cashflow_series([], $from, $to, $groupBy);

$inflows = [];
$outflows = [];
$totalIn = 0.0;
$totalOut = 0.0;

if (database_ready()) {
    try {
        $stmt = db()->prepare("
            SELECT transaction_type, amount, description, transaction_date 
            FROM transactions 
            WHERE user_id = :user_id AND deleted_at IS NULL AND transaction_type IN ('income', 'expense') AND transaction_date BETWEEN :from AND :to
            ORDER BY transaction_date DESC, id DESC
        ");
        $stmt->execute(['user_id' => $user['id'], 'from' => $from, 'to' => $to]);
        $rows = $stmt->fetchAll();

        $perDay = [];
        foreach ($rows as $r) {
            $k = $r['transaction_date'] . '|' . $r['transaction_type'];
            $perDay[$k] = ($perDay[$k] ?? 0.0) + (float) $r['amount'];
            $amt = (float) $r['amount'];
            if ($r['transaction_type'] === 'income') {
                $inflows[] = $r;
                $totalIn += $amt;
            } else {
                $outflows[] = $r;
                $totalOut += $amt;
            }
        }
        $daily = [];
        foreach ($perDay as $k => $v) { [$d, $ty] = explode('|', $k); $daily[] = ['transaction_date' => $d, 'transaction_type' => $ty, 'total' => $v]; }
        $trend = build_cashflow_series($daily, $from, $to, $groupBy);
    } catch (Throwable $e) {}
}

$netFlow = $totalIn - $totalOut;
$page_title = 'Cash Flow Overview';
$page_js = 'charts.js';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">LIQUIDITY &amp; RUNWAY</span>
        <h1>Cash Inflow &amp; Outflow Statement</h1>
        <p>Monitor real cleared cash entering and leaving your personal life.</p>
    </div>
</div>

<?php render_period_bar('cashflow.php', $from, $to, '', $groupBy); ?>

<div class="stat-grid" style="margin-bottom: 24px;">
    <div class="stat-card stat-card--mint">
        <span class="stat-card__label">Gross Cash Inflows</span>
        <strong class="stat-card__value">+<?= money($totalIn) ?></strong>
        <span class="stat-card__note">Selected period</span>
    </div>
    <div class="stat-card stat-card--rose">
        <span class="stat-card__label">Gross Cash Outflows</span>
        <strong class="stat-card__value"><?= money($totalOut) ?></strong>
        <span class="stat-card__note">Selected period</span>
    </div>
    <div class="stat-card <?= $netFlow >= 0 ? 'stat-card--gold' : 'stat-card--rose' ?>">
        <span class="stat-card__label">Net Operating Flow</span>
        <strong class="stat-card__value"><?= ($netFlow < 0 ? '-' : '+') . money(abs($netFlow)) ?></strong>
        <span class="stat-card__note"><?= $netFlow >= 0 ? 'Surplus capital retained' : 'Deficit burn rate' ?></span>
    </div>
</div>

<section class="panel" style="margin-bottom: 24px;">
    <div class="panel__heading"><div><h2>Money In vs Money Out</h2><p>Per <?= $trend['granularity'] ?> · hover a bar for exact amounts</p></div></div>
    <div class="chart-frame" style="min-height: 240px; padding: 10px; position: relative;">
        <canvas data-cashflow-chart data-chart='<?= json_encode($trend, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>' style="width: 100%; height: 240px;" aria-label="Money in versus money out"></canvas>
    </div>
</section>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel__heading">
            <div>
                <h2>Inflows (<?= count($inflows) ?>)</h2>
                <p>Salaries, allowances, grants and casual work</p>
            </div>
        </div>
        <?php if (empty($inflows)) : ?>
            <p class="muted" style="padding: 16px;">No inflows recorded for this period.</p>
        <?php else : ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Description</th><th style="text-align: right;">Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($inflows as $in) : ?>
                            <tr>
                                <td><?= e(safe_date((string) $in['transaction_date'])) ?></td>
                                <td><?= e((string) $in['description']) ?></td>
                                <td style="text-align: right; color: #22c55e; font-weight: 700;">+<?= money((float) $in['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel__heading">
            <div>
                <h2>Outflows (<?= count($outflows) ?>)</h2>
                <p>Living costs, essentials and bills</p>
            </div>
        </div>
        <?php if (empty($outflows)) : ?>
            <p class="muted" style="padding: 16px;">No outflows recorded for this period.</p>
        <?php else : ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Description</th><th style="text-align: right;">Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($outflows as $out) : ?>
                            <tr>
                                <td><?= e(safe_date((string) $out['transaction_date'])) ?></td>
                                <td><?= e((string) $out['description']) ?></td>
                                <td style="text-align: right; color: #f43f5e; font-weight: 700;"><?= money((float) $out['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
