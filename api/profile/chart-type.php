<?php
// Saves (POST) or reads (GET) the chart style the person chose: bar, line or pie.
// Every graph in the app follows this one choice.
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';

if (!is_authenticated()) {
    json_response(['ok' => false, 'error' => 'Please sign in to save your chart style.'], 401);
}
$user = current_user();
$userId = (int) ($user['id'] ?? 0);

if (request_method_is('GET')) {
    json_response(['ok' => true, 'chart_type' => get_chart_preference($userId), 'options' => array_keys(chart_style_options())]);
}
if (!request_method_is('POST')) {
    json_response(['ok' => false, 'error' => 'Use GET or POST.'], 405);
}

// Only accept requests that come from this same site.
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && strcasecmp((string) parse_url($origin, PHP_URL_HOST), (string) strtok((string) ($_SERVER['HTTP_HOST'] ?? ''), ':')) !== 0) {
    json_response(['ok' => false, 'error' => 'Request not allowed.'], 403);
}

$type = normalize_chart_type($_POST['chart_type'] ?? '');
if (!array_key_exists($type, chart_style_options())) {
    json_response(['ok' => false, 'error' => 'Choose bar, line or pie.'], 422);
}
if (!save_chart_preference($userId, $type)) {
    json_response(['ok' => false, 'error' => 'Your chart style could not be saved. Please try again.'], 500);
}
json_response(['ok' => true, 'chart_type' => $type]);
