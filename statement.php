<?php
// Public statement page. The QR code on a statement opens this page, so anyone who scans it
// sees that statement straight away: no sign-in and no app. The long random code in the link is the key.
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/statement_view.php';

$token = strtolower(trim((string) ($_GET['t'] ?? '')));
$found = find_statement_share($token);

if (is_array($found)) {
    render_statement_page([
        'snapshot' => $found['snapshot'],
        'url' => $found['url'],
        'show_print' => true,
        'shared_view' => true,
        'local_warning' => false,
    ]);
    exit;
}

$messages = [
    'expired' => ['This statement link has expired', 'For privacy, statement links stop working after 180 days. Ask the person who shared it to send a fresh one.'],
    'revoked' => ['This statement link was switched off', 'The owner turned this link off. Ask them to send a new statement.'],
    'invalid' => ['We could not find that statement', 'The link may be incomplete or mistyped. Scan the QR code again, or ask the person who shared it to send it again.'],
];
[$heading, $copy] = $messages[$found] ?? $messages['invalid'];
http_response_code($found === 'invalid' ? 404 : 410);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
$base = app_base_path();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= e($heading) ?> · Imali Smart</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f7f5ef;color:#193c31;font-family:Arial,Helvetica,sans-serif;padding:20px}.card{max-width:440px;background:#fff;border:1px solid #e2e5df;border-radius:14px;padding:28px;text-align:center}img{width:64px;height:64px;object-fit:contain}h1{font-size:21px;margin:14px 0 8px}p{color:#5d6e66;line-height:1.55;margin:0 0 18px}a{display:inline-block;background:#1d5b47;color:#fff;text-decoration:none;border-radius:8px;padding:10px 16px;font-size:14px}</style></head>
<body><div class="card"><img src="<?= e($base) ?>/assets/logo/imali-logo.png" alt="Imali Smart"><h1><?= e($heading) ?></h1><p><?= e($copy) ?></p><a href="<?= e($base) ?>/index.php">Go to Imali Smart</a></div></body></html>
