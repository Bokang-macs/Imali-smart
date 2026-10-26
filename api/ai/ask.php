<?php
/**
 * AI Assistant / FAQ Question Endpoint.
 * Supports both authenticated users and guests asking financial and platform questions.
 * When authenticated with consent, includes safe 30-day aggregate financial totals.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/gemini.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!request_method_is('POST')) {
    json_response(['ok' => false, 'error' => 'POST request method required.'], 405);
}

// Receive payload from POST or JSON input
$rawInput = file_get_contents('php://input');
$bodyData = [];
if (!empty($rawInput) && str_starts_with(trim($rawInput), '{')) {
    try {
        $bodyData = json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $bodyData = [];
    }
}
$data = !empty($bodyData) ? $bodyData : $_POST;

// Validate CSRF if token is provided or user has active session with CSRF set
$submittedToken = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!empty($submittedToken) && !verify_csrf($submittedToken)) {
    json_response(['ok' => false, 'error' => 'Session expired. Please refresh the page.'], 419);
}

$question = trim((string)($data['question'] ?? ''));
if ($question === '' || mb_strlen($question) > 1500) {
    json_response(['ok' => false, 'error' => 'Please provide a question of up to 1,500 characters.'], 422);
}

$language = (string)($data['language'] ?? 'auto');
if (!array_key_exists($language, gemini_language_options())) {
    $language = 'auto';
}

// Conversational history if passed
$history = [];
if (!empty($data['history'])) {
    $rawHistory = $data['history'];
    if (is_string($rawHistory)) {
        try {
            $decoded = json_decode($rawHistory, true);
            if (is_array($decoded)) {
                $history = $decoded;
            }
        } catch (Throwable $e) {}
    } elseif (is_array($rawHistory)) {
        $history = $rawHistory;
    }
}

// Build contextual summary if user is logged in and consent is granted
$context = [];
$insights = [];
$fraudSignals = [];
$isAuth = is_authenticated();
$consentGranted = (isset($data['consent']) && ($data['consent'] === '1' || $data['consent'] === 1 || $data['consent'] === true));

if ($isAuth && $consentGranted && database_ready()) {
    $user = current_user();
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    try {
        $stmt = db()->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS expenses,
                COUNT(*) AS total_transactions
            FROM transactions 
            WHERE user_id = :id AND deleted_at IS NULL AND transaction_date >= :since
        ");
        $stmt->execute(['id' => $user['id'], 'since' => $thirtyDaysAgo]);
        $row = $stmt->fetch() ?: [];

        $inc = (float)($row['income'] ?? 0);
        $exp = (float)($row['expenses'] ?? 0);
        $netMovement = $inc - $exp;

        $context = [
            'period' => 'last 30 days',
            'recorded_income' => $inc,
            'recorded_expenses' => $exp,
            'recorded_net_movement' => $netMovement,
            'total_transactions' => (int)($row['total_transactions'] ?? 0),
            'currency' => 'ZAR (R)',
        ];

        // Top expense category
        $catStmt = db()->prepare("
            SELECT c.name, SUM(t.amount) as amount 
            FROM transactions t 
            LEFT JOIN expense_categories c ON c.id = t.category_id 
            WHERE t.user_id = :id AND t.transaction_type = 'expense' AND t.deleted_at IS NULL AND t.transaction_date >= :since 
            GROUP BY c.name 
            ORDER BY amount DESC 
            LIMIT 1
        ");
        $catStmt->execute(['id' => $user['id'], 'since' => $thirtyDaysAgo]);
        $context['largest_expense_category'] = $catStmt->fetch() ?: null;

        // Give Gemini enough real, recent detail to make useful recommendations without
        // sending credentials, account numbers, or full historical statements.
        $txStmt = db()->prepare("
            SELECT t.transaction_date, t.transaction_type, t.amount, t.description,
                   COALESCE(c.name, 'Uncategorised') AS category
            FROM transactions t
            LEFT JOIN expense_categories c ON c.id = t.category_id
            WHERE t.user_id = :id AND t.deleted_at IS NULL
              AND t.transaction_date >= :since
            ORDER BY t.transaction_date DESC, t.id DESC
            LIMIT 30
        ");
        $txStmt->execute(['id' => $user['id'], 'since' => $thirtyDaysAgo]);
        $transactions = array_map(static function (array $tx): array {
            return [
                'date' => (string)$tx['transaction_date'],
                'type' => (string)$tx['transaction_type'],
                'amount' => (float)$tx['amount'],
                'description' => mb_substr((string)$tx['description'], 0, 80),
                'category' => mb_substr((string)$tx['category'], 0, 60),
            ];
        }, $txStmt->fetchAll() ?: []);
        $context['recent_transactions'] = $transactions;

        $expenseRows = array_values(array_filter($transactions, static fn(array $tx): bool => $tx['type'] === 'expense'));
        $expenseCount = count($expenseRows);
        $averageExpense = $expenseCount > 0
            ? array_sum(array_column($expenseRows, 'amount')) / $expenseCount
            : 0.0;
        $expenseRatio = $inc > 0 ? ($exp / $inc) : null;

        if ($expenseRatio !== null && $expenseRatio >= 0.8) {
            $insights[] = 'Spending is using ' . number_format($expenseRatio * 100, 0) . '% of recorded income. Review wants, subscriptions, and variable costs before the next pay cycle.';
        } elseif ($expenseRatio !== null && $expenseRatio >= 0.6) {
            $insights[] = 'Spending is above 60% of recorded income. Check the largest category and set a practical limit in Budgets.';
        }
        if ($netMovement < 0) {
            $insights[] = 'The last 30 days show a cash-flow deficit of ' . money(abs($netMovement)) . '. Pause non-essential spending and prioritise bills and a small buffer.';
        }
        if ($topCategory = (string)($context['largest_expense_category']['name'] ?? '')) {
            $insights[] = 'The largest expense category is ' . $topCategory . '. Compare its total with your needs/wants plan and consider a category budget.';
        }

        foreach ($expenseRows as $tx) {
            $isLarge = $averageExpense > 0 && $tx['amount'] >= max($averageExpense * 3, 5000);
            if ($isLarge) {
                $fraudSignals[] = [
                    'type' => 'unusual_amount',
                    'date' => $tx['date'],
                    'amount' => $tx['amount'],
                    'description' => $tx['description'],
                    'message' => 'This expense is much larger than your recent average. Confirm it is yours and contact your bank immediately if you do not recognise it.',
                ];
            }
        }
        $fraudSignals = array_slice($fraudSignals, 0, 5);
        $context['spending_ratio'] = $expenseRatio;
        $context['average_expense'] = round($averageExpense, 2);
        $context['recommendations'] = $insights;
        $context['fraud_signals'] = $fraudSignals;
    } catch (Throwable $e) {
        // Fallback to basic context without crashing
        $context = ['currency' => 'ZAR (R)'];
    }
}

// Release the session lock so other pages do not wait while the AI thinks.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
@set_time_limit(40);

// Generate answer via Gemini with knowledge base fallback
$result = gemini_generate($question, $language, $context, $history);

if (!empty($result['text'])) {
    json_response([
        'ok' => true,
        'ai_status' => ($result['source'] ?? '') === 'Imali Smart Knowledge Base' ? ('offline:' . ($result['reason'] ?? 'unknown')) : 'online',
        'source' => $result['source'] ?? 'Gemini AI',
        'language' => $language,
        'answer' => $result['text'],
        'cached' => !empty($result['cached']),
        'model_used' => $result['model_used'] ?? '',
        'follow_ups' => $result['follow_ups'] ?? [],
        'topic' => $result['topic'] ?? null,
        'recommendations' => $insights,
        'fraud_signals' => $fraudSignals,
    ]);
}

// Safety fallback
$fallback = get_smart_financial_faq_answer($question, $context, $language);
json_response([
    'ok' => true,
    'source' => 'Imali Smart Knowledge Base',
    'language' => $language,
    'answer' => $fallback['text'],
    'cached' => true,
    'model_used' => 'Imali Financial Engine',
    'follow_ups' => $fallback['follow_ups'] ?? [],
    'topic' => $fallback['topic'] ?? 'General',
    'recommendations' => $insights,
    'fraud_signals' => $fraudSignals,
]);
