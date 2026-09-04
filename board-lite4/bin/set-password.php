<?php

declare(strict_types=1);

use Chessboard\Database;
use Chessboard\Repository\ModerationRepository;

/** @var Chessboard\Config $config */
$config = require __DIR__ . '/_cli.php';

$username = trim((string) ($argv[1] ?? getenv('CHESSBOARD_ADMIN_USER') ?: 'admin'));
$moderation = new ModerationRepository(new Database($config));
$user = $moderation->moderatorByUsername($username);
if ($user === null) {
    fail("Moderator not found: {$username}");
}

$configuredPassword = getenv('CHESSBOARD_ADMIN_PASSWORD');
if ($configuredPassword !== false && $configuredPassword !== '' && strlen($configuredPassword) < 12) {
    fail('CHESSBOARD_ADMIN_PASSWORD must contain at least 12 characters.');
}
$password = $configuredPassword === false || $configuredPassword === ''
    ? generated_password()
    : $configuredPassword;

$database = new Database($config);
$query = $database->pdo()->prepare(
    'UPDATE moderators SET password_hash = :password_hash WHERE id = :id'
);
$query->execute([
    'password_hash' => secure_password_hash($password),
    'id' => (int) $user['id'],
]);

cli_output("Password updated for {$username}.\n");
if ($configuredPassword === false || $configuredPassword === '') {
    cli_output("New password: {$password}\n");
}
