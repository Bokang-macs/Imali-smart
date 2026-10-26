<?php
declare(strict_types=1);

/**
 * Imali Smart - translates the whole app into the chosen language.
 *
 * When the language is not English, the finished page is passed through this file
 * so that all visible text (text, placeholders, titles, image text and labels)
 * shows in the chosen language, even where a page still has English written in it.

 *
 * For each text, the steps are:  1) the built-in dictionary (includes/i18n.php)
 *                              2) saved translations (storage/i18n/<lang>.json)
 *                              3) Google Gemini (the result is then saved for next time)
 * If Gemini does not answer, the English text stays, so the app never breaks.
 */

// Starts page buffering so the finished page can be translated.
function imali_translator_boot(): void
{
    if (PHP_SAPI === 'cli' || defined('IMALI_TRANSLATOR_BOOTED')) {
        return;
    }
    define('IMALI_TRANSLATOR_BOOTED', true);
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#/(api|actions)/#', $script)) {
        return;
    }
    ob_start('imali_translator_callback');
}

// Runs on the finished page. If anything fails, the English page is shown.
function imali_translator_callback(string $html, int $phase = 0): string
{
    try {
        return imali_translate_html($html);
    } catch (Throwable $e) {
        return $html;
    }
}

// Short texts used by JavaScript (such as chat labels) that also need translating.
function imali_tr_ui_strings(): array
{
    return [
        'You', 'Ask Imali', 'Thinking…', 'Gemini', 'Application fallback', 'Unavailable', 'Connection error',
        'The assistant is unavailable. Please try again.',
        'The assistant could not be reached. Check your connection and try again.',
        'Please fill in this field.', 'Saving…', 'Loading…', 'Something went wrong. Please try again.',
    ];
}

// Cleans a text. Returns null if it should not be translated.
function imali_tr_normalize(string $raw): ?string
{
    $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = trim((string)preg_replace('/\s+/u', ' ', $decoded));
    if ($s === '' || mb_strlen($s) > 600) return null;
    if (!preg_match('/\p{L}{2,}/u', $s)) return null;
    if (preg_match('#^(https?://|www\.)\S+$#i', $s)) return null;
    if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $s)) return null;
    if (preg_match('/\{\{|\}\}|<\?|\?>|\$\{/', $s)) return null;
    return $s;
}

// Returns the file where translations for a language are saved.
function imali_tr_store_path(string $lang): string
{
    return dirname(__DIR__) . '/storage/i18n/' . preg_replace('/[^A-Za-z-]/', '', $lang) . '.json';
}

