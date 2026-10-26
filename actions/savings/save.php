<?php
// Creates or updates a savings goal for the logged-in user.
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
$target = parse_money_input($_POST['target_amount'] ?? 0);
$current = parse_money_input($_POST['current_amount'] ?? 0);
$date = trim((string) ($_POST['target_date'] ?? ''));
$category = trim((string) ($_POST['category'] ?? 'Emergency Fund'));

if ($name === '' || $target <= 0) {
    flash('error', 'Please provide a valid goal name and target amount.');
    redirect_to('savings.php');
}

try {
    $stmt = db()->prepare('
        INSERT INTO savings_goals (user_id, name, target_amount, current_amount, target_date, category)
        VALUES (:user_id, :name, :target, :current, :date, :category)
    ');
    $stmt->execute([
        'user_id' => $user['id'],
        'name' => $name,
        'target' => $target,
        'current' => $current,
        'date' => $date ?: null,
        'category' => $category,
    ]);
    record_audit_event((int)$user['id'], 'savings_goal.created', 'savings_goal', (int)db()->lastInsertId(), ['name'=>$name,'target_amount'=>$target,'current_amount'=>$current,'target_date'=>$date ?: null]);
    if (function_exists('invalidate_financial_records_cache')) {
        invalidate_financial_records_cache((int) $user['id']);
    }
    flash('success', 'Savings goal established.');
} catch (Throwable $e) {
    flash('error', 'Could not create savings goal: ' . $e->getMessage());
}

redirect_to('savings.php');
