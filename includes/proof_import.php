<?php
/**
 * Automatic import of a successfully scanned proof of payment / bank statement.
 *
 * Called by api/proofs/upload.php straight after Gemini confirms the document is valid.
 * Every readable row is saved to `transactions` (the Transactions page), duplicates are skipped,
 * and the import is recorded on the `payment_proofs` row (status, linked transaction, summary).
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * A proof of payment says who paid whom. When exactly one of payer / recipient matches the account holder's name,
 * that decides income (we received) or expense (we paid), overriding a doubtful AI guess. Statements with many rows
 * keep their own per-row credit/debit types.
 */
function proof_apply_direction(array $fields, string $holderName): array
{
    $tokens = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($holderName))) ?: [], static fn($t) => mb_strlen($t) >= 3));
    if (!$tokens) return $fields;
    $matches = static function (mixed $name) use ($tokens): bool {
        $n = mb_strtolower(trim((string)$name));
        if ($n === '') return false;
        foreach ($tokens as $t) if (str_contains($n, $t)) return true;
        return false;
    };
    $payer = $matches($fields['payer_name'] ?? ''); $recipient = $matches($fields['recipient_name'] ?? '');
    if ($payer === $recipient) return $fields; // unknown or both: keep the AI's classification
    $type = $recipient ? 'income' : 'expense';
    $rows = is_array($fields['transactions'] ?? null) ? $fields['transactions'] : [];
    if (count($rows) <= 1) {
        $fields['transaction_type'] = $type;
        if ($rows && is_array($rows[0])) { $rows[0]['transaction_type'] = $type; $fields['transactions'] = $rows; }
    }
    return $fields;
}

/** Turn a label such as "credit", "Money out", "DR" or "payment received" into income / expense, or null when it says neither. */
function proof_type_from_label(mixed $raw): ?string
{
    $t = strtolower(trim((string)$raw));
    if ($t === '') return null;
    if (in_array($t, ['income', 'credit', 'cr', 'deposit', 'received', 'money in', 'in', 'inflow', 'payment received'], true)) return 'income';
    if (in_array($t, ['expense', 'debit', 'dr', 'withdrawal', 'paid', 'payment', 'money out', 'out', 'outflow', 'purchase', 'fee'], true)) return 'expense';
    if (preg_match('/\b(income|credit|cr|deposit|received|inflow|money in|refund)\b/', $t)) return 'income';
    if (preg_match('/\b(expense|debit|dr|withdraw\w*|paid|payment|outflow|money out|purchase|fee|charge)\b/', $t)) return 'expense';
    return null;
}

/** Last-resort guess from the wording of a statement line, used only when the document gave no credit/debit signal. */
function proof_type_from_description(string $description): ?string
{
    $d = ' ' . strtolower($description) . ' ';
    if (preg_match('/\b(salary|payroll|wages?|payslip|deposit|refund|reversal|cashback|interest received|credit interest|payment received|received from|sassa|grant|stipend|eft credit|cr)\b/', $d)) return 'income';
    if (preg_match('/\b(purchase|pos|withdrawal|atm|fee|fees|charge|debit order|payment to|paid to|airtime|prepaid|transfer to|dr|monthly account fee|service fee)\b/', $d)) return 'expense';
    return null;
}

/**
 * Work out income or expense for one statement row. Evidence is used strongest first:
 * the credit/debit direction printed on the document, a minus sign / brackets / CR / DR beside the amount,
 * the AI's own income/expense label, the wording of the line, then the document-level type.
 * Anything still unknown is treated as money paid out (a proof of payment is usually the account holder paying).
 */
function proof_normalise_type(mixed $raw, mixed $amount = null, string $description = '', mixed $direction = null, ?string $documentType = null): ?string
{
    if (($fromDirection = proof_type_from_label($direction)) !== null) return $fromDirection;

    if (is_string($amount)) {
        if (preg_match('/\b(cr|credit)\.?\s*$/i', trim($amount))) return 'income';
        if (preg_match('/\b(dr|debit)\.?\s*$/i', trim($amount)) || preg_match('/^\s*(\(|-|\x{2212}|\x{2013})/u', $amount)) return 'expense';
    } elseif (is_numeric($amount) && (float)$amount < 0) {
        return 'expense';
    }

    if (($fromLabel = proof_type_from_label($raw)) !== null) return $fromLabel;
    if (($fromWords = proof_type_from_description($description)) !== null) return $fromWords;
    return proof_type_from_label($documentType);
}

