<?php
// Deletes one budget that belongs to the logged-in user, then returns to the budgets page.
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

require_auth();
require_csrf();

$user = current_user();
$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = db()->prepare('DELETE FROM budgets WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $user['id']]);
        if (function_exists('invalidate_financial_records_cache')) {
            invalidate_financial_records_cache((int) $user['id']);
        }
        flash('success', 'Budget deleted.');
    } catch (Throwable $e) {
        flash('error', 'Could not delete budget.');
    }
}

redirect_to('budgets.php');
