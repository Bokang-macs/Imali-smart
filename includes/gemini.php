<?php
// Talks to Google Gemini for the chatbot and for translations. The API key stays on the server.
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Maps app language codes to language names for Gemini.
function gemini_language_map(): array
{
    return [
        'en' => 'English',
        'zu' => 'isiZulu',
        'xh' => 'isiXhosa',
        'af' => 'Afrikaans',
        'nso' => 'Sepedi',
        'st' => 'Sesotho',
        'tn' => 'Setswana',
        'ts' => 'Xitsonga',
        've' => 'Tshivenḓa',
        'ss' => 'siSwati',
        'nr' => 'isiNdebele',
        'ar' => 'Arabic',
        'fr' => 'French',
        'pt' => 'Portuguese',
        'es' => 'Spanish',
        'hi' => 'Hindi',
        'zh-Hans' => 'Simplified Chinese',
        'zh-Hant' => 'Traditional Chinese',
    ];
}

// Language choices shown in the chat page.
function gemini_language_options(): array
{
    return [
        'auto' => 'Auto-detect active language',
        'English' => 'English',
        'isiZulu' => 'isiZulu',
        'isiXhosa' => 'isiXhosa',
        'Sepedi' => 'Sepedi',
        'Setswana' => 'Setswana',
        'Sesotho' => 'Sesotho',
        'Tshivenda' => 'Tshivenḓa',
        'Afrikaans' => 'Afrikaans',
        'siSwati' => 'siSwati',
        'isiNdebele' => 'isiNdebele',
        'Xitsonga' => 'Xitsonga',
        'French' => 'Français',
        'Portuguese' => 'Português',
        'Spanish' => 'Español',
        'Arabic' => 'العربية',
        'Hindi' => 'हिन्दी',
        'Simplified Chinese' => '简体中文',
    ];
}

// Turns a language code or name into a language name.
function gemini_resolve_language_name(string $lang): string
{
    $map = gemini_language_map();
    if (isset($map[$lang])) {
        return $map[$lang];
    }
    $options = gemini_language_options();
    if (isset($options[$lang])) {
        return $lang;
    }
    return 'English';
}

// Builds the instruction that tells Gemini which language to answer in.
function gemini_language_instruction(string $language): string
{
    $resolved = gemini_resolve_language_name($language);
    if ($language === 'auto') {
        $activeName = function_exists('current_lang') ? gemini_resolve_language_name(current_lang()) : 'English';
        return 'Reply in ' . $activeName . ' (the language the user selected in the app) unless the user clearly writes in a different language, in which case reply in the language they used. Keep South African rand amounts readable with the R symbol and preserve important financial terms in a natural, understandable way.';
    }
    return 'Answer entirely and naturally in ' . $resolved . '. Keep South African rand amounts readable with the R symbol (e.g. R 1,500.00, R250) and preserve all calculated numbers, dates, and accounting precision exactly.';
}

/**
 * Model order for Gemini calls: the configured model first, then fallbacks.
 * Set GEMINI_MODEL and GEMINI_FALLBACK_MODELS (comma separated) in .env to change them.
 * The "-latest" names are aliases Google keeps pointing at a current model, so they do not go stale.
 */
function gemini_model_chain(): array
{
    $config = app_config();
    $primary = trim((string)($config['gemini_model'] ?? '')) ?: 'gemini-flash-latest';
    $fallbacks = ['gemini-2.5-flash', 'gemini-flash-latest', 'gemini-flash-lite-latest'];
    $chain = [];
    // The model that worked last time goes first, so we do not re-try dead names on every question.
    $good = function_exists('cache_get') ? cache_get('gemini_good_model') : null;
    foreach (array_merge([$primary], is_string($good) ? [$good] : [], $fallbacks) as $model) {
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '', (string)$model);
        if ($clean === '' || in_array($clean, $chain, true)) continue;
        if (function_exists('cache_get') && cache_get('gemini_bad_model_' . $clean)) continue;
        $chain[] = $clean;
    }
    return $chain ?: ['gemini-flash-latest', 'gemini-flash-lite-latest'];
}

/**
 * One HTTPS request to Gemini. Returns [status, raw body, curl error].
 */
function gemini_ca_bundle(): ?string
{
    return imali_ca_bundle();
}

function gemini_http_post(string $model, string $apiKey, array $body, int $timeout): array
{
    $handle = curl_init(rtrim((string)env_value('GEMINI_API_BASE', 'https://generativelanguage.googleapis.com/v1beta'), '/') . '/models/' . rawurlencode($model) . ':generateContent');
    $options = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => max(3, $timeout),
        CURLOPT_ENCODING => '',
        CURLOPT_TCP_KEEPALIVE => 1,
    ];
    $ca = gemini_ca_bundle();
    if ($ca !== null) {
        $options[CURLOPT_CAINFO] = $ca;
    }
    curl_setopt_array($handle, $options);
    $raw = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    return [$status, $raw === false ? '' : (string)$raw, $error];
}

/**
 * Execute a Gemini REST request with automatic model fallback.
 * $budget is the most seconds we will spend across all models before giving up.
 */
function gemini_call_api(array $body, int $budget = 25, int $perCallCap = 20): array
{
    $config = app_config();
    $apiKey = trim((string)($config['gemini_api_key'] ?? ''), " \t\n\r\0\x0B" . chr(34) . chr(39) . chr(92));
    if ($apiKey === '' || $apiKey === 'MY_GEMINI_API_KEY') {
        error_log('[Imali Smart] Gemini: GEMINI_API_KEY is missing. Create .env in the project root.');
        return ['ok' => false, 'reason' => 'not_configured', 'text' => ''];
    }
    if (!function_exists('curl_init')) {
        error_log('[Imali Smart] Gemini: PHP curl extension is disabled (enable extension=curl in php.ini).');
        return ['ok' => false, 'reason' => 'curl_unavailable', 'text' => ''];
    }

    $deadline = microtime(true) + $budget;
    $lastError = 'unknown';
    $lastStatus = 0;

    foreach (gemini_model_chain() as $model) {
        $remaining = (int)floor($deadline - microtime(true));
        if ($remaining < 3) break;

        // Gemini 2.5 Flash "thinks" by default and the thinking tokens can eat the whole output budget,
        // which returned an empty answer. Turn thinking off for chat-style answers on those models.
        $attemptBody = $body;
        if (str_starts_with($model, 'gemini-2.5-flash')) {
            $attemptBody['generationConfig']['thinkingConfig'] = ['thinkingBudget' => 0];
        }

        $attempts = 0;
        while ($attempts < 2) {
            $attempts++;
            [$status, $raw, $curlError] = gemini_http_post($model, $apiKey, $attemptBody, (int)min(max(floor($deadline - microtime(true)), 3), $perCallCap));
            $lastStatus = $status;

            // Unknown thinking field on some models: retry once without it.
            if ($status === 400 && isset($attemptBody['generationConfig']['thinkingConfig']) && stripos($raw, 'thinking') !== false) {
                unset($attemptBody['generationConfig']['thinkingConfig']);
                continue;
            }
            // Temporary overload: one short retry.
            if (in_array($status, [500, 502, 503, 504], true) && $attempts < 2 && ($deadline - microtime(true)) > 6) {
                usleep(600000);
                continue;
            }
            break;
        }

        $payload = $raw !== '' ? json_decode($raw, true) : null;
        $apiMessage = is_array($payload) ? (string)($payload['error']['message'] ?? '') : '';

        if ($status >= 200 && $status < 300 && is_array($payload)) {
            $text = '';
            foreach (($payload['candidates'][0]['content']['parts'] ?? []) as $part) {
                if (!empty($part['thought'])) continue; // skip reasoning parts
                if (isset($part['text']) && is_string($part['text'])) $text .= $part['text'];
            }
            $text = trim($text);
            if ($text !== '') {
                if (function_exists('cache_set')) cache_set('gemini_good_model', $model, 6 * 3600);
                return ['ok' => true, 'reason' => 'success', 'text' => $text, 'model_used' => $model];
            }
            $finish = (string)($payload['candidates'][0]['finishReason'] ?? ($payload['promptFeedback']['blockReason'] ?? 'no_candidates'));
            error_log('[Imali Smart] Gemini empty answer on ' . $model . ' (' . $finish . ')');
            $lastError = 'empty_answer_' . $finish;
            continue;
        }

        if ($status === 0) {
            error_log('[Imali Smart] Gemini network error on ' . $model . ': ' . $curlError . ' (if this mentions SSL certificate, set curl.cainfo in php.ini to a cacert.pem)');
            $lastError = 'network_error';
            // A TLS/network failure is the same for every model, so stop early.
            break;
        }
        if ($status === 429) {
            error_log('[Imali Smart] Gemini rate limited (429) on ' . $model . ': ' . $apiMessage);
            $lastError = 'rate_limited';
            continue;
        }
        // Google answers 400 "API key not valid" for a wrong key, and 401/403 for revoked or restricted keys.
        if (in_array($status, [401, 403], true) || ($status === 400 && stripos($apiMessage, 'api key') !== false)) {
            error_log('[Imali Smart] Gemini key rejected (' . $status . '): ' . $apiMessage);
            return ['ok' => false, 'reason' => 'key_rejected', 'status' => $status, 'detail' => $apiMessage, 'text' => ''];
        }
        if ($status === 404) {
            error_log('[Imali Smart] Gemini model not found: ' . $model . ' - ' . $apiMessage);
            if (function_exists('cache_set')) cache_set('gemini_bad_model_' . $model, 1, 3600);
        } else {
            error_log('[Imali Smart] Gemini HTTP ' . $status . ' on ' . $model . ': ' . $apiMessage);
        }
        $lastError = 'http_' . $status;
    }

    return ['ok' => false, 'reason' => $lastError, 'status' => $lastStatus, 'detail' => $apiMessage ?? '', 'text' => ''];
}

