<?php
// Translates text into the chosen language with Gemini. Accepts one text or a list of texts.
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/response.php';
require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/gemini.php';

header('Content-Type: application/json; charset=utf-8');

if (!request_method_is('POST')) {
    json_response(['ok' => false, 'error' => 'Method not allowed. POST required.'], 405);
}

// Receive payload (JSON or form-urlencoded)
$inputRaw = file_get_contents('php://input');
$data = [];
if (!empty($inputRaw) && str_starts_with(trim($inputRaw), '{')) {
    try {
        $data = json_decode($inputRaw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $data = [];
    }
}
if (empty($data)) {
    $data = $_POST;
}

$targetLang = trim((string)($data['target_lang'] ?? current_lang()));
$sourceLang = trim((string)($data['source_lang'] ?? 'en'));

$allLangs = available_languages();
if (!isset($allLangs[$targetLang])) {
    $targetLang = current_lang();
}

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
@set_time_limit(60);

// Support single text or array of texts
if (isset($data['texts']) && is_array($data['texts'])) {
    $results = [];
    foreach ($data['texts'] as $idx => $t) {
        if (!is_string($t)) continue;
        $res = gemini_translate_text($t, $targetLang, $sourceLang);
        $results[$idx] = $res['text'] ?? $t;
    }
    json_response([
        'ok' => true,
        'translations' => $results,
        'target_lang' => $targetLang,
    ]);
}

$text = trim((string)($data['text'] ?? ''));
if ($text === '') {
    json_response(['ok' => false, 'error' => 'No text provided to translate.'], 422);
}

$result = gemini_translate_text($text, $targetLang, $sourceLang);

json_response([
    'ok' => true,
    'original' => $text,
    'translated' => $result['text'] ?? $text,
    'target_lang' => $targetLang,
    'cached' => $result['cached'] ?? false,
]);
