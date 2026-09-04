<?php

declare(strict_types=1);

use Newboard\App;
use Newboard\Autoload;
use Newboard\Http\Request;

$root = dirname(__DIR__);

require $root . '/src/Autoload.php';
Autoload::register($root . '/src');

// Serve uploaded files from data/uploads when using front controller only
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($uri) && str_starts_with($uri, '/uploads/')) {
    $file = $root . '/data' . $uri;
    $realBase = realpath($root . '/data/uploads');
    $realFile = realpath($file);
    if ($realBase && $realFile && str_starts_with($realFile, $realBase) && is_file($realFile)) {
        $mime = mime_content_type($realFile) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        readfile($realFile);
        exit;
    }
    http_response_code(404);
    echo 'Not found';
    exit;
}

try {
    $app = new App($root);
    $configBase = '';
    // optional local base_path
    $local = $root . '/config/local.php';
    if (is_readable($local)) {
        $cfg = require $local;
        if (is_array($cfg) && isset($cfg['base_path'])) {
            $configBase = (string) $cfg['base_path'];
        }
    }
    $request = Request::fromGlobals($configBase);
    $app->run($request)->send();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Bootstrap error: " . $e->getMessage() . "\n";
    echo "Run: php bin/install.php\n";
}
