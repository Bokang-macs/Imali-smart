<?php
// Builds and draws a personal money statement. The same page is used for the print view
// and for the public page opened by the QR code (statement.php).
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// Friendly column names for the statement table.
function statement_column_label(string $key): string
{
    $labels = [
        'transaction_date' => 'Date', 'description' => 'Description', 'transaction_type' => 'Type', 'amount' => 'Amount',
        'category' => 'Category', 'payment_method' => 'Payment method', 'reference_code' => 'Reference',
        'name' => 'Name', 'period_start' => 'From', 'period_end' => 'To', 'spent' => 'Spent',
        'target_amount' => 'Target', 'current_amount' => 'Saved', 'target_date' => 'Target date', 'status' => 'Status',
    ];
    return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
}

// Returns [text, css class] for one table cell.
function statement_cell(string $key, mixed $value, array $row): array
{
    $text = (string) $value;
    if ($key === 'transaction_type') {
        return [$text === 'income' ? 'Money in' : ($text === 'expense' ? 'Money out' : ucfirst($text)), 'type type--' . $text];
    }
    if ($key === 'amount' && isset($row['transaction_type'])) {
        $sign = $row['transaction_type'] === 'income' ? '+' : ($row['transaction_type'] === 'expense' ? '-' : '');
        return [$sign . money((float) $value), 'amount amount--' . $row['transaction_type']];
    }
    if (in_array($key, ['amount', 'spent', 'target_amount', 'current_amount'], true)) {
        return [money((float) $value), 'amount'];
    }
    if ($key === 'status') return [ucfirst($text), ''];
    return [$text === '' ? '-' : $text, ''];
}

// Works out the summary figures shown above the table.
function statement_totals(string $report, array $rows): array
{
    $income = 0.0; $expense = 0.0;
    if (in_array($report, ['statement', 'transactions', 'income', 'expenses'], true)) {
        foreach ($rows as $r) {
            $amt = (float) ($r['amount'] ?? 0);
            if (($r['transaction_type'] ?? '') === 'income') $income += $amt;
            elseif (($r['transaction_type'] ?? '') === 'expense') $expense += $amt;
        }
        if ($report === 'income') return [['Money in', '+' . money($income)], ['Records', (string) count($rows)]];
        if ($report === 'expenses') return [['Money out', '-' . money($expense)], ['Records', (string) count($rows)]];
        $net = $income - $expense;
        return [['Money in', '+' . money($income)], ['Money out', '-' . money($expense)], ['Net movement', ($net < 0 ? '-' : '+') . money(abs($net))], ['Records', (string) count($rows)]];
    }
    if ($report === 'budgets') {
        $budget = 0.0; $spent = 0.0;
        foreach ($rows as $r) { $budget += (float) ($r['amount'] ?? 0); $spent += (float) ($r['spent'] ?? 0); }
        return [['Budgeted', money($budget)], ['Spent', money($spent)], ['Left', ($budget - $spent < 0 ? '-' : '') . money(abs($budget - $spent))]];
    }
    $target = 0.0; $saved = 0.0;
    foreach ($rows as $r) { $target += (float) ($r['target_amount'] ?? 0); $saved += (float) ($r['current_amount'] ?? 0); }
    $pct = $target > 0 ? (int) round($saved / $target * 100) : 0;
    return [['Target', money($target)], ['Saved so far', money($saved)], ['Progress', $pct . '%']];
}

// Everything a statement page needs, stored with the QR code so the link always shows the same statement.
function statement_build_snapshot(string $report, string $from, string $to, array $rows, array $user, string $languageName): array
{
    $heroTitle = $report === 'budgets' ? 'Your budget at a glance' : ($report === 'savings' ? 'Your savings at a glance' : 'Your money at a glance');
    $heroSubtitle = $report === 'budgets' ? 'Every budget and what has been spent against it' : ($report === 'savings' ? 'Every savings goal and how far along it is' : 'A clear view of your personal money for this period');
    return [
        'report' => $report,
        'title' => $heroTitle,
        'subtitle' => $heroSubtitle,
        'section' => ucfirst($report) . ' detail',
        'user_name' => (string) ($user['full_name'] ?? ''),
        'from' => $from,
        'to' => $to,
        'language' => $languageName,
        'generated' => date('Y-m-d H:i'),
        'headers' => array_keys($rows[0] ?? []),
        'rows' => array_values($rows),
        'totals' => statement_totals($report, $rows),
    ];
}

