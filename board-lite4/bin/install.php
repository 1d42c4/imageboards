<?php

declare(strict_types=1);

use Chessboard\Database;
use Chessboard\Repository\BoardRepository;
use Chessboard\Repository\ModerationRepository;

/** @var Chessboard\Config $config */
$config = require __DIR__ . '/_cli.php';

$requiredExtensions = ['pdo_sqlite', 'mbstring', 'fileinfo', 'gd', 'session', 'json'];
$missing = array_values(array_filter(
    $requiredExtensions,
    static fn (string $extension): bool => !extension_loaded($extension),
));
if ($missing !== []) {
    fail('Missing PHP extensions: ' . implode(', ', $missing));
}

ensure_directory(dirname($config->requireString('database_path')));
ensure_directory($config->requireString('storage_path') . '/original');
ensure_directory($config->requireString('storage_path') . '/thumb');
ensure_directory(dirname($config->requireString('log_path')));

$database = new Database($config, true);
$migrations = $database->migrate();
$boards = new BoardRepository($database);
$moderation = new ModerationRepository($database);

$boardCreated = false;
if (!$boards->exists('chess')) {
    $boards->create(
        'chess',
        'Chess',
        'Chess discussion, game analysis, puzzles, openings, and tournament talk.',
    );
    $boardCreated = true;
}

$username = trim((string) (getenv('CHESSBOARD_ADMIN_USER') ?: 'admin'));
if (!preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $username)) {
    fail('CHESSBOARD_ADMIN_USER must be 3–32 letters, numbers, underscores, or hyphens.');
}

$moderatorCreated = false;
$password = null;
$generatedPassword = false;
if ($moderation->moderatorByUsername($username) === null) {
    $configuredPassword = getenv('CHESSBOARD_ADMIN_PASSWORD');
    if ($configuredPassword !== false && $configuredPassword !== '' && strlen($configuredPassword) < 12) {
        fail('CHESSBOARD_ADMIN_PASSWORD must contain at least 12 characters.');
    }

    $generatedPassword = $configuredPassword === false || $configuredPassword === '';
    $password = $generatedPassword ? generated_password() : $configuredPassword;
    $moderation->createModerator($username, secure_password_hash($password));
    $moderatorCreated = true;
}

cli_output("Chessboard Lite is installed.\n");
cli_output($migrations === []
    ? "Database schema: already current\n"
    : "Database schema: applied " . implode(', ', $migrations) . "\n");
cli_output($boardCreated ? "Default /chess/ board: created\n" : "Default /chess/ board: already present\n");
cli_output($moderatorCreated
    ? "Moderator {$username}: created\n"
    : "Moderator {$username}: already present\n");

if ($moderatorCreated && $generatedPassword && $password !== null) {
    cli_output("\nSave these credentials now; the generated password is not stored in plain text.\n");
    cli_output("Username: {$username}\nPassword: {$password}\n");
}

cli_output("\nModerator sign-in: " . $config->basePath() . "/mod/login\n");