/**
 * Reads a proof-of-payment image or PDF with Gemini Vision.
 * This is deliberately an extraction-only helper: callers may show the result
 * for review, but must never write it into a transaction form automatically.
 */
function gemini_scan_document(string $bytes, string $mimeType, string $language = 'English', string $accountHolder = ''): array
{
    if ($bytes === '' || !in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
        return ['ok' => false, 'reason' => 'unsupported_document', 'fields' => []];
    }
    $prompt = 'Inspect this uploaded financial document and decide whether it is a genuine readable bank statement or proof of payment. '
        . 'Return JSON only with these keys: is_valid_statement (boolean), document_type, account_holder, payer_name, recipient_name, amount, currency, payment_date (YYYY-MM-DD), payment_date_text (the date exactly as printed), reference, bank_or_provider, description, confidence, warnings, transaction_type (income or expense, for a single proof of payment: expense when the account holder paid money out, income when they received it), transactions. '
        . 'Set is_valid_statement to true only when the document visibly contains coherent financial-document evidence such as a bank/provider name, dates, amounts, and transaction/payment details. '
        . 'Set it to false for unrelated images, blank pages, fabricated-looking content, unreadable documents, or documents that are not financial records. '
        . 'transactions must be an array of objects with direction (credit or debit, copied from how the document itself shows the row: a Credit/Money in/CR column or a positive balance movement is credit, a Debit/Money out/DR column or a minus sign is debit), transaction_type (income when direction is credit, expense when direction is debit), amount, transaction_date (YYYY-MM-DD), date_text (the date exactly as printed on the document, including words such as Today or Yesterday, for example "Today, 26 Oct 2025 10:45 AM"), description, reference_code, payment_method, and category_hint; use an empty array when no reliable rows are visible. '
        . 'category_hint must be exactly one of these names: for income rows Salary and wages, Grants and support, Interest received, Refunds, Other income; for expense rows Housing, Food and groceries, Transport, Utilities, Airtime and data, Health, Education, Debt repayment, Giving and family support, Bank charges, Other expense. '
        . 'Use null for missing scalar values. Never guess. Do not extract passwords, PINs, OTPs, full card numbers, or full bank account numbers. '
        . 'Use transaction_type income for money received (credits) and expense for money paid out (debits). For a single proof of payment, also include one transaction row. Amounts must be plain positive numbers and every date must be YYYY-MM-DD. Include EVERY transaction row visible in the document. '
        . 'DATE RULES: the date today in South Africa is ' . date('Y-m-d') . '. South African documents write dates day-first (DD/MM/YYYY), so read 05/10/2026 as 5 October 2026, never as 10 May 2026. '
        . 'Copy every date exactly as it is printed on the document and never replace it with the date today. When a row shows a day and month without a year, take the year from the statement period or the document header. If a date is not visible, use null.';
    if (trim($accountHolder) !== '') {
        $prompt .= ' The person using this app (the account holder) is named "' . trim($accountHolder) . '". For a proof of payment: if the account holder is the payer/sender, transaction_type is expense; if the account holder is the recipient/beneficiary (money received), transaction_type is income. Always fill payer_name and recipient_name.';
    }
    $body = [
        'systemInstruction' => ['parts' => [['text' => 'You are a careful financial document reader. ' . gemini_language_instruction($language)]]],
        'contents' => [[
            'role' => 'user',
            'parts' => [
                ['text' => $prompt],
                ['inlineData' => ['mimeType' => $mimeType, 'data' => base64_encode($bytes)]],
            ],
        ]],
        'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 16000, 'responseMimeType' => 'application/json'],
    ];
    $result = gemini_call_api($body, 80, 70);
    if (!$result['ok'] || empty($result['text'])) return ['ok' => false, 'reason' => $result['reason'] ?? 'scan_failed', 'detail' => $result['detail'] ?? '', 'fields' => []];
    // Gemini sometimes wraps JSON in ```json fences, adds text around it, or returns a bare list of rows.
    $text = trim($result['text']);
    $json = json_decode($text, true);
    if (!is_array($json) && preg_match('/\{.*\}|\[.*\]/s', $text, $m)) $json = json_decode($m[0], true);
    if (is_array($json) && (array_keys($json) === range(0, count($json) - 1)) && isset($json[0]) && is_array($json[0]) && isset($json[0]['amount'])) {
        $json = ['is_valid_statement' => true, 'transactions' => $json];
    } elseif (is_array($json) && (array_keys($json) === range(0, count($json) - 1)) && isset($json[0]) && is_array($json[0])) {
        $json = $json[0];
    }
    if (!is_array($json)) return ['ok' => false, 'reason' => 'invalid_scan_response', 'fields' => []];
    $allowed = ['is_valid_statement', 'document_type', 'account_holder', 'payer_name', 'recipient_name', 'amount', 'currency', 'payment_date', 'payment_date_text', 'reference', 'bank_or_provider', 'description', 'confidence', 'warnings', 'transactions', 'transaction_type'];
    $fields = [];
    foreach ($allowed as $key) $fields[$key] = $json[$key] ?? null;
    return ['ok' => true, 'reason' => 'success', 'fields' => $fields, 'model_used' => $result['model_used'] ?? ''];
}

/**
 * Instant replies for greetings and thanks, so they cost no API call.
 */
function gemini_small_talk_reply(string $question, string $language): ?string
{
    $q = mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $question)));
    if ($q === '' || mb_strlen($q) > 30) return null;
    if (in_array($q, ['hi', 'hello', 'hey', 'howzit', 'sawubona', 'molo', 'dumela', 'hallo', 'goeie dag', 'good morning', 'good afternoon', 'good evening'], true)) {
        return "Hello! I'm your Imali Smart assistant. I can help with budgeting, saving, debt payoff, irregular income planning. What would you like to work on today?";
    }
    if (in_array($q, ['thanks', 'thank you', 'thanks a lot', 'ngiyabonga', 'baie dankie', 'enkosi', 'ta'], true)) {
        return "You're welcome! Ask me anything else about your money whenever you need.";
    }
    return null;
}

