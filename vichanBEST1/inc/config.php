<?php
/**
 * Default configuration — keep overrides in secrets.php / instance-config.php.
 * This fork: PHP 8 + PostgreSQL, no Composer, privacy-first.
 */
defined('TINYBOARD') or exit;

// --- General ---
$config['version'] = 'slim';
$config['timezone'] = 'America/Los_Angeles';
$config['debug'] = false;
$config['verbose_errors'] = false;
$config['deprecation_errors'] = false;
$config['debug_explain'] = false;
$config['twig_auto_reload'] = false; // recompile view templates when source changes if true
$config['tmp'] = sys_get_temp_dir();
$config['redirect_http'] = 303;
$config['has_installed'] = '.installed';
$config['root'] = '/';
$config['root_file'] = false;
// Used only for display/logging; native captcha verifies in-process (no self-HTTP).
$config['domain'] = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
$config['locale'] = 'en';
$config['http_timeout'] = 10; // remote captcha HTTP client (recaptcha/hcaptcha)
$config['auto_maintenance'] = true;
$config['syslog'] = false;
$config['log_system'] = [
	'type' => 'error_log',
	'name' => 'vichan',
	'syslog_stderr' => false,
	'file_path' => '/var/log/vichan.log',
];

// Client addresses are never read or stored.

// --- Database (override in secrets.php) ---
$config['db']['type'] = 'pgsql';
$config['db']['server'] = '127.0.0.1';
$config['db']['port'] = '5432';
$config['db']['database'] = 'vichan';
$config['db']['user'] = '';
$config['db']['password'] = '';
$config['db']['dsn'] = '';
$config['db']['timeout'] = 30;
$config['db']['persistent'] = false;

// --- Cookies / salts (override salts in secrets.php) ---
$config['cookies'] = [
	'mod' => 'mod',
	'js' => 'serv',
	'salt' => 'abcdefghijklmnopqrstuvwxyz09123456789!@#$%^&*()',
	'path' => '/',
	'time' => 60 * 60 * 24 * 30 * 3,
	'expire' => 60 * 60 * 24 * 30 * 3,
	'jail' => true,
	'secure_login_only' => 0,
];
$config['secure_trip_salt'] = ')(*&^%$#@!98765432190zyxwvutsrqponmlkjihgfedcba';
$config['password_crypt'] = '$6$rounds=25000$'; // legacy v1 crypt() prefix only
$config['password_crypt_version'] = 2; // 2 = password_hash(PASSWORD_DEFAULT)

// --- Limits / optional captcha ---
$config['max_links'] = 20;
$config['max_cites'] = 45;
$config['max_filesize'] = 10 * 1024 * 1024;

// Optional captcha: false | 'native' | 'recaptcha' | 'hcaptcha'
// Native: JS loads via provider_get (JSON); noscript uses ?raw=1 PNG + captcha_cookie.
$config['captcha'] = [
	'provider' => false,
	'native' => [
		'provider_get' => '/securimage.php',
		'extra' => 'abcdefghijklmnopqrstuvwxyz',
		'new_thread_capt' => false, // true = only OPs need captcha
	],
	'recaptcha' => ['sitekey' => '', 'secret' => ''],
	'hcaptcha' => ['sitekey' => '', 'secret' => ''],
];
$config['report_captcha'] = false;

