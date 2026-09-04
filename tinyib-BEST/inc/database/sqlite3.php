<?php
if (!defined('TINYIB_BOARD')) {
	die('');
}

/**
 * Fetch a single associative row from a prepared statement, or null.
 */
function sqlite3FetchOne(SQLite3Stmt $stmt): ?array {
	$result = $stmt->execute();
	if ($result === false) {
		return null;
	}
	$row = $result->fetchArray(SQLITE3_ASSOC);
	$result->finalize();
	return $row === false ? null : $row;
}

/**
 * Fetch all associative rows from a prepared statement.
 */
function sqlite3FetchAll(SQLite3Stmt $stmt): array {
	$result = $stmt->execute();
	if ($result === false) {
		return [];
	}
	$rows = [];
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$rows[] = $row;
	}
	$result->finalize();
	return $rows;
}

// Account functions
function accountByID($id) {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBACCOUNTS . ' WHERE id = :id LIMIT 1');
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	return sqlite3FetchOne($stmt) ?? [];
}

function accountByUsername($username) {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBACCOUNTS . ' WHERE username = :username LIMIT 1');
	$stmt->bindValue(':username', $username, SQLITE3_TEXT);
	return sqlite3FetchOne($stmt) ?? [];
}

function allAccounts() {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBACCOUNTS . ' ORDER BY role ASC, username ASC');
	return sqlite3FetchAll($stmt);
}

function insertAccount($account) {
	global $db;
	$stmt = $db->prepare('INSERT INTO ' . TINYIB_DBACCOUNTS . ' (username, password, role, lastactive) VALUES (:username, :password, :role, 0)');
	$stmt->bindValue(':username', $account['username'], SQLITE3_TEXT);
	$stmt->bindValue(':password', hashData($account['password']), SQLITE3_TEXT);
	$stmt->bindValue(':role', (int)$account['role'], SQLITE3_INTEGER);
	$stmt->execute();
	return $db->lastInsertRowID();
}

function updateAccount($account) {
	global $db;
	$stmt = $db->prepare('UPDATE ' . TINYIB_DBACCOUNTS . ' SET username = :username, password = :password, role = :role, lastactive = :lastactive WHERE id = :id');
	$stmt->bindValue(':username', $account['username'], SQLITE3_TEXT);
	$stmt->bindValue(':password', hashData($account['password']), SQLITE3_TEXT);
	$stmt->bindValue(':role', (int)$account['role'], SQLITE3_INTEGER);
	$stmt->bindValue(':lastactive', (int)$account['lastactive'], SQLITE3_INTEGER);
	$stmt->bindValue(':id', (int)$account['id'], SQLITE3_INTEGER);
	$stmt->execute();
	return $db->lastInsertRowID();
}

function deleteAccountByID($id) {
	global $db;
	$stmt = $db->prepare('DELETE FROM ' . TINYIB_DBACCOUNTS . ' WHERE id = :id');
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$stmt->execute();
}

// Ban functions
function banByID($id) {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBBANS . ' WHERE id = :id LIMIT 1');
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	return sqlite3FetchOne($stmt) ?? [];
}

function banByIP($ip) {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBBANS . ' WHERE ip = :ip OR ip = :iphash LIMIT 1');
	$stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
	$stmt->bindValue(':iphash', hashData($ip), SQLITE3_TEXT);
	return sqlite3FetchOne($stmt) ?? [];
}

function allBans() {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBBANS . ' ORDER BY timestamp DESC');
	return sqlite3FetchAll($stmt);
}

function insertBan($ban) {
	global $db;
	$stmt = $db->prepare('INSERT INTO ' . TINYIB_DBBANS . ' (ip, timestamp, expire, reason) VALUES (:ip, :timestamp, :expire, :reason)');
	$stmt->bindValue(':ip', hashData($ban['ip']), SQLITE3_TEXT);
	$stmt->bindValue(':timestamp', time(), SQLITE3_INTEGER);
	$stmt->bindValue(':expire', (int)$ban['expire'], SQLITE3_INTEGER);
	$stmt->bindValue(':reason', $ban['reason'], SQLITE3_TEXT);
	$stmt->execute();
	return $db->lastInsertRowID();
}

function clearExpiredBans() {
	global $db;
	$stmt = $db->prepare('DELETE FROM ' . TINYIB_DBBANS . ' WHERE expire > 0 AND expire <= :now');
	$stmt->bindValue(':now', time(), SQLITE3_INTEGER);
	$stmt->execute();
}

