<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';
$configuredBase = '/' . trim((string) (getenv('CHESSBOARD_BASE_PATH') ?: ''), '/');
$configuredBase = $configuredBase === '/' ? '' : $configuredBase;
if ($configuredBase !== '' && ($path === $configuredBase || str_starts_with($path, $configuredBase . '/'))) {
    $path = substr($path, strlen($configuredBase)) ?: '/';
}

$isAsset = preg_match('#^/assets/[a-zA-Z0-9._/-]+$#', $path) === 1 &&
    !str_contains($path, '..');
$isSetup = $path === '/setup.php';
$file = $isAsset || $isSetup ? __DIR__ . $path : '';
if ($file !== '' && is_file($file)) {
    if ($isSetup) {
        require $file;
        exit;
    }

    $mime = str_ends_with($file, '.css')
        ? 'text/css; charset=utf-8'
        : 'text/javascript; charset=utf-8';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($file));
    header('Cache-Control: public, max-age=3600');
    readfile($file);
    exit;
}

require __DIR__ . '/index.php';
