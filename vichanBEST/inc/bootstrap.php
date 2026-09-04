<?php
/**
 * Application bootstrap — no Composer required.
 */
@define('TINYBOARD', 'xD');

require_once __DIR__ . '/autoload.php';

if (PHP_SAPI !== 'cli') {
	security_reject_bad_request();
	security_send_headers();
}
