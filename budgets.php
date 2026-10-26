<?php
// Budgets page. Shows each budget and how much has been spent against it.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_auth();
$user = current_user();

$message = flash('budget_success') ?? flash('success');
$error = flash('budget_error') ?? flash('error');

// Handle Budget Creation & Deletion
if (request_method_is('POST')) {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_budget') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $amount = parse_money_input($_POST['amount'] ?? 0);
        $start = trim((string) ($_POST['period_start'] ?? date('Y-m-01')));
        $end = trim((string) ($_POST['period_end'] ?? date('Y-m-t')));

        if ($name === '' || $amount <= 0) {
            flash('budget_error', 'Please provide a valid budget name and positive target amount.');
        } elseif (!database_ready()) {
            flash('budget_error', 'Database connection unavailable.');
        } else {
            try {
                $stmt = db()->prepare('
                    INSERT INTO budgets (user_id, name, amount, period_start, period_end)
                    VALUES (:user_id, :name, :amount, :start, :end)
                ');
                $stmt->execute([
                    'user_id' => $user['id'],
                    'name' => $name,
                    'amount' => $amount,
                    'start' => $start,
                    'end' => $end,
                ]);
                if (function_exists('invalidate_financial_records_cache')) {
                    invalidate_financial_records_cache((int) $user['id']);
                }
                flash('budget_success', "Budget '{$name}' created with allocation of " . money($amount) . '.');
            } catch (Throwable $e) {
                flash('budget_error', 'Failed to save budget: ' . $e->getMessage());
            }
        }
        redirect_to('budgets.php');
    }

    if ($action === 'delete_budget') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && database_ready()) {
            try {
                $stmt = db()->prepare('DELETE FROM budgets WHERE id = :id AND user_id = :user_id');
                $stmt->execute(['id' => $id, 'user_id' => $user['id']]);
                if (function_exists('invalidate_financial_records_cache')) {
                    invalidate_financial_records_cache((int) $user['id']);
                }
                flash('budget_success', 'Budget successfully removed.');
            } catch (Throwable $e) {
                flash('budget_error', 'Failed to delete budget.');
            }
        }
        redirect_to('budgets.php');
    }
}

// Get all budgets and the amount spent on each, using one query
$budgets = [];
$totalBudgeted = 0.0;
$totalSpent = 0.0;

