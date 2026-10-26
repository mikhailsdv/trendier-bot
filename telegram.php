<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use Trendier\TelegramBot;

if (!TelegramBot::isAuthorized()) {
    http_response_code(403);
    exit;
}

// Any non-200 answer makes Telegram resend the update again and again, so errors are only logged.
try {
    $update = json_decode(file_get_contents('php://input'), true);
    $reply = is_array($update) ? new TelegramBot()->handle($update) : null;
} catch (Throwable $e) {
    error_log((string) $e);
    $reply = null;
}

if ($reply !== null) {
    header('Content-Type: application/json');
    echo json_encode($reply, JSON_UNESCAPED_UNICODE);
}
