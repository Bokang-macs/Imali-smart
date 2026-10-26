<?php
// Loads the main settings file.
declare(strict_types=1);

// Adds the file's last-changed time to CSS/JS links so browsers never show an old cached copy after an update.
if (!function_exists('asset_v')) {
    function asset_v(string $path): string
    {
        $file = dirname(__DIR__) . '/' . ltrim($path, '/');
        return is_file($file) ? (string) filemtime($file) : '1';
    }
}

require_once dirname(__DIR__) . '/config/app.php';
