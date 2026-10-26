<?php
// Translates one small batch of page texts with Gemini and saves the result for everyone.
// Called by the browser after a page has loaded (see imali_tr_client_script in includes/page_translator.php),
// so opening a new language never makes a page wait.
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/response.php';
require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/gemini.php';

if (!request_method_is('POST')) {
    json_response(['ok' => false, 'error' => 'POST required.'], 405);
}

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(['ok' => false, 'error' => 'Invalid request.'], 400);
}

$lang = (string)($data['lang'] ?? '');
$texts = $data['texts'] ?? null;
$sig = (string)($data['sig'] ?? '');

if (!isset(available_languages()[$lang]) || $lang === 'en' || !is_array($texts) || !$texts || count($texts) > 40) {
    json_response(['ok' => false, 'error' => 'Invalid request.'], 422);
}
foreach ($texts as $t) {
    if (!is_string($t) || $t === '' || mb_strlen($t) > 600) {
        json_response(['ok' => false, 'error' => 'Invalid request.'], 422);
    }
}
// Only texts that this site put on a page are accepted, so the endpoint cannot be used to spend the Gemini quota.
if (!hash_equals(imali_tr_sign($lang, $texts), $sig)) {
    json_response(['ok' => false, 'error' => 'Invalid signature.'], 403);
}

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
@set_time_limit(90);

$out = [];
$store = imali_tr_load_store($lang);
$todo = [];
foreach ($texts as $t) {
    if (isset($store[$t]) && is_string($store[$t]) && $store[$t] !== '') $out[$t] = $store[$t];
    else $todo[] = $t;
}

if ($todo) {
    // If Gemini just failed for this language, do not make every visitor wait for it again.
    $cacheDir = dirname(__DIR__) . '/storage/cache';
    $failFlag = $cacheDir . '/tr_fail_' . preg_replace('/[^A-Za-z-]/', '', $lang);
    if (is_file($failFlag) && time() - (int)@filemtime($failFlag) < 20) {
        json_response(['ok' => false, 'error' => 'Translation service busy. Try again shortly.'], 503);
    }
    $fresh = imali_tr_gemini_batches($todo, $lang);
    if ($fresh) {
        imali_tr_save_store($lang, $fresh);
        $out = array_merge($out, $fresh);
        if (is_file($failFlag)) @unlink($failFlag);
    } else {
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
        @touch($failFlag);
        json_response(['ok' => false, 'error' => 'Translation unavailable.'], 502);
    }
}

json_response(['ok' => true, 'translations' => $out, 'lang' => $lang]);
