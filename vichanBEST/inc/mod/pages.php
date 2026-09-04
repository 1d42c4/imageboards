<?php
/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */
use Vichan\Context;
use Vichan\Functions\Format;
use Vichan\Functions\Net;
use Vichan\Data\Driver\CacheDriver;

defined('TINYBOARD') or exit;


function mod_page($title, $template, $args, $mod, $subtitle = false) {
	global $config;

	$options = [
		'config' => $config,
		'mod' => $mod,
		'hide_dashboard_link' => $template == $config['file_mod_dashboard'],
		'title' => $title,
		'subtitle' => $subtitle,
		'boardlist' => createBoardlist($mod),
		'body' => Element(
			$template,
			array_merge(
				[ 'config' => $config, 'mod' => $mod ],
				$args
			)
		)
	];

	echo Element($config['file_page_template'], $options);
}

function mod_login(Context $ctx, $redirect = false) {
	global $mod;
	$config = $ctx->get('config');

	$args = [];

	$secure_login_mode = $config['cookies']['secure_login_only'];
	if ($secure_login_mode !== 0 && !Net\is_connection_secure($secure_login_mode === 1)) {
		$args['error'] = $config['error']['insecure'];
	} elseif (isset($_POST['login'])) {
		// Check if inputs are set and not empty
		if (!isset($_POST['username'], $_POST['password']) || $_POST['username'] == '' || $_POST['password'] == '') {
			$args['error'] = $config['error']['invalid'];
		}
		elseif (strlen($_POST['password']) > 128 || strlen($_POST['username']) > 128) {
            $args['error'] = $config['error']['infotoolong'];
		} elseif (!login($_POST['username'], $_POST['password'])) {
			if ($config['syslog'])
				_syslog(LOG_WARNING, 'Unauthorized login attempt!');

			$args['error'] = $config['error']['invalid'];
		} else {
			modLog('Logged in');

			// Login successful
			// Set cookies
			setCookies();

			if ($redirect)
				header('Location: ?' . $redirect, true, $config['redirect_http']);
			else
				header('Location: ?/', true, $config['redirect_http']);
		}
	}

	if (isset($_POST['username']))
		$args['username'] = $_POST['username'];

	mod_page(_('Login'), $config['file_mod_login'], $args, $mod);
}

function mod_confirm(Context $ctx, $request) {
	global $mod;
	$config = $ctx->get('config');
	mod_page(
		_('Confirm action'),
		$config['file_mod_confim'],
		[
			'request' => $request,
			'token' => make_secure_link_token($request)
		],
		$mod
	);
}

function mod_logout(Context $ctx) {
	$config = $ctx->get('config');
	destroyCookies();

	header('Location: ?/', true, $config['redirect_http']);
}

function mod_dashboard(Context $ctx) {
	global $mod;
	$config = $ctx->get('config');

	$args = [];
	$args['boards'] = listBoards();

	$query = query('SELECT COUNT(*) FROM ``reports``') or error(db_error($query));
	$args['reports'] = $query->fetchColumn();
	$args['pending'] = function_exists('count_pending_posts_for_mod')
		? count_pending_posts_for_mod($mod)
		: 0;

	$args['logout_token'] = make_secure_link_token('logout');

	mod_page(_('Dashboard'), $config['file_mod_dashboard'], $args, $mod);
}

function mod_edit_board(Context $ctx, $boardName) {
	global $board, $config, $mod;

	$cache = $ctx->get(CacheDriver::class);

	if (!openBoard($boardName))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['manageboards'], $board['uri']))
			error($config['error']['noaccess']);

	if (isset($_POST['title'], $_POST['subtitle'])) {
		if (isset($_POST['delete'])) {
			if (!hasPermission($config['mod']['manageboards'], $board['uri']))
				error($config['error']['deleteboard']);

			$query = prepare('DELETE FROM ``boards`` WHERE `uri` = :uri');
			$query->bindValue(':uri', $board['uri']);
			$query->execute() or error(db_error($query));

			$cache->delete('board_' . $board['uri']);
			$cache->delete('all_boards');

			modLog('Deleted board: ' . sprintf($config['board_abbreviation'], $board['uri']), false);

			// Delete posting table
			$query = query(sprintf('DROP TABLE IF EXISTS ``posts_%s``', $board['uri'])) or error(db_error());

			// Clear reports
			$query = prepare('DELETE FROM ``reports`` WHERE `board` = :id');
			$query->bindValue(':id', $board['uri'], PDO::PARAM_STR);
			$query->execute() or error(db_error($query));

			// Delete from table
			$query = prepare('DELETE FROM ``boards`` WHERE `uri` = :uri');
			$query->bindValue(':uri', $board['uri'], PDO::PARAM_STR);
			$query->execute() or error(db_error($query));

			$query = prepare("SELECT `board`, `post` FROM ``cites`` WHERE `target_board` = :board ORDER BY `board`");
			$query->bindValue(':board', $board['uri']);
			$query->execute() or error(db_error($query));
			while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
				if ($board['uri'] != $cite['board']) {
					if (!isset($tmp_board))
						$tmp_board = $board;
					openBoard($cite['board']);
					rebuildPost($cite['post']);
				}
			}

			if (isset($tmp_board))
				$board = $tmp_board;

			$query = prepare('DELETE FROM ``cites`` WHERE `board` = :board OR `target_board` = :board');
			$query->bindValue(':board', $board['uri']);
			$query->execute() or error(db_error($query));

			// Remove board from users/permissions table
			$query = query('SELECT `id`,`boards` FROM ``mods``') or error(db_error());
			while ($user = $query->fetch(PDO::FETCH_ASSOC)) {
				$user_boards = explode(',', $user['boards']);
				if (in_array($board['uri'], $user_boards)) {
					unset($user_boards[array_search($board['uri'], $user_boards)]);
					$_query = prepare('UPDATE ``mods`` SET `boards` = :boards WHERE `id` = :id');
					$_query->bindValue(':boards', implode(',', $user_boards));
					$_query->bindValue(':id', $user['id']);
					$_query->execute() or error(db_error($_query));
				}
			}

			// Delete entire board directory
			rrmdir($board['uri'] . '/');
		} else {
			board_moderation_ensure_schema($board['uri']);

			// Independent toggles: either can be on/off per board
			$require_approval = !empty($_POST['require_approval']) ? 1 : 0;

			$post_password = $board['post_password'] ?? null;
			if (empty($_POST['enable_board_password'])) {
				// Password requirement off for this board
				$post_password = null;
			} elseif (isset($_POST['board_password']) && trim((string)$_POST['board_password']) !== '') {
				// Password on + new value provided
				$post_password = board_password_hash((string)$_POST['board_password']);
			} elseif (empty($post_password)) {
				// Turned on but no password stored and none typed
				error(_('Enter a board password, or turn the board-password option off.'));
			}
			// else: keep existing hash when still enabled and field left blank

			$query = prepare('UPDATE ``boards`` SET `title` = :title, `subtitle` = :subtitle, `require_approval` = :require_approval, `post_password` = :post_password WHERE `uri` = :uri');
			$query->bindValue(':uri', $board['uri']);
			$query->bindValue(':title', $_POST['title']);
			$query->bindValue(':subtitle', $_POST['subtitle']);
			$query->bindValue(':require_approval', $require_approval, PDO::PARAM_INT);
			if ($post_password === null) {
				$query->bindValue(':post_password', null, PDO::PARAM_NULL);
			} else {
				$query->bindValue(':post_password', $post_password);
			}
			$query->execute() or error(db_error($query));

			modLog('Edited board information for ' . sprintf($config['board_abbreviation'], $board['uri'])
				. ($require_approval ? ' [approval on]' : ' [approval off]')
				. ($post_password ? ' [board password on]' : ' [board password off]'), false);
		}

		$cache->delete('board_' . $board['uri']);
		$cache->delete('all_boards');


		Vichan\Functions\Theme\rebuild_themes('boards');

		header('Location: ?/', true, $config['redirect_http']);
	} else {
		mod_page(
			sprintf('%s: ' . $config['board_abbreviation'], _('Edit board'), $board['uri']),
			$config['file_mod_board'],
			[
				'board' => $board,
				'token' => make_secure_link_token('edit/' . $board['uri'])
			],
			$mod
		);
	}
}

