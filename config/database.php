<?php
// Connects to the production MySQL database configured in .env (InfinityFree).
declare(strict_types=1);

require_once __DIR__ . '/app.php';

/**
 * Returns a PDO database handle (MySQL, configured in .env).
 */
function db(): ?PDO
{
    static $pdo = null;
    static $attempted = false;
    static $driver = 'mysql';

    if ($attempted) return $pdo;
    $attempted = true;

    $config = app_config();

    // 1. Try MySQL Connection
    try {
        $dsn = (string) ($config['database_url'] ?? '');
        $user = null;
        $password = null;

        if ($dsn !== '') {
            $parts = parse_url($dsn);
            if ($parts === false || empty($parts['host'])) {
                throw new RuntimeException('Invalid DATABASE_URL.');
            }
            $database = ltrim((string) ($parts['path'] ?? ''), '/');
            $dsn = 'mysql:host=' . $parts['host'] . ';port=' . ($parts['port'] ?? 3306) . ';dbname=' . $database . ';charset=utf8mb4';
            $user = $parts['user'] ?? null;
            $password = $parts['pass'] ?? null;
        } else {
            $host = $config['db_host'] ?: '127.0.0.1';
            $port = $config['db_port'] ?: '3306';
            $name = $config['db_name'] ?: 'imali_smart';
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $user = $config['db_user'] ?: 'root';
            $password = $config['db_password'] ?? '';
        }

        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $GLOBALS['db_driver'] = 'mysql';
        return $pdo;
    } catch (Throwable $e) {
        $GLOBALS['db_error'] = $e->getMessage();
        $hint = '';
        if (str_contains($e->getMessage(), 'Unknown database')) {
            $hint = ' (check DB_NAME in .env and import database/schema.sql in phpMyAdmin)';
        } elseif (str_contains($e->getMessage(), 'Access denied')) {
            $hint = ' (check DB_USER / DB_PASSWORD in .env)';
        }
        error_log('[Imali Smart] MySQL connection failed: ' . $e->getMessage() . $hint);
        $pdo = null;
    }

    return $pdo;
}

// Returns true if the database connection works.
function database_ready(): bool
{
    return db() instanceof PDO;
}

// Returns the database type in use (always mysql).
function db_driver(): string
{
    db();
    return 'mysql';
}

// Returns the last connection error message (empty when connected). Handy when checking the .env database settings.
function db_last_error(): string
{
    db();
    return (string) ($GLOBALS['db_error'] ?? '');
}
