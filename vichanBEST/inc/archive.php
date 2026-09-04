<?php
/**
 * Board archive — threads that fall off the active pages are kept read-only.
 */

defined('TINYBOARD') or exit;

function archive_enabled(): bool {
	global $config;
	return !empty($config['archive']['enabled']);
}

function archive_dir(): string {
	global $board, $config;
	return $board['dir'] . ($config['archive']['dir'] ?? 'archive/');
}

function archive_thread_path(int $id): string {
	global $config;
	$page = $config['archive']['file_page'] ?? '%d.html';
	return archive_dir() . sprintf($page, $id);
}

function archive_index_path(): string {
	global $config;
	return archive_dir() . ($config['archive']['file_index'] ?? 'index.html');
}

function archive_public_url(int $id = 0): string {
	global $board, $config;
	$base = $config['root'] . archive_dir();
	if ($id < 1) {
		return $base . ($config['archive']['file_index'] ?? 'index.html');
	}
	return $base . sprintf($config['archive']['file_page'] ?? '%d.html', $id);
}

/** Create archive table + directory for the open board. */
function archive_setup_board(): void {
	global $board, $config;

	if (!archive_enabled() || empty($board['uri'])) {
		return;
	}

	$dir = archive_dir();
	if (!is_dir($dir)) {
		@mkdir($dir, 0777, true)
			or error("Couldn't create archive directory {$dir}. Check permissions.", true);
	}

	// information_schema uses unquoted names; bypass rewrite helpers
	$check = $GLOBALS['pdo']->prepare(
		'SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ? LIMIT 1'
	);
	$check->execute(['archive_' . $board['uri']]);
	if ($check->fetchColumn()) {
		return;
	}

	$sql = Element(db_schema_template('archive'), ['board' => $board['uri']]);
	foreach (db_split_sql_statements($sql) as $stmt) {
		query($stmt) or error(db_error());
	}
}

/**
 * Move a live OP + replies into the archive (keeps images on disk).
 * Removes the thread from the active board and writes static archive HTML.
 */
function archiveThread(int $id, bool $log = true): bool {
	global $board, $config, $pdo;

	if (!archive_enabled()) {
		return false;
	}

	$id = (int)$id;
	archive_setup_board();

	// Already archived?
	$exists = prepare(sprintf('SELECT 1 FROM ``archive_%s`` WHERE `id` = :id AND `thread` IS NULL LIMIT 1', $board['uri']));
	$exists->bindValue(':id', $id, PDO::PARAM_INT);
	$exists->execute() or error(db_error($exists));
	if ($exists->fetchColumn()) {
		// Ensure HTML exists; still drop live copy if present
		buildArchiveThread($id);
		_archive_purge_live($id);
		return true;
	}

	$query = prepare(sprintf(
		'SELECT * FROM ``posts_%s`` WHERE (`thread` IS NULL AND `id` = :id) OR `thread` = :id ORDER BY `id`',
		$board['uri']
	));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$posts = $query->fetchAll(PDO::FETCH_ASSOC);

	if (!$posts) {
		return false;
	}

	// OP must be first / present
	$has_op = false;
	foreach ($posts as $p) {
		if (empty($p['thread'])) {
			$has_op = true;
			break;
		}
	}
	if (!$has_op) {
		return false;
	}

	$archived_at = time();
	$ids = [];

	$insert = prepare(sprintf(
		'INSERT INTO ``archive_%s`` (
			`id`, `thread`, `subject`, `email`, `name`, `trip`, `capcode`, `body`, `body_nomarkup`,
			`time`, `bump`, `files`, `num_files`, `filehash`, `password`, `ip`,
			`sticky`, `locked`, `cycle`, `sage`, `embed`, `slug`, `archived_at`
		) VALUES (
			:id, :thread, :subject, :email, :name, :trip, :capcode, :body, :body_nomarkup,
			:time, :bump, :files, :num_files, :filehash, :password, :ip,
			:sticky, :locked, :cycle, :sage, :embed, :slug, :archived_at
		)',
		$board['uri']
	));

	foreach ($posts as $post) {
		$ids[] = (int)$post['id'];
		$insert->bindValue(':id', (int)$post['id'], PDO::PARAM_INT);
		if (!empty($post['thread'])) {
			$insert->bindValue(':thread', (int)$post['thread'], PDO::PARAM_INT);
		} else {
			$insert->bindValue(':thread', null, PDO::PARAM_NULL);
		}
		$insert->bindValue(':subject', $post['subject']);
		$insert->bindValue(':email', $post['email']);
		$insert->bindValue(':name', $post['name']);
		$insert->bindValue(':trip', $post['trip']);
		$insert->bindValue(':capcode', $post['capcode']);
		$insert->bindValue(':body', $post['body']);
		$insert->bindValue(':body_nomarkup', $post['body_nomarkup']);
		$insert->bindValue(':time', (int)$post['time'], PDO::PARAM_INT);
		if (isset($post['bump']) && $post['bump'] !== null && $post['bump'] !== '') {
			$insert->bindValue(':bump', (int)$post['bump'], PDO::PARAM_INT);
		} else {
			$insert->bindValue(':bump', null, PDO::PARAM_NULL);
		}
		$insert->bindValue(':files', $post['files']);
		$insert->bindValue(':num_files', (int)($post['num_files'] ?? 0), PDO::PARAM_INT);
		$insert->bindValue(':filehash', $post['filehash']);
		$insert->bindValue(':password', $post['password']);
		$insert->bindValue(':ip', $post['ip'] ?? '');
		$insert->bindValue(':sticky', (int)($post['sticky'] ?? 0), PDO::PARAM_INT);
		$insert->bindValue(':locked', (int)($post['locked'] ?? 0), PDO::PARAM_INT);
		$insert->bindValue(':cycle', (int)($post['cycle'] ?? 0), PDO::PARAM_INT);
		$insert->bindValue(':sage', (int)($post['sage'] ?? 0), PDO::PARAM_INT);
		$insert->bindValue(':embed', $post['embed']);
		$insert->bindValue(':slug', $post['slug']);
		$insert->bindValue(':archived_at', $archived_at, PDO::PARAM_INT);
		$insert->execute() or error(db_error($insert));
	}

	_archive_purge_live($id, $ids);

	buildArchiveThread($id);
	buildArchiveIndex();

	if ($log && function_exists('modLog')) {
		modLog("Archived thread #{$id} (" . (count($posts) - 1) . ' replies)');
	}

	return true;
}

