<?php
declare(strict_types=1);

use TinyIB\Storage\FlatFileDatabase;

require __DIR__ . '/../inc/database/flatfile/FlatFileDatabase.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
	if ($expected !== $actual) {
		throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
	}
}

function assertTrueValue(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function postFixture(int $parent, string $message, string $fileHash = '', int $moderated = 1): array {
	return array(
		'parent' => $parent,
		'name' => 'Anonymous',
		'tripcode' => '',
		'email' => '',
		'nameblock' => 'Anonymous',
		'subject' => '',
		'message' => $message,
		'password' => 'password-hash',
		'file' => $fileHash === '' ? '' : 'image.png',
		'file_hex' => $fileHash,
		'file_original' => $fileHash === '' ? '' : 'image.png',
		'file_size' => $fileHash === '' ? 0 : 1234,
		'file_size_formatted' => $fileHash === '' ? '' : '1.21 KB',
		'image_width' => $fileHash === '' ? 0 : 640,
		'image_height' => $fileHash === '' ? 0 : 480,
		'thumb' => $fileHash === '' ? '' : 'thumb.png',
		'thumb_width' => $fileHash === '' ? 0 : 200,
		'thumb_height' => $fileHash === '' ? 0 : 150,
		'stickied' => 0,
		'locked' => 0,
		'moderated' => $moderated
	);
}

function removeTestDirectory(string $directory): void {
	if (!is_dir($directory)) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($iterator as $item) {
		$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
	}
	rmdir($directory);
}

$testDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tinyib-flatfile-' . bin2hex(random_bytes(6));

try {
	$db = new FlatFileDatabase($testDirectory);
	assertTrueValue(is_file($testDirectory . '/.htaccess'), 'Web-server protection was not created.');
	assertTrueValue(is_file($testDirectory . '/accounts.php'), 'Accounts document was not created.');

	$accountId = $db->insertRecord('accounts', array('username' => 'admin', 'password' => 'hash', 'role' => 1, 'lastactive' => 0));
	assertSameValue(1, $accountId, 'First account ID should be 1.');
	$account = $db->findRecord('accounts', static fn(array $record): bool => $record['username'] === 'admin');
	assertSameValue('admin', $account['username'], 'Account lookup failed.');
	$account['lastactive'] = 123;
	$db->updateRecord('accounts', $accountId, $account);
	assertSameValue(123, $db->records('accounts')[0]['lastactive'], 'Account update failed.');

	$threadId = $db->insertPost(postFixture(0, 'Opening post', 'hash-a'));
	$replyId = $db->insertPost(postFixture($threadId, 'Reply'));
	$secondThreadId = $db->insertPost(postFixture(0, 'Second thread', '', 0));
	assertSameValue(1, $threadId, 'First post ID should be 1.');
	assertSameValue(2, $replyId, 'Reply ID should be 2.');
	assertSameValue(3, $secondThreadId, 'Second thread ID should be 3.');
	assertSameValue(2, $db->countThreads(), 'Thread count is incorrect.');
	assertTrueValue($db->threadExists($threadId), 'Opening thread was not indexed.');
	assertSameValue(2, count($db->postsInThread($threadId, false)), 'Thread should contain OP and reply.');
	assertSameValue($threadId, $db->postsByFileHash('hash-a')[0]['id'], 'File-hash index lookup failed.');
	assertSameValue(1, count($db->allThreads(true)), 'Moderated thread filtering failed.');
	assertSameValue($secondThreadId, $db->latestPosts(false)[0]['id'], 'Unmoderated recent-post lookup failed.');

	$db->updatePost($threadId, static function (array $post): array {
		$post['message'] = 'Edited opening post';
		$post['stickied'] = 1;
		return $post;
	});
	assertSameValue('Edited opening post', $db->postById($threadId)['message'], 'Post update failed.');
	assertSameValue(1, $db->allThreads(false)[0]['stickied'], 'Thread summary was not updated.');

	$db->deletePost($replyId);
	assertSameValue(array(), $db->postById($replyId), 'Deleted reply remains indexed.');
	assertSameValue(1, count($db->postsInThread($threadId, false)), 'Reply was not removed from its thread.');

	$guard = "<?php http_response_code(404); exit; ?>\n";
	$db->insertRecord('reports', array('ip' => 'legacy-address-hash', 'post' => $threadId));
	$db->insertRecord('keywords', array('text' => 'legacy', 'action' => 'ban1d'));
	$legacyBans = array('version' => 1, 'next_id' => 2, 'records' => array('1' => array('id' => 1, 'ip' => 'legacy-address-hash')));
	file_put_contents($testDirectory . '/bans.php', $guard . json_encode($legacyBans, JSON_PRETTY_PRINT) . "\n");

	$threadPath = $testDirectory . '/threads/' . $threadId . '.php';
	$threadDocument = json_decode(substr(file_get_contents($threadPath), strlen($guard)), true, 512, JSON_THROW_ON_ERROR);
	$threadDocument['posts'][(string)$threadId]['ip'] = 'legacy-address-hash';
	file_put_contents($threadPath, $guard . json_encode($threadDocument, JSON_PRETTY_PRINT) . "\n");

	$emptyIndex = array('version' => 1, 'next_id' => 1, 'threads' => array(), 'post_threads' => array(), 'file_posts' => array(), 'last_post_by_ip' => array('legacy-address-hash' => $threadId));
	file_put_contents($testDirectory . '/posts.php', $guard . json_encode($emptyIndex, JSON_PRETTY_PRINT) . "\n");
	file_put_contents($testDirectory . '/.recover', 'test');
	$db = new FlatFileDatabase($testDirectory);
	assertSameValue(2, $db->countThreads(), 'Recovery did not rebuild the thread index.');
	assertSameValue('Edited opening post', $db->postById($threadId)['message'], 'Recovery did not rebuild the post map.');
	assertTrueValue(!array_key_exists('ip', $db->postById($threadId)), 'Legacy address data was not removed from a post.');
	assertTrueValue(!array_key_exists('ip', $db->records('reports')[0]), 'Legacy address data was not removed from a report.');
	assertSameValue('delete', $db->records('keywords')[0]['action'], 'Legacy ban keyword action was not converted to delete.');
	assertTrueValue(!is_file($testDirectory . '/bans.php'), 'Legacy ban data was not removed.');
	$rebuiltIndex = json_decode(substr(file_get_contents($testDirectory . '/posts.php'), strlen($guard)), true, 512, JSON_THROW_ON_ERROR);
	assertTrueValue(!array_key_exists('last_post_by_ip', $rebuiltIndex), 'Legacy address index was not removed.');

	$db->deleteRecords('accounts', static fn(array $record): bool => (int)$record['id'] === $accountId);
	assertSameValue(array(), $db->records('accounts'), 'Collection deletion failed.');

	echo "FlatFileDatabase tests passed.\n";
} finally {
	removeTestDirectory($testDirectory);
}
