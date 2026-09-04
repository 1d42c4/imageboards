<?php

declare(strict_types=1);

use Chessboard\Database;

/** @var Chessboard\Config $config */
$config = require __DIR__ . '/_cli.php';

$checks = [];
$checks['PHP 8.5+'] = PHP_VERSION_ID >= 80500
    ? 'ok (' . PHP_VERSION . ')'
    : 'failed (' . PHP_VERSION . ')';
foreach (['pdo_sqlite', 'mbstring', 'fileinfo', 'gd', 'session', 'json'] as $extension) {
    $checks["extension: {$extension}"] = extension_loaded($extension) ? 'ok' : 'missing';
}

$databasePath = $config->requireString('database_path');
$storagePath = $config->requireString('storage_path');
$keyPath = $config->requireString('key_path');
$checks['database file'] = is_file($databasePath) ? 'ok' : 'missing (run bin/install.php)';
$checks['application key'] = is_file($keyPath) ? 'ok' : 'missing (run bin/install.php)';
$checks['storage directory'] = is_dir($storagePath) && is_writable($storagePath)
    ? 'ok'
    : 'missing or not writable';

if (is_file($databasePath) && extension_loaded('pdo_sqlite')) {
    try {
        $database = new Database($config);
        $version = $database->pdo()->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn();
        $checks['database schema'] = $version === false || $version === null
            ? 'missing'
            : 'ok (' . $version . ')';
        $integrity = $database->pdo()->query('PRAGMA quick_check')->fetchColumn();
        $checks['SQLite integrity'] = $integrity === 'ok' ? 'ok' : (string) $integrity;
    } catch (Throwable $error) {
        $checks['database connection'] = 'failed: ' . $error->getMessage();
    }
}

$failed = false;
foreach ($checks as $label => $result) {
    $ok = str_starts_with($result, 'ok');
    $failed = $failed || !$ok;
    cli_output(sprintf("[%s] %-24s %s\n", $ok ? 'OK' : '!!', $label, $result));
}

exit($failed ? 1 : 0);
