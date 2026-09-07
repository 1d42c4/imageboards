<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
/** @var \VichanModern\App $app */
$app = require dirname(__DIR__) . '/bootstrap.php';
$command = $argv[1] ?? 'help';
if (!$app->installed()) {
    throw new RuntimeException('Run bin/install.php first.');
}
switch ($command) {
    case 'rebuild': $app->builder->rebuild();
        echo "Static pages rebuilt.\n";
        break;
    case 'reset-password':
        $username = $argv[2] ?? 'admin';
        $password = (new Random\Randomizer(new Random\Engine\Secure()))->getBytesFromString('abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-!', 24);
        if ($app->db->one('SELECT id FROM staff WHERE username=?', [$username]) === null) {
            throw new RuntimeException('Staff account not found.');
        }
        $app->db->execute('UPDATE staff SET password_hash=?,version=version+1 WHERE username=?', [\VichanModern\Security::hashPassword($password), $username]);
        file_put_contents($app->config->path('var/first-login.txt'), "Username: $username\nPassword: $password\n", LOCK_EX);
        echo "Password reset. Read var/first-login.txt. All existing staff sessions for that account are invalidated.\n";
        break;
    case 'check':
        echo 'PHP: ' . PHP_VERSION . "\n";
        echo 'Database: ' . $app->db->query('PRAGMA integrity_check')->fetchColumn() . "\n";
        echo 'Posts: ' . $app->db->query('SELECT COUNT(*) FROM posts')->fetchColumn() . "\n";
        echo 'Files: ' . $app->db->query('SELECT COUNT(*) FROM files')->fetchColumn() . "\n";
        break;
    default: echo "Commands: rebuild | check | reset-password [username]\n";
}