// --- Posts ---
$config['anonymous'] = 'Anonymous';
$config['force_body'] = true;
$config['force_body_op'] = true;
$config['force_image_op'] = false; // text-only threads OK
$config['max_body'] = 100000; // full post length allowed (stored + full thread view)
$config['maximum_lines'] = 500; // hard reject posts with more newlines than this
// Board index preview only (full thread/reply page shows entire body):
$config['body_truncate'] = 15; // max lines on index
$config['body_truncate_char'] = 2500; // and max chars on index (dense text without newlines)
$config['max_images'] = 1;
$config['multiimage_method'] = 'split';
$config['reply_limit'] = 250;
$config['reply_hard_limit'] = 0;
$config['image_hard_limit'] = 0;
$config['threads_per_page'] = 10;
$config['max_pages'] = 10;
$config['threads_preview'] = 5;
$config['threads_preview_sticky'] = 1;
// User self-delete removed — mods delete via the mod panel only.
$config['always_noko'] = true;
$config['always_sage'] = false;
$config['track_cites'] = true;
$config['always_regenerate_markup'] = false;
$config['auto_unicode'] = true;
$config['markup_urls'] = true;
$config['link_prefix'] = '';
$config['url_ads'] = &$config['link_prefix'];
$config['early_404'] = false;
$config['early_404_page'] = 3;
$config['early_404_replies'] = 5;
$config['early_404_staged'] = false;
$config['wordfilters'] = [];
$config['field_disable_name'] = false;
$config['field_disable_email'] = true; // email form field removed; always_sage uses column only
$config['field_disable_subject'] = false;
$config['field_disable_reply_subject'] = false;
$config['hide_sage'] = true;
$config['hide_email'] = true;
$config['spoiler_images'] = false;
$config['strip_superfluous_returns'] = true;
$config['strip_combining_chars'] = true;
$config['max_combining_chars'] = 3;
$config['slugify'] = false;
$config['slug_max_size'] = 80;
$config['tripcodes'] = ['enabled' => true, 'length' => 12, 'prefix' => '!!'];
$config['disable_tripcodes'] = false;
$config['custom_tripcode'] = [];
$config['custom_capcode'] = [];
$config['capcode'] = ' <span class="capcode">## %s</span>';
$config['button_newtopic'] = _('New Topic');
$config['button_reply'] = _('New Reply');
$config['show_modname'] = false;
$config['report_limit'] = 3;
$config['report_max_length'] = 30;
$config['board_locked'] = false;
// When true, deleting a non-sage reply recalculates the parent thread bump time
$config['recalc_bump_on_delete'] = false;

// --- Images (images only; no video/webm stack) ---
$config['allowed_ext'] = ['jpg', 'jpeg', 'bmp', 'gif', 'png', 'webp'];
$config['allowed_ext_op'] = [];
$config['allowed_ext_files'] = []; // non-image uploads disabled
$config['file_icons'] = ['default' => 'file.png']; // fallback thumb for non-images if re-enabled
$config['thumb_width'] = 255;
$config['thumb_height'] = 255;
$config['thumb_op_width'] = 255;
$config['thumb_op_height'] = 255;
$config['thumb_ext'] = '';
// Thumbnails use PHP GD only. Animated GIFs keep the original as the "thumb".
$config['max_width'] = 10000;
$config['max_height'] = 10000;
$config['strip_exif'] = false; // true = re-encode with GD to drop metadata
$config['redraw_image'] = false; // true = always re-encode with GD
$config['show_filename'] = true;
$config['max_filename_len'] = 255;
$config['filename_func'] = function ($f) {
	return time() . substr(microtime(), 2, 3);
};
$config['image_reject_repost'] = false;
$config['image_reject_repost_in_thread'] = false;

// --- Markup ---
$config['markup'] = [
	["/\[b\](.+?)\[\/b\]/s", '<strong>$1</strong>'],
	["/\[i\](.+?)\[\/i\]/s", '<em>$1</em>'],
	["/\*\*(.+?)\*\*/s", '<strong>$1</strong>'],
	["/\*(.+?)\*/s", '<em>$1</em>'],
	["/%%(.+?)%%/s", '<span class="spoiler">$1</span>'],
];
$config['markup_code'] = false;
$config['always_regenerate_markup'] = false;
// Embeds disabled (empty so old posts with embed column still render safely)
$config['embedding'] = [];

