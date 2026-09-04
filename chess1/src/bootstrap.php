<?php

declare(strict_types=1);

use Chessboard\Config;

if (PHP_VERSION_ID < 80400) {
    throw new RuntimeException('Chessboard Lite requires PHP 8.4 or newer.');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Chessboard\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$settings = require dirname(__DIR__) . '/config/app.php';

return new Config($settings, dirname(__DIR__));

