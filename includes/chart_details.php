<?php
/**
 * Real, per-period detail for the interactive graphs.
 *
 * Every figure comes straight from the user's own `transactions` rows (soft-deleted rows excluded).
 * The graphs use this for the live readout while the cursor moves, and the AI insight endpoint
 * recomputes the same facts server-side, so nothing the AI sees is taken from the browser.
 */
declare(strict_types=1);

// The AI button needs a real CSRF token, so make sure the helper is loaded on every page that builds chart data.
require_once __DIR__ . '/csrf.php';

/** Facts for one date range: totals, categories, biggest items, comparison with the previous range. */
function chart_range_facts(PDO $pdo, int $userId, string $from, string $to, float $openingBalance = 0.0): array
{
    $stmt = $pdo->prepare("SELECT t.transaction_date, t.transaction_type, t.amount, t.description, COALESCE(NULLIF(c.name, ''), 'Uncategorised') AS category_name
        FROM transactions t LEFT JOIN expense_categories c ON c.id = t.category_id
        WHERE t.user_id = :u AND t.deleted_at IS NULL AND t.transaction_type IN ('income','expense') AND t.transaction_date BETWEEN :from AND :to
        ORDER BY t.transaction_date ASC, t.id ASC");
    $stmt->execute(['u' => $userId, 'from' => $from, 'to' => $to]);
    $rows = $stmt->fetchAll();

    $days = max(1, (int)(new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1);
    $facts = ['from' => $from, 'to' => $to, 'days' => $days, 'income' => 0.0, 'expenses' => 0.0, 'net' => 0.0, 'count' => 0, 'income_count' => 0, 'expense_count' => 0,
        'categories' => [], 'largest_expense' => null, 'largest_income' => null, 'top' => [], 'balance_end' => $openingBalance, 'avg_daily_spend' => 0.0,
        'prev' => ['from' => null, 'to' => null, 'income' => 0.0, 'expenses' => 0.0], 'expense_change_pct' => null, 'income_change_pct' => null, 'busiest_day' => null];
    $perDay = [];
    foreach ($rows as $r) {
        $amt = round((float)$r['amount'], 2);
        $facts['count']++;
        $perDay[$r['transaction_date']] = ($perDay[$r['transaction_date']] ?? 0) + 1;
        $item = ['date' => (string)$r['transaction_date'], 'description' => (string)$r['description'], 'type' => (string)$r['transaction_type'], 'amount' => $amt];
        if ($r['transaction_type'] === 'income') {
            $facts['income'] += $amt; $facts['income_count']++;
            if (!$facts['largest_income'] || $amt > $facts['largest_income']['amount']) $facts['largest_income'] = $item;
        } else {
            $facts['expenses'] += $amt; $facts['expense_count']++;
            $facts['categories'][$r['category_name']] = ($facts['categories'][$r['category_name']] ?? 0.0) + $amt;
            if (!$facts['largest_expense'] || $amt > $facts['largest_expense']['amount']) $facts['largest_expense'] = $item;
        }
        $facts['top'][] = $item;
    }
    usort($facts['top'], static fn($a, $b) => $b['amount'] <=> $a['amount']);
    $facts['top'] = array_slice($facts['top'], 0, 3);
    arsort($facts['categories']);
    $cats = [];
    foreach (array_slice($facts['categories'], 0, 4, true) as $name => $value) $cats[] = ['name' => $name, 'amount' => round($value, 2), 'share' => $facts['expenses'] > 0 ? round($value / $facts['expenses'] * 100, 1) : 0.0];
    $facts['categories'] = $cats;
    $facts['income'] = round($facts['income'], 2);
    $facts['expenses'] = round($facts['expenses'], 2);
    $facts['net'] = round($facts['income'] - $facts['expenses'], 2);
    $facts['avg_daily_spend'] = round($facts['expenses'] / $days, 2);
    if ($perDay) { arsort($perDay); $facts['busiest_day'] = ['date' => (string)array_key_first($perDay), 'count' => (int)reset($perDay)]; }

    // Running balance at the end of the range: opening balance plus every transaction up to and including `to`.
    $b = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END), 0) FROM transactions WHERE user_id = :u AND deleted_at IS NULL AND transaction_type IN ('income','expense') AND transaction_date <= :to");
    $b->execute(['u' => $userId, 'to' => $to]);
    $facts['balance_end'] = round($openingBalance + (float)$b->fetchColumn(), 2);

    // Previous range of the same length, so the AI and the tooltip can say "up/down compared with before".
    $prevTo = (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
    $prevFrom = (new DateTimeImmutable($from))->modify('-' . $days . ' days')->format('Y-m-d');
    $p = $pdo->prepare("SELECT transaction_type, COALESCE(SUM(amount), 0) total FROM transactions WHERE user_id = :u AND deleted_at IS NULL AND transaction_type IN ('income','expense') AND transaction_date BETWEEN :from AND :to GROUP BY transaction_type");
    $p->execute(['u' => $userId, 'from' => $prevFrom, 'to' => $prevTo]);
    $facts['prev']['from'] = $prevFrom; $facts['prev']['to'] = $prevTo;
    foreach ($p->fetchAll() as $row) $facts['prev'][$row['transaction_type'] === 'income' ? 'income' : 'expenses'] = round((float)$row['total'], 2);
    if ($facts['prev']['expenses'] > 0) $facts['expense_change_pct'] = round(($facts['expenses'] - $facts['prev']['expenses']) / $facts['prev']['expenses'] * 100, 1);
    if ($facts['prev']['income'] > 0) $facts['income_change_pct'] = round(($facts['income'] - $facts['prev']['income']) / $facts['prev']['income'] * 100, 1);
    return $facts;
}

/**
 * Details for every bucket of a cash-flow series (as built by build_cashflow_series).
 * @return array{buckets: array<int,array>, summary: array}
 */
function chart_bucket_details(PDO $pdo, int $userId, array $series, float $openingBalance = 0.0): array
{
    $starts = array_values($series['from'] ?? []);
    $ends = array_values($series['to'] ?? []);
    $n = min(count($starts), count($ends));
    if ($n < 1) return ['buckets' => [], 'summary' => null];

    $stmt = $pdo->prepare("SELECT t.transaction_date, t.transaction_type, t.amount, t.description, COALESCE(NULLIF(c.name, ''), 'Uncategorised') AS category_name
        FROM transactions t LEFT JOIN expense_categories c ON c.id = t.category_id
        WHERE t.user_id = :u AND t.deleted_at IS NULL AND t.transaction_type IN ('income','expense') AND t.transaction_date BETWEEN :from AND :to
        ORDER BY t.transaction_date ASC, t.id ASC");
    $stmt->execute(['u' => $userId, 'from' => $starts[0], 'to' => $ends[$n - 1]]);
    $rows = $stmt->fetchAll();

    $before = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END), 0) FROM transactions WHERE user_id = :u AND deleted_at IS NULL AND transaction_type IN ('income','expense') AND transaction_date < :from");
    $before->execute(['u' => $userId, 'from' => $starts[0]]);
    $balance = $openingBalance + (float)$before->fetchColumn();

    $buckets = [];
    for ($i = 0; $i < $n; $i++) {
        $buckets[$i] = ['index' => $i, 'from' => $starts[$i], 'to' => $ends[$i], 'income' => 0.0, 'expenses' => 0.0, 'count' => 0, 'income_count' => 0, 'expense_count' => 0,
            'cats' => [], 'largest' => null, 'days' => max(1, (int)(new DateTimeImmutable($starts[$i]))->diff(new DateTimeImmutable($ends[$i]))->days + 1)];
    }
    foreach ($rows as $r) {
        $d = (string)$r['transaction_date'];
        for ($i = 0; $i < $n; $i++) {
            if ($d < $starts[$i] || $d > $ends[$i]) continue;
            $amt = (float)$r['amount'];
            $buckets[$i]['count']++;
            if ($r['transaction_type'] === 'income') { $buckets[$i]['income'] += $amt; $buckets[$i]['income_count']++; }
            else {
                $buckets[$i]['expenses'] += $amt; $buckets[$i]['expense_count']++;
                $buckets[$i]['cats'][$r['category_name']] = ($buckets[$i]['cats'][$r['category_name']] ?? 0.0) + $amt;
                if (!$buckets[$i]['largest'] || $amt > $buckets[$i]['largest']['amount']) $buckets[$i]['largest'] = ['description' => (string)$r['description'], 'amount' => round($amt, 2), 'date' => $d];
            }
            break;
        }
    }
    $totalIn = 0.0; $totalOut = 0.0; $totalCount = 0; $best = 0; $worst = 0;
    foreach ($buckets as $i => &$b) {
        $b['income'] = round($b['income'], 2); $b['expenses'] = round($b['expenses'], 2); $b['net'] = round($b['income'] - $b['expenses'], 2);
        $balance += $b['net']; $b['balance'] = round($balance, 2);
        arsort($b['cats']);
        $name = array_key_first($b['cats']);
        $b['top_category'] = $name !== null ? ['name' => $name, 'amount' => round($b['cats'][$name], 2), 'share' => $b['expenses'] > 0 ? round($b['cats'][$name] / $b['expenses'] * 100) : 0] : null;
        unset($b['cats']);
        $b['avg_daily_spend'] = round($b['expenses'] / $b['days'], 2);
        $prev = $i > 0 ? $buckets[$i - 1] : null;
        $b['expense_change_pct'] = ($prev && $prev['expenses'] > 0) ? round(($b['expenses'] - $prev['expenses']) / $prev['expenses'] * 100, 1) : null;
        $b['income_change_pct'] = ($prev && $prev['income'] > 0) ? round(($b['income'] - $prev['income']) / $prev['income'] * 100, 1) : null;
        $totalIn += $b['income']; $totalOut += $b['expenses']; $totalCount += $b['count'];
        if ($b['net'] > $buckets[$best]['net']) $best = $i;
        if ($b['net'] < $buckets[$worst]['net']) $worst = $i;
    }
    unset($b);
    $summary = ['from' => $starts[0], 'to' => $ends[$n - 1], 'income' => round($totalIn, 2), 'expenses' => round($totalOut, 2), 'net' => round($totalIn - $totalOut, 2), 'count' => $totalCount,
        'balance' => $buckets[$n - 1]['balance'], 'best_index' => $best, 'worst_index' => $worst];
    return ['buckets' => $buckets, 'summary' => $summary];
}

