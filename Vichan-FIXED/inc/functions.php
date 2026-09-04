<?php

/*
 *  Copyright (c) 2010-2014 Tinyboard Development Group
 */


if (realpath($_SERVER['SCRIPT_FILENAME']) == str_replace('\\', '/', __FILE__)) {
	// You cannot request this file directly.
	exit;
}

$microtime_start = microtime(true);

// the user is not currently logged in as a moderator
$mod = false;

register_shutdown_function('fatal_error_handler');
mb_internal_encoding('UTF-8');
loadConfig();

function init_locale($locale, $error = 'error') {
	// English-only slim fork: identity translator (no .po/.mo tree).
	if (!function_exists('_')) {
		function _(string $message): string {
			return $message;
		}
	}
	if (function_exists('setlocale')) {
		@setlocale(LC_ALL, $locale, $locale . '.UTF-8', 'en_US.UTF-8', 'C');
	}
}
$current_locale = 'en';


function loadConfig() {
	global $board, $config, $debug, $__version, $microtime_start, $current_locale, $events;

	$error = function_exists('error') ? 'error' : 'basic_error_function_because_the_other_isnt_loaded_yet';

	$boardsuffix = isset($board['uri']) ? $board['uri'] : '';

	if (file_exists('tmp/cache/cache_config.php')) {
		require_once('tmp/cache/cache_config.php');
	}


	if (isset($config['cache_config']) &&
		$config['cache_config'] &&
		$config = Cache::get('config_' . $boardsuffix))
	{
		$events = Cache::get('events_' . $boardsuffix );

		define_groups();

		if (file_exists('inc/instance-functions.php')) {
			require_once('inc/instance-functions.php');
		}

		if ($config['locale'] != $current_locale) {
					$current_locale = $config['locale'];
					init_locale($config['locale'], $error);
			}
	} else {
		$config = array();

		reset_events();

		$arrays = array(
			'db',
			'cache',
			'lock',
			'cookies',
			'error',
			'dir',
			'mod',
			'wordfilters',
			'custom_capcode',
			'custom_tripcode',
			'remote',
			'allowed_ext',
			'allowed_ext_files',
			'file_icons',
			'footer',
			'stylesheets',
			'additional_javascript',
			'markup',
			'custom_pages',
			'dashboard_links'
		);

		foreach ($arrays as $key) {
			$config[$key] = array();
		}

		if (!file_exists('inc/instance-config.php'))
			$error('vichan is not configured! Create inc/instance-config.php.');

		// Initialize locale as early as possible

		// Those calls are expensive. Unfortunately, our cache system is not initialized at this point.
		// So, we may store the locale in a tmp/ filesystem.

		if (file_exists($fn = 'tmp/cache/locale_' . $boardsuffix ) ) {
			$config['locale'] = @file_get_contents($fn);
		}
		else {
			$config['locale'] = 'en';

			$configstr = file_get_contents('inc/secrets.php');

			if (isset($board['dir']) && file_exists($board['dir'] . '/config.php')) {
				$configstr .= file_get_contents($board['dir'] . '/config.php');
			}
			$matches = array();
			preg_match_all('/[^\/#*]\$config\s*\[\s*[\'"]locale[\'"]\s*\]\s*=\s*([\'"])(.*?)\1/', $configstr, $matches);
			if ($matches && isset ($matches[2]) && $matches[2]) {
				$matches = $matches[2];
				$config['locale'] = $matches[count($matches)-1];
			}

			@file_put_contents($fn, $config['locale']);
		}

		if ($config['locale'] != $current_locale) {
			$current_locale = $config['locale'];
			init_locale($config['locale'], $error);
		}

		require 'inc/config.php';

		require 'inc/instance-config.php';

		if (isset($board['dir']) && file_exists($board['dir'] . '/config.php')) {
			require $board['dir'] . '/config.php';
		}

		if ($config['locale'] != $current_locale) {
			$current_locale = $config['locale'];
			init_locale($config['locale'], $error);
		}

		if (!isset($config['global_message']))
			$config['global_message'] = false;

		if (!isset($config['post_url']))
			$config['post_url'] = $config['root'] . $config['file_post'];


		if (!isset($config['cookies']['path']))
			$config['cookies']['path'] = &$config['root'];

		if (!isset($config['dir']['static']))
			$config['dir']['static'] = $config['root'] . 'static/';

		if (!isset($config['image_blank']))
			$config['image_blank'] = $config['dir']['static'] . 'blank.gif';

		if (!isset($config['image_sticky']))
			$config['image_sticky'] = $config['dir']['static'] . 'sticky.gif';
		if (!isset($config['image_locked']))
			$config['image_locked'] = $config['dir']['static'] . 'locked.gif';
		if (!isset($config['image_bumplocked']))
			$config['image_bumplocked'] = $config['dir']['static'] . 'sage.gif';
		if (!isset($config['image_deleted']))
			$config['image_deleted'] = $config['dir']['static'] . 'deleted.png';

		if (isset($board)) {
			if (!isset($config['uri_thumb']))
				$config['uri_thumb'] = $config['root'] . $board['dir'] . $config['dir']['thumb'];
			elseif (isset($board['dir']))
				$config['uri_thumb'] = sprintf($config['uri_thumb'], $board['dir']);

			if (!isset($config['uri_img']))
				$config['uri_img'] = $config['root'] . $board['dir'] . $config['dir']['img'];
			elseif (isset($board['dir']))
				$config['uri_img'] = sprintf($config['uri_img'], $board['dir']);
		}

		if (!isset($config['uri_stylesheets']))
			$config['uri_stylesheets'] = $config['root'] . 'stylesheets/';

		if (!isset($config['url_stylesheet']))
			$config['url_stylesheet'] = $config['uri_stylesheets'] . 'style.css';
		if (!isset($config['url_javascript']))
			$config['url_javascript'] = $config['root'] . $config['file_script'];
		if (!isset($config['additional_javascript_url']))
			$config['additional_javascript_url'] = $config['root'];
		if (!isset($__version))
			$__version = file_exists('.installed') ? trim(file_get_contents('.installed')) : false;
		$config['version'] = $__version;



	}
	// Effectful config processing below:

	date_default_timezone_set($config['timezone']);

	if ($config['root_file']) {
		chdir($config['root_file']);
	}

	if ($config['verbose_errors']) {
		set_error_handler('verbose_error_handler');
		error_reporting($config['deprecation_errors'] ? E_ALL : E_ALL & ~E_DEPRECATED);
		ini_set('display_errors', true);
		ini_set('html_errors', false);
	} else {
		ini_set('display_errors', false);
	}

	if ($config['syslog'])
		openlog('tinyboard', LOG_ODELAY, LOG_SYSLOG); // open a connection to sysem logger

	if ($config['cache']['enabled'])
		require_once 'inc/cache.php';

	event('load-config');

	if ($config['cache_config'] && !isset ($config['cache_config_loaded'])) {
		file_put_contents('tmp/cache/cache_config.php', '<?php '.
			'$config = array();'.
			'$config[\'cache\'] = '.var_export($config['cache'], true).';'.
			'$config[\'cache_config\'] = true;'.
			'$config[\'debug\'] = '.var_export($config['debug'], true).';'.
			'require_once(\'inc/cache.php\');'
		);

		$config['cache_config_loaded'] = true;

		Cache::set('config_'.$boardsuffix, $config);
		Cache::set('events_'.$boardsuffix, $events);
	}

	if (is_array($config['anonymous']))
		$config['anonymous'] = $config['anonymous'][array_rand($config['anonymous'])];

	if ($config['debug']) {
		if (!isset($debug)) {
			$debug = array(
				'sql' => array(),
				'exec' => array(),
				'purge' => array(),
				'cached' => array(),
				'write' => array(),
				'time' => array(
					'db_queries' => 0,
					'exec' => 0,
				),
				'start' => $microtime_start,
				'start_debug' => microtime(true)
			);
			$debug['start'] = $microtime_start;
		}
	}
}

