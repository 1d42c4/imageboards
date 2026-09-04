<?php
/**
 * Catalog + boards list rebuild (legacy name: rebuild_themes).
 * Installable themes were removed; this is what remaining call sites still invoke.
 */
namespace Vichan\Functions\Theme;

function rebuild_themes(string $action, $boardname = false): void {
	if (!\function_exists('rebuild_catalog')) {
		require_once \dirname(__DIR__) . '/catalog.php';
	}
	if (!\function_exists('rebuild_boards_list')) {
		require_once \dirname(__DIR__) . '/homepage.php';
	}

	// No public banlist pages anymore
	if ($action === 'bans') {
		return;
	}

	if ($action === 'all' || $action === 'boards' || $action === 'news') {
		\rebuild_boards_list();
		\ensure_landing_page();
	}

	// post / post-thread / post-delete / all / boards → catalog
	if (\in_array($action, ['all', 'post', 'post-thread', 'post-delete', 'boards'], true)) {
		\rebuild_catalog($boardname ?: false);
	}
}
