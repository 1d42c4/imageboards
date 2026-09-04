<?php
/**
 * Public ban check page — shows ban details if the visitor is banned, else "not banned".
 */
require_once __DIR__ . '/inc/bootstrap.php';

checkBan();

// Not banned
echo Element('page.html', [
	'title' => _('Not banned!'),
	'config' => $config,
	'nojavascript' => true,
	'body' => Element('notbanned.html', []),
]);
