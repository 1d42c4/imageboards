<?php
/**
 * Built-in catalog generator (no theme system).
 * Writes {board}/catalog.html (+ optional RSS) whenever the board is rebuilt.
 */

defined('TINYBOARD') or exit;

/**
 * Rebuild catalog pages for one board or every board.
 *
 * @param string|false $board_uri Board URI, or false for all boards
 */
function rebuild_catalog($board_uri = false): void {
	global $config;

	if (isset($config['catalog']['enabled']) && !$config['catalog']['enabled']) {
		return;
	}

	$uris = [];
	if ($board_uri) {
		$uris[] = $board_uri;
	} else {
		foreach (listBoards() as $b) {
			$uris[] = $b['uri'];
		}
	}

	foreach ($uris as $uri) {
		catalog_build_board($uri);
	}
}

/**
 * Build catalog.html (and index.rss) for a single board.
 */
function catalog_build_board(string $board_name, $mod = false) {
	global $config, $board;

	if (!isset($board) || $board['uri'] != $board_name) {
		if (!openBoard($board_name)) {
			return false;
		}
	}

	// Ensure catalog JS is available on catalog pages
	if (!in_array('js/catalog.js', $config['additional_javascript'], true)) {
		// Don't mutate global list permanently for board pages — inject only for this render
		$extra_js = true;
	} else {
		$extra_js = false;
	}

	$recent_posts = [];

	// PostgreSQL-safe: correlate subqueries on outer table alias (not SELECT alias)
	global $pdo;
	$bn = $pdo->quote($board_name);
	$pub = function_exists('sql_posts_public') ? sql_posts_public('p') : '1=1';
	$pub_r = function_exists('sql_posts_public') ? sql_posts_public() : '1=1';
	$query = query(sprintf(
		"SELECT p.*, p.id AS thread_id,
			(SELECT COUNT(id) FROM ``posts_%s`` WHERE thread = p.id AND %s) AS reply_count,
			(SELECT COALESCE(SUM(num_files), 0) FROM ``posts_%s`` WHERE thread = p.id AND num_files IS NOT NULL AND %s) AS image_count,
			%s AS board
		 FROM ``posts_%s`` p WHERE p.thread IS NULL AND %s ORDER BY p.bump DESC",
		$board_name,
		$pub_r,
		$board_name,
		$pub_r,
		$bn,
		$board_name,
		$pub
	)) or error(db_error());

	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		if ($mod) {
			$post['link'] = $config['root'] . $config['file_mod'] . '?/' . $board['dir'] . $config['dir']['res'] . link_for($post);
		} else {
			$post['link'] = $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($post);
		}
		$post['board_name'] = $board['name'] ?? $board['title'] ?? $board_name;

		if ($post['embed'] && preg_match('/^https?:\/\/(\w+\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9\-_]{10,11})(&.+)?$/i', $post['embed'], $matches)) {
			$post['youtube'] = $matches[2];
		}

		if (isset($post['files']) && $post['files']) {
			$files = json_decode($post['files']);
			if ($files && $files[0]) {
				if ($files[0]->file == 'deleted') {
					$post['file'] = $config['image_deleted'];
					if (count($files) > 1) {
						foreach ($files as $file) {
							if ($file === $files[0] || $file->file == 'deleted') {
								continue;
							}
							$post['file'] = $config['uri_thumb'] . $file->thumb;
							break;
						}
					}
				} elseif ($files[0]->thumb == 'spoiler') {
					$post['file'] = $config['root'] . $config['spoiler_image'];
				} else {
					$post['file'] = $config['uri_thumb'] . $files[0]->thumb;
				}
			}
		} else {
			$post['file'] = $config['root'] . $config['image_deleted'];
		}

		if (empty($post['image_count'])) {
			$post['image_count'] = 0;
		}
		$post['pubdate'] = date('r', (int)$post['time']);
		$recent_posts[] = $post;
	}

	$saved_js = $config['additional_javascript'];
	if ($extra_js) {
		$config['additional_javascript'][] = 'js/catalog.js';
	}

	$link = $mod
		? $config['root'] . $config['file_mod'] . '?/' . $board['dir']
		: $config['root'] . $board['dir'];

	$title = $config['catalog']['title'] ?? 'Catalog';
	$subtitle = $config['catalog']['subtitle'] ?? '';

	$html = Element('catalog.html', [
		'settings' => ['title' => $title, 'subtitle' => $subtitle],
		'config' => $config,
		'boardlist' => createBoardlist($mod),
		'recent_posts' => $recent_posts,
		'board' => $board_name,
		'link' => $link,
		'mod' => $mod,
	]);

	$config['additional_javascript'] = $saved_js;

	if ($mod) {
		return $html;
	}

	file_write($config['dir']['home'] . $board_name . '/' . ($config['file_catalog'] ?? 'catalog.html'), $html);

	if (!empty($config['catalog']['rss'])) {
		file_write(
			$config['dir']['home'] . $board_name . '/index.rss',
			Element('catalog_rss.xml', [
				'config' => $config,
				'recent_posts' => $recent_posts,
				'board' => $board,
			])
		);
	}

	return true;
}
