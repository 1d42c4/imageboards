<?php

declare(strict_types=1);
// Development server: only public files can be served. Production uses deploy/nginx.conf.
$root = __DIR__ . '/public';
$config = require __DIR__ . '/config.php';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = rawurldecode(explode('?', $uri, 2)[0]);
$base = (string) $config['base_path'];
if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
    $path = substr($path, strlen($base));
} elseif ($base !== '') {
    http_response_code(404);
    exit('Not found');
}
if (preg_match('~[\x00-\x1F\\\\]|(?:\A|/)\.{1,2}(?:/|\z)|(?:\A|/)\.~', $path)) {
    http_response_code(404);
    exit('Not found');
}
$path = '/' . ltrim($path, '/');
if (str_ends_with($path, '/')) {
    $path .= 'index.html';
}
$resolved = realpath($root . $path);
$public = realpath($root);
if ($resolved === false || $public === false || !str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', $public) . '/') || !is_file($resolved)) {
    http_response_code(404);
    exit('Not found');
}
$extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
if ($extension === 'php') {
    $endpoints = ['post.php', 'action.php', 'session.php', 'captcha.php', 'media.php', 'read.php', 'compose.php', 'search.php', 'mod.php'];
    if (!in_array(ltrim($path, '/'), $endpoints, true)) {
        http_response_code(404);
        exit('Not found');
    }
    require $resolved;
    exit;
}
$mime = match ($extension) {
    'html' => 'text/html; charset=UTF-8', 'css' => 'text/css; charset=UTF-8', 'js' => 'text/javascript; charset=UTF-8', 'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'ico' => 'image/x-icon', default => null
};
if ($mime === null) {
    http_response_code(404);
    exit('Not found');
}
require_once __DIR__ . '/src/Security.php';
\VichanModern\Security::headers(false);
header('Content-Type: ' . $mime);
header('Cache-Control: no-cache');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
    readfile($resolved);
}