if (database_ready()) {
    try {
        $stmt = db()->prepare('
            SELECT id, name, amount, period_start, period_end
            FROM budgets
            WHERE user_id = :user_id
            ORDER BY period_end DESC, id DESC
        ');
        $stmt->execute(['user_id' => $user['id']]);
        $rawBudgets = $stmt->fetchAll();

        // Optimized batch retrieval of user expenses to avoid N+1 query loops
        $txStmt = db()->prepare("
            SELECT amount, transaction_date
            FROM transactions
            WHERE user_id = :user_id AND transaction_type = 'expense' AND deleted_at IS NULL
        ");
        $txStmt->execute(['user_id' => $user['id']]);
        $userExpenses = $txStmt->fetchAll();

        foreach ($rawBudgets as $b) {
            $bAmount = (float) $b['amount'];
            $totalBudgeted += $bAmount;
            $start = $b['period_start'];
            $end = $b['period_end'];

            // Compute actual spent within envelope date range in-memory
            $spent = 0.0;
            foreach ($userExpenses as $tx) {
                $txDate = (string) $tx['transaction_date'];
                if ($txDate >= $start && $txDate <= $end) {
                    $spent += (float) $tx['amount'];
                }
            }

            $totalSpent += $spent;
            $pct = $bAmount > 0 ? min(150, round(($spent / $bAmount) * 100)) : 0;
            $remaining = max(0.0, $bAmount - $spent);

            $budgets[] = array_merge($b, [
                'spent' => $spent,
                'pct' => $pct,
                'remaining' => $remaining,
                'is_over' => $spent > $bAmount,
            ]);
        }
    } catch (Throwable $e) {}
}


$page_title = 'Budgets & Expense Allocation';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">CAPITAL DISCIPLINE</span>
        <h1>Allocated Budgets &amp; Limits</h1>
        <p>Give every Rand a clear mission before the month starts. Monitor live outflows against targets.</p>
    </div>
    <div class="page-heading__actions">
        <a class="btn btn--accent" href="#new-budget-modal">+ Add New Budget</a>
    </div>
</div>

<?php if ($message) : ?>
    <div class="alert alert--success" data-auto-dismiss>
        <?= e($message) ?>
    </div>
<?php endif; ?>

<?php if ($error) : ?>
    <div class="alert alert--error" data-auto-dismiss>
        <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Stat Summary Grid -->
<div class="stat-grid" style="margin-bottom: 24px;">
    <div class="stat-card stat-card--gold">
        <span class="stat-card__label">Total Budgeted</span>
        <strong class="stat-card__value"><?= money($totalBudgeted) ?></strong>
        <span class="stat-card__note">Across <?= count($budgets) ?> active envelope(s)</span>
    </div>
    <div class="stat-card stat-card--rose">
        <span class="stat-card__label">Total Actual Outflow</span>
        <strong class="stat-card__value"><?= money($totalSpent) ?></strong>
        <span class="stat-card__note"><?= $totalBudgeted > 0 ? round(($totalSpent / $totalBudgeted) * 100, 1) . '% overall utilization' : 'No allocations' ?></span>
    </div>
    <div class="stat-card stat-card--mint">
        <span class="stat-card__label">Available Buffer Room</span>
        <strong class="stat-card__value"><?= money(max(0, $totalBudgeted - $totalSpent)) ?></strong>
        <span class="stat-card__note">Unspent planned capital</span>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Active Budgets List -->
    <section class="panel">
        <div class="panel__heading">
            <div>
                <h2>Your Budget Envelopes</h2>
                <p>Track spent vs cap in real time</p>
            </div>
        </div>

        <?php if (empty($budgets)) : ?>
            <div class="empty-state">
                <div class="empty-state__mark">◒</div>
                <h3>No budgets established yet</h3>
                <p>Creating your first budget takes 30 seconds. Choose an essential category like groceries or fuel.</p>
            </div>
        <?php else : ?>
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php foreach ($budgets as $b) : 
                    $barColor = $b['is_over'] ? '#ef4444' : ($b['pct'] >= 85 ? '#eab308' : '#22c55e');
                ?>
                    <div style="padding: 18px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <strong style="font-size: 1.15rem; color: var(--ink);"><?= e((string) $b['name']) ?></strong>
                                </div>
                                <small style="color: var(--ink-soft); margin-top: 4px; display: block;">
                                    <?= e(safe_date((string) $b['period_start'])) ?> — <?= e(safe_date((string) $b['period_end'])) ?>
                                </small>
                            </div>
                            <div style="text-align: right;">
                                <strong style="font-size: 1.2rem; color: <?= $barColor ?>;">
                                    <?= money($b['spent']) ?>
                                </strong>
                                <span style="font-size: 12px; color: var(--ink-soft);">of <?= money($b['amount']) ?></span>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div style="height: 10px; background: var(--line); border-radius: 5px; overflow: hidden; margin: 12px 0;">
                            <div style="width: <?= min(100, $b['pct']) ?>%; height: 100%; background: <?= $barColor ?>; border-radius: 5px; transition: width 0.3s ease;"></div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
                            <span style="color: <?= $b['is_over'] ? '#ef4444' : '#94a3b8' ?>; font-weight: <?= $b['is_over'] ? '700' : 'normal' ?>;">
                                <?= $b['is_over'] ? 'Over budget by ' . money($b['spent'] - $b['amount']) : money($b['remaining']) . ' left to spend (' . (100 - $b['pct']) . '%)' ?>
                            </span>

                            <form method="post" action="budgets.php" onsubmit="return confirm('Remove this budget envelope?');" style="margin: 0;">
                                <input type="hidden" name="action" value="delete_budget">
                                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--quiet btn--small" style="color: #f43f5e; padding: 4px 8px; font-size: 11px;">Remove</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Create Budget Form -->
    <aside class="panel" id="new-budget-modal">
        <div class="panel__heading">
            <div>
                <h2>Create Budget Allocation</h2>
                <p>Define spending ceiling for category or period</p>
            </div>
        </div>

        <form method="post" action="budgets.php" data-validate class="form-grid">
            <input type="hidden" name="action" value="create_budget">
            <?= csrf_field() ?>

            <div class="form-field">
                <label for="name">Budget Name</label>
                <input id="name" name="name" type="text" required placeholder="e.g. Groceries, Taxi Fare, Electricity" maxlength="120">
            </div>

            <div class="form-field">
                <label for="amount">Budget Limit (ZAR)</label>
                <input id="amount" name="amount" type="number" step="0.01" min="0.01" required placeholder="e.g. 2500.00 or 1500000.00">
            </div>

            <div class="auth-form__grid auth-form__grid--two">
                <div class="form-field">
                    <label for="period_start">Start Date</label>
                    <input id="period_start" name="period_start" type="date" value="<?= date('Y-m-01') ?>" required>
                </div>
                <div class="form-field">
                    <label for="period_end">End Date</label>
                    <input id="period_end" name="period_end" type="date" value="<?= date('Y-m-t') ?>" required>
                </div>
            </div>
            <button class="btn btn--primary" type="submit" style="margin-top: 10px;">
                Save Budget Envelope <span>+</span>
            </button>
        </form>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
