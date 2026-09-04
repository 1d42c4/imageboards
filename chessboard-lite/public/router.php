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
$file = $isAsset ? __DIR__ . $path : '';
if ($file !== '' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
