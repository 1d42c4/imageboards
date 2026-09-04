<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

if (realpath($_SERVER['SCRIPT_FILENAME']) == str_replace('\\', '/', __FILE__)) {
	// You cannot request this file directly.
	exit;
}

/*
	joaoptm78@gmail.com
	http://www.php.net/manual/en/function.filesize.php#100097
*/
function format_bytes($size) {
	$units = array(' B', ' KB', ' MB', ' GB', ' TB');
	for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
	return round($size, 2).$units[$i];
}

/** Secure-link tokens for mod post controls (avoids fragile template concat). */
function mod_post_control_tokens(?array $board, object $post): array {
	if (!$board || empty($board['dir']) || empty($post->id)) {
		return [];
	}
	$dir = $board['dir'];
	$id = (int)$post->id;
	$tok = static function (string $uri): string {
		return make_secure_link_token($uri);
	};
	return [
		'delete_token' => $tok("{$dir}delete/{$id}"),
		'deletebyip_token' => $tok("{$dir}deletebyip/{$id}"),
		'sticky_token' => $tok("{$dir}sticky/{$id}"),
		'unsticky_token' => $tok("{$dir}unsticky/{$id}"),
		'lock_token' => $tok("{$dir}lock/{$id}"),
		'unlock_token' => $tok("{$dir}unlock/{$id}"),
		'archive_token' => $tok("{$dir}archive/{$id}"),
		'bumplock_token' => $tok("{$dir}bumplock/{$id}"),
		'bumpunlock_token' => $tok("{$dir}bumpunlock/{$id}"),
	];
}

/**
 * Render one segment of the manual boardlist.
 *
 * Entries (all edited by hand in config — never auto-filled from DB):
 *   'Label' => '/path/'              — custom label + any relative or absolute URL
 *   'Label' => 'https://example.com' — external site
 *   'Group' => [ ... nested ... ]    — subgroup in [brackets]
 *   'b'                              — short form (numeric key): /b/index.html only
 */
function doBoardListPart($list, $root, &$boards) {
	global $config;

	$body = '';
	if (!is_array($list) && !is_object($list)) {
		return $body;
	}
	foreach ($list as $key => $board) {
		if (is_array($board)) {
			$desc = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8');
			$body .= ' <span class="sub" data-description="' . $desc . '">['
				. doBoardListPart($board, $root, $boards) . ']</span> ';
			continue;
		}

		$val = (string)$board;
		// Custom URL if value looks like a path/URL (works even when PHP casts "1" keys to int)
		$is_url = (bool)preg_match('#^(https?:)?/|^[a-z][a-z0-9+.-]*:#i', $val);
		if (is_string($key) || $is_url) {
			$href = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
			$label = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8');
			$body .= ' <a href="' . $href . '">' . $label . '</a> /';
			continue;
		}

		// Short form: bare board URI → site root + /uri/index.html
		$uri = $val;
		$title = isset($boards[$uri])
			? ' title="' . htmlspecialchars($boards[$uri], ENT_QUOTES, 'UTF-8') . '"'
			: '';
		$href = htmlspecialchars($root . $uri . '/' . $config['file_index'], ENT_QUOTES, 'UTF-8');
		$label = htmlspecialchars($uri, ENT_QUOTES, 'UTF-8');
		$body .= ' <a href="' . $href . '"' . $title . '>' . $label . '</a> /';
	}
	$body = preg_replace('/\/$/', '', $body);

	return $body;
}

/**
 * Top/bottom navigation strip. Completely manual via $config['boards'].
 * Creating a board in mod does not add a link here — edit instance-config.php.
 */
