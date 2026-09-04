<?php
/*
 *  Copyright (c) 2010-2014 Tinyboard Development Group
 */

require_once 'inc/bootstrap.php';

if ($config['debug']) {
	$parse_start_time = microtime(true);
}

require_once 'inc/mod/pages.php';


$ctx = Vichan\build_context($config);

check_login($ctx, true);

$query = isset($_SERVER['QUERY_STRING']) ? rawurldecode($_SERVER['QUERY_STRING']) : '';

$pages = [
	''					=> ':?/',			// redirect to dashboard
	'/'					=> 'dashboard',			// dashboard
	'/confirm/(.+)'				=> 'confirm',			// confirm action (if javascript didn't work)
	'/logout'				=> 'secure logout',		// logout

	'/users'				=> 'users',			// manage users
	'/users/(\d+)/(promote|demote)'		=> 'secure user_promote',	// prmote/demote user
	'/users/(\d+)'				=> 'secure_POST user',		// edit user
	'/users/new'				=> 'secure_POST user_new',	// create a new user

	'/log'					=> 'log',			// modlog
	'/log/(\d+)'				=> 'log',			// modlog
	'/log:([^/:]+)'				=> 'user_log',			// modlog
	'/log:([^/:]+)/(\d+)'			=> 'user_log',			// modlog
	'/log:b:([^/]+)'			=> 'board_log',			// modlog
	'/log:b:([^/]+)/(\d+)'			=> 'board_log',			// modlog

	'/edit/(\%b)'				=> 'secure_POST edit_board',	// edit board details
	'/new-board'				=> 'secure_POST new_board',	// create a new board

	'/rebuild'				=> 'secure_POST rebuild',	// rebuild static files
	'/reports'				=> 'reports',			// report queue
	'/reports/(\d+)/dismiss(&all|&post)?'		=> 'secure report_dismiss',	// dismiss a report
	'/pending'				=> 'pending',			// posts awaiting approval
	'/pending/(\%b)/(\d+)/(approve|reject)'	=> 'secure pending_action',

	'/recent/(\d+)'				=> 'recent_posts',

	'/(\%b)/edit(_raw)?/(\d+)'		=> 'secure_POST edit_post',
	'/(\%b)/delete/(\d+)'			=> 'secure delete',
	'/(\%b)/deletefile/(\d+)/(\d+)'		=> 'secure deletefile',
	'/(\%b+)/spoiler/(\d+)/(\d+)'		=> 'secure spoiler_image',
	'/(\%b)/(un)?lock/(\d+)'		=> 'secure lock',
	'/(\%b)/(un)?sticky/(\d+)'		=> 'secure sticky',
	'/(\%b)/bump(un)?lock/(\d+)'		=> 'secure bumplock',
	'/(\%b)/archive/(\d+)'			=> 'secure archive_thread',

	// Board / thread viewing (mod overlay)
	'/(\%b)/'										=> 'view_board',
	'/(\%b)/' . preg_quote($config['file_index'], '!')					=> 'view_board',
	'/(\%b)/' . preg_quote($config['file_catalog'], '!')					=> 'view_catalog',
	'/(\%b)/' . str_replace('%d', '(\d+)', preg_quote($config['file_page'], '!'))		=> 'view_board',
	'/(\%b)/' . preg_quote($config['dir']['res'], '!') .
			str_replace('%d', '(\d+)', preg_quote($config['file_page'], '!'))	=> 'view_thread',
	'/(\%b)/' . preg_quote($config['dir']['res'], '!') .
			str_replace([ '%d','%s' ], [ '(\d+)', '[a-z0-9-]+' ], preg_quote($config['file_page_slug'], '!'))	=> 'view_thread',
];


