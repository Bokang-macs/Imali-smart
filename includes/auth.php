<?php
// Login helpers: who is logged in, log in, log out and protect pages.
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Returns the logged-in user from the database, or null.
function current_user(bool $refresh = false): ?array
{
    static $user;
    if ($refresh) $user = null;
    if ($user !== null) return $user;
    $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($id < 1 || !database_ready()) return null;
    try {
        $statement = db()->prepare('SELECT id, full_name, email, phone_number, alternative_email, date_of_birth, province, city, preferred_contact_method, preferred_language, currency_code, opening_balance FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch() ?: null;
    } catch (Throwable $e) {
        $user = null;
    }
    return $user;
}

function remember_me_cookie_name(): string
{
    return 'imali_remember';
}

function remember_me_cookie_options(int $lifetime): array
{
    $secure = is_https_request() || app_config()['is_preview_https'];
    return ['expires' => time() + $lifetime, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => $secure ? 'None' : 'Lax'];
}

function issue_remember_me_token(int $userId): void
{
    return; // Persistent "keep me signed in" logins are disabled: users must sign in each session.
    if ($userId < 1 || !database_ready()) return;
    try {
        $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $stmt = db()->prepare('INSERT INTO remember_me_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)');
        $stmt->execute(['user_id' => $userId, 'token_hash' => hash('sha256', $plainToken), 'expires_at' => date('Y-m-d H:i:s', time() + (30 * 86400))]);
        setcookie(remember_me_cookie_name(), $plainToken, remember_me_cookie_options(30 * 86400));
    } catch (Throwable $e) {
        error_log('[Imali Smart] Remember Me token issue failed: ' . $e->getMessage());
    }
}

function forget_remember_me_token(): void
{
    $plainToken = (string) ($_COOKIE[remember_me_cookie_name()] ?? '');
    if ($plainToken !== '' && database_ready()) {
        try {
            $stmt = db()->prepare('DELETE FROM remember_me_tokens WHERE token_hash = :token_hash');
            $stmt->execute(['token_hash' => hash('sha256', $plainToken)]);
        } catch (Throwable $e) {}
    }
    setcookie(remember_me_cookie_name(), '', remember_me_cookie_options(-3600));
    unset($_COOKIE[remember_me_cookie_name()]);
}

function restore_user_from_remember_me(): void
{
    return; // Disabled: the app never signs anyone in automatically.
    $plainToken = (string) ($_COOKIE[remember_me_cookie_name()] ?? '');
    if ($plainToken === '' || !database_ready()) return;
    $tokenHash = hash('sha256', $plainToken);
    try {
        $stmt = db()->prepare('SELECT user_id FROM remember_me_tokens WHERE token_hash = :token_hash AND expires_at > NOW() LIMIT 1');
        $stmt->execute(['token_hash' => $tokenHash]);
        $userId = (int) $stmt->fetchColumn();
        if ($userId < 1) {
            forget_remember_me_token();
            return;
        }
        db()->prepare('DELETE FROM remember_me_tokens WHERE token_hash = :token_hash')->execute(['token_hash' => $tokenHash]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['logged_in_at'] = time();
        issue_remember_me_token($userId);
    } catch (Throwable $e) {
        error_log('[Imali Smart] Remember Me restore failed: ' . $e->getMessage());
    }
}

// Marks the user as logged in and keeps their language choice.
function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['logged_in_at'] = time();
    if (function_exists('record_audit_event')) {
        record_audit_event($userId, 'auth.login', 'user', $userId, ['remember_me' => !empty($_POST['remember_me'])]);
    }

    // Synchronize language preference
    if (function_exists('database_ready') && database_ready()) {
        try {
            $stmt = db()->prepare('SELECT preferred_language FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $dbLang = $stmt->fetchColumn();

            $activeSessionLang = $_SESSION['imali_lang'] ?? $_COOKIE['imali_lang'] ?? null;
            if ($activeSessionLang && function_exists('available_languages') && isset(available_languages()[$activeSessionLang])) {
                $up = db()->prepare('UPDATE users SET preferred_language = :l WHERE id = :id');
                $up->execute(['l' => $activeSessionLang, 'id' => $userId]);
                if (function_exists('set_current_lang')) {
                    set_current_lang((string)$activeSessionLang);
                }
            } elseif ($dbLang && function_exists('set_current_lang')) {
                set_current_lang((string)$dbLang);
            }
        } catch (Throwable $e) {}
    }
}

// Clears the session so the user is logged out.
function logout_user(): void
{
    forget_remember_me_token();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

// Returns true if a user is logged in.
function is_authenticated(): bool
{
    return current_user() !== null;
}

// Stops the page and asks for login if nobody is logged in.
function require_auth(bool $json = false): void
{
    if (is_authenticated()) return;
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Authentication required. Please log in.']);
        exit;
    }
    flash('auth_notice', 'Please sign in to access your dashboard and workspace. If you don\'t have an account, register below.');
    $target = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
    redirect_to('login.php?redirect=' . urlencode($target));
}