/**
 * Generate financial guidance or assistant answers using Gemini.
 * Order: instant small talk, cache, Gemini (with model fallback), then the built-in knowledge base.
 */
function gemini_generate(string $question, string $language = 'English', array $context = [], array $history = []): array
{
    // 0. Greetings answered instantly.
    $small = gemini_small_talk_reply($question, $language);
    if ($small !== null && ($language === 'auto' || $language === 'English' || $language === 'en')) {
        return ['ok' => true, 'source' => 'Imali Smart', 'text' => $small, 'cached' => true, 'follow_ups' => [], 'topic' => 'Greeting', 'model_used' => 'instant'];
    }

    // Clean the history: drop the current question if the page already added it, keep the last 6 turns.
    $turns = [];
    foreach (array_slice(array_values(array_filter($history, 'is_array')), -7) as $turn) {
        $text = trim(mb_substr((string)($turn['text'] ?? ''), 0, 700));
        if ($text === '') continue;
        $turns[] = ['role' => (($turn['role'] ?? '') === 'user') ? 'user' : 'model', 'text' => $text];
    }
    if ($turns && $turns[count($turns) - 1]['role'] === 'user' && trim($turns[count($turns) - 1]['text']) === trim($question)) {
        array_pop($turns);
    }
    $turns = array_slice($turns, -6);
    while ($turns && $turns[0]['role'] !== 'user') array_shift($turns); // Gemini needs the first turn to be the user's

    // 1. Cache. Only for stand-alone questions: a follow-up like "and for business?" depends on the earlier chat.
    $normQ = preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($question)));
    $cacheKey = 'gemini_chat_' . hash('sha256', $language . '_' . $normQ . '_' . json_encode($context));
    if (!$turns && function_exists('cache_get')) {
        $cached = cache_get($cacheKey);
        if (is_array($cached) && !empty($cached['text'])) {
            return [
                'ok' => true,
                'source' => 'Imali Smart AI (instant)',
                'text' => $cached['text'],
                'cached' => true,
                'follow_ups' => $cached['follow_ups'] ?? [],
                'topic' => $cached['topic'] ?? 'Financial Guidance',
                'model_used' => $cached['model_used'] ?? 'cache',
            ];
        }
    }

    // 2. Gemini.
    $safeContext = $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'none shared';
    $system = "You are Imali Smart Assistant, a warm, practical money coach for South Africans (personal finance). "
        . gemini_language_instruction($language) . "\n"
        . "How to answer:\n"
        . "- Start with the direct answer in the first sentence. No greetings or filler.\n"
        . "- Keep it short: about 80-170 words, or a little more only when a calculation needs it. Use short bullet points starting with '- ' for steps, and **bold** for key numbers.\n"
        . "- Use rand amounts (R 1,500) and South African context: SARS, UIF, TFSA, stokvels, SASSA, FICA, EFT, loadshedding costs, kota/spaza pricing.\n"
        . "- Show your working when you calculate, and double-check the arithmetic.\n"
        . "- If the user's figures are shared below, use them; never invent figures. If you need a number you do not have, ask for it in one short question.\n"
        . "- When the supplied spending ratio or recommendations indicate pressure, give one or two specific, realistic next steps using the user's actual figures. Do not shame the user or make a diagnosis.\n"
        . "- Review recent transactions for unusual amounts or patterns. You may describe them as possible anomalies or fraud signals, never as confirmed fraud. Tell the user to verify the transaction and contact their bank through an official channel if it is not theirs. Never request an OTP, PIN, CVV, password, or full card/account number.\n"
        . "- You teach and explain; you are not a licensed financial adviser. For large decisions (loans, investments, tax disputes) add one short line suggesting a registered adviser or SARS.\n"
        . "- Imali Smart features you can point to: Transactions, Budgets, Savings, Debts, Steady Pay, Reports and personal money insights.\n"
        . "- Never ask for PINs, passwords, OTPs or CVVs, and warn the user if anyone else does.\n"
        . "User's last-30-day summary: " . $safeContext;

    $contents = [];
    foreach ($turns as $turn) {
        $contents[] = ['role' => $turn['role'], 'parts' => [['text' => $turn['text']]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $question]]];

    $apiResult = gemini_call_api([
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents' => $contents,
        'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 1500, 'topP' => 0.95],
    ]);

    if ($apiResult['ok'] && !empty($apiResult['text'])) {
        if (!$turns && function_exists('cache_set')) {
            cache_set($cacheKey, ['text' => $apiResult['text'], 'follow_ups' => [], 'model_used' => $apiResult['model_used'] ?? ''], 6 * 3600);
        }
        return [
            'ok' => true,
            'source' => 'Gemini AI',
            'text' => $apiResult['text'],
            'model_used' => $apiResult['model_used'] ?? '',
            'cached' => false,
            'follow_ups' => [],
        ];
    }

    // 3. Instant fallback to the South African knowledge base, so the user always gets an answer.
    $fallback = get_smart_financial_faq_answer($question, $context, $language);
    return [
        'ok' => true,
        'reason' => $apiResult['reason'] ?? 'fallback_knowledge_base',
        'detail' => $apiResult['detail'] ?? '',
        'source' => 'Imali Smart Knowledge Base',
        'text' => $fallback['text'],
        'follow_ups' => $fallback['follow_ups'] ?? [],
        'topic' => $fallback['topic'] ?? 'General',
        'cached' => true,
    ];
}

/**
 * High-quality South African financial and platform knowledge base.
 * Guarantees reliable, contextual answers whenever Gemini is rate-limited or offline.
 */
