<?php
if (!defined('TINYIB_BOARD')) {
	die('');
}

if (!extension_loaded('sqlite3')) {
	fancyDie('SQLite3 extension is either not installed or loaded');
}

try {
	$db = new SQLite3(TINYIB_DBPATH);
} catch (Exception $e) {
	fancyDie('Could not connect to database: ' . $e->getMessage());
}

$db->busyTimeout(60000);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec('PRAGMA journal_mode = WAL');

/**
 * Return true if a table exists in the SQLite database.
 */
function sqlite3TableExists(string $name): bool {
	global $db;
	$stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
	$stmt->bindValue(':name', $name, SQLITE3_TEXT);
	$result = $stmt->execute();
	$exists = (bool)$result->fetchArray();
	$result->finalize();
	return $exists;
}

/**
 * Add a column if it is not already present (SQLite has no IF NOT EXISTS for columns).
 */
function sqlite3AddColumnIfMissing(string $table, string $column, string $definition): void {
	global $db;
	$result = $db->query("PRAGMA table_info(" . $table . ")");
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		if (strcasecmp($row['name'], $column) === 0) {
			$result->finalize();
			return;
		}
	}
	$result->finalize();
	$db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
}

// Create tables (when necessary)
if (!sqlite3TableExists(TINYIB_DBACCOUNTS)) {
	$db->exec('CREATE TABLE ' . TINYIB_DBACCOUNTS . ' (
		id INTEGER PRIMARY KEY,
		username TEXT NOT NULL,
		password TEXT NOT NULL,
		role INTEGER NOT NULL,
		lastactive INTEGER NOT NULL DEFAULT 0
	)');
}

if (!sqlite3TableExists(TINYIB_DBBANS)) {
	$db->exec('CREATE TABLE ' . TINYIB_DBBANS . ' (
		id INTEGER PRIMARY KEY,
		ip TEXT NOT NULL,
		timestamp INTEGER NOT NULL,
		expire INTEGER NOT NULL,
		reason TEXT NOT NULL
	)');
	$db->exec('CREATE INDEX IF NOT EXISTS idx_' . TINYIB_DBBANS . '_ip ON ' . TINYIB_DBBANS . ' (ip)');
}

if (!sqlite3TableExists(TINYIB_DBKEYWORDS)) {
	$db->exec('CREATE TABLE ' . TINYIB_DBKEYWORDS . ' (
		id INTEGER PRIMARY KEY,
		text TEXT NOT NULL,
		action TEXT NOT NULL
	)');
}

if (!sqlite3TableExists(TINYIB_DBLOGS)) {
	$db->exec('CREATE TABLE ' . TINYIB_DBLOGS . ' (
		id INTEGER PRIMARY KEY,
		timestamp INTEGER NOT NULL,
		account INTEGER NOT NULL,
		message TEXT NOT NULL
	)');
	$db->exec('CREATE INDEX IF NOT EXISTS idx_' . TINYIB_DBLOGS . '_account ON ' . TINYIB_DBLOGS . ' (account)');
}

if (!sqlite3TableExists(TINYIB_DBPOSTS)) {
	$db->exec('CREATE TABLE ' . TINYIB_DBPOSTS . ' (
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
	)');
	$db->exec('CREATE INDEX IF NOT EXISTS idx_' . TINYIB_DBPOSTS . '_parent ON ' . TINYIB_DBPOSTS . ' (parent)');
	$db->exec('CREATE INDEX IF NOT EXISTS idx_' . TINYIB_DBPOSTS . '_bumped ON ' . TINYIB_DBPOSTS . ' (bumped)');
	$db->exec('CREATE INDEX IF NOT EXISTS idx_' . TINYIB_DBPOSTS . '_stickied ON ' . TINYIB_DBPOSTS . ' (stickied)');
	$db->exec('CREATE INDEX IF NOT EXISTS idx_' . TINYIB_DBPOSTS . '_moderated ON ' . TINYIB_DBPOSTS . ' (moderated)');
}

if (!sqlite3TableExists(TINYIB_DBREPORTS)) {
	$db->exec('CREATE TABLE ' . TINYIB_DBREPORTS . ' (
		id INTEGER PRIMARY KEY,
		ip TEXT NOT NULL,
		post INTEGER NOT NULL
	)');
	$db->exec('CREATE INDEX IF NOT EXISTS idx_' . TINYIB_DBREPORTS . '_post ON ' . TINYIB_DBREPORTS . ' (post)');
}

// Upgrade older databases that may be missing columns
sqlite3AddColumnIfMissing(TINYIB_DBPOSTS, 'moderated', "INTEGER NOT NULL DEFAULT 1");
sqlite3AddColumnIfMissing(TINYIB_DBPOSTS, 'stickied', "INTEGER NOT NULL DEFAULT 0");
sqlite3AddColumnIfMissing(TINYIB_DBPOSTS, 'locked', "INTEGER NOT NULL DEFAULT 0");
