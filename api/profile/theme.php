<?php
// Saves the theme choice (light, dark or system) for the logged-in user.
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('is_authenticated') || !is_authenticated()) {
    json_response(['ok' => false, 'message' => 'Guest user - preference stored in browser session.'], 200);
}

$user = current_user();
$theme = trim((string) ($_POST['theme'] ?? $_GET['theme'] ?? 'default'));

if (!in_array($theme, ['default', 'light', 'dark'], true)) {
    $theme = 'default';
}

if (function_exists('database_ready') && database_ready() && !empty($user['id'])) {
    try {
        // MySQL reports 0 affected rows when the value is unchanged, so check existence instead of rowCount().
        $exists = db()->prepare('SELECT 1 FROM user_settings WHERE user_id = :uid LIMIT 1');
        $exists->execute(['uid' => $user['id']]);
        if ($exists->fetchColumn()) {
            $stmt = db()->prepare('UPDATE user_settings SET theme_preference = :theme WHERE user_id = :uid');
        } else {
            $stmt = db()->prepare('INSERT INTO user_settings (user_id, theme_preference) VALUES (:uid, :theme)');
        }
        $stmt->execute(['uid' => $user['id'], 'theme' => $theme]);
        json_response(['ok' => true, 'theme' => $theme]);
    } catch (Throwable $e) {
        // Fallback for sqlite or older schema
        json_response(['ok' => true, 'theme' => $theme, 'note' => 'Stored locally']);
    }
}

json_response(['ok' => true, 'theme' => $theme]);