function get_smart_financial_faq_answer(string $question, array $context = [], string $language = 'English'): array
{
    $q = mb_strtolower(trim($question));
    $userIncome = isset($context['recorded_income']) ? (float)$context['recorded_income'] : null;
    $userExpenses = isset($context['recorded_expenses']) ? (float)$context['recorded_expenses'] : null;
    $netMovement = isset($context['recorded_net_movement']) ? (float)$context['recorded_net_movement'] : null;
    $topCategory = isset($context['largest_expense_category']['name']) ? (string)$context['largest_expense_category']['name'] : null;

    $hasUserData = ($userIncome !== null || $userExpenses !== null);
    $userSummarySnippet = '';
    if ($hasUserData) {
        $userSummarySnippet = "\n\n**Your Current 30-Day Financial Snapshot:**\n"
            . "• **Recorded Income:** " . money($userIncome ?? 0) . "\n"
            . "• **Recorded Expenses:** " . money($userExpenses ?? 0) . "\n"
            . "• **Net Cashflow:** " . money($netMovement ?? 0) . ($netMovement >= 0 ? " (Surplus)" : " (Deficit)") . "\n"
            . ($topCategory ? "• **Top Expense Category:** " . htmlspecialchars($topCategory) . "\n" : "");
    }

    // Topic 1: Large amounts & graph scaling
    if (str_contains($q, 'large') || str_contains($q, 'million') || str_contains($q, 'limit') || str_contains($q, 'scale') || str_contains($q, 'graph') || str_contains($q, 'chart') || str_contains($q, 'overflow')) {
        $text = "### Support for Large Financial Amounts & Dynamic Graphs\n\n"
            . "Imali Smart is engineered to handle financial values of every scale without artificial ceilings — from everyday R10 transactions to **hundreds of thousands, millions, and tens of millions of Rand** (e.g. R 1,500,000.00 or R 25,000,000.00+).\n\n"
            . "**Key Financial Graph Enhancements:**\n"
            . "• **Dynamic Axis Scaling:** Chart vertical axes calculate human-friendly ticks automatically based on your actual records (e.g. R 500k, R 1.5M, R 25M).\n"
            . "• **Smart Compact Formatting:** Visual labels abbreviate gracefully (`k` for thousands, `M` for millions, `B` for billions) so chart borders never clip.\n"
            . "• **Exact Precision on Hover:** Moving your cursor or tapping any data point displays the exact, unrounded Rand and cent figure (e.g. *R 1,524,850.75*).\n"
            . "• **Flexible Inputs:** When recording transactions, you can input large values directly with or without commas (e.g. `1500000` or `1,500,000.00`)."
            . $userSummarySnippet;

        return [
            'text' => $text,
            'topic' => 'Large Financial Amounts',
            'follow_ups' => [
                'How do I record a large business expense?',
                'How do I download an executive PDF report of my finances?',
                'How does the chart axis scale automatically?',
            ],
        ];
    }

    // Topic 2: Recording expenses & transactions
    if (str_contains($q, 'expense') || str_contains($q, 'record') || str_contains($q, 'transaction') || str_contains($q, 'input') || str_contains($q, 'duplicate') || str_contains($q, 'decimal')) {
        $text = "### Recording Expenses and Transactions\n\n"
            . "Recording transactions in Imali Smart is designed to be fast, accurate, and protected against accidental duplicate entries:\n\n"
            . "1. **Navigate to Transactions:** Go to the **Transactions** tab from the top navigation.\n"
            . "2. **Enter the Amount:** Type any decimal or whole Rand value (e.g. `R125.50` or `R 250,000.00`). The system automatically handles comma thousands separators and cent precision.\n"
            . "3. **Select Category & Date:** Choose the corresponding expense or income category and verify the transaction date.\n"
            . "4. **Add Notes / Reference:** Optional vendor details or invoice numbers for your records.\n"
            . "5. **Submission Protection:** The submit button provides instant visual feedback and automatically prevents duplicate clicks, ensuring transactions are never recorded twice."
            . $userSummarySnippet;

        return [
            'text' => $text,
            'topic' => 'Expense Recording',
            'follow_ups' => [
                'What happens if I submit an expense with decimal cents?',
                'How do I categorize my expenses effectively?',
                'Can I download a spreadsheet of my recorded transactions?',
            ],
        ];
    }

    // Topic 3: Report Downloads (PDF, Excel, CSV)
    if (str_contains($q, 'report') || str_contains($q, 'download') || str_contains($q, 'pdf') || str_contains($q, 'excel') || str_contains($q, 'csv') || str_contains($q, 'export') || str_contains($q, 'statement')) {
        $text = "### Executive Financial Reports & Downloads\n\n"
            . "Imali Smart provides professional report generation tailored for personal audits, business accounting, and SARS tax preparation under the **Reports** hub:\n\n"
            . "• **Executive PDF Statements:** Formatted official statements featuring your executive summary, income vs. expense breakdown, net savings, and category distribution.\n"
            . "• **Excel-Compatible Worksheets (.xls):** Multi-column spreadsheets with formatted currency figures, totals, and calculations ready for Microsoft Excel or Google Sheets.\n"
            . "• **Clean CSV Transaction History:** Lightweight transaction exports with complete dates, categories, descriptions, and net balances.\n"
            . "• **Custom Date Filters:** Generate reports for the current month, previous quarter, year-to-date, or a custom date range.\n\n"
            . "*All downloads are generated securely from your authenticated database records with human-friendly filenames.*"
            . $userSummarySnippet;

        return [
            'text' => $text,
            'topic' => 'Reports & Statements',
            'follow_ups' => [
                'How do I generate an Excel spreadsheet for tax filing?',
                'Are the downloaded PDF statements accepted by accountants?',
                'How do I filter transactions for a specific month?',
            ],
        ];
    }

    // Topic 4: Budgeting & 50/30/20 Rule
    if (str_contains($q, 'budget') || str_contains($q, '50/30/20') || str_contains($q, 'rule') || str_contains($q, 'limit') || str_contains($q, 'allocate')) {
        $text = "### Budgeting and the 50/30/20 Framework\n\n"
            . "The **50/30/20 budgeting rule** is a proven guideline for managing personal and household cashflow:\n\n"
            . "• **50% Needs:** Essential living expenses — rent/bond, groceries, municipal utilities, basic transport, and school fees.\n"
            . "• **30% Wants:** Discretionary lifestyle spending — dining out, streaming subscriptions, leisure, hobbies, and personal gifts.\n"
            . "• **20% Financial Freedom:** High-impact money moves — debt snowball repayments, multi-stage emergency savings, and retirement cushions.\n\n"
            . "**In Imali Smart:** You can set monthly spending limits for each category under **Budgets**. The platform tracks your real-time spend with color-coded progress indicators, warning you before you exceed category thresholds."
            . $userSummarySnippet;

        return [
            'text' => $text,
            'topic' => 'Budgeting',
            'follow_ups' => [
                'How do I set up a monthly category budget?',
                'How should I divide my income if I have high debt?',
                'Can I track stokvel savings in my budget?',
            ],
        ];
    }

    // Topic 5: Debt Snowball & Repayment
    if (str_contains($q, 'debt') || str_contains($q, 'snowball') || str_contains($q, 'avalanche') || str_contains($q, 'loan') || str_contains($q, 'interest') || str_contains($q, 'repay')) {
        $text = "### Debt Snowball Strategy\n\n"
            . "The **Debt Snowball** is a momentum-based debt elimination method built right into Imali Smart:\n\n"
            . "1. **List All Debts:** Record all accounts (credit cards, clothing accounts, personal loans, vehicle finance) from smallest balance to largest.\n"
            . "2. **Pay Minimums:** Maintain the required minimum payment on all accounts to protect your credit score.\n"
            . "3. **Target Smallest Balance:** Attack the smallest balance with every extra Rand of surplus cashflow until it is completely paid off.\n"
            . "4. **Roll the Payment Forward:** Once the first debt is eliminated, roll its entire monthly payment amount into the next smallest debt.\n\n"
            . "*This compounding snowball creates quick psychological victories and rapidly accelerates total debt freedom.*";

        return [
            'text' => $text,
            'topic' => 'Debt Elimination',
            'follow_ups' => [
                'What is the difference between snowball and avalanche debt repayment?',
                'How do I track loan repayments in Imali Smart?',
                'How does eliminating debt improve my monthly cashflow?',
            ],
        ];
    }

    // Topic 6: Business Till Cash-up & Retail Reconciliation
    if (str_contains($q, 'till') || str_contains($q, 'cash up') || str_contains($q, 'cashup') || str_contains($q, 'spaza') || str_contains($q, 'shop') || str_contains($q, 'shortage') || str_contains($q, 'reconcil')) {
        $text = "### Business Till Cash-Up & Daily Reconciliation\n\n"
            . "For retail shopkeepers, spaza owners, and traders, Imali Smart includes a dedicated **Till Cash-Up** tool under Business Hub:\n\n"
            . "• **Cash Drawer Denomination Count:** Enter your physical note and coin counts (R200, R100, R50, R20, R10 notes and R5, R2, R1 coins).\n"
            . "• **Card Terminal Totals:** Enter your Speedpoint / card swipe batch settlement slip.\n"
            . "• **Automated Variance Detection:** The platform instantly compares counted takings against registered shift sales, highlighting any **shortages** (unaccounted cash loss) or **surpluses** immediately.\n"
            . "• **Shift Records:** Save permanent audit records per cashier or shift to eliminate till discrepancies."
            . $userSummarySnippet;

        return [
            'text' => $text,
            'topic' => 'Till Cash-Up & Retail',
            'follow_ups' => [
                'How do I handle till cash shortages at end-of-day?',
                'Can I track customer credit (papgeld/book) for my shop?',
                'How do I record card machine fees in my expenses?',
            ],
        ];
    }

    // Topic 7: Savings, Emergency Funds & Stokvels
    if (str_contains($q, 'saving') || str_contains($q, 'stokvel') || str_contains($q, 'emergency') || str_contains($q, 'goal') || str_contains($q, 'cushion')) {
        $text = "### Emergency Funds & Stokvel Savings\n\n"
            . "Building a financial safety cushion protects you from unexpected shocks without having to resort to high-interest loans:\n\n"
            . "• **Starter Emergency Fund:** Aim for an initial cushion of **R3,000 to R5,000** for immediate unforeseen needs (tyre punctures, urgent medical co-pays).\n"
            . "• **Full Emergency Fund:** Gradually expand to **3 to 6 months of essential living expenses** kept in an accessible, low-risk interest-bearing account.\n"
            . "• **Stokvel Accountability:** Imali Smart allows you to record your regular rotational stokvel or grocery club contributions, ensuring scheduled payouts are factored into your monthly cashflow."
            . $userSummarySnippet;

        return [
            'text' => $text,
            'topic' => 'Savings & Stokvels',
            'follow_ups' => [
                'How many months of expenses should I save in an emergency fund?',
                'How do I record a monthly stokvel contribution in Imali Smart?',
                'Where should I keep my emergency fund in South Africa?',
            ],
        ];
    }

    // Topic 8: SARS Tax, Invoicing & Compliance
    if (str_contains($q, 'tax') || str_contains($q, 'sars') || str_contains($q, 'invoice') || str_contains($q, 'provisional') || str_contains($q, 'vat')) {
        $text = "### SARS Tax Considerations & Invoicing\n\n"
            . "Keeping accurate financial records is essential for individual and small business compliance with SARS:\n\n"
            . "• **Deductible Business Expenses:** Track all legitimate operational expenses (supplies, equipment, software, phone/internet, travel) with dates and categories.\n"
            . "• **Professional Invoices:** Generate structured invoices with your business name, client details, payment terms, and itemized lines under **Invoices**.\n"
            . "• **Tax Audit Trail:** Download your full financial year records in Excel or PDF format via the **Reports** hub to provide your tax practitioner or SARS eFiling with clean documentation.\n"
            . "• **Turnover Tax vs Standard Income:** Small businesses with turnover below R1 million may qualify for simplified SARS Turnover Tax."
            . $userSummarySnippet;

        return [
            'text' => $text,
            'topic' => 'Tax & Invoicing',
            'follow_ups' => [
                'How do I export my yearly transactions for my tax return?',
                'What business expenses can I deduct for SARS?',
                'How do I create a professional invoice in Imali Smart?',
            ],
        ];
    }

    // Topic 9: Privacy & POPIA Protection
    if (str_contains($q, 'privacy') || str_contains($q, 'popia') || str_contains($q, 'security') || str_contains($q, 'password') || str_contains($q, 'safe') || str_contains($q, 'pin')) {
        $text = "### Privacy, POPIA Compliance & Security\n\n"
            . "Imali Smart adheres strictly to South Africa's **Protection of Personal Information Act (POPIA Act 4 of 2013)**:\n\n"
            . "• **Zero Sensitive Credential Storage:** Imali Smart **never** asks for, receives, or stores your banking passwords, card PINs, ATM cards, CVVs, or bank OTPs.\n"
            . "• **Confidential Financial Records:** Your ledger entries, balances, and reports belong exclusively to you. No other user can access your data.\n"
            . "• **AI Privacy Boundary:** When using AI coaching or FAQ assistance, only authorized high-level summary figures (totals, top category) are processed; individual transaction line items and personal identifiers are never transmitted."
            . $userSummarySnippet;

        return [
            'text' => $text,
            'topic' => 'Security & Privacy',
            'follow_ups' => [
                'Can anyone else view my financial records?',
                'How is my data protected under POPIA?',
                'How do I reset or update my account password?',
            ],
        ];
    }

    // Topic: Investing (shares, ETFs, big companies)
    if (str_contains($q, 'invest') || str_contains($q, 'shares') || str_contains($q, 'stock') || str_contains($q, 'etf') || str_contains($q, 'jse') || str_contains($q, 'dividend') || str_contains($q, 'unit trust') || str_contains($q, 'tfsa')) {
        $text = "### Investing in Big Companies (South Africa)\n\n"
            . "You can own a piece of large listed companies without needing a lot of money:\n\n"
            . "• **ETFs / index funds:** One purchase spreads your money across many big companies (e.g. JSE Top 40 or global index trackers). This is the simplest way to start.\n"
            . "• **Individual shares:** Buy through a licensed broker or investment platform (FSCA-registered). Shares carry more risk than a fund.\n"
            . "• **Tax-Free Savings Account (TFSA):** Hold ETFs inside a TFSA so growth and dividends are tax-free (annual limit R36,000; lifetime R500,000).\n"
            . "• **Before you invest:** clear high-interest debt and keep a 3-month emergency fund first. Only invest money you will not need for 5+ years.\n"
            . "• **Beware scams:** Anyone promising guaranteed high returns, or asking you to send money to a personal account, is not legitimate. Check the provider on the FSCA website.\n\n"
            . "*This is educational information, not personal financial advice. For larger amounts, speak to a registered financial adviser.*"
            . $userSummarySnippet;

        return [
            'text' => $text,
            'topic' => 'Investing',
            'follow_ups' => [
                'What is an ETF and how do I buy one in South Africa?',
                'How does a Tax-Free Savings Account work?',
                'How do I spot an investment scam?',
            ],
        ];
    }

    // Default general response
    $text = "### Welcome to Imali Smart Assistant\n\n"
        . "Imali Smart is your comprehensive South African financial management companion. Here is how you can make the most of the platform:\n\n"
        . "• **Track Transactions:** Record income and expenses of any scale — from R10 to R25,000,000+ — with decimal accuracy and duplicate prevention.\n"
        . "• **Dynamic Visual Graphs:** View income vs. expense breakdowns, monthly cashflows, and net balance charts that automatically scale.\n"
        . "• **Professional Reports:** Download executive PDF statements and Excel spreadsheets for personal tracking or SARS tax preparation.\n"
        . "• **Business Tools:** Reconcile daily till cash takings, track customer credit books, and issue clean client invoices.\n"
        . "• **Financial Discipline:** Implement the 50/30/20 budget framework, set category limits, and eliminate debt using the snowball method."
        . $userSummarySnippet;

    return [
        'text' => $text,
        'topic' => 'General Financial Guidance',
        'follow_ups' => [
            'How do I record a large business expense?',
            'How does the 50/30/20 budget framework work?',
            'How do I download an executive PDF financial report?',
        ],
    ];
}