// Loads saved translations for a language.
function imali_tr_load_store(string $lang): array
{
    $file = imali_tr_store_path($lang);
    if (!is_readable($file)) return [];
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

// Saves new translations without losing the old ones.
function imali_tr_save_store(string $lang, array $new): void
{
    if (!$new) return;
    $dir = dirname(__DIR__) . '/storage/i18n';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = imali_tr_store_path($lang);
    $fh = @fopen($file, 'c+');
    if (!$fh) return;
    if (flock($fh, LOCK_EX)) {
        $existing = json_decode((string)stream_get_contents($fh), true);
        $merged = array_merge(is_array($existing) ? $existing : [], $new);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

/** Runs $fn on every text, placeholder, title, alt and label inside one piece of HTML. */
function imali_tr_walk(string $part, callable $fn): string
{
    // 1) text between tags
    $part = preg_replace_callback('#(?<=>)([^<>]+)#', function ($m) use ($fn) {
        $raw = $m[1];
        $norm = imali_tr_normalize($raw);
        if ($norm === null) return $raw;
        $tr = $fn($norm);
        if ($tr === null || $tr === $norm) return $raw;
        preg_match('/^\s*/u', $raw, $lead);
        preg_match('/\s*$/u', $raw, $trail);
        return ($lead[0] ?? '') . htmlspecialchars($tr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ($trail[0] ?? '');
    }, $part) ?? $part;

    // 2) placeholder, title, alt and label attributes
    $part = preg_replace_callback('#<([a-zA-Z][a-zA-Z0-9-]*)\b[^<>]*>#', function ($m) use ($fn) {
        $tag = $m[0];
        $attrs = ['placeholder', 'title', 'alt', 'aria-label'];
        if (strtolower($m[1]) === 'meta' && preg_match('/(name|property)=["\'](description|og:title|og:description|twitter:title|twitter:description)["\']/i', $tag)) {
            $attrs[] = 'content';
        }
        foreach ($attrs as $attr) {
            $tag = preg_replace_callback('#(\s' . preg_quote($attr, '#') . '=)(["\'])(.*?)\2#s', function ($a) use ($fn) {
                $norm = imali_tr_normalize($a[3]);
                if ($norm === null) return $a[0];
                $tr = $fn($norm);
                if ($tr === null || $tr === $norm) return $a[0];
                return $a[1] . $a[2] . htmlspecialchars($tr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $a[2];
            }, $tag) ?? $tag;
        }
        return $tag;
    }, $part) ?? $part;

    return $part;
}

// Translates all visible text on the page to the chosen language.
function imali_translate_html(string $html): string
{
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    if ($lang === 'en' || $html === '') return $html;

    foreach (headers_list() as $h) {
        if (stripos($h, 'content-type:') === 0 && stripos($h, 'text/html') === false) return $html;
    }
    if (stripos($html, '<html') === false && stripos($html, '<!doctype') === false) return $html;
    if (strlen($html) > 1500000) return $html;

    @set_time_limit(120);
    require_once __DIR__ . '/gemini.php';

    // Build a list of English text -> translated text, and a list of texts that are already translated.
    $dict = [];
    $done = [];
    $catalog = function_exists('translation_catalog') ? translation_catalog() : [];
    $en = $catalog['en'] ?? [];
    foreach (($catalog[$lang] ?? []) as $k => $v) {
        if (!is_string($v)) continue;
        $nv = imali_tr_normalize($v);
        if ($nv !== null) $done[$nv] = true;
        if (isset($en[$k]) && is_string($en[$k]) && $en[$k] !== $v) {
            $ne = imali_tr_normalize($en[$k]);
            if ($ne !== null && $nv !== null) $dict[$ne] = $nv;
        }
    }
    // Do not translate language names or the brand name.
    $skip = ['Imali Smart' => true];
    foreach (available_languages() as $l) {
        foreach (['name', 'native'] as $f) if (!empty($l[$f])) $skip[(string)$l[$f]] = true;
    }

    $parts = preg_split('#(<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>|<!--.*?-->|<textarea\b[^>]*>.*?</textarea>|<pre\b[^>]*>.*?</pre>|<svg\b[^>]*>.*?</svg>|<code\b[^>]*>.*?</code>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return $html;

    // Step 1: collect every text that may need translating
    $found = [];
    foreach (imali_tr_ui_strings() as $s) $found[$s] = true;
    foreach ($parts as $i => $p) {
        if ($i % 2 === 1) continue;
        imali_tr_walk($p, function (string $s) use (&$found, $skip, $done) {
            if (!isset($skip[$s]) && !isset($done[$s])) $found[$s] = true;
            return null;
        });
    }

    // Step 2: find each translation: dictionary, then saved file, then Gemini
    $store = imali_tr_load_store($lang);
    $resolved = [];
    $pending = [];
    foreach (array_keys($found) as $s) {
        if (isset($dict[$s])) { $resolved[$s] = $dict[$s]; continue; }
        if (isset($store[$s]) && is_string($store[$s]) && $store[$s] !== '') { $resolved[$s] = $store[$s]; continue; }
        $pending[] = (string)$s;
    }
    // Texts that are not saved yet are NOT translated here: waiting for Gemini made the first
    // visit to a language take over a minute. The page is shown at once with what is already
    // known, and the browser fetches the rest in small batches (see imali_tr_client_script).
    $lazyChunks = [];
    foreach (array_chunk(array_slice($pending, 0, 600), 25) as $chunk) {
        $lazyChunks[] = ['t' => array_values($chunk), 's' => imali_tr_sign($lang, $chunk)];
    }

    // Step 3: put the translations back into the page
    $lookup = function (string $s) use ($resolved, $skip, $done): ?string {
        if (isset($skip[$s]) || isset($done[$s])) return null;
        return $resolved[$s] ?? null;
    };
    foreach ($parts as $i => $p) {
        if ($i % 2 === 1) continue;
        $parts[$i] = imali_tr_walk($p, $lookup);
    }
    $out = implode('', $parts);

    // Give the translated short texts to JavaScript (chat labels and messages)
    $ui = [];
    foreach (imali_tr_ui_strings() as $s) $ui[$s] = $resolved[$s] ?? $s;
    $script = '<script>window.IMALI_UI=' . json_encode($ui, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';window.IMALI_LANG=' . json_encode($lang) . ';</script>';
    $pos = strripos($out, '</head>');
    if ($pos !== false) $out = substr($out, 0, $pos) . $script . substr($out, $pos);

    if ($lazyChunks) {
        $base = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        $lazy = ['lang' => $lang, 'url' => $base . '/api/translate-batch.php', 'chunks' => $lazyChunks];
        $client = '<script>window.IMALI_LAZY=' . json_encode($lazy, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';'
            . imali_tr_client_script() . '</script>';
        $pos = strripos($out, '</body>');
        $out = $pos !== false ? substr($out, 0, $pos) . $client . substr($out, $pos) : $out . $client;
    }
    return $out;
}

// Signs a list of texts so api/translate-batch.php only translates texts this site itself put on a page.
function imali_tr_sign(string $lang, array $texts): string
{
    $cfg = function_exists('app_config') ? app_config() : [];
    $secret = 'imali-tr|' . (string)($cfg['gemini_api_key'] ?? '') . '|' . (string)($cfg['app_url'] ?? '') . '|' . __DIR__;
    return hash_hmac('sha256', $lang . "\n" . json_encode(array_values($texts), JSON_UNESCAPED_UNICODE), $secret);
}

// JavaScript that translates the remaining texts in the background and updates the page as they arrive.
function imali_tr_client_script(): string
{
    return <<<'JS'
(function(){var D=window.IMALI_LAZY;if(!D||!D.chunks||!D.chunks.length)return;
var map={},queue=D.chunks.slice(),active=0,fails=0,total=D.chunks.length,finished=0,bar=null;
var SKIP='script,style,textarea,pre,svg,code,noscript';
function norm(raw){var s=raw.replace(/\s+/g,' ').trim();if(!s||s.length>600)return null;
if(!/\p{L}{2,}/u.test(s))return null;if(/^(https?:\/\/|www\.)\S+$/i.test(s))return null;
if(/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s))return null;if(/\{\{|\}\}|<\?|\?>|\$\{/.test(s))return null;return s}
function swap(raw){var s=norm(raw);if(s===null||!Object.prototype.hasOwnProperty.call(map,s))return null;
var lead=(raw.match(/^\s*/)||[''])[0],trail=(raw.match(/\s*$/)||[''])[0];return lead+map[s]+trail}
function apply(){var w=document.createTreeWalker(document.documentElement,NodeFilter.SHOW_TEXT,null),n,list=[];
while((n=w.nextNode())){var p=n.parentNode;if(p&&p.nodeType===1&&p.closest&&p.closest(SKIP))continue;list.push(n)}
list.forEach(function(t){var r=swap(t.nodeValue);if(r!==null)t.nodeValue=r});
var attrs=['placeholder','title','alt','aria-label'];
document.querySelectorAll('[placeholder],[title],[alt],[aria-label]').forEach(function(el){attrs.forEach(function(a){
if(el.hasAttribute(a)){var r=swap(el.getAttribute(a));if(r!==null)el.setAttribute(a,r)}})});
document.querySelectorAll('meta[name="description"],meta[property="og:title"],meta[property="og:description"],meta[name="twitter:title"],meta[name="twitter:description"]').forEach(function(el){
var r=swap(el.getAttribute('content')||'');if(r!==null)el.setAttribute('content',r)});
var ui=window.IMALI_UI;if(ui)Object.keys(ui).forEach(function(k){if(Object.prototype.hasOwnProperty.call(map,k))ui[k]=map[k]})}
function progress(){if(!bar){bar=document.createElement('div');bar.setAttribute('aria-hidden','true');
bar.style.cssText='position:fixed;top:0;left:0;height:3px;width:0;background:#0f766e;z-index:2147483647;transition:width .3s';
document.body.appendChild(bar)}
bar.style.width=Math.min(100,Math.round(finished/total*100))+'%';
if(finished>=total||(!queue.length&&!active)){setTimeout(function(){if(bar&&bar.parentNode)bar.parentNode.removeChild(bar)},400)}}
function run(c){active++;
fetch(D.url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({lang:D.lang,texts:c.t,sig:c.s})})
.then(function(r){return r.json()})
.then(function(j){if(j&&j.ok&&j.translations){for(var k in j.translations){if(typeof j.translations[k]==='string'&&j.translations[k])map[k]=j.translations[k]}
apply();finished++}else{throw new Error('bad')}})
.catch(function(){fails++;if(!c.r){c.r=1;queue.push(c)}else{finished++}})
.then(function(){active--;progress();pump()})}
function pump(){while(active<4&&queue.length&&fails<4){run(queue.shift())}}
function start(){progress();pump()}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start()})();
JS;
}

/** Translates a list of English texts with Gemini, many requests at the same time. Returns [english => translated]. */
function imali_tr_gemini_batches(array $strings, string $lang): array
{
    $cfg = app_config();
    $key = trim((string)($cfg['gemini_api_key'] ?? ''));
    if ($key === '' || !function_exists('curl_multi_init')) return [];

    $langName = gemini_resolve_language_name($lang);
    $primary = (string)($cfg['gemini_model'] ?? 'gemini-2.5-flash');
    $models = array_values(array_unique([$primary, 'gemini-2.0-flash', 'gemini-flash-latest']));

    $chunks = [];
    $cur = [];
    $chars = 0;
    foreach ($strings as $s) {
        $cur[] = $s;
        $chars += mb_strlen($s);
        if (count($cur) >= 40 || $chars >= 4000) { $chunks[] = $cur; $cur = []; $chars = 0; }
    }
    if ($cur) $chunks[] = $cur;

    $system = 'You translate text for a South African money app. '
        . 'Translate every string in the JSON array the user sends from English into ' . $langName . ' (' . $lang . '). '
        . 'Return ONLY a JSON array of strings with exactly the same length and order. '
        . 'Keep numbers, currency amounts such as R 1,500.00, percentages, dates, email addresses, URLs, placeholders like :name or %s and the brand name Imali Smart unchanged. '
        . 'Use simple, natural wording that a person with limited financial knowledge understands. No explanations.';

    $result = [];
    $remaining = $chunks;
    foreach ($models as $model) {
        if (!$remaining) break;
        $failed = [];
        foreach (array_chunk($remaining, 8) as $wave) {
            $mh = curl_multi_init();
            $handles = [];
            foreach ($wave as $idx => $chunk) {
                $body = [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => json_encode($chunk, JSON_UNESCAPED_UNICODE)]]]],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 8192,
                        'responseMimeType' => 'application/json',
                        'responseSchema' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    ],
                ];
                if (str_starts_with($model, 'gemini-2.5')) {
                    $body['generationConfig']['thinkingConfig'] = ['thinkingBudget' => 0];
                }
                $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode(preg_replace('/[^A-Za-z0-9._-]/', '', $model)) . ':generateContent');
                if (function_exists('imali_ca_bundle') && ($ca = imali_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $ca);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
                    CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    CURLOPT_CONNECTTIMEOUT => 6,
                    CURLOPT_TIMEOUT => 45,
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$idx] = $ch;
            }
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) curl_multi_select($mh, 1.0);
            } while ($active && $status === CURLM_OK);

            foreach ($handles as $idx => $ch) {
                $raw = curl_multi_getcontent($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                $chunk = $wave[$idx];
                $ok = false;
                if ($code >= 200 && $code < 300 && is_string($raw)) {
                    $payload = json_decode($raw, true);
                    $text = '';
                    foreach (($payload['candidates'][0]['content']['parts'] ?? []) as $p) {
                        if (isset($p['text'])) $text .= $p['text'];
                    }
                    $arr = json_decode(trim($text), true);
                    if (is_array($arr) && count($arr) === count($chunk)) {
                        $arr = array_values($arr);
                        foreach ($chunk as $n => $src) {
                            if (is_string($arr[$n]) && trim($arr[$n]) !== '') $result[$src] = trim($arr[$n]);
                        }
                        $ok = true;
                    }
                }
                if (!$ok) $failed[] = $chunk;
            }
            curl_multi_close($mh);
        }
        $remaining = $failed;
    }
    return $result;
}