// --- Display / UI ---
$config['locale'] = 'en';
$config['post_date'] = '%m/%d/%y (%a) %H:%M:%S';
$config['url_banner'] = false;
$config['banner_width'] = false;
$config['banner_height'] = false;
$config['url_favicon'] = false;
$config['default_stylesheet'] = ['Yotsuba B', ''];
$config['stylesheets'] = [
	'Yotsuba B' => '',
	'Yotsuba C' => 'yotsuba_b.css',
	'Yotsuba' => 'yotsuba.css',
	'Futaba' => 'futaba.css',
	'Futaba Light' => 'futaba-light.css',
	'Burichan' => 'burichan.css',
	'Dark' => 'dark.css',
	'Tomorrow' => 'tomorrow.css',
	'Terminal' => 'terminal2.css',
	'Green Dark' => 'greendark.css',
	'Miku' => 'miku.css',
	'Pink' => 'pink.css',
	'Photon' => 'photon.css',
	'Notsuba' => 'notsuba.css',
];
$config['boardlist_wrap_bracket'] = false;
$config['page_nav_top'] = false;
$config['catalog_link'] = 'catalog.html';
$config['catalog'] = ['enabled' => true, 'title' => 'Catalog', 'subtitle' => '', 'rss' => true];
// Threads pushed past max_pages are kept here (read-only HTML + DB copy; images stay on disk).
$config['archive'] = [
	'enabled' => true,
	'dir' => 'archive/',
	'file_index' => 'index.html',
	'file_page' => '%d.html',          // per-thread page
	'file_list_page' => 'list-%d.html', // archive index pages 2+
	'file_thread' => 'archive_thread.html',
	'file_index_template' => 'archive_index.html',
	'threads_per_page' => 50,
];
$config['homepage'] = ['title' => 'Boards', 'boards_file' => 'boards.html'];
// Top/bottom nav links — MANUAL ONLY (edit in instance-config.php).
// New boards are never added here automatically. Use label => URL for full control:
//   $config['boards'] = [
//     'Home'  => '/',
//     'b'     => '/b/',
//     'Lichess' => 'https://lichess.org',
//     'Tools' => [ 'Analysis' => 'https://…', 'Puzzles' => '/puzzles/' ],
//   ];
// Empty array = no top strip.
$config['boards'] = [];
$config['minify_html'] = true;
$config['ad'] = ['top' => '', 'bottom' => ''];
$config['blotter'] = &$config['global_message'];
$config['footer'] = [];
$config['content_lazy_loading'] = false;
$config['additional_javascript'] = [
	'js/inline-expanding.js',
	'js/hide-form.js',
];
$config['additional_javascript_url'] = false;
$config['resource_version'] = 0;

