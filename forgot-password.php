<?php
// Requests a single-use password reset email through Brevo.
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/brevo.php';

$page_title = 'Forgot password';
$message = null;
$error = null;

if (request_method_is('POST')) {
    require_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (!database_ready()) {
        $error = 'The workspace service is temporarily unavailable. Please try again shortly.';
    } else {
        // Always show the same success response so account existence is not disclosed.
        $message = 'If an account matches that email, we will send a password-reset link shortly.';
        try {
            $stmt = db()->prepare('SELECT id, full_name, email FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['email' => $email]);
            $account = $stmt->fetch();
            // Cooldown: at most one reset email per account per minute (stops inbox flooding).
            $recent = false;
            if ($account) {
                $last = db()->prepare('SELECT created_at FROM password_reset_tokens WHERE user_id = :user_id AND used_at IS NULL ORDER BY id DESC LIMIT 1');
                $last->execute(['user_id' => $account['id']]);
                $lastAt = $last->fetchColumn();
                $recent = $lastAt && (time() - (int) strtotime((string) $lastAt)) < 60;
            }
            if ($account && !$recent) {
                $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $tokenHash = hash('sha256', $plainToken);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);
                db()->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id AND used_at IS NULL')->execute(['user_id' => $account['id']]);
                $insert = db()->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)');
                $insert->execute(['user_id' => $account['id'], 'token_hash' => $tokenHash, 'expires_at' => $expiresAt]);

                $config = app_config();
                $baseUrl = rtrim((string) ($config['app_url'] ?? ''), '/');
                if ($baseUrl === '') {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
                    $baseUrl = $scheme . '://' . $host . ($scriptDir !== '' && $scriptDir !== '/' ? $scriptDir : '');
                }
                $resetUrl = $baseUrl . '/reset-password.php?token=' . rawurlencode($plainToken);
                $name = trim((string) ($account['full_name'] ?? 'there')) ?: 'there';
                $subject = 'Reset your Imali Smart password';
                $text = "Hi {$name},\n\nUse this link to reset your Imali Smart password:\n{$resetUrl}\n\nThis link expires in 1 hour and can be used once. If you did not request this, ignore this email.\n\nImali Smart";
                $html = '<p>Hi ' . e($name) . ',</p><p>Use the button below to reset your Imali Smart password.</p><p><a href="' . e($resetUrl) . '">Reset my password</a></p><p>This link expires in 1 hour and can be used once. If you did not request this, ignore this email.</p><p>Imali Smart</p>';
                $sent = brevo_send_email((string) $account['email'], $subject, $text, $html);
                if (!$sent['ok']) {
                    error_log('[Imali Smart] Password reset email was not accepted by Brevo: ' . (string) ($sent['reason'] ?? 'unknown') . ' (set BREVO_API_KEY and a verified BREVO_SENDER_EMAIL in .env)');
                    // Local testing only: with no email service set up, write the link to the PHP error log
                    // (C:\xampp\apache\logs\error.log) so you can still test the reset flow on your own PC.
                    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                    if (in_array($remote, ['127.0.0.1', '::1'], true)) {
                        error_log('[Imali Smart] LOCAL TEST reset link for ' . $account['email'] . ': ' . $resetUrl);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[Imali Smart] Password reset request failed: ' . $e->getMessage());
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($page_title) ?> · Imali Smart</title><link rel="stylesheet" href="css/variables.css?v=<?= asset_v('css/variables.css') ?>"><link rel="stylesheet" href="css/global.css?v=<?= asset_v('css/global.css') ?>"><link rel="stylesheet" href="css/layout.css?v=<?= asset_v('css/layout.css') ?>"><link rel="stylesheet" href="css/auth.css?v=<?= asset_v('css/auth.css') ?>"></head><body class="app-body"><main class="auth-layout"><aside class="auth-aside"><a class="brand-mark" href="index.php">Imali Smart</a><div class="auth-aside__copy"><span class="eyebrow">ACCOUNT RECOVERY</span><h1>Get back to your money workspace.</h1><p>We will send a secure, single-use reset link to your account email.</p></div></aside><section class="auth-panel"><div class="auth-card"><span class="eyebrow">PASSWORD RESET</span><h2>Forgot your password?</h2><p>Enter the email address linked to your Imali Smart account.</p><?php if ($message): ?><div class="alert alert--success"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid"><?= csrf_field() ?><div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>"></div><button class="btn btn--primary" type="submit">Send reset link</button></form><p class="auth-footer-link"><a href="login.php">Return to sign in</a></p></div></section></main></body></html>
