<?php

declare(strict_types=1);

use Chessboard\Database;

/** @var Chessboard\Config $config */
$config = require __DIR__ . '/_cli.php';
$database = new Database($config);
$applied = $database->migrate();

cli_output($applied === []
    ? "Database schema is already current.\n"
    : "Applied migrations: " . implode(', ', $applied) . "\n");
