<?php
// Debts page. Lists debts, payments and a simple debt snowball plan.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_auth();
$user = current_user();

$message = flash('debt_success') ?? flash('success');
$error = flash('debt_error') ?? flash('error');

// Handle Debt creation, payment, and deletion
if (request_method_is('POST')) {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_debt') {
        $creditor = trim((string) ($_POST['creditor_name'] ?? ''));
        $total = parse_money_input($_POST['total_amount'] ?? 0);
        $balance = isset($_POST['current_balance']) && $_POST['current_balance'] !== '' ? parse_money_input($_POST['current_balance']) : $total;
        $rate = (float) ($_POST['interest_rate'] ?? 0);
        $minPayment = parse_money_input($_POST['minimum_payment'] ?? 0);
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));

        if ($creditor === '' || $total <= 0) {
            flash('debt_error', 'Please provide a creditor name and positive total debt amount.');
        } elseif (!database_ready()) {
            flash('debt_error', 'Database connection unavailable.');
        } else {
            try {
                $stmt = db()->prepare('
                    INSERT INTO debts (user_id, creditor_name, total_amount, current_balance, interest_rate, minimum_payment, due_date, status)
                    VALUES (:user_id, :creditor, :total, :balance, :rate, :min_pay, :due, "active")
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
                if (function_exists('invalidate_financial_records_cache')) {
                    invalidate_financial_records_cache((int) $user['id']);
                }
                flash('debt_success', "Debt obligation '{$creditor}' added to Snowball Planner.");
            } catch (Throwable $e) {
                flash('debt_error', 'Could not record debt: ' . $e->getMessage());
            }
        }
        redirect_to('debts.php');
    }

    if ($action === 'pay_debt') {
        $id = (int) ($_POST['id'] ?? 0);
        $payment = parse_money_input($_POST['payment_amount'] ?? 0);

        if ($id > 0 && $payment > 0 && database_ready()) {
            try {
                $stmt = db()->prepare('
                    UPDATE debts 
                    SET current_balance = CASE WHEN current_balance - :amount < 0 THEN 0 ELSE current_balance - :amount2 END 
                    WHERE id = :id AND user_id = :user_id
                ');
                $stmt->execute(['amount' => $payment, 'amount2' => $payment, 'id' => $id, 'user_id' => $user['id']]);

                // Also record as expense transaction in ledger
                $tStmt = db()->prepare('
                    INSERT INTO transactions (user_id, transaction_type, amount, transaction_date, description, payment_method)
                    VALUES (:user_id, "expense", :amount, :date, :desc, "EFT")
                ');
                $tStmt->execute([
                    'user_id' => $user['id'],
                    'amount' => $payment,
                    'date' => date('Y-m-d'),
                    'desc' => 'Debt repayment payment',
                ]);

                if (function_exists('invalidate_financial_records_cache')) {
                    invalidate_financial_records_cache((int) $user['id']);
                }

                flash('debt_success', 'Recorded payment of ' . money($payment) . '. Debt balance reduced!');
            } catch (Throwable $e) {
                flash('debt_error', 'Could not record debt payment.');
            }
        }
        redirect_to('debts.php');
    }

    if ($action === 'delete_debt') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && database_ready()) {
            try {
                $stmt = db()->prepare('DELETE FROM debts WHERE id = :id AND user_id = :user_id');
                $stmt->execute(['id' => $id, 'user_id' => $user['id']]);
                if (function_exists('invalidate_financial_records_cache')) {
                    invalidate_financial_records_cache((int) $user['id']);
                }
                flash('debt_success', 'Debt obligation removed.');
            } catch (Throwable $e) {
                flash('debt_error', 'Could not delete debt.');
            }
        }
        redirect_to('debts.php');

    }
}

// Fetch Debts sorted by lowest balance first (Debt Snowball Method)
$debts = [];
$totalDebt = 0.0;
$totalMinMonthly = 0.0;
$totalInitial = 0.0;