/** Remove live posts/HTML/cites for a thread; keep image files on disk. */
function _archive_purge_live(int $id, ?array $ids = null): void {
	global $board, $config;

	if ($ids === null) {
		$q = prepare(sprintf(
			'SELECT `id` FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id',
			$board['uri']
		));
		$q->bindValue(':id', $id, PDO::PARAM_INT);
		$q->execute() or error(db_error($q));
		$ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
	}

	if ($ids) {
		// Drop cites involving these posts
		$in = implode(',', $ids);
		$query = prepare("DELETE FROM ``cites`` WHERE (`target_board` = :board AND `target` IN ($in)) OR (`board` = :board AND `post` IN ($in))");
		$query->bindValue(':board', $board['uri']);
		$query->execute() or error(db_error($query));
	}

	// Live HTML
	$op = ['id' => $id, 'thread' => null];
	$res = $board['dir'] . $config['dir']['res'] . link_for($op);
	if (file_exists($res)) {
		file_unlink($res);
	}

	// Delete live rows (files intentionally kept)
	$del = prepare(sprintf('DELETE FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id', $board['uri']));
	$del->bindValue(':id', $id, PDO::PARAM_INT);
	$del->execute() or error(db_error($del));
}

/** Build one archived thread page (read-only). */
function buildArchiveThread(int $id, bool $return = false) {
	global $board, $config;

	if (!archive_enabled()) {
		return $return ? '' : null;
	}

	archive_setup_board();
	$id = (int)$id;

	$query = prepare(sprintf(
		'SELECT * FROM ``archive_%s`` WHERE (`thread` IS NULL AND `id` = :id) OR `thread` = :id ORDER BY `id`',
		$board['uri']
	));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		if (!isset($thread)) {
			$thread = new Thread($post, $config['root'], false);
		} else {
			$thread->add(new Post($post, $config['root'], false));
		}
	}

	if (!isset($thread)) {
		if ($return) {
			return false;
		}
		error($config['error']['nonexistant']);
	}

	// Full bodies (not index-truncated) for educational reading
	$body = $thread->build(false);

	$archive_href = archive_public_url($id);
	// Point No./>> links that targeted live res/ page at the archive copy instead
	$live = $config['root'] . $board['dir'] . $config['dir']['res'] . link_for(['id' => $id]);
	$body = str_replace($live, $archive_href, $body);

	$options = [
		'board' => $board,
		'thread' => $thread,
		'body' => $body,
		'config' => $config,
		'id' => $id,
		'mod' => false,
		'archive' => true,
		'archive_url' => $archive_href,
		'archive_index' => archive_public_url(0),
		'boardlist' => createBoardlist(false),
		'return' => $config['root'] . $board['dir'] . $config['file_index'],
		'archived_at' => $thread->archived_at ?? null,
	];

	$html = Element($config['archive']['file_thread'] ?? 'archive_thread.html', $options);
	$html = str_replace($live, $archive_href, $html);

	if ($return) {
		return $html;
	}

	file_write(archive_thread_path($id), $html);
}

