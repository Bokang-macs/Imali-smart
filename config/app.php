<?php
// Main settings. Reads the .env file, starts the session and holds small helper functions.
declare(strict_types=1);

// Adds the file's last-changed time to CSS/JS links so browsers never show an old cached copy after an update.
if (!function_exists('asset_v')) {
    function asset_v(string $path): string
    {
        $file = dirname(__DIR__) . '/' . ltrim($path, '/');
        return is_file($file) ? (string) filemtime($file) : '1';
    }
}


// South African time, so "today", "this month" and holidays roll over at local midnight.
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Johannesburg');

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Imali Smart');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

// Production: never print PHP errors or file paths to visitors. Errors still go to the server error log.
// Set APP_DEBUG=true in .env only while fixing a problem.
$__imaliDebug = in_array(strtolower((string) (getenv('APP_DEBUG') ?: '')), ['1', 'true'], true);
if (!$__imaliDebug && is_readable(dirname(__DIR__) . '/.env')) {
    $__imaliEnv = (string) @file_get_contents(dirname(__DIR__) . '/.env');
    $__imaliDebug = (bool) preg_match('/^\s*APP_DEBUG\s*=\s*["\']?(true|1)["\']?\s*$/mi', $__imaliEnv);
}
@ini_set('display_errors', $__imaliDebug ? '1' : '0');
@ini_set('log_errors', '1');
unset($__imaliDebug, $__imaliEnv);

require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/cache.php';

/**
 * Retrieve environment variable or fallback
 */
function env_value(string $key, ?string $default = null): ?string
{
    static $loaded = false;
    static $values = [];
    if (!$loaded) {
        $loaded = true;
        $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$name, $value] = explode('=', $line, 2);
                $values[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }
    }
    $envVal = getenv($key);
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? ($envVal !== false && $envVal !== '' ? $envVal : null) ?? $values[$key] ?? null;
    return $value === null || $value === '' ? $default : $value;
}

/**
 * Global App Configuration
 */