function deleteBanByID($id) {
	global $db;
	$stmt = $db->prepare('DELETE FROM ' . TINYIB_DBBANS . ' WHERE id = :id');
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$stmt->execute();
}

// Keyword functions
function keywordByID($id) {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBKEYWORDS . ' WHERE id = :id LIMIT 1');
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	return sqlite3FetchOne($stmt) ?? [];
}

function keywordByText($text) {
	global $db;
	$text = strtolower($text);
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBKEYWORDS . ' WHERE text = :text');
	$stmt->bindValue(':text', $text, SQLITE3_TEXT);
	$result = $stmt->execute();
	while ($keyword = $result->fetchArray(SQLITE3_ASSOC)) {
		if ($keyword['text'] === $text) {
			$result->finalize();
			return $keyword;
		}
	}
	$result->finalize();
	return [];
}

function allKeywords() {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBKEYWORDS . ' ORDER BY text ASC');
	return sqlite3FetchAll($stmt);
}

function insertKeyword($keyword) {
	global $db;
	$keyword['text'] = strtolower($keyword['text']);
	$stmt = $db->prepare('INSERT INTO ' . TINYIB_DBKEYWORDS . ' (text, action) VALUES (:text, :action)');
	$stmt->bindValue(':text', $keyword['text'], SQLITE3_TEXT);
	$stmt->bindValue(':action', $keyword['action'], SQLITE3_TEXT);
	$stmt->execute();
}

function deleteKeyword($id) {
	global $db;
	$stmt = $db->prepare('DELETE FROM ' . TINYIB_DBKEYWORDS . ' WHERE id = :id');
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$stmt->execute();
}

// Log functions
function getLogs($offset, $limit) {
	global $db;
	// SQLite uses LIMIT count OFFSET offset
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBLOGS . ' ORDER BY timestamp DESC LIMIT :limit OFFSET :offset');
	$stmt->bindValue(':limit', (int)$limit, SQLITE3_INTEGER);
	$stmt->bindValue(':offset', (int)$offset, SQLITE3_INTEGER);
	return sqlite3FetchAll($stmt);
}

function allLogs() {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBLOGS . ' ORDER BY timestamp ASC');
	return sqlite3FetchAll($stmt);
}

function insertLog($log) {
	global $db;
	$stmt = $db->prepare('INSERT INTO ' . TINYIB_DBLOGS . ' (timestamp, account, message) VALUES (:timestamp, :account, :message)');
	$stmt->bindValue(':timestamp', (int)$log['timestamp'], SQLITE3_INTEGER);
	$stmt->bindValue(':account', (int)$log['account'], SQLITE3_INTEGER);
	$stmt->bindValue(':message', $log['message'], SQLITE3_TEXT);
	$stmt->execute();
}

// Post functions
function uniquePosts() {
	global $db;
	return (int)$db->querySingle('SELECT COUNT(ip) FROM (SELECT DISTINCT ip FROM ' . TINYIB_DBPOSTS . ')');
}

function postByID($id) {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBPOSTS . ' WHERE id = :id LIMIT 1');
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	return sqlite3FetchOne($stmt);
}

function threadExistsByID($id) {
	global $db;
	$stmt = $db->prepare('SELECT COUNT(*) FROM ' . TINYIB_DBPOSTS . ' WHERE id = :id AND parent = 0 LIMIT 1');
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$result = $stmt->execute();
	$count = (int)$result->fetchArray(SQLITE3_NUM)[0];
	$result->finalize();
	return $count > 0;
}

