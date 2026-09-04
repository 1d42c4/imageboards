<?php
/**
 * Per-board posting password + optional pre-publish approval.
 */

defined('TINYBOARD') or exit;

/** Ensure boards / posts tables have moderation columns (safe on every open). */
function board_moderation_ensure_schema(?string $board_uri = null): void {
	global $pdo;

	static $boards_ready = false;
	if (!$boards_ready) {
		try {
			$pdo->exec('ALTER TABLE boards ADD COLUMN IF NOT EXISTS post_password varchar(255) DEFAULT NULL');
			$pdo->exec('ALTER TABLE boards ADD COLUMN IF NOT EXISTS require_approval smallint NOT NULL DEFAULT 0');
			$boards_ready = true;
		} catch (Throwable $e) {
			// Table may not exist during early install
		}
	}

	if ($board_uri === null || $board_uri === '') {
		return;
	}

	static $posts_ready = [];
	if (isset($posts_ready[$board_uri])) {
		return;
	}

	try {
		// Identifier from board URI only (already validated elsewhere)
		$uri = preg_replace('/[^0-9a-zA-Z$_\x{0080}-\x{FFFF}]/u', '', $board_uri);
		if ($uri === '') {
			return;
		}
		$table = 'posts_' . $uri;
		$pdo->exec('ALTER TABLE "' . str_replace('"', '""', $table) . '" ADD COLUMN IF NOT EXISTS pending smallint NOT NULL DEFAULT 0');
		$pdo->exec('CREATE INDEX IF NOT EXISTS "' . str_replace('"', '""', $table) . '_pending_idx" ON "' . str_replace('"', '""', $table) . '" (pending) WHERE pending = 1');
		$posts_ready[$board_uri] = true;
	} catch (Throwable $e) {
		// ignore if posts table missing mid-create
	}
}

function board_has_post_password(?array $boardRow = null): bool {
	global $board;
	$b = $boardRow ?? $board ?? [];
	return !empty($b['post_password']);
}

function board_requires_approval(?array $boardRow = null): bool {
	global $board;
	$b = $boardRow ?? $board ?? [];
	return !empty($b['require_approval']);
}

/** SQL fragment: only public (approved) posts. */
function sql_posts_public(string $alias = ''): string {
	$p = $alias !== '' ? $alias . '.' : '';
	return "COALESCE({$p}pending, 0) = 0";
}

/**
 * Hash a new board posting password (empty = no password).
 */
function board_password_hash(string $plain): ?string {
	$plain = trim($plain);
	if ($plain === '') {
		return null;
	}
	return password_hash($plain, PASSWORD_DEFAULT);
}

function board_password_verify(string $plain, ?string $hash): bool {
	if ($hash === null || $hash === '') {
		return true;
	}
	return password_verify($plain, $hash);
}

/** Count pending posts across boards the mod can see. */
function count_pending_posts_for_mod(array $mod): int {
	$boards = listBoards() ?: [];
	$total = 0;
	foreach ($boards as $b) {
		if (!hasPermission($GLOBALS['config']['mod']['approve_posts'] ?? 20, $b['uri'])) {
			continue;
		}
		board_moderation_ensure_schema($b['uri']);
		try {
			$q = query(sprintf(
				'SELECT COUNT(*) FROM ``posts_%s`` WHERE COALESCE(pending, 0) = 1',
				$b['uri']
			));
			if ($q) {
				$total += (int)$q->fetchColumn();
			}
		} catch (Throwable $e) {
			// board table may not have column yet
		}
	}
	return $total;
}
