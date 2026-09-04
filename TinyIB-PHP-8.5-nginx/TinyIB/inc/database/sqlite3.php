<?php
declare(strict_types=1);

if (!defined('TINYIB_BOARD')) {
	die('');
}

function dbEscape(mixed $value): string {
	global $db;
	return $db->escapeString((string)$value);
}

// Account functions
function accountByID($id) {
	global $db;
	$result = $db->query("SELECT * FROM " . TINYIB_DBACCOUNTS . " WHERE id = '" . dbEscape($id) . "' LIMIT 1");
	while ($account = $result->fetchArray(SQLITE3_ASSOC)) {
		return $account;
	}
}

function accountByUsername($username) {
	global $db;
	$result = $db->query("SELECT * FROM " . TINYIB_DBACCOUNTS . " WHERE username = '" . dbEscape($username) . "' LIMIT 1");
	while ($account = $result->fetchArray(SQLITE3_ASSOC)) {
		return $account;
	}
}

function allAccounts() {
	global $db;
	$accounts = array();
	$result = $db->query("SELECT * FROM " . TINYIB_DBACCOUNTS . " ORDER BY role ASC, username ASC");
	while ($account = $result->fetchArray(SQLITE3_ASSOC)) {
		$accounts[] = $account;
	}
	return $accounts;
}

function insertAccount($account) {
	global $db;
	$db->exec("INSERT INTO " . TINYIB_DBACCOUNTS . " (username, password, role, lastactive) VALUES ('" . dbEscape($account['username']) . "', '" . dbEscape(hashData($account['password'])) . "', '" . dbEscape($account['role']) . "', '0')");
	return $db->lastInsertRowID();
}

function updateAccount($account) {
	global $db;
	$db->exec("UPDATE " . TINYIB_DBACCOUNTS . " SET username = '" . dbEscape($account['username']) . "', password = '" . dbEscape(hashData($account['password'])) . "', role = '" . dbEscape($account['role']) . "', lastactive = '" . dbEscape($account['lastactive']) . "'  WHERE id = '" . dbEscape($account['id']) . "'");
	return $db->lastInsertRowID();
}

// Keyword functions
function keywordByID($id) {
	global $db;
	$result = $db->query("SELECT * FROM " . TINYIB_DBKEYWORDS . " WHERE id = '" . dbEscape($id) . "' LIMIT 1");
	while ($keyword = $result->fetchArray(SQLITE3_ASSOC)) {
		return $keyword;
	}
	return array();
}

function keywordByText($text) {
	global $db;
	$text = strtolower($text);
	$result = $db->query("SELECT * FROM " . TINYIB_DBKEYWORDS . " WHERE text = '" . dbEscape($text) . "'");
	while ($keyword = $result->fetchArray(SQLITE3_ASSOC)) {
		if ($keyword['text'] === $text) {
			return $keyword;
		}
	}
	return array();
}

function allKeywords() {
	global $db;
	$keywords = array();
	$result = $db->query("SELECT * FROM " . TINYIB_DBKEYWORDS . " ORDER BY text ASC");
	while ($keyword = $result->fetchArray(SQLITE3_ASSOC)) {
		$keywords[] = $keyword;
	}
	return $keywords;
}

function insertKeyword($keyword) {
	global $db;
	$keyword['text'] = strtolower($keyword['text']);
	$db->exec("INSERT INTO " . TINYIB_DBKEYWORDS . " (text, action) VALUES ('" . dbEscape($keyword['text']) . "', '" . dbEscape($keyword['action']) . "')");
}

function deleteKeyword($id) {
	global $db;
	$db->exec("DELETE FROM " . TINYIB_DBKEYWORDS . " WHERE id = '" . dbEscape($id) . "'");
}

