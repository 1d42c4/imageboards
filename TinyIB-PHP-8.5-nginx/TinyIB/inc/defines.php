<?php
declare(strict_types=1);

if (!defined('TINYIB_BOARD')) {
	die('');
}

define('TINYIB_NEWTHREAD', '0');
define('TINYIB_INDEXPAGE', false);
define('TINYIB_RESPAGE', true);
define('TINYIB_LOCKFILE', 'tinyib.lock');
define('TINYIB_WORDBREAK_IDENTIFIER', '@!@TINYIB_WORDBREAK@!@');

// Account roles
define('TINYIB_SUPER_ADMINISTRATOR', 1);
define('TINYIB_ADMINISTRATOR', 2);
define('TINYIB_MODERATOR', 3);
define('TINYIB_DISABLED', 99);