/** Adds `details`, `summary` and the AI endpoint settings to a chart payload. Never throws: charts still work without it. */
function chart_attach_details(array $chart, array $detail, bool $aiEnabled = true): array
{
    if (!$detail['buckets']) return $chart;
    $chart['details'] = $detail['buckets'];
    $chart['summary'] = $detail['summary'];
    if ($aiEnabled) $chart['ai'] = ['url' => 'api/charts/insight.php', 'csrf' => csrf_token()];
    return $chart;
}

/** Plain-language fallback used when Gemini is unavailable. Uses the same real numbers. */
function chart_rules_insight(array $f): string
{
    $r = static fn(float $v): string => 'R ' . number_format(abs($v), 2, '.', ',');
    if ($f['count'] === 0) return 'No income or expenses were recorded between ' . $f['from'] . ' and ' . $f['to'] . '. Record or import your transactions to see insights for this period.';
    $parts = [];
    $parts[] = 'You received ' . $r($f['income']) . ' and spent ' . $r($f['expenses']) . ', so you ' . ($f['net'] >= 0 ? 'kept ' : 'overspent by ') . $r($f['net']) . '.';
    if ($f['categories']) $parts[] = 'Most of your spending went on ' . $f['categories'][0]['name'] . ' (' . $r($f['categories'][0]['amount']) . ', ' . round($f['categories'][0]['share']) . '% of expenses).';
    if ($f['expense_change_pct'] !== null) $parts[] = 'Spending is ' . ($f['expense_change_pct'] >= 0 ? 'up ' : 'down ') . abs($f['expense_change_pct']) . '% compared with the previous ' . $f['days'] . ' days.';
    if ($f['income'] > 0 && $f['expenses'] / $f['income'] > 0.8) $parts[] = 'You spent more than 80% of what came in. Trimming your biggest category a little would help you keep at least 20%.';
    elseif ($f['income'] > 0) $parts[] = 'You are keeping more than 20% of your income, which is a healthy rate.';
    return implode(' ', $parts);
}