function createBoardlist($mod=false) {
	global $config;

	$list = $config['boards'] ?? [];
	if ($list === false || $list === null || $list === [] || !is_array($list)) {
		return ['top' => '', 'bottom' => ''];
	}

	// Optional titles only when using bare URI short form
	$boards = [];
	foreach (listBoards() ?: [] as $val) {
		$boards[$val['uri']] = $val['title'];
	}

	// Mod overlay uses ?/… only for bare-URI short entries; label=>URL is left as written.
	$body = doBoardListPart($list, $mod ? '?/' : $config['root'], $boards);

	if (!empty($config['boardlist_wrap_bracket']) && !preg_match('/\] $/', $body)) {
		$body = '[' . $body . ']';
	}

	$body = trim($body);
	if ($body === '') {
		return ['top' => '', 'bottom' => ''];
	}

	$top = "<script type='text/javascript'>if (typeof do_boardlist != 'undefined') do_boardlist();</script>";

	return [
		'top' => '<div class="boardlist">' . $body . '</div>' . $top,
		'bottom' => '<div class="boardlist bottom">' . $body . '</div>',
	];
}

function error($message, $priority = true, $debug_stuff = []) {
	global $board, $mod, $config, $db_error;

	if (!empty($config['syslog']) && $priority !== false) {
		// Use LOG_NOTICE instead of LOG_ERR or LOG_WARNING because most error message are not significant.
		_syslog($priority !== true ? $priority : LOG_NOTICE, $message);
	}

	if (defined('STDIN')) {
		// Running from CLI
		echo('Error: ' . $message . "\n");
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		die();
	}

	if ($config['debug'] && isset($db_error)) {
		$debug_stuff = array_combine(array('SQLSTATE', 'Error code', 'Error message'), $db_error);
	}

	if ($config['debug']) {
		$debug_stuff['backtrace'] = debug_backtrace();
	}

	if (isset($_POST['json_response'])) {
		header('Content-Type: text/json; charset=utf-8');
		die(json_encode(array(
			'error' => $message
		)));
	}
	else {
		header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
	}

	$pw = $config['db']['password'];
	$debug_callback = function($item) use (&$debug_callback, $pw) {
		if (is_array($item)) {
			$item = array_filter($item, $debug_callback);
		}
		return ($item !== $pw || !$pw);
	};


	if ($debug_stuff)
		$debug_stuff = array_filter($debug_stuff, $debug_callback);

	die(Element($config['file_page_template'], array(
		'config' => $config,
		'title' => _('Error'),
		'subtitle' => _('An error has occured.'),
		'body' => Element($config['file_error'], array(
			'config' => $config,
			'message' => $message,
			'mod' => $mod,
			'board' => isset($board) ? $board : false,
			'debug' => $config['debug'] ? (is_array($debug_stuff) ? str_replace("\n", '&#10;', utf8tohtml(print_r($debug_stuff, true))) : utf8tohtml($debug_stuff)) : null
		))
	)));
}

function capcode($cap) {
	global $config;

	if (!$cap)
		return false;

	$capcode = array();
	if (isset($config['custom_capcode'][$cap])) {
		if (is_array($config['custom_capcode'][$cap])) {
			$capcode['cap'] = sprintf($config['custom_capcode'][$cap][0], $cap);
			if (isset($config['custom_capcode'][$cap][1]))
				$capcode['name'] = $config['custom_capcode'][$cap][1];
			if (isset($config['custom_capcode'][$cap][2]))
				$capcode['trip'] = $config['custom_capcode'][$cap][2];
		} else {
			$capcode['cap'] = sprintf($config['custom_capcode'][$cap], $cap);
		}
	} else {
		$capcode['cap'] = sprintf($config['capcode'], $cap);
	}

	return $capcode;
}

/**
 * Preview truncate for board index list views only.
 * Full thread pages render {{ post.body }} with no truncate (up to max_body).
 */
