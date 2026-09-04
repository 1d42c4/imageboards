<?php
declare(strict_types=1);

if (!defined('TINYIB_BOARD')) {
	die('');
}

require_once __DIR__ . '/flatfile/FlatFileDatabase.php';

try {
	$db = new TinyIB\Storage\FlatFileDatabase(TINYIB_FLATFILE_PATH);
} catch (Throwable $error) {
	fancyDie('Unable to initialize flat-file storage: ' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8'));
}