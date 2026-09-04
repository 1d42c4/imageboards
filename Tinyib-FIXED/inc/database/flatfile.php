<?php
declare(strict_types=1);

use TinyIB\Storage\FlatFileDatabase;

if (!defined('TINYIB_BOARD')) {
	die('');
}

function flatFileDatabase(): FlatFileDatabase {
	return $GLOBALS['db'];
}

// Accounts
function accountByID($id) {
	$id = (int)$id;
	return flatFileDatabase()->findRecord('accounts', static fn(array $account): bool => (int)$account['id'] === $id);
}

function accountByUsername($username) {
	$username = (string)$username;
	return flatFileDatabase()->findRecord('accounts', static fn(array $account): bool => $account['username'] === $username);
}

function allAccounts() {
	$accounts = flatFileDatabase()->records('accounts');
	usort($accounts, static function (array $left, array $right): int {
		$role = (int)$left['role'] <=> (int)$right['role'];
		return $role !== 0 ? $role : strcasecmp($left['username'], $right['username']);
	});
	return $accounts;
}

function insertAccount($account) {
	return flatFileDatabase()->insertRecord('accounts', array(
		'username' => (string)$account['username'],
		'password' => hashData((string)$account['password']),
		'role' => (int)$account['role'],
		'lastactive' => 0
	));
}

function updateAccount($account) {
	flatFileDatabase()->updateRecord('accounts', (int)$account['id'], array(
		'username' => (string)$account['username'],
		'password' => hashData((string)$account['password']),
		'role' => (int)$account['role'],
		'lastactive' => (int)$account['lastactive']
	));
}

function deleteAccountByID($id) {
	$id = (int)$id;
	flatFileDatabase()->deleteRecords('accounts', static fn(array $account): bool => (int)$account['id'] === $id);
}

// Keywords
function keywordByID($id) {
	$id = (int)$id;
	return flatFileDatabase()->findRecord('keywords', static fn(array $keyword): bool => (int)$keyword['id'] === $id);
}

function keywordByText($text) {
	$text = strtolower((string)$text);
	return flatFileDatabase()->findRecord('keywords', static fn(array $keyword): bool => $keyword['text'] === $text);
}

function allKeywords() {
	$keywords = flatFileDatabase()->records('keywords');
	usort($keywords, static fn(array $left, array $right): int => strcmp($left['text'], $right['text']));
	return $keywords;
}

function insertKeyword($keyword) {
	return flatFileDatabase()->insertRecord('keywords', array(
		'text' => strtolower((string)$keyword['text']),
		'action' => (string)$keyword['action']
	));
}

function deleteKeyword($id) {
	$id = (int)$id;
	flatFileDatabase()->deleteRecords('keywords', static fn(array $keyword): bool => (int)$keyword['id'] === $id);
}

// Moderation log
function getLogs($offset, $limit) {
	$logs = flatFileDatabase()->records('logs');
	usort($logs, static function (array $left, array $right): int {
		$timestamp = (int)$right['timestamp'] <=> (int)$left['timestamp'];
		return $timestamp !== 0 ? $timestamp : ((int)$right['id'] <=> (int)$left['id']);
	});
	return array_slice($logs, max(0, (int)$offset), max(0, (int)$limit));
}

function allLogs() {
	$logs = flatFileDatabase()->records('logs');
	usort($logs, static function (array $left, array $right): int {
		$timestamp = (int)$left['timestamp'] <=> (int)$right['timestamp'];
		return $timestamp !== 0 ? $timestamp : ((int)$left['id'] <=> (int)$right['id']);
	});
	return $logs;
}

function insertLog($log) {
	return flatFileDatabase()->insertRecord('logs', array(
		'timestamp' => (int)$log['timestamp'],
		'account' => (int)$log['account'],
		'message' => (string)$log['message']
	));
}

// Posts
function postByID($id) {
	return flatFileDatabase()->postById((int)$id);
}