function truncate($body, $url, $max_lines = false, $max_chars = false) {
	global $config;

	if ($max_lines === false) {
		$max_lines = (int)$config['body_truncate'];
	}
	if ($max_chars === false) {
		$max_chars = (int)$config['body_truncate_char'];
	}

	// Strip comments first
	$body = preg_replace('/<!--.*?-->/s', '', (string)$body);
	// Normalize breaks for consistent line counting
	$body = preg_replace('/<br\s*\/?>/i', '<br/>', $body);
	$original_body = $body;

	$lines = substr_count($body, '<br/>');
	if ($max_lines > 0 && $lines > $max_lines) {
		if (preg_match('/(((.*?)<br\/>){' . $max_lines . '})/s', $body, $m)) {
			$body = $m[0];
		}
	}
	if ($max_chars > 0 && mb_strlen($body) > $max_chars) {
		$body = mb_substr($body, 0, $max_chars);
	}

	if ($body === $original_body) {
		return $body;
	}

	// Drop broken trailing tags / entities
	$body = preg_replace('/<([\w]+)?([^>]*)?$/', '', $body);
	$body = preg_replace('/&[^;]*$/', '', $body);

	if (preg_match_all('/<([\w]+)[^>]*>/', $body, $open_tags)) {
		$tags = [];
		foreach ($open_tags[0] as $i => $full) {
			if (!preg_match('/\/\s*>$/', $full)) {
				$tags[] = $open_tags[1][$i];
			}
		}
		if (preg_match_all('/<\/([\w]+)>/', $body, $closed_tags)) {
			foreach ($closed_tags[1] as $closed) {
				$found = array_search($closed, $tags, true);
				if ($found !== false) {
					unset($tags[$found]);
				}
			}
		}
		$void = ['br', 'img', 'hr', 'meta', 'link', 'input', 'colgroup', 'dd', 'dt', 'li', 'optgroup', 'option', 'p', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'];
		foreach ($tags as $tag) {
			if (!in_array(strtolower($tag), $void, true)) {
				$body .= "</{$tag}>";
			}
		}
	}

	$href = $url ? htmlspecialchars((string)$url, ENT_QUOTES, 'UTF-8') : '#';
	$body .= '<span class="toolong">'
		. sprintf(_('Post too long. Click <a href="%s">here</a> to view the full text.'), $href)
		. '</span>';

	return $body;
}

function bidi_cleanup($data) {
	// Closes all embedded RTL and LTR unicode formatting blocks in a string so that
	// it can be used inside another without controlling its direction.

	$explicits	= '\xE2\x80\xAA|\xE2\x80\xAB|\xE2\x80\xAD|\xE2\x80\xAE';
	$pdf		= '\xE2\x80\xAC';

	preg_match_all("!$explicits!",	$data, $m1, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
	preg_match_all("!$pdf!", 	$data, $m2, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

	if (count($m1) || count($m2)){

		$p = array();
		foreach ($m1 as $m){ $p[$m[0][1]] = 'push'; }
		foreach ($m2 as $m){ $p[$m[0][1]] = 'pop'; }
		ksort($p);

		$offset = 0;
		$stack = 0;
		foreach ($p as $pos => $type){

			if ($type == 'push'){
				$stack++;
			}else{
				if ($stack){
					$stack--;
				}else{
					# we have a pop without a push - remove it
					$data = substr($data, 0, $pos-$offset)
						.substr($data, $pos+3-$offset);
					$offset += 3;
				}
			}
		}

		# now add some pops if your stack is bigger than 0
		for ($i=0; $i<$stack; $i++){
			$data .= "\xE2\x80\xAC";
		}

		return $data;
	}

	return $data;
}

function embed_html($link) {
	// Embedding providers removed — never trust stored HTML embeds.
	return '';
}

#[AllowDynamicProperties]
class Post {
	public function __construct($post, $root=null, $mod=false) {
		global $config;
		if (!isset($root))
			$root = &$config['root'];

		foreach ($post as $key => $value) {
			$this->{$key} = $value;
		}

		if (isset($this->files) && $this->files) {
			$this->files = is_string($this->files) ? json_decode($this->files) : $this->files;
			// Compatibility for posts before individual file hashing
						foreach ($this->files as $i => &$file) {
				if (empty($file)) {
					unset($this->files[$i]);
					continue;
				}
				if (is_array($file)) {
					if (!isset($file['hash'])) {
						$file['hash'] = $this->filehash;
					}
				} else if (is_object($file)) {
					if (!isset($file->hash)) {
						$file->hash = $this->filehash;
					}
				}
			}
		}

		$this->subject = utf8tohtml($this->subject);
		$this->name = utf8tohtml($this->name);
		$this->mod = $mod;
		$this->root = $root;

		if ($this->embed)
			$this->embed = embed_html($this->embed);

		$this->modifiers = extract_modifiers($this->body_nomarkup);

		if ($config['always_regenerate_markup']) {
			$this->body = $this->body_nomarkup;
			markup($this->body);
		}

		if ($this->mod)
			// Fix internal links
			// Very complicated regex
			$this->body = preg_replace(
				'/<a((([a-zA-Z]+="[^"]+")|[a-zA-Z]+=[a-zA-Z]+|\s)*)href="' . preg_quote($config['root'], '/') . '(' . sprintf(preg_quote($config['board_path'], '/'), $config['board_regex']) . ')/u',
				'<a $1href="?/$4',
				$this->body
			);
	}
	public function link($pre = '', $page = false) {
		global $config, $board;

		return $this->root . $board['dir'] . $config['dir']['res'] . link_for((array)$this, $page == '50') . '#' . $pre . $this->id;
	}

	public function build($index=false) {
		global $board, $config;

		$options = [
			'config' => $config,
			'board' => $board,
			'post' => &$this,
			'index' => $index,
			'mod' => $this->mod
		];
		if ($this->mod) {
			$options += mod_post_control_tokens($board, $this);
		}

		return Element($config['file_post_reply'], $options);
	}
};

#[AllowDynamicProperties]
class Thread {
	public function __construct($post, $root = null, $mod = false, $hr = true) {
		global $config;
		if (!isset($root))
			$root = &$config['root'];

		foreach ($post as $key => $value) {
			$this->{$key} = $value;
		}

		if (isset($this->files))
			$this->files = is_string($this->files) ? json_decode($this->files) : $this->files;

		$this->subject = utf8tohtml($this->subject);
		$this->name = utf8tohtml($this->name);
		$this->mod = $mod;
		$this->root = $root;
		$this->hr = $hr;

		$this->posts = array();
		$this->omitted = 0;
		$this->omitted_images = 0;

		if ($this->embed)
			$this->embed = embed_html($this->embed);

		$this->modifiers = extract_modifiers($this->body_nomarkup);

		if ($config['always_regenerate_markup']) {
			$this->body = $this->body_nomarkup;
			markup($this->body);
		}

		if ($this->mod)
			// Fix internal links
			// Very complicated regex
			$this->body = preg_replace(
				'/<a((([a-zA-Z]+="[^"]+")|[a-zA-Z]+=[a-zA-Z]+|\s)*)href="' . preg_quote($config['root'], '/') . '(' . sprintf(preg_quote($config['board_path'], '/'), $config['board_regex']) . ')/u',
				'<a $1href="?/$4',
				$this->body
			);
	}
	public function link($pre = '', $page = false) {
		global $config, $board;

		return $this->root . $board['dir'] . $config['dir']['res'] . link_for((array)$this) . '#' . $pre . $this->id;
	}
	public function add(Post $post) {
		$this->posts[] = $post;
	}
	public function postCount() {
		   return count($this->posts) + $this->omitted;
	}
	public function build($index=false) {
		global $board, $config, $debug;

		event('show-thread', $this);

		$options = [
			'config' => $config,
			'board' => $board,
			'post' => &$this,
			'index' => $index,
			'mod' => $this->mod
		];
		if ($this->mod) {
			$options += mod_post_control_tokens($board, $this);
		}

		$built = Element($config['file_post_thread'], $options);

		return $built;
	}
};