if (!$mod) {
	$pages = [ '!^(.+)?$!' => 'login' ];
} elseif (isset($_GET['status'], $_GET['r'])) {
	// Allow only same-app relative redirects (block open redirects / protocol-relative URLs).
	$r = (string)$_GET['r'];
	$status = (int)$_GET['status'];
	if ($status < 300 || $status > 399) {
		$status = (int)$config['redirect_http'];
	}
	$safe = false;
	if ($r !== '' && !str_contains($r, "\0") && !str_contains($r, "\r") && !str_contains($r, "\n")) {
		// ?/mod-path… or root-relative /path (not //evil)
		if (str_starts_with($r, '?/') || str_starts_with($r, $config['file_mod'] . '?/')) {
			$safe = true;
		} elseif (str_starts_with($r, '/') && !str_starts_with($r, '//') && !preg_match('#^[a-z][a-z0-9+.-]*:#i', $r)) {
			$safe = true;
		}
	}
	if ($safe) {
		header('Location: ' . $r, true, $status);
		exit;
	}
	header('Location: ?/', true, (int)$config['redirect_http']);
	exit;
}

if (isset($config['mod']['custom_pages'])) {
	$pages = array_merge($pages, $config['mod']['custom_pages']);
}

$new_pages = [];
foreach ($pages as $key => $callback) {
	if (is_string($callback) && preg_match('/^secure /', $callback)) {
		$key .= '(/(?P<token>[a-f0-9]{8}))?';
	}
	$key = str_replace('\%b', '?P<board>' . sprintf(substr($config['board_path'], 0, -1), $config['board_regex']), $key);
	$new_pages[(!empty($key) and $key[0] == '!') ? $key : '!^' . $key . '(?:&[^&=]+=[^&]*)*$!u'] = $callback;
}
$pages = $new_pages;

foreach ($pages as $uri => $handler) {
	if (preg_match($uri, $query, $matches)) {
		$matches[0] = $ctx; // Replace the text captured by the full pattern with a reference to the context.

		if (isset($matches['board'])) {
			$board_match = $matches['board'];
			unset($matches['board']);
			$key = array_search($board_match, $matches);
			if (preg_match('/^' . sprintf(substr($config['board_path'], 0, -1), '(' . $config['board_regex'] . ')') . '$/u', $matches[$key], $board_match)) {
				$matches[$key] = $board_match[1];
			}
		}

		if (is_string($handler) && preg_match('/^secure(_POST)? /', $handler, $m)) {
			$secure_post_only = isset($m[1]);
			if (!$secure_post_only || $_SERVER['REQUEST_METHOD'] == 'POST') {
				$token = isset($matches['token']) ? $matches['token'] : (isset($_POST['token']) ? $_POST['token'] : false);

				if ($token === false) {
					if ($secure_post_only)
						error($config['error']['csrf']);
					else {
						mod_confirm($ctx, substr($query, 1));
						exit;
					}
				}

				// CSRF-protected page; validate security token
				$actual_query = preg_replace('!/([a-f0-9]{8})$!', '', $query);
				if ($token != make_secure_link_token(substr($actual_query, 1))) {
					error($config['error']['csrf']);
				}
			}
			$handler = preg_replace('/^secure(_POST)? /', '', $handler);
		}

		if ($config['debug']) {
			$debug['mod_page'] = [
				'req' => $query,
				'match' => $uri,
				'handler' => $handler,
			];
			$debug['time']['parse_mod_req'] = '~' . round((microtime(true) - $parse_start_time) * 1000, 2) . 'ms';
		}

		// We don't want to call named parameters (PHP 8).
		$matches = array_values($matches);

		if (is_string($handler)) {
			if ($handler[0] == ':') {
				header('Location: ' . substr($handler, 1),  true, $config['redirect_http']);
			} elseif (is_callable("mod_$handler")) {
				call_user_func_array("mod_$handler", $matches);
			} else {
				error("Mod page '$handler' not found!");
			}
		} elseif (is_callable($handler)) {
			call_user_func_array($handler, $matches);
		} else {
			error("Mod page '$handler' not a string, and not callable!");
		}

		exit;
	}
}

error($config['error']['404']);
