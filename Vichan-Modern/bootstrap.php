<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80510 || PHP_VERSION_ID >= 80600) {
    throw new RuntimeException('Vichan Modern requires PHP 8.5.10 or a newer PHP 8.5 patch release.');
}
foreach (['pdo_sqlite', 'mbstring', 'gd', 'fileinfo'] as $extension) {
    if (!extension_loaded($extension)) {
        throw new RuntimeException('Missing required PHP extension: ' . $extension);
    }
}
if (!in_array('argon2id', password_algos(), true)) {
    throw new RuntimeException('PHP must include Argon2id password hashing support.');
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
date_default_timezone_set('UTC');
spl_autoload_register(static function (string $class): void {
    $prefix = 'VichanModern\\';
    if (str_starts_with($class, $prefix)) {
        $name = substr($class, strlen($prefix));
        if (preg_match('/\A[A-Za-z]+\z/D', $name)) {
            $file = __DIR__ . '/src/' . $name . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    }
});
set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $level)) {
        return false;
    }
    throw new ErrorException($message, 0, $level, $file, $line);
});
return new VichanModern\App(__DIR__, require __DIR__ . '/config.php');