function mod_new_board(Context $ctx) {
	global $board, $mod;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['newboard']))
		error($config['error']['noaccess']);

	if (isset($_POST['uri'], $_POST['title'], $_POST['subtitle'])) {
		if ($_POST['uri'] == '')
			error(sprintf($config['error']['required'], 'URI'));

		if ($_POST['title'] == '')
			error(sprintf($config['error']['required'], 'title'));

		if (!preg_match('/^' . $config['board_regex'] . '$/u', $_POST['uri']))
			error(sprintf($config['error']['invalidfield'], 'URI'));

		$cache = $ctx->get(CacheDriver::class);

		$bytes = 0;
		$chars = preg_split('//u', $_POST['uri'], -1, PREG_SPLIT_NO_EMPTY);
		foreach ($chars as $char) {
			$o = 0;
			$ord = ordutf8($char, $o);
			if ($ord > 0x0080)
				$bytes += 5; // @01ff
			else
				$bytes ++;
		}
		$bytes + strlen('posts_.frm');

		if ($bytes > 255) {
			error('Your filesystem cannot handle a board URI of that length (' . $bytes . '/255 bytes)');
			exit;
		}

		if (openBoard($_POST['uri'])) {
			error(sprintf($config['error']['boardexists'], $board['url']));
		}

		$query = prepare('INSERT INTO ``boards`` VALUES (:uri, :title, :subtitle)');
		$query->bindValue(':uri', $_POST['uri']);
		$query->bindValue(':title', $_POST['title']);
		$query->bindValue(':subtitle', $_POST['subtitle']);
		$query->execute() or error(db_error($query));

		modLog('Created a new board: ' . sprintf($config['board_abbreviation'], $_POST['uri']));

		if (!openBoard($_POST['uri']))
			error(_("Couldn't open board after creation."));

		$query = Element(db_schema_template('posts'), [ 'board' => $board['uri'] ]);

		// PostgreSQL schemas may contain multiple statements (table + indexes)
		foreach (db_split_sql_statements($query) as $stmt) {
			query($stmt) or error(db_error());
		}

		$cache = $ctx->get(CacheDriver::class);
		$cache->delete('all_boards');

		// Build the board
		buildIndex();
		if (function_exists('buildArchiveIndex') && archive_enabled()) {
			buildArchiveIndex();
		}

		Vichan\Functions\Theme\rebuild_themes('boards');

		header('Location: ?/' . $board['uri'] . '/' . $config['file_index'], true, $config['redirect_http']);
	}

	mod_page(
		_('New board'),
		$config['file_mod_board'],
		[
			'new' => true,
			'token' => make_secure_link_token('new-board')
		],
		$mod
	);
}

function mod_log(Context $ctx, $page_no = 1) {
	global $mod;
	$config = $ctx->get('config');

	if ($page_no < 1)
		error($config['error']['404']);

	if (!hasPermission($config['mod']['modlog']))
		error($config['error']['noaccess']);

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':limit', $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($logs) && $page_no > 1)
		error($config['error']['404']);

	$query = prepare("SELECT COUNT(*) FROM ``modlogs``");
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(_('Moderation log'), $config['file_mod_log'], [ 'logs' => $logs, 'count' => $count ], $mod);
}

function mod_user_log(Context $ctx, $username, $page_no = 1) {
	global $mod;
	$config = $ctx->get('config');

	if ($page_no < 1)
		error($config['error']['404']);

	if (!hasPermission($config['mod']['modlog']))
		error($config['error']['noaccess']);

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `username` = :username ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':username', $username);
	$query->bindValue(':limit', $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($logs) && $page_no > 1)
		error($config['error']['404']);

	$query = prepare("SELECT COUNT(*) FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `username` = :username");
	$query->bindValue(':username', $username);
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(_('Moderation log'), $config['file_mod_log'], [ 'logs' => $logs, 'count' => $count, 'username' => $username ], $mod);
}

function mod_board_log(Context $ctx, $board, $page_no = 1, $hide_names = false, $public = false) {
	global $mod;
	$config = $ctx->get('config');

	if ($page_no < 1)
		error($config['error']['404']);

	if (!hasPermission($config['mod']['mod_board_log'], $board) && !$public)
		error($config['error']['noaccess']);

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `board` = :board ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':board', $board);
	$query->bindValue(':limit', $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($logs) && $page_no > 1)
		error($config['error']['404']);

	if (!hasPermission($config['mod']['show_ip'])) {
		// Supports ipv4 only!
		foreach ($logs as $i => &$log) {
			$log['text'] = preg_replace_callback('/(?:<a href="\?\/IP\/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}">)?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?:<\/a>)?/', function($matches) {
				return "xxxx";//less_ip($matches[1]);
			}, $log['text']);
		}
	}

	$query = prepare("SELECT COUNT(*) FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `board` = :board");
	$query->bindValue(':board', $board);
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(
		_('Board log'),
		$config['file_mod_log'],
		[
			'logs' => $logs,
			'count' => $count,
			'board' => $board,
			'hide_names' => $hide_names,
			'public' => $public
		],
		$mod
	);
}

function mod_view_catalog(Context $ctx, $boardName) {
	$config = $ctx->get('config');
	if (!function_exists('catalog_build_board')) {
		require_once dirname(__DIR__) . '/catalog.php';
	}
	echo catalog_build_board($boardName, true);
}

function mod_view_board(Context $ctx, $boardName, $page_no = 1) {
	global $mod;
	$config = $ctx->get('config');

	if (!openBoard($boardName))
		error($config['error']['noboard']);

	if (!$page = index($page_no, $mod)) {
		error($config['error']['404']);
	}

	$page['pages'] = getPages(true);
	$page['pages'][$page_no - 1]['selected'] = true;
	$page['btn'] = getPageButtons($page['pages'], true);
	$page['mod'] = true;
	$page['config'] = $config;

	echo Element($config['file_board_index'], $page);
}

function mod_view_thread(Context $ctx, $boardName, $thread) {
	global $mod;
	$config = $ctx->get('config');

	if (!openBoard($boardName))
		error($config['error']['noboard']);

	$page = buildThread($thread, true, $mod);
	echo $page;
}

