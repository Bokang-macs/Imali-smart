<?php
// Savings page. Lists savings goals, deposits, and congratulates the user when saving more than planned.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_auth();
$user = current_user();

$message = flash('savings_success') ?? flash('success');
$error = flash('savings_error') ?? flash('error');

// Handle Actions: Create Goal, Deposit to Goal, Delete Goal
if (request_method_is('POST')) {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_goal') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $target = parse_money_input($_POST['target_amount'] ?? 0);
        $current = parse_money_input($_POST['current_amount'] ?? 0);
        $date = trim((string) ($_POST['target_date'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? 'Emergency Cushion'));

        if ($name === '' || $target <= 0) {
            flash('savings_error', 'Please enter a goal name and a target amount greater than zero.');
        } elseif (!database_ready()) {
            flash('savings_error', 'Database connection unavailable.');
        } else {
            try {
                $stmt = db()->prepare("
                    INSERT INTO savings_goals (user_id, name, target_amount, current_amount, target_date, category, status)
                    VALUES (:user_id, :name, :target, :current, :date, :category, 'active')
                ");
                $stmt->execute([
                    'user_id' => $user['id'],
                    'name' => $name,
                    'target' => $target,
                    'current' => $current,
                    'date' => $date ?: null,
                    'category' => $category,
                ]);
                if (function_exists('invalidate_financial_records_cache')) {
                    invalidate_financial_records_cache((int) $user['id']);
                }

                if ($current > $target) {
                    $surplus = $current - $target;
                    flash('savings_success', " Halala! Congratulations! You exceeded your planned savings target for '{$name}' right from the start by " . money($surplus) . "!");
                } else {
                    flash('savings_success', "Savings goal '{$name}' created with target of " . money($target) . '.');
                }
            } catch (Throwable $e) {
                flash('savings_error', 'Could not create savings goal: ' . $e->getMessage());
            }
        }
        redirect_to('savings.php');
    }

    if ($action === 'deposit') {
        $id = (int) ($_POST['id'] ?? 0);
        $amount = parse_money_input($_POST['amount'] ?? 0);

        if ($id > 0 && $amount > 0 && database_ready()) {
            try {
                $gStmt = db()->prepare('SELECT name, target_amount, current_amount FROM savings_goals WHERE id = :id AND user_id = :user_id');
                $gStmt->execute(['id' => $id, 'user_id' => $user['id']]);
                $goalRow = $gStmt->fetch();

                if ($goalRow) {
                    $target = (float) $goalRow['target_amount'];
                    $oldAmt = (float) $goalRow['current_amount'];
                    $newAmt = $oldAmt + $amount;

                    $stmt = db()->prepare('
                        UPDATE savings_goals 
                        SET current_amount = :new_amt 
                        WHERE id = :id AND user_id = :user_id
                    ');
                    $stmt->execute(['new_amt' => $newAmt, 'id' => $id, 'user_id' => $user['id']]);
                    if (function_exists('invalidate_financial_records_cache')) {
                        invalidate_financial_records_cache((int) $user['id']);
                    }

                    // CONGRATULATE user if saved more than planned
                    if ($newAmt > $target) {
                        $surplus = $newAmt - $target;
                        $pct = round(($newAmt / $target) * 100);
                        flash('savings_success', " Halala! Congratulations, {$user['full_name']}! You have saved more than what you planned for '{$goalRow['name']}'! Target: " . money($target) . ", Saved: " . money($newAmt) . " ({$pct}% achieved · +" . money($surplus) . " surplus cushion)!");
                    } elseif ($newAmt == $target) {
                        flash('savings_success', " Congratulations! You have achieved 100% of your planned savings target for '{$goalRow['name']}' (" . money($newAmt) . ")!");
                    } else {
                        flash('savings_success', 'Successfully added ' . money($amount) . " to '{$goalRow['name']}'. " . money($target - $newAmt) . ' remaining to completion.');
                    }
                }
            } catch (Throwable $e) {
                flash('savings_error', 'Deposit could not be processed.');
            }
        }
        redirect_to('savings.php');
    }

    if ($action === 'delete_goal') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && database_ready()) {
            try {
                $stmt = db()->prepare('DELETE FROM savings_goals WHERE id = :id AND user_id = :user_id');
                $stmt->execute(['id' => $id, 'user_id' => $user['id']]);
                if (function_exists('invalidate_financial_records_cache')) {
                    invalidate_financial_records_cache((int) $user['id']);
                }
                flash('savings_success', 'Savings goal removed.');
            } catch (Throwable $e) {
                flash('savings_error', 'Could not delete savings goal.');
            }
        }
        redirect_to('savings.php');
    }
}