/**
 * "Today" and "Yesterday" printed on a receipt mean the day it was made. The scanner is asked to hand back the date
 * exactly as printed (date_text), so that word can win over a year the document or the AI got wrong.
 */
function proof_relative_date(mixed $raw): ?string
{
    $d = (string)$raw;
    if (preg_match('/\byesterday\b/i', $d)) return date('Y-m-d', strtotime('-1 day'));
    if (preg_match('/\btoday\b/i', $d)) return date('Y-m-d');
    return null;
}

/** Month number for "Oct", "october", "Sept" ... or null. Only real month names count, so "Decimal" or "Monday" never match. */
function proof_month_number(string $word): ?int
{
    $w = strtolower(rtrim($word, '.'));
    if (strlen($w) < 3) return null;
    foreach (['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'] as $i => $full) {
        if (str_starts_with($full, $w)) return $i + 1;
    }
    return null;
}

/** A real calendar date as YYYY-MM-DD, or null. 31 Feb is rejected instead of rolling into March. */
function proof_ymd(int $year, int $month, int $day): ?string
{
    return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
}

/**
 * Accept 2026-10-25, 25/10/2026, 25 Oct 2026, 25-10-26, Oct 25 2026, 20261025 ... and return a real YYYY-MM-DD or null.
 * Numeric dates are read day-first (South African order). A day and month with no year take the most recent such date.
 * A date more than a day in the future cannot be a payment that already happened, so it is rejected (null) rather
 * than stored: the caller then falls back to the document's own date.
 */
function proof_normalise_date(mixed $raw): ?string
{
    $d = trim((string)$raw);
    if ($d === '') return null;
    if (($relative = proof_relative_date($d)) !== null) return $relative;

    $date = null;
    if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $d, $m)) {
        $date = proof_ymd((int)$m[1], (int)$m[2], (int)$m[3]);
    } elseif (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $d, $m)) {
        $date = proof_ymd((int)$m[1], (int)$m[2], (int)$m[3]);
    } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})/', $d, $m)) { // South African day-first order
        $y = (int)$m[3]; if ($y < 100) $y += 2000;
        $date = proof_ymd($y, (int)$m[2], (int)$m[1]);
    } elseif (preg_match('/(\d{1,2})(?:st|nd|rd|th)?\s+([A-Za-z]{3,9})\.?,?\s+(\d{4})/', $d, $m)) { // "26 Oct 2025 10:45 AM"
        $mon = proof_month_number($m[2]);
        $date = $mon ? proof_ymd((int)$m[3], $mon, (int)$m[1]) : null;
    } elseif (preg_match('/([A-Za-z]{3,9})\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})/', $d, $m)) { // "Oct 26, 2025"
        $mon = proof_month_number($m[1]);
        $date = $mon ? proof_ymd((int)$m[3], $mon, (int)$m[2]) : null;
    } elseif (preg_match('/^(\d{1,2})(?:st|nd|rd|th)?\s+([A-Za-z]{3,9})\.?$/', $d, $m) || preg_match('/^([A-Za-z]{3,9})\.?\s+(\d{1,2})(?:st|nd|rd|th)?$/', $d, $m2)) {
        // No year printed ("26 Oct"): use the latest year in which that date is not in the future.
        [$dayPart, $monPart] = isset($m2) && $m2 ? [$m2[2], $m2[1]] : [$m[1], $m[2]];
        $mon = proof_month_number($monPart);
        if ($mon) {
            $year = (int)date('Y');
            $date = proof_ymd($year, $mon, (int)$dayPart);
            if ($date !== null && $date > date('Y-m-d', strtotime('+1 day'))) $date = proof_ymd($year - 1, $mon, (int)$dayPart);
        }
    }

    if ($date === null) return null;
    return $date > date('Y-m-d', strtotime('+1 day')) ? null : $date;
}