function insertPost($post) {
	global $db;
	$stmt = $db->prepare('INSERT INTO ' . TINYIB_DBPOSTS . ' (
		parent, timestamp, bumped, ip, name, tripcode, email, nameblock, subject, message, password,
		file, file_hex, file_original, file_size, file_size_formatted, image_width, image_height,
		thumb, thumb_width, thumb_height, moderated
	) VALUES (
		:parent, :timestamp, :bumped, :ip, :name, :tripcode, :email, :nameblock, :subject, :message, :password,
		:file, :file_hex, :file_original, :file_size, :file_size_formatted, :image_width, :image_height,
		:thumb, :thumb_width, :thumb_height, :moderated
	)');
	$now = time();
	$stmt->bindValue(':parent', (int)$post['parent'], SQLITE3_INTEGER);
	$stmt->bindValue(':timestamp', $now, SQLITE3_INTEGER);
	$stmt->bindValue(':bumped', $now, SQLITE3_INTEGER);
	$stmt->bindValue(':ip', hashData(remoteAddress()), SQLITE3_TEXT);
	$stmt->bindValue(':name', $post['name'], SQLITE3_TEXT);
	$stmt->bindValue(':tripcode', $post['tripcode'], SQLITE3_TEXT);
	$stmt->bindValue(':email', $post['email'], SQLITE3_TEXT);
	$stmt->bindValue(':nameblock', $post['nameblock'], SQLITE3_TEXT);
	$stmt->bindValue(':subject', $post['subject'], SQLITE3_TEXT);
	$stmt->bindValue(':message', $post['message'], SQLITE3_TEXT);
	$stmt->bindValue(':password', $post['password'], SQLITE3_TEXT);
	$stmt->bindValue(':file', $post['file'], SQLITE3_TEXT);
	$stmt->bindValue(':file_hex', $post['file_hex'], SQLITE3_TEXT);
	$stmt->bindValue(':file_original', $post['file_original'], SQLITE3_TEXT);
	$stmt->bindValue(':file_size', (int)$post['file_size'], SQLITE3_INTEGER);
	$stmt->bindValue(':file_size_formatted', $post['file_size_formatted'], SQLITE3_TEXT);
	$stmt->bindValue(':image_width', (int)$post['image_width'], SQLITE3_INTEGER);
	$stmt->bindValue(':image_height', (int)$post['image_height'], SQLITE3_INTEGER);
	$stmt->bindValue(':thumb', $post['thumb'], SQLITE3_TEXT);
	$stmt->bindValue(':thumb_width', (int)$post['thumb_width'], SQLITE3_INTEGER);
	$stmt->bindValue(':thumb_height', (int)$post['thumb_height'], SQLITE3_INTEGER);
	$stmt->bindValue(':moderated', (int)$post['moderated'], SQLITE3_INTEGER);
	$stmt->execute();
	return $db->lastInsertRowID();
}

function updatePostMessage($id, $message) {
	global $db;
	$stmt = $db->prepare('UPDATE ' . TINYIB_DBPOSTS . ' SET message = :message WHERE id = :id');
	$stmt->bindValue(':message', $message, SQLITE3_TEXT);
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$stmt->execute();
}

function updatePostBumped($id, $bumped) {
	global $db;
	$stmt = $db->prepare('UPDATE ' . TINYIB_DBPOSTS . ' SET bumped = :bumped WHERE id = :id');
	$stmt->bindValue(':bumped', (int)$bumped, SQLITE3_INTEGER);
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$stmt->execute();
}

function approvePostByID($id, $moderated) {
	global $db;
	$stmt = $db->prepare('UPDATE ' . TINYIB_DBPOSTS . ' SET moderated = :moderated WHERE id = :id');
	$stmt->bindValue(':moderated', (int)$moderated, SQLITE3_INTEGER);
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$stmt->execute();
}

function bumpThreadByID($id) {
	global $db;
	$stmt = $db->prepare('UPDATE ' . TINYIB_DBPOSTS . ' SET bumped = :bumped WHERE id = :id');
	$stmt->bindValue(':bumped', time(), SQLITE3_INTEGER);
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$stmt->execute();
}

function stickyThreadByID($id, $setsticky) {
	global $db;
	$stmt = $db->prepare('UPDATE ' . TINYIB_DBPOSTS . ' SET stickied = :stickied WHERE id = :id');
	$stmt->bindValue(':stickied', (int)$setsticky, SQLITE3_INTEGER);
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$stmt->execute();
}

function lockThreadByID($id, $setlock) {
	global $db;
	$stmt = $db->prepare('UPDATE ' . TINYIB_DBPOSTS . ' SET locked = :locked WHERE id = :id');
	$stmt->bindValue(':locked', (int)$setlock, SQLITE3_INTEGER);
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$stmt->execute();
}

function countThreads() {
	global $db;
	return (int)$db->querySingle('SELECT COUNT(*) FROM ' . TINYIB_DBPOSTS . ' WHERE parent = 0');
}

function allThreads($moderated_only = true) {
	global $db;
	$sql = 'SELECT * FROM ' . TINYIB_DBPOSTS . ' WHERE parent = 0';
	if ($moderated_only) {
		$sql .= ' AND moderated > 0';
	}
	$sql .= ' ORDER BY stickied DESC, bumped DESC';
	$stmt = $db->prepare($sql);
	return sqlite3FetchAll($stmt);
}

function numRepliesToThreadByID($id) {
	global $db;
	$stmt = $db->prepare('SELECT COUNT(*) FROM ' . TINYIB_DBPOSTS . ' WHERE parent = :id');
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$result = $stmt->execute();
	$count = (int)$result->fetchArray(SQLITE3_NUM)[0];
	$result->finalize();
	return $count;
}