// Fetch personal savings goals.
$goals = []; $totalSaved = 0.0; $totalTarget = 0.0; $surpassedGoals = [];
if (database_ready()) { try { $stmt = db()->prepare('SELECT id, name, target_amount, current_amount, target_date, category, status FROM savings_goals WHERE user_id = :user_id ORDER BY current_amount DESC, id DESC'); $stmt->execute(['user_id' => $user['id']]); $goals = $stmt->fetchAll(); foreach ($goals as $g) { $c=(float)$g['current_amount']; $t=(float)$g['target_amount']; $totalSaved += $c; $totalTarget += $t; if ($c > $t) $surpassedGoals[]=$g; } } catch (Throwable $e) {} }
$overallPct = $totalTarget > 0 ? min(100, round(($totalSaved / $totalTarget) * 100)) : 0;

$page_title = 'Savings Goals';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">RESERVE BUILDING &amp; CELEBRATION</span>
        <h1>Savings Goals</h1>
        <p>Set aside money for rainy days, essential costs and the personal milestones that matter to you.</p>
    </div>
    <div class="page-heading__actions">
        <a class="btn btn--accent" href="#new-goal-form">+ Create Savings Goal</a>
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

<!-- PROMINENT CONGRATULATIONS BANNER WHEN USER SAVED MORE THAN PLANNED -->
<?php if (!empty($surpassedGoals)) : ?>
    <?php foreach ($surpassedGoals as $sg) : 
        $sgTarget = (float) $sg['target_amount'];
        $sgCurr = (float) $sg['current_amount'];
        $sgSurplus = $sgCurr - $sgTarget;
        $sgPct = round(($sgCurr / $sgTarget) * 100);
    ?>
    <div class="panel" style="background: linear-gradient(135deg, rgba(22, 163, 74, 0.12) 0%, rgba(232, 163, 58, 0.16) 100%); border: 2px solid #16a34a; border-radius: 16px; padding: 22px 24px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(22, 163, 74, 0.1);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 52px; height: 52px; border-radius: 50%; background: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 26px; color: #fff; flex-shrink: 0; box-shadow: 0 4px 10px rgba(22, 163, 74, 0.3);">
                    
                </div>
                <div>
                    <span class="badge" style="background: #15803d; color: #ffffff; font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 6px; letter-spacing: 0.05em;">
                        HALALA! SAVINGS TARGET SURPASSED
                    </span>
                    <h2 style="margin: 6px 0 4px 0; color: var(--ink); font-size: 1.35rem;">
                        Congratulations, <?= e($user['full_name'] ?? 'Saver') ?>! You saved more than what you planned!
                    </h2>
                    <p style="margin: 0; color: var(--ink); font-size: 14px; line-height: 1.55;">
                        On <strong><?= e($sg['name']) ?></strong>, your planned target was <strong><?= money($sgTarget) ?></strong>, but you have deposited an impressive <strong><?= money($sgCurr) ?></strong>.
                        That is <strong style="color: #15803d;"><?= $sgPct ?>% achieved</strong> — giving you a surplus wealth cushion of <strong style="color: #15803d;">+<?= money($sgSurplus) ?></strong>!
                    </p>
                </div>
            </div>
            <div style="text-align: right; background: rgba(255,255,255,0.7); padding: 12px 18px; border-radius: 12px; border: 1px solid rgba(22, 163, 74, 0.3);">
                <span style="font-size: 24px; font-weight: 800; color: #15803d; display: block;">+<?= money($sgSurplus) ?></span>
                <span style="font-size: 11px; color: var(--ink-soft); font-weight: 700; text-transform: uppercase;">Surplus Wealth Cushion</span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Stat Grid -->
