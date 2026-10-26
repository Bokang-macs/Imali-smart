<?php
// Adds one income or expense transaction for the logged-in user.
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_auth(); require_csrf(); $user = current_user();
$type = strtolower(trim((string)($_POST['transaction_type'] ?? 'expense'))); if (!in_array($type, ['income','expense','transfer'], true)) $type = 'expense';
$amount = parse_money_input($_POST['amount'] ?? 0); $description = trim((string)($_POST['description'] ?? '')); $date = trim((string)($_POST['transaction_date'] ?? date('Y-m-d'))); $method = trim((string)($_POST['payment_method'] ?? 'EFT')); $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null; $budgetId = !empty($_POST['budget_id']) ? (int)$_POST['budget_id'] : null; $reference = trim((string)($_POST['system_reference'] ?? $_POST['reference_code'] ?? '')); $redirect = trim((string)($_POST['redirect_to'] ?? 'transactions.php'));
if ($reference === '' || !str_starts_with($reference, 'TXN-')) $reference = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
if ($amount <= 0 || $amount > 999999999999.99 || $description === '') { flash('error', 'Please provide a clear description and a valid transaction amount greater than R0.00.'); redirect_to($redirect); }
$checkedDate = normalize_transaction_date($date);
if ($checkedDate === null) { flash('error', 'Please choose a real date that is not in the future.'); redirect_to($redirect); }
$date = $checkedDate;
try { $fingerprint = hash('sha256', implode('|', [$user['id'],$type,number_format($amount,2,'.',''),$date,$description])); $last = $_SESSION['_last_transaction'] ?? null; if (is_array($last) && ($last['hash'] ?? '') === $fingerprint && time() - (int)($last['at'] ?? 0) < 15) { flash('success','This transaction has already been recorded.'); redirect_to($redirect); }
$stmt = db()->prepare('INSERT INTO transactions (user_id, category_id, budget_id, transaction_type, amount, transaction_date, description, payment_method, reference_code) VALUES (:user_id,:category_id,:budget_id,:type,:amount,:date,:description,:method,:reference)');
$stmt->execute(['user_id'=>$user['id'],'category_id'=>$categoryId,'budget_id'=>$budgetId,'type'=>$type,'amount'=>$amount,'date'=>$date,'description'=>$description,'method'=>$method ?: 'EFT / Card','reference'=>$reference]); $_SESSION['_last_transaction']=['hash'=>$fingerprint,'at'=>time()]; if (function_exists('invalidate_financial_records_cache')) invalidate_financial_records_cache((int)$user['id']); flash('success', ucfirst($type) . ' of ' . money($amount) . ' successfully recorded.'); } catch (Throwable $e) { flash('error','Could not save transaction: ' . $e->getMessage()); } redirect_to($redirect);