/** Parse amounts like "R 12 500,00", "12,500.00", "1 250.5" or "-450.20" (South African statements often use a decimal comma). */
function proof_parse_amount(mixed $raw): float
{
    if (is_int($raw) || is_float($raw)) return (float)$raw;
    $v = preg_replace('/[^\d,.\-()]/u', '', str_replace(["\xC2\xA0", ' '], '', (string)$raw));
    if ($v === '' || $v === null) return 0.0;
    if (preg_match('/,\d{1,2}$/', $v)) $v = str_replace(',', '.', str_replace('.', '', $v)); // decimal comma
    else $v = str_replace(',', '', $v);
    return (float)preg_replace('/[^\d.\-]/', '', $v);
}

/** Turn the scan result into clean, importable rows. */
function proof_build_rows(array $fields): array
{
    $source = is_array($fields['transactions'] ?? null) ? $fields['transactions'] : [];
    $rows = [];
    foreach ($source as $row) {
        if (is_array($row)) $rows[] = $row;
    }
    // The date printed on a single proof of payment: used for any row that has no readable date of its own.
    $documentDate = proof_relative_date($fields['payment_date_text'] ?? '') ?? proof_normalise_date($fields['payment_date'] ?? '');
    // A single proof of payment often has no row list: build one row from the top-level fields.
    if (!$rows && !empty($fields['amount'])) {
        $rows[] = [
            'transaction_type' => $fields['transaction_type'] ?? '',
            'amount' => $fields['amount'],
            'transaction_date' => $fields['payment_date'] ?? '',
            'date_text' => $fields['payment_date_text'] ?? '',
            'description' => ($fields['description'] ?? '') ?: trim('Payment to ' . (string)($fields['recipient_name'] ?? 'recipient')),
            'reference_code' => $fields['reference'] ?? '',
            'payment_method' => $fields['bank_or_provider'] ?? 'Proof of payment',
            'category_hint' => '',
        ];
    }

    $clean = [];
    foreach ($rows as $row) {
        $description = trim((string)($row['description'] ?? ''));
        $rawAmount = $row['amount'] ?? 0;
        $docType = count($rows) === 1 ? (string)($fields['transaction_type'] ?? '') : null;
        $type = proof_normalise_type($row['transaction_type'] ?? '', $rawAmount, $description, $row['direction'] ?? ($row['credit_or_debit'] ?? null), $docType);
        if ($type === null) {
            // A proof of payment is money the account holder sent out unless the document says otherwise.
            $type = 'expense';
        }
        $amount = round(abs(proof_parse_amount($rawAmount)), 2);
        // Date order of trust: "Today"/"Yesterday" as printed, then the row's own date, then the document's date.
        // Only when the document gives no usable date at all is today used, and the row is flagged so the person is told.
        $date = proof_relative_date($row['date_text'] ?? '') ?? proof_normalise_date($row['transaction_date'] ?? ($row['date'] ?? ''));
        $estimated = false;
        if ($date === null) $date = $documentDate;
        if ($date === null) { $date = date('Y-m-d'); $estimated = true; } // the amount is real money: keep it rather than lose it
        if ($amount <= 0 || $amount > 999999999999.99) continue;
        $clean[] = [
            'type' => $type,
            'amount' => $amount,
            'date' => $date,
            'date_estimated' => $estimated,
            'description' => mb_substr($description !== '' ? $description : 'Imported statement transaction', 0, 255),
            'reference' => mb_substr(trim((string)($row['reference_code'] ?? '')), 0, 120),
            'method' => mb_substr(trim((string)($row['payment_method'] ?? '')) ?: 'Statement import', 0, 60),
            'category_hint' => trim((string)($row['category_hint'] ?? '')),
        ];
    }
    return $clean;
}

