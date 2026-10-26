<?php
// Small helper functions used on many pages (money format, dates, menu).
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/**
 * Records a meaningful user action without interrupting the finance workflow if
 * an older database has not yet received the newest audit columns.
 */
function record_audit_event(
    int $userId,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    array $details = [],
    string $result = 'success'
): void {
    if ($userId < 1 || !function_exists('database_ready') || !database_ready()) return;
    try {
        $pdo = db();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $idDefinition = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_events (
            id {$idDefinition},
            user_id INTEGER NULL,
            action VARCHAR(120) NOT NULL,
            event_type VARCHAR(120) NOT NULL,
            result VARCHAR(30) NOT NULL DEFAULT 'success',
            entity_type VARCHAR(100) NULL,
            entity_id INTEGER NULL,
            details_json TEXT NULL,
            occurred_at DATETIME(6) NOT NULL,
            created_at DATETIME(6) NOT NULL
        )");
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s.u');
        $requestId = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8)));
        $payload = array_merge($details, [
            'request_id' => substr($requestId, 0, 80),
            'ip_address' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
        try {
            $stmt = $pdo->prepare('INSERT INTO audit_events (user_id,action,event_type,result,entity_type,entity_id,details_json,occurred_at,created_at) VALUES (:user_id,:action,:event_type,:result,:entity_type,:entity_id,:details,:occurred_at,:created_at)');
            $stmt->execute(['user_id'=>$userId,'action'=>$action,'event_type'=>$action,'result'=>$result,'entity_type'=>$entityType,'entity_id'=>$entityId,'details'=>json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'occurred_at'=>$now,'created_at'=>$now]);
        } catch (Throwable $legacy) {
            // Compatibility with the previous audit_events shape.
            $stmt = $pdo->prepare('INSERT INTO audit_events (user_id,event_type,entity_type,entity_id,metadata_json,created_at) VALUES (:user_id,:event_type,:entity_type,:entity_id,:metadata,:created_at)');
            $stmt->execute(['user_id'=>$userId,'event_type'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId,'metadata'=>json_encode(array_merge($payload,['result'=>$result,'occurred_at'=>$now]), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'created_at'=>$now]);
        }
    } catch (Throwable $ignored) {
        error_log('[Imali Smart] Audit event could not be recorded: ' . $ignored->getMessage());
    }
}

// Formats a number as rand, for example R 1,500.00, supporting millions and billions accurately.
function money(float|int|string|null $amount, string $currency = 'R'): string
{
    $val = parse_money_input($amount);
    $sign = $val < 0 ? '-' : '';
    return $sign . $currency . ' ' . number_format(abs($val), 2, '.', ',');
}

// Safely parses user-entered monetary amounts, supporting R symbols, commas, and decimals.
function parse_money_input(mixed $val): float
{
    if (is_float($val) || is_int($val)) {
        return (float) $val;
    }
    if (!is_string($val)) {
        return 0.0;
    }
    $clean = trim($val);
    if ($clean === '') {
        return 0.0;
    }
    // Remove leading currency symbols or spaces (R, ZAR, etc.)
    $clean = preg_replace('/^[^\d.-]+/', '', $clean);
    // Remove space or comma thousands separators
    $clean = str_replace([' ', ','], ['', ''], $clean);
    return is_numeric($clean) ? (float) $clean : 0.0;
}

// Formats monetary values with compact notation for small spaces or chart badges (e.g. R 25M, R 1.5M, R 250k).
function compact_money(float|int|string|null $amount, string $currency = 'R'): string
{
    $val = parse_money_input($amount);
    $abs = abs($val);
    $sign = $val < 0 ? '-' : '';
    if ($abs >= 1e9) {
        return $sign . $currency . ' ' . number_format($abs / 1e9, $abs >= 1e10 ? 0 : 1) . 'B';
    }
    if ($abs >= 1e6) {
        return $sign . $currency . ' ' . number_format($abs / 1e6, $abs >= 1e7 ? 0 : 1) . 'M';
    }
    if ($abs >= 1e4) {
        return $sign . $currency . ' ' . number_format($abs / 1e3, 0) . 'k';
    }
    return $sign . $currency . ' ' . number_format($abs, 2, '.', ',');
}

// Returns true if the current page matches the given name.
function page_is(string $page): bool
{
    return basename($_SERVER['PHP_SELF'] ?? '') === $page;
}

// Returns the list of menu items.
function app_nav_items(): array
{
    return [
        ['label'=>'Overview','href'=>'dashboard.php'],['label'=>'Transactions','href'=>'transactions.php'],['label'=>'Budgets','href'=>'budgets.php'],['label'=>'Savings Goals','href'=>'savings.php'],['label'=>'Debt Payoff','href'=>'debts.php'],['label'=>'Cash Flow','href'=>'cashflow.php'],['label'=>'Reports','href'=>'reports.php'],['label'=>'Ask Imali','href'=>'ai-assistant.php']
    ];
}

// Returns the menu items that belong to one group.
function nav_group(string $label, array $items): array
{
    return ['label' => $label, 'items' => $items];
}

// Returns a valid date or a default if the input is not a date.
function safe_date(string $date): string
{
    $time = strtotime($date);
    return $time ? date('j M Y', $time) : '—';
}

// True only for a real calendar date written as YYYY-MM-DD. "2026-02-31" is rejected instead of silently rolling into March.
function is_valid_ymd(mixed $value): bool
{
    if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

// A date typed for a ledger entry: it must be a real date and no more than one day ahead of South African time
// (the timezone is set in config/app.php). Returns YYYY-MM-DD, or null when the date is not acceptable.
function normalize_transaction_date(mixed $value): ?string
{
    $value = trim((string) $value);
    if (!is_valid_ymd($value)) {
        return null;
    }
    return $value > date('Y-m-d', strtotime('+1 day')) ? null : $value;
}

// Returns the start and end dates chosen by the user. Anything that is not a real date falls back to "this month so far".
function selected_period(): array
{
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    return [is_valid_ymd($from) ? $from : date('Y-m-01'), is_valid_ymd($to) ? $to : date('Y-m-d')];
}

// Returns which transaction scopes a workspace mode should include.
function mode_scopes(?string $mode = null): array
{
    return [];
}

// Returns a clean from/to period: valid dates, from before to, and never longer than ten years.
function normalize_period(string $from, string $to): array
{
    $f = strtotime($from) ?: strtotime(date('Y-m-01'));
    $t = strtotime($to) ?: strtotime(date('Y-m-d'));
    if ($f > $t) {
        [$f, $t] = [$t, $f];
    }
    if ($t - $f > 3660 * 86400) {
        $f = $t - 3660 * 86400;
    }
    return [date('Y-m-d', $f), date('Y-m-d', $t)];
}

/**
 * Turns per-day income/expense totals into chart data for a period.
 * $daily is a list of ['transaction_date' => 'Y-m-d', 'transaction_type' => 'income|expense', 'total' => number].
 * Up to 31 days shows one bar pair per day, up to about 20 weeks shows weeks, longer shows calendar months.
 */
function build_cashflow_series(array $daily, string $from, string $to, string $requestedGranularity = 'auto'): array
{
    $start = new DateTimeImmutable($from);
    $end = new DateTimeImmutable($to);
    $days = (int) $start->diff($end)->days + 1;

    $buckets = [];
    $requestedGranularity = in_array($requestedGranularity, ['auto', 'week', 'month'], true) ? $requestedGranularity : 'auto';
    if ($requestedGranularity === 'month' || ($requestedGranularity === 'auto' && $days > 140)) {
        $granularity = 'month';
        for ($d = $start->modify('first day of this month'); $d <= $end; $d = $d->modify('first day of next month')) {
            $monthEnd = $d->modify('last day of this month');
            $buckets[] = ['from' => $d < $start ? $start : $d, 'to' => $monthEnd > $end ? $end : $monthEnd, 'label' => $d->format('M y')];
        }
    } elseif ($requestedGranularity === 'week' || ($requestedGranularity === 'auto' && $days > 7)) {
        $granularity = 'week';
        for ($d = $start; $d <= $end; $d = $d->modify('+7 days')) {
            $last = $d->modify('+6 days');
            $weekEnd = $last > $end ? $end : $last;
            $label = $d->format('n') === $weekEnd->format('n')
                ? ($d->format('j') === $weekEnd->format('j') ? $d->format('j M') : $d->format('j') . '–' . $weekEnd->format('j M'))
                : $d->format('j M') . '–' . $weekEnd->format('j M');
            $buckets[] = ['from' => $d, 'to' => $weekEnd, 'label' => $label];
        }
    } elseif ($days <= 7) {
        $granularity = 'day';
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            $buckets[] = ['from' => $d, 'to' => $d, 'label' => $d->format('j M')];
        }
    } else {
        $granularity = 'month';
        for ($d = $start->modify('first day of this month'); $d <= $end; $d = $d->modify('first day of next month')) {
            $buckets[] = ['from' => $d, 'to' => $d->modify('last day of this month'), 'label' => $d->format('M y')];
        }
    }

    $labels = [];
    $income = array_fill(0, count($buckets), 0.0);
    $expenses = array_fill(0, count($buckets), 0.0);
    foreach ($buckets as $b) {
        $labels[] = $b['label'];
    }

    foreach ($daily as $row) {
        $date = (string) $row['transaction_date'];
        foreach ($buckets as $i => $b) {
            if ($date >= $b['from']->format('Y-m-d') && $date <= $b['to']->format('Y-m-d')) {
                if ($row['transaction_type'] === 'income') {
                    $income[$i] += (float) $row['total'];
                } elseif ($row['transaction_type'] === 'expense') {
                    $expenses[$i] += (float) $row['total'];
                }
                break;
            }
        }
    }

    $profit = [];
    foreach ($income as $i => $v) {
        $profit[] = round($v - $expenses[$i], 2);
    }

    return [
        'labels' => $labels,
        'income' => array_map(static fn ($v) => round($v, 2), $income),
        'expenses' => array_map(static fn ($v) => round($v, 2), $expenses),
        'profit' => $profit,
        'from' => array_map(static fn ($b) => $b['from']->format('Y-m-d'), $buckets),
        'to' => array_map(static fn ($b) => $b['to']->format('Y-m-d'), $buckets),
        'ranges' => array_map(static fn ($b) => $b['from']->format('Y-m-d') === $b['to']->format('Y-m-d') ? $b['from']->format('j M Y') : $b['from']->format('j M Y') . ' – ' . $b['to']->format('j M Y'), $buckets),
        'granularity' => $granularity,
    ];
}

// Quick date ranges shown as one-tap buttons above charts.
function period_presets(): array
{
    $today = date('Y-m-d');
    return [
        'This month' => [date('Y-m-01'), $today],
        'Last month' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
        'Last 3 months' => [date('Y-m-d', strtotime('-3 months +1 day')), $today],
        'This year' => [date('Y-01-01'), $today],
    ];
}

// Prints the one-tap range buttons plus a custom From / To form. $page is the page to reload, for example 'analytics.php'.
function render_period_bar(string $page, string $from, string $to, string $modeLabel = '', string $grouping = 'auto'): void
{
    echo '<div class="period-bar">';
    echo '<div class="period-bar__chips" role="group" aria-label="Quick ranges">';
    foreach (period_presets() as $label => [$f, $t]) {
        $on = ($f === $from && $t === $to);
        echo '<a class="period-chip' . ($on ? ' is-active' : '') . '" href="' . e($page) . '?from=' . e($f) . '&to=' . e($t) . '">' . e($label) . '</a>';
    }
    echo '</div>';
    echo '<form method="get" action="' . e($page) . '" class="period-bar__form">';
    echo '<label>View <select name="group_by"><option value="auto"' . ($grouping === 'auto' ? ' selected' : '') . '>Automatic</option><option value="week"' . ($grouping === 'week' ? ' selected' : '') . '>Weeks</option><option value="month"' . ($grouping === 'month' ? ' selected' : '') . '>Months</option></select></label>';
    echo '<label>From <input type="date" name="from" value="' . e($from) . '" max="' . e(date('Y-m-d')) . '"></label>';
    echo '<label>To <input type="date" name="to" value="' . e($to) . '"></label>';
    echo '<button class="btn btn--quiet btn--small" type="submit">' . e(t('apply')) . '</button>';
    echo '</form>';
    echo '</div>';
    echo '<style>
.period-bar{display:flex;flex-wrap:wrap;gap:12px 18px;align-items:center;margin:0 0 20px}
.period-bar__chips{display:flex;flex-wrap:wrap;gap:8px}
.period-chip{border:1px solid var(--line);background:var(--panel);color:var(--ink);border-radius:99px;padding:6px 14px;font-size:12.5px;font-weight:700;text-decoration:none}
.period-chip:hover{border-color:var(--marigold,#e8a33a)}
.period-chip.is-active{background:var(--marigold,#e8a33a);border-color:var(--marigold,#e8a33a);color:#18221f}
.period-bar__form{display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-size:12px;color:var(--ink-soft)}
.period-bar__form input{padding:6px 8px;border:1px solid var(--line);border-radius:8px;background:var(--panel);color:var(--ink);font-size:13px}
.period-bar__mode{margin-left:auto;font-size:12px;color:var(--ink-soft);text-decoration:none}
.period-bar__mode strong{color:var(--marigold-deep,#b45309)}
.chart-empty{text-align:center;padding:36px 16px;color:var(--ink-soft)}
.chart-legend{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;margin-top:14px}
.chart-legend__row{display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid var(--line);border-radius:10px;background:var(--paper-deep);font-size:13px}
.chart-legend__dot{width:12px;height:12px;border-radius:4px;flex:none}
.chart-legend__row b{margin-left:auto;color:var(--ink)}
.chart-legend__row small{color:var(--ink-faint)}
.meter{height:10px;background:var(--line);border-radius:6px;overflow:hidden}
.meter>div{height:100%;border-radius:6px}
</style>';
}

/**
 * Irregular-income summary: turns up-and-down monthly income into one steady "pay yourself" number.
 *
 * Plain-language rules (shown to the user on irregular-income.php):
 *  - Typical income   = average of the last 6 full months that had income.
 *  - Lean month       = the lowest of those months.
 *  - Steady pay       = 85% of typical income. The other 15% is kept back in good months.
 *  - Lean-month buffer target = 3 x the gap between average spending and the lean month (never below 0).
 *  - Safe to spend    = steady pay minus what has already gone out this month, spread over the days left.
 */
function irregular_income_summary(int $userId, array $scopes = []): array
{
    $out = [
        'months' => [], 'months_with_income' => 0, 'average_income' => 0.0, 'lean_income' => 0.0, 'best_income' => 0.0,
        'average_expenses' => 0.0, 'swing_pct' => 0.0, 'steady_pay' => 0.0, 'buffer_target' => 0.0, 'buffer_now' => 0.0,
        'spent_this_month' => 0.0, 'income_this_month' => 0.0, 'safe_left' => 0.0, 'safe_per_day' => 0.0, 'days_left' => 0,
        'ready' => false,
    ];
    if (!database_ready() || $userId < 1) return $out;

    try {
        $first = date('Y-m-01', strtotime('-6 months'));
        $st = db()->prepare("SELECT transaction_date, transaction_type, amount FROM transactions
            WHERE user_id = :u AND deleted_at IS NULL AND transaction_type IN ('income','expense')
              AND transaction_date >= :f");
        $st->execute(['u' => $userId, 'f' => $first]);
        $inc = []; $exp = [];
        foreach ($st->fetchAll() as $r) {
            $m = substr((string) $r['transaction_date'], 0, 7);
            if ($r['transaction_type'] === 'income') { $inc[$m] = ($inc[$m] ?? 0.0) + (float) $r['amount']; }
            else { $exp[$m] = ($exp[$m] ?? 0.0) + (float) $r['amount']; }
        }

        $thisMonth = date('Y-m');
        $full = [];
        for ($i = 6; $i >= 1; $i--) {
            $m = date('Y-m', strtotime("first day of -{$i} months"));
            $full[$m] = ['label' => date('M Y', strtotime($m . '-01')), 'income' => round($inc[$m] ?? 0.0, 2), 'expenses' => round($exp[$m] ?? 0.0, 2)];
        }
        $out['months'] = $full;

        $withIncome = array_filter($full, static fn ($m) => $m['income'] > 0);
        $out['months_with_income'] = count($withIncome);
        if ($withIncome) {
            $vals = array_column($withIncome, 'income');
            $out['average_income'] = round(array_sum($vals) / count($vals), 2);
            $out['lean_income'] = min($vals);
            $out['best_income'] = max($vals);
            $out['swing_pct'] = $out['average_income'] > 0 ? round(($out['best_income'] - $out['lean_income']) / $out['average_income'] * 100) : 0.0;
        }
        $expVals = array_filter(array_column($full, 'expenses'), static fn ($v) => $v > 0);
        $out['average_expenses'] = $expVals ? round(array_sum($expVals) / count($expVals), 2) : 0.0;

        $out['steady_pay'] = round($out['average_income'] * 0.85, 2);
        $out['buffer_target'] = round(3 * max(0.0, $out['average_expenses'] - $out['lean_income']), 2);

        $sv = db()->prepare('SELECT COALESCE(SUM(current_amount), 0) FROM savings_goals WHERE user_id = :u');
        $sv->execute(['u' => $userId]);
        $out['buffer_now'] = (float) $sv->fetchColumn();

        $out['income_this_month'] = round($inc[$thisMonth] ?? 0.0, 2);
        $out['spent_this_month'] = round($exp[$thisMonth] ?? 0.0, 2);
        $out['days_left'] = max(1, (int) date('t') - (int) date('j') + 1);
        $out['safe_left'] = round($out['steady_pay'] - $out['spent_this_month'], 2);
        $out['safe_per_day'] = round(max(0.0, $out['safe_left']) / $out['days_left'], 2);
        $out['ready'] = $out['months_with_income'] >= 2;
    } catch (Throwable $e) {}
    return $out;
}

// Works out the yearly cost of a loan (as a percentage) from the amount, monthly payment and number of months.
function loan_annual_rate(float $amount, float $payment, int $months): float
{
    if ($amount <= 0 || $payment <= 0 || $months < 1) return 0.0;
    if ($payment * $months <= $amount) return 0.0;
    $lo = 0.0; $hi = 5.0; // monthly rate between 0% and 500%
    for ($i = 0; $i < 80; $i++) {
        $mid = ($lo + $hi) / 2;
        $pv = $mid < 1e-9 ? $payment * $months : $payment * (1 - pow(1 + $mid, -$months)) / $mid;
        if ($pv > $amount) { $lo = $mid; } else { $hi = $mid; }
    }
    return round((pow(1 + ($lo + $hi) / 2, 12) - 1) * 100, 1);
}

// ==============================================================================
// Chart style (Settings > Charts). One choice that every graph in the app follows.
// ==============================================================================

// The chart styles a person can choose from.
function chart_style_options(): array
{
    return [
        'bar'  => ['label' => 'Bars',       'hint' => 'Compare amounts side by side. The easiest to read.'],
        'line' => ['label' => 'Trend line', 'hint' => 'See how your money moves up and down over time.'],
        'pie'  => ['label' => 'Pie',        'hint' => 'See each part as a share of the whole.'],
    ];
}

// Turns any saved or typed value into one of: bar, line, pie. Older values such as "graph" still work.
function normalize_chart_type(mixed $value): string
{
    $v = strtolower(trim((string) $value));
    if ($v === 'graph' || $v === 'bars') return 'bar';
    if ($v === 'trend') return 'line';
    if ($v === 'share' || $v === 'donut') return 'pie';
    return in_array($v, ['bar', 'line', 'pie'], true) ? $v : 'bar';
}

// Makes sure the chart-style column exists and accepts bar, line and pie (older databases only allowed graph or pie).
function ensure_chart_type_column(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $driver = function_exists('db_driver') ? db_driver() : (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            try {
                $pdo->query('SELECT preferred_chart_type FROM user_settings LIMIT 1');
            } catch (Throwable $e) {
                $pdo->exec("ALTER TABLE user_settings ADD COLUMN preferred_chart_type VARCHAR(10) NOT NULL DEFAULT 'bar'");
            }
            return;
        }
        $col = $pdo->query("SHOW COLUMNS FROM user_settings LIKE 'preferred_chart_type'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE user_settings ADD COLUMN preferred_chart_type VARCHAR(10) NOT NULL DEFAULT 'bar'");
        } elseif (stripos((string) ($col['Type'] ?? ''), 'enum') === 0) {
            $pdo->exec("ALTER TABLE user_settings MODIFY preferred_chart_type VARCHAR(10) NOT NULL DEFAULT 'bar'");
            $pdo->exec("UPDATE user_settings SET preferred_chart_type = 'bar' WHERE preferred_chart_type = 'graph'");
        }
    } catch (Throwable $e) {
        error_log('[Imali Smart] Could not prepare the chart style column: ' . $e->getMessage());
    }
}

// Reads the person's chosen chart style (bar when nothing is saved yet).
function get_chart_preference(int $userId): string
{
    if ($userId < 1 || !function_exists('database_ready') || !database_ready()) return 'bar';
    $read = static function () use ($userId): string {
        $q = db()->prepare('SELECT preferred_chart_type FROM user_settings WHERE user_id = :u');
        $q->execute(['u' => $userId]);
        $v = $q->fetchColumn();
        return $v === false ? 'bar' : normalize_chart_type($v);
    };
    try {
        return $read();
    } catch (Throwable $e) {
        try { ensure_chart_type_column(db()); return $read(); } catch (Throwable $e2) { return 'bar'; }
    }
}

// Saves the person's chart style. Returns true when it was stored.
function save_chart_preference(int $userId, string $type): bool
{
    $type = normalize_chart_type($type);
    if ($userId < 1 || !function_exists('database_ready') || !database_ready()) return false;
    $write = static function () use ($userId, $type): void {
        $pdo = db();
        $q = $pdo->prepare('SELECT 1 FROM user_settings WHERE user_id = :u LIMIT 1');
        $q->execute(['u' => $userId]);
        if ($q->fetchColumn()) {
            $w = $pdo->prepare('UPDATE user_settings SET preferred_chart_type = :t WHERE user_id = :u');
        } else {
            $w = $pdo->prepare('INSERT INTO user_settings (user_id, preferred_chart_type) VALUES (:u, :t)');
        }
        $w->execute(['u' => $userId, 't' => $type]);
    };
    $stored = static function () use ($userId): string {
        $q = db()->prepare('SELECT preferred_chart_type FROM user_settings WHERE user_id = :u');
        $q->execute(['u' => $userId]);
        return (string) $q->fetchColumn();
    };
    try {
        $write();
        // Some servers silently ignore a value an old column does not allow, so check what was really stored.
        if ($stored() !== $type) {
            ensure_chart_type_column(db());
            $write();
            return $stored() === $type;
        }
        return true;
    } catch (Throwable $e) {
        try { ensure_chart_type_column(db()); $write(); return true; }
        catch (Throwable $e2) { error_log('[Imali Smart] Chart style not saved: ' . $e2->getMessage()); return false; }
    }
}

// ==============================================================================
// Shareable statements. The QR code on a statement opens a private page that shows
// that statement, with no sign-in and no app.
// ==============================================================================

// The web address people reach this site on. Uses APP_URL on a live site, or the address in the browser when APP_URL is local.
function public_base_url(): string
{
    $env = rtrim((string) env_value('APP_URL', ''), '/');
    if ($env !== '' && !str_starts_with($env, 'MY_APP_URL')) {
        $host = strtolower((string) parse_url($env, PHP_URL_HOST));
        if (!in_array($host, ['localhost', '127.0.0.1', '::1', ''], true)) return app_base_url();
    }
    $proto = is_https_request() ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
    return $proto . '://' . $host . app_base_path();
}

// True when the address only works on this computer (a phone could not open it).
function is_local_address(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) return true;
    return (bool) preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $host) || str_ends_with($host, '.local');
}

function statement_public_url(string $token): string
{
    return public_base_url() . '/statement.php?t=' . rawurlencode($token);
}

// Adds the columns that store a shareable statement (older installs do not have them yet).
function ensure_report_share_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $driver = function_exists('db_driver') ? db_driver() : (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $idDef = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $pdo->exec("CREATE TABLE IF NOT EXISTS report_exports (
            id {$idDef}, user_id BIGINT NOT NULL, report_type VARCHAR(40) NOT NULL, export_format VARCHAR(20) NOT NULL,
            period_start DATE NOT NULL, period_end DATE NOT NULL, file_name VARCHAR(255) NULL, language_code VARCHAR(12) NOT NULL DEFAULT 'en',
            template_version VARCHAR(40) NOT NULL DEFAULT 'imali-editorial-v1', qr_target TEXT NULL, page_count SMALLINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP)");
        $existing = [];
        if ($driver === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info(report_exports)')->fetchAll() as $c) $existing[] = (string) $c['name'];
        } else {
            foreach ($pdo->query('SHOW COLUMNS FROM report_exports')->fetchAll() as $c) $existing[] = (string) $c['Field'];
        }
        $wanted = [
            'share_token' => 'VARCHAR(64) NULL',
            'snapshot_json' => 'LONGTEXT NULL',
            'share_expires_at' => 'DATETIME NULL',
            'share_revoked_at' => 'DATETIME NULL',
            'share_views' => 'INT NOT NULL DEFAULT 0',
            'share_last_viewed_at' => 'DATETIME NULL',
        ];
        $addedToken = false;
        foreach ($wanted as $name => $def) {
            if (!in_array($name, $existing, true)) {
                $pdo->exec("ALTER TABLE report_exports ADD COLUMN {$name} {$def}");
                if ($name === 'share_token') $addedToken = true;
            }
        }
        if ($addedToken) {
            try { $pdo->exec('CREATE UNIQUE INDEX uq_report_share_token ON report_exports (share_token)'); } catch (Throwable $ignored) {}
        }
    } catch (Throwable $e) {
        error_log('[Imali Smart] Could not prepare shareable statements: ' . $e->getMessage());
    }
}

// Saves a statement so its QR code can open it later. Returns ['token','url','expires'] or null.
function create_statement_share(int $userId, string $report, string $from, string $to, string $languageCode, array $snapshot): ?array
{
    if ($userId < 1 || !function_exists('database_ready') || !database_ready()) return null;
    try {
        $pdo = db();
        ensure_report_share_columns($pdo);
        $token = bin2hex(random_bytes(24));
        $url = statement_public_url($token);
        $expires = date('Y-m-d H:i:s', strtotime('+180 days'));
        $q = $pdo->prepare('INSERT INTO report_exports (user_id,report_type,export_format,period_start,period_end,file_name,language_code,template_version,qr_target,page_count,share_token,snapshot_json,share_expires_at) VALUES (:user,:report,:format,:from,:to,:file,:lang,:tpl,:qr,:pages,:token,:snap,:exp)');
        $q->execute([
            'user' => $userId, 'report' => $report, 'format' => 'pdf', 'from' => $from, 'to' => $to,
            'file' => 'imali-smart-' . $report . '-' . date('Ymd-His'), 'lang' => $languageCode, 'tpl' => 'imali-editorial-v2',
            'qr' => $url, 'pages' => 1, 'token' => $token,
            'snap' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            'exp' => $expires,
        ]);
        return ['token' => $token, 'url' => $url, 'expires' => $expires];
    } catch (Throwable $e) {
        error_log('[Imali Smart] Could not create a shareable statement: ' . $e->getMessage());
        return null;
    }
}

// Finds a shared statement from the code in its link. Returns ['snapshot'=>array,'url'=>string] or a string reason: invalid, expired, revoked.
function find_statement_share(string $token): array|string
{
    if (!preg_match('/^[a-f0-9]{32,64}$/', $token)) return 'invalid';
    if (!function_exists('database_ready') || !database_ready()) return 'invalid';
    try {
        $pdo = db();
        ensure_report_share_columns($pdo);
        $q = $pdo->prepare('SELECT id, snapshot_json, share_expires_at, share_revoked_at FROM report_exports WHERE share_token = :t LIMIT 1');
        $q->execute(['t' => $token]);
        $row = $q->fetch();
        if (!$row || empty($row['snapshot_json'])) return 'invalid';
        if (!empty($row['share_revoked_at'])) return 'revoked';
        if (!empty($row['share_expires_at']) && strtotime((string) $row['share_expires_at']) < time()) return 'expired';
        $snapshot = json_decode((string) $row['snapshot_json'], true);
        if (!is_array($snapshot)) return 'invalid';
        try {
            $u = $pdo->prepare('UPDATE report_exports SET share_views = share_views + 1, share_last_viewed_at = :now WHERE id = :id');
            $u->execute(['now' => date('Y-m-d H:i:s'), 'id' => $row['id']]);
        } catch (Throwable $ignored) {}
        return ['snapshot' => $snapshot, 'url' => statement_public_url($token)];
    } catch (Throwable $e) {
        error_log('[Imali Smart] Shared statement lookup failed: ' . $e->getMessage());
        return 'invalid';
    }
}