// Paths / filenames
$config['board_path'] = '%s/';
$config['board_regex'] = '[0-9a-zA-Z$_\x{0080}-\x{FFFF}]{1,58}';
$config['board_abbreviation'] = '/%s/';
$config['post_url'] = $config['root'] . 'post.php';
$config['file_index'] = 'index.html';
$config['file_page'] = '%d.html';
$config['file_page_slug'] = '%d-%s.html';
$config['file_page_template'] = 'page.html';
$config['file_board_index'] = 'index.html';
$config['file_catalog'] = 'catalog.html';
$config['file_thread'] = 'thread.html';
$config['file_post_reply'] = 'post_reply.html';
$config['file_post_thread'] = 'post_thread.html';
$config['file_post'] = 'post.php';
$config['file_mod'] = 'mod.php';
$config['file_script'] = 'main.js';
$config['file_login'] = 'mod/login.html';
$config['file_error'] = 'error.html';
$config['file_report'] = 'report.html';
$config['file_mod_dashboard'] = 'mod/dashboard.html';
$config['file_mod_login'] = 'mod/login.html';
$config['file_mod_board'] = 'mod/board.html';
$config['file_mod_log'] = 'mod/log.html';
$config['file_mod_users'] = 'mod/users.html';
$config['file_mod_user'] = 'mod/user.html';
$config['file_mod_confim'] = 'mod/confirm.html';
$config['file_mod_rebuild'] = 'mod/rebuild.html';
$config['file_mod_rebuilt'] = 'mod/rebuilt.html';
$config['file_mod_reports'] = 'mod/reports.html';
$config['file_mod_report'] = 'mod/report.html';
$config['file_mod_recent_posts'] = 'mod/recent_posts.html';
$config['file_mod_edit_post_form'] = 'mod/edit_post_form.html';
$config['file_mod_pending'] = 'mod/pending.html';
$config['file_thumb'] = 'file.png';
$config['spoiler_image'] = 'static/spoiler.png';
$config['image_deleted'] = 'static/deleted.png';
$config['image_sticky'] = 'static/locked.gif';
$config['image_locked'] = 'static/locked.gif';
$config['image_bumplocked'] = 'static/locked.gif';
$config['image_blank'] = 'static/blank.gif';
$config['dir'] = [
	'img' => 'src/',
	'thumb' => 'thumb/',
	'res' => 'res/',
	'static' => 'static/',
	'template' => getcwd() . '/templates',
	'home' => '',
];
// uri_thumb / uri_img / url_stylesheet / url_javascript / uri_stylesheets
// are filled in loadConfig() when a board is opened — do not set them to false here.



// Generation always immediate
$config['page_404'] = '/404.html';
$config['try_smarter'] = true;
$config['gzip_static'] = false;
// Cache: false | 'php' (request array) | 'fs' | 'none'
$config['cache'] = [
	'enabled' => false,
	'timeout' => 60 * 60 * 48,
	'prefix' => '',
];
$config['cache_config'] = false;
$config['purge'] = [];
$config['purge_timeout'] = 3;

// --- Errors (used by templates / post.php) ---
$config['error'] = [
	'toolong' => _('The %s field was too long.'),
	'toolong_body' => _('The body was too long.'),
	'tooshort_body' => _('The body was too short or empty.'),
	'noimage' => _('You must upload an image.'),
	'nomove' => _('The server failed to handle your upload.'),
	'fileext' => _('Unsupported image format.'),
	'noboard' => _('Invalid board!'),
	'nonexistant' => _('Thread specified does not exist.'),
	'locked' => _('Thread locked. You may not reply at this time.'),
	'board_password' => _('Incorrect board password.'),
	'pending_approval' => _('Your post was submitted and is waiting for moderator approval before it appears.'),
	'reply_hard_limit' => _('Thread has reached its maximum reply limit.'),
	'image_hard_limit' => _('Thread has reached its maximum image limit.'),
	'nopost' => _('You didn\'t make a post.'),
	'toomanylinks' => _('Too many links.'),
	'toomanycites' => _('Too many cites; post discarded.'),
	'toomanycross' => _('Too many cross-board links; post discarded.'),
	'invalidimg' => _('Invalid image.'),
	'unknownext' => _('Unknown file extension.'),
	'filesize' => _('Maximum file size: %maxsz% bytes<br>Your file\'s size: %filesz% bytes'),
	'maxsize' => _('The file was too big.'),
	'invalidimage' => _('Corrupted image.'),
	'fileexists' => _('That file already exists!'),
	'fileexistsinthread' => _('That file already exists in this thread!'),
	'alreadyuploaded' => _('You already uploaded this!'),
	'invalidfilename' => _('Invalid filename.'),
	'invalid' => _('Invalid request.'),
	'noaccess' => _('You don\'t have permission to do that.'),
	'404' => _('Page not found.'),
	'rebuild' => _('Rebuilding…'),
	'invalidpost' => _('That post doesn\'t exist…'),
	'badsyntax' => _('Invalid PHP syntax: '),
	'toomanylinks' => _('Too many links.'),
];

