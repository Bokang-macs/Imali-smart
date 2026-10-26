<?php
// Creates or updates a budget for the logged-in user after checking the form values.
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

require_auth();
require_csrf();

$user = current_user();
$name = trim((string) ($_POST['name'] ?? ''));
$amount = parse_money_input($_POST['amount'] ?? 0);
$start = trim((string) ($_POST['period_start'] ?? date('Y-m-01')));
$end = trim((string) ($_POST['period_end'] ?? date('Y-m-t')));
$scope = trim((string) ($_POST['scope'] ?? 'personal'));

if ($name === '' || $amount <= 0) {
    flash('error', 'Please provide a valid budget name and amount.');
    redirect_to('budgets.php');
}
if (!is_valid_ymd($start) || !is_valid_ymd($end) || $end < $start) {
    flash('error', 'Please choose a real start date and an end date that is not before it.');
    redirect_to('budgets.php');
}

try {
    $stmt = db()->prepare('
        INSERT INTO budgets (user_id, name, amount, period_start, period_end, scope)
        VALUES (:user_id, :name, :amount, :start, :end, :scope)
    ');
    $stmt->execute([
        'user_id' => $user['id'],
        'name' => $name,
        'amount' => $amount,
        'start' => $start,
        'end' => $end,
        'scope' => $scope,
    ]);
    record_audit_event((int)$user['id'], 'budget.created', 'budget', (int)db()->lastInsertId(), ['name'=>$name,'amount'=>$amount,'period_start'=>$start,'period_end'=>$end]);
    if (function_exists('invalidate_financial_records_cache')) {
        invalidate_financial_records_cache((int) $user['id']);
    }
    flash('success', 'Budget allocated successfully.');
} catch (Throwable $e) {
    flash('error', 'Could not save budget: ' . $e->getMessage());
}

redirect_to('budgets.php');