function basic_error_function_because_the_other_isnt_loaded_yet($message, $priority = true) {
	global $config;

	if (!empty($config['syslog']) && $priority !== false && function_exists('_syslog')) {
		_syslog($priority !== true ? $priority : LOG_NOTICE, $message);
	}

	// Yes, this is horrible.
	die('<!DOCTYPE html><html><head><title>Error</title>' .
		'<style type="text/css">' .
			'body{text-align:center;font-family:arial, helvetica, sans-serif;font-size:10pt;}' .
			'p{padding:0;margin:20px 0;}' .
			'p.c{font-size:11px;}' .
		'</style></head>' .
		'<body><h2>Error</h2>' . $message . '<hr/>' .
		'<p class="c">This alternative error page is being displayed because the other couldn\'t be found or hasn\'t loaded yet.</p></body></html>');
}

function fatal_error_handler() {
	if ($error = error_get_last()) {
		if ($error['type'] == E_ERROR) {
			if (function_exists('error')) {
				error('Caught fatal error: ' . $error['message'] . ' in <strong>' . $error['file'] . '</strong> on line ' . $error['line'], LOG_ERR);
			} else {
				basic_error_function_because_the_other_isnt_loaded_yet('Caught fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'], LOG_ERR);
			}
		}
	}
}

function _syslog($priority, $message) {
	if (isset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'])) {
		$suffix = 'request: "' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . '"';
		syslog($priority, $message . ' - ' . $suffix);
	} else {
		syslog($priority, $message);
	}
}

function verbose_error_handler($errno, $errstr, $errfile, $errline) {
	global $config;

	if (error_reporting() == 0)
		return false; // Looks like this warning was suppressed by the @ operator.
	if ($errno == E_DEPRECATED && !$config['deprecation_errors'])
		return false;

	error(utf8tohtml($errstr), true, array(
		'file' => $errfile . ':' . $errline,
		'errno' => $errno,
		'error' => $errstr,
		'backtrace' => array_slice(debug_backtrace(), 1)
	));
}

function define_groups() {
	global $config;

	foreach ($config['mod']['groups'] as $group_value => $group_name) {
		$group_name = strtoupper($group_name);
		if(!defined($group_name)) {
			define($group_name, $group_value);
		}
	}

	ksort($config['mod']['groups']);
}

function sprintf3($str, $vars, $delim = '%') {
	$replaces = array();
	foreach ($vars as $k => $v) {
		$replaces[$delim . $k . $delim] = $v;
	}
	return str_replace(array_keys($replaces),
					   array_values($replaces), $str);
}

function mb_substr_replace($string, $replacement, $start, $length) {
	return mb_substr($string, 0, $start) . $replacement . mb_substr($string, $start + $length);
}

function setupBoard($array) {
	global $board, $config, $mod;

	$board = [
		'uri' => $array['uri'],
		'title' => $array['title'],
		'subtitle' => $array['subtitle'] ?? null,
		'post_password' => $array['post_password'] ?? null,
		'require_approval' => !empty($array['require_approval']) ? 1 : 0,
	];

	// older versions
	$board['name'] = &$board['title'];

	$board['dir'] = sprintf($config['board_path'], $board['uri']);
	$board['url'] = sprintf($config['board_abbreviation'], $board['uri']);

	loadConfig();

	if (!file_exists($board['dir']))
		@mkdir($board['dir'], 0777) or error("Couldn't create " . $board['dir'] . ". Check permissions.", true);
	if (!file_exists($board['dir'] . $config['dir']['img']))
		@mkdir($board['dir'] . $config['dir']['img'], 0777)
			or error("Couldn't create " . $board['dir'] . $config['dir']['img'] . ". Check permissions.", true);
	if (!file_exists($board['dir'] . $config['dir']['thumb']))
		@mkdir($board['dir'] . $config['dir']['thumb'], 0777)
			or error("Couldn't create " . $board['dir'] . $config['dir']['img'] . ". Check permissions.", true);
	if (!file_exists($board['dir'] . $config['dir']['res']))
		@mkdir($board['dir'] . $config['dir']['res'], 0777)
			or error("Couldn't create " . $board['dir'] . $config['dir']['img'] . ". Check permissions.", true);

	if (function_exists('board_moderation_ensure_schema')) {
		board_moderation_ensure_schema($board['uri']);
	}
	if (function_exists('archive_setup_board')) {
		archive_setup_board();
	}
}

function openBoard($uri) {
	global $config, $build_pages, $board;

	if ($config['try_smarter'])
		$build_pages = array();

	// And what if we don't really need to change a board we have opened?
	if (isset ($board) && isset ($board['uri']) && $board['uri'] == $uri) {
		return true;
	}

	$b = getBoardInfo($uri);
	if ($b) {
		setupBoard($b);

		if (function_exists('after_open_board')) {
			after_open_board();
		}

		return true;
	}
	return false;
}

function getBoardInfo($uri) {
	global $config;

	if ($config['cache']['enabled'] && ($board = cache::get('board_' . $uri))) {
		return $board;
	}

	$query = prepare("SELECT * FROM ``boards`` WHERE `uri` = :uri LIMIT 1");
	$query->bindValue(':uri', $uri);
	$query->execute() or error(db_error($query));

	if ($board = $query->fetch(PDO::FETCH_ASSOC)) {
		if ($config['cache']['enabled'])
			cache::set('board_' . $uri, $board);
		return $board;
	}

	return false;
}

function purge($uri) {
	global $config, $debug;

	// Fix for Unicode
	$uri = rawurlencode($uri);

	$noescape = "/!~*()+:";
	$noescape = preg_split('//', $noescape);
	$noescape_url = array_map("rawurlencode", $noescape);
	$uri = str_replace($noescape_url, $noescape, $uri);

	$uri = $config['root'] . $uri;

	if ($config['debug']) {
		$debug['purge'][] = $uri;
	}

	foreach ($config['purge'] as &$purge) {
		$host = &$purge[0];
		$port = &$purge[1];
		$http_host = isset($purge[2]) ? $purge[2] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
		$request = "PURGE {$uri} HTTP/1.1\r\nHost: {$http_host}\r\nUser-Agent: Tinyboard\r\nConnection: Close\r\n\r\n";
		if ($fp = fsockopen($host, $port, $errno, $errstr, $config['purge_timeout'])) {
			fwrite($fp, $request);
			fclose($fp);
		} else {
			// Cannot connect?
			error('Could not PURGE for ' . $host);
		}
	}
}

