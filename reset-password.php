<?php
// Consumes a single-use password reset token.
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/csrf.php';

$page_title = 'Reset password';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = null;
$message = null;
$validToken = false;

if ($token !== '' && preg_match('/^[A-Za-z0-9_-]{30,100}$/', $token) && database_ready()) {
    try {
        $check = db()->prepare('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > :now LIMIT 1');
        $check->execute(['token_hash' => hash('sha256', $token), 'now' => date('Y-m-d H:i:s')]);
        $validToken = (bool) $check->fetch();
        if (!$validToken) {
            $error = 'This reset link is invalid or has expired. Please request a new one.';
        }
    } catch (Throwable $e) {
        $validToken = false;
        $error = 'Unable to verify reset link at this time.';
    }
} elseif ($token !== '') {
    $error = 'This reset link is malformed or invalid. Please request a new one.';
}

if (request_method_is('POST')) {
    require_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (!$validToken) {
        $error = 'This reset link is invalid or has expired. Request a new one.';
    } elseif (strlen($password) < 8) {
        $error = 'Use a password with at least 8 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The passwords do not match.';
    } else {
        try {
            db()->beginTransaction();
            $lookup = db()->prepare('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > :now LIMIT 1');
            $lookup->execute(['token_hash' => hash('sha256', $token), 'now' => date('Y-m-d H:i:s')]);
            $reset = $lookup->fetch();
            if (!$reset) {
                throw new RuntimeException('Expired reset token.');
            }
            $update = db()->prepare('UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id AND deleted_at IS NULL');
            $update->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'user_id' => $reset['user_id']]);
            $used = db()->prepare('UPDATE password_reset_tokens SET used_at = :used_at WHERE id = :id');
            $used->execute(['used_at' => date('Y-m-d H:i:s'), 'id' => $reset['id']]);
            // Old "remember me" logins must stop working after a password reset.
            db()->prepare('DELETE FROM remember_me_tokens WHERE user_id = :user_id')->execute(['user_id' => $reset['user_id']]);
            db()->commit();
            $validToken = false;
            $message = 'Your password has been reset. You can now sign in.';
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            error_log('[Imali Smart] Password reset completion failed: ' . $e->getMessage());
            $error = 'We could not reset your password. Please request a new link.';
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($page_title) ?> · Imali Smart</title><link rel="stylesheet" href="css/variables.css?v=<?= asset_v('css/variables.css') ?>"><link rel="stylesheet" href="css/global.css?v=<?= asset_v('css/global.css') ?>"><link rel="stylesheet" href="css/layout.css?v=<?= asset_v('css/layout.css') ?>"><link rel="stylesheet" href="css/auth.css?v=<?= asset_v('css/auth.css') ?>"></head><body class="app-body"><main class="auth-layout"><aside class="auth-aside"><a class="brand-mark" href="index.php">Imali Smart</a><div class="auth-aside__copy"><span class="eyebrow">ACCOUNT RECOVERY</span><h1>Choose a new password.</h1><p>Your reset link is single-use and expires after one hour.</p></div></aside><section class="auth-panel"><div class="auth-card"><span class="eyebrow">NEW PASSWORD</span><h2>Reset your password</h2><?php if ($message): ?><div class="alert alert--success"><?= e($message) ?></div><a class="btn btn--primary" href="login.php">Continue to sign in</a><?php elseif ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?><?php if ($validToken): ?><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><div class="form-field"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required></div><div class="form-field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></div><button class="btn btn--primary" type="submit">Save new password</button></form><?php elseif (!$message): ?><a class="btn btn--primary" href="forgot-password.php">Request a new reset link</a><?php endif; ?><p class="auth-footer-link"><a href="login.php">Return to sign in</a></p></div></section></main></body></html>