<div class="stat-grid" style="margin-bottom: 24px;">
    <div class="stat-card stat-card--mint">
        <span class="stat-card__label">Total Stored Capital</span>
        <strong class="stat-card__value"><?= money($totalSaved) ?></strong>
        <span class="stat-card__note">Accumulated across <?= count($goals) ?> goal(s)</span>
    </div>
    <div class="stat-card stat-card--gold">
        <span class="stat-card__label">Total Target Ambition</span>
        <strong class="stat-card__value"><?= money($totalTarget) ?></strong>
        <span class="stat-card__note"><?= $overallPct ?>% of total milestone reached</span>
    </div>
    <div class="stat-card stat-card--blue">
        <span class="stat-card__label">Goals Exceeded</span>
        <strong class="stat-card__value" style="color: #15803d;"><?= count($surpassedGoals) ?> Goal(s)</strong>
        <span class="stat-card__note">Milestones beaten with bonus surplus</span>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Active Goals Cards -->
    <section class="panel">
        <div class="panel__heading">
            <div>
                <h2>Your Savings Milestones</h2>
                <p>Deposit funds, track progress, and celebrate when you beat your targets</p>
            </div>
        </div>

        <?php if (empty($goals)) : ?>
            <div class="empty-state">
                <div class="empty-state__mark">◉</div>
                <h3>No savings goals established yet</h3>
                <p>Start with a manageable 3-month emergency buffer or asset milestone.</p>
            </div>
        <?php else : ?>
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php foreach ($goals as $goal) : 
                    $target = (float) $goal['target_amount'];
                    $curr = (float) $goal['current_amount'];
                    $pct = $target > 0 ? round(($curr / $target) * 100) : 0;
                    $isSurpassed = $curr > $target;
                    $isComplete = $curr >= $target;
                    $surplus = $curr - $target;
                ?>
                    <div style="padding: 22px; background: var(--paper-deep); border: <?= $isSurpassed ? '2px solid #16a34a' : '1px solid var(--line)' ?>; border-radius: 14px; position: relative;">
                        
                        <?php if ($isSurpassed) : ?>
                            <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; background: #dcfce7; color: #15803d; font-size: 11px; font-weight: 800; margin-bottom: 10px;">
                                <span></span> TARGET SURPASSED (+<?= money($surplus) ?> SURPLUS · <?= $pct ?>%)
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <span class="badge" style="background: rgba(232, 163, 58, 0.15); color: var(--marigold-deep); font-size: 11px;">
                                        <?= e((string) ($goal['category'] ?? 'Emergency Cushion')) ?>
                                    </span>
                                </div>
                                <h3 style="font-size: 1.25rem; color: var(--ink); margin: 6px 0 2px 0;">
                                    <?= e((string) $goal['name']) ?>
                                </h3>
                                <small style="color: var(--ink-soft);">
                                    Target Date: <?= $goal['target_date'] ? e(safe_date((string) $goal['target_date'])) : 'Continuous / Open-ended' ?>
                                </small>
                            </div>
                            <div style="text-align: right;">
                                <strong style="font-size: 1.4rem; color: <?= $isSurpassed ? '#15803d' : ($isComplete ? '#16a34a' : 'var(--marigold-deep)') ?>;">
                                    <?= money($curr) ?>
                                </strong>
                                <div style="font-size: 12px; color: var(--ink-soft);">
                                    target <?= money($target) ?> (<?= $pct ?>%)
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar (Extends to 100% with gold/green gradient for surpassed goals) -->
                        <div style="height: 12px; background: var(--line); border-radius: 6px; overflow: hidden; margin: 12px 0;">
                            <div style="width: <?= min(100, $pct) ?>%; height: 100%; background: <?= $isSurpassed ? 'linear-gradient(90deg, #16a34a, #eab308)' : 'linear-gradient(90deg, #e8a33a, #22c55e)' ?>; border-radius: 6px; transition: width 0.4s ease;"></div>
                        </div>

                        <!-- Action Bar: Top-up & Delete -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; flex-wrap: wrap; gap: 10px;">
                            <span style="font-size: 12.5px; color: <?= $isSurpassed ? '#15803d' : ($isComplete ? '#16a34a' : 'var(--ink-soft)') ?>; font-weight: 700;">
                                <?php if ($isSurpassed) : ?>
                                     Congratulations! Target surpassed by <?= money($surplus) ?>!
                                <?php elseif ($isComplete) : ?>
                                    ✓ Target 100% Achieved! Congratulations!
                                <?php else : ?>
                                    <?= money($target - $curr) ?> remaining to goal
                                <?php endif; ?>
                            </span>

                            <div style="display: flex; gap: 8px; align-items: center;">
                                <!-- Quick Deposit Form -->
                                <form method="post" action="savings.php" style="display: flex; gap: 6px; align-items: center; margin: 0;">
                                    <input type="hidden" name="action" value="deposit">
                                    <input type="hidden" name="id" value="<?= (int) $goal['id'] ?>">
                                    <?= csrf_field() ?>
                                    <input type="number" name="amount" min="0.01" step="0.01" placeholder="+ R 500.00" style="width: 120px; padding: 6px 10px; font-size: 12px; border-radius: 6px; border: 1px solid var(--line);" required>
                                    <button type="submit" class="btn btn--primary btn--small" style="padding: 6px 12px; font-size: 12px;">+ Deposit</button>
                                </form>

                                <form method="post" action="savings.php" onsubmit="return confirm('Delete this savings goal?');" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_goal">
                                    <input type="hidden" name="id" value="<?= (int) $goal['id'] ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn--quiet btn--small" style="color: #f43f5e; padding: 6px 8px; font-size: 11px;" title="Delete goal">✕</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Create Goal Form -->
    <aside class="panel" id="new-goal-form">
        <div class="panel__heading">
            <div>
                <h2>Create Savings Target</h2>
                <p>Set a specific financial milestone</p>
            </div>
        </div>

        <form method="post" action="savings.php" data-validate class="form-grid">
            <input type="hidden" name="action" value="create_goal">
            <?= csrf_field() ?>

            <div class="form-field">
                <label for="name">Milestone Name</label>
                <input id="name" name="name" type="text" required placeholder="e.g. 3-Month Emergency Cushion, Tuition, Home Deposit" maxlength="120">
            </div>

            <div class="form-field">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="Emergency Cushion">Emergency Cushion</option>
                    <option value="Education &amp; Fees">Education &amp; University</option>
                    <option value="Holiday &amp; Family">Holiday &amp; Family Support</option>
                    <option value="House &amp; Property">House &amp; Construction</option>
                    <option value="Stokvel &amp; Holiday">Stokvel &amp; Club Savings</option>
                    <option value="General Savings">General Savings</option>
                </select>
            </div>

            <div class="auth-form__grid auth-form__grid--two">
                <div class="form-field">
                    <label for="target_amount">Target Target (ZAR)</label>
                    <input id="target_amount" name="target_amount" type="number" min="1" step="1" required placeholder="15000.00">
                </div>
                <div class="form-field">
                    <label for="current_amount">Current Saved</label>
                    <input id="current_amount" name="current_amount" type="number" min="0" step="0.01" value="0.00">
                </div>
            </div>

            <div class="form-field">
                <label for="target_date">Target Completion Date (Optional)</label>
                <input id="target_date" name="target_date" type="date">
            </div>

            <button class="btn btn--primary" type="submit" style="margin-top: 10px; width: 100%; justify-content: center;">
                Establish Savings Goal <span>+</span>
            </button>
        </form>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
