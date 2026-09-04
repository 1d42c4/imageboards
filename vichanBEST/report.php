<?php
/**
 * Standalone report form (optional). Normal flow uses the report box on board pages → post.php.
 */
require __DIR__ . '/inc/bootstrap.php';

$global = isset($_GET['global']);
$post = isset($_GET['post']) ? (string)$_GET['post'] : '';
$boardUri = isset($_GET['board']) ? (string)$_GET['board'] : '';

if ($post === '' || !preg_match('/^delete_\d+$/', $post) || $boardUri === '' || !openBoard($boardUri)) {
	header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
	error(_('Bad request.'));
}

// Captcha HTML for the dedicated form: only native provider is wired without extra helpers.
$captcha = null;
if (!empty($config['report_captcha']) && ($config['captcha']['provider'] ?? false) === 'native') {
	// Form still posts to post.php; captcha fields optional via JS load_captcha on board pages.
	$captcha = null;
}

$body = Element($config['file_report'], [
	'global' => $global,
	'post' => $post,
	'board' => $board,
	'captcha' => $captcha,
	'config' => $config,
	'reason_prefill' => '',
]);

echo Element($config['file_page_template'], [
	'config' => $config,
	'body' => $body,
	'title' => _('Report'),
]);
