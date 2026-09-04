<?php

/*
 *  Instance Configuration
 *  ----------------------
 *  Edit this file and not config.php for imageboard configuration.
 *
 *  You can copy values from config.php (defaults) and paste them here.
 */



	// Database stuff (override in secrets.php — never commit real credentials)
	$config['db']['type']		= 'pgsql';
	$config['db']['server']		= '127.0.0.1';
	$config['db']['port']		= '5432';
	$config['db']['user']		= '';
	$config['db']['password']	= '';
	$config['db']['database']	= '';

	//$config['root']				= '/';

	// secrets.php is gitignored / local-only; holds credentials and salts
	if (is_readable(__DIR__ . '/secrets.php')) {
		require __DIR__ . '/secrets.php';
	}

	// --- Top board / site links (manual — rebuild pages after edits) ---
	// Shown on every board page (index, thread, archive, catalog).
	// Creating a board in mod does NOT add a link; edit this list yourself.
	// Format: 'Label' => 'url'  (relative path or full https:// URL)
	// TEMP test strip: a–z then 1–10 (replace with real links when ready)
	$config['boards'] = [];
	foreach (range('a', 'z') as $letter) {
		$config['boards'][$letter] = '/' . $letter . '/';
	}
	for ($n = 1; $n <= 10; $n++) {
		$config['boards'][(string)$n] = '/' . $n . '/';
	}

	// --- Dev / testing: no post throttles ---
	// Remove or comment this block before production.
	$config['filters'] = [];                 // no flood filters
	$config['flood_time'] = 0;
	$config['flood_time_ip'] = 0;
	$config['flood_time_same'] = 0;
	$config['max_links'] = 9999;
	$config['max_cites'] = 9999;
	$config['captcha']['provider'] = false;
	$config['report_captcha'] = false;