// Log functions
function getLogs($offset, $limit) {
	global $db;
	$logs = array();
	$result = $db->query("SELECT * FROM " . TINYIB_DBLOGS . " ORDER BY timestamp DESC LIMIT " . intval($offset) . ", " . intval($limit));
	while ($log = $result->fetchArray(SQLITE3_ASSOC)) {
		$logs[] = $log;
	}
	return $logs;
}

function insertLog($log) {
	global $db;
	$db->exec("INSERT INTO " . TINYIB_DBLOGS . " (timestamp, account, message) VALUES ('" . dbEscape($log['timestamp']) . "', '" . dbEscape($log['account']) . "', '" . dbEscape($log['message']) . "')");
}

// Post functions
function uniquePosts() {
	global $db;
	return $db->querySingle("SELECT COUNT(ip) FROM (SELECT DISTINCT ip FROM " . TINYIB_DBPOSTS . ")");
}

function postByID($id) {
	global $db;
	$result = $db->query("SELECT * FROM " . TINYIB_DBPOSTS . " WHERE id = '" . dbEscape($id) . "' LIMIT 1");
	while ($post = $result->fetchArray(SQLITE3_ASSOC)) {
		return $post;
	}
}

function threadExistsByID($id) {
	global $db;
	return $db->querySingle("SELECT COUNT(*) FROM " . TINYIB_DBPOSTS . " WHERE id = '" . dbEscape($id) . "' AND parent = 0 LIMIT 1") > 0;
}

function insertPost($post) {
	global $db;
	$db->exec("INSERT INTO " . TINYIB_DBPOSTS . " (parent, timestamp, bumped, ip, name, tripcode, email, nameblock, subject, message, password, file, file_hex, file_original, file_size, file_size_formatted, image_width, image_height, thumb, thumb_width, thumb_height, moderated) VALUES (" . $post['parent'] . ", " . time() . ", " . time() . ", '" . hashData(remoteAddress()) . "', '" . dbEscape($post['name']) . "', '" . dbEscape($post['tripcode']) . "',	'" . dbEscape($post['email']) . "',	'" . dbEscape($post['nameblock']) . "', '" . dbEscape($post['subject']) . "', '" . dbEscape($post['message']) . "', '" . dbEscape($post['password']) . "', '" . $post['file'] . "', '" . $post['file_hex'] . "', '" . dbEscape($post['file_original']) . "', " . $post['file_size'] . ", '" . $post['file_size_formatted'] . "', " . $post['image_width'] . ", " . $post['image_height'] . ", '" . $post['thumb'] . "', " . $post['thumb_width'] . ", " . $post['thumb_height'] . ", " . $post['moderated'] . ")");
	return $db->lastInsertRowID();
}

function updatePostBumped($id, $bumped) {
	global $db;
	$db->exec("UPDATE " . TINYIB_DBPOSTS . " SET bumped = '" . dbEscape($bumped) . "' WHERE id = " . $id);
}

function approvePostByID($id, $moderated) {
	global $db;
	$db->exec("UPDATE " . TINYIB_DBPOSTS . " SET moderated = " . $moderated . " WHERE id = " . $id);
}

function bumpThreadByID($id) {
	global $db;
	$db->exec("UPDATE " . TINYIB_DBPOSTS . " SET bumped = " . time() . " WHERE id = " . $id);
}

function stickyThreadByID($id, $setsticky) {
	global $db;
	$db->exec("UPDATE " . TINYIB_DBPOSTS . " SET stickied = '" . dbEscape($setsticky) . "' WHERE id = " . $id);
}

function lockThreadByID($id, $setlock) {
	global $db;
	$db->exec("UPDATE " . TINYIB_DBPOSTS . " SET locked = '" . dbEscape($setlock) . "' WHERE id = " . $id);
}

function countThreads() {
	global $db;
	return $db->querySingle("SELECT COUNT(*) FROM " . TINYIB_DBPOSTS . " WHERE parent = 0");
}