/**
 * Prints the full statement page.
 * $d keys: snapshot (array), url (string, the address the QR code opens), show_print (bool), shared_view (bool), local_warning (bool)
 */
function render_statement_page(array $d): void
{
    $s = $d['snapshot'];
    $base = app_base_path();
    $logo = $base . '/assets/logo/imali-logo.png';
    $headers = $s['headers'] ?? [];
    $rows = $s['rows'] ?? [];
    $url = (string) ($d['url'] ?? '');
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= e((string) $s['title']) ?> · Imali Smart</title>
<link rel="icon" type="image/png" href="<?= e($logo) ?>">
<style>
@page{size:A4;margin:0}
*{box-sizing:border-box}
body{margin:0;background:#e9ecea;color:#193c31;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%}
.sheet{position:relative;width:100%;max-width:794px;min-height:1123px;margin:0 auto 28px;padding:44px 44px 96px;background:#f7f5ef;overflow:hidden}
.sheet:before{content:"";position:absolute;inset:260px 70px 120px;background:url(<?= e($logo) ?>) center/420px no-repeat;opacity:.05;pointer-events:none}
.sheet>*{position:relative;z-index:1}
.brandbar{display:grid;grid-template-columns:96px 1fr 120px;align-items:start;gap:14px}
.brandbar-logo{width:84px;height:64px;object-fit:contain}
.brand-center{text-align:center;padding-top:10px}
.brand-name{font-size:16px;font-weight:800;letter-spacing:.09em;color:#1d5b47}
.tagline{font-size:11px;color:#6f7f78;margin-top:6px}
.qrbox{text-align:right}
.qrbox [data-qr]{display:inline-block;line-height:0;background:#fff;padding:4px;border:1px solid #e2e5df;border-radius:6px}
.qrbox .tagline{margin-top:5px}
.title-row{border-bottom:2px solid #1d5b47;margin-top:26px;padding-bottom:14px}
.title-row h1{font-size:30px;line-height:1.1;margin:0;color:#183d32}
.subtitle{font-size:12px;color:#738078;margin-top:8px}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:20px 0}
.meta-card{background:#fff;border:1px solid #e2e5df;border-radius:10px;padding:11px 13px}
.meta-label{font-size:9px;color:#89938d;text-transform:uppercase;letter-spacing:.08em}
.meta-value{font-size:14px;font-weight:700;color:#20392f;margin-top:6px;word-break:break-word}
.totals{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin:0 0 20px}
.total{background:#1d5b47;color:#fff;border-radius:10px;padding:12px 14px}
.total span{display:block;font-size:10px;letter-spacing:.07em;text-transform:uppercase;opacity:.8}
.total strong{display:block;font-size:17px;margin-top:6px}
.section-heading{display:flex;justify-content:space-between;align-items:end;margin:18px 0 9px}
.section-heading h2{font-size:16px;margin:0;color:#205d48}
.section-heading span{font-size:10px;color:#89938d}
.table-shell{background:rgba(255,255,255,.9);border:1px solid #e2e5df;border-radius:12px;overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:11px;color:#31443d}
th{background:#edf1ed;color:#205d48;text-align:left;font-size:10px;padding:10px 9px;white-space:nowrap}
td{padding:9px;border-bottom:1px solid #e7ebe7;vertical-align:top}
tr:last-child td{border-bottom:0}
td.amount{font-weight:700;text-align:right;white-space:nowrap}
td.amount--income{color:#15803d}
td.amount--expense{color:#b91c1c}
td.type{white-space:nowrap}
.notice{background:#fff7e0;border:1px solid #f0d78a;color:#6b4e00;border-radius:10px;padding:10px 13px;font-size:12px;margin:0 0 16px}
.linkline{font-size:10px;color:#78857e;margin-top:14px;word-break:break-all}
.sheet-footer{position:absolute;left:44px;right:44px;bottom:26px;border-top:1px solid #1d5b47;padding-top:9px;display:flex;justify-content:space-between;gap:12px;font-size:9px;color:#78857e}
.sheet-footer strong{color:#205d48}
.toolbar{position:fixed;right:18px;top:14px;z-index:10;display:flex;gap:8px}
.toolbar button,.toolbar a{background:#1d5b47;color:#fff;border:0;border-radius:6px;padding:9px 14px;font-size:13px;text-decoration:none;cursor:pointer}
.toolbar a.quiet{background:#fff;color:#1d5b47;border:1px solid #1d5b47}
@media (max-width:640px){
 body{background:#f7f5ef}
 .sheet{padding:20px 16px 110px;min-height:0;margin:0}
 .brandbar{grid-template-columns:64px 1fr 96px;gap:8px}
 .brandbar-logo{width:60px;height:48px}
 .title-row h1{font-size:23px}
 .meta-grid{grid-template-columns:1fr 1fr}
 .sheet-footer{left:16px;right:16px;flex-direction:column;position:static;margin-top:24px}
 .toolbar{position:static;padding:10px 16px 0;justify-content:flex-end}
}
@media print{
 body{background:#f7f5ef}
 .sheet{margin:0;box-shadow:none;page-break-after:always}
 .toolbar,.notice{display:none}
}
</style></head><body>
<?php if (!empty($d['show_print'])): ?><div class="toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button><a class="quiet" href="<?= e($base) ?>/reports.php">Back to reports</a></div><?php endif; ?>
<main class="sheet">
<?php if (!empty($d['local_warning'])): ?><div class="notice"><strong>Phones cannot open this QR code yet.</strong> This site is running on your own computer, so the link only works on this computer. Once Imali Smart is online (or you open it with your computer’s network address), new statements will open on any phone.</div><?php endif; ?>
<header class="brandbar"><img class="brandbar-logo" src="<?= e($logo) ?>" alt="Imali Smart logo"><div class="brand-center"><div class="brand-name">IMALI SMART</div><div class="tagline">Plan with confidence, track with clarity</div></div>
<div class="qrbox"><?php if ($url !== ''): ?><div data-qr="<?= e($url) ?>" data-qr-size="92"></div><div class="tagline">Scan to see this statement</div><?php endif; ?></div></header>
<section class="title-row"><h1><?= e((string) $s['title']) ?></h1><div class="subtitle"><?= e((string) $s['subtitle']) ?></div></section>
<section class="meta-grid">
<div class="meta-card"><div class="meta-label">Prepared for</div><div class="meta-value"><?= e((string) $s['user_name']) ?></div></div>
<div class="meta-card"><div class="meta-label">Period</div><div class="meta-value"><?= e((string) $s['from']) ?> to <?= e((string) $s['to']) ?></div></div>
<div class="meta-card"><div class="meta-label">Language</div><div class="meta-value"><?= e((string) $s['language']) ?></div></div>
<div class="meta-card"><div class="meta-label">Generated</div><div class="meta-value"><?= e((string) $s['generated']) ?></div></div>
</section>
<?php if (!empty($s['totals'])): ?><section class="totals"><?php foreach ($s['totals'] as $t): ?><div class="total"><span><?= e((string) $t[0]) ?></span><strong><?= e((string) $t[1]) ?></strong></div><?php endforeach; ?></section><?php endif; ?>
<section class="section-heading"><h2><?= e((string) $s['section']) ?></h2><span>Personal finance record</span></section>
<div class="table-shell"><table><thead><tr><?php foreach ($headers as $h): ?><th><?= e(statement_column_label((string) $h)) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php if (!$rows): ?><tr><td>No records in this period.</td></tr><?php else: foreach ($rows as $row): ?><tr><?php foreach ($headers as $h): [$text, $cls] = statement_cell((string) $h, $row[$h] ?? '', $row); ?><td<?= $cls !== '' ? ' class="' . e($cls) . '"' : '' ?>><?= e($text) ?></td><?php endforeach; ?></tr><?php endforeach; endif; ?>
</tbody></table></div>
<?php if ($url !== ''): ?><div class="linkline">Statement link: <?= e($url) ?></div><?php endif; ?>
<footer class="sheet-footer"><div><strong>Imali Smart</strong> · Plan with confidence, track with clarity<br>Keep this statement private and share it only with someone you trust.</div><div style="text-align:right">Prepared for <?= e((string) $s['user_name']) ?><br>Generated <?= e((string) $s['generated']) ?></div></footer>
</main>
<script src="<?= e($base) ?>/js/qr.js"></script>
</body></html><?php
}