function app_config(): array
{
    static $config;
    if ($config !== null) return $config;
    $config = [
        'name' => APP_NAME,
        'version' => APP_VERSION,
        'app_url' => rtrim((string) env_value('APP_URL', ''), '/'),
        'database_url' => env_value('DATABASE_URL'),
        'db_host' => env_value('DB_HOST', '127.0.0.1'),
        'db_port' => env_value('DB_PORT', '3306'),
        'db_name' => env_value('DB_NAME', 'imali_smart'),
        'db_user' => env_value('DB_USER', 'root'),
        'db_password' => env_value('DB_PASSWORD', ''),
        'gemini_api_key' => env_value('GEMINI_API_KEY'),
        'gemini_model' => env_value('GEMINI_MODEL', 'gemini-flash-lite-latest'),
        'weather_api_key' => env_value('WEATHER_API_KEY'), // no longer required: weather uses Open-Meteo (no key)
        'resend_api_key' => env_value('RESEND_API_KEY'),
        'resend_from_email' => env_value('RESEND_FROM_EMAIL', 'onboarding@resend.dev'),
        'brevo_api_key' => env_value('BREVO_API_KEY'),
        'brevo_sender_email' => env_value('BREVO_SENDER_EMAIL'),
        'brevo_sender_name' => env_value('BREVO_SENDER_NAME', APP_NAME),
        'email_validation_url' => env_value('EMAIL_VALIDATION_URL'),
        'email_validation_api_key' => env_value('EMAIL_VALIDATION_API_KEY'),
        'holidays_api_url' => env_value('HOLIDAYS_API_URL', 'https://randomapi.dev/api/holidays?country=ZA&year=' . date('Y')),
        'frankfurter_api_url' => env_value('FRANKFURTER_API_URL', 'https://api.frankfurter.dev/v2/rate/'),
        'sarb_rates_url' => env_value('SARB_RATES_URL', 'https://custom.resbank.co.za/SarbWebApi/WebIndicators/HomePageRates'),
        'qr_server_api_url' => env_value('QR_SERVER_API_URL', 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='),
        'is_preview_https' => env_value('PREVIEW_HTTPS', 'false') === 'true',
    ];
    return $config;
}

/**
 * Safe Session Start
 */
/**
 * True when the visitor reached the site over HTTPS. Also works behind a proxy / CDN
 * (InfinityFree, Cloudflare) that terminates HTTPS and forwards plain HTTP to PHP.
 */
function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
    if (str_contains((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"scheme":"https"')) return true;
    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = is_https_request() || app_config()['is_preview_https'];
    session_name('imali_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $secure ? 'None' : 'Lax',
    ]);
    session_start();
}

start_app_session();

// Makes text safe to print in HTML.
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Web path where the app lives, e.g. "" when served from the site root or "/imali-smart" in a sub-folder.
function app_base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $root = str_replace('\\', '/', (string) realpath(dirname(__DIR__)));
    $script = str_replace('\\', '/', (string) realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($root !== '' && $script !== '' && str_starts_with($script, $root . '/')) {
        $relative = substr($script, strlen($root));            // e.g. /actions/transactions/add.php
        if ($relative !== '' && str_ends_with($scriptName, $relative)) {
            return $base = rtrim(substr($scriptName, 0, -strlen($relative)), '/');
        }
    }
    $doc = str_replace('\\', '/', (string) realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')));
    if ($doc !== '' && $root !== '' && str_starts_with($root, $doc)) {
        return $base = rtrim(substr($root, strlen($doc)), '/');
    }
    return $base = '';
}

// Full base URL to the application root, e.g. "http://localhost:3000" or Cloud Run URL
function app_base_url(): string
{
    $envUrl = rtrim((string) env_value('APP_URL', ''), '/');
    if ($envUrl !== '' && !str_starts_with($envUrl, 'MY_APP_URL')) {
        // APP_URL may already include the folder (http://localhost/imali-smart), so do not add it twice.
        $envPath = rtrim((string) parse_url($envUrl, PHP_URL_PATH), '/');
        $basePath = app_base_path();
        if ($basePath === '' || $envPath === $basePath || str_ends_with($envPath, $basePath)) return $envUrl;
        return $envUrl . $basePath;
    }
    $proto = is_https_request() || app_config()['is_preview_https'] ? 'https' : 'http';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost:3000';
    return $proto . '://' . $host . app_base_path();
}

// Sends the browser to another page. Relative paths such as "transactions.php" are resolved from the app
// folder, so they also work when the app is not installed at the site root.
function redirect_to(string $path): never
{
    if (!str_starts_with($path, '/') && !str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
        $path = app_base_path() . '/' . $path;
    }
    header('Location: ' . $path);
    exit;
}

// Returns true if the request uses the given method (GET or POST).
function request_method_is(string $method): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === strtoupper($method);
}

// Returns the path of the current page.
function request_path(): string
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
}

// Saves a short message to show once on the next page.
function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}

// Turns an error into a safe message with no secrets.
function app_error_message(Throwable $error): string
{
    error_log('[Imali Smart] ' . $error->getMessage());
    return 'We could not complete that request. Please verify your connection or try again.';
}

/**
 * Path to a CA certificate bundle for HTTPS calls (Gemini, Brevo, holidays...).
 * Some Windows PHP installs have none configured, which makes every curl HTTPS request fail with
 * "SSL certificate problem". Returns null when nothing is found (curl then uses its own default).
 */
function imali_ca_bundle(): ?string
{
    static $path = false;
    if ($path !== false) return $path;
    $candidates = [
        (string) env_value('CURL_CA_BUNDLE', ''),
        (string) ini_get('curl.cainfo'),
        (string) ini_get('openssl.cafile'),
        dirname(PHP_BINARY) . '/extras/ssl/cacert.pem',
        'C:/php/extras/ssl/cacert.pem',
        dirname(__DIR__) . '/config/cacert.pem',           // drop a downloaded cacert.pem here if needed
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && @is_file($candidate) && @is_readable($candidate)) {
            return $path = $candidate;
        }
    }
    return $path = null;
}