function allThreads($moderated_only = true) {
	global $db;
	$threads = array();
	$result = $db->query("SELECT * FROM " . TINYIB_DBPOSTS . " WHERE parent = 0" . ($moderated_only ? " AND moderated > 0" : "") . " ORDER BY stickied DESC, bumped DESC");
	while ($thread = $result->fetchArray(SQLITE3_ASSOC)) {
		$threads[] = $thread;
	}
	return $threads;
}

function numRepliesToThreadByID($id) {
	global $db;
	return $db->querySingle("SELECT COUNT(*) FROM " . TINYIB_DBPOSTS . " WHERE parent = " . $id);
}

function _postsInThreadByID($id, $moderated_only = true) {
	global $db;
	$posts = array();
	$result = $db->query("SELECT * FROM " . TINYIB_DBPOSTS . " WHERE (id = " . $id . " OR parent = " . $id . ")" . ($moderated_only ? " AND moderated > 0" : "") . " ORDER BY id ASC");
	while ($post = $result->fetchArray(SQLITE3_ASSOC)) {
		$posts[] = $post;
	}
	return $posts;
}

function postsByHex($hex) {
	global $db;
	$posts = array();
	$result = $db->query("SELECT id, parent FROM " . TINYIB_DBPOSTS . " WHERE file_hex = '" . dbEscape($hex) . "' LIMIT 1");
	while ($post = $result->fetchArray(SQLITE3_ASSOC)) {
		$posts[] = $post;
	}
	return $posts;
}

function latestPosts($moderated = true) {
	global $db;
	$posts = array();
	$result = $db->query("SELECT * FROM " . TINYIB_DBPOSTS . " WHERE `moderated` " . ($moderated ? '>' : '=') . " 0 ORDER BY timestamp DESC LIMIT 10");
	while ($post = $result->fetchArray(SQLITE3_ASSOC)) {
		$posts[] = $post;
	}
	return $posts;
}

function deletePostByID($id) {
	global $db;
	$db->exec("DELETE FROM " . TINYIB_DBPOSTS . " WHERE id = '" . dbEscape($id) . "'");
}

function trimThreads() {
	global $db;
	if (TINYIB_MAXTHREADS > 0) {
		$result = $db->query("SELECT id FROM " . TINYIB_DBPOSTS . " WHERE parent = 0 ORDER BY stickied DESC, bumped DESC LIMIT " . TINYIB_MAXTHREADS . ", 10");
		while ($post = $result->fetchArray(SQLITE3_ASSOC)) {
			deletePost($post['id']);
		}
	}
}

function lastPostByIP() {
	global $db;
	$result = $db->query("SELECT * FROM " . TINYIB_DBPOSTS . " WHERE ip = '" . dbEscape(remoteAddress()) . "' OR ip = '" . dbEscape(hashData(remoteAddress())) . "' ORDER BY id DESC LIMIT 1");
	while ($post = $result->fetchArray(SQLITE3_ASSOC)) {
		return $post;
	}
}

// Report functions
function reportsByPost($post) {
	global $db;
	$reports = array();
	$result = $db->query("SELECT * FROM " . TINYIB_DBREPORTS . " WHERE post = '" . dbEscape($post) . "'");
	while ($report = $result->fetchArray(SQLITE3_ASSOC)) {
		$reports[] = $report;
	}
	return $reports;
}

function allReports() {
	global $db;
	$reports = array();
	$result = $db->query("SELECT * FROM " . TINYIB_DBREPORTS . " ORDER BY post ASC");
	while ($report = $result->fetchArray(SQLITE3_ASSOC)) {
		$reports[] = $report;
	}
	return $reports;
}

function insertReport($report) {
	global $db;
	$db->exec("INSERT INTO " . TINYIB_DBREPORTS . " (post, reason) VALUES ('" . dbEscape($report['post']) . "', '" . dbEscape($report['reason']) . "')");
}

function deleteReportsByPost($post) {
	global $db;
	$db->exec("DELETE FROM " . TINYIB_DBREPORTS . " WHERE post = '" . dbEscape($post) . "'");
}
