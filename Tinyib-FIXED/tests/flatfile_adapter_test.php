<?php
declare(strict_types=1);

function assertAdapter(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function removeAdapterDirectory(string $directory): void {
	if (!is_dir($directory)) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iterator as $item) {
		$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
	}
	rmdir($directory);
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

$testDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tinyib-flatfile-adapter-' . bin2hex(random_bytes(6));
define('TINYIB_BOARD', 'test');
define('TINYIB_NEWTHREAD', '0');
define('TINYIB_MAXTHREADS', 0);
define('TINYIB_FLATFILE_PATH', $testDirectory);

try {
	require __DIR__ . '/../inc/database/flatfile_link.php';
	require __DIR__ . '/../inc/database/flatfile.php';

	$accountId = insertAccount(array('username' => 'admin', 'password' => 'secret', 'role' => 1));
	assertAdapter($accountId === 1 && accountByUsername('admin')['id'] === 1, 'Account facade failed.');

	insertKeyword(array('text' => 'BLOCKED', 'action' => 'deny'));
	assertAdapter(keywordByText('blocked')['action'] === 'deny', 'Keyword facade failed.');

	$post = array(
		'parent' => 0, 'name' => '', 'tripcode' => '', 'email' => '',
		'nameblock' => 'Anonymous', 'subject' => 'Subject', 'message' => 'Message', 'password' => 'hash',
		'file' => '', 'file_hex' => '', 'file_original' => '', 'file_size' => 0, 'file_size_formatted' => '',
		'image_width' => 0, 'image_height' => 0, 'thumb' => '', 'thumb_width' => 0, 'thumb_height' => 0,
		'stickied' => 0, 'locked' => 0, 'moderated' => 1
	);
	$threadId = insertPost($post);
	$post['parent'] = $threadId;
	$post['message'] = 'Reply';
	$replyId = insertPost($post);
	assertAdapter(threadExistsByID($threadId), 'Thread existence facade failed.');
	assertAdapter(numRepliesToThreadByID($threadId) === 1, 'Reply count facade failed.');
	assertAdapter(postByID($replyId)['message'] === 'Reply', 'Post lookup facade failed.');
	assertAdapter(!array_key_exists('ip', postByID($replyId)), 'Post records must not contain network identifiers.');

	insertReport(array('post' => $replyId));
	assertAdapter(count(reportsByPost($replyId)) === 1, 'Report list facade failed.');
	assertAdapter(!array_key_exists('ip', reportsByPost($replyId)[0]), 'Report records must not contain network identifiers.');
	deleteReportsByPost($replyId);
	assertAdapter(reportsByPost($replyId) === array(), 'Report deletion facade failed.');

	deletePostByID($replyId);
	assertAdapter(postByID($replyId) === array(), 'Post deletion facade failed.');

	echo "Flat-file adapter tests passed.\n";
} finally {
	removeAdapterDirectory($testDirectory);
}