function threadExistsByID($id) {
	return flatFileDatabase()->threadExists((int)$id);
}

function insertPost($post) {
	return flatFileDatabase()->insertPost($post);
}

function updatePostMessage($id, $message) {
	$message = (string)$message;
	flatFileDatabase()->updatePost((int)$id, static function (array $post) use ($message): array {
		$post['message'] = $message;
		return $post;
	});
}

function updatePostBumped($id, $bumped) {
	$bumped = (int)$bumped;
	flatFileDatabase()->updatePost((int)$id, static function (array $post) use ($bumped): array {
		$post['bumped'] = $bumped;
		return $post;
	});
}

function approvePostByID($id, $moderated) {
	$moderated = (int)$moderated;
	flatFileDatabase()->updatePost((int)$id, static function (array $post) use ($moderated): array {
		$post['moderated'] = $moderated;
		return $post;
	});
}

function bumpThreadByID($id) {
	$bumped = time();
	flatFileDatabase()->updatePost((int)$id, static function (array $post) use ($bumped): array {
		$post['bumped'] = $bumped;
		return $post;
	});
}

function stickyThreadByID($id, $setsticky) {
	$stickied = (int)$setsticky;
	flatFileDatabase()->updatePost((int)$id, static function (array $post) use ($stickied): array {
		$post['stickied'] = $stickied;
		return $post;
	});
}

function lockThreadByID($id, $setlock) {
	$locked = (int)$setlock;
	flatFileDatabase()->updatePost((int)$id, static function (array $post) use ($locked): array {
		$post['locked'] = $locked;
		return $post;
	});
}

function countThreads() {
	return flatFileDatabase()->countThreads();
}

function allThreads($moderated_only = true) {
	return flatFileDatabase()->allThreads((bool)$moderated_only);
}

function numRepliesToThreadByID($id) {
	$posts = flatFileDatabase()->postsInThread((int)$id, false);
	return max(0, count($posts) - 1);
}

function _postsInThreadByID($id, $moderated_only = true) {
	return flatFileDatabase()->postsInThread((int)$id, (bool)$moderated_only);
}

function imagesInThreadByID($id, $moderated_only = true) {
	$images = 0;
	foreach (flatFileDatabase()->postsInThread((int)$id, false) as $post) {
		if ($post['file'] !== '') {
			$images++;
		}
	}
	return $images;
}

function postsByHex($hex) {
	$posts = flatFileDatabase()->postsByFileHash((string)$hex, 1);
	return array_map(
		static fn(array $post): array => array('id' => $post['id'], 'parent' => $post['parent']),
		$posts
	);
}

function latestPosts($moderated = true) {
	return flatFileDatabase()->latestPosts((bool)$moderated, 10);
}

function deletePostByID($id) {
	flatFileDatabase()->deletePost((int)$id);
}

function trimThreads() {
	if (TINYIB_MAXTHREADS <= 0) {
		return;
	}
	$threads = flatFileDatabase()->allThreads(false);
	foreach (array_slice($threads, TINYIB_MAXTHREADS) as $thread) {
		deletePost($thread['id']);
	}
}

// Reports
function reportsByPost($post) {
	$post = (int)$post;
	return array_values(array_filter(
		flatFileDatabase()->records('reports'),
		static fn(array $report): bool => (int)$report['post'] === $post
	));
}

function allReports() {
	$reports = flatFileDatabase()->records('reports');
	usort($reports, static function (array $left, array $right): int {
		$post = (int)$left['post'] <=> (int)$right['post'];
		return $post !== 0 ? $post : ((int)$left['id'] <=> (int)$right['id']);
	});
	return $reports;
}

function insertReport($report) {
	return flatFileDatabase()->insertRecord('reports', array(
		'post' => (int)$report['post']
	));
}

function deleteReportsByPost($post) {
	$post = (int)$post;
	flatFileDatabase()->deleteRecords('reports', static fn(array $report): bool => (int)$report['post'] === $post);
}