function _postsInThreadByID($id, $moderated_only = true) {
	global $db;
	$sql = 'SELECT * FROM ' . TINYIB_DBPOSTS . ' WHERE (id = :id OR parent = :id)';
	if ($moderated_only) {
		$sql .= ' AND moderated > 0';
	}
	$sql .= ' ORDER BY id ASC';
	$stmt = $db->prepare($sql);
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	return sqlite3FetchAll($stmt);
}

function imagesInThreadByID($id, $moderated_only = true) {
	$images = 0;
	$posts = postsInThreadByID($id, false);
	foreach ($posts as $post) {
		if ($post['file'] != '') {
			$images++;
		}
	}
	return $images;
}

function postsByHex($hex) {
	global $db;
	$stmt = $db->prepare('SELECT id, parent FROM ' . TINYIB_DBPOSTS . ' WHERE file_hex = :hex LIMIT 1');
	$stmt->bindValue(':hex', $hex, SQLITE3_TEXT);
	return sqlite3FetchAll($stmt);
}

function latestPosts($moderated = true) {
	global $db;
	$sql = 'SELECT * FROM ' . TINYIB_DBPOSTS . ' WHERE moderated ' . ($moderated ? '>' : '=') . ' 0 ORDER BY timestamp DESC LIMIT 10';
	$stmt = $db->prepare($sql);
	return sqlite3FetchAll($stmt);
}

function deletePostByID($id) {
	global $db;
	$stmt = $db->prepare('DELETE FROM ' . TINYIB_DBPOSTS . ' WHERE id = :id');
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$stmt->execute();
}

function trimThreads() {
	if (TINYIB_MAXTHREADS <= 0) {
		return;
	}
	global $db;
	// OFFSET past max threads, then delete those older threads
	$stmt = $db->prepare('SELECT id FROM ' . TINYIB_DBPOSTS . ' WHERE parent = 0 ORDER BY stickied DESC, bumped DESC LIMIT -1 OFFSET :offset');
	$stmt->bindValue(':offset', (int)TINYIB_MAXTHREADS, SQLITE3_INTEGER);
	$posts = sqlite3FetchAll($stmt);
	foreach ($posts as $post) {
		deletePost($post['id']);
	}
}

function lastPostByIP() {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBPOSTS . ' WHERE ip = :ip OR ip = :iphash ORDER BY id DESC LIMIT 1');
	$stmt->bindValue(':ip', remoteAddress(), SQLITE3_TEXT);
	$stmt->bindValue(':iphash', hashData(remoteAddress()), SQLITE3_TEXT);
	return sqlite3FetchOne($stmt);
}

// Report functions
function reportByIP($post, $ip) {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBREPORTS . ' WHERE post = :post AND (ip = :ip OR ip = :iphash) LIMIT 1');
	$stmt->bindValue(':post', (int)$post, SQLITE3_INTEGER);
	$stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
	$stmt->bindValue(':iphash', hashData($ip), SQLITE3_TEXT);
	return sqlite3FetchOne($stmt) ?? [];
}

function reportsByPost($post) {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBREPORTS . ' WHERE post = :post');
	$stmt->bindValue(':post', (int)$post, SQLITE3_INTEGER);
	return sqlite3FetchAll($stmt);
}

function allReports() {
	global $db;
	$stmt = $db->prepare('SELECT * FROM ' . TINYIB_DBREPORTS . ' ORDER BY post ASC');
	return sqlite3FetchAll($stmt);
}

function insertReport($report) {
	global $db;
	$stmt = $db->prepare('INSERT INTO ' . TINYIB_DBREPORTS . ' (ip, post) VALUES (:ip, :post)');
	$stmt->bindValue(':ip', hashData($report['ip']), SQLITE3_TEXT);
	$stmt->bindValue(':post', (int)$report['post'], SQLITE3_INTEGER);
	$stmt->execute();
}

function deleteReportsByPost($post) {
	global $db;
	$stmt = $db->prepare('DELETE FROM ' . TINYIB_DBREPORTS . ' WHERE post = :post');
	$stmt->bindValue(':post', (int)$post, SQLITE3_INTEGER);
	$stmt->execute();
}

function deleteReportsByIP($ip) {
	global $db;
	$stmt = $db->prepare('DELETE FROM ' . TINYIB_DBREPORTS . ' WHERE ip = :ip OR ip = :iphash');
	$stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
	$stmt->bindValue(':iphash', hashData($ip), SQLITE3_TEXT);
	$stmt->execute();
}