function mod_edit_ban(Context $ctx, $ban_id) {
	global $mod;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['edit_ban']))
		error($config['error']['noaccess']);

	$args['bans'] = Bans::find(null, false, true, $ban_id, $config['auto_maintenance']);
	$args['ban_id'] = $ban_id;
	$args['boards'] = listBoards();
	$args['current_board'] = isset($args['bans'][0]['board']) ? $args['bans'][0]['board'] : false;

	if (!$args['bans'])
		error($config['error']['404']);

	if (isset($_POST['new_ban'])) {

		$new_ban['mask'] = $args['bans'][0]['mask'];
		$new_ban['post'] = isset($args['bans'][0]['post']) ? $args['bans'][0]['post'] : false;
		$new_ban['board'] = $args['current_board'];

		if (isset($_POST['reason']))
			$new_ban['reason'] = $_POST['reason'];
		else
			$new_ban['reason'] = $args['bans'][0]['reason'];

		if (isset($_POST['length']) && !empty($_POST['length']))
			$new_ban['length'] = $_POST['length'];
		else
			$new_ban['length'] = false;

		if (isset($_POST['board'])) {
			if ($_POST['board'] == '*')
				$new_ban['board'] = false;
			else
				$new_ban['board'] = $_POST['board'];
		}

		Bans::new_ban($new_ban['mask'], $new_ban['reason'], $new_ban['length'], $new_ban['board'], false, $new_ban['post']);
		Bans::delete($ban_id);

		header('Location: ?/', true, $config['redirect_http']);
	}

	$args['token'] = make_secure_link_token('edit_ban/' . $ban_id);

	mod_page(_('Edit ban'), 'mod/edit_ban.html', $args, $mod);
}

function mod_ban(Context $ctx) {
	global $mod;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['ban']))
		error($config['error']['noaccess']);

	if (!isset($_POST['ip'], $_POST['reason'], $_POST['length'], $_POST['board'])) {
		mod_page(_('New ban'), $config['file_mod_ban_form'], [ 'token' => make_secure_link_token('ban') ], $mod);
		return;
	}

	Bans::new_ban($_POST['ip'], $_POST['reason'], $_POST['length'], $_POST['board'] == '*' ? false : $_POST['board']);

	if (isset($_POST['redirect']))
		header('Location: ' . $_POST['redirect'], true, $config['redirect_http']);
	else
		header('Location: ?/', true, $config['redirect_http']);
}

function mod_bans(Context $ctx) {
	global $mod;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['view_banlist']))
		error($config['error']['noaccess']);

	// IP storage disabled â€” ban list is meaningless / hidden
	if (!privacy_store_ip()) {
		mod_page(
			_('Ban list'),
			$config['file_page_template'],
			[
				'config' => $config,
				'mod' => $mod,
				'title' => _('Ban list'),
				'body' => '<div class="ban"><p>'
					. _('IP bans are disabled because this site does not store client IP addresses.')
					. '</p><p>' . _('Configure privacy and other settings in <code>inc/secrets.php</code> / <code>inc/instance-config.php</code>.')
					. '</p><p><a href="?/">' . _('Dashboard') . '</a></p></div>',
			],
			$mod
		);
		return;
	}

	if (isset($_POST['unban'])) {
		if (!hasPermission($config['mod']['unban']))
			error($config['error']['noaccess']);

		$unban = [];
		foreach ($_POST as $name => $unused) {
			if (preg_match('/^ban_(\d+)$/', $name, $match))
				$unban[] = $match[1];
		}
		if (isset($config['mod']['unban_limit']) && $config['mod']['unban_limit'] && count($unban) > $config['mod']['unban_limit'])
			error(sprintf($config['error']['toomanyunban'], $config['mod']['unban_limit'], count($unban)));

		foreach ($unban as $id) {
			Bans::delete($id, true, $mod['boards'], true);
		}
		Vichan\Functions\Theme\rebuild_themes('bans');
		header('Location: ?/bans', true, $config['redirect_http']);
		return;
	}

	mod_page(
		_('Ban list'),
		$config['file_mod_ban_list'],
		[
			'mod' => $mod,
			'boards' => json_encode($mod['boards']),
			'token' => make_secure_link_token('bans'),
			'token_json' => make_secure_link_token('bans.json')
		],
		$mod
	);
}

function mod_bans_json(Context $ctx) {
	global $mod;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['ban']))
		error($config['error']['noaccess']);

	// Compress the json for faster loads
	if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ob_start("ob_gzhandler");

	Bans::stream_json(false, false, !hasPermission($config['mod']['view_banstaff']), $mod['boards']);
}

function mod_lock(Context $ctx, $board, $unlock, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['lock'], $board))
		error($config['error']['noaccess']);

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `locked` = :locked WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':locked', $unlock ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($unlock ? 'Unlocked' : 'Locked') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	if ($config['mod']['dismiss_reports_on_lock']) {
		$query = prepare('DELETE FROM ``reports`` WHERE `board` = :board AND `post` = :id');
		$query->bindValue(':board', $board);
		$query->bindValue(':id', $post);
		$query->execute() or error(db_error($query));
	}

	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);

	if ($unlock)
		event('unlock', $post);
	else
		event('lock', $post);
}

/** Manually archive a live thread (read-only archive page; leaves the board). */
function mod_archive_thread(Context $ctx, $board, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board)) {
		error($config['error']['noboard']);
	}

	if (!archive_enabled()) {
		error(_('Archive is disabled.'));
	}

	if (!hasPermission($config['mod']['archive'], $board)) {
		error($config['error']['noaccess']);
	}

	$post = (int)$post;

	// Must be a live OP
	$check = prepare(sprintf(
		'SELECT `id` FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL LIMIT 1',
		$board
	));
	$check->bindValue(':id', $post, PDO::PARAM_INT);
	$check->execute() or error(db_error($check));
	if (!$check->fetchColumn()) {
		error($config['error']['invalidpost']);
	}

	if (!archiveThread($post, true)) {
		error(_('Could not archive that thread.'));
	}

	buildIndex();

	// Public archive page (mod overlay does not serve archive HTML)
	header(
		'Location: ' . $config['root'] . sprintf($config['board_path'], $board)
			. ($config['archive']['dir'] ?? 'archive/')
			. sprintf($config['archive']['file_page'] ?? '%d.html', $post),
		true,
		$config['redirect_http']
	);
}