function file_write($path, $data, $simple = false, $skip_purge = false) {
	global $config, $debug;

	// Ensure parent directory exists (e.g. board/res/)
	$dir = dirname($path);
	if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
		if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
			error('Unable to create directory for writing: ' . $dir);
		}
	}

	if (!$fp = fopen($path, $simple ? 'w' : 'c'))
		error('Unable to open file for writing: ' . $path);

	// File locking
	if (!$simple && !flock($fp, LOCK_EX)) {
		error('Unable to lock file: ' . $path);
	}

	// Truncate file
	if (!$simple && !ftruncate($fp, 0))
		error('Unable to truncate file: ' . $path);

	// Write data
	if (($bytes = fwrite($fp, $data)) === false)
		error('Unable to write to file: ' . $path);

	// Unlock
	if (!$simple)
		flock($fp, LOCK_UN);

	// Close
	if (!fclose($fp))
		error('Unable to close file: ' . $path);

	/**
	 * Create gzipped file.
	 *
	 * When writing into a file foo.bar and the size is larger or equal to 1
	 * KiB, this also produces the gzipped version foo.bar.gz
	 *
	 * This is useful with nginx with gzip_static on.
	 */
	if ($config['gzip_static']) {
		$gzpath = "$path.gz";

		if ($bytes & ~0x3ff) {  // if ($bytes >= 1024)
			if (file_put_contents($gzpath, gzencode($data), $simple ? 0 : LOCK_EX) === false)
				error("Unable to write to file: $gzpath");
			//if (!touch($gzpath, filemtime($path), fileatime($path)))
			//	error("Unable to touch file: $gzpath");
		}
		else {
			@unlink($gzpath);
		}
	}

	if (!$skip_purge && isset($config['purge'])) {
		// Purge cache
		if (basename($path) == $config['file_index']) {
			// Index file (/index.html); purge "/" as well
			$uri = dirname($path);
			// root
			if ($uri == '.')
				$uri = '';
			else
				$uri .= '/';
			purge($uri);
		}
		purge($path);
	}

	if ($config['debug']) {
		$debug['write'][] = $path . ': ' . $bytes . ' bytes';
	}

	event('write', $path);
}

function file_unlink($path) {
	global $config, $debug;

	if ($config['debug']) {
		if (!isset($debug['unlink']))
			$debug['unlink'] = array();
		$debug['unlink'][] = $path;
	}

	if (file_exists($path)) {
		$ret = @unlink($path);
	} else {
		$ret = true;
	}

	if ($config['gzip_static']) {
		$gzpath = "$path.gz";

		if (file_exists($gzpath)) {
			@unlink($gzpath);
		}
	}

	if (isset($config['purge']) && $path[0] != '/' && isset($_SERVER['HTTP_HOST'])) {
		// Purge cache
		if (basename($path) == $config['file_index']) {
			// Index file (/index.html); purge "/" as well
			$uri = dirname($path);
			// root
			if ($uri == '.')
				$uri = '';
			else
				$uri .= '/';
			purge($uri);
		}
		purge($path);
	}

	event('unlink', $path);

	return $ret;
}

/** Unlink board image + thumb from a files[] JSON entry (object or array). */
function unlink_board_file_entry($entry): void {
	global $board, $config;
	if (!$entry) {
		return;
	}
	$e = (array)$entry;
	if (!empty($e['file']) && $e['file'] !== 'deleted') {
		file_unlink($board['dir'] . $config['dir']['img'] . $e['file']);
	}
	if (!empty($e['thumb']) && !in_array($e['thumb'], ['spoiler', 'deleted', 'file'], true)) {
		file_unlink($board['dir'] . $config['dir']['thumb'] . $e['thumb']);
	}
}

function hasPermission($action = null, $board = null, $_mod = null) {
	global $config;

	if (isset($_mod))
		$mod = &$_mod;
	else
		global $mod;

	if (!is_array($mod))
		return false;

	if (isset($action) && $mod['type'] < $action)
		return false;

	if (!isset($board) || $config['mod']['skip_per_board'])
		return true;

	if (!isset($mod['boards']))
		return false;

	if (!in_array('*', $mod['boards']) && !in_array($board, $mod['boards']))
		return false;

	return true;
}

function listBoards($just_uri = false) {
	global $config;

	$just_uri ? $cache_name = 'all_boards_uri' : $cache_name = 'all_boards';

	if ($config['cache']['enabled'] && ($boards = cache::get($cache_name)))
		return $boards;

	if (!$just_uri) {
		$query = query("SELECT * FROM ``boards`` ORDER BY `uri`") or error(db_error());
		$boards = $query->fetchAll();
	} else {
		$boards = array();
		$query = query("SELECT `uri` FROM ``boards``") or error(db_error());
		while ($board = $query->fetchColumn()) {
			$boards[] = $board;
		}
	}

	if ($config['cache']['enabled'])
		cache::set($cache_name, $boards);

	return $boards;
}

function post(array $post) {
	global $pdo, $board;
	$query = prepare(sprintf("INSERT INTO ``posts_%s`` VALUES ( NULL, :thread, :subject, :email, :name, :trip, :capcode, :body, :body_nomarkup, :time, :time, :files, :num_files, :filehash, :password, :sticky, :locked, :cycle, 0, :embed, :slug, :pending)", $board['uri']));

	// Basic stuff
	if (!empty($post['subject'])) {
		$query->bindValue(':subject', $post['subject']);
	} else {
		$query->bindValue(':subject', null, PDO::PARAM_NULL);
	}

	if (!empty($post['email'])) {
		$query->bindValue(':email', $post['email']);
	} else {
		$query->bindValue(':email', null, PDO::PARAM_NULL);
	}

	if (!empty($post['trip'])) {
		$query->bindValue(':trip', $post['trip']);
	} else {
		$query->bindValue(':trip', null, PDO::PARAM_NULL);
	}

	$query->bindValue(':name', $post['name']);
	$query->bindValue(':body', $post['body']);
	$query->bindValue(':body_nomarkup', $post['body_nomarkup']);
	$query->bindValue(':time', isset($post['time']) ? $post['time'] : time(), PDO::PARAM_INT);
	$query->bindValue(':password', $post['password']);

	$is_mod_post = !empty($post['mod']);

	if ($post['op'] && $is_mod_post && !empty($post['sticky'])) {
		$query->bindValue(':sticky', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':sticky', false, PDO::PARAM_INT);
	}

	if ($post['op'] && $is_mod_post && !empty($post['locked'])) {
		$query->bindValue(':locked', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':locked', false, PDO::PARAM_INT);
	}

	// cycle column kept in schema; feature removed
	$query->bindValue(':cycle', false, PDO::PARAM_INT);

	if ($is_mod_post && !empty($post['capcode'])) {
		$query->bindValue(':capcode', $post['capcode'], PDO::PARAM_STR);
	} else {
		$query->bindValue(':capcode', null, PDO::PARAM_NULL);
	}

	if (!empty($post['embed'])) {
		$query->bindValue(':embed', $post['embed']);
	} else {
		$query->bindValue(':embed', null, PDO::PARAM_NULL);
	}

	if ($post['op']) {
		// No parent thread, image
		$query->bindValue(':thread', null, PDO::PARAM_NULL);
	} else {
		$query->bindValue(':thread', $post['thread'], PDO::PARAM_INT);
	}

	if ($post['has_file']) {
		$query->bindValue(':files', json_encode($post['files']));
		$query->bindValue(':num_files', $post['num_files']);
		$query->bindValue(':filehash', $post['filehash']);
	} else {
		$query->bindValue(':files', null, PDO::PARAM_NULL);
		$query->bindValue(':num_files', 0);
		$query->bindValue(':filehash', null, PDO::PARAM_NULL);
	}

	if ($post['op']) {
		$query->bindValue(':slug', slugify($post));
	}
	else {
		$query->bindValue(':slug', NULL);
	}

	// 1 = held for mod approval (hidden from public until approved)
	$query->bindValue(':pending', !empty($post['pending']) ? 1 : 0, PDO::PARAM_INT);

	if (!$query->execute()) {
		undoImage($post);
		error(db_error($query));
	}

	return db_last_insert_id('posts_' . $board['uri']);
}

