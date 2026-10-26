<?php
/**
 * Upload and scan a proof of payment / bank statement.
 * When Gemini confirms the document is valid, every readable row is imported straight into
 * Transactions and the result is recorded on the payment_proofs row (see includes/proof_import.php).
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/gemini.php';
require_once dirname(__DIR__, 2) . '/includes/proof_import.php';

@ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
require_auth(true);
if (!request_method_is('POST')) json_response(['ok' => false, 'error' => 'POST request method required.'], 405);
require_csrf(true);

@set_time_limit(120);
@ignore_user_abort(true);
$user = current_user();
$file = $_FILES['proof'] ?? null;
if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => 'Choose a proof-of-payment image or PDF first.'], 422);
}
if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 8 * 1024 * 1024) {
    json_response(['ok' => false, 'error' => 'The document must be smaller than 8 MB.'], 422);
}

$tmp = (string)$file['tmp_name'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
if ($finfo) finfo_close($finfo);
$allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) json_response(['ok' => false, 'error' => 'Only PDF, JPG, PNG, and WEBP proof documents are supported.'], 422);

$proofDir = dirname(__DIR__, 2) . '/uploads/payment-proofs';
if (!is_dir($proofDir) && !@mkdir($proofDir, 0700, true)) json_response(['ok' => false, 'error' => 'The secure upload folder is unavailable.'], 500);
$storageKey = date('Y/m') . '/' . bin2hex(random_bytes(18)) . '.' . $allowed[$mime];
$relativePath = 'uploads/payment-proofs/' . $storageKey;
$absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;
if (!is_dir(dirname($absolutePath))) @mkdir(dirname($absolutePath), 0700, true);
if (!move_uploaded_file($tmp, $absolutePath)) json_response(['ok' => false, 'error' => 'The document could not be stored securely.'], 500);

$fields = [];
$scan = gemini_scan_document((string)@file_get_contents($absolutePath), $mime, (string)($_POST['language'] ?? 'English'), (string)($user['full_name'] ?? ''));
if (!empty($scan['ok'])) $fields = proof_apply_direction($scan['fields'] ?? [], (string)($user['full_name'] ?? ''));
$validFlag = $fields['is_valid_statement'] ?? false;
$isValidDocument = !empty($scan['ok']) && ($validFlag === true || $validFlag === 1 || strtolower((string)$validFlag) === 'true');
// Gemini is sometimes over-cautious: accept the document when it still returned clear dated, priced rows from a named bank/provider.
if (!empty($scan['ok']) && !$isValidDocument && proof_build_rows($fields) && (!empty($fields['bank_or_provider']) || !empty($fields['document_type']))) {
    $isValidDocument = true;
    $fields['is_valid_statement'] = true;
}
$scanFailMessages = [
    'not_configured' => 'The AI scanner is not set up: GEMINI_API_KEY is missing in the .env file on the server.',
    'key_rejected' => 'Google rejected the Gemini API key. Check GEMINI_API_KEY in the .env file on the server.',
    'rate_limited' => 'The AI scanner is busy right now (rate limit). Please try again in a minute.',
    'network_error' => 'The server could not reach the Gemini AI service. Check that the host allows outgoing connections, then try again.',
    'curl_unavailable' => 'PHP curl is disabled on the server, so the AI scanner cannot connect.',
    'invalid_scan_response' => 'The AI replied in an unreadable format. Please try scanning again.',
];

try {
    $pdo = db();
    if (!$pdo) throw new RuntimeException('Database is not available.');
    $driver = db_driver();
    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_proofs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, transaction_id BIGINT UNSIGNED NULL, storage_key VARCHAR(255) NOT NULL, original_name VARCHAR(255) NULL, mime_type VARCHAR(100) NULL, file_size INT UNSIGNED NULL, verification_status VARCHAR(30) NOT NULL DEFAULT 'unverified', extracted_json LONGTEXT NULL, scanned_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, deleted_at TIMESTAMP NULL, KEY idx_payment_proofs_owner (user_id, deleted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach ([['extracted_json', 'LONGTEXT NULL'], ['scanned_at', 'DATETIME NULL']] as [$column, $definition]) {
            $exists = $pdo->query("SHOW COLUMNS FROM payment_proofs LIKE '{$column}'")->fetch();
            if (!$exists) $pdo->exec("ALTER TABLE payment_proofs ADD COLUMN {$column} {$definition}");
        }
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS payment_proofs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, transaction_id INTEGER NULL, storage_key TEXT NOT NULL, original_name TEXT NULL, mime_type TEXT NULL, file_size INTEGER NULL, verification_status TEXT NOT NULL DEFAULT "unverified", extracted_json TEXT NULL, scanned_at TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, deleted_at DATETIME NULL)');
        $columns = $pdo->query('PRAGMA table_info(payment_proofs)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('extracted_json', $columns, true)) $pdo->exec('ALTER TABLE payment_proofs ADD COLUMN extracted_json TEXT NULL');
        if (!in_array('scanned_at', $columns, true)) $pdo->exec('ALTER TABLE payment_proofs ADD COLUMN scanned_at TEXT NULL');
    }
    $stmt = $pdo->prepare('INSERT INTO payment_proofs (user_id, storage_key, original_name, mime_type, file_size, verification_status, extracted_json, scanned_at) VALUES (:user_id, :storage_key, :original_name, :mime_type, :file_size, :status, :fields, :scanned_at)');
    $stmt->execute([
        'user_id' => $user['id'], 'storage_key' => $relativePath,
        'original_name' => mb_substr((string)($file['name'] ?? 'proof'), 0, 255),
        'mime_type' => $mime, 'file_size' => (int)$file['size'],
        'status' => !empty($scan['ok']) ? ($isValidDocument ? 'scanned_review' : 'invalid_document') : 'scan_unavailable',
        'fields' => $fields ? json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        'scanned_at' => !empty($scan['ok']) ? date('Y-m-d H:i:s') : null,
    ]);
    $proofId = (int)$pdo->lastInsertId();
    $import = ['imported' => 0, 'skipped' => 0, 'invalid' => 0, 'transaction_ids' => [], 'rows' => []];
    if ($isValidDocument) {
        try {
            $import = proof_auto_import($pdo, (int)$user['id'], $proofId, $fields, (string)($file['name'] ?? ''));
        } catch (Throwable $importError) {
            error_log('[Imali Smart] Auto-import failed: ' . $importError->getMessage());
            json_response(['ok' => true, 'valid_document' => true, 'scan_status' => 'scanned_review', 'proof_id' => $proofId, 'fields' => $fields, 'imported' => 0, 'skipped' => 0, 'rows' => [], 'summary' => proof_import_summary([]), 'message' => 'The document was scanned and saved, but its transactions could not be saved: ' . mb_substr($importError->getMessage(), 0, 160)]);
        }
    }
    if (!empty($scan['ok'])) {
        if (!$isValidDocument) $message = 'Invalid document: this does not look like a readable bank statement or proof of payment.';
        elseif ($import['imported'] > 0) $message = $import['imported'] . ' transaction' . ($import['imported'] === 1 ? '' : 's') . ' imported to Transactions' . ($import['skipped'] ? ' (' . $import['skipped'] . ' duplicate' . ($import['skipped'] === 1 ? '' : 's') . ' skipped)' : '') . '.';
        elseif ($import['skipped'] > 0) $message = 'Document scanned. Its transactions were already in your ledger, so nothing new was added.';
        else $message = 'Document scanned and saved, but no clear transaction rows (amount and date) were found to import.';
    } else {
        $message = $scanFailMessages[$scan['reason'] ?? ''] ?? 'Document stored, but the AI scan was unavailable (' . ($scan['reason'] ?? 'unknown') . '). Please try again.';
    }
    json_response(['ok' => true, 'valid_document' => $isValidDocument, 'message' => $message, 'scan_status' => $import['imported'] > 0 ? 'imported' : (!empty($scan['ok']) ? ($isValidDocument ? 'scanned_review' : 'invalid_document') : 'scan_unavailable'), 'proof_id' => $proofId, 'fields' => $fields, 'imported' => $import['imported'], 'skipped' => $import['skipped'], 'rows' => $import['rows'], 'summary' => proof_import_summary($import['rows']), 'links' => proof_result_links($import['rows']), 'today' => date('Y-m-d'), 'date_notice' => proof_date_notice($import['rows']), 'reason' => $scan['reason'] ?? '']);
} catch (Throwable $e) {
    @unlink($absolutePath);
    error_log('[Imali Smart] Proof upload failed: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'The document was not saved. Please try again.'], 500);
}
