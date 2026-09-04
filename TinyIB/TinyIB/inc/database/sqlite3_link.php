<?php
if (!defined('TINYIB_BOARD')) {
	die('');
}

if (!extension_loaded('sqlite3')) {
	fancyDie('The SQLite3 extension is not installed or enabled.');
}

function sqliteIdentifier($identifier) {
	if (!is_string($identifier) || !preg_match('/\A[A-Za-z0-9_]+\z/D', $identifier)) {
		fancyDie('An invalid SQLite table name is configured.');
	}
	return '"' . $identifier . '"';
}

function sqliteColumnExists($db, $table, $column) {
	$result = $db->query('PRAGMA table_info(' . sqliteIdentifier($table) . ')');
	while ($definition = $result->fetchArray(SQLITE3_ASSOC)) {
		if ($definition['name'] === $column) {
			return true;
		}
	}
	return false;
}

$database_directory = dirname(TINYIB_DBPATH);
if (!is_dir($database_directory) || !is_writable($database_directory)) {
	fancyDie('The SQLite database directory is missing or is not writable.');
}

try {
	$db = new SQLite3(TINYIB_DBPATH, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
	$db->enableExceptions(true);
	$db->busyTimeout(60000);
	$db->exec('PRAGMA foreign_keys = ON');

	$accounts_table = sqliteIdentifier(TINYIB_DBACCOUNTS);
	$bans_table = sqliteIdentifier(TINYIB_DBBANS);
	$keywords_table = sqliteIdentifier(TINYIB_DBKEYWORDS);
	$logs_table = sqliteIdentifier(TINYIB_DBLOGS);
	$posts_table = sqliteIdentifier(TINYIB_DBPOSTS);
	$reports_table = sqliteIdentifier(TINYIB_DBREPORTS);

	$db->exec("CREATE TABLE IF NOT EXISTS $accounts_table (
		id INTEGER PRIMARY KEY,
		username TEXT NOT NULL,
		password TEXT NOT NULL,
		role INTEGER NOT NULL,
		lastactive INTEGER NOT NULL
	)");

	$db->exec("CREATE TABLE IF NOT EXISTS $bans_table (
		id INTEGER PRIMARY KEY,
		ip TEXT NOT NULL,
		timestamp INTEGER NOT NULL,
		expire INTEGER NOT NULL,
		reason TEXT NOT NULL
	)");

	$db->exec("CREATE TABLE IF NOT EXISTS $keywords_table (
		id INTEGER PRIMARY KEY,
		text TEXT NOT NULL,
		action TEXT NOT NULL
	)");

	$db->exec("CREATE TABLE IF NOT EXISTS $logs_table (
		id INTEGER PRIMARY KEY,
		timestamp INTEGER NOT NULL,
		account INTEGER NOT NULL,
		message TEXT NOT NULL
	)");

	$db->exec("CREATE TABLE IF NOT EXISTS $posts_table (
		id INTEGER PRIMARY KEY,
		parent INTEGER NOT NULL,
		timestamp INTEGER NOT NULL,
		bumped INTEGER NOT NULL,
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
		file_size INTEGER NOT NULL DEFAULT 0,
		file_size_formatted TEXT NOT NULL,
		image_width INTEGER NOT NULL DEFAULT 0,
		image_height INTEGER NOT NULL DEFAULT 0,
		thumb TEXT NOT NULL,
		thumb_width INTEGER NOT NULL DEFAULT 0,
		thumb_height INTEGER NOT NULL DEFAULT 0,
		moderated INTEGER NOT NULL DEFAULT 1,
		stickied INTEGER NOT NULL DEFAULT 0,
		locked INTEGER NOT NULL DEFAULT 0
	)");

	$db->exec("CREATE TABLE IF NOT EXISTS $reports_table (
		id INTEGER PRIMARY KEY,
		ip TEXT NOT NULL,
		post INTEGER NOT NULL
	)");

	foreach (array(
		'moderated' => 'INTEGER NOT NULL DEFAULT 1',
		'stickied' => 'INTEGER NOT NULL DEFAULT 0',
		'locked' => 'INTEGER NOT NULL DEFAULT 0',
	) as $column => $definition) {
		if (!sqliteColumnExists($db, TINYIB_DBPOSTS, $column)) {
			$db->exec("ALTER TABLE $posts_table ADD COLUMN " . sqliteIdentifier($column) . " $definition");
		}
	}

	$db->exec('CREATE INDEX IF NOT EXISTS ' . sqliteIdentifier(TINYIB_DBPOSTS . '_parent_idx') . " ON $posts_table (parent)");
	$db->exec('CREATE INDEX IF NOT EXISTS ' . sqliteIdentifier(TINYIB_DBPOSTS . '_bumped_idx') . " ON $posts_table (bumped)");
	$db->exec('CREATE INDEX IF NOT EXISTS ' . sqliteIdentifier(TINYIB_DBPOSTS . '_moderated_idx') . " ON $posts_table (moderated)");
	$db->exec('CREATE INDEX IF NOT EXISTS ' . sqliteIdentifier(TINYIB_DBPOSTS . '_stickied_idx') . " ON $posts_table (stickied)");
	$db->exec('CREATE INDEX IF NOT EXISTS ' . sqliteIdentifier(TINYIB_DBBANS . '_ip_idx') . " ON $bans_table (ip)");
	$db->exec('CREATE INDEX IF NOT EXISTS ' . sqliteIdentifier(TINYIB_DBLOGS . '_account_idx') . " ON $logs_table (account)");
	$db->exec('CREATE INDEX IF NOT EXISTS ' . sqliteIdentifier(TINYIB_DBREPORTS . '_post_idx') . " ON $reports_table (post)");
} catch (Throwable $error) {
	fancyDie('Could not initialize SQLite: ' . htmlspecialchars($error->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}
