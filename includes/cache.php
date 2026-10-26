<?php
// Small file cache that makes repeated page loads faster.
declare(strict_types=1);

/**
 * Imali Smart - Fast Application Cache & Query Optimization Engine
 * Saves results in memory and in files for each user, and clears them when data changes.
 * Never leaks data between accounts.
 */

if (!defined('IMALIS_CACHE_DIR')) {
    define('IMALIS_CACHE_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache');
}

$GLOBALS['IMALIS_MEMORY_CACHE'] = [];

/**
 * Ensure storage directory exists.
 */
function cache_ensure_dir(): void
{
    if (!is_dir(IMALIS_CACHE_DIR)) {
        @mkdir(IMALIS_CACHE_DIR, 0755, true);
    }
}

/**
 * Generate a safe user-scoped cache key.
 */
function cache_key(string $prefix, int|string $userId = 0, array $params = []): string
{
    $scope = $userId ? 'usr_' . $userId : 'global';
    $paramHash = !empty($params) ? '_' . substr(md5(serialize($params)), 0, 10) : '';
    return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $prefix) . '_' . $scope . $paramHash;
}

/**
 * Retrieve a cached value if not expired.
 */
function cache_get(string $key): mixed
{
    if (isset($GLOBALS['IMALIS_MEMORY_CACHE'][$key])) {
        return $GLOBALS['IMALIS_MEMORY_CACHE'][$key];
    }

    cache_ensure_dir();
    $file = IMALIS_CACHE_DIR . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    if (!is_file($file)) {
        return null;
    }

    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }

    $data = @unserialize($raw);
    if (!is_array($data) || !isset($data['expires_at'], $data['payload'])) {
        @unlink($file);
        return null;
    }

    if (time() > (int)$data['expires_at']) {
        @unlink($file);
        return null;
    }

    $GLOBALS['IMALIS_MEMORY_CACHE'][$key] = $data['payload'];
    return $data['payload'];
}

/**
 * Store a value in the cache with a specified TTL (in seconds).
 */
function cache_set(string $key, mixed $value, int $ttl = 300): void
{
    $GLOBALS['IMALIS_MEMORY_CACHE'][$key] = $value;

    cache_ensure_dir();
    $file = IMALIS_CACHE_DIR . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    $data = [
        'key' => $key,
        'created_at' => time(),
        'expires_at' => time() + $ttl,
        'payload' => $value,
    ];
    @file_put_contents($file, serialize($data), LOCK_EX);
}

/**
 * Delete a specific cached key.
 */
function cache_forget(string $key): void
{
    unset($GLOBALS['IMALIS_MEMORY_CACHE'][$key]);

    cache_ensure_dir();
    $file = IMALIS_CACHE_DIR . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    if (is_file($file)) {
        @unlink($file);
    }
}

/**
 * Clears all saved data for one user. Called when transactions, budgets, debts or invoices change.
 */
function cache_invalidate_user(int $userId): void
{
    if ($userId < 1) return;

    // Wipe memory cache for this user
    foreach (array_keys($GLOBALS['IMALIS_MEMORY_CACHE']) as $k) {
        if (str_contains($k, 'usr_' . $userId)) {
            unset($GLOBALS['IMALIS_MEMORY_CACHE'][$k]);
        }
    }

    cache_ensure_dir();

    // Wipe cached file keys tagged with user ID
    $files = glob(IMALIS_CACHE_DIR . DIRECTORY_SEPARATOR . '*.cache') ?: [];
    foreach ($files as $file) {
        $raw = @file_get_contents($file);
        if ($raw !== false && str_contains($raw, 'usr_' . $userId)) {
            @unlink($file);
        }
    }
}

/**
 * Invalidate user caches across all common financial mutations.
 */
function invalidate_financial_records_cache(int $userId): void
{
    cache_invalidate_user($userId);
}
