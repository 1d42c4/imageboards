#!/usr/bin/php
<?php

/*
 *  rebuild.php - rebuilds all static files
 * 
 *  Command line arguments:
 *     -q, --quiet
 *          Suppress output. 
 *
 *     --quick
 *          Do not rebuild posts.
 *
 *     -b, --board <string>
 *          Rebuild only the specified board.
 *
 *     -f, --full
 *          Rebuild replies as well as threads (re-markup).
 *
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit('Not Found');
}

require dirname(__FILE__) . '/inc/cli.php';

$start = microtime(true);

// parse command line
$opts = getopt('qfb:', ['board:', 'quick', 'full', 'quiet']);
$options = [];
$global_locale = $config['locale'];

$options['board'] = isset($opts['board']) ? $opts['board'] : (isset($opts['b']) ? $opts['b'] : false);
$options['quiet'] = isset($opts['q']) || isset($opts['quiet']);
$options['quick'] = isset($opts['quick']);
$options['full'] = isset($opts['full']) || isset($opts['f']);

if(!$options['quiet'])
	echo "== Tinyboard + vichan {$config['version']} ==\n";	

if(!$options['quiet'])
	echo "Clearing template cache...\n";

clear_template_cache();

if(!$options['quiet'])
	echo "Regenerating catalog & board list...\n";
Vichan\Functions\Theme\rebuild_themes('all');

if(!$options['quiet'])
	echo "Generating Javascript file...\n";
buildJavascript();

$main_js = $config['file_script'];

$boards = listBoards() ?: [];

foreach ($boards as $_board_row) {
	if ($options['board'] && $_board_row['uri'] != $options['board']) {
		continue;
	}

	if (!$options['quiet']) {
		echo "Opening board /{$_board_row['uri']}/...\n";
	}
	// Reset locale to global locale
	$config['locale'] = $global_locale;
	if (!openBoard($_board_row['uri'])) {
		echo "Failed to open /{$_board_row['uri']}/\n";
		continue;
	}
	$config['try_smarter'] = false;

	if ($config['file_script'] != $main_js) {
		if (!$options['quiet']) {
			echo "Generating Javascript file...\n";
		}
		buildJavascript();
	}

	if (!$options['quiet']) {
		echo "Creating index pages...\n";
	}
	buildIndex();

	if ($options['quick']) {
		continue;
	}

	if ($options['full']) {
		$query = query(sprintf("SELECT `id` FROM ``posts_%s``", $_board_row['uri'])) or error(db_error());
		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			if (!$options['quiet']) {
				echo "Rebuilding #{$post['id']}...\n";
			}
			rebuildPost($post['id']);
		}
	}

	$query = query(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL", $_board_row['uri'])) or error(db_error());
	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		if (!$options['quiet']) {
			echo "Rebuilding #{$post['id']}...\n";
		}
		// Re-open board in case nested code clobbered global $board
		openBoard($_board_row['uri']);
		buildThread($post['id']);
	}

	if (function_exists('rebuildArchive') && archive_enabled()) {
		if (!$options['quiet']) {
			echo "Rebuilding archive...\n";
		}
		openBoard($_board_row['uri']);
		rebuildArchive();
	}
}

if (!$options['quiet']) {
	printf("Complete! Took %g seconds\n", microtime(true) - $start);
}

modLog('Rebuilt everything using tools/rebuild.php');

