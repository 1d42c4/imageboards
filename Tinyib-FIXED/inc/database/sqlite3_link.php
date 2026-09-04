<?php
if (!defined('TINYIB_BOARD')) {
	die('');
}

if (!extension_loaded('sqlite3')) {
	fancyDie("SQLite3 extension is either not installed or loaded");
}

$db = new SQLite3(TINYIB_DBPATH);
if (!$db) {
	fancyDie("Could not connect to database: " . $db->lastErrorMsg());
}

$db->busyTimeout(60000);
$db->enableExceptions(true);

function sqliteTableExists(SQLite3 $db, string $table): bool {
	$table = $db->escapeString($table);
	return (int)$db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = '$table'") > 0;
}

function sqliteTableHasColumn(SQLite3 $db, string $table, string $column): bool {
	$result = $db->query("PRAGMA table_info(" . $table . ")");
	while ($info = $result->fetchArray(SQLITE3_ASSOC)) {
		if ($info['name'] === $column) {
			return true;
		}
	}
	return false;
}

function sqliteRebuildTable(SQLite3 $db, string $table, string $columnsSql, array $columns): void {
	$temporaryTable = $table . '_privacy_migration';
	$columnList = implode(', ', $columns);
	$db->exec('BEGIN IMMEDIATE');
	try {
		$db->exec("DROP TABLE IF EXISTS " . $temporaryTable);
		$db->exec("CREATE TABLE " . $temporaryTable . " (" . $columnsSql . ")");
		$db->exec("INSERT INTO " . $temporaryTable . " (" . $columnList . ") SELECT " . $columnList . " FROM " . $table);
		$db->exec("DROP TABLE " . $table);
		$db->exec("ALTER TABLE " . $temporaryTable . " RENAME TO " . $table);
		$db->exec('COMMIT');
	} catch (Throwable $error) {
		$db->exec('ROLLBACK');
		throw $error;
	}
}

// Create tables (when necessary)
$result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . TINYIB_DBACCOUNTS . "'");
if (!$result->fetchArray()) {
	$db->exec("CREATE TABLE " . TINYIB_DBACCOUNTS . " (
		id INTEGER PRIMARY KEY,
		username TEXT NOT NULL,
		password TEXT NOT NULL,
		role INTEGER NOT NULL,
		lastactive TIMESTAMP NOT NULL
	)");
}

$legacyBansTable = defined('TINYIB_DBBANS') ? TINYIB_DBBANS : 'bans';
$db->exec("DROP TABLE IF EXISTS " . $legacyBansTable);

$result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . TINYIB_DBKEYWORDS . "'");
if (!$result->fetchArray()) {
	$db->exec("CREATE TABLE " . TINYIB_DBKEYWORDS . " (
		id INTEGER PRIMARY KEY,
		text TEXT NOT NULL,
		action TEXT NOT NULL
	)");
}
$db->exec("UPDATE " . TINYIB_DBKEYWORDS . " SET action = 'delete' WHERE action LIKE 'ban%'");

$result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . TINYIB_DBLOGS . "'");
if (!$result->fetchArray()) {
	$db->exec("CREATE TABLE " . TINYIB_DBLOGS . " (
		id INTEGER PRIMARY KEY,
		timestamp TIMESTAMP NOT NULL,
		account INTEGER NOT NULL,
		message TEXT NOT NULL
	)");
}

$postColumnsSql = "
	id INTEGER PRIMARY KEY,
	parent INTEGER NOT NULL,
	timestamp TIMESTAMP NOT NULL,
	bumped TIMESTAMP NOT NULL,
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
";
$postColumns = array(
	'id', 'parent', 'timestamp', 'bumped', 'name', 'tripcode', 'email', 'nameblock',
	'subject', 'message', 'password', 'file', 'file_hex', 'file_original', 'file_size',
	'file_size_formatted', 'image_width', 'image_height', 'thumb', 'thumb_width',
	'thumb_height', 'moderated', 'stickied', 'locked'
);

if (!sqliteTableExists($db, TINYIB_DBPOSTS)) {
	$db->exec("CREATE TABLE " . TINYIB_DBPOSTS . " (" . $postColumnsSql . ")");
} else {
	if (!sqliteTableHasColumn($db, TINYIB_DBPOSTS, 'moderated')) {
		$db->exec("ALTER TABLE " . TINYIB_DBPOSTS . " ADD COLUMN moderated INTEGER NOT NULL DEFAULT '1'");
	}
	if (!sqliteTableHasColumn($db, TINYIB_DBPOSTS, 'stickied')) {
		$db->exec("ALTER TABLE " . TINYIB_DBPOSTS . " ADD COLUMN stickied INTEGER NOT NULL DEFAULT '0'");
	}
	if (!sqliteTableHasColumn($db, TINYIB_DBPOSTS, 'locked')) {
		$db->exec("ALTER TABLE " . TINYIB_DBPOSTS . " ADD COLUMN locked INTEGER NOT NULL DEFAULT '0'");
	}
	if (sqliteTableHasColumn($db, TINYIB_DBPOSTS, 'ip')) {
		sqliteRebuildTable($db, TINYIB_DBPOSTS, $postColumnsSql, $postColumns);
	}
}

$reportColumnsSql = "
	id INTEGER PRIMARY KEY,
	post INTEGER NOT NULL
";
if (!sqliteTableExists($db, TINYIB_DBREPORTS)) {
	$db->exec("CREATE TABLE " . TINYIB_DBREPORTS . " (" . $reportColumnsSql . ")");
} elseif (sqliteTableHasColumn($db, TINYIB_DBREPORTS, 'ip')) {
	sqliteRebuildTable($db, TINYIB_DBREPORTS, $reportColumnsSql, array('id', 'post'));
}