function mod_sticky(Context $ctx, $board, $unsticky, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['sticky'], $board))
		error($config['error']['noaccess']);

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `sticky` = :sticky WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':sticky', $unsticky ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($unsticky ? 'Unstickied' : 'Stickied') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_bumplock(Context $ctx, $board, $unbumplock, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['bumplock'], $board))
		error($config['error']['noaccess']);

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `sage` = :bumplock WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':bumplock', $unbumplock ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($unbumplock ? 'Unbumplocked' : 'Bumplocked') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_ban_post(Context $ctx, $board, $delete, $post, $token = false) {
	global $mod;
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['ban'], $board))
		error($config['error']['noaccess']);

	$security_token = make_secure_link_token($board . '/ban/' . $post);

	$query = prepare(sprintf('SELECT ' . ($config['ban_show_post'] ? '*' : '`ip`, `thread`') .
		' FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $post);
	$query->execute() or error(db_error($query));
	if (!$_post = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	$thread = $_post['thread'];
	$ip = $_post['ip'];

	if (isset($_POST['new_ban'], $_POST['reason'], $_POST['length'], $_POST['board'])) {
		if (isset($_POST['ip']))
			$ip = $_POST['ip'];

		Bans::new_ban($ip, $_POST['reason'], $_POST['length'], $_POST['board'] == '*' ? false : $_POST['board'],
			false, $config['ban_show_post'] ? $_post : false);

		if (isset($_POST['public_message'], $_POST['message'])) {
			// public ban message
			$length_english = Bans::parse_time($_POST['length']) ? 'for ' . Format\until(Bans::parse_time($_POST['length'])) : 'permanently';
			$_POST['message'] = preg_replace('/[\r\n]/', '', $_POST['message']);
			$_POST['message'] = str_replace('%length%', $length_english, $_POST['message']);
			$_POST['message'] = str_replace('%LENGTH%', strtoupper($length_english), $_POST['message']);
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `body_nomarkup` = CONCAT(`body_nomarkup`, :body_nomarkup) WHERE `id` = :id', $board));
			$query->bindValue(':id', $post);
			$query->bindValue(':body_nomarkup', sprintf("\n<tinyboard ban message>%s</tinyboard>", utf8tohtml($_POST['message'])));
			$query->execute() or error(db_error($query));
			rebuildPost($post);

			modLog("Attached a public ban message to post #{$post}: " . utf8tohtml($_POST['message']));
			buildThread($thread ? $thread : $post);
			buildIndex();
		} elseif (isset($_POST['delete']) && (int) $_POST['delete']) {
			// Delete post
			deletePost($post);
			modLog("Deleted post #{$post}");
			// Rebuild board
			buildIndex();
			// Rebuild themes
			Vichan\Functions\Theme\rebuild_themes('post-delete', $board);
		}

		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
	}

	$args = array(
		'ip' => $ip,
		'hide_ip' => !hasPermission($config['mod']['show_ip'], $board),
		'post' => $post,
		'board' => $board,
		'delete' => (bool)$delete,
		'boards' => listBoards(),
		'reasons' => $config['premade_ban_reasons'],
		'token' => $security_token
	);

	mod_page(_('New ban'), $config['file_mod_ban_form'], $args, $mod);
}

function mod_edit_post(Context $ctx, $board, $edit_raw_html, $postID) {
	global $mod;
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['editpost'], $board))
		error($config['error']['noaccess']);

	if ($edit_raw_html && !hasPermission($config['mod']['rawhtml'], $board))
		error($config['error']['noaccess']);

	$security_token = make_secure_link_token($board . '/edit' . ($edit_raw_html ? '_raw' : '') . '/' . $postID);

	$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $postID);
	$query->execute() or error(db_error($query));

	if (!$post = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	$is_op = empty($post['thread']);
	$files = [];
	if (!empty($post['files'])) {
		$decoded = json_decode($post['files'], true);
		if (is_array($decoded)) {
			$files = $decoded;
		}
	}

	if (isset($_POST['name'], $_POST['email'], $_POST['subject'], $_POST['body'])) {
		// Remove any modifiers they may have put in
		$_POST['body'] = remove_modifiers($_POST['body']);

		// Add back modifiers in the original post
		$modifiers = extract_modifiers($post['body_nomarkup']);
		foreach ($modifiers as $key => $value) {
			$_POST['body'] .= "<tinyboard $key>$value</tinyboard>";
		}

		// --- Image remove / replace (optional) ---
		$new_files_json = null;
		$new_num_files = null;
		$new_filehash = null;
		$image_note = '';

		$has_upload = isset($_FILES['file'])
			&& is_array($_FILES['file'])
			&& (int)$_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE
			&& !empty($_FILES['file']['tmp_name']);

		if ($has_upload || !empty($_POST['remove_file'])) {
			require_once dirname(__DIR__) . '/image.php';
		}

		if ($has_upload) {
			if ((int)$_FILES['file']['error'] !== UPLOAD_ERR_OK) {
				error(_('Upload failed.'));
			}

			// Drop previous images from disk
			foreach ($files as $old) {
				unlink_board_file_entry($old);
			}

			$new = process_board_image_upload(
				$_FILES['file']['tmp_name'],
				$_FILES['file']['name'] ?? 'upload',
				$is_op
			);
			// Store relative names in JSON (same shape as public posts)
			$entry = [
				'name' => $new['filename'],
				'filename' => $new['filename'],
				'extension' => $new['extension'],
				'file' => $new['file'],
				'thumb' => $new['thumb'],
				'is_an_image' => true,
				'hash' => $new['hash'],
				'size' => $new['size'],
				'width' => $new['width'],
				'height' => $new['height'],
				'thumbwidth' => $new['thumbwidth'],
				'thumbheight' => $new['thumbheight'],
			];
			$new_files_json = json_encode([$entry]);
			$new_num_files = 1;
			$new_filehash = $new['hash'];
			$image_note = ' (replaced image)';
		} elseif (!empty($_POST['remove_file'])) {
			// Mark images deleted; keep post text
			if ($files) {
				foreach ($files as $i => $old) {
					unlink_board_file_entry($old);
					$files[$i] = [
						'file' => 'deleted',
						'thumb' => false,
					];
				}
				$new_files_json = json_encode(array_values($files));
				$new_num_files = count($files);
				$new_filehash = null;
				$image_note = ' (removed image)';
			}
		}

		if ($edit_raw_html) {
			$sql = sprintf(
				'UPDATE ``posts_%s`` SET `name` = :name, `email` = :email, `subject` = :subject, `body` = :body, `body_nomarkup` = :body_nomarkup',
				$board
			);
		} else {
			$sql = sprintf(
				'UPDATE ``posts_%s`` SET `name` = :name, `email` = :email, `subject` = :subject, `body_nomarkup` = :body',
				$board
			);
		}
		if ($new_files_json !== null) {
			$sql .= ', `files` = :files, `num_files` = :num_files, `filehash` = :filehash';
		}
		$sql .= ' WHERE `id` = :id';

		$query = prepare($sql);
		$query->bindValue(':id', $postID);
		$query->bindValue(':name', $_POST['name']);
		$query->bindValue(':email', $_POST['email']);
		$query->bindValue(':subject', $_POST['subject']);
		$query->bindValue(':body', $_POST['body']);
		if ($edit_raw_html) {
			$body_nomarkup = $_POST['body'] . "\n<tinyboard raw html>1</tinyboard>";
			$query->bindValue(':body_nomarkup', $body_nomarkup);
		}
		if ($new_files_json !== null) {
			$query->bindValue(':files', $new_files_json);
			$query->bindValue(':num_files', $new_num_files, PDO::PARAM_INT);
			if ($new_filehash === null) {
				$query->bindValue(':filehash', null, PDO::PARAM_NULL);
			} else {
				$query->bindValue(':filehash', $new_filehash);
			}
		}
		$query->execute() or error(db_error($query));

		if ($edit_raw_html) {
			modLog("Edited raw HTML of post #{$postID}{$image_note}");
			// Still rebuild thread so file changes show
			buildThread($is_op ? $postID : (int)$post['thread']);
		} else {
			modLog("Edited post #{$postID}{$image_note}");
			rebuildPost($postID);
		}

		buildIndex();
		Vichan\Functions\Theme\rebuild_themes('post', $board);

		// Refresh post row for redirect link
		$post['id'] = $postID;
		header(
			'Location: ?/' . sprintf($config['board_path'], $board) . $config['dir']['res']
				. link_for($post) . '#' . $postID,
			true,
			$config['redirect_http']
		);
		return;
	}

	// Remove modifiers for display
	$post['body_nomarkup'] = remove_modifiers($post['body_nomarkup']);

	$post['body_nomarkup'] = utf8tohtml($post['body_nomarkup']);
	$post['body'] = utf8tohtml($post['body']);
	if ($config['minify_html']) {
		$post['body_nomarkup'] = str_replace("\n", '&#010;', $post['body_nomarkup']);
		$post['body'] = str_replace("\n", '&#010;', $post['body']);
		$post['body_nomarkup'] = str_replace("\r", '', $post['body_nomarkup']);
		$post['body'] = str_replace("\r", '', $post['body']);
		$post['body_nomarkup'] = str_replace("\t", '&#09;', $post['body_nomarkup']);
		$post['body'] = str_replace("\t", '&#09;', $post['body']);
	}

	mod_page(
		_('Edit post'),
		$config['file_mod_edit_post_form'],
		[
			'token' => $security_token,
			'board' => $board,
			'raw' => $edit_raw_html,
			'post' => $post,
			'files' => $files,
		],
		$mod
	);
}

