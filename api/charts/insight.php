<?php
/**
 * AI insight for a chart period.
 * The browser only sends a date range; every number the AI sees is recomputed here from the user's own transactions.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/gemini.php';
require_once dirname(__DIR__, 2) . '/includes/cache.php';
require_once dirname(__DIR__, 2) . '/includes/chart_details.php';

header('Content-Type: application/json; charset=utf-8');
require_auth();
if (!request_method_is('POST')) json_response(['ok' => false, 'error' => 'POST request method required.'], 405);

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
if (!verify_csrf((string)($input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) json_response(['ok' => false, 'error' => 'Your session has expired or this page is out of date. Please refresh the page and try again.'], 419);

$from = (string)($input['from'] ?? ''); $to = (string)($input['to'] ?? '');
$valid = static fn(string $d): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && date('Y-m-d', (int)strtotime($d)) === $d;
if (!$valid($from) || !$valid($to) || $from > $to) json_response(['ok' => false, 'error' => 'Choose a valid date range.'], 422);
if ((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 800) json_response(['ok' => false, 'error' => 'Choose a range of up to about two years.'], 422);

// Simple throttle so the button cannot be hammered.
if (time() - (int)($_SESSION['_chart_ai_at'] ?? 0) < 2) json_response(['ok' => false, 'error' => 'One moment, then try again.'], 429);
$_SESSION['_chart_ai_at'] = time();

$user = current_user();
$language = (string)($input['language'] ?? 'auto');
if (!array_key_exists($language, gemini_language_options())) $language = 'auto';

try {
    $pdo = db();
    if (!$pdo) throw new RuntimeException('Database is not available.');
    $facts = chart_range_facts($pdo, (int)$user['id'], $from, $to, (float)($user['opening_balance'] ?? 0));
} catch (Throwable $e) {
    error_log('[Imali Smart] Chart insight facts failed: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Your figures could not be loaded right now.'], 500);
}
$summary = ['facts' => $facts];

if ($facts['count'] === 0) json_response(['ok' => true, 'source' => 'rules', 'insight' => chart_rules_insight($facts), 'facts' => $summary['facts']]);

$cacheKey = 'chart_insight_' . hash('sha256', $user['id'] . '|' . $language . '|' . json_encode($facts));
if (function_exists('cache_get')) { $cached = cache_get($cacheKey); if (is_string($cached) && $cached !== '') json_response(['ok' => true, 'source' => 'ai', 'insight' => $cached, 'cached' => true, 'facts' => $facts]); }

$money = static fn(float $v): string => 'R ' . number_format($v, 2, '.', ',');
$lines = ['Period: ' . $from . ' to ' . $to . ' (' . $facts['days'] . ' days)',
    'Income: ' . $money($facts['income']) . ' from ' . $facts['income_count'] . ' entries',
    'Expenses: ' . $money($facts['expenses']) . ' from ' . $facts['expense_count'] . ' entries',
    'Net: ' . $money($facts['net']), 'Balance at end of period: ' . $money($facts['balance_end']), 'Average daily spend: ' . $money($facts['avg_daily_spend'])];
foreach ($facts['categories'] as $c) $lines[] = 'Category ' . $c['name'] . ': ' . $money($c['amount']) . ' (' . $c['share'] . '% of expenses)';
if ($facts['largest_expense']) $lines[] = 'Largest expense: ' . $money($facts['largest_expense']['amount']) . ' on ' . $facts['largest_expense']['date'] . ' (' . mb_substr($facts['largest_expense']['description'], 0, 80) . ')';
if ($facts['largest_income']) $lines[] = 'Largest income: ' . $money($facts['largest_income']['amount']) . ' on ' . $facts['largest_income']['date'];
if ($facts['expense_change_pct'] !== null) $lines[] = 'Expenses vs previous ' . $facts['days'] . ' days (' . $facts['prev']['from'] . ' to ' . $facts['prev']['to'] . '): ' . $facts['expense_change_pct'] . '%';
if ($facts['income_change_pct'] !== null) $lines[] = 'Income vs previous period: ' . $facts['income_change_pct'] . '%';

$prompt = "Explain this period of the user's money in plain, friendly language. Write 2 to 4 short sentences, at most 80 words, no headings or bullet points. "
    . "Use ONLY the figures below, never invent numbers. Point out the most useful thing (a trend, a big category, or a risk) and end with one practical tip. "
    . "Use the 80/20 guide (spend up to 80%, keep at least 20%) only if income is above zero.\n\n" . implode("\n", $lines);
$body = [
    'systemInstruction' => ['parts' => [['text' => 'You are Imali Smart, a careful and encouraging money coach for South African users. ' . gemini_language_instruction($language)]]],
    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 400],
];
$result = gemini_call_api($body, 20);
$text = trim((string)($result['text'] ?? ''));
if (!$result['ok'] || $text === '') json_response(['ok' => true, 'source' => 'rules', 'insight' => chart_rules_insight($facts), 'facts' => $facts]);
$text = trim(preg_replace('/[*#`]+/', '', $text));
if (function_exists('cache_set')) cache_set($cacheKey, $text, 600);
json_response(['ok' => true, 'source' => 'ai', 'insight' => $text, 'facts' => $facts]);
