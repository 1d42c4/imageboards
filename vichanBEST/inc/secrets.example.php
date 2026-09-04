<?php
/**
 * Copy to secrets.php and fill in real values.
 * secrets.php is gitignored — never commit credentials.
 */

defined('TINYBOARD') or exit;

// PostgreSQL only
$config['db']['type']     = 'pgsql';
$config['db']['server']   = '127.0.0.1';
$config['db']['port']     = '5432';
$config['db']['database'] = 'vichan';
$config['db']['user']     = 'vichan';
$config['db']['password'] = 'change-me';

// 0 = allow HTTP logins (local dev); 1 = HTTPS only; 2 = HTTPS, ignore proxy headers
$config['cookies']['secure_login_only'] = 0;

// Generate with: php -r "echo 'OSSL.'.base64_encode(random_bytes(64)), PHP_EOL;"
$config['cookies']['salt'] = 'OSSL.CHANGE_ME';
// Used for secure tripcodes (Name#password). Must be long and secret.
$config['secure_trip_salt'] = 'OSSL.CHANGE_ME';
$config['secure_password_salt'] = 'OSSL.CHANGE_ME';

// Site-wide (not on the form): stay on thread after post; never bump if true
// $config['always_noko'] = true;
// $config['always_sage'] = false;

$config['debug'] = false;
$config['verbose_errors'] = false;