function mod_delete(Context $ctx, $board, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['delete'], $board))
		error($config['error']['noaccess']);

	// Delete post
	deletePost($post);
	// Record the action
	modLog("Deleted post #{$post}");
	// Rebuild board
	buildIndex();
	// Rebuild themes
	Vichan\Functions\Theme\rebuild_themes('post-delete', $board);
	// Redirect
	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_deletefile(Context $ctx, $board, $post, $file) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['deletefile'], $board))
		error($config['error']['noaccess']);

	// Delete file (keeps post text; marks file as deleted)
	deleteFile($post, true, $file);
	// Record the action
	modLog("Deleted file from post #{$post}");

	// Rebuild board + catalog
	buildIndex();
	Vichan\Functions\Theme\rebuild_themes('post-delete', $board);

	// Stay on the thread when possible
	$query = prepare(sprintf('SELECT `thread` FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$row = $query->fetch(PDO::FETCH_ASSOC);
	$thread = $row && $row['thread'] ? (int)$row['thread'] : (int)$post;
	header(
		'Location: ?/' . sprintf($config['board_path'], $board) . $config['dir']['res']
			. link_for(['id' => $thread, 'thread' => null]) . '#' . (int)$post,
		true,
		$config['redirect_http']
	);
}

function mod_spoiler_image(Context $ctx, $board, $post, $file) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['spoilerimage'], $board))
		error($config['error']['noaccess']);

	// Delete file thumbnail
	$query = prepare(sprintf("SELECT `files`, `thread` FROM ``posts_%s`` WHERE id = :id", $board));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$result = $query->fetch(PDO::FETCH_ASSOC);
	$files = json_decode($result['files']);


	$size_spoiler_image = @getimagesize($config['spoiler_image']);
	file_unlink($board . '/' . $config['dir']['thumb'] . $files[$file]->thumb);
	$files[$file]->thumb = 'spoiler';
	$files[$file]->thumbwidth = $size_spoiler_image[0];
	$files[$file]->thumbheight = $size_spoiler_image[1];

	// Make thumbnail spoiler
	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `files` = :files WHERE `id` = :id", $board));
	$query->bindValue(':files', json_encode($files));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	// Record the action
	modLog("Spoilered file from post #{$post}");

	// Rebuild thread
	buildThread($result['thread'] ? $result['thread'] : $post);

	// Rebuild board
	buildIndex();

	// Rebuild themes
	Vichan\Functions\Theme\rebuild_themes('post-delete', $board);

	// Redirect
	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_deletebyip(Context $ctx, $boardName, $post, $global = false) {
	global $board;
	$config = $ctx->get('config');

	$global = (bool)$global;

	if (!openBoard($boardName))
		error($config['error']['noboard']);

	if (!$global && !hasPermission($config['mod']['deletebyip'], $boardName))
		error($config['error']['noaccess']);

	if ($global && !hasPermission($config['mod']['deletebyip_global'], $boardName))
		error($config['error']['noaccess']);

	if (!privacy_store_ip()) {
		error(_('Delete-by-IP is unavailable: client IPs are not stored.'));
	}

	// Find IP address
	$query = prepare(sprintf('SELECT `ip` FROM ``posts_%s`` WHERE `id` = :id', $boardName));
	$query->bindValue(':id', $post);
	$query->execute() or error(db_error($query));
	if (!$ip = $query->fetchColumn())
		error($config['error']['invalidpost']);

	$boards = $global ? listBoards() : array(array('uri' => $boardName));

	$query = '';
	foreach ($boards as $_board) {
		$query .= sprintf("SELECT `thread`, `id`, '%s' AS `board` FROM ``posts_%s`` WHERE `ip` = :ip UNION ALL ", $_board['uri'], $_board['uri']);
	}
	$query = preg_replace('/UNION ALL $/', '', $query);

	$query = prepare($query);
	$query->bindValue(':ip', $ip);
	$query->execute() or error(db_error($query));

	if ($query->rowCount() < 1)
		error($config['error']['invalidpost']);

	@set_time_limit($config['mod']['rebuild_timelimit']);

	$threads_to_rebuild = [];
	$threads_deleted = [];
	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		openBoard($post['board']);

		deletePost($post['id'], false, false);

		Vichan\Functions\Theme\rebuild_themes('post-delete', $board['uri']);

		buildIndex();

		if ($post['thread'])
			$threads_to_rebuild[$post['board']][$post['thread']] = true;
		else
			$threads_deleted[$post['board']][$post['id']] = true;
	}

	foreach ($threads_to_rebuild as $_board => $_threads) {
		openBoard($_board);
		foreach ($_threads as $_thread => $_dummy) {
			if ($_dummy && !isset($threads_deleted[$_board][$_thread]))
				buildThread($_thread);
		}
		buildIndex();
	}

	if ($global) {
		$board = false;
	}

	// Record the action
	$cip = cloak_ip($ip);
	modLog("Deleted all posts by IP address: <a href=\"?/IP/$cip\">$cip</a>");

	// Redirect
	header('Location: ?/' . sprintf($config['board_path'], $boardName) . $config['file_index'], true, $config['redirect_http']);
}

