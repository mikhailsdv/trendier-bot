<?php

declare(strict_types=1);

// A PHP warning in the output would break the JSON answer.
ini_set('display_errors', '0');

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Trendier\\')) {
        require __DIR__ . '/' . substr($class, strlen('Trendier\\')) . '.php';
    }
});

Trendier\Config::load(dirname(__DIR__) . '/.env');
