<?php
// Sends transactional email through Brevo. API credentials remain server-side.
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/external_api.php';

/**
 * Send one transactional email through Brevo.
 * Brevo requires the sender address to be verified in the account.
 */
function brevo_send_email(string $to, string $subject, string $text, string $html = ''): array
{
    $config = app_config();
    $key = trim((string) ($config['brevo_api_key'] ?? ''));
    $senderEmail = trim((string) ($config['brevo_sender_email'] ?? ''));
    $senderName = trim((string) ($config['brevo_sender_name'] ?? APP_NAME));

    if ($key === '' || $senderEmail === '') {
        return ['ok' => false, 'reason' => 'not_configured'];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($subject) > 180) {
        return ['ok' => false, 'reason' => 'invalid_input'];
    }

    $payload = [
        'sender' => ['email' => $senderEmail, 'name' => $senderName !== '' ? $senderName : APP_NAME],
        'to' => [['email' => $to]],
        'subject' => $subject,
        'textContent' => $text,
    ];
    if ($html !== '') {
        $payload['htmlContent'] = $html;
    }

    $result = external_json_post('https://api.brevo.com/v3/smtp/email', $payload, [
        'api-key: ' . $key,
    ]);
    if (!$result['ok']) {
        return ['ok' => false, 'reason' => 'provider_error'];
    }

    $messageId = trim((string) ($result['data']['messageId'] ?? ''));
    return ['ok' => true, 'provider_id' => $messageId, 'status' => 'accepted_not_delivered'];
}
