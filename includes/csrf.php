<?php
// Protects forms from fake requests using a secret token.
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Returns the secret token for this session.
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['_csrf'];
}

// Prints the hidden form field that holds the token.
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// Checks that the token sent by the form is correct.
function verify_csrf(?string $token): bool
{
    return is_string($token) && isset($_SESSION['_csrf']) && hash_equals((string) $_SESSION['_csrf'], $token);
}

// Stops the request if the token is wrong.
function require_csrf(bool $isJson = false): void
{
    if (!verify_csrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        http_response_code(419);
        $wantsJson = $isJson || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Your form session expired. Please refresh the page and try again.']);
            exit;
        }
        exit('Your form expired. Please go back and try again.');
    }
}
