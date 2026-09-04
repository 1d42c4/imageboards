<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$local = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];

if (!is_array($local)) {
    throw new RuntimeException('config/local.php must return an array.');
}

$env = static function (string $name, mixed $default = null): mixed {
    $value = getenv($name);

    return $value === false || $value === '' ? $default : $value;
};

$basePath = '/' . trim((string) $env('CHESSBOARD_BASE_PATH', ''), '/');
$basePath = $basePath === '/' ? '' : $basePath;

$defaults = [
    'app_name' => (string) $env('CHESSBOARD_NAME', 'Chessboard Lite'),
    'tagline' => (string) $env('CHESSBOARD_TAGLINE', 'Talk chess. Share positions. Analyze together.'),
    'timezone' => (string) $env('CHESSBOARD_TIMEZONE', 'UTC'),
    'base_path' => $basePath,
    'debug' => filter_var($env('CHESSBOARD_DEBUG', false), FILTER_VALIDATE_BOOL),
    'database_path' => (string) $env('CHESSBOARD_DB_PATH', $root . '/var/chessboard.sqlite'),
    'storage_path' => (string) $env('CHESSBOARD_STORAGE_PATH', $root . '/var/uploads'),
    'log_path' => (string) $env('CHESSBOARD_LOG_PATH', $root . '/var/log/app.log'),
    'session_name' => (string) $env('CHESSBOARD_SESSION_NAME', 'chessboard_session'),
    'threads_per_page' => 15,
    'recent_replies' => 3,
    'max_body_length' => 12_000,
    'max_upload_bytes' => 5 * 1024 * 1024,
    'max_image_pixels' => 40_000_000,
    'thumbnail_size' => 320,
];

return array_replace($defaults, $local);

