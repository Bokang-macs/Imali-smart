<?php
/**
 * AI connection check. Open while signed in: http://localhost/Imali-Smart/api/ai/status.php
 * Shows whether the Gemini key is loaded and what Google answers. The key itself is never shown.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/gemini.php';

header('Content-Type: application/json; charset=utf-8');
if (!is_authenticated()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sign in first, then reload this page.']);
    exit;
}

$config = app_config();
$key = trim((string)($config['gemini_api_key'] ?? ''), " \t\n\r\0\x0B" . chr(34) . chr(39) . chr(92));
$envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

$report = [
    'env_file_found' => is_file($envFile),
    'env_file_path' => $envFile,
    'key_loaded' => $key !== '' && $key !== 'MY_GEMINI_API_KEY',
    'key_length' => strlen($key),
    'model_setting' => (string)($config['gemini_model'] ?? ''),
    'model_order' => gemini_model_chain(),
    'curl_enabled' => function_exists('curl_init'),
    'ca_bundle' => gemini_ca_bundle(),
];

$test = gemini_call_api([
    'contents' => [['role' => 'user', 'parts' => [['text' => 'Reply with the single word: ready']]]],
    'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 20],
], 20);

$report['gemini_ok'] = $test['ok'];
$report['gemini_reason'] = $test['reason'] ?? '';
$report['gemini_detail'] = $test['detail'] ?? '';
$report['model_used'] = $test['model_used'] ?? '';
$report['reply'] = $test['text'] ?? '';

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
