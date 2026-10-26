<?php
// Adds money to a savings goal for the logged-in user.
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

require_auth();
require_csrf();

$user = current_user();
$id = (int) ($_POST['id'] ?? 0);
$deposit = parse_money_input($_POST['amount'] ?? 0);

if ($id > 0 && $deposit > 0) {
    try {
        $gStmt = db()->prepare('SELECT name, target_amount, current_amount FROM savings_goals WHERE id = :id AND user_id = :user_id');
        $gStmt->execute(['id' => $id, 'user_id' => $user['id']]);
        $goalRow = $gStmt->fetch();

        if ($goalRow) {
            $target = (float) $goalRow['target_amount'];
            $oldAmt = (float) $goalRow['current_amount'];
            $newAmt = $oldAmt + $deposit;

            $stmt = db()->prepare('UPDATE savings_goals SET current_amount = :amount WHERE id = :id AND user_id = :user_id');
            $stmt->execute(['amount' => $newAmt, 'id' => $id, 'user_id' => $user['id']]);
            if (function_exists('invalidate_financial_records_cache')) {
                invalidate_financial_records_cache((int) $user['id']);
            }

            if ($newAmt > $target) {
                $surplus = $newAmt - $target;
                flash('success', " Halala! Congratulations! You exceeded your planned target for '{$goalRow['name']}' by " . money($surplus) . "! (Total saved: " . money($newAmt) . ")!");
            } else {
                flash('success', 'R ' . number_format($deposit, 2) . ' deposited into ' . $goalRow['name'] . '.');
            }
        }
    } catch (Throwable $e) {
        flash('error', 'Could not record deposit.');
    }
}

redirect_to('savings.php');
