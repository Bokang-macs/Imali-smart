<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_auth();
$user = current_user();
$languages = available_languages();
$languageCode = (string)($user['preferred_language'] ?? 'en');
$languageName = isset($languages[$languageCode]) ? (string)$languages[$languageCode]['name'] . ' - ' . (string)$languages[$languageCode]['native'] : 'English';
$statementSlogan = 'Plan with confidence, track with clarity';
$report = (string)($_GET['report'] ?? 'statement');
$format = strtolower((string)($_GET['format'] ?? 'csv'));
$from = is_valid_ymd($_GET['from'] ?? null) ? (string)$_GET['from'] : date('Y-m-01');
$to = is_valid_ymd($_GET['to'] ?? null) ? (string)$_GET['to'] : date('Y-m-d');
// "months=N" (1-60) exports the current month plus the N-1 months before it, up to today.
$months = filter_var($_GET['months'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
if ($months !== false && $months !== null) {
    $from = (new DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months')->format('Y-m-d');
    $to = date('Y-m-d');
}
[$from, $to] = normalize_period($from, $to); // reversed ranges are put the right way round, as on the report pages
$allowed = ['statement', 'transactions', 'income', 'expenses', 'budgets', 'savings'];
if (!in_array($report, $allowed, true)) $report = 'statement';
if (!in_array($format, ['csv', 'excel', 'xls', 'print', 'pdf'], true)) $format = 'csv';
$rows = [];
$title = ucfirst($report) . ' report';
if (database_ready()) {
    try {
        $pdo = db();
        if (in_array($report, ['statement', 'transactions', 'income', 'expenses'], true)) {
            $sql = "SELECT t.transaction_date,t.description,t.transaction_type,t.amount,COALESCE(c.name,'General') category,COALESCE(t.payment_method,'') payment_method,COALESCE(t.reference_code,'') reference_code FROM transactions t LEFT JOIN expense_categories c ON c.id=t.category_id WHERE t.user_id=:u AND t.deleted_at IS NULL AND t.transaction_date BETWEEN :from AND :to";
            if ($report === 'income') $sql .= " AND t.transaction_type='income'";
            if ($report === 'expenses') $sql .= " AND t.transaction_type='expense'";
            $sql .= ' ORDER BY t.transaction_date ASC,t.id ASC';
            $q = $pdo->prepare($sql); $q->execute(['u'=>$user['id'], 'from'=>$from, 'to'=>$to]); $rows = $q->fetchAll();
        } elseif ($report === 'budgets') {
            $q = $pdo->prepare("SELECT b.name,b.amount,b.period_start,b.period_end,COALESCE(SUM(t.amount),0) spent FROM budgets b LEFT JOIN transactions t ON t.budget_id=b.id AND t.user_id=b.user_id AND t.deleted_at IS NULL AND t.transaction_type='expense' AND t.transaction_date BETWEEN b.period_start AND b.period_end WHERE b.user_id=:u GROUP BY b.id,b.name,b.amount,b.period_start,b.period_end ORDER BY b.name");
            $q->execute(['u'=>$user['id']]); $rows = $q->fetchAll();
        } else {
            $q = $pdo->prepare('SELECT name,target_amount,current_amount,target_date,category,status FROM savings_goals WHERE user_id=:u ORDER BY name');
            $q->execute(['u'=>$user['id']]); $rows = $q->fetchAll();
        }
    } catch (Throwable $e) { http_response_code(500); exit('Could not build this report.'); }
}
$filename = 'imali-smart-' . $report . '-' . date('Ymd-His');
$logoUrl = public_base_url() . '/assets/logo/imali-logo.png';
record_audit_event((int)$user['id'], 'report.downloaded', 'report', null, ['report'=>$report, 'format'=>$format, 'from'=>$from, 'to'=>$to]);

// Printable statements get a private link. The QR code on the page opens that link, so whoever scans it sees the statement itself.
if ($format === 'print' || $format === 'pdf') {
    require_once dirname(__DIR__, 2) . '/includes/statement_view.php';
    $snapshot = statement_build_snapshot($report, $from, $to, $rows, $user, $languageName);
    $share = create_statement_share((int)$user['id'], $report, $from, $to, $languageCode, $snapshot);
    render_statement_page([
        'snapshot' => $snapshot,
        'url' => $share['url'] ?? '',
        'show_print' => true,
        'shared_view' => false,
        'local_warning' => $share ? is_local_address($share['url']) : false,
    ]);
    exit;
}

$exportFormat = $format === 'xls' ? 'excel' : $format;
try {
    $export = db()->prepare("INSERT INTO report_exports (user_id,report_type,export_format,period_start,period_end,file_name,language_code,template_version,qr_target,page_count) VALUES (:user,:report,:format,:from,:to,:file,:language,:template,:qr,:pages)");
    $export->execute(['user'=>$user['id'],'report'=>$report,'format'=>$exportFormat,'from'=>$from,'to'=>$to,'file'=>$filename,'language'=>$languageCode,'template'=>'imali-editorial-v2','qr'=>null,'pages'=>1]);
} catch (Throwable $ignored) { /* The history table is optional on older installations. */ }

$headers = array_keys($rows[0] ?? ['No records' => '']);
if ($format === 'excel' || $format === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    ?><html><head><meta charset="utf-8"><style>body{font-family:Arial;color:#172033;background:#fff}body:before{content:"";position:fixed;inset:0;background:url(<?=e($logoUrl)?>) center 46% / 420px no-repeat;opacity:.045;pointer-events:none}body>*{position:relative}.tagline{color:#17803a;font-weight:700;letter-spacing:.04em}table{border-collapse:collapse;width:100%}th{background:#0b1220;color:#fff}th,td{border:1px solid #dbe3ea;padding:8px;text-align:left}.brand{font-size:20px;font-weight:700;color:#0b1220}.muted{color:#64748b}</style></head><body><p class="brand">Imali Smart</p><p class="tagline">Your money. Your future. Smarter.</p><p class="muted"><?=e($title)?> · <?=e($from)?> to <?=e($to)?></p><p class="muted">Language: <?=e($languageName)?></p><table><thead><tr><?php foreach ($headers as $h): ?><th><?=e(ucwords(str_replace('_', ' ', (string)$h)))?></th><?php endforeach; ?></tr></thead><tbody><?php if (!$rows): ?><tr><td>No records in selected period.</td></tr><?php else: foreach ($rows as $row): ?><tr><?php foreach ($row as $value): ?><td><?=e((string)$value)?></td><?php endforeach; ?></tr><?php endforeach; endif; ?></tbody></table><p class="muted">Imali Smart · Your money. Your future. Smarter. · Generated <?=e(date('Y-m-d H:i') . ' ' . date_default_timezone_get())?></p></body></html><?php
    exit;
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Imali Smart - ' . ucfirst($report) . ' report']);
fputcsv($out, ['Account', $user['full_name']]);
fputcsv($out, ['Period', $from . ' to ' . $to]);
fputcsv($out, ['Generated', date('Y-m-d H:i') . ' (' . date_default_timezone_get() . ')']);
fputcsv($out, ['Language', $languageName]);
fputcsv($out, []);
if ($rows) { fputcsv($out, $headers); foreach ($rows as $row) fputcsv($out, $row); } else fputcsv($out, ['No records in selected period.']);
fputcsv($out, []); fputcsv($out, ['Imali Smart', 'Personal money confidence workspace']);
fclose($out);