// --- Mod ---
$config['mod'] = [
	'default' => '/',
	'recent_reports' => 10,
	'modlog_page' => 50,
	'rebuild_timelimit' => 0,
	'snippet_length' => 75,
	'raw_html_default' => false,
	'dismiss_reports_on_lock' => true,
	'dashboard_links' => [],
	'groups' => [
		10 => 'Janitor',
		20 => 'Mod',
		30 => 'Admin',
		99 => 'Disabled',
	],
	'capcode' => [], // filled after define_groups
	'skip_per_board' => false,
	'delete' => 10,
	'spoilerimage' => 10,
	'deletefile' => 10,
	'sticky' => 20,
	'lock' => 20,
	'archive' => 20, // manually archive any live thread
	'approve_posts' => 20, // approve/reject posts held for moderation
	'postinlocked' => 20,
	'bumplock' => 20,
	'view_bumplock' => 20,
	'editpost' => 20, // mods can edit text + replace image
	'bypass_field_disable' => 20,
	'rawhtml' => 30,
	'reports' => 10,
	'report_dismiss' => 10,
	'report_dismiss_post' => 10,
	'manageusers' => 30,
	'change_password' => 10,
	'createusers' => 30,
	'editusers' => 30,
	'deleteusers' => 30,
	'promoteusers' => 30,
	'modlog' => 20,
	'manageboards' => 30,
	'newboard' => 30,
	'recent' => 20,
	'rebuild' => 30,
	'post_force_name' => false,
	'link_delete' => '[D]',
	'link_deletefile' => '[F]',
	'link_spoilerimage' => '[S]',
	'link_sticky' => '[Sticky]',
	'link_desticky' => '[-Sticky]',
	'link_lock' => '[Lock]',
	'link_unlock' => '[-Lock]',
	'link_archive' => '[Archive]',
	'link_bumplock' => '[Sage]',
	'link_bumpunlock' => '[-Sage]',
	'link_editpost' => '[Edit]',
];

// Groups constants (JANITOR, MOD, ADMIN)
if (function_exists('define_groups')) {
	define_groups();
	$config['mod']['capcode'] = [
		MOD => ['Mod'],
		ADMIN => true,
	];
	// Re-bind permission levels to constants if defined
	if (defined('JANITOR')) {
		$config['mod']['delete'] = JANITOR;
		$config['mod']['spoilerimage'] = JANITOR;
		$config['mod']['deletefile'] = JANITOR;
		$config['mod']['reports'] = JANITOR;
		$config['mod']['report_dismiss'] = JANITOR;
		$config['mod']['report_dismiss_post'] = JANITOR;
		$config['mod']['change_password'] = JANITOR;
	}
	if (defined('MOD')) {
		$config['mod']['sticky'] = MOD;
		$config['mod']['lock'] = MOD;
		$config['mod']['archive'] = MOD;
		$config['mod']['approve_posts'] = MOD;
		$config['mod']['postinlocked'] = MOD;
		$config['mod']['bumplock'] = MOD;
		$config['mod']['view_bumplock'] = MOD;
		$config['mod']['bypass_field_disable'] = MOD;
		$config['mod']['modlog'] = MOD;
		$config['mod']['recent'] = MOD;
		$config['mod']['editpost'] = MOD; // edit text / replace image
	}
	if (defined('ADMIN')) {
		$config['mod']['rawhtml'] = ADMIN;
		$config['mod']['manageusers'] = ADMIN;
		$config['mod']['createusers'] = ADMIN;
		$config['mod']['editusers'] = ADMIN;
		$config['mod']['deleteusers'] = ADMIN;
		$config['mod']['promoteusers'] = ADMIN;
		$config['mod']['manageboards'] = ADMIN;
		$config['mod']['newboard'] = ADMIN;
		$config['mod']['rebuild'] = ADMIN;
	}
}

// Events / empty hooks
$config['events'] = [];

// Load instance + secrets last via instance-config if present is handled in loadConfig
