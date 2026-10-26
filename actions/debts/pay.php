<?php
// Records a payment on a debt and lowers the balance that is still owed.
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

require_auth();
require_csrf();

$user = current_user();
$id = (int) ($_POST['id'] ?? 0);
$payment = (float) ($_POST['payment_amount'] ?? 0);

if ($id > 0 && $payment > 0) {
    try {
        $stmt = db()->prepare('UPDATE debts SET current_balance = CASE WHEN current_balance - :amount < 0 THEN 0 ELSE current_balance - :amount2 END WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['amount' => $payment, 'amount2' => $payment, 'id' => $id, 'user_id' => $user['id']]);
        if (function_exists('invalidate_financial_records_cache')) {
            invalidate_financial_records_cache((int) $user['id']);
        }
        flash('success', 'R ' . number_format($payment, 2) . ' debt payment recorded. Snowball balance decreased.');
    } catch (Throwable $e) {
        flash('error', 'Could not record debt payment.');
    }
}

redirect_to('debts.php');
