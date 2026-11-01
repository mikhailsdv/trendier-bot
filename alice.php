<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use Trendier\AliceSkill;

try {
    $request = json_decode(file_get_contents('php://input'), true);
    $response = new AliceSkill()->handle(is_array($request) ? $request : []);
} catch (Throwable $e) {
    error_log((string) $e);
    $response = AliceSkill::response('Извините, что-то пошло не так. Попробуйте еще раз.');
}

header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