/** Links that take the user straight to the recorded transactions, with a period that covers their dates. */
function proof_result_links(array $rows): array
{
    $ids = []; $dates = [];
    foreach ($rows as $r) {
        if (!empty($r['id'])) { $ids[] = (int)$r['id']; $dates[] = $r['date']; }
    }
    if (!$ids) return [];
    sort($dates);
    $from = date('Y-m-01', strtotime($dates[0]));
    $to = date('Y-m-t', strtotime($dates[count($dates) - 1]));
    if ($to > date('Y-m-d')) $to = date('Y-m-d');
    $period = 'from=' . $from . '&to=' . $to;
    $types = array_unique(array_map(static fn($r) => $r['type'], array_filter($rows, static fn($r) => !empty($r['id']))));
    $type = count($types) === 1 ? '&type=' . $types[0] : '';
    $byType = [];
    foreach ($rows as $r) if (!empty($r['id'])) $byType[$r['type']][] = (int)$r['id'];
    $split = [];
    if (count($byType) > 1) { // a statement with both money in and money out: one link for each side
        foreach (['income', 'expense'] as $t) {
            if (!empty($byType[$t])) $split[$t . '_view'] = 'transactions.php?highlight=' . implode(',', array_slice(array_unique($byType[$t]), 0, 50)) . '&type=' . $t . '&' . $period;
        }
    }
    return $split + [
        'transaction' => 'transactions.php?highlight=' . implode(',', array_slice(array_unique($ids), 0, 50)) . $type . '&' . $period,
        'dashboard' => 'dashboard.php?' . $period,
        'cashflow' => 'cashflow.php?' . $period,
        'analytics' => 'analytics.php?' . $period,
        'reports' => 'reports.php?' . $period,
        'period_from' => $from, 'period_to' => $to,
    ];
}

/**
 * One sentence about dates the person should double-check, or null when everything is clear:
 * rows whose date could not be read (today was used) and documents dated long before today.
 */
function proof_date_notice(array $rows): ?string
{
    $estimated = 0; $old = [];
    $cutoff = date('Y-m-d', strtotime('-60 days'));
    foreach ($rows as $r) {
        if (empty($r['id'])) continue;
        if (!empty($r['date_estimated'])) $estimated++;
        elseif (($r['date'] ?? '') !== '' && $r['date'] < $cutoff) $old[$r['date']] = true;
    }
    $notes = [];
    if ($estimated) $notes[] = $estimated === 1 ? 'One record had no readable date on the document, so today\'s date was used.' : $estimated . ' records had no readable date on the document, so today\'s date was used.';
    if ($old) {
        $dates = array_keys($old); sort($dates);
        $notes[] = 'The document is dated ' . date('j M Y', strtotime($dates[0])) . (count($dates) > 1 ? ' to ' . date('j M Y', strtotime($dates[count($dates) - 1])) : '') . ', more than 60 days ago.';
    }
    return $notes ? implode(' ', $notes) . ' If that is not right, correct the date below.' : null;
}

/** Count and total the imported rows by income / expense for the "what was recorded" alert. */
function proof_import_summary(array $rows): array
{
    $sum = ['income_count' => 0, 'income_total' => 0.0, 'expense_count' => 0, 'expense_total' => 0.0];
    foreach ($rows as $r) {
        if (($r['status'] ?? '') !== 'imported') continue;
        $k = $r['type'] === 'income' ? 'income' : 'expense';
        $sum[$k . '_count']++;
        $sum[$k . '_total'] = round($sum[$k . '_total'] + (float)$r['amount'], 2);
    }
    return $sum;
}

/**
 * Choose the category for an imported row so income lands under an income category and
 * expenses under an expense category: the AI's hint first, then keywords in the description,
 * then "Other income" / "Other expense". Missing system categories are created.
 */