function mod_user(Context $ctx, $uid) {
	global $mod;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['editusers']) && !(hasPermission($config['mod']['change_password']) && $uid == $mod['id']))
		error($config['error']['noaccess']);

	$query = prepare('SELECT * FROM ``mods`` WHERE `id` = :id');
	$query->bindValue(':id', $uid);
	$query->execute() or error(db_error($query));
	if (!$user = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	if (hasPermission($config['mod']['editusers']) && isset($_POST['username'], $_POST['password'])) {
		if (isset($_POST['allboards'])) {
			$boards = array('*');
		} else {
			$_boards = listBoards();
			foreach ($_boards as &$board) {
				$board = $board['uri'];
			}

			$boards = [];
			foreach ($_POST as $name => $value) {
				if (preg_match('/^board_(' . $config['board_regex'] . ')$/u', $name, $matches) && in_array($matches[1], $_boards))
					$boards[] = $matches[1];
			}
		}

		if (isset($_POST['delete'])) {
			if (!hasPermission($config['mod']['deleteusers']))
				error($config['error']['noaccess']);

			$query = prepare('DELETE FROM ``mods`` WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->execute() or error(db_error($query));

			modLog('Deleted user ' . utf8tohtml($user['username']) . ' <small>(#' . $user['id'] . ')</small>');

			header('Location: ?/users', true, $config['redirect_http']);

			return;
		}

		if ($_POST['username'] == '')
			error(sprintf($config['error']['required'], 'username'));

		$query = prepare('UPDATE ``mods`` SET `username` = :username, `boards` = :boards WHERE `id` = :id');
		$query->bindValue(':id', $uid);
		$query->bindValue(':username', $_POST['username']);
		$query->bindValue(':boards', implode(',', $boards));
		$query->execute() or error(db_error($query));

		if ($user['username'] !== $_POST['username']) {
			// account was renamed
			modLog('Renamed user "' . utf8tohtml($user['username']) . '" <small>(#' . $user['id'] . ')</small> to "' . utf8tohtml($_POST['username']) . '"');
		}

		if ($_POST['password'] != '') {
			list($version, $password) = crypt_password($_POST['password']);

			$query = prepare('UPDATE ``mods`` SET `password` = :password, `version` = :version WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->bindValue(':password', $password);
			$query->bindValue(':version', $version);
			$query->execute() or error(db_error($query));

			modLog('Changed password for ' . utf8tohtml($_POST['username']) . ' <small>(#' . $user['id'] . ')</small>');

			if ($uid == $mod['id']) {
				login($_POST['username'], $_POST['password']);
				setCookies();
			}
		}

		if (hasPermission($config['mod']['manageusers']))
			header('Location: ?/users', true, $config['redirect_http']);
		else
			header('Location: ?/', true, $config['redirect_http']);

		return;
	}

	if (hasPermission($config['mod']['change_password']) && $uid == $mod['id'] && isset($_POST['password'])) {
		if ($_POST['password'] != '') {
			list($version, $password) = crypt_password($_POST['password']);

			$query = prepare('UPDATE ``mods`` SET `password` = :password, `version` = :version WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->bindValue(':password', $password);
			$query->bindValue(':version', $version);
			$query->execute() or error(db_error($query));

			modLog('Changed own password');

			login($user['username'], $_POST['password']);
			setCookies();
		}

		if (hasPermission($config['mod']['manageusers']))
			header('Location: ?/users', true, $config['redirect_http']);
		else
			header('Location: ?/', true, $config['redirect_http']);

		return;
	}

	if (hasPermission($config['mod']['modlog'])) {
		$query = prepare('SELECT * FROM ``modlogs`` WHERE `mod` = :id ORDER BY `time` DESC LIMIT 5');
		$query->bindValue(':id', $uid);
		$query->execute() or error(db_error($query));
		$log = $query->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$log = [];
	}

	$user['boards'] = explode(',', $user['boards']);

	mod_page(
		_('Edit user'),
		$config['file_mod_user'],
		[
			'user' => $user,
			'logs' => $log,
			'boards' => listBoards(),
			'token' => make_secure_link_token('users/' . $user['id'])
		],
		$mod
	);
}

function mod_user_new(Context $ctx) {
	global $pdo, $config, $mod;

	if (!hasPermission($config['mod']['createusers']))
		error($config['error']['noaccess']);

	if (isset($_POST['username'], $_POST['password'], $_POST['type'])) {
		if ($_POST['username'] == '')
			error(sprintf($config['error']['required'], 'username'));
		if ($_POST['password'] == '')
			error(sprintf($config['error']['required'], 'password'));

		if (isset($_POST['allboards'])) {
			$boards = array('*');
		} else {
			$_boards = listBoards();
			foreach ($_boards as &$board) {
				$board = $board['uri'];
			}

			$boards = [];
			foreach ($_POST as $name => $value) {
				if (preg_match('/^board_(' . $config['board_regex'] . ')$/u', $name, $matches) && in_array($matches[1], $_boards))
					$boards[] = $matches[1];
			}
		}

		$type = (int)$_POST['type'];
		if (!isset($config['mod']['groups'][$type]) || $type == DISABLED)
			error(sprintf($config['error']['invalidfield'], 'type'));

		list($version, $password) = crypt_password($_POST['password']);

		$query = prepare('INSERT INTO ``mods`` VALUES (NULL, :username, :password, :version, :type, :boards)');
		$query->bindValue(':username', $_POST['username']);
		$query->bindValue(':password', $password);
		$query->bindValue(':version', $version);
		$query->bindValue(':type', $type);
		$query->bindValue(':boards', implode(',', $boards));
		$query->execute() or error(db_error($query));

		$userID = $pdo->lastInsertId();

		modLog('Created a new user: ' . utf8tohtml($_POST['username']) . ' <small>(#' . $userID . ')</small>');

		header('Location: ?/users', true, $config['redirect_http']);
		return;
	}

	mod_page(
		_('New user'),
		$config['file_mod_user'],
		[
			'new' => true,
			'boards' => listBoards(),
			'token' => make_secure_link_token('users/new')
		],
		$mod
	);
}


function mod_users(Context $ctx) {
	global $mod;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['manageusers']))
		error($config['error']['noaccess']);

	$query = query("SELECT
		*,
		(SELECT `time` FROM ``modlogs`` WHERE `mod` = `id` ORDER BY `time` DESC LIMIT 1) AS `last`,
		(SELECT `text` FROM ``modlogs`` WHERE `mod` = `id` ORDER BY `time` DESC LIMIT 1) AS `action`
		FROM ``mods`` ORDER BY `type` DESC,`id`") or error(db_error());
	$users = $query->fetchAll(PDO::FETCH_ASSOC);

	$group_names = $config['mod']['groups'] ?? [];
	$types = array_keys($group_names);
	sort($types, SORT_NUMERIC);
	$min_type = $types ? (int)$types[0] : 0;
	$max_type = $types ? (int)$types[count($types) - 1] : 0;

	foreach ($users as &$user) {
		$user['promote_token'] = make_secure_link_token("users/{$user['id']}/promote");
		$user['demote_token'] = make_secure_link_token("users/{$user['id']}/demote");
		$t = (int)$user['type'];
		$user['type_label'] = $group_names[$t] ?? ('type ' . $t);
		if ($user['boards'] === '' || $user['boards'] === null) {
			$user['boards_label'] = _('none');
		} elseif ($user['boards'] === '*') {
			$user['boards_label'] = _('all boards');
		} else {
			$parts = [];
			foreach (explode(',', $user['boards']) as $b) {
				$b = trim($b);
				$parts[] = $b === '*' ? '*' : sprintf($config['board_abbreviation'], $b);
			}
			sort($parts);
			$user['boards_label'] = implode(', ', $parts);
		}
		$user['can_promote'] = $t < $max_type;
		$user['can_demote'] = $t > $min_type;
	}
	unset($user);

	mod_page(sprintf('%s (%d)', _('Manage users'), count($users)), $config['file_mod_users'], [ 'users' => $users ], $mod);
}

function mod_user_promote(Context $ctx, $uid, $action) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['promoteusers']))
		error($config['error']['noaccess']);

	$query = prepare("SELECT `type`, `username` FROM ``mods`` WHERE `id` = :id");
	$query->bindValue(':id', $uid);
	$query->execute() or error(db_error($query));

	if (!$mod = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	$new_group = false;

	$groups = $config['mod']['groups'];
	if ($action == 'demote')
		$groups = array_reverse($groups, true);

	foreach ($groups as $group_value => $group_name) {
		if ($action == 'promote' && $group_value > $mod['type']) {
			$new_group = $group_value;
			break;
		} elseif ($action == 'demote' && $group_value < $mod['type']) {
			$new_group = $group_value;
			break;
		}
	}

	if ($new_group === false || $new_group == DISABLED)
		error(_('Impossible to promote/demote user.'));

	$query = prepare("UPDATE ``mods`` SET `type` = :group_value WHERE `id` = :id");
	$query->bindValue(':id', $uid);
	$query->bindValue(':group_value', $new_group);
	$query->execute() or error(db_error($query));

	modLog(($action == 'promote' ? 'Promoted' : 'Demoted') . ' user "' .
		utf8tohtml($mod['username']) . '" to ' . $config['mod']['groups'][$new_group]);

	header('Location: ?/users', true, $config['redirect_http']);
}

