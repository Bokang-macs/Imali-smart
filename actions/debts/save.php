<?php
// Creates or updates a debt for the logged-in user.
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

require_auth();
require_csrf();

$user = current_user();
$creditor = trim((string) ($_POST['creditor_name'] ?? ''));
$total = parse_money_input($_POST['total_amount'] ?? 0);
$balance = isset($_POST['current_balance']) && $_POST['current_balance'] !== '' ? parse_money_input($_POST['current_balance']) : $total;
$rate = (float) ($_POST['interest_rate'] ?? 0);
$minPayment = parse_money_input($_POST['minimum_payment'] ?? 0);
$dueDate = trim((string) ($_POST['due_date'] ?? ''));

if ($creditor === '' || $total <= 0) {
    flash('error', 'Please provide a creditor name and total debt amount.');
    redirect_to('debts.php');
}

try {
    $stmt = db()->prepare('
        INSERT INTO debts (user_id, creditor_name, total_amount, current_balance, interest_rate, minimum_payment, due_date)
        VALUES (:user_id, :creditor, :total, :balance, :rate, :min_pay, :due)
    ');
    $stmt->execute([
        'user_id' => $user['id'],
        'creditor' => $creditor,
        'total' => $total,
        'balance' => $balance,
        'rate' => $rate,
        'min_pay' => $minPayment,
        'due' => $dueDate,
    ]);
    record_audit_event((int)$user['id'], 'debt.created', 'debt', (int)db()->lastInsertId(), ['creditor'=>$creditor,'total_amount'=>$total,'current_balance'=>$balance,'due_date'=>$dueDate ?: null]);
    if (function_exists('invalidate_financial_records_cache')) {
        invalidate_financial_records_cache((int) $user['id']);
    }
    flash('success', 'Debt obligation added to snowball planner.');
} catch (Throwable $e) {
    flash('error', 'Could not record debt: ' . $e->getMessage());
}

redirect_to('debts.php');