/**
 * Translate one piece of text (lessons, tips, notifications)
 * Results are saved so the same text is only translated once. Numbers and amounts stay the same.
 */
function gemini_translate_text(string $text, string $targetLang, string $sourceLang = 'en'): array
{
    $text = trim($text);
    if ($text === '') {
        return ['ok' => true, 'text' => '', 'cached' => true];
    }

    if ($targetLang === $sourceLang || $targetLang === 'en') {
        return ['ok' => true, 'text' => $text, 'cached' => true];
    }

    $hash = md5($sourceLang . ':' . $targetLang . ':' . $text);

    // 1. Check in-memory / persistent database translation cache
    if (function_exists('database_ready') && database_ready()) {
        try {
            $stmt = db()->prepare('SELECT translated_text FROM dynamic_translations WHERE translation_hash = :h AND target_lang = :tl LIMIT 1');
            $stmt->execute(['h' => $hash, 'tl' => $targetLang]);
            $cached = $stmt->fetchColumn();
            if ($cached !== false && is_string($cached) && trim($cached) !== '') {
                return ['ok' => true, 'text' => $cached, 'cached' => true];
            }
        } catch (Throwable $e) {}
    }

    // 2. Check the file cache
    if (function_exists('cache_get')) {
        $cText = cache_get("dt_{$targetLang}_{$hash}");
        if ($cText !== null && is_string($cText)) {
            return ['ok' => true, 'text' => $cText, 'cached' => true];
        }
    }

    // 3. Call Gemini Translation API
    $targetName = gemini_resolve_language_name($targetLang);
    $body = [
        'systemInstruction' => ['parts' => [['text' => 'You are a direct translation engine. Translate the user input directly into ' . $targetName . ' (' . $targetLang . '). Output ONLY the raw translated string without preamble, conversational remarks, markdown explanations, notes, or quotes. Preserve all numbers, currency symbols like R, and percentages unchanged.']]],
        'contents' => [['role' => 'user', 'parts' => [['text' => $text]]]],
        'generationConfig' => ['temperature' => 0.0, 'maxOutputTokens' => 1500],
    ];

    $result = gemini_call_api($body);
    if ($result['ok'] && !empty($result['text'])) {
        $translated = trim($result['text']);
        // Strip accidental enclosing quotes if any
        if ((str_starts_with($translated, '"') && str_ends_with($translated, '"')) ||
            (str_starts_with($translated, "'") && str_ends_with($translated, "'"))) {
            $translated = trim(substr($translated, 1, -1));
        }

        // Save to persistent database cache
        if (function_exists('database_ready') && database_ready()) {
            try {
                $ins = db()->prepare('
                    INSERT INTO dynamic_translations (translation_hash, source_lang, target_lang, source_text, translated_text)
                    VALUES (:h, :sl, :tl, :st, :tt)
                    ON DUPLICATE KEY UPDATE translated_text = VALUES(translated_text)
                ');
                $ins->execute([
                    'h' => $hash,
                    'sl' => $sourceLang,
                    'tl' => $targetLang,
                    'st' => $text,
                    'tt' => $translated,
                ]);
            } catch (Throwable $e) {
                // For SQLite compatibility with ON DUPLICATE KEY
                try {
                    $insLite = db()->prepare('
                        INSERT OR REPLACE INTO dynamic_translations (translation_hash, source_lang, target_lang, source_text, translated_text)
                        VALUES (:h, :sl, :tl, :st, :tt)
                    ');
                    $insLite->execute([
                        'h' => $hash,
                        'sl' => $sourceLang,
                        'tl' => $targetLang,
                        'st' => $text,
                        'tt' => $translated,
                    ]);
                } catch (Throwable $e2) {}
            }
        }

        if (function_exists('cache_set')) {
            cache_set("dt_{$targetLang}_{$hash}", $translated, 86400 * 30);
        }

        return ['ok' => true, 'text' => $translated, 'cached' => false];
    }

    // Graceful fallback to original text so UI never breaks
    return ['ok' => false, 'text' => $text, 'cached' => false, 'reason' => $result['reason'] ?? 'api_failed'];
}

/**
 * Generate interactive financial quizzes and tests for personal money learning using Gemini AI.
 * Falls back to curated bank if offline or rate limited.
 */
function gemini_generate_quiz(string $topic = 'budgeting', string $level = 'beginner', int $numQuestions = 5, string $language = 'English'): array
{
    $numQuestions = max(1, min(10, $numQuestions));
    $validLevels = ['beginner', 'intermediate', 'advanced'];
    if (!in_array($level, $validLevels, true)) {
        $level = 'beginner';
    }

    $systemInstruction = "You are a master financial educator, certified accountant, and coach for 'Imali Smart personal money learning' in South Africa. "
        . "Your task is to generate realistic, practical multiple-choice quiz and test questions for adult learners and entrepreneurs. "
        . "RULES: "
        . "1. Use real South African context (Rand / ZAR with R symbol, e.g. R 1,500.00, R 250, R 15,000). "
        . "2. Include real-life scenarios: household budgeting, spaza shops, grocery stokvels, taxi fares, monthly allowances, store credit traps, compound interest, emergency funds, debt snowball, and SARS small business turnover tax. "
        . "3. EVERY calculation MUST BE 100% MATHEMATICALLY ACCURATE. Double check all additions, subtractions, percentages, and formulas before returning. "
        . "4. Output ONLY a valid JSON array of objects. Do not wrap in markdown or conversational chatter. "
        . "Each question object MUST have these exact keys: "
        . "  - id: (integer 1 to N) "
        . "  - title: (short title, e.g. '50/30/20 Salary Breakdown') "
        . "  - scenario: (1-2 sentence real-life story / problem description) "
        . "  - question: (the specific question to solve) "
        . "  - calculation_formula: (exact math formula and step-by-step arithmetic) "
        . "  - options: (array of exactly 4 choices with formatted amounts, e.g. ['R 750', 'R 850', 'R 650', 'R 1,050']) "
        . "  - correct_index: (integer 0, 1, 2, or 3 pointing to the correct choice) "
        . "  - explanation: (clear educational breakdown of why the answer is right and why the other choices fail) "
        . "  - key_takeaway: (1 crisp practical takeaway rule) "
        . "  - difficulty: (string: 'beginner', 'intermediate', or 'advanced')";

    $userPrompt = "Generate {$numQuestions} unique, practical financial questions for curriculum level '{$level}' on the topic: '{$topic}'. Language: {$language}. Return ONLY the JSON array.";

    $body = [
        'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
        'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
        'generationConfig' => [
            'temperature' => 0.25,
            'maxOutputTokens' => 3000,
            'responseMimeType' => 'application/json'
        ]
    ];

    $apiResult = gemini_call_api($body, 25);
    if ($apiResult['ok'] && !empty($apiResult['text'])) {
        $rawText = trim($apiResult['text']);
        // Strip markdown code fences if model enclosed them
        if (str_starts_with($rawText, '```')) {
            $rawText = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
            $rawText = preg_replace('/\s*```$/', '', $rawText);
            $rawText = trim($rawText);
        }
        try {
            $parsed = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($parsed) && count($parsed) > 0) {
                // If it returned an object with a 'questions' key, normalize
                if (isset($parsed['questions']) && is_array($parsed['questions'])) {
                    $parsed = $parsed['questions'];
                }
                $validQuestions = [];
                foreach ($parsed as $idx => $q) {
                    if (isset($q['question'], $q['options'], $q['correct_index']) && is_array($q['options']) && count($q['options']) === 4) {
                        $correctIdx = (int)$q['correct_index'];
                        if ($correctIdx < 0 || $correctIdx > 3) $correctIdx = 0;
                        $validQuestions[] = [
                            'id' => (int)($q['id'] ?? ($idx + 1)),
                            'title' => (string)($q['title'] ?? ('Question ' . ($idx + 1))),
                            'scenario' => (string)($q['scenario'] ?? ''),
                            'question' => (string)$q['question'],
                            'calculation_formula' => (string)($q['calculation_formula'] ?? ''),
                            'options' => array_map('strval', $q['options']),
                            'correct_index' => $correctIdx,
                            'explanation' => (string)($q['explanation'] ?? 'This option represents the mathematically correct outcome.'),
                            'key_takeaway' => (string)($q['key_takeaway'] ?? 'Always separate essential costs from discretionary spending.'),
                            'difficulty' => (string)($q['difficulty'] ?? $level),
                        ];
                    }
                }
                if (count($validQuestions) > 0) {
                    return [
                        'ok' => true,
                        'source' => 'gemini',
                        'model_used' => $apiResult['model_used'] ?? 'gemini-3.8-flash',
                        'level' => $level,
                        'topic' => $topic,
                        'count' => count($validQuestions),
                        'questions' => array_slice($validQuestions, 0, $numQuestions),
                    ];
                }
            }
        } catch (Throwable $jsonErr) {
            error_log('[Imali Smart] Gemini quiz JSON parse error: ' . $jsonErr->getMessage());
        }
    }

    // Fallback to comprehensive curated questions
    $bank = get_curated_quiz_bank($topic, $level, $numQuestions);
    return [
        'ok' => true,
        'source' => 'curated_bank',
        'model_used' => 'Imali Curated Financial Engine',
        'level' => $level,
        'topic' => $topic,
        'count' => count($bank),
        'questions' => $bank,
    ];
}

/**
 * Rich offline & fallback question bank for personal money learning.
 * Guarantees high-precision South African financial scenarios.
 */
function get_curated_quiz_bank(string $topic, string $level, int $count = 5): array
{
    $allQuestions = [
        'beginner' => [
            [
                'id' => 1,
                'title' => 'Weekly Envelope Allocation',
                'scenario' => 'Thabo earns R 3,200 this week from part-time retail work. His essential fixed transport and groceries cost R 1,950.',
                'question' => 'How much discretionary money remains for emergency savings and flexible expenses?',
                'calculation_formula' => 'R 3,200 (Total earnings) - R 1,950 (Committed essential costs) = R 1,250',
                'options' => ['R 1,250', 'R 1,450', 'R 950', 'R 5,150'],
                'correct_index' => 0,
                'explanation' => 'Subtract committed living expenses (R 1,950) directly from total net earnings (R 3,200) to find the remaining surplus of R 1,250. Directing at least 20% (R 250) of this remainder to an emergency buffer creates sustainable security.',
                'key_takeaway' => 'Always fund fixed living essentials before assigning flexible discretionary cash.',
                'difficulty' => 'beginner',
            ],
            [
                'id' => 2,
                'title' => 'The 50/30/20 Framework in Practice',
                'scenario' => 'Nomsa receives a monthly net salary of R 14,000 into her bank account. She wants to adopt the 50/30/20 budgeting rule.',
                'question' => 'What is the maximum amount Nomsa should allocate toward her "Needs" (housing, utilities, groceries, transport)?',
                'calculation_formula' => '50% of R 14,000 = 0.50 × 14,000 = R 7,000',
                'options' => ['R 7,000', 'R 4,200', 'R 2,800', 'R 9,500'],
                'correct_index' => 0,
                'explanation' => 'Under the 50/30/20 guideline: 50% = Needs (R 7,000), 30% = Wants (R 4,200), and 20% = Savings / Debt Snowball (R 2,800).',
                'key_takeaway' => 'Capping needs at 50% of take-home pay prevents lifestyle inflation and paycheck-to-paycheck stress.',
                'difficulty' => 'beginner',
            ],
            [
                'id' => 3,
                'title' => 'Debit Order & Bank Charge Protection',
                'scenario' => 'Sipho has an active debit order of R 450 scheduled for the 25th. His account balance on the morning of the 25th is R 410.',
                'question' => 'What will happen if the debit order bounces due to insufficient funds?',
                'calculation_formula' => 'Unpaid debit order penalty (often R 50 to R 115 depending on bank) + unpaid creditor fee + negative impact on credit record.',
                'options' => [
                    'The bank charges an unpaid debit order fee and the missed payment damages his credit score',
                    'The bank pays the remaining R 40 for free with zero penalties',
                    'The creditor automatically cancels the debt contract without consequence',
                    'Nothing happens until next month'
                ],
                'correct_index' => 0,
                'explanation' => 'Bounced debit orders attract penalty dispute charges from your bank and mark a default on your credit record with credit bureaus like TransUnion and Experian.',
                'key_takeaway' => 'Maintain an emergency buffer in your cheque account so scheduled debits never bounce.',
                'difficulty' => 'beginner',
            ],
            [
                'id' => 4,
                'title' => 'Needs vs. Wants Trade-Off',
                'scenario' => 'Ayanda has R 600 remaining before payday. She needs R 350 for her monthly work train ticket and wants a pair of designer branded sunglasses on sale for R 400.',
                'question' => 'What is the financially responsible decision?',
                'calculation_formula' => 'Available R 600 - Train Ticket R 350 = R 250 surplus remaining. Sunglasses cost R 400 > R 250.',
                'options' => [
                    'Purchase the train ticket first; save the remaining R 250 toward buying the sunglasses next month',
                    'Buy the sunglasses on credit and skip buying the train ticket',
                    'Borrow R 200 from a loan shark to buy both items today',
                    'Spend the full R 600 on dining out because it was a tough week'
                ],
                'correct_index' => 0,
                'explanation' => 'Work transport directly enables your continued income. Buying wants before needs risks losing your job or falling into predatory high-interest debt.',
                'key_takeaway' => 'Income-generating tools (like transit tickets) take absolute priority over depreciating lifestyle items.',
                'difficulty' => 'beginner',
            ],
            [
                'id' => 5,
                'title' => 'Starter Emergency Buffer Goal',
                'scenario' => 'Kagiso wants to build a Level 1 Starter Emergency Cushion to avoid using store cards for minor breakdowns.',
                'question' => 'What is the recommended starter emergency cushion target for an entry-level income earner in South Africa?',
                'calculation_formula' => 'Level 1 target: R 1,500 to R 3,000 for unexpected tyre repair, plumbing, or urgent co-pays.',
                'options' => ['R 2,500 – R 3,000', 'R 100,000', 'R 50', 'Zero (rely on credit cards)'],
                'correct_index' => 0,
                'explanation' => 'A starter cushion of R 2,500 to R 3,000 handles 80% of minor household crises without taking out a micro-loan or payday loan at 60%+ APR.',
                'key_takeaway' => 'Build a starter emergency cushion before aggressively investing in long-term assets.',
                'difficulty' => 'beginner',
            ]
        ],
        'intermediate' => [
            [
                'id' => 1,
                'title' => 'Compound Interest vs. Inflation',
                'scenario' => 'Lerato invests R 10,000 in a fixed deposit paying 9.0% annual interest compounded monthly. Inflation averages 5.0% per annum.',
                'question' => 'What is Lerato\'s approximate real (inflation-adjusted) return after one year?',
                'calculation_formula' => 'Nominal rate (9.0%) - Inflation rate (5.0%) ≈ 4.0% real return on purchasing power.',
                'options' => ['Approximately 4.0% real growth', 'Negative 4.0% loss', '14.0% growth', '0.0% unchanged'],
                'correct_index' => 0,
                'explanation' => 'Real return measures actual purchasing power gained after subtracting inflation: 9% nominal yield minus 5% inflation gives approximately 4% real wealth accumulation.',
                'key_takeaway' => 'To build true wealth, your investment yield must consistently beat CPI inflation.',
                'difficulty' => 'intermediate',
            ],
            [
                'id' => 2,
                'title' => 'Debt Snowball Ordering',
                'scenario' => 'Mpho has 3 active debts: Credit Card (R 12,000 at 22%), Clothing Account (R 1,800 at 24%), and Personal Loan (R 35,000 at 18%).',
                'question' => 'Using Dave Ramsey\'s Debt Snowball method, which debt should Mpho attack first with extra payments?',
                'calculation_formula' => 'Order debts strictly by balance: 1. Clothing Account (R 1,800), 2. Credit Card (R 12,000), 3. Personal Loan (R 35,000).',
                'options' => [
                    'Clothing Account (R 1,800 balance)',
                    'Personal Loan (R 35,000 balance)',
                    'Credit Card (R 12,000 balance)',
                    'Pay equal R 100 on all three debts'
                ],
                'correct_index' => 0,
                'explanation' => 'The Debt Snowball prioritizes the smallest balance first (Clothing Account: R 1,800) regardless of interest rate. Knocking out the smallest debt creates rapid psychological momentum and frees up minimum monthly cashflow to roll into the next debt.',
                'key_takeaway' => 'Knock out your smallest debt first to build unstoppable psychological momentum.',
                'difficulty' => 'intermediate',
            ],
            [
                'id' => 3,
                'title' => 'National Credit Act (NCA) Interest Limits',
                'scenario' => 'A micro-lender offers a 1-month short-term loan of R 1,000 and demands R 1,600 repayment next month (a 60% monthly interest rate).',
                'question' => 'Is this legal under the South African National Credit Act (NCA)?',
                'calculation_formula' => 'NCA max short-term credit rate is 5% per month for the first agreement in a calendar year (and initiation fee capped). 60% per month is predatory and illegal.',
                'options' => [
                    'No, 60% in a single month is predatory and breaches NCA statutory caps',
                    'Yes, lenders can charge any interest rate agreed in writing',
                    'Yes, provided they hold your ID card as deposit',
                    'Only if approved by the local municipality'
                ],
                'correct_index' => 0,
                'explanation' => 'Under South African regulations administered by the National Credit Regulator (NCR), short-term micro-loans have strict interest and fee caps. Demanding 60% monthly interest is illegal mashonisa activity.',
                'key_takeaway' => 'Never borrow from lenders who exceed NCA rate caps or demand bank cards as collateral.',
                'difficulty' => 'intermediate',
            ],
            [
                'id' => 4,
                'title' => 'True Cost of Financing a Vehicle',
                'scenario' => 'Bongani buys a used vehicle for R 120,000 with a 35% balloon payment at the end of a 60-month term.',
                'question' => 'What is the danger of a balloon payment?',
                'calculation_formula' => '35% of R 120,000 = R 42,000 lump sum due at end of term, while paying interest on R 42,000 for the full 5 years.',
                'options' => [
                    'A lump sum of R 42,000 must be paid or refinanced at year 5, incurring double interest',
                    'The car becomes free after 60 months with no further obligations',
                    'The bank pays the remaining 35% on your behalf',
                    'The balloon amount is subtracted from your original deposit'
                ],
                'correct_index' => 0,
                'explanation' => 'A balloon payment reduces monthly instalments artificially, but keeps you paying interest on the balloon portion (R 42,000) for the entire 5 years. At month 60, you must settle R 42,000 in cash or re-finance.',
                'key_takeaway' => 'Avoid large balloon payments that trap you in continuous car debt cycles.',
                'difficulty' => 'intermediate',
            ],
            [
                'id' => 5,
                'title' => 'Stokvel Governance & Yield',
                'scenario' => 'A 10-member grocery stokvel collects R 1,000 per member monthly into a 32-day notice account yielding 8.5% annual interest.',
                'question' => 'How much total principal capital does the stokvel accumulate over a 12-month cycle before interest?',
                'calculation_formula' => '10 members × R 1,000/month × 12 months = R 120,000 total contributions.',
                'options' => ['R 120,000', 'R 12,000', 'R 100,000', 'R 144,000'],
                'correct_index' => 0,
                'explanation' => '10 members contributing R 1,000 monthly pool R 10,000 per month, totaling R 120,000 over 12 months. When kept in an interest-bearing notice account rather than a zero-interest savings tin, members earn thousands in extra interest bonuses.',
                'key_takeaway' => 'Formalize stokvel funds in compliant business notice accounts to earn collective yield.',
                'difficulty' => 'intermediate',
            ]
        ],
        'advanced' => [
            [
                'id' => 1,
                'title' => 'Gross Margin vs. Markup Calculation',
                'scenario' => 'A fast-food entrepreneur prepares gourmet kota sandwiches. The true direct cost (bread, polony, cheese, egg, packaging) is R 30 per kota. She sells each kota for R 50.',
                'question' => 'What is the Markup percentage and what is the Gross Profit Margin percentage?',
                'calculation_formula' => 'Profit = R 50 - R 30 = R 20. Markup = (20 / 30) × 100 = 66.7%. Margin = (20 / 50) × 100 = 40.0%.',
                'options' => [
                    'Markup is 66.7%, Gross Margin is 40.0%',
                    'Markup is 40.0%, Gross Margin is 66.7%',
                    'Both are 20.0%',
                    'Markup is 50.0%, Gross Margin is 50.0%'
                ],
                'correct_index' => 0,
                'explanation' => 'Markup is calculated on cost: (R 20 / R 30) = 66.7%. Gross Profit Margin is calculated on selling price: (R 20 / R 50) = 40.0%. Confusing margin with markup is one of the leading causes of small business bankruptcy.',
                'key_takeaway' => 'Never confuse markup with margin; margin reflects the actual percentage of sales kept as profit.',
                'difficulty' => 'advanced',
            ],
            [
                'id' => 2,
                'title' => 'Break-Even Sales Volume',
                'scenario' => 'A township hair salon has fixed monthly overheads of R 8,000 (rent, power, booking software). Each styling appointment sells for R 250 and consumes R 50 in direct variable products.',
                'question' => 'How many client appointments must the salon perform each month just to break even (cover all costs)?',
                'calculation_formula' => 'Contribution Margin per client = R 250 - R 50 = R 200. Break-even volume = R 8,000 / R 200 = 40 appointments.',
                'options' => ['40 appointments', '32 appointments', '50 appointments', '160 appointments'],
                'correct_index' => 0,
                'explanation' => 'Each client contributes R 200 (R 250 price - R 50 variable cost) toward fixed rent. Dividing R 8,000 by R 200 gives 40 appointments per month (or roughly 10 appointments per week) to reach zero-profit break-even.',
                'key_takeaway' => 'Knowing your break-even volume sets your non-negotiable minimum weekly sales target.',
                'difficulty' => 'advanced',
            ],
            [
                'id' => 3,
                'title' => 'SARS Turnover Tax for Micro-Enterprises',
                'scenario' => 'An electrical repair enterprise registered for SARS Turnover Tax generated R 450,000 in total qualifying sales during the tax year.',
                'question' => 'Using the SARS Turnover Tax bracket (R 335,001 – R 500,000: 1% of amount above R 335,000), what is the annual tax liability?',
                'calculation_formula' => 'Taxable excess = R 450,000 - R 335,000 = R 115,000. Tax owed = 1% of R 115,000 = R 1,150.',
                'options' => ['R 1,150', 'R 4,500', 'R 126,000 (standard 28% corporate tax)', 'Zero'],
                'correct_index' => 0,
                'explanation' => 'SARS Turnover Tax replaces normal income tax, CGT, and provisional tax for businesses under R1m turnover. On R 450,000 turnover, the tax is just 1% of the excess over R 335,000, which equals R 1,150.',
                'key_takeaway' => 'Qualifying micro-enterprises save significant compliance costs by opting into SARS Turnover Tax.',
                'difficulty' => 'advanced',
            ],
            [
                'id' => 4,
                'title' => 'Cash Flow Runway & Working Capital',
                'scenario' => 'A mobile car wash business has R 18,000 in the bank. Monthly operational costs are R 12,000, while monthly customer revenue is R 6,000 during a rainy winter dip.',
                'question' => 'What is the net monthly burn rate and how many months of cash runway does the business have?',
                'calculation_formula' => 'Monthly burn = R 12,000 costs - R 6,000 sales = -R 6,000/month. Runway = R 18,000 / R 6,000 = 3 months.',
                'options' => [
                    'Burn rate is R 6,000/month; Runway is 3 months',
                    'Burn rate is R 12,000/month; Runway is 1.5 months',
                    'Burn rate is R 6,000/month; Runway is 12 months',
                    'The business has unlimited runway'
                ],
                'correct_index' => 0,
                'explanation' => 'Net burn rate is the net cash deficit per month (R 12,000 - R 6,000 = R 6,000). At R 6,000 per month, an R 18,000 reserve lasts exactly 3 months before running out of operating cash.',
                'key_takeaway' => 'Monitor your cash runway closely so you can adjust expenses months before cash runs out.',
                'difficulty' => 'advanced',
            ],
            [
                'id' => 5,
                'title' => 'Till Cash-Up Variance & Internal Controls',
                'scenario' => 'At end-of-day shift closure, a spaza shop register shows R 4,850 in expected cash sales. The physical cash counted in the drawer is R 4,620.',
                'question' => 'What is the till variance and how should the owner record it?',
                'calculation_formula' => 'Variance = Actual cash (R 4,620) - Expected cash (R 4,850) = -R 230 (Cash Shortage).',
                'options' => [
                    'A Shortage of R 230; investigate potential cashier change errors or untracked cash payouts',
                    'A Surplus of R 230; record as bonus income',
                    'The till balances with zero discrepancies',
                    'Ignore the difference if under R 500'
                ],
                'correct_index' => 0,
                'explanation' => 'Physical counted cash (R 4,620) is R 230 less than expected sales (R 4,850). This is a cash shortage that must be audited for unrecorded petty cash payouts or change errors.',
                'key_takeaway' => 'Daily till cash-ups catch cash leakages immediately before they compound over months.',
                'difficulty' => 'advanced',
            ]
        ]
    ];

    $pool = $allQuestions[$level] ?? $allQuestions['beginner'];
    return array_slice($pool, 0, $count);
}
