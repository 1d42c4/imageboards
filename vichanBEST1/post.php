<?php
/*
 *  Copyright (c) 2010-2014 Tinyboard Development Group
 */

require_once 'inc/bootstrap.php';

use Vichan\Data\Driver\LogDriver;
use Vichan\Service\{RemoteCaptchaQuery, NativeCaptchaQuery};

$context = Vichan\build_context($config);

// Recover captcha cookie from noscript Set-Cookie path when form field is absent
if (!isset($_POST['captcha_cookie']) && isset($_COOKIE['captcha_cookie'])) {
	$_POST['captcha_cookie'] = $_COOKIE['captcha_cookie'];
}

if (isset($_POST['delete'])) {
	// User self-delete by password removed — use mod panel to delete posts.
	error(_('Posting passwords for user deletion are disabled. Ask a moderator to remove a post.'));

} elseif (isset($_POST['report'])) {
	if (!isset($_POST['board'], $_POST['reason']))
		error($config['error']['invalid']);

	$report = array();
	foreach ($_POST as $post => $value) {
		if (preg_match('/^delete_(\d+)$/', $post, $m)) {
			$report[] = (int)$m[1];
		}
	}

	// Check if board exists
	if (!openBoard($_POST['board']))
		error($config['error']['noboard']);

	if ((!isset($_POST['mod']) || !$_POST['mod']) && $config['board_locked']) {
		error("Board is locked");
	}

	if (empty($report))
		error($config['error']['noreport']);

	if (count($report) > $config['report_limit'])
		error($config['error']['toomanyreports']);


	if ($config['report_captcha']) {
		if (!isset($_POST['captcha_text'], $_POST['captcha_cookie']) || $_POST['captcha_text'] === '' || $_POST['captcha_cookie'] === '') {
			error($config['error']['invalid']);
		}

		try {
			$query = $context->get(NativeCaptchaQuery::class);
			$success = $query->verify(
				(string)$_POST['captcha_text'],
				(string)$_POST['captcha_cookie']
			);

			if (!$success) {
				error($config['error']['captcha']);
			}
		} catch (RuntimeException $e) {
			$context->get(LogDriver::class)->log(LogDriver::ERROR, "Native captcha exception: {$e->getMessage()}");
			error($config['error']['local_io_error']);
		}
	}

	$reason = escape_markup_modifiers($_POST['reason']);
	markup($reason);

	if (mb_strlen($reason) > $config['report_max_length']) {
		error($config['error']['toolongreport']);
	}

	foreach ($report as &$id) {
		$query = prepare(sprintf("SELECT `id`, `thread` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$post = $query->fetch(PDO::FETCH_ASSOC);
		if ($post === false) {
			$context->get(LogDriver::class)->log(LogDriver::INFO, "Failed to report non-existing post #{$id} in {$board['dir']}");
			error($config['error']['nopost']);
		}

		$error = event('report', array('board' => $board['uri'], 'post' => $post, 'reason' => $reason, 'link' => link_for($post)));
		if ($error) {
			error($error);
		}

		$context->get(LogDriver::class)->log(
			LogDriver::INFO,
			'Reported post: /'
				 . $board['dir'] . $config['dir']['res'] . link_for($post) . ($post['thread'] ? '#' . $id : '')
				 . " for \"$reason\""
		);
		$query = prepare("INSERT INTO ``reports`` VALUES (NULL, :time, :board, :post, :reason)");
		$query->bindValue(':time', time(), PDO::PARAM_INT);
		$query->bindValue(':board', $board['uri'], PDO::PARAM_STR);
		$query->bindValue(':post', $id, PDO::PARAM_INT);
		$query->bindValue(':reason', $reason, PDO::PARAM_STR);
		$query->execute() or error(db_error($query));
	}

	check_login($context, false);
	$root = $mod ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];

	if (!isset($_POST['json_response'])) {
		$index = htmlspecialchars($root . $board['dir'] . $config['file_index'], ENT_QUOTES, 'UTF-8');
		echo Element($config['file_page_template'], array('config' => $config, 'body' => '<div style="text-align:center"><a href="javascript:window.close()">[ ' . _('Close window') ." ]</a> <a href='$index'>[ " . _('Return') . ' ]</a></div>', 'title' => _('Report submitted!')));
	} else {
		header('Content-Type: text/json');
		echo json_encode(array('success' => true));
	}
} elseif (isset($_POST['post'])) {
	if (!isset($_POST['body'], $_POST['board']))
		error($config['error']['invalid']);

	$post = array('board' => $_POST['board'], 'files' => array());

	// Check if board exists
	if (!openBoard($post['board']))
		error($config['error']['noboard']);

	if ((!isset($_POST['mod']) || !$_POST['mod']) && $config['board_locked']) {
		error("Board is locked");
	}

	if (!isset($_POST['name']))
		$_POST['name'] = $config['anonymous'];

	if (!isset($_POST['email']))
		$_POST['email'] = '';

	if (!isset($_POST['subject']))
		$_POST['subject'] = '';

	if (isset($_POST['thread'])) {
		$post['op'] = false;
		$post['thread'] = round($_POST['thread']);
	} else
		$post['op'] = true;


	// Check for CAPTCHA right after opening the board so the "return" link is in there.
	try {
		$provider = $config['captcha']['provider'];
		$new_thread_capt = !empty($config['captcha']['native']['new_thread_capt']);

		// Native captcha (in-process DB verify)
		if (($provider === 'native' && !$new_thread_capt)
			|| ($provider === 'native' && $new_thread_capt && $post['op'])) {
			if (!isset($_POST['captcha_text'], $_POST['captcha_cookie'])
				|| $_POST['captcha_text'] === '' || $_POST['captcha_cookie'] === '') {
				error($config['error']['captcha']);
			}
			$query = $context->get(NativeCaptchaQuery::class);
			$success = $query->verify((string)$_POST['captcha_text'], (string)$_POST['captcha_cookie']);

			if (!$success) {
				$provider_get = htmlspecialchars($config['captcha']['native']['provider_get'] ?? '/securimage.php', ENT_QUOTES, 'UTF-8');
				$extra = htmlspecialchars($config['captcha']['native']['extra'] ?? '', ENT_QUOTES, 'UTF-8');
				error(
					utf8tohtml($config['error']['captcha']) .
					"<script>if (typeof actually_load_captcha === 'function') actually_load_captcha(\"{$provider_get}\", \"{$extra}\");</script>",
					true,
					[],
					true
				);
			}
		}
		// Remote 3rd party captchas (never send client address to vendors).
		elseif ($provider === 'recaptcha' || $provider === 'hcaptcha') {
			$query = $context->get(RemoteCaptchaQuery::class);
			$field = $query->responseField();

			if (!isset($_POST[$field])) {
				error($config['error']['invalid']);
			}
			$response = $_POST[$field];
			$success = $query->verify($response);
			if (!$success) {
				error($config['error']['captcha']);
			}
		}
	} catch (RuntimeException $e) {
		$context->get(LogDriver::class)->log(LogDriver::ERROR, "Captcha IO exception: {$e->getMessage()}");
		error($config['error']['remote_io_error']);
	} catch (JsonException $e) {
		$context->get(LogDriver::class)->log(LogDriver::ERROR, "Bad JSON reply to captcha: {$e->getMessage()}");
		error($config['error']['remote_io_error']);
	}


	if (!(($post['op'] && $_POST['post'] == $config['button_newtopic']) ||
		(!$post['op'] && $_POST['post'] == $config['button_reply']))) {
		error($config['error']['invalid']);
	}

	if ($post['mod'] = isset($_POST['mod']) && $_POST['mod']) {
		check_login($context, false);
		if (!$mod) {
			// Liar. You're not a mod.
			error($config['error']['notamod']);
		}

		$post['sticky'] = $post['op'] && isset($_POST['sticky']);
		$post['locked'] = $post['op'] && isset($_POST['lock']);
		$post['raw'] = isset($_POST['raw']);

		if ($post['sticky'] && !hasPermission($config['mod']['sticky'], $board['uri']))
			error($config['error']['noaccess']);
		if ($post['locked'] && !hasPermission($config['mod']['lock'], $board['uri']))
			error($config['error']['noaccess']);
		if ($post['raw'] && !hasPermission($config['mod']['rawhtml'], $board['uri']))
			error($config['error']['noaccess']);
	}

	// Club / private board password (separate from per-post delete password)
	$is_mod_post = !empty($post['mod']) && $mod;
	if (function_exists('board_has_post_password') && board_has_post_password() && !$is_mod_post) {
		$entered = isset($_POST['board_password']) ? (string)$_POST['board_password'] : '';
		if (!board_password_verify($entered, $board['post_password'] ?? null)) {
			error($config['error']['board_password']);
		}
	}

	//Check if thread exists
	if (!$post['op']) {
		$pub = function_exists('sql_posts_public') ? (' AND ' . sql_posts_public()) : '';
		// Allow replying only to public (approved) threads
		$query = prepare(sprintf("SELECT `sticky`,`locked`,`sage`,`slug` FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL%s LIMIT 1", $board['uri'], $pub));
		$query->bindValue(':id', $post['thread'], PDO::PARAM_INT);
		$query->execute() or error(db_error());

		if (!$thread = $query->fetch(PDO::FETCH_ASSOC)) {
			// Non-existant
			error($config['error']['nonexistant']);
		}
	}
	else {
		$thread = false;
	}


	if (!hasPermission($config['mod']['bypass_field_disable'], $board['uri'])) {
		if ($config['field_disable_name'])
			$_POST['name'] = $config['anonymous']; // "forced anonymous"

		if ($config['field_disable_email'])
			$_POST['email'] = '';

		if ($config['field_disable_subject'] || (!$post['op'] && $config['field_disable_reply_subject']))
			$_POST['subject'] = '';
	}

	$post['name'] = $_POST['name'] != '' ? $_POST['name'] : $config['anonymous'];
	$post['subject'] = $_POST['subject'];
	// Email field removed from form; only always_sage uses the column.
	$post['email'] = '';
	$post['body'] = $_POST['body'];
	// Per-post delete passwords removed; column kept empty for schema compatibility.
	$post['password'] = '';
	$post['has_file'] = (($post['op'] && $config['force_image_op']) || count($_FILES) > 0);


	if (!$post['has_file'] || (($post['op'] && $config['force_body_op']) || (!$post['op'] && $config['force_body']))) {
		$stripped_whitespace = preg_replace('/[\s]/u', '', $post['body']);
		if ($stripped_whitespace == '') {
			error($config['error']['tooshort_body']);
		}
	}

	if (!$post['op']) {
		// Check if thread is locked
		// but allow mods to post
		if ($thread['locked'] && !hasPermission($config['mod']['postinlocked'], $board['uri']))
			error($config['error']['locked']);

		$numposts = numPosts($post['thread']);

		if ($config['reply_hard_limit'] != 0 && $config['reply_hard_limit'] <= $numposts['replies'])
			error($config['error']['reply_hard_limit']);

		if ($post['has_file'] && $config['image_hard_limit'] != 0 && $config['image_hard_limit'] <= $numposts['images'])
			error($config['error']['image_hard_limit']);
	}

	if ($post['has_file']) {
		// Determine size sanity
		$size = 0;
		if ($config['multiimage_method'] == 'split') {
			foreach ($_FILES as $key => $file) {
				$size += $file['size'];
			}
		} elseif ($config['multiimage_method'] == 'each') {
			foreach ($_FILES as $key => $file) {
				if ($file['size'] > $size) {
					$size = $file['size'];
				}
			}
		} else {
			error(_('Unrecognized file size determination method.'));
		}

		if ($size > $config['max_filesize'])
			error(sprintf3($config['error']['filesize'], array(
				'sz' => number_format($size),
				'filesz' => number_format($size),
				'maxsz' => number_format($config['max_filesize'])
			)));
		$post['filesize'] = $size;
	}


	$post['capcode'] = false;

	if ($mod && preg_match('/^((.+) )?## (.+)$/', $post['name'], $matches)) {
		$name = $matches[2] != '' ? $matches[2] : $config['anonymous'];
		$cap = $matches[3];

		if (isset($config['mod']['capcode'][$mod['type']])) {
			if (	$config['mod']['capcode'][$mod['type']] === true ||
				(is_array($config['mod']['capcode'][$mod['type']]) &&
					in_array($cap, $config['mod']['capcode'][$mod['type']])
				)) {

				$post['capcode'] = utf8tohtml($cap);
				$post['name'] = $name;
			}
		}
	}

	$trip = generate_tripcode($post['name']);
	$post['name'] = $trip[0];
	if ($config['disable_tripcodes'] && !$mod) {
		$post['trip'] = '';
	}
	else {
		$post['trip'] = isset($trip[1]) ? $trip[1] : ''; // XX: Dropped posts and tripcodes
	}

	// Site-wide behavior from config (email field not on form)
	$noko = !empty($config['always_noko']);
	$post['email'] = !empty($config['always_sage']) ? 'sage' : '';

	if ($post['has_file']) {
		$i = 0;
		foreach ($_FILES as $key => $file) {
			if (!in_array($file['error'], array(UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK))) {
				error(sprintf3($config['error']['phpfileserror'], array(
					'index' => $i+1,
					'code' => $file['error']
				)));
			}

			if ($file['size'] && $file['tmp_name']) {
				$file['filename'] = urldecode($file['name']);
				$file['extension'] = strtolower(mb_substr($file['filename'], mb_strrpos($file['filename'], '.') + 1));
				if (isset($config['filename_func']))
					$file['file_id'] = $config['filename_func']($file);
				else
					$file['file_id'] = bin2hex(random_bytes(8));

				if (sizeof($_FILES) > 1)
					$file['file_id'] .= "-$i";

				$file['file'] = $board['dir'] . $config['dir']['img'] . $file['file_id'] . '.' . $file['extension'];
				$file['thumb'] = $board['dir'] . $config['dir']['thumb'] . $file['file_id'] . '.' . ($config['thumb_ext'] ? $config['thumb_ext'] : $file['extension']);
				$post['files'][] = $file;
				$i++;
			}
		}
	}

	if (empty($post['files'])) $post['has_file'] = false;

	// Check for a file on OP
	if ($post['op'] && !$post['has_file'] && $config['force_image_op']) {
		error($config['error']['noimage']);
	}

	// Check for too many files
	if (sizeof($post['files']) > $config['max_images'])
		error($config['error']['toomanyimages']);

	if ($config['strip_combining_chars']) {
		$post['name'] = strip_combining_chars($post['name']);
		$post['email'] = strip_combining_chars($post['email']);
		$post['subject'] = strip_combining_chars($post['subject']);
		$post['body'] = strip_combining_chars($post['body']);
	}

	// Check string lengths
	if (mb_strlen($post['name']) > 35)
		error(sprintf($config['error']['toolong'], 'name'));
	if (mb_strlen($post['email']) > 40)
		error(sprintf($config['error']['toolong'], 'email'));
	if (mb_strlen($post['subject']) > 100)
		error(sprintf($config['error']['toolong'], 'subject'));
	if (!$mod && mb_strlen($post['body']) > $config['max_body'])
		error($config['error']['toolong_body']);
	if (!$mod && substr_count($post['body'], "\n") >= $config['maximum_lines'])
		error($config['error']['toomanylines']);
	wordfilters($post['body']);

	$post['body'] = escape_markup_modifiers($post['body']);

	if ($mod && isset($post['raw']) && $post['raw']) {
		$post['body'] .= "\n<tinyboard raw html>1</tinyboard>";
	}

	$post['body_nomarkup'] = $post['body'];

	$post['tracked_cites'] = markup($post['body'], true);


	if ($post['has_file']) {
		$allhashes = '';

		foreach ($post['files'] as $key => &$file) {
			if ($post['op'] && $config['allowed_ext_op']) {
				if (!in_array($file['extension'], $config['allowed_ext_op']))
					error($config['error']['unknownext']);
			}
			elseif (!in_array($file['extension'], $config['allowed_ext']) && !in_array($file['extension'], $config['allowed_ext_files']))
				error($config['error']['unknownext']);

			$file['is_an_image'] = !in_array($file['extension'], $config['allowed_ext_files']);

			// Truncate filename if it is too long
			$file['filename'] = mb_substr($file['filename'], 0, $config['max_filename_len']);

			$upload = $file['tmp_name'];

			if (!is_readable($upload))
				error($config['error']['nomove']);

			$hash = md5_file($upload);

			$file['hash'] = $hash;
			$allhashes .= $hash;
		}

		if (count ($post['files']) == 1) {
			$post['filehash'] = $hash;
		}
		else {
			$post['filehash'] = md5($allhashes);
		}
	}

	if ($post['has_file']) {
		require_once 'inc/image.php';

		foreach ($post['files'] as $key => &$file) {
		if ($file['is_an_image']) {
			if (!$size = @getimagesize($file['tmp_name'])) {
				error($config['error']['invalidimg']);
			}
			if (!in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_BMP, IMAGETYPE_WEBP], true)) {
				error($config['error']['invalidimg']);
			}
			if ($size[0] > $config['max_width'] || $size[1] > $config['max_height']) {
				error($config['error']['maxsize']);
			}

			$image = new Image($file['tmp_name'], $file['extension'], $size);
			if ($image->size->width > $config['max_width'] || $image->size->height > $config['max_height']) {
				$image->delete();
				error($config['error']['maxsize']);
			}

			$file['width'] = $image->size->width;
			$file['height'] = $image->size->height;

			$max_tw = $post['op'] ? (int)$config['thumb_op_width'] : (int)$config['thumb_width'];
			$max_th = $post['op'] ? (int)$config['thumb_op_height'] : (int)$config['thumb_height'];
			$thumb_ext = $config['thumb_ext'] ? $config['thumb_ext'] : $file['extension'];

			if ($config['spoiler_images'] && isset($_POST['spoiler'])) {
				$file['thumb'] = 'spoiler';
				$size = @getimagesize($config['spoiler_image']);
				$file['thumbwidth'] = $size[0];
				$file['thumbheight'] = $size[1];
			} elseif (strtolower($file['extension']) === 'gif' && gif_is_animated($file['tmp_name'])) {
				// Keep original animated GIF for the thumb so it still plays.
				if (!@copy($file['tmp_name'], $file['thumb'])) {
					error($config['error']['nomove']);
				}
				[$file['thumbwidth'], $file['thumbheight']] = image_fit_box(
					(int)$image->size->width,
					(int)$image->size->height,
					$max_tw,
					$max_th
				);
			} elseif ($image->size->width <= $max_tw
				&& $image->size->height <= $max_th
				&& $file['extension'] === $thumb_ext) {
				copy($file['tmp_name'], $file['thumb']);
				$file['thumbwidth'] = $image->size->width;
				$file['thumbheight'] = $image->size->height;
			} else {
				$thumb = $image->resize($thumb_ext, $max_tw, $max_th);
				$thumb->to($file['thumb']);
				$file['thumbwidth'] = $thumb->width;
				$file['thumbheight'] = $thumb->height;
				$thumb->_destroy();
			}

			// Optional GD re-encode drops metadata when strip_exif/redraw_image is on
			$dont_copy_file = false;
			if (!empty($config['redraw_image']) || !empty($config['strip_exif'])) {
				$image->to($file['file']);
				$dont_copy_file = true;
				$file['size'] = @filesize($file['file']) ?: $file['size'];
			}
			$image->destroy();
		} else {
			// non-image uploads (disabled by default: allowed_ext_files is empty)
			$file['thumb'] = 'file';
			$icon = $config['file_icons'][$file['extension']] ?? $config['file_icons']['default'];
			$size = @getimagesize(sprintf($config['file_thumb'], $icon));
			$file['thumbwidth'] = $size[0] ?? 48;
			$file['thumbheight'] = $size[1] ?? 48;
			$dont_copy_file = false;
		}

		if (!$dont_copy_file) {
			if (!@move_uploaded_file($file['tmp_name'], $file['file'])) {
				error($config['error']['nomove']);
			}
		}

		if ($config['image_reject_repost']) {
			if ($p = getPostByHash($post['filehash'])) {
				undoImage($post);
				error(sprintf($config['error']['fileexists'],
					($post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root']) .
					($board['dir'] . $config['dir']['res'] .
						($p['thread'] ?
							$p['thread'] . '.html#' . $p['id']
						:
							$p['id'] . '.html'
						))
				));
			}
		} else if (!$post['op'] && $config['image_reject_repost_in_thread']) {
			if ($p = getPostByHashInThread($post['filehash'], $post['thread'])) {
				undoImage($post);
				error(sprintf($config['error']['fileexistsinthread'],
					($post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root']) .
					($board['dir'] . $config['dir']['res'] .
						($p['thread'] ?
							$p['thread'] . '.html#' . $p['id']
						:
							$p['id'] . '.html'
						))
				));
			}
		}
		} // end foreach files
	} // end has_file image processing

	// Remove board directories before inserting them into the database.
	if ($post['has_file']) {
		foreach ($post['files'] as $key => &$file) {
			$file['file_path'] = $file['file'];
			$file['thumb_path'] = $file['thumb'];
			$file['file'] = mb_substr($file['file'], mb_strlen($board['dir'] . $config['dir']['img']));
			if ($file['is_an_image'] && $file['thumb'] != 'spoiler')
				$file['thumb'] = mb_substr($file['thumb'], mb_strlen($board['dir'] . $config['dir']['thumb']));
		}
	}

	$post = (object)$post;
	$post->files = array_map(function($a) { return (object)$a; }, $post->files);

	$error = event('post', $post);
	$post->files = array_map(function($a) { return (array)$a; }, $post->files);

	if ($error) {
		undoImage((array)$post);
		error($error);
	}
	$post = (array)$post;

	$post['num_files'] = sizeof($post['files']);

	// Hold for mod approval when board requires it (mods post live immediately)
	$post['pending'] = 0;
	if (function_exists('board_requires_approval') && board_requires_approval() && !$is_mod_post) {
		$post['pending'] = 1;
	}

	$post['id'] = $id = post($post);
	$post['slug'] = slugify($post);

	$held = !empty($post['pending']);

	if (!$held && isset($post['tracked_cites']) && !empty($post['tracked_cites'])) {
		$insert_rows = array();
		foreach ($post['tracked_cites'] as $cite) {
			$insert_rows[] = '(' .
				$pdo->quote($board['uri']) . ', ' . (int)$id . ', ' .
				$pdo->quote($cite[0]) . ', ' . (int)$cite[1] . ')';
		}
		query('INSERT INTO ``cites`` VALUES ' . implode(', ', $insert_rows)) or error(db_error());
	}

	if (!$held && !$post['op'] && strtolower($post['email']) != 'sage' && !$thread['sage'] && ($config['reply_limit'] == 0 || $numposts['replies']+1 < $config['reply_limit'])) {
		bumpThread($post['thread']);
	}

	if (isset($_SERVER['HTTP_REFERER'])) {
		// Tell Javascript that we posted successfully
		if (isset($_COOKIE[$config['cookies']['js']])) {
			$js = json_decode($_COOKIE[$config['cookies']['js']]);
		} else {
			$js = (object)array();
		}
		// Tell it to delete the cached post for referer
		$js->{$_SERVER['HTTP_REFERER']} = true;

		// Encode and set cookie.
		$options = [
			'expires' => 0,
			'path' => $config['cookies']['jail'] ? $config['cookies']['path'] : '/',
			'httponly' => false,
			'samesite' => 'Strict'
		];
		setcookie($config['cookies']['js'], json_encode($js), $options);
	}

	$root = $post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];

	if ($held) {
		// Don't publish yet — stay on board index with notice
		$redirect = $root . $board['dir'] . $config['file_index'];
		$context->get(LogDriver::class)->log(
			LogDriver::INFO,
			'Pending post held for approval: /' . $board['dir'] . ' #' . $id
		);

		if (!isset($_POST['json_response'])) {
			// Show success page explaining approval wait
			die(Element($config['file_page_template'], [
				'config' => $config,
				'boardlist' => createBoardlist(false),
				'title' => _('Post submitted'),
				'body' => '<div class="notice"><p>' . htmlspecialchars($config['error']['pending_approval']) . '</p>'
					. '<p><a href="' . htmlspecialchars($redirect) . '">' . _('Return') . '</a></p></div>',
			]));
		}
		header('Content-Type: text/json; charset=utf-8');
		echo json_encode([
			'redirect' => $redirect,
			'noko' => false,
			'id' => $id,
			'pending' => true,
			'message' => $config['error']['pending_approval'],
		]);
		return;
	}

	if ($noko) {
		$redirect = $root . $board['dir'] . $config['dir']['res'] .
			link_for($post, false, false, $thread) . (!$post['op'] ? '#' . $id : '');
	} else {
		$redirect = $root . $board['dir'] . $config['file_index'];
	}

	buildThread($post['op'] ? $id : $post['thread']);

	$context->get(LogDriver::class)->log(
		LogDriver::INFO,
		'New post: /' . $board['dir'] . $config['dir']['res'] . link_for($post) . (!$post['op'] ? '#' . $id : '')
	);

	if (!$post['mod']) header('X-Associated-Content: "' . $redirect . '"');


	if (!isset($_POST['json_response'])) {
		header('Location: ' . $redirect, true, $config['redirect_http']);
	} else {
		header('Content-Type: text/json; charset=utf-8');
		echo json_encode(array(
			'redirect' => $redirect,
			'noko' => $noko,
			'id' => $id
		));
	}

	if ($config['try_smarter'] && $post['op'])
		$build_pages = range(1, $config['max_pages']);

	if ($post['op'])
		clean($id);

	event('post-after', $post);

	buildIndex();

	// We are already done, let's continue our heavy-lifting work in the background (if we run off FastCGI)
	if (function_exists('fastcgi_finish_request'))
		@fastcgi_finish_request();

	if ($post['op'])
		Vichan\Functions\Theme\rebuild_themes('post-thread', $board['uri']);
	else
		Vichan\Functions\Theme\rebuild_themes('post', $board['uri']);

} else {
	// They opened post.php in their browser manually.
	error($config['error']['nopost']);
}
