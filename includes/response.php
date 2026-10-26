<?php
// Helper that sends JSON answers.
declare(strict_types=1);

// Sends a JSON answer and stops.
function json_response(array $payload, int $status = 200): never
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
