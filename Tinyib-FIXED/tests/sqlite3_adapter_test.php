<?php
declare(strict_types=1);

function assertSqlite(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function fancyDie($message): never {
	throw new RuntimeException((string)$message);
}

function hashData($data, $force = false): string {
	$data = (string)$data;
	return str_starts_with($data, 'hash:') && !$force ? $data : 'hash:' . hash('sha256', $data);
}

function deletePost($id): void {
	deletePostByID($id);
}

$databasePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tinyib-sqlite3-' . bin2hex(random_bytes(6)) . '.db';
define('TINYIB_BOARD', 'test');
define('TINYIB_NEWTHREAD', '0');
define('TINYIB_MAXTHREADS', 0);
define('TINYIB_DBPATH', $databasePath);
define('TINYIB_DBACCOUNTS', 'accounts');
define('TINYIB_DBKEYWORDS', 'keywords');
define('TINYIB_DBLOGS', 'logs');
define('TINYIB_DBPOSTS', 'posts');
define('TINYIB_DBREPORTS', 'reports');

try {
	$legacyDb = new SQLite3($databasePath);
	$legacyDb->exec("CREATE TABLE bans (id INTEGER PRIMARY KEY, ip TEXT NOT NULL, timestamp TIMESTAMP NOT NULL, expire TIMESTAMP NOT NULL, reason TEXT NOT NULL)");
	$legacyDb->exec("INSERT INTO bans (ip, timestamp, expire, reason) VALUES ('legacy-address-hash', 1, 0, 'legacy')");
	$legacyDb->exec("CREATE TABLE reports (id INTEGER PRIMARY KEY, ip TEXT NOT NULL, post INTEGER NOT NULL)");
	$legacyDb->exec("INSERT INTO reports (ip, post) VALUES ('legacy-address-hash', 999)");
	$legacyDb->exec("CREATE TABLE keywords (id INTEGER PRIMARY KEY, text TEXT NOT NULL, action TEXT NOT NULL)");
	$legacyDb->exec("INSERT INTO keywords (text, action) VALUES ('legacy', 'ban1h')");
	$legacyDb->exec("CREATE TABLE posts (
		id INTEGER PRIMARY KEY,
		parent INTEGER NOT NULL,
		timestamp TIMESTAMP NOT NULL,
		bumped TIMESTAMP NOT NULL,
		ip TEXT NOT NULL,
		name TEXT NOT NULL,
		tripcode TEXT NOT NULL,
		email TEXT NOT NULL,
		nameblock TEXT NOT NULL,
		subject TEXT NOT NULL,
		message TEXT NOT NULL,
		password TEXT NOT NULL,
		file TEXT NOT NULL,
		file_hex TEXT NOT NULL,
		file_original TEXT NOT NULL,
		file_size INTEGER NOT NULL DEFAULT '0',
		file_size_formatted TEXT NOT NULL,
		image_width INTEGER NOT NULL DEFAULT '0',
		image_height INTEGER NOT NULL DEFAULT '0',
		thumb TEXT NOT NULL,
		thumb_width INTEGER NOT NULL DEFAULT '0',
		thumb_height INTEGER NOT NULL DEFAULT '0',
		moderated INTEGER NOT NULL DEFAULT '1',
		stickied INTEGER NOT NULL DEFAULT '0',
		locked INTEGER NOT NULL DEFAULT '0'
	)");
	$legacyDb->exec("INSERT INTO posts (id, parent, timestamp, bumped, ip, name, tripcode, email, nameblock, subject, message, password, file, file_hex, file_original, file_size, file_size_formatted, image_width, image_height, thumb, thumb_width, thumb_height, moderated, stickied, locked) VALUES (10, 0, 1, 1, 'legacy-address-hash', 'Anonymous', '', '', 'Anonymous', '', 'Legacy post', '', '', '', '', 0, '', 0, 0, '', 0, 0, 1, 0, 0)");
	$legacyDb->close();

	require __DIR__ . '/../inc/database/sqlite3_link.php';
	require __DIR__ . '/../inc/database/sqlite3.php';

	assertSqlite((int)$db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'bans'") === 0, 'Legacy ban table was not removed.');
	$reportColumns = array();
	$result = $db->query("PRAGMA table_info(reports)");
	while ($column = $result->fetchArray(SQLITE3_ASSOC)) {
		$reportColumns[] = $column['name'];
	}
	assertSqlite(!in_array('ip', $reportColumns, true), 'Legacy report address column was not removed.');
	$postColumns = array();
	$result = $db->query("PRAGMA table_info(posts)");
	while ($column = $result->fetchArray(SQLITE3_ASSOC)) {
		$postColumns[] = $column['name'];
	}
	assertSqlite(!in_array('ip', $postColumns, true), 'Legacy post address column was not removed.');
	assertSqlite(postByID(10)['message'] === 'Legacy post', 'Legacy post was not preserved during the privacy migration.');
	assertSqlite(keywordByText('legacy')['action'] === 'delete', 'Legacy ban keyword action was not converted to delete.');

	$accountId = insertAccount(array('username' => 'admin', 'password' => 'secret', 'role' => 1));
	assertSqlite($accountId === 1 && accountByUsername('admin')['id'] === 1, 'SQLite3 account operations failed.');

	$post = array(
		'parent' => 0, 'name' => '', 'tripcode' => '', 'email' => '', 'nameblock' => 'Anonymous',
		'subject' => 'Subject', 'message' => 'Message', 'password' => 'hash', 'file' => '', 'file_hex' => '',
		'file_original' => '', 'file_size' => 0, 'file_size_formatted' => '', 'image_width' => 0,
		'image_height' => 0, 'thumb' => '', 'thumb_width' => 0, 'thumb_height' => 0, 'moderated' => 1
	);
	$threadId = insertPost($post);
	$post['parent'] = $threadId;
	$post['message'] = 'Reply';
	$replyId = insertPost($post);
	assertSqlite(threadExistsByID($threadId), 'SQLite3 thread lookup failed.');
	assertSqlite(numRepliesToThreadByID($threadId) === 1, 'SQLite3 reply count failed.');
	assertSqlite(postByID($replyId)['message'] === 'Reply', 'SQLite3 post lookup failed.');
	assertSqlite(!array_key_exists('ip', postByID($replyId)), 'SQLite3 post records must not contain network identifiers.');

	insertReport(array('post' => $replyId));
	assertSqlite(count(reportsByPost($replyId)) === 1, 'SQLite3 report operations failed.');
	assertSqlite(!array_key_exists('ip', reportsByPost($replyId)[0]), 'SQLite3 report records must not contain network identifiers.');
	deletePostByID($replyId);
	assertSqlite(postByID($replyId) === null, 'SQLite3 post deletion failed.');

	echo "SQLite3 adapter tests passed.\n";
} finally {
	if (isset($db) && $db instanceof SQLite3) {
		$db->close();
	}
	if (is_file($databasePath)) {
		unlink($databasePath);
	}
}
