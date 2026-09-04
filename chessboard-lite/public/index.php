<?php

declare(strict_types=1);

use Chessboard\Application;
use Chessboard\Http\Request;
use Chessboard\Security\Session;

try {
    $config = require dirname(__DIR__) . '/src/bootstrap.php';
    $request = Request::fromGlobals($config->basePath());
    Session::start($config, $request->isSecure());
    $application = new Application($config);
    $application->handle($request)->send($request->method === 'HEAD');
} catch (Throwable $error) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Chessboard Lite could not start.\n\n";
    echo $error->getMessage() . "\n";
    exit;
}

