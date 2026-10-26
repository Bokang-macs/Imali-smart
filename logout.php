<?php
// Logs the user out and returns to the login page.
// Settings sends a POST with a CSRF token; the older sidebar link still works with GET.
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (request_method_is('POST')) {
    // Already signed out (for example the session expired): just go to the login page.
    if (!is_authenticated()) {
        redirect_to('login.php');
    }
    require_csrf();
}

// Translate the message before the session is destroyed so the chosen language is kept.
$signedOutMessage = t('signout_done');

logout_user();

// Start a fresh session so the login page can show a one-time "signed out" message.
start_app_session();
session_regenerate_id(true);
flash('auth_notice', $signedOutMessage);

redirect_to('login.php');
