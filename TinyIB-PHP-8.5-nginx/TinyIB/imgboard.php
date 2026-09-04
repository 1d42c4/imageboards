<?php
declare(strict_types=1);

/*
TinyIB
https://codeberg.org/tslocum/tinyib

MIT License

Copyright (c) 2020 Trevor Slocum <trevor@rocket9labs.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

error_reporting(E_ALL);
ini_set('display_errors', '0');

$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(array(
	'lifetime' => 2592000,
	'path' => '/',
	'secure' => $https,
	'httponly' => true,
	'samesite' => 'Lax',
));
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

ob_implicit_flush();
while (ob_get_level() > 0) {
	ob_end_flush();
}

function fancyDie(string $message, int $go_back = 1): never {
	$go_back_text = __('Click here to go back');
	die('<body text="#800000" bgcolor="#FFFFEE" align="center"><br><div style="display: inline-block; background-color: #F0E0D6;font-size: 1.25em;font-family: Tahoma, Geneva, sans-serif;padding: 7px;border: 1px solid #D9BFB7;border-left: none;border-top: none;">' . $message . '</div><br><br>- <a href="javascript:history.go(-' . $go_back . ')">' . $go_back_text . '</a> -</body>');
}

if (PHP_VERSION_ID < 80500 || PHP_VERSION_ID >= 80600) {
	fancyDie('TinyIB requires PHP 8.5.x.');
}

if (!file_exists('settings.php')) {
	fancyDie('The required settings.php file is missing.');
}
require_once 'settings.php';
require_once 'inc/defines.php';

function __(string $string): string {
	return $string;
}

$captcha_modes = array(TINYIB_CAPTCHA, TINYIB_REPLYCAPTCHA, TINYIB_REPORTCAPTCHA, TINYIB_MANAGECAPTCHA);
foreach ($captcha_modes as $captcha_mode) {
	if (!in_array($captcha_mode, array('', 'simple'), true)) {
		fancyDie(__('The only supported CAPTCHA mode is "simple".'));
	}
}

if (TINYIB_TRIPSEED == '') {
	fancyDie(__('TINYIB_TRIPSEED must be configured.'));
}

$bcrypt_salt = '$2y$12$' . str_pad(str_replace('=', '/', str_replace('+', '.', substr(base64_encode(TINYIB_TRIPSEED), 0, 22))), 22, '/');

// Check directories are writable by the script
$writedirs = array('.', 'res', 'src', 'thumb');
foreach ($writedirs as $dir) {
	if (!is_dir($dir) || !is_writable($dir)) {
		fancyDie(sprintf(__("Directory '%s' can not be written to.  Please modify its permissions."), $dir));
	}
}

$includes = array(
	'inc/functions.php',
	'inc/html.php',
	'inc/database/sqlite3_link.php',
	'inc/database/sqlite3.php',
	'inc/database/database.php',
);
foreach ($includes as $include) {
	require_once $include;
}

list($account, $loggedin, $isadmin) = manageCheckLogIn(false);

$redirect = true;
// Check if the request is to make a post
if (!isset($_GET['delete']) && !isset($_GET['manage']) && (isset($_POST['name']) || isset($_POST['email']) || isset($_POST['subject']) || isset($_POST['message']) || isset($_POST['file']) || isset($_POST['embed']) || isset($_POST['password']))) {
	$lock = lockDatabase();

	foreach (array('name', 'email', 'subject', 'message', 'password', 'embed') as $field) {
		if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
			$_POST[$field] = '';
		}
	}

	$staffpost = isStaffPost();
	$capcode = '';
	if (!$staffpost) {
		checkMessageSize();
	}

	$post = newPost(setParent());

	if (!$loggedin) {
		checkCAPTCHA($post['parent'] == TINYIB_NEWTHREAD ? TINYIB_CAPTCHA : TINYIB_REPLYCAPTCHA);
		checkFlood();
	}

	if (!$loggedin) {
		if ($post['parent'] == TINYIB_NEWTHREAD && TINYIB_DISALLOWTHREADS != '') {
			fancyDie(TINYIB_DISALLOWTHREADS);
		} else if ($post['parent'] != TINYIB_NEWTHREAD && TINYIB_DISALLOWREPLIES != '') {
			fancyDie(TINYIB_DISALLOWREPLIES);
		}
	}

	$hide_fields = $post['parent'] == TINYIB_NEWTHREAD ? $tinyib_hidefieldsop : $tinyib_hidefields;

	if ($post['parent'] != TINYIB_NEWTHREAD && !$loggedin) {
		$parent = postByID($post['parent']);
		if (!isset($parent['locked'])) {
			fancyDie(__('Invalid parent thread ID supplied, unable to create post.'));
		} else if ($parent['locked'] == 1) {
			fancyDie(__('Replies are not allowed to locked threads.'));
		}
	}

	if ($post['name'] == '' && $post['tripcode'] == '') {
		global $tinyib_anonymous;
		$post['name'] = $tinyib_anonymous[array_rand($tinyib_anonymous)];
	}

	$post['ip'] = remoteAddress();

	$spoiler = TINYIB_SPOILERIMAGE && isset($_POST['spoiler']);

	if ($staffpost || !in_array('name', $hide_fields)) {
		list($post['name'], $post['tripcode']) = nameAndTripcode($_POST['name']);
		if (TINYIB_MAXNAME > 0) {
			$post['name'] = _substr($post['name'], 0, TINYIB_MAXNAME);
		}
		$post['name'] = cleanString($post['name']);
	}
	if ($staffpost || !in_array('email', $hide_fields)) {
		$post['email'] = $_POST['email'];
		if (TINYIB_MAXEMAIL > 0) {
			$post['email'] = _substr($post['email'], 0, TINYIB_MAXEMAIL);
		}
		$post['email'] = cleanString(str_replace('"', '&quot;', $post['email']));
	}
	if ($staffpost) {
		$capcode = ($isadmin) ? ' <span style="color: ' . $tinyib_capcodes[0][1] . ' ;">## ' . $tinyib_capcodes[0][0] . '</span>' : ' <span style="color: ' . $tinyib_capcodes[1][1] . ';">## ' . $tinyib_capcodes[1][0] . '</span>';
	}
	if ($staffpost || !in_array('subject', $hide_fields)) {
		$post['subject'] = $_POST['subject'];
		if (TINYIB_MAXSUBJECT > 0) {
			$post['subject'] = _substr($post['subject'], 0, TINYIB_MAXSUBJECT);
		}
		$post['subject'] = cleanString($post['subject']);
	}
	if ($staffpost || !in_array('message', $hide_fields)) {
		$post['message'] = $_POST['message'];
		if ($staffpost && isset($_POST['raw'])) {
			// Treat message as raw HTML
		} else {
			if (TINYIB_WORDBREAK > 0) {
				$post['message'] = preg_replace('/([^\s]{' . TINYIB_WORDBREAK . '})(?=[^\s])/u', '$1' . TINYIB_WORDBREAK_IDENTIFIER, $post['message']);
			}
			$post['message'] = str_replace("\n", '<br>', makeLinksClickable(colorQuote(postLink(cleanString(rtrim($post['message']))))));

			if (TINYIB_SPOILERTEXT) {
				$post['message'] = preg_replace('/&lt;s&gt;(.*?)&lt;\/s&gt;/i', '<span class="spoiler">$1</span>', $post['message']);
				$post['message'] = preg_replace('/&lt;spoiler&gt;(.*?)&lt;\/spoiler&gt;/i', '<span class="spoiler">$1</span>', $post['message']);
				$post['message'] = preg_replace('/&lt;spoilers&gt;(.*?)&lt;\/spoilers&gt;/i', '<span class="spoiler">$1</span>', $post['message']);
			}

			if (TINYIB_WORDBREAK > 0) {
				$post['message'] = finishWordBreak($post['message']);
			}
		}
	}
	if ($staffpost || !in_array('password', $hide_fields)) {
		$post['password'] = ($_POST['password'] != '') ? hashData($_POST['password']) : '';
	}

	$hide_post = false;
	$report_post = false;
	$report_reason = '';
	foreach (array($post['name'], $post['email'], $post['subject'], $post['message']) as $field) {
		$keyword = checkKeywords($field);
		if (empty($keyword)) {
			continue;
		}

		switch ($keyword['action']) {
			case 'report':
				$report_post = true;
				$report_reason = __('Matched a configured keyword.');
				break;
			case 'hide':
				$hide_post = true;
				break;
			case 'delete':
				fancyDie(__('Your post contains a blocked keyword.'));
			default:
				fancyDie(__('An unsupported keyword action is configured.'));
		}
		break;
	}

	$post['nameblock'] = nameBlock($post['name'], $post['tripcode'], $post['email'], $capcode);

	if (isset($_POST['embed']) && trim($_POST['embed']) != '' && ($staffpost || !in_array('embed', $hide_fields))) {
		if (isset($_FILES['file']) && $_FILES['file']['name'] != "") {
			fancyDie(__('Embedding a URL and uploading a file at the same time is not supported.'));
		}

		list($service, $embed) = getEmbed(trim($_POST['embed']));
		if (empty($embed) || !isset($embed['html']) || !isset($embed['title']) || !isset($embed['thumbnail_url'])) {
			if (!TINYIB_UPLOADVIAURL) {
				fancyDie(sprintf(__('Invalid embed URL. Only %s URLs are supported.'), implode('/', array_keys($tinyib_embeds))));
			}

			$headers = get_headers(trim($_POST['embed']), true);
			if (TINYIB_MAXKB > 0 && isset($headers['Content-Length']) && intval($headers['Content-Length']) > (TINYIB_MAXKB * 1024)) {
				fancyDie(sprintf(__('That file is larger than %s.'), TINYIB_MAXKBDESC));
			}

			$data = url_get_contents(trim($_POST['embed']));
			if (strlen($data) == 0) {
				fancyDie(__('Failed to download file at specified URL.'));
			}

			if (TINYIB_MAXKB > 0 && strlen($data) > (TINYIB_MAXKB * 1024)) {
				fancyDie(sprintf(__('That file is larger than %s.'), TINYIB_MAXKBDESC));
			}

			$filepath = 'src/' . time() . substr(microtime(), 2, 3) . rand(1000, 9999) . '.txt';
			if (!file_put_contents($filepath, $data)) {
				@unlink($filepath);
				fancyDie(__('Failed to download file at specified URL.'));
			}

			$post = attachFile($post, $filepath, basename(parse_url(trim($_POST['embed']), PHP_URL_PATH)), false, $spoiler);
		} else {
			$post['file_hex'] = $service;
			$temp_file = time() . substr(microtime(), 2, 3);
			$file_location = "thumb/" . $temp_file;
			file_put_contents($file_location, url_get_contents($embed['thumbnail_url']));

			$file_info = getimagesize($file_location);
			$file_mime = mime_content_type($file_location);
			$post['image_width'] = $file_info[0];
			$post['image_height'] = $file_info[1];

			if ($file_mime == "image/jpeg") {
				$post['thumb'] = $temp_file . '.jpg';
			} else if ($file_mime == "image/gif") {
				$post['thumb'] = $temp_file . '.gif';
			} else if ($file_mime == "image/png") {
				$post['thumb'] = $temp_file . '.png';
			} else {
				fancyDie(__('Error while processing audio/video.'));
			}
			$thumb_location = "thumb/" . $post['thumb'];

			list($thumb_maxwidth, $thumb_maxheight) = thumbnailDimensions($post);

			if (!createThumbnail($file_location, $thumb_location, $thumb_maxwidth, $thumb_maxheight, $spoiler)) {
				fancyDie(__('Could not create thumbnail.'));
			}

			addVideoOverlay($thumb_location);

			$thumb_info = getimagesize($thumb_location);
			$post['thumb_width'] = $thumb_info[0];
			$post['thumb_height'] = $thumb_info[1];

			$post['file_original'] = cleanString($embed['title']);
			$post['file'] = str_ireplace(array('src="https://', 'src="http://'), 'src="//', $embed['html']);
		}
	} else if (isset($_FILES['file']) && $_FILES['file']['name'] != "" && ($staffpost || !in_array('file', $hide_fields))) {
		validateFileUpload();

		$post = attachFile($post, $_FILES['file']['tmp_name'], $_FILES['file']['name'], true, $spoiler);
	}

	if ($post['file'] == '') { // No file uploaded
		$file_ok = !empty($tinyib_uploads) && ($staffpost || !in_array('file', $hide_fields));
		$embed_ok = (!empty($tinyib_embeds) || TINYIB_UPLOADVIAURL) && ($staffpost || !in_array('embed', $hide_fields));
		$allowed = '';
		if ($file_ok && $embed_ok) {
			$allowed = __('upload a file or embed a URL');
		} else if ($file_ok) {
			$allowed = __('upload a file');
		} else if ($embed_ok) {
			$allowed = __('embed a URL');
		}
		if ($post['parent'] == TINYIB_NEWTHREAD && $allowed != "" && !TINYIB_NOFILEOK) {
			fancyDie(sprintf(__('Please %s to start a new thread.'), $allowed));
		}
		if (!$staffpost && str_replace('<br>', '', $post['message']) == "") {
			$message_ok = !in_array('message', $hide_fields);
			if ($message_ok) {
				if ($allowed != '') {
					fancyDie(sprintf(__('Please enter a message and/or %s.'), $allowed));
				}
				fancyDie(__('Please enter a message.'));
			}
			fancyDie(sprintf(__('Please %s.'), $allowed));
		}
	}

	if (!$loggedin && (($post['file'] != '' && TINYIB_REQMOD == 'files') || TINYIB_REQMOD == 'all')) {
		$post['moderated'] = '0';
		echo sprintf(__('Your %s will be shown <b>once it has been approved</b>.'), $post['parent'] == TINYIB_NEWTHREAD ? 'thread' : 'post') . '<br>';
		$slow_redirect = true;
	}

	$post['id'] = insertPost($post);

	if ($report_post) {
		$report = array('post' => $post['id'], 'reason' => $report_reason);
		insertReport($report);
		checkAutoHide($post);
	}

	if ($hide_post) {
		approvePostByID($post['id'], 0);
	}

	if ($post['moderated'] == '1') {
		if (TINYIB_ALWAYSNOKO || strtolower($post['email']) == 'noko') {
			$redirect = 'res/' . ($post['parent'] == TINYIB_NEWTHREAD ? $post['id'] : $post['parent']) . '.html#' . $post['id'];
		}

		trimThreads();

		echo __('Updating thread...') . '<br>';
		if ($post['parent'] != TINYIB_NEWTHREAD) {
			rebuildThread($post['parent']);

			if (strtolower($post['email']) != 'sage') {
				if (TINYIB_MAXREPLIES == 0 || numRepliesToThreadByID($post['parent']) <= TINYIB_MAXREPLIES) {
					bumpThreadByID($post['parent']);
				}
			}
		} else {
			rebuildThread($post['id']);
		}

		echo __('Updating index...') . '<br>';
		rebuildIndexes();
	}

	if ($staffpost) {
		manageLogAction(__('Created staff post') . ' ' . postLink('&gt;&gt;' . $post['id']));
	}
// Check if the request is to preview a post
} elseif (isset($_GET['preview']) && !isset($_GET['manage'])) {
	$post = postByID(intval($_GET['preview']));
	if (empty($post)) {
		die(__('This post has been deleted'));
	} else if ($post['moderated'] == 0 && !$isadmin) {
		die(__('This post requires moderation before it can be displayed'));
	}

	$html = buildPost($post, isset($_GET['res']), true);
	if (isset($_GET['res'])) {
		$html = fixLinksInRes($html);
	}

	echo $html;
	die();
// Check if the request is to auto-refresh a thread
} elseif (isset($_GET['posts']) && !isset($_GET['manage'])) {
	if (TINYIB_AUTOREFRESH <= 0) {
		fancyDie(__('Automatic refreshing is disabled.'));
	}

	$thread_id = intval($_GET['posts']);
	$new_since = intval($_GET['since']);
	if ($thread_id <= 0 || $new_since < 0) {
		fancyDie('');
	}

	$json_posts = array();
	$posts = postsInThreadByID($thread_id);
	if ($new_since > 0) {
		foreach ($posts as $i => $post) {
			if ($post['id'] <= $new_since) {
				continue;
			}
			$json_posts[$post['id']] = fixLinksInRes(buildPost($post, true));
		}
	}

	echo json_encode($json_posts);
	die();
// Check if the request is to report a post
} elseif (isset($_GET['report']) && !isset($_GET['manage'])) {
	$lock = lockDatabase();

	if (!TINYIB_REPORT) {
		fancyDie(__('Reporting is disabled.'));
	}

	$report_post_id = is_string($_GET['report']) || is_int($_GET['report']) ? (int)$_GET['report'] : 0;
	$post = postByID($report_post_id);
	if (!$post) {
		fancyDie(__('Sorry, an invalid post identifier was sent. Please go back, refresh the page, and try again.'));
	}

	if ($post['moderated'] == 2) {
		fancyDie(__('Moderators have determined that post does not break any rules.'));
	}

	$reported_posts = $_SESSION['tinyib_reported_posts'] ?? array();
	if (!is_array($reported_posts)) {
		$reported_posts = array();
	}
	if (in_array((int)$post['id'], $reported_posts, true)) {
		fancyDie(__('You have already submitted a report for that post.'));
	}

	if (isset($_GET['verify'])) {
		if (TINYIB_REPORTCAPTCHA != '') {
			checkCAPTCHA(TINYIB_REPORTCAPTCHA);
		}

		$reason = isset($_POST['reason']) && is_string($_POST['reason']) ? trim($_POST['reason']) : '';
		if ($reason == '') {
			fancyDie(__('Please enter a reason for the report.'));
		}
		$reason = _substr($reason, 0, 500);

		insertReport(array('post' => $post['id'], 'reason' => $reason));
		$reported_posts[] = (int)$post['id'];
		$_SESSION['tinyib_reported_posts'] = array_values(array_unique($reported_posts));
		checkAutoHide($post);

		fancyDie(__('Post reported.'), 2);
	}

	$captcha = '';
	if (TINYIB_REPORTCAPTCHA != '') {
		$captcha = '
<br>
<input type="text" name="captcha" id="captcha" size="6" accesskey="c" autocomplete="off">&nbsp;&nbsp;' . __('(enter the text below)') . '<br>
<img id="captchaimage" src="inc/captcha.php" width="175" height="55" alt="CAPTCHA" onclick="javascript:reloadCAPTCHA()" style="margin-top: 5px;cursor: pointer;"><br><br>';
	}

	$txt_report = sprintf(__('Report post No.%d'), $post['id']);
	$txt_reason = __('Reason');
	$txt_submit = __('Submit');
	$body = <<<EOF
<form id="tinyib" name="tinyib" method="post" action="?report={$post['id']}&verify">
<fieldset>
<legend align="center">$txt_report</legend>
<div class="login">
<label for="reason">$txt_reason</label><br>
<textarea name="reason" id="reason" cols="48" rows="4" maxlength="500" required></textarea><br>
$captcha
<input type="submit" value="$txt_submit" class="managebutton">
</div>
</fieldset>
</form>
EOF;

	echo pageHeader() . $body . pageFooter();
	die();
// Check if the request is to delete a post and/or its associated image
} elseif (isset($_GET['delete']) && !isset($_GET['manage'])) {
	$lock = lockDatabase();

	if (!isset($_POST['delete'])) {
		fancyDie(__('Tick the box next to a post and click "Delete" to delete it.'));
	}

	$post_ids = array();
	if (is_array($_POST['delete'])) {
		$post_ids = $_POST['delete'];
	} else {
		$post_ids = array($_POST['delete']);
	}
	$post_ids = array_values(array_unique(array_filter(array_map('intval', $post_ids), function ($id) {
		return $id > 0;
	})));
	if (empty($post_ids)) {
		fancyDie(__('Sorry, an invalid post identifier was sent. Please go back, refresh the page, and try again.'));
	}

	list($account, $loggedin, $isadmin) = manageCheckLogIn(false);
	if (!empty($account)) {
		// Redirect to post moderation page
		echo '--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="0;url=' . basename($_SERVER['PHP_SELF']) . '?manage&moderate=' . implode(',', $post_ids) . '">';
		die();
	}

	$post = postByID($post_ids[0]);
	if (!$post) {
		fancyDie(__('Sorry, an invalid post identifier was sent. Please go back, refresh the page, and try again.'));
	} else {
		$password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
		$password_matches = $post['password'] != '' && (
			hash_equals($post['password'], hashData($password))
			|| hash_equals($post['password'], md5(md5($password)))
		);
		if (!$password_matches) {
			fancyDie(__('Invalid password.'));
		}

		deletePost($post['id']);
		if ($post['parent'] == TINYIB_NEWTHREAD) {
			threadUpdated($post['id']);
		} else {
			threadUpdated($post['parent']);
		}
		fancyDie(__('Post deleted.'));
	}

	$redirect = false;
// Check if the request is to access the management area
} elseif (isset($_GET['manage'])) {
	$lock = lockDatabase();

	$text = '';
	$onload = '';
	$navbar = '&nbsp;';
	$redirect = false;
	$loggedin = false;
	$isadmin = false;
	$returnlink = basename($_SERVER['PHP_SELF']);

	if (isset($_GET["logout"])) {
		$_SESSION['tinyib'] = '';
		$_SESSION['tinyib_key'] = '';
		session_destroy();
		die('--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="0;url=imgboard.php">');
	}

	list($account, $loggedin, $isadmin) = manageCheckLogIn(true);

	if ($loggedin) {
		if ($isadmin) {
			if (isset($_GET['rebuildall'])) {
				$allthreads = allThreads();
				foreach ($allthreads as $thread) {
					rebuildThread($thread['id']);
				}
				rebuildIndexes();
				$text .= manageInfo(__('Rebuilt board.'));
			} else if (isset($_GET['modlog'])) {
				$text .= manageModerationLog($_GET['modlog']);
			} else if (isset($_GET['reports'])) {
				if (!TINYIB_REPORT) {
					fancyDie(__('Reporting is disabled.'));
				}
				$text .= manageReportsPage();
			} elseif (isset($_GET['accounts'])) {
				if ($account['role'] != TINYIB_SUPER_ADMINISTRATOR) {
					fancyDie(__('Access denied'));
				}

				$id = intval($_GET['accounts']);
				if (isset($_POST['id'])) {
					$id = intval($_POST['id']);
				}
				$a = array('id' => 0);
				if ($id > 0) {
					$a = accountByID($id);
					if (empty($a)) {
						fancyDie(__('Account not found.'));
					}

					if ($a['username'] == 'admin' && TINYIB_ADMINPASS != '') {
						fancyDie(__('This account may not be updated while TINYIB_ADMINPASS is set.'));
					} else if ($a['username'] == 'mod' && TINYIB_MODPASS != '') {
						fancyDie(__('This account may not be updated while TINYIB_MODPASS is set.'));
					}
				}

				if (isset($_POST['id'])) {
					if ($id == 0 && $_POST['password'] == '') {
						fancyDie(__('A password is required.'));
					}

					$prev = $a;

					$a['username'] = $_POST['username'];
					if ($_POST['password'] != '') {
						$a['password'] = $_POST['password'];
					}
					$a['role'] = intval($_POST['role']);
					if ($a['role'] !== TINYIB_SUPER_ADMINISTRATOR && $a['role'] != TINYIB_ADMINISTRATOR && $a['role'] != TINYIB_MODERATOR && $a['role'] != TINYIB_DISABLED) {
						fancyDie(__('Invalid role.'));
					}

					if ($id == 0) {
						insertAccount($a);
						manageLogAction(sprintf(__('Added account %s'), $a['username']));
						$text .= manageInfo(__('Added account'));
					} else {
						updateAccount($a);
						if ($a['username'] != $prev['username']) {
							manageLogAction(sprintf(__('Renamed account %1$s as %2$s'), $prev['username'], $a['username']));
						}
						if ($a['password'] != $prev['password']) {
							manageLogAction(sprintf(__('Changed password of account %s'), $a['username']));
						}
						if ($a['role'] != $prev['role']) {
							$r = '';
							switch ($a['role']) {
								case  TINYIB_SUPER_ADMINISTRATOR:
									$r = __('Super-administrator');
									break;
								case  TINYIB_ADMINISTRATOR:
									$r = __('Administrator');
									break;
								case TINYIB_MODERATOR:
									$r = __('Moderator');
									break;
								case  TINYIB_DISABLED:
									$r = __('Disabled');
									break;
							}
							manageLogAction(sprintf(__('Changed role of account %s to %s'), $a['username'], $r));
						}
						$text .= manageInfo(__('Updated account'));
					}
				}

				$onload = manageOnLoad('accounts');
				$text .= manageAccountForm($_GET['accounts']);
				if (intval($_GET['accounts']) == 0) {
					$text .= manageAccountsTable();
				}
			} elseif (isset($_GET['keywords'])) {
				$keyword_id = intval($_GET['keywords']);
				if (isset($_POST['text']) && is_string($_POST['text']) && $_POST['text'] != '') {
					$allowed_actions = array('delete', 'hide');
					if (TINYIB_REPORT && TINYIB_REQMOD != 'all') {
						$allowed_actions[] = 'report';
					}
					$keyword_action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
					if (!in_array($keyword_action, $allowed_actions, true)) {
						fancyDie(__('Invalid keyword action.'));
					}

					if ($keyword_id > 0) {
						deleteKeyword($keyword_id);
					}

					$keyword_exists = keywordByText($_POST['text']);
					if ($keyword_exists) {
						fancyDie(__('Sorry, that keyword has already been added.'));
					}

					$keyword = array();
					$keyword['text'] = $_POST['text'];
					$keyword['action'] = $keyword_action;

					$kw = $keyword['text'];

					if (isset($_POST['regexp']) && $_POST['regexp'] == '1') {
						$keyword['text'] = 'regexp:' . $keyword['text'];
					}

					insertKeyword($keyword);
					if ($keyword_id > 0) {
						manageLogAction(sprintf(__('Updated keyword %s'), htmlEscape($kw)));
						$text .= manageInfo(__('Keyword updated.'));
						$keyword_id = 0;
					} else {
						manageLogAction(sprintf(__('Updated keyword %s'), htmlEscape($kw)));
						$text .= manageInfo(__('Keyword added.'));
					}
				} elseif (isset($_GET['deletekeyword'])) {
					$keyword = keywordByID($_GET['deletekeyword']);
					if (empty($keyword)) {
						fancyDie(__('That keyword does not exist.'));
					}

					$kw = $keyword['text'];
					if (substr($keyword['text'], 0, 7) == 'regexp:') {
						$kw = substr($keyword['text'], 7);
					}

					deleteKeyword($_GET['deletekeyword']);
					manageLogAction(sprintf(__('Deleted keyword %s'), htmlEscape($kw)));
					$text .= manageInfo(__('Keyword deleted.'));
				}

				$onload = manageOnLoad('keywords');
				if ($keyword_id > 0) {
					$text .= manageEditKeyword($keyword_id);
				} else {
					$text .= manageEditKeyword(0);
					$text .= manageKeywordsTable();
				}
			}
		}

		if (isset($_GET['delete'])) {
			$post_ids = explode(',', $_GET['delete']);
			$posts = array();
			foreach ($post_ids as $post_id) {
				$post = postByID($post_id);
				if (!$post) {
					continue; // The post has already been deleted
				}
				$posts[$post_id] = $post;
			}
			foreach ($post_ids as $post_id) {
				if (!isset($posts[$post_id])) {
					continue;
				}
				$post = $posts[$post_id];

				deletePost($post['id']);
				if ($post['parent'] == TINYIB_NEWTHREAD) {
					rebuildThread($post['id']);
				} else {
					rebuildThread($post['parent']);
				}

				$action = sprintf(__('Deleted %s'),'&gt;&gt;' . $post['id']) . ' - ' . hashData($post['ip']);
				$stripped = strip_tags($post['message']);
				if ($stripped != '') {
					$action .= ' - ' . htmlEscape(_substr($stripped, 0, 32));
					if (_strlen($stripped) > 32) {
						$action .= '...';
					}
				}
				manageLogAction($action);
			}
			rebuildIndexes();
			$deleted_count = count($posts);
			if ($deleted_count == 1) {
				$text .= manageInfo(__('Deleted 1 post'));
			} else {
				$text .= manageInfo(sprintf(__('Deleted %d posts'), $deleted_count));
			}
		} elseif (isset($_GET['approve'])) {
			if ($_GET['approve'] > 0) {
				$post = postByID($_GET['approve']);
				if ($post) {
					approvePostByID($post['id'], 2);
					$thread_id = $post['parent'] == TINYIB_NEWTHREAD ? $post['id'] : $post['parent'];

					if (strtolower($post['email']) != 'sage' && (TINYIB_MAXREPLIES == 0 || numRepliesToThreadByID($thread_id) <= TINYIB_MAXREPLIES)) {
						bumpThreadByID($thread_id);
					}
					threadUpdated($thread_id);

					manageLogAction(__('Approved') . ' ' . postLink('&gt;&gt;' . $post['id']));
					$text .= manageInfo(sprintf(__('Post No.%d approved.'), $post['id']));
				} else {
					fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
				}
			}
		} elseif (isset($_GET['moderate'])) {
			if ($_GET['moderate'] != '' && $_GET['moderate'] != '0') {
				$post_ids = explode(',', $_GET['moderate']);
				$compact = count($post_ids) > 1;
				$posts = array();
				$threads = 0;
				$replies = 0;

				foreach ($post_ids as $post_id) {
					$post = postByID($post_id);
					if (!$post) {
						fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
					}
					if ($post['parent'] == TINYIB_NEWTHREAD) {
						$threads++;
					} else {
						$replies++;
					}

					$posts[$post_id] = $post;
				}

				if (count($post_ids) > 1) {
					$text .= manageModerateAll($post_ids, $threads, $replies);
				}
				foreach ($post_ids as $post_id) {
					$text .= manageModeratePost($posts[$post_id], $compact);
				}
			} else {
				$onload = manageOnLoad('moderate');
				$text .= manageModeratePostForm();
			}
		} elseif (isset($_GET['sticky']) && isset($_GET['setsticky'])) {
			if ($_GET['sticky'] > 0) {
				$post = postByID($_GET['sticky']);
				if ($post && $post['parent'] == TINYIB_NEWTHREAD) {
					stickyThreadByID($post['id'], intval($_GET['setsticky']));
					threadUpdated($post['id']);

					$actionMessage = intval($_GET['setsticky']) == 1 ? __('Stickied') : __('Unstickied') . ' ' . postLink('&gt;&gt;' . $post['id']);
					manageLogAction($actionMessage);
					$text .= manageInfo($actionMessage);
				} else {
					fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
				}
			} else {
				fancyDie(__('Form data was lost. Please go back and try again.'));
			}
		} elseif (isset($_GET['lock']) && isset($_GET['setlock'])) {
			if ($_GET['lock'] > 0) {
				$post = postByID($_GET['lock']);
				if ($post && $post['parent'] == TINYIB_NEWTHREAD) {
					lockThreadByID($post['id'], intval($_GET['setlock']));
					threadUpdated($post['id']);

					$actionMessage = intval($_GET['setlock']) == 1 ? __('Locked') : __('Unlocked') . ' ' . postLink('&gt;&gt;' . $post['id']);
					manageLogAction($actionMessage);
					$text .= manageInfo($actionMessage);
				} else {
					fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
				}
			} else {
				fancyDie(__('Form data was lost. Please go back and try again.'));
			}
		} elseif (isset($_GET['clearreports'])) {
			if ($_GET['clearreports'] > 0) {
				$post = postByID($_GET['clearreports']);
				if ($post) {
					approvePostByID($post['id'], 2);
					deleteReportsByPost($post['id']);

					manageLogAction(__('Approved') . ' ' . postLink('&gt;&gt;' . $post['id']));
					$text .= manageInfo(sprintf(__('Post No.%d approved.'), $post['id']));
				} else {
					fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
				}
			}
		} elseif (isset($_GET["staffpost"])) {
			$onload = manageOnLoad("staffpost");
			$text .= buildPostForm(0, true);
		} elseif (isset($_GET['changepassword'])) {
			if ($account['username'] == 'admin' && TINYIB_ADMINPASS != '') {
				fancyDie(__('This account may not be updated while TINYIB_ADMINPASS is set.'));
			} else if ($account['username'] == 'mod' && TINYIB_MODPASS != '') {
				fancyDie(__('This account may not be updated while TINYIB_MODPASS is set.'));
			}

			if (isset($_POST['password']) && isset($_POST['confirm'])) {
				if ($_POST['password'] == '') {
					fancyDie(__('A password is required.'));
				} else if ($_POST['password'] != $_POST['confirm']) {
					fancyDie(__('Passwords do not match.'));
				}

				$account['password'] = $_POST['password'];
				updateAccount($account);

				$text .= manageInfo(__('Password updated'));
			}

			$text .= manageChangePasswordForm();
		}

		if ($text == '') {
			$text = manageStatus();
		}
	} else {
		$onload = manageOnLoad('login');
		$text .= manageLogInForm();
	}

	echo managePage($text, $onload);
} elseif (!file_exists(TINYIB_INDEX) || countThreads() == 0) {
	rebuildIndexes();
}

if ($redirect) {
	echo '--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="' . (isset($slow_redirect) ? '3' : '0') . ';url=' . (is_string($redirect) ? $redirect : TINYIB_INDEX) . '">';
}