function proof_resolve_category(PDO $pdo, int $userId, string $type, string $hint, string $description): int
{
    static $cache = [];
    $find = function (string $name) use ($pdo, $userId, $type, &$cache): ?int {
        $key = $userId . '|' . $type . '|' . mb_strtolower($name);
        if (array_key_exists($key, $cache)) return $cache[$key];
        $q = $pdo->prepare("SELECT id FROM expense_categories WHERE (user_id = :u OR user_id IS NULL) AND category_type IN (:type, 'both') AND LOWER(name) = LOWER(:name) ORDER BY (user_id IS NULL) ASC, id ASC LIMIT 1");
        $q->execute(['u' => $userId, 'type' => $type, 'name' => $name]);
        $id = $q->fetchColumn();
        return $cache[$key] = ($id ? (int)$id : null);
    };

    if ($hint !== '' && ($id = $find($hint)) !== null) return $id;

    // Most specific rules first. Keywords match whole words only, so "spar" no longer matches "spark" and "fees" no longer
    // files a bank fee under Education. A rule's category is created on demand if the database does not have it yet.
    $rules = $type === 'income'
        ? [
            'Salary and wages' => ['salary', 'payroll', 'wage', 'wages', 'payslip', 'remuneration'],
            'Grants and support' => ['grant', 'sassa', 'nsfas', 'bursary', 'stipend', 'support', 'unemployment', 'uif', 'child support'],
            'Interest received' => ['interest received', 'credit interest', 'interest earned', 'interest'],
            'Refunds' => ['refund', 'reversal', 'cashback', 'cash back'],
        ]
        : [
            'Education' => ['school', 'school fees', 'tuition', 'university', 'college', 'unisa', 'textbook', 'textbooks', 'stationery'],
            'Bank charges' => ['bank fee', 'bank fees', 'service fee', 'monthly account fee', 'account fee', 'admin fee', 'atm fee', 'cash withdrawal fee', 'card fee', 'bank charge', 'bank charges', 'sms notification', 'fee', 'fees', 'charge', 'charges'],
            'Housing' => ['rent', 'bond', 'mortgage', 'levy', 'rates and taxes', 'body corporate'],
            'Food and groceries' => ['checkers', 'shoprite', 'pick n pay', 'woolworths food', 'spar', 'grocer', 'groceries', 'food', 'restaurant', 'kfc', 'nando', 'nandos', 'mcdonald', 'mcdonalds', 'steers', 'takeaway', 'uber eats', 'mr d', 'boxer', 'ok foods', 'cafe', 'bakery'],
            'Transport' => ['uber', 'bolt', 'taxi', 'fuel', 'petrol', 'diesel', 'engen', 'shell', 'sasol', 'bp', 'caltex', 'gautrain', 'prasa', 'toll', 'e-toll', 'transport', 'bus', 'rea vaya'],
            'Utilities' => ['electricity', 'eskom', 'prepaid electricity', 'water', 'municipal', 'city of', 'utility', 'utilities', 'dstv', 'multichoice', 'fibre', 'insurance', 'netflix', 'showmax'],
            'Airtime and data' => ['airtime', 'data bundle', 'data', 'vodacom', 'mtn', 'cell c', 'telkom', 'rain'],
            'Health' => ['pharmacy', 'clicks', 'dis-chem', 'dischem', 'doctor', 'clinic', 'hospital', 'medical', 'discovery health', 'dentist', 'optometrist'],
            'Debt repayment' => ['loan', 'credit card', 'repayment', 'instalment', 'installment', 'finance', 'capitec credit', 'debt'],
            'Giving and family support' => ['donation', 'church', 'tithe', 'gift', 'family', 'stokvel', 'burial society'],
        ];
    $text = ' ' . mb_strtolower($description) . ' ';
    foreach ($rules as $name => $keywords) {
        foreach ($keywords as $word) {
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/u', $text)) {
                if (($id = $find($name)) !== null) return $id;
                $pdo->prepare('INSERT INTO expense_categories (user_id, name, category_type, is_system) VALUES (NULL, :name, :type, 1)')->execute(['name' => $name, 'type' => $type]);
                return $cache[$userId . '|' . $type . '|' . mb_strtolower($name)] = (int)$pdo->lastInsertId();
            }
        }
    }

    $fallback = $type === 'income' ? 'Other income' : 'Other expense';
    if (($id = $find($fallback)) !== null) return $id;

    $pdo->prepare('INSERT INTO expense_categories (user_id, name, category_type, is_system) VALUES (NULL, :name, :type, 1)')->execute(['name' => $fallback, 'type' => $type]);
    $newId = (int)$pdo->lastInsertId();
    $cache[$userId . '|' . $type . '|' . mb_strtolower($fallback)] = $newId;
    return $newId;
}