if (database_ready()) {
    try {
        $stmt = db()->prepare('
            SELECT id, creditor_name, total_amount, current_balance, interest_rate, minimum_payment, due_date, status
            FROM debts
            WHERE user_id = :user_id
            ORDER BY current_balance ASC, interest_rate DESC
        ');
        $stmt->execute(['user_id' => $user['id']]);
        $debts = $stmt->fetchAll();

        foreach ($debts as $d) {
            $totalDebt += (float) $d['current_balance'];
            $totalMinMonthly += (float) $d['minimum_payment'];
            $totalInitial += (float) $d['total_amount'];
        }
    } catch (Throwable $e) {}
}

$paidOffAmount = max(0, $totalInitial - $totalDebt);
$overallPayoffPct = $totalInitial > 0 ? min(100, round(($paidOffAmount / $totalInitial) * 100)) : 0;

$page_title = 'Debt Payoff Planner';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">FREEDOM BLUEPRINT</span>
        <h1>Debt Payoff Plan</h1>
        <p>Knock out your smallest balances first for psychological momentum, then roll payments into larger debts.</p>
    </div>
    <div class="page-heading__actions">
        <a class="btn btn--accent" href="#new-debt-form">+ Add Debt Obligation</a>
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

<!-- Stat Grid -->
<div class="stat-grid" style="margin-bottom: 24px;">
    <div class="stat-card stat-card--rose">
        <span class="stat-card__label">Total Remaining Debt</span>
        <strong class="stat-card__value"><?= money($totalDebt) ?></strong>
        <span class="stat-card__note">Principal balance to extinguish</span>
    </div>
    <div class="stat-card stat-card--gold">
        <span class="stat-card__label">Monthly Minimum Requirement</span>
        <strong class="stat-card__value"><?= money($totalMinMonthly) ?></strong>
        <span class="stat-card__note">Total minimum installments</span>
    </div>
    <div class="stat-card stat-card--mint">
        <span class="stat-card__label">Capital Already Paid Off</span>
        <strong class="stat-card__value"><?= money($paidOffAmount) ?></strong>
        <span class="stat-card__note"><?= $overallPayoffPct ?>% of initial debt eliminated</span>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Active Debts Ordered by Snowball Priority -->
    <section class="panel">
        <div class="panel__heading">
            <div>
                <h2>Snowball Priority Queue</h2>
                <p>Focus aggressive extra payments on Target #1 while maintaining minimums on others</p>
            </div>
        </div>

        <?php if (empty($debts)) : ?>
            <div class="empty-state">
                <div class="empty-state__mark">◓</div>
                <h3>No debts currently recorded</h3>
                <p>Congratulations, or add your store accounts, overdrafts, and credit cards to formulate a payoff timeline.</p>
            </div>
        <?php else : ?>
            <div style="display: flex; flex-direction: column; gap: 18px;">
                <?php 
                $rank = 1;
                foreach ($debts as $debt) : 
                    $orig = (float) $debt['total_amount'];
                    $curr = (float) $debt['current_balance'];
                    $paid = max(0, $orig - $curr);
                    $pct = $orig > 0 ? min(100, round(($paid / $orig) * 100)) : 100;
                    $isZero = $curr <= 0;
                ?>
                    <div style="padding: 18px; background: var(--paper-deep); border: 1px solid <?= $rank === 1 && !$isZero ? 'var(--marigold)' : 'var(--line)' ?>; border-radius: 12px; position: relative;">
                        <?php if ($rank === 1 && !$isZero) : ?>
                            <div style="position: absolute; top: -10px; right: 20px; background: #e8a33a; color: #18221f; font-weight: 800; font-size: 10px; padding: 2px 10px; border-radius: 99px; text-transform: uppercase;">
                                ★ Top Priority Payoff Target
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="badge" style="background: var(--panel); border: 1px solid var(--line); color: var(--ink); font-size: 10px;">
                                        #<?= $rank++ ?> in Queue
                                    </span>
                                    <strong style="font-size: 1.2rem; color: var(--ink);"><?= e((string) $debt['creditor_name']) ?></strong>
                                </div>
                                <small style="color: var(--ink-soft); display: block; margin-top: 4px;">
                                    Rate: <?= (float) $debt['interest_rate'] ?>% APR | Min Due: <?= money((float) $debt['minimum_payment']) ?> (<?= e((string) $debt['due_date']) ?>)
                                </small>
                            </div>
                            <div style="text-align: right;">
                                <strong style="font-size: 1.3rem; color: <?= $isZero ? '#22c55e' : '#f43f5e' ?>;">
                                    <?= money($curr) ?>
                                </strong>
                                <div style="font-size: 11px; color: var(--ink-soft);">
                                    of <?= money($orig) ?> original
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div style="height: 10px; background: rgba(255,255,255,0.08); border-radius: 5px; overflow: hidden; margin: 10px 0;">
                            <div style="width: <?= $pct ?>%; height: 100%; background: #22c55e; border-radius: 5px; transition: width 0.3s ease;"></div>
                        </div>

                        <!-- Pay Form & Delete -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; flex-wrap: wrap; gap: 8px;">
                            <span style="font-size: 12px; color: <?= $isZero ? '#22c55e' : '#94a3b8' ?>;">
                                <?= $isZero ? '✓ Completely Paid Off!' : money($paid) . ' paid off (' . $pct . '%)' ?>
                            </span>

                            <div style="display: flex; gap: 8px; align-items: center;">
                                <?php if (!$isZero) : ?>
                                    <form method="post" action="debts.php" style="display: flex; gap: 6px; align-items: center; margin: 0;">
                                        <input type="hidden" name="action" value="pay_debt">
                                        <input type="hidden" name="id" value="<?= (int) $debt['id'] ?>">
                                        <?= csrf_field() ?>
                                        <input type="number" name="payment_amount" min="0.01" step="0.01" placeholder="R <?= (float) $debt['minimum_payment'] ?: '250.00' ?>" style="width: 115px; padding: 6px 10px; font-size: 12px; border-radius: 6px;" required>
                                        <button type="submit" class="btn btn--primary btn--small" style="padding: 6px 12px; font-size: 12px;">Record Pay</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="debts.php" onsubmit="return confirm('Remove this debt?');" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_debt">
                                    <input type="hidden" name="id" value="<?= (int) $debt['id'] ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn--quiet btn--small" style="color: #f43f5e; padding: 6px 8px; font-size: 11px;">✕</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Create Debt Form -->
    <aside class="panel" id="new-debt-form">
        <div class="panel__heading">
            <div>
                <h2>Add Debt Obligation</h2>
                <p>Enter balance and creditor terms</p>
            </div>
        </div>

        <form method="post" action="debts.php" data-validate class="form-grid">
            <input type="hidden" name="action" value="create_debt">
            <?= csrf_field() ?>

            <div class="form-field">
                <label for="creditor_name">Creditor / Account Name</label>
                <input id="creditor_name" name="creditor_name" type="text" required placeholder="e.g. Retail Store Card, Capitec Microloan, Bank Overdraft" maxlength="120">
            </div>

            <div class="auth-form__grid auth-form__grid--two">
                <div class="form-field">
                    <label for="total_amount">Original Balance (ZAR)</label>
                    <input id="total_amount" name="total_amount" type="number" min="0.01" step="0.01" required placeholder="e.g. 5000.00 or 1500000.00">
                </div>
                <div class="form-field">
                    <label for="current_balance">Current Remaining (ZAR)</label>
                    <input id="current_balance" name="current_balance" type="number" min="0" step="0.01" required placeholder="e.g. 2400.00 or 1200000.00">
                </div>
            </div>

            <div class="auth-form__grid auth-form__grid--two">
                <div class="form-field">
                    <label for="interest_rate">Interest Rate (% APR)</label>
                    <input id="interest_rate" name="interest_rate" type="number" min="0" max="100" step="0.01" placeholder="18.5">
                </div>
                <div class="form-field">
                    <label for="minimum_payment">Min Monthly Due (ZAR)</label>
                    <input id="minimum_payment" name="minimum_payment" type="number" min="0" step="0.01" placeholder="e.g. 350.00 or 25000.00">
                </div>
            </div>

            <div class="form-field">
                <label for="due_date">Due Day / Date</label>
                <input id="due_date" name="due_date" type="text" placeholder="e.g. 25th of month or 2026-11-01">
            </div>

            <button class="btn btn--primary" type="submit" style="margin-top: 10px;">
                Add to Snowball <span>+</span>
            </button>
        </form>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>