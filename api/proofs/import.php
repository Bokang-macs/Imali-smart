<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/proof_import.php';

header('Content-Type: application/json; charset=utf-8');
require_auth();
if (!request_method_is('POST')) json_response(['ok' => false, 'error' => 'POST request method required.'], 405);
require_csrf();
$user = current_user();
$proofId = (int)($_POST['proof_id'] ?? 0);
$selectedRows = json_decode((string)($_POST['rows'] ?? '[]'), true);
if ($proofId < 1 || !is_array($selectedRows)) json_response(['ok' => false, 'error' => 'Choose valid rows to import.'], 422);

try {
    $pdo = db();
    if (!$pdo) throw new RuntimeException('Database unavailable.');
    $proof = $pdo->prepare('SELECT id, extracted_json, verification_status FROM payment_proofs WHERE id = :id AND user_id = :u AND deleted_at IS NULL LIMIT 1');
    $proof->execute(['id' => $proofId, 'u' => $user['id']]);
    $record = $proof->fetch();
    if (!$record || !in_array((string)$record['verification_status'], ['scanned_review'], true)) json_response(['ok' => false, 'error' => 'This document is not a valid, reviewable financial statement.'], 422);
    $stored = json_decode((string)$record['extracted_json'], true);
    $storedValid = $stored['is_valid_statement'] ?? false;
    if (!is_array($stored) || !($storedValid === true || $storedValid === 1 || strtolower((string)$storedValid) === 'true') || !is_array($stored['transactions'] ?? null)) json_response(['ok' => false, 'error' => 'No verified transaction rows are available for import.'], 422);

    $rowsByKey = [];
    foreach ($stored['transactions'] as $row) {
        if (is_array($row)) $rowsByKey[hash('sha256', json_encode($row))] = $row;
    }
    $chosen = [];
    foreach ($selectedRows as $candidate) {
        if (!is_array($candidate)) continue;
        $row = $rowsByKey[hash('sha256', json_encode($candidate))] ?? null;
        if ($row) $chosen[] = $row;
    }
    $stored['transactions'] = $chosen;
    $stored['amount'] = null; // import only the chosen rows, never a fallback single-payment row
    $result = proof_auto_import($pdo, (int)$user['id'], $proofId, $stored, 'manual selection');
    $count = $result['imported'];
    if ($count < 1) json_response(['ok' => false, 'error' => 'No valid rows were selected for import.'], 422);
    json_response(['ok' => true, 'imported' => $count, 'message' => $count . ' transaction' . ($count === 1 ? '' : 's') . ' added to Transactions. Refresh Analytics to see them in the graphs.']);
} catch (Throwable $e) {
    error_log('[Imali Smart] Proof import failed: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'The selected rows could not be imported.'], 500);
}