function bumpThread($id) {
	global $config, $board, $build_pages;

	if (event('bump', $id))
		return true;

	if ($config['try_smarter']) {
		$build_pages = array_merge(range(1, thread_find_page($id)), $build_pages);
	}

	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `bump` = :time WHERE `id` = :id AND `thread` IS NULL", $board['uri']));
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
}

// Remove file from post
function deleteFile($id, $remove_entirely_if_already=true, $file=null) {
	global $board, $config;

	$query = prepare(sprintf("SELECT `thread`, `files`, `num_files` FROM ``posts_%s`` WHERE `id` = :id LIMIT 1", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	if (!$post = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['invalidpost']);
	$files = json_decode($post['files']);
	$file_to_delete = $file !== false ? $files[(int)$file] : (object)array('file' => false);

	if (!$files[0]) error(_('That post has no files.'));

	if ($files[0]->file == 'deleted' && $post['num_files'] == 1 && !$post['thread'])
		return; // Can't delete OP's image completely.

	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `files` = :file WHERE `id` = :id", $board['uri']));
	if (($file && $file_to_delete->file == 'deleted') && $remove_entirely_if_already) {
		// Already deleted; remove file fully
		$files[$file] = null;
	} else {
		foreach ($files as $i => $f) {
			if (($file !== false && $i == $file) || $file === null) {
				// Delete thumbnail
				if (isset ($f->thumb) && $f->thumb) {
					file_unlink($board['dir'] . $config['dir']['thumb'] . $f->thumb);
					unset($files[$i]->thumb);
				}

				// Delete file
				file_unlink($board['dir'] . $config['dir']['img'] . $f->file);
				$files[$i]->file = 'deleted';
			}
		}
	}

	$query->bindValue(':file', json_encode($files), PDO::PARAM_STR);

	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($post['thread'])
		buildThread($post['thread']);
	else
		buildThread($id);
}

// rebuild post (markup)
function rebuildPost($id) {
	global $board, $mod;

	$query = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ((!$post = $query->fetch(PDO::FETCH_ASSOC)) || !$post['body_nomarkup'])
		return false;

	markup($post['body'] = &$post['body_nomarkup']);
	$post = (object)$post;
	event('rebuildpost', $post);
	$post = (array)$post;

	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `body` = :body WHERE `id` = :id", $board['uri']));
	$query->bindValue(':body', $post['body']);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	buildThread($post['thread'] ? $post['thread'] : $id);

	return true;
}

// Delete a post (reply or thread)
function deletePost($id, $error_if_doesnt_exist=true, $rebuild_after=true) {
	global $board, $config;

	// Select post and replies (if thread) in one query
	$query = prepare(sprintf("SELECT `id`,`thread`,`files`,`slug` FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($query->rowCount() < 1) {
		if ($error_if_doesnt_exist)
			error($config['error']['invalidpost']);
		else return false;
	}

	$ids = array();

	// Delete posts and maybe replies
	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		event('delete', $post);

		$thread_id = $post['thread'];
		if (!$post['thread']) {
			// Delete thread HTML page
			file_unlink($board['dir'] . $config['dir']['res'] . link_for($post) );

		} elseif ($query->rowCount() == 1) {
			// Rebuild thread
			$rebuild = &$post['thread'];
		}
		if ($post['files']) {
			foreach (json_decode($post['files']) as $i => $f) {
				unlink_board_file_entry($f);
			}
		}

		$ids[] = (int)$post['id'];

	}

	$query = prepare(sprintf("DELETE FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	$query = prepare("SELECT `board`, `post` FROM ``cites`` WHERE `target_board` = :board AND (`target` = " . implode(' OR `target` = ', $ids) . ") ORDER BY `board`");
	$query->bindValue(':board', $board['uri']);
	$query->execute() or error(db_error($query));
	while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
		if ($board['uri'] != $cite['board']) {
			if (!isset($tmp_board))
				$tmp_board = $board['uri'];
			openBoard($cite['board']);
		}
		rebuildPost($cite['post']);
	}

	if (isset($tmp_board))
		openBoard($tmp_board);

	$query = prepare("DELETE FROM ``cites`` WHERE (`target_board` = :board AND (`target` = " . implode(' OR `target` = ', $ids) . ")) OR (`board` = :board AND (`post` = " . implode(' OR `post` = ', $ids) . "))");
	$query->bindValue(':board', $board['uri']);
	$query->execute() or error(db_error($query));

	// Optional: recalculate thread bump after deleting a non-OP post
	if (!empty($config['recalc_bump_on_delete']) && isset($thread_id)) {
		$query = prepare(sprintf("SELECT `sage` FROM ``posts_%s`` WHERE `id` = :thread", $board['uri']));
		$query->bindValue(':thread', $thread_id);
		$query->execute() or error(db_error($query));
		$bumplocked = (bool)$query->fetchColumn();

		if (!$bumplocked) {
			// PostgreSQL: IS DISTINCT FROM (MySQL null-safe <=> is not rewritten)
			$query = prepare(sprintf("SELECT `time` FROM ``posts_%s`` WHERE (`thread` = :thread AND email IS DISTINCT FROM 'sage') OR `id` = :thread ORDER BY `time` DESC LIMIT 1", $board['uri']));
			$query->bindValue(':thread', $thread_id);
			$query->execute() or error(db_error($query));
			$bump = $query->fetchColumn();

			$query = prepare(sprintf("UPDATE ``posts_%s`` SET `bump` = :bump WHERE `id` = :thread", $board['uri']));
			$query->bindValue(':bump', $bump);
			$query->bindValue(':thread', $thread_id);
			$query->execute() or error(db_error($query));
		}
	}

	if (isset($rebuild) && $rebuild_after) {
		buildThread($rebuild);
		buildIndex();
	}

	return true;
}

function clean($pid = false) {
	global $board, $config;
	$offset = round($config['max_pages']*$config['threads_per_page']);

	// I too wish there was an easier way of doing this...
	$pub = function_exists('sql_posts_public') ? sql_posts_public() : '1=1';
	$query = prepare(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL AND %s ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset, 9001", $board['uri'], $pub));
	$query->bindValue(':offset', $offset, PDO::PARAM_INT);

	$query->execute() or error(db_error($query));
	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		if (function_exists('archive_enabled') && archive_enabled() && function_exists('archiveThread')) {
			archiveThread((int)$post['id'], (bool)$pid);
			if ($pid) {
				modLog("Automatically archived thread #{$post['id']} due to new thread #{$pid}");
			}
		} else {
			deletePost($post['id'], false, false);
			if ($pid) {
				modLog("Automatically deleting thread #{$post['id']} due to new thread #{$pid}");
			}
		}
	}

	// Optional: drop low-reply threads off early pages
	if ($config['early_404']) {
		$offset = round($config['early_404_page']*$config['threads_per_page']);
		$query = prepare(sprintf("SELECT `id` AS `thread_id`, (SELECT COUNT(`id`) FROM ``posts_%s`` WHERE `thread` = `thread_id`) AS `reply_count` FROM ``posts_%s`` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset, 9001", $board['uri'], $board['uri']));
		$query->bindValue(':offset', $offset, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		if ($config['early_404_staged']) {
			$page = $config['early_404_page'];
			$iter = 0;
		}
		else {
			$page = 1;
		}

		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			if ($post['reply_count'] < $page*$config['early_404_replies']) {
				if (function_exists('archive_enabled') && archive_enabled() && function_exists('archiveThread')) {
					archiveThread((int)$post['thread_id'], (bool)$pid);
					if ($pid) {
						modLog("Automatically archived thread #{$post['thread_id']} due to new thread #{$pid} (early 404; {$post['reply_count']} replies)");
					}
				} else {
					deletePost($post['thread_id'], false, false);
					if ($pid) {
						modLog("Automatically deleting thread #{$post['thread_id']} due to new thread #{$pid} (early 404 is set, #{$post['thread_id']} had {$post['reply_count']} replies)");
					}
				}
			}

			if ($config['early_404_staged']) {
				$iter++;

				if ($iter == $config['threads_per_page']) {
					$page++;
					$iter = 0;
				}
			}
		}
	}
}

function thread_find_page($thread) {
	global $config, $board;

	$query = query(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC", $board['uri'])) or error(db_error($query));
	$threads = $query->fetchAll(PDO::FETCH_COLUMN);
	if (($index = array_search($thread, $threads)) === false)
		return false;
	return floor(($config['threads_per_page'] + $index) / $config['threads_per_page']);
}

// $brief means that we won't need to generate anything yet
function index($page, $mod=false, $brief = false) {
	global $board, $config, $debug;

	$body = '';
	$offset = round($page*$config['threads_per_page']-$config['threads_per_page']);

	$pub = function_exists('sql_posts_public') ? sql_posts_public() : '1=1';
	$query = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `thread` IS NULL AND %s ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset,:threads_per_page", $board['uri'], $pub));
	$query->bindValue(':offset', $offset, PDO::PARAM_INT);
	$query->bindValue(':threads_per_page', $config['threads_per_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($page == 1 && $query->rowCount() < $config['threads_per_page'])
		$board['thread_count'] = $query->rowCount();

	if ($query->rowCount() < 1 && $page > 1)
		return false;

	$threads = array();

	while ($th = $query->fetch(PDO::FETCH_ASSOC)) {
		$thread = new Thread($th, $mod ? '?/' : $config['root'], $mod);

		if ($config['cache']['enabled']) {
			$cached = cache::get("thread_index_{$board['uri']}_{$th['id']}");
			if (isset($cached['replies'], $cached['omitted'])) {
				$replies = $cached['replies'];
				$omitted = $cached['omitted'];
			} else {
				unset($cached);
			}
		}

		if (!isset($cached)) {
			$posts = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `thread` = :id AND %s ORDER BY `id` DESC LIMIT :limit", $board['uri'], $pub));
			$posts->bindValue(':id', $th['id']);
			$posts->bindValue(':limit', ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']), PDO::PARAM_INT);
			$posts->execute() or error(db_error($posts));

			$replies = array_reverse($posts->fetchAll(PDO::FETCH_ASSOC));

			if (count($replies) == ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview'])) {
				$count = numPosts($th['id']);
				$omitted = array('post_count' => $count['replies'], 'image_count' => $count['images']);
			} else {
				$omitted = false;
			}

			if ($config['cache']['enabled'])
				cache::set("thread_index_{$board['uri']}_{$th['id']}", array(
					'replies' => $replies,
					'omitted' => $omitted,
				));
		}

		$num_images = 0;
		foreach ($replies as $po) {
			if ($po['num_files'])
				$num_images+=$po['num_files'];

			$thread->add(new Post($po, $mod ? '?/' : $config['root'], $mod));
		}

		$thread->images = $num_images;
		$thread->replies = isset($omitted['post_count']) ? $omitted['post_count'] : count($replies);

		if ($omitted) {
			$thread->omitted = $omitted['post_count'] - ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']);
			$thread->omitted_images = $omitted['image_count'] - $num_images;
		}

		$threads[] = $thread;

		if (!$brief) {
			$body .= $thread->build(true);
		}
	}

	return array(
		'board' => $board,
		'body' => $body,
		'post_url' => $config['post_url'],
		'config' => $config,
		'boardlist' => createBoardlist($mod),
		'threads' => $threads,
	);
}

function getPageButtons($pages, $mod=false) {
	global $config, $board;

	$btn = array();
	$root = ($mod ? '?/' : $config['root']) . $board['dir'];

	foreach ($pages as $num => $page) {
		if (isset($page['selected'])) {
			// Previous button
			if ($num == 0) {
				// There is no previous page.
				$btn['prev'] = _('Previous');
			} else {
				$loc = ($mod ? '?/' . $board['uri'] . '/' : '') .
					($num == 1 ?
						$config['file_index']
					:
						sprintf($config['file_page'], $num)
					);

				$btn['prev'] = '<form action="' . ($mod ? '' : $root . $loc) . '" method="get">' .
					($mod ?
						'<input type="hidden" name="status" value="301" />' .
						'<input type="hidden" name="r" value="' . htmlentities($loc) . '" />'
					:'') .
				'<input type="submit" value="' . _('Previous') . '" /></form>';
			}

			if ($num == count($pages) - 1) {
				// There is no next page.
				$btn['next'] = _('Next');
			} else {
				$loc = ($mod ? '?/' . $board['uri'] . '/' : '') . sprintf($config['file_page'], $num + 2);

				$btn['next'] = '<form action="' . ($mod ? '' : $root . $loc) . '" method="get">' .
					($mod ?
						'<input type="hidden" name="status" value="301" />' .
						'<input type="hidden" name="r" value="' . htmlentities($loc) . '" />'
					:'') .
				'<input type="submit" value="' . _('Next') . '" /></form>';
			}
		}
	}

	return $btn;
}

function getPages($mod=false) {
	global $board, $config;

	if (isset($board['thread_count'])) {
		$count = $board['thread_count'];
	} else {
		// Count threads
		$pub = function_exists('sql_posts_public') ? sql_posts_public() : '1=1';
		$query = query(sprintf("SELECT COUNT(*) FROM ``posts_%s`` WHERE `thread` IS NULL AND %s", $board['uri'], $pub)) or error(db_error());
		$count = $query->fetchColumn();
	}
	$count = floor(($config['threads_per_page'] + $count - 1) / $config['threads_per_page']);

	if ($count < 1) $count = 1;

	$pages = array();
	for ($x=0;$x<$count && $x<$config['max_pages'];$x++) {
		$pages[] = array(
			'num' => $x+1,
			'link' => $x==0 ? ($mod ? '?/' : $config['root']) . $board['dir'] . $config['file_index'] : ($mod ? '?/' : $config['root']) . $board['dir'] . sprintf($config['file_page'], $x+1)
		);
	}

	return $pages;
}

// Returns an associative array with 'replies' and 'images' keys
function numPosts($id) {
	global $board;
	$pub = function_exists('sql_posts_public') ? sql_posts_public() : '1=1';
	$query = prepare(sprintf("SELECT COUNT(*) AS `replies`, SUM(`num_files`) AS `images` FROM ``posts_%s`` WHERE `thread` = :thread AND %s", $board['uri'], $pub));
	$query->bindValue(':thread', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	return $query->fetch(PDO::FETCH_ASSOC);
}

function buildIndex($global_api = "yes") {
	global $board, $config, $build_pages, $mod;

	// $global_api kept for call-site compatibility; public JSON API removed.
	$pages = null;
	$action = 'rebuild';

	for ($page = 1; $page <= $config['max_pages']; $page++) {
		$filename = $board['dir'] . ($page == 1 ? $config['file_index'] : sprintf($config['file_page'], $page));

		$wont_build_this_page = $config['try_smarter'] && isset($build_pages) && !empty($build_pages) && !in_array($page, $build_pages);
		if ($wont_build_this_page) {
			continue;
		}

		$content = index($page, false, $wont_build_this_page);
		if (!$content) {
			break;
		}

		// Skip rewrite if index body unchanged (optional cache)
		if ($config['cache']['enabled']) {
			$contentHash = md5(json_encode($content['body']));
			$contentHashKey = '_index_hashed_' . $board['uri'] . '_' . $page;
			if (cache::get($contentHashKey) == $contentHash) {
				continue;
			}
			cache::set($contentHashKey, $contentHash, 3600);
		}

		if (!$pages) {
			$pages = getPages();
		}
		$content['pages'] = $pages;
		$content['pages'][$page - 1]['selected'] = true;
		$content['btn'] = getPageButtons($content['pages']);
		file_write($filename, Element($config['file_board_index'], $content));
	}

	// Remove pages beyond the last built page
	if ($page <= $config['max_pages']) {
		for (; $page <= $config['max_pages']; $page++) {
			$filename = $board['dir'] . ($page == 1 ? $config['file_index'] : sprintf($config['file_page'], $page));
			file_unlink($filename);
		}
	}

	// HTML catalog
	if (!function_exists('catalog_build_board')) {
		require_once __DIR__ . '/catalog.php';
	}
	catalog_build_board($board['uri']);

	// Keep archive index present (threads themselves rebuild via rebuildArchive / archiveThread)
	if (function_exists('archive_enabled') && archive_enabled() && function_exists('buildArchiveIndex')) {
		if (!is_file(archive_index_path())) {
			buildArchiveIndex();
		}
	}

	// Drop legacy public JSON sidecars if present
	foreach (['catalog.json', 'threads.json', '0.json', '1.json', '2.json', '3.json', '4.json', '5.json', '6.json', '7.json', '8.json', '9.json'] as $side) {
		$file = $board['dir'] . $side;
		if (file_exists($file)) {
			file_unlink($file);
		}
	}

	if ($config['try_smarter']) {
		$build_pages = array();
	}
}

function buildJavascript() {
	global $config;

	$stylesheets = array();
	foreach ($config['stylesheets'] as $name => $uri) {
		$stylesheets[] = array(
			'name' => addslashes($name),
			'uri' => addslashes((!empty($uri) ? $config['uri_stylesheets'] : '') . $uri));
	}

	$script = Element('main.js', array(
		'config' => $config,
		'stylesheets' => $stylesheets
	));

	file_write($config['file_script'], $script);
}

function wordfilters(&$body) {
	global $config;

	foreach ($config['wordfilters'] as $filter) {
		if (isset($filter[2]) && $filter[2]) {
			if (is_callable($filter[1]))
				$body = preg_replace_callback($filter[0], $filter[1], $body);
			else
				$body = preg_replace($filter[0], $filter[1], $body);
		} else {
			$body = str_ireplace($filter[0], $filter[1], $body);
		}
	}
}

function markup_url($matches) {
	global $config, $markup_urls;

	$url = $matches[1];
	$after = $matches[2];

	$markup_urls[] = $url;

	$link = (object) array(
		'href' => $config['link_prefix'] . $url,
		'text' => $url,
		'rel' => 'nofollow',
		'target' => '_blank',
	);

	event('markup-url', $link);
	$link = (array)$link;

	$parts = array();
	foreach ($link as $attr => $value) {
		if ($attr == 'text' || $attr == 'after')
			continue;
		$parts[] = $attr . '="' . $value . '"';
	}
	if (isset($link['after']))
		$after = $link['after'] . $after;
	return '<a ' . implode(' ', $parts) . '>' . $link['text'] . '</a>' . $after;
}

function unicodify($body) {
	$body = str_replace('...', '&hellip;', $body);
	$body = str_replace('&lt;--', '&larr;', $body);
	$body = str_replace('--&gt;', '&rarr;', $body);

	// En and em- dashes are rendered exactly the same in
	// most monospace fonts (they look the same in code
	// editors).
	$body = str_replace('---', '&mdash;', $body); // em dash
	$body = str_replace('--', '&ndash;', $body); // en dash

	return $body;
}

function extract_modifiers($body) {
	$modifiers = array();

	if (preg_match_all('@<tinyboard ([\w\s]+)>(.*?)</tinyboard>@us', $body, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			if (preg_match('/^escape /', $match[1]))
				continue;
			$modifiers[$match[1]] = html_entity_decode($match[2]);
		}
	}

	return $modifiers;
}

function remove_modifiers($body) {
	return $body ? preg_replace('@<tinyboard ([\w\s]+)>(.+?)</tinyboard>@usm', '', $body) : null;
}

function markup(&$body, $track_cites = false, $op = false) {
	global $board, $config, $markup_urls;

	$modifiers = extract_modifiers($body);

	$body = preg_replace('@<tinyboard (?!escape )([\w\s]+)>(.+?)</tinyboard>@us', '', $body);
	$body = preg_replace('@<(tinyboard) escape ([\w\s]+)>@i', '<$1 $2>', $body);

	if (isset($modifiers['raw html']) && $modifiers['raw html'] == '1') {
		return array();
	}

	$body = str_replace("\r", '', $body);
	$body = utf8tohtml($body);

	if ($config['markup_code']) {
		$code_markup = array();
		$body = preg_replace_callback($config['markup_code'], function($matches) use (&$code_markup) {
			$d = count($code_markup);
			$code_markup[] = $matches;
			return "<code $d>";
		}, $body);
	}

	foreach ($config['markup'] as $markup) {
		if (is_string($markup[1])) {
			$body = preg_replace($markup[0], $markup[1], $body);
		} elseif (is_callable($markup[1])) {
			$body = preg_replace_callback($markup[0], $markup[1], $body);
		}
	}

	if ($config['markup_urls']) {
		$markup_urls = array();

		$body = preg_replace_callback(
				'/((?:https?:\/\/|ftp:\/\/|irc:\/\/)[^\s<>()"]+?(?:\([^\s<>()"]*?\)[^\s<>()"]*?)*)((?:\s|<|>|"|\.||\]|!|\?|,|&#44;|&quot;)*(?:[\s<>()"]|$))/',
				'markup_url',
				$body,
				-1,
				$num_links);

		if ($num_links > $config['max_links'])
			error($config['error']['toomanylinks']);
	}

	if ($config['auto_unicode']) {
		$body = unicodify($body);

		if ($config['markup_urls']) {
			foreach ($markup_urls as &$url) {
				$body = str_replace(unicodify($url), $url, $body);
			}
		}
	}

	$tracked_cites = array();

	// Cites
	if (isset($board) && preg_match_all('/(^|[\s(])&gt;&gt;(\d+?)((?=[\s,.)?!])|$)/m', $body, $cites, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		if (count($cites[0]) > $config['max_cites']) {
			error($config['error']['toomanycites']);
		}

		$skip_chars = 0;
		$body_tmp = $body;

		$search_cites = array();
		foreach ($cites as $matches) {
			$search_cites[] = '`id` = ' . $matches[2][0];
		}
		$search_cites = array_unique($search_cites);

		$query = query(sprintf('SELECT `thread`, `id` FROM ``posts_%s`` WHERE ' .
			implode(' OR ', $search_cites), $board['uri'])) or error(db_error());

		$cited_posts = array();
		while ($cited = $query->fetch(PDO::FETCH_ASSOC)) {
			$cited_posts[$cited['id']] = $cited['thread'] ? $cited['thread'] : false;
		}

		foreach ($cites as $matches) {
			$cite = $matches[2][0];

			// preg_match_all is not multibyte-safe
			foreach ($matches as &$match) {
				$match[1] = mb_strlen(substr($body_tmp, 0, $match[1]));
			}

			if (isset($cited_posts[$cite])) {
				$replacement = '<a onclick="highlightReply(\''.$cite.'\', event);" href="' .
					$config['root'] . $board['dir'] . $config['dir']['res'] .
					link_for(array('id' => $cite, 'thread' => $cited_posts[$cite])) . '#' . $cite . '">' .
					'&gt;&gt;' . $cite .
					'</a>';

				$body = mb_substr_replace($body, $matches[1][0] . $replacement . $matches[3][0], $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
				$skip_chars += mb_strlen($matches[1][0] . $replacement . $matches[3][0]) - mb_strlen($matches[0][0]);

				if ($track_cites && $config['track_cites'])
					$tracked_cites[] = array($board['uri'], $cite);
			}
		}
	}

	// Cross-board linking
	if (preg_match_all('/(^|[\s(])&gt;&gt;&gt;\/(' . $config['board_regex'] . 'f?)\/(\d+)?((?=[\s,.)?!])|$)/um', $body, $cites, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		if (count($cites[0]) > $config['max_cites']) {
			error($config['error']['toomanycross']);
		}

		$skip_chars = 0;
		$body_tmp = $body;

		if (isset($cited_posts)) {
			// Carry found posts from local board >>X links
			foreach ($cited_posts as $cite => $thread) {
				$cited_posts[$cite] = $config['root'] . $board['dir'] . $config['dir']['res'] .
					($thread ? $thread : $cite) . '.html#' . $cite;
			}

			$cited_posts = array(
				$board['uri'] => $cited_posts
			);
		} else
			$cited_posts = array();

		$crossboard_indexes = array();
		$search_cites_boards = array();

		foreach ($cites as $matches) {
			$_board = $matches[2][0];
			$cite = @$matches[3][0];

			if (!isset($search_cites_boards[$_board]))
				$search_cites_boards[$_board] = array();
			$search_cites_boards[$_board][] = $cite;
		}

		$tmp_board = $board['uri'];

		foreach ($search_cites_boards as $_board => $search_cites) {
			$clauses = array();
			foreach ($search_cites as $cite) {
				if (!$cite || isset($cited_posts[$_board][$cite]))
					continue;
				$clauses[] = '`id` = ' . $cite;
			}
			$clauses = array_unique($clauses);

			if ($board['uri'] != $_board) {
				if (!openBoard($_board))
					continue; // Unknown board
			}

			if (!empty($clauses)) {
				$cited_posts[$_board] = array();

				$query = query(sprintf('SELECT `thread`, `id`, `slug` FROM ``posts_%s`` WHERE ' .
					implode(' OR ', $clauses), $board['uri'])) or error(db_error());

				while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
					$cited_posts[$_board][$cite['id']] = $config['root'] . $board['dir'] . $config['dir']['res'] .
						link_for($cite) . '#' . $cite['id'];
				}
			}

			$crossboard_indexes[$_board] = $config['root'] . $board['dir'] . $config['file_index'];
		}

		// Restore old board
		if ($board['uri'] != $tmp_board)
			openBoard($tmp_board);

		foreach ($cites as $matches) {
			$_board = $matches[2][0];
			$cite = @$matches[3][0];

			// preg_match_all is not multibyte-safe
			foreach ($matches as &$match) {
				$match[1] = mb_strlen(substr($body_tmp, 0, $match[1]));
			}

			if ($cite) {
				if (isset($cited_posts[$_board][$cite])) {
					$link = $cited_posts[$_board][$cite];

					$replacement = '<a ' .
						($_board == $board['uri'] ?
							'onclick="highlightReply(\''.$cite.'\', event);" '
						: '') . 'href="' . $link . '">' .
						'&gt;&gt;&gt;/' . $_board . '/' . $cite .
						'</a>';

					$body = mb_substr_replace($body, $matches[1][0] . $replacement . $matches[4][0], $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
					$skip_chars += mb_strlen($matches[1][0] . $replacement . $matches[4][0]) - mb_strlen($matches[0][0]);

					if ($track_cites && $config['track_cites'])
						$tracked_cites[] = array($_board, $cite);
				}
			} elseif(isset($crossboard_indexes[$_board])) {
				$replacement = '<a href="' . $crossboard_indexes[$_board] . '">' .
						'&gt;&gt;&gt;/' . $_board . '/' .
						'</a>';
				$body = mb_substr_replace($body, $matches[1][0] . $replacement . $matches[4][0], $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
				$skip_chars += mb_strlen($matches[1][0] . $replacement . $matches[4][0]) - mb_strlen($matches[0][0]);
			}
		}
	}

	$tracked_cites = array_unique($tracked_cites, SORT_REGULAR);

	$body = preg_replace("/^\s*&gt;.*$/m", '<span class="quote">$0</span>', $body);

	if ($config['strip_superfluous_returns'])
		$body = preg_replace('/\s+$/', '', $body);

	$body = preg_replace("/\n/", '<br/>', $body);

	// Fix code markup
	if ($config['markup_code']) {
		foreach ($code_markup as $id => $val) {
			$code = isset($val[2]) ? $val[2] : $val[1];
			$code_lang = isset($val[2]) ? $val[1] : "";

			$code = "<pre class='code lang-$code_lang'>".str_replace(array("\n","\t"), array("&#10;","&#9;"), htmlspecialchars($code))."</pre>";

			$body = str_replace("<code $id>", $code, $body);
		}
	}

	// replace tabs with 8 spaces
	$body = str_replace("\t", '		', $body);

	return $tracked_cites;
}

function escape_markup_modifiers($string) {
	return preg_replace('@<(tinyboard) ([\w\s]+)>@mi', '<$1 escape $2>', $string);
}

function defined_flags_accumulate($desired_flags) {
	global $config;
	$output_flags = 0x0;
	foreach ($desired_flags as $flagname) {
		if (defined($flagname)) {
			$flag = constant($flagname);
			if (gettype($flag) != 'integer')
				error(sprintf($config['error']['flag_wrongtype'], $flagname));
			$output_flags |= $flag;
		} else {
			if ($config['deprecation_errors'])
				error(sprintf($config['error']['flag_undefined'], $flagname));
		}
	}
	return $output_flags;
}

function utf8tohtml($utf8) {
	$flags = defined_flags_accumulate(['ENT_NOQUOTES', 'ENT_SUBSTITUTE', 'ENT_DISALLOWED']);
	return $utf8 ? htmlspecialchars($utf8, $flags, 'UTF-8') : '';
}

function ordutf8($string, &$offset) {
	$code = ord(substr($string, $offset,1));
	if ($code >= 128) { // otherwise 0xxxxxxx
		if ($code < 224)
			$bytesnumber = 2; // 110xxxxx
		else if ($code < 240)
			$bytesnumber = 3; // 1110xxxx
		else if ($code < 248)
			$bytesnumber = 4; // 11110xxx
		$codetemp = $code - 192 - ($bytesnumber > 2 ? 32 : 0) - ($bytesnumber > 3 ? 16 : 0);
		for ($i = 2; $i <= $bytesnumber; $i++) {
			$offset ++;
			$code2 = ord(substr($string, $offset, 1)) - 128; //10xxxxxx
			$codetemp = $codetemp*64 + $code2;
		}
		$code = $codetemp;
	}
	$offset += 1;
	if ($offset >= strlen($string))
		$offset = -1;
	return $code;
}

// Limit Non_Spacing_Mark and Enclosing_Mark characters
function strip_combining_chars($str) {
	global $config;
	$limit = strval($config['max_combining_chars']+1);
	return preg_replace('/(\p{Me}|\p{Mn}){'.$limit.',}/u','', $str);
}

function buildThread($id, $return = false, $mod = false) {
	global $board, $config, $build_pages;
	$id = round($id);

	if (event('build-thread', $id))
		return;

	if ($config['cache']['enabled'] && !$mod) {
		cache::delete("thread_index_{$board['uri']}_{$id}");
		cache::delete("thread_{$board['uri']}_{$id}");
	}

	if (!empty($config['try_smarter']) && !$mod)
		$build_pages[] = thread_find_page($id);

	// Public pages hide pending posts; mod overlay shows them for review.
	if ($mod) {
		$query = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE (`thread` IS NULL AND `id` = :id) OR `thread` = :id ORDER BY `thread`,`id`", $board['uri']));
	} else {
		$pub = function_exists('sql_posts_public') ? sql_posts_public() : '1=1';
		$query = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE ((`thread` IS NULL AND `id` = :id) OR `thread` = :id) AND %s ORDER BY `thread`,`id`", $board['uri'], $pub));
	}
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		if (!isset($thread)) {
			$thread = new Thread($post, $mod ? '?/' : $config['root'], $mod);
		} else {
			$thread->add(new Post($post, $mod ? '?/' : $config['root'], $mod));
		}
	}

	if (!isset($thread))
		error($config['error']['nonexistant']);

	$options = [
		'board' => $board,
		'thread' => $thread,
		'body' => $thread->build(),
		'config' => $config,
		'id' => $id,
		'mod' => $mod,
		'boardlist' => createBoardlist($mod),
		'return' => ($mod ? '?' . $board['url'] . $config['file_index'] : $config['root'] . $board['dir'] . $config['file_index'])
	];
	$body = Element($config['file_thread'], $options);

	if ($return) {
		return $body;
	}

	file_write($board['dir'] . $config['dir']['res'] . link_for($thread), $body);
}

function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir."/".$object) == "dir")
					rrmdir($dir."/".$object);
				else
					file_unlink($dir."/".$object);
			}
		}
		reset($objects);
		rmdir($dir);
	}
}

/**
 * Parse Name#password / Name##password into display name + secure trip.
 * HMAC-SHA256 only (legacy DES crypt removed).
 *
 * @return array{0:string,1?:string} [name] or [name, trip]
 */
function generate_tripcode($name) {
	global $config;

	if (!empty($config['disable_tripcodes']) || (isset($config['tripcodes']['enabled']) && !$config['tripcodes']['enabled'])) {
		if (preg_match('/^([^#]*)(?:##|#).+$/u', $name, $m)) {
			$n = trim($m[1]);
			return [$n !== '' ? $n : $config['anonymous']];
		}
		return [$name];
	}

	if ($trip = event('tripcode', $name)) {
		return $trip;
	}

	if (!preg_match('/^([^#]*)(##|#)(.+)$/u', $name, $match)) {
		return [$name];
	}

	$display_name = $match[1];
	$marker = $match[2];
	$password = $match[3];

	$custom_key = $marker . $password;
	if (isset($config['custom_tripcode'][$custom_key])) {
		return [$display_name, $config['custom_tripcode'][$custom_key]];
	}

	$length = (int)($config['tripcodes']['length'] ?? 12);
	$length = max(8, min(32, $length));
	$prefix = $config['tripcodes']['prefix'] ?? '!!';
	$salt = $config['secure_trip_salt'] ?? '';

	$raw = hash_hmac('sha256', $password, $salt, true);
	$b64 = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
	return [$display_name, $prefix . substr($b64, 0, $length)];
}

function getPostByHash($hash) {
	global $board;
	$query = prepare(sprintf("SELECT `id`,`thread` FROM ``posts_%s`` WHERE `filehash` = :hash", $board['uri']));
	$query->bindValue(':hash', $hash, PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		return $post;
	}

	return false;
}

function getPostByHashInThread($hash, $thread) {
	global $board;
	$query = prepare(sprintf("SELECT `id`,`thread` FROM ``posts_%s`` WHERE `filehash` = :hash AND ( `thread` = :thread OR `id` = :thread )", $board['uri']));
	$query->bindValue(':hash', $hash, PDO::PARAM_STR);
	$query->bindValue(':thread', $thread, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		return $post;
	}

	return false;
}

function undoImage(array $post) {
	if (!$post['has_file'] || !isset($post['files']))
		return;

	foreach ($post['files'] as $key => $file) {
		if (isset($file['file_path']))
			file_unlink($file['file_path']);
		if (isset($file['thumb_path']))
			file_unlink($file['thumb_path']);
	}
}

function slugify($post) {
	global $config;

	$slug = "";

	if (isset($post['subject']) && $post['subject'])
		$slug = $post['subject'];
	elseif (isset ($post['body_nomarkup']) && $post['body_nomarkup'])
		$slug = $post['body_nomarkup'];
	elseif (isset ($post['body']) && $post['body'])
		$slug = strip_tags($post['body']);

	// Fix UTF-8 first
	$slug = mb_convert_encoding($slug, "UTF-8", "UTF-8");

	// Transliterate local characters like ü, I wonder how would it work for weird alphabets :^)
	$slug = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $slug);

	// Remove Tinyboard custom markup
	$slug = preg_replace("/<tinyboard [^>]+>.*?<\/tinyboard>/s", '', $slug);

	// Downcase everything
	$slug = strtolower($slug);

	// Strip bad characters, alphanumerics should suffice
	$slug = preg_replace('/[^a-zA-Z0-9]/', '-', $slug);

	// Replace multiple dashes with single ones
	$slug = preg_replace('/-+/', '-', $slug);

	// Strip dashes at the beginning and at the end
	$slug = preg_replace('/^-|-$/', '', $slug);

	// Slug should be X characters long, at max (80?)
	$slug = substr($slug, 0, $config['slug_max_size']);

	// Slug is now ready
	return $slug;
}

function link_for($post, $page50 = false, $foreignlink = false, $thread = false) {
	global $config, $board;

	// $page50 retained for call-site compatibility (noko50 removed).
	$post = (array)$post;

	// Where do we need to look for OP?
	$b = $foreignlink ? $foreignlink : (isset($post['board']) ? array('uri' => $post['board']) : $board);

	$id = (isset($post['thread']) && $post['thread']) ? $post['thread'] : $post['id'];

	$slug = false;

	if ($config['slugify'] && ( (isset($post['thread']) && $post['thread']) || !isset ($post['slug']) ) ) {
		$cvar = "slug_".$b['uri']."_".$id;
		if (!$thread) {
			$slug = Cache::get($cvar);

			if ($slug === false) {
				$query = prepare(sprintf("SELECT `slug` FROM ``posts_%s`` WHERE `id` = :id", $b['uri']));
				$query->bindValue(':id', $id, PDO::PARAM_INT);
				$query->execute() or error(db_error($query));

				$thread = $query->fetch(PDO::FETCH_ASSOC);

				$slug = $thread['slug'];

				Cache::set($cvar, $slug);
			}
		}
		else {
			$slug = $thread['slug'];
		}
	}
	elseif ($config['slugify']) {
		$slug = $post['slug'];
	}

	$tpl = $slug ? $config['file_page_slug'] : $config['file_page'];

	return sprintf($tpl, $id, $slug);
}