/**
 * Import all valid rows for a scanned proof.
 * @return array{imported:int, skipped:int, invalid:int, transaction_ids:int[], rows:array<int,array>}
 */
function proof_auto_import(PDO $pdo, int $userId, int $proofId, array $fields, string $originalName = ''): array
{
    $rows = proof_build_rows($fields);
    $total = count(is_array($fields['transactions'] ?? null) ? $fields['transactions'] : []) ?: count($rows);
    $result = ['imported' => 0, 'skipped' => 0, 'invalid' => max(0, $total - count($rows)), 'transaction_ids' => [], 'rows' => []];
    if (!$rows) return $result;

    $dupe = $pdo->prepare('SELECT id FROM transactions WHERE user_id = :u AND deleted_at IS NULL AND transaction_type = :type AND amount = :amount AND transaction_date = :date AND (LOWER(description) = LOWER(:description) OR (:reference1 <> \'\' AND reference_code = :reference2)) LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO transactions (user_id, category_id, transaction_type, amount, transaction_date, description, payment_method, reference_code) VALUES (:u, :category_id, :type, :amount, :date, :description, :method, :reference)');

    $startedHere = !$pdo->inTransaction();
    if ($startedHere) $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $dupe->execute(['u' => $userId, 'type' => $row['type'], 'amount' => number_format($row['amount'], 2, '.', ''), 'date' => $row['date'], 'description' => $row['description'], 'reference1' => $row['reference'], 'reference2' => $row['reference']]);
            $existingId = $dupe->fetchColumn();
            if ($existingId) { $result['skipped']++; $result['rows'][] = $row + ['id' => (int)$existingId, 'status' => 'duplicate']; continue; }

            $categoryId = proof_resolve_category($pdo, $userId, $row['type'], $row['category_hint'], $row['description']);
            $reference = $row['reference'] !== '' ? $row['reference'] : 'TXN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $insert->execute(['u' => $userId, 'category_id' => $categoryId, 'type' => $row['type'], 'amount' => number_format($row['amount'], 2, '.', ''), 'date' => $row['date'], 'description' => $row['description'], 'method' => $row['method'], 'reference' => $reference]);
            $id = (int)$pdo->lastInsertId();
            $result['imported']++;
            $result['transaction_ids'][] = $id;
            $result['rows'][] = $row + ['reference' => $reference, 'id' => $id, 'status' => 'imported'];
        }

        // Record the outcome on the proof itself: status, first linked transaction, and an import summary beside the scan data.
        $fields['import_summary'] = ['imported' => $result['imported'], 'skipped_duplicates' => $result['skipped'], 'invalid_rows' => $result['invalid'], 'transaction_ids' => $result['transaction_ids'], 'imported_at' => date('Y-m-d H:i:s'), 'source_file' => $originalName];
        $status = $result['imported'] > 0 ? 'imported' : 'scanned_review';
        $update = $pdo->prepare('UPDATE payment_proofs SET verification_status = :status, transaction_id = :tx, extracted_json = :json WHERE id = :id AND user_id = :u');
        $update->execute(['status' => $status, 'tx' => $result['transaction_ids'][0] ?? null, 'json' => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'id' => $proofId, 'u' => $userId]);

        if ($startedHere && $pdo->inTransaction()) $pdo->commit();
    } catch (Throwable $e) {
        if ($startedHere && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    foreach ($result['rows'] as $r) {
        if (($r['status'] ?? '') === 'imported') record_audit_event($userId, 'transaction.imported_from_proof', 'transaction', (int)$r['id'], ['proof_id' => $proofId, 'type' => $r['type'], 'amount' => $r['amount'], 'transaction_date' => $r['date']]);
    }
    if ($result['imported'] > 0 && function_exists('invalidate_financial_records_cache')) invalidate_financial_records_cache($userId);
    return $result;
}
