<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
/** @var \VichanModern\App $app */
$app = require dirname(__DIR__) . '/bootstrap.php';
if ($app->installed()) {
    $app->builder->rebuild();
    echo "Existing installation ready.\n";
    exit;
}
$app->db->pdo->exec((string) file_get_contents(dirname(__DIR__) . '/schema.sql'));
$username = getenv('VICHAN_ADMIN_USER') ?: 'admin';
$password = getenv('VICHAN_ADMIN_PASSWORD') ?: (new Random\Randomizer(new Random\Engine\Secure()))->getBytesFromString('abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-!', 24);
if (!preg_match('/\A[a-zA-Z0-9_-]{3,32}\z/D', $username)) {
    throw new RuntimeException('Invalid administrator username.');
}
\VichanModern\Input::password($password);
$app->db->transaction(function () use ($app, $username, $password): void {
    $app->db->execute('INSERT INTO staff(username,password_hash,role) VALUES(?,?,?)', [$username, \VichanModern\Security::hashPassword($password), 'admin']);
    $app->db->execute('INSERT INTO boards(slug,title,subtitle) VALUES(?,?,?)', ['chess', 'Chess', 'Games, articles, analysis and conversation.']);
});
$app->builder->rebuild();
// First-run credentials stay outside the public web root; remove this file after changing the password.
file_put_contents($app->config->path('var/first-login.txt'), "Vichan Modern administrator\nUsername: " . $username . "\nPassword: " . $password . "\n\nSign in at /mod.php and change your password. Then delete this file.\n", LOCK_EX);
echo "Installed. First-login details are in var/first-login.txt.\n";
