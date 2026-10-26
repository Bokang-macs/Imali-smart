<?php
/** Correct the date of transactions just imported from a scanned document, so they sit in the right place in every list. */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
require_auth(true);
if (!request_method_is('POST')) json_response(['ok' => false, 'error' => 'POST request method required.'], 405);
require_csrf();
$user = current_user();
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))), static fn($v) => $v > 0)));
$date = (string)($_POST['date'] ?? '');
$ts = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? strtotime($date) : false;
if (!$ids || count($ids) > 50 || $ts === false || date('Y-m-d', $ts) !== $date || $date > date('Y-m-d', strtotime('+1 day'))) {
    json_response(['ok' => false, 'error' => 'Choose a valid date that is not in the future.'], 422);
}
try {
    $pdo = db();
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("UPDATE transactions SET transaction_date = ? WHERE user_id = ? AND deleted_at IS NULL AND id IN ($in)");
    $st->execute(array_merge([$date, (int)$user['id']], $ids));
    foreach ($ids as $id) record_audit_event((int)$user['id'], 'transaction.date_corrected', 'transaction', $id, ['transaction_date' => $date]);
    if (function_exists('invalidate_financial_records_cache')) invalidate_financial_records_cache((int)$user['id']);
    $from = date('Y-m-01', $ts); $to = min(date('Y-m-t', $ts), date('Y-m-d'));
    json_response(['ok' => true, 'updated' => $st->rowCount(), 'date' => $date, 'period' => ['from' => $from, 'to' => $to]]);
} catch (Throwable $e) {
    error_log('[Imali Smart] set-date failed: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'The date could not be changed.'], 500);
}
