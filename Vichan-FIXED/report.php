<?php
/**
 * Standalone report form (optional). Normal flow uses the report box on board pages → post.php.
 */
require __DIR__ . '/inc/bootstrap.php';

use function Vichan\Service\native_captcha_create;

$global = isset($_GET['global']);
$post = isset($_GET['post']) ? (string)$_GET['post'] : '';
$boardUri = isset($_GET['board']) ? (string)$_GET['board'] : '';

if ($post === '' || !preg_match('/^delete_\d+$/', $post) || $boardUri === '' || !openBoard($boardUri)) {
	header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
	error(_('Bad request.'));
}

// Captcha for dedicated form when report_captcha is enabled (native only).
$captcha = null;
if (!empty($config['report_captcha'])) {
	$extra = $config['captcha']['native']['extra'] ?? '';
	$challenge = native_captcha_create((string)$extra);
	$captcha = [
		'cookie' => $challenge['cookie'],
		'html' => $challenge['html'],
	];
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
