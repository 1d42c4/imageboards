<?php

declare(strict_types=1);

/**
 * CLI installer: schema, trip salt, admin user, starter board /b/.
 *
 *   php bin/install.php
 *   php bin/install.php --force
 */

$root = dirname(__DIR__);

require $root . '/src/Autoload.php';
Newboard\Autoload::register($root . '/src');

use Newboard\Config;
use Newboard\Database;
use Newboard\Security\PasswordHasher;

$force = in_array('--force', $argv, true);

$config = Config::load($root);
$dbPath = $config->string('db.path');
$dataDir = $config->string('paths.data');
$uploads = $config->string('paths.uploads');

foreach ([$dataDir, $uploads] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        fwrite(STDERR, "Cannot create {$dir}\n");
        exit(1);
    }
}

if (is_file($dbPath) && !$force) {
    fwrite(STDERR, "Database already exists: {$dbPath}\nUse --force to wipe and reinstall.\n");
    exit(1);
}

if (is_file($dbPath) && $force) {
    unlink($dbPath);
    @unlink($dbPath . '-wal');
    @unlink($dbPath . '-shm');
}

$schema = file_get_contents($root . '/schema/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Missing schema/schema.sql\n");
    exit(1);
}

$db = new Database($dbPath);
$db->pdo()->exec($schema);

$tripSalt = bin2hex(random_bytes(32));
$db->query('INSERT INTO meta (key, value) VALUES (?, ?)', ['trip_salt', $tripSalt]);
$db->query('INSERT INTO meta (key, value) VALUES (?, ?)', ['installed_at', (string) time()]);
$db->query('INSERT INTO meta (key, value) VALUES (?, ?)', ['schema_version', '1']);

$hash = (new PasswordHasher())->hash('password');
$db->query(
    'INSERT INTO mods (username, password_hash, type, boards, created_at) VALUES (?, ?, ?, ?, ?)',
    ['admin', $hash, 'admin', '*', time()]
);

$db->query(
    'INSERT INTO boards (uri, title, subtitle, created_at, require_password, require_approval, force_image_op)
     VALUES (?, ?, ?, ?, 0, 0, 0)',
    ['b', 'Random', 'Modern Tinyboard-style board (no IP collection)', time()]
);

// Protect data directory if served incorrectly
file_put_contents($dataDir . '/.htaccess', "Require all denied\n");
file_put_contents($dataDir . '/index.html', '');

echo "Installed.\n";
echo "  Database : {$dbPath}\n";
echo "  Board    : /b/\n";
echo "  Mod      : /mod  (admin / password) — change immediately\n";
echo "  Privacy  : no IP columns; session cooldown only\n";
echo "\nStart:\n  php -S 127.0.0.1:8080 -t public public/router.php\n";