function mod_rebuild(Context $ctx) {
	global $mod;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['rebuild']))
		error($config['error']['noaccess']);

	$cache = $ctx->get(CacheDriver::class);

	if (isset($_POST['rebuild'])) {
		@set_time_limit($config['mod']['rebuild_timelimit']);

		$log = [];
		$boards = listBoards();
		$rebuilt_scripts = [];

		if (isset($_POST['rebuild_cache'])) {
			if ($config['cache']['enabled']) {
				$log[] = 'Flushing cache';
				$cache->flush();
			}

			$log[] = 'Clearing template cache';
			clear_template_cache();
		}

		if (isset($_POST['rebuild_themes'])) {
			$log[] = 'Regenerating catalog & board list';
			Vichan\Functions\Theme\rebuild_themes('all');
		}

		if (isset($_POST['rebuild_javascript'])) {
			$log[] = 'Rebuilding <strong>' . $config['file_script'] . '</strong>';
			buildJavascript();
			$rebuilt_scripts[] = $config['file_script'];
		}

		foreach ($boards as $board) {
			if (!(isset($_POST['boards_all']) || isset($_POST['board_' . $board['uri']])))
				continue;

			openBoard($board['uri']);
			$config['try_smarter'] = false;

			if (isset($_POST['rebuild_index'])) {
				buildIndex();
				$log[] = '<strong>' . sprintf($config['board_abbreviation'], $board['uri']) . '</strong>: Creating index pages';
			}

			if (isset($_POST['rebuild_javascript']) && !in_array($config['file_script'], $rebuilt_scripts)) {
				$log[] = '<strong>' . sprintf($config['board_abbreviation'], $board['uri']) . '</strong>: Rebuilding <strong>' . $config['file_script'] . '</strong>';
				buildJavascript();
				$rebuilt_scripts[] = $config['file_script'];
			}

			if (isset($_POST['rebuild_thread'])) {
				$query = query(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL", $board['uri'])) or error(db_error());
				while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
					$log[] = '<strong>' . sprintf($config['board_abbreviation'], $board['uri']) . '</strong>: Rebuilding thread #' . $post['id'];
					buildThread($post['id']);
				}
				if (function_exists('rebuildArchive') && archive_enabled()) {
					rebuildArchive();
					$log[] = '<strong>' . sprintf($config['board_abbreviation'], $board['uri']) . '</strong>: Rebuilt archive';
				}
			}
		}

		mod_page(_('Rebuild'), $config['file_mod_rebuilt'], [ 'logs' => $log ], $mod);
		return;
	}

	mod_page(
		_('Rebuild'),
		$config['file_mod_rebuild'],
		[
			'boards' => listBoards(),
			'token' => make_secure_link_token('rebuild')
		],
		$mod
	);
}

/** Queue of posts held for approval (board require_approval). */
function mod_pending(Context $ctx) {
	global $mod, $config, $board;

	if (!hasPermission($config['mod']['approve_posts'])) {
		error($config['error']['noaccess']);
	}

	$items = [];
	foreach (listBoards() ?: [] as $b) {
		if (!hasPermission($config['mod']['approve_posts'], $b['uri'])) {
			continue;
		}
		if (!openBoard($b['uri'])) {
			continue;
		}
		board_moderation_ensure_schema($b['uri']);
		$query = query(sprintf(
			'SELECT * FROM ``posts_%s`` WHERE COALESCE(pending, 0) = 1 ORDER BY `time` ASC LIMIT 100',
			$b['uri']
		)) or error(db_error());
		while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
			$files_html = '';
			if (!empty($row['files'])) {
				$files = json_decode($row['files'], true);
				if (is_array($files)) {
					foreach ($files as $f) {
						if (!empty($f['thumb']) && !in_array($f['thumb'], ['spoiler', 'deleted', 'file'], true)) {
							$files_html .= '<img src="' . htmlspecialchars($config['root'] . $board['dir'] . $config['dir']['thumb'] . $f['thumb'], ENT_QUOTES, 'UTF-8') . '" alt="" style="max-width:120px;max-height:120px;margin:2px;" />';
						}
					}
				}
			}
			$items[] = [
				'board' => $b['uri'],
				'id' => (int)$row['id'],
				'thread' => $row['thread'] ? (int)$row['thread'] : null,
				'time' => (int)$row['time'],
				'name' => $row['name'],
				'subject' => $row['subject'],
				'body' => $row['body'],
				'files_html' => $files_html,
				'approve_token' => make_secure_link_token('pending/' . $b['uri'] . '/' . $row['id'] . '/approve'),
				'reject_token' => make_secure_link_token('pending/' . $b['uri'] . '/' . $row['id'] . '/reject'),
			];
		}
	}

	// Newest first overall
	usort($items, static function ($a, $b) {
		return $a['time'] <=> $b['time'];
	});

	mod_page(
		_('Post approval queue'),
		$config['file_mod_pending'],
		['posts' => $items],
		$mod
	);
}

function mod_pending_action(Context $ctx, $boardName, $postId, $action) {
	global $mod, $config, $board;

	if (!openBoard($boardName)) {
		error($config['error']['noboard']);
	}
	if (!hasPermission($config['mod']['approve_posts'], $boardName)) {
		error($config['error']['noaccess']);
	}

	board_moderation_ensure_schema($boardName);
	$postId = (int)$postId;

	$query = prepare(sprintf(
		'SELECT * FROM ``posts_%s`` WHERE `id` = :id AND COALESCE(pending, 0) = 1 LIMIT 1',
		$boardName
	));
	$query->bindValue(':id', $postId, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$row = $query->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		error($config['error']['invalidpost']);
	}

	if ($action === 'reject') {
		deletePost($postId, false, true);
		modLog("Rejected pending post #{$postId}");
		header('Location: ?/pending', true, $config['redirect_http']);
		return;
	}

	// Approve
	$upd = prepare(sprintf(
		'UPDATE ``posts_%s`` SET `pending` = 0 WHERE `id` = :id',
		$boardName
	));
	$upd->bindValue(':id', $postId, PDO::PARAM_INT);
	$upd->execute() or error(db_error($upd));

	// Rebuild body / cite tracking now that the post is public
	if (function_exists('rebuildPost')) {
		rebuildPost($postId);
	}

	// Bump parent thread if this was a reply
	if (!empty($row['thread'])) {
		$threadId = (int)$row['thread'];
		// Refresh bump time to this post's time if not sage
		if (empty($row['email']) || strtolower($row['email']) !== 'sage') {
			$bq = prepare(sprintf(
				'UPDATE ``posts_%s`` SET `bump` = :bump WHERE `id` = :id AND `thread` IS NULL AND COALESCE(`sage`, 0) = 0',
				$boardName
			));
			$bq->bindValue(':bump', (int)$row['time'], PDO::PARAM_INT);
			$bq->bindValue(':id', $threadId, PDO::PARAM_INT);
			$bq->execute() or error(db_error($bq));
		}
		buildThread($threadId);
	} else {
		buildThread($postId);
		clean($postId);
	}

	buildIndex();
	modLog("Approved pending post #{$postId}");
	header('Location: ?/pending', true, $config['redirect_http']);
}