/** List pages of archived threads (newest archived first). */
function buildArchiveIndex(): void {
	global $board, $config;

	if (!archive_enabled()) {
		return;
	}

	archive_setup_board();

	$per_page = max(1, (int)($config['archive']['threads_per_page'] ?? 50));

	$count_q = query(sprintf(
		'SELECT COUNT(*) FROM ``archive_%s`` WHERE `thread` IS NULL',
		$board['uri']
	)) or error(db_error());
	$total = (int)$count_q->fetchColumn();
	$pages = max(1, (int)ceil($total / $per_page));

	for ($page = 1; $page <= $pages; $page++) {
		$offset = ($page - 1) * $per_page;
		$query = prepare(sprintf(
			'SELECT a.*, (
				SELECT COUNT(*) FROM ``archive_%s`` r WHERE r.`thread` = a.`id`
			) AS reply_count
			FROM ``archive_%s`` a
			WHERE a.`thread` IS NULL
			ORDER BY a.`archived_at` DESC, a.`id` DESC
			LIMIT :limit OFFSET :offset',
			$board['uri'],
			$board['uri']
		));
		$query->bindValue(':limit', $per_page, PDO::PARAM_INT);
		$query->bindValue(':offset', $offset, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$threads = [];
		while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
			$snippet = $row['body_nomarkup'] ?? strip_tags($row['body'] ?? '');
			$snippet = preg_replace('/\s+/', ' ', $snippet);
			if (mb_strlen($snippet) > 200) {
				$snippet = mb_substr($snippet, 0, 200) . '…';
			}
			$thumb = null;
			if (!empty($row['files'])) {
				$files = json_decode($row['files'], true);
				if (is_array($files) && isset($files[0]['thumb']) && !in_array($files[0]['thumb'], ['spoiler', 'deleted', 'file'], true)) {
					$thumb = $files[0]['thumb'];
				}
			}
			$threads[] = [
				'id' => (int)$row['id'],
				'subject' => $row['subject'],
				'name' => $row['name'],
				'time' => (int)$row['time'],
				'archived_at' => (int)$row['archived_at'],
				'reply_count' => (int)$row['reply_count'],
				'snippet' => $snippet,
				'thumb' => $thumb,
				'url' => archive_public_url((int)$row['id']),
			];
		}

		$page_links = [];
		for ($p = 1; $p <= $pages; $p++) {
			$file = $p === 1
				? ($config['archive']['file_index'] ?? 'index.html')
				: sprintf($config['archive']['file_page_index'] ?? '%d.html', $p);
			// Keep thread pages as %d.html and index pages as index / 2.html under archive/
			// Use separate naming: index.html, page2.html to avoid clashing with thread ids
			if ($p === 1) {
				$href = archive_public_url(0);
				$file_out = $config['archive']['file_index'] ?? 'index.html';
			} else {
				$file_out = sprintf($config['archive']['file_list_page'] ?? 'list-%d.html', $p);
				$href = $config['root'] . archive_dir() . $file_out;
			}
			$page_links[] = [
				'num' => $p,
				'selected' => $p === $page,
				'link' => $href,
				'file' => $file_out,
			];
		}

		$html = Element($config['archive']['file_index_template'] ?? 'archive_index.html', [
			'config' => $config,
			'board' => $board,
			'threads' => $threads,
			'pages' => $page_links,
			'page' => $page,
			'total' => $total,
			'boardlist' => createBoardlist(false),
			'return' => $config['root'] . $board['dir'] . $config['file_index'],
		]);

		$out = $page === 1
			? archive_index_path()
			: archive_dir() . sprintf($config['archive']['file_list_page'] ?? 'list-%d.html', $page);
		file_write($out, $html);
	}

	// Remove stale list pages beyond current page count
	for ($p = $pages + 1; $p <= $pages + 20; $p++) {
		$stale = archive_dir() . sprintf($config['archive']['file_list_page'] ?? 'list-%d.html', $p);
		if (file_exists($stale)) {
			file_unlink($stale);
		}
	}

	// Empty archive still gets an index
	if ($total === 0) {
		$html = Element($config['archive']['file_index_template'] ?? 'archive_index.html', [
			'config' => $config,
			'board' => $board,
			'threads' => [],
			'pages' => [['num' => 1, 'selected' => true, 'link' => archive_public_url(0)]],
			'page' => 1,
			'total' => 0,
			'boardlist' => createBoardlist(false),
			'return' => $config['root'] . $board['dir'] . $config['file_index'],
		]);
		file_write(archive_index_path(), $html);
	}
}

/** Rebuild all archive HTML for the open board. */
function rebuildArchive(): void {
	global $board;

	if (!archive_enabled()) {
		return;
	}

	archive_setup_board();

	$query = query(sprintf(
		'SELECT `id` FROM ``archive_%s`` WHERE `thread` IS NULL ORDER BY `id`',
		$board['uri']
	)) or error(db_error());

	while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
		buildArchiveThread((int)$row['id']);
	}

	buildArchiveIndex();
}
