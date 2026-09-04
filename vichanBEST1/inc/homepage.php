<?php
/**
 * Optional generated board list. The site root index.html is owned by the admin
 * (see examples/landing.html) and is never overwritten after first install.
 */

defined('TINYBOARD') or exit;

/**
 * Write boards.html — a simple list of boards for use on custom landing pages.
 */
function rebuild_boards_list(): void {
	global $config;

	$boards = listBoards();
	$root = htmlspecialchars($config['root'] ?? '/', ENT_QUOTES, 'UTF-8');
	$title = htmlspecialchars($config['homepage']['title'] ?? 'Boards', ENT_QUOTES, 'UTF-8');

	$items = '';
	foreach ($boards as $b) {
		$uri = htmlspecialchars($b['uri'], ENT_QUOTES, 'UTF-8');
		$name = htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8');
		$sub = isset($b['subtitle']) && $b['subtitle'] !== '' && $b['subtitle'] !== null
			? '<span class="board-sub">' . htmlspecialchars($b['subtitle'], ENT_QUOTES, 'UTF-8') . '</span>'
			: '';
		$items .= "\t\t<li><a href=\"{$root}{$uri}/\">/{$uri}/ — {$name}</a> {$sub}"
			. " <a class=\"catalog-link\" href=\"{$root}{$uri}/catalog.html\">catalog</a></li>\n";
	}

	if ($items === '') {
		$items = "\t\t<li><em>No boards yet.</em></li>\n";
	}

	$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{$title}</title>
	<link rel="stylesheet" href="{$root}stylesheets/style.css">
	<style>
		body { max-width: 40rem; margin: 2rem auto; padding: 0 1rem; font-family: sans-serif; }
		ul.board-list { list-style: none; padding: 0; }
		ul.board-list li { margin: 0.6rem 0; padding: 0.5rem 0; border-bottom: 1px solid #ccc; }
		.board-sub { color: #666; font-size: 0.9em; }
		.catalog-link { font-size: 0.85em; margin-left: 0.5rem; }
	</style>
</head>
<body>
	<h1>{$title}</h1>
	<ul class="board-list">
{$items}	</ul>
	<p class="unimportant"><a href="{$root}">Home</a></p>
</body>
</html>
HTML;

	$file = $config['dir']['home'] . ($config['homepage']['boards_file'] ?? 'boards.html');
	file_write($file, $html);
}

/**
 * Create a starter index.html only if missing (never clobber a custom landing page).
 */
function ensure_landing_page(): void {
	global $config;

	$index = $config['dir']['home'] . 'index.html';
	if (file_exists($index)) {
		return;
	}

	$example = $config['dir']['home'] . 'examples/landing.html';
	if (is_readable($example)) {
		copy($example, $index);
		return;
	}

	// Minimal fallback
	$root = htmlspecialchars($config['root'] ?? '/', ENT_QUOTES, 'UTF-8');
	file_write($index, <<<HTML
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Imageboard</title>
	<link rel="stylesheet" href="{$root}stylesheets/style.css">
</head>
<body style="max-width:40rem;margin:2rem auto;padding:0 1rem;font-family:sans-serif">
	<h1>Welcome</h1>
	<p>Edit this file (<code>index.html</code>) — it is yours. Board list:</p>
	<p><a href="{$root}boards.html">All boards</a> · <a href="{$root}b/">/b/</a> · <a href="{$root}b/catalog.html">/b/ catalog</a></p>
	<p><a href="{$root}mod.php">Mod panel</a></p>
</body>
</html>
HTML);
}