function mod_reports(Context $ctx) {
	global $mod;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['reports']))
		error($config['error']['noaccess']);

	$query = prepare("SELECT * FROM ``reports`` ORDER BY `time` DESC LIMIT :limit");
	$query->bindValue(':limit', $config['mod']['recent_reports'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$reports = $query->fetchAll(PDO::FETCH_ASSOC);

	$report_queries = [];
	foreach ($reports as $report) {
		if (!isset($report_queries[$report['board']]))
			$report_queries[$report['board']] = [];
		$report_queries[$report['board']][] = $report['post'];
	}

	$report_posts = [];
	foreach ($report_queries as $board => $posts) {
		$report_posts[$board] = [];

		$query = query(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = ' . implode(' OR `id` = ', $posts), $board)) or error(db_error());
		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$report_posts[$board][$post['id']] = $post;
		}
	}

	$count = 0;
	$body = '';
	foreach ($reports as $report) {
		if (!isset($report_posts[$report['board']][$report['post']])) {
			// // Invalid report (post has since been deleted)
			$query = prepare("DELETE FROM ``reports`` WHERE `post` = :id AND `board` = :board");
			$query->bindValue(':id', $report['post'], PDO::PARAM_INT);
			$query->bindValue(':board', $report['board']);
			$query->execute() or error(db_error($query));
			continue;
		}

		openBoard($report['board']);

		$post = &$report_posts[$report['board']][$report['post']];

		if (!$post['thread']) {
			// Still need to fix this:
			$po = new Thread($post, '?/', $mod, false);
		} else {
			$po = new Post($post, '?/', $mod);
		}

		// a little messy and inefficient
		$append_html = Element($config['file_mod_report'], [
			'report' => $report,
			'config' => $config,
			'mod' => $mod,
			'token' => make_secure_link_token('reports/' . $report['id'] . '/dismiss'),
			'token_all' => make_secure_link_token('reports/' . $report['id'] . '/dismiss&all'),
			'token_post' => make_secure_link_token('reports/'. $report['id'] . '/dismiss&post'),
		]);

		// Bug fix for https://github.com/savetheinternet/Tinyboard/issues/21
		$po->body = truncate($po->body, $po->link(), $config['body_truncate'] - substr_count($append_html, '<br>'));

		if (mb_strlen($po->body) + mb_strlen($append_html) > $config['body_truncate_char']) {
			// still too long; temporarily increase limit in the config
			$__old_body_truncate_char = $config['body_truncate_char'];
			$config['body_truncate_char'] = mb_strlen($po->body) + mb_strlen($append_html);
		}

		$po->body .= $append_html;

		$body .= $po->build(true) . '<hr>';

		if (isset($__old_body_truncate_char))
			$config['body_truncate_char'] = $__old_body_truncate_char;

		$count++;
	}

	mod_page(
		sprintf('%s (%d)', _('Report queue'), $count),
		$config['file_mod_reports'],
		[
			'reports' => $body,
			'count' => $count
		],
		$mod
	);
}

function mod_report_dismiss(Context $ctx, $id, $action) {
	$config = $ctx->get('config');

	$query = prepare("SELECT `post`, `board`, `ip` FROM ``reports`` WHERE `id` = :id");
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));
	if ($report = $query->fetch(PDO::FETCH_ASSOC)) {
		$ip = $report['ip'];
		$board = $report['board'];
		$post = $report['post'];
	} else
		error($config['error']['404']);

	switch($action){
		case '&post':
			if (!hasPermission($config['mod']['report_dismiss_post'], $board))
				error($config['error']['noaccess']);

			$query = prepare("DELETE FROM ``reports`` WHERE `post` = :post");
			$query->bindValue(':post', $post);
			modLog("Dismissed all reports for post #{$id}", $board);
			break;
		case '&all':
			if (!hasPermission($config['mod']['report_dismiss_ip'], $board))
				error($config['error']['noaccess']);

			$query = prepare("DELETE FROM ``reports`` WHERE `ip` = :ip");
			$query->bindValue(':ip', $ip);
			$cip = cloak_ip($ip);
			modLog("Dismissed all reports by <a href=\"?/IP/$cip\">$cip</a>");
			break;
		case '':
		default:
			if (!hasPermission($config['mod']['report_dismiss'], $board))
				error($config['error']['noaccess']);

			$query = prepare("DELETE FROM ``reports`` WHERE `id` = :id");
			$query->bindValue(':id', $id);
			modLog("Dismissed a report for post #{$id}", $board);
			break;
	}
	$query->execute() or error(db_error($query));

	header('Location: ?/reports', true, $config['redirect_http']);
}

function mod_recent_posts(Context $ctx, $lim) {
	global $mod, $pdo;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['recent']))
		error($config['error']['noaccess']);

	$limit = (is_numeric($lim))? $lim : 25;
	$last_time = (isset($_GET['last']) && is_numeric($_GET['last'])) ? $_GET['last'] : 0;

	$mod_boards = [];
	$boards = listBoards();

	//if not all boards
	if ($mod['boards'][0]!='*') {
		foreach ($boards as $board) {
			if (in_array($board['uri'], $mod['boards']))
				$mod_boards[] = $board;
		}
	} else {
		$mod_boards = $boards;
	}

	// Manually build an SQL query
	$query = 'SELECT * FROM (';
	foreach ($mod_boards as $board) {
		$query .= sprintf('SELECT *, %s AS `board` FROM ``posts_%s`` UNION ALL ', $pdo->quote($board['uri']), $board['uri']);
	}
	// Remove the last "UNION ALL" seperator and complete the query
	$query = preg_replace('/UNION ALL $/', ') AS `all_posts` WHERE (`time` < :last_time OR NOT :last_time) ORDER BY `time` DESC LIMIT ' . $limit, $query);
	$query = prepare($query);
	$query->bindValue(':last_time', $last_time);
	$query->execute() or error(db_error($query));
	$posts = $query->fetchAll(PDO::FETCH_ASSOC);

	foreach ($posts as &$post) {
		openBoard($post['board']);
		if (!$post['thread']) {
			// Still need to fix this:
			$po = new Thread($post, '?/', $mod, false);
			$post['built'] = $po->build(true);
		} else {
			$po = new Post($post, '?/', $mod);
			$post['built'] = $po->build(true);
		}
		$last_time = $post['time'];
	}

	echo mod_page(
		_('Recent posts'),
		$config['file_mod_recent_posts'],
		[
			'posts' => $posts,
			'limit' => $limit,
			'last_time' => $last_time
		],
		$mod
	);
}

