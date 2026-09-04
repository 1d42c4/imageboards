<?php

declare(strict_types=1);

use Chessboard\Config;

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo "Chessboard Lite's installer must be run with PHP's command-line executable.\n\n";
    echo "On Windows, open Command Prompt or PowerShell and run:\n";
    echo "C:\\path\\to\\php.exe C:\\tinyib\\ib1\\bin\\install.php\n\n";
    echo "Do not browse to install.php, double-click it, or use php-cgi.exe.\n";
    echo "Detected PHP SAPI: " . PHP_SAPI . "\n";
    exit(1);
}

/** @var Config $config */
$config = require dirname(__DIR__) . '/src/bootstrap.php';

function cli_output(string $message, bool $error = false): void
{
    $constant = $error ? 'STDERR' : 'STDOUT';
    if (defined($constant)) {
        $stream = constant($constant);
        if (is_resource($stream) && fwrite($stream, $message) !== false) {
            return;
        }
    }

    $uri = $error ? 'php://stderr' : 'php://stdout';
    if (@file_put_contents($uri, $message) === false) {
        echo $message;
    }
}

/**
 * @return never
 */
function fail(string $message, int $status = 1): void
{
    cli_output("Error: {$message}\n", true);
    exit($status);
}

function ensure_directory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
        fail("Unable to create directory: {$path}");
    }
}

function write_application_key(Config $config): bool
{
    $path = $config->requireString('key_path');
    if (is_file($path)) {
        return false;
    }

    ensure_directory(dirname($path));
    $key = bin2hex(random_bytes(32)) . "\n";
    if (file_put_contents($path, $key, LOCK_EX) === false) {
        fail("Unable to write application key: {$path}");
    }
    @chmod($path, 0600);

    return true;
}

function preferred_password_algorithm(): string|int|null
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
}

function secure_password_hash(string $password): string
{
    $hash = password_hash($password, preferred_password_algorithm());
    if ($hash === false) {
        fail('PHP was unable to hash the moderator password.');
    }

    return $hash;
}

function generated_password(): string
{
    return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
}

return $config;
