<?php
declare(strict_types=1);

namespace TinyIB\Storage;

use JsonException;
use RuntimeException;
use Throwable;

final class FlatFileDatabase {
	private const FORMAT_VERSION = 1;
	private const PHP_GUARD = "<?php http_response_code(404); exit; ?>\n";
	private const COLLECTIONS = array('accounts', 'keywords', 'logs', 'reports');

	private string $dataDirectory;
	private string $threadDirectory;
	private string $postIndexPath;
	private string $writeLockPath;
	private string $recoveryMarkerPath;
	private array $cache = array();

	public function __construct(string $dataDirectory) {
		$dataDirectory = rtrim($dataDirectory, "/\\");
		if ($dataDirectory === '') {
			throw new RuntimeException('The flat-file data directory cannot be empty.');
		}

		$this->dataDirectory = $dataDirectory;
		$this->threadDirectory = $dataDirectory . DIRECTORY_SEPARATOR . 'threads';
		$this->postIndexPath = $dataDirectory . DIRECTORY_SEPARATOR . 'posts.php';
		$this->writeLockPath = $dataDirectory . DIRECTORY_SEPARATOR . '.write.lock';
		$this->recoveryMarkerPath = $dataDirectory . DIRECTORY_SEPARATOR . '.recover';

		$this->ensureDirectory($this->dataDirectory);
		$this->ensureDirectory($this->threadDirectory);
		$this->installWebProtection($this->dataDirectory);
		$this->installWebProtection($this->threadDirectory);

		$this->withExclusiveLock(function (): void {
			$this->cache = array();
			foreach (self::COLLECTIONS as $collection) {
				$path = $this->collectionPath($collection);
				if (!is_file($path)) {
					$this->writeDocument($path, $this->emptyCollection());
				}
			}

			$purgedLegacyData = $this->purgeLegacyNetworkDataLocked();
			$legacyPostIndex = false;
			if (is_file($this->postIndexPath)) {
				$legacyPostIndex = array_key_exists('last_post_by_ip', $this->readPostIndex());
			}

			if (!is_file($this->postIndexPath) || is_file($this->recoveryMarkerPath) || $purgedLegacyData || $legacyPostIndex) {
				$this->rebuildPostIndexLocked();
				$this->clearRecoveryMarker();
			}
		});
	}

	public function findRecord(string $collection, callable $predicate): array {
		foreach ($this->records($collection) as $record) {
			if ($predicate($record)) {
				return $record;
			}
		}
		return array();
	}

	public function records(string $collection): array {
		$document = $this->readCollection($collection);
		return array_values($document['records']);
	}

	public function insertRecord(string $collection, array $record): int {
		return $this->withExclusiveLock(function () use ($collection, $record): int {
			$this->cache = array();
			$document = $this->readCollection($collection);
			$id = max(1, (int)$document['next_id']);
			$document['next_id'] = $id + 1;
			$record['id'] = $id;
			$document['records'][(string)$id] = $record;
			$this->writeDocument($this->collectionPath($collection), $document);
			return $id;
		});
	}

	public function updateRecord(string $collection, int $id, array $record): void {
		$this->withExclusiveLock(function () use ($collection, $id, $record): void {
			$this->cache = array();
			$document = $this->readCollection($collection);
			$key = (string)$id;
			if (!isset($document['records'][$key])) {
				return;
			}
			$record['id'] = $id;
			if ($document['records'][$key] === $record) {
				return;
			}
			$document['records'][$key] = $record;
			$this->writeDocument($this->collectionPath($collection), $document);
		});
	}

	public function deleteRecords(string $collection, callable $predicate): int {
		return $this->withExclusiveLock(function () use ($collection, $predicate): int {
			$this->cache = array();
			$document = $this->readCollection($collection);
			$deleted = 0;
			foreach ($document['records'] as $id => $record) {
				if ($predicate($record)) {
					unset($document['records'][$id]);
					$deleted++;
				}
			}
			if ($deleted > 0) {
				$this->writeDocument($this->collectionPath($collection), $document);
			}
			return $deleted;
		});
	}

	public function insertPost(array $post): int {
		return $this->mutatePosts(function () use ($post): int {
			$index = $this->readPostIndex();
			$id = max(1, (int)$index['next_id']);
			$parent = (int)($post['parent'] ?? 0);
			$threadId = $parent === 0 ? $id : $parent;
			$threadPath = $this->threadPath($threadId);

			if ($parent !== 0 && !is_file($threadPath)) {
				throw new RuntimeException('Cannot add a reply to a thread that does not exist.');
			}

			$timestamp = time();
			$post['id'] = $id;
			$post['parent'] = $parent;
			$post['timestamp'] = $timestamp;
			$post['bumped'] = $timestamp;
			$post = $this->normalizePost($post);

			$thread = $parent === 0
				? $this->emptyThread($threadId)
				: $this->readThread($threadId);
			$thread['posts'][(string)$id] = $post;
			$this->sortPostMap($thread['posts']);

			// Thread files are canonical. The compact index can always be rebuilt from them.
			$this->writeDocument($threadPath, $thread);

			$index['next_id'] = $id + 1;
			$index['post_threads'][(string)$id] = $threadId;
			if ($parent === 0) {
				$index['threads'][(string)$threadId] = $post;
			}
			if ($post['file_hex'] !== '') {
				$index['file_posts'][$post['file_hex']] ??= array();
				$index['file_posts'][$post['file_hex']][] = $id;
			}
			$this->writeDocument($this->postIndexPath, $index);
			return $id;
		});
	}

	public function postById(int $id): array {
		if ($id <= 0) {
			return array();
		}
		$index = $this->readPostIndex();
		$threadId = (int)($index['post_threads'][(string)$id] ?? 0);
		if ($threadId <= 0) {
			return array();
		}
		$thread = $this->readThread($threadId);
		return $thread['posts'][(string)$id] ?? array();
	}

	public function threadExists(int $id): bool {
		if ($id <= 0) {
			return false;
		}
		$index = $this->readPostIndex();
		return isset($index['threads'][(string)$id]);
	}

	public function updatePost(int $id, callable $mutator): void {
		$this->mutatePosts(function () use ($id, $mutator): void {
			$index = $this->readPostIndex();
			$threadId = (int)($index['post_threads'][(string)$id] ?? 0);
			if ($threadId <= 0) {
				return;
			}

			$thread = $this->readThread($threadId);
			$key = (string)$id;
			if (!isset($thread['posts'][$key])) {
				return;
			}

			$original = $thread['posts'][$key];
			$updated = $this->normalizePost($mutator($original));
			$updated['id'] = $original['id'];
			$updated['parent'] = $original['parent'];
			$updated['timestamp'] = $original['timestamp'];
			if ($updated === $original) {
				return;
			}

			$thread['posts'][$key] = $updated;
			$this->writeDocument($this->threadPath($threadId), $thread);
			if ($id === $threadId) {
				$index['threads'][(string)$threadId] = $updated;
				$this->writeDocument($this->postIndexPath, $index);
			}
		});
	}

	public function deletePost(int $id): void {
		$this->mutatePosts(function () use ($id): void {
			$index = $this->readPostIndex();
			$threadId = (int)($index['post_threads'][(string)$id] ?? 0);
			if ($threadId <= 0) {
				return;
			}

			$threadPath = $this->threadPath($threadId);
			$thread = $this->readThread($threadId);
			$key = (string)$id;
			if (!isset($thread['posts'][$key])) {
				return;
			}

			$removedPosts = $id === $threadId ? $thread['posts'] : array($key => $thread['posts'][$key]);
			if ($id === $threadId) {
				if (is_file($threadPath) && !unlink($threadPath)) {
					throw new RuntimeException('Unable to remove thread data file.');
				}
				unset($this->cache[$threadPath], $index['threads'][(string)$threadId]);
			} else {
				unset($thread['posts'][$key]);
				$this->writeDocument($threadPath, $thread);
			}

			foreach ($removedPosts as $removedPost) {
				$removedId = (int)$removedPost['id'];
				unset($index['post_threads'][(string)$removedId]);

				$fileHash = (string)$removedPost['file_hex'];
				if ($fileHash !== '' && isset($index['file_posts'][$fileHash])) {
					$index['file_posts'][$fileHash] = array_values(array_filter(
						$index['file_posts'][$fileHash],
						static fn(int $postId): bool => $postId !== $removedId
					));
					if ($index['file_posts'][$fileHash] === array()) {
						unset($index['file_posts'][$fileHash]);
					}
				}
			}

			$this->writeDocument($this->postIndexPath, $index);
		});
	}
	public function countThreads(): int {
		return count($this->readPostIndex()['threads']);
	}

	public function allThreads(bool $moderatedOnly = true): array {
		$threads = array_values($this->readPostIndex()['threads']);
		if ($moderatedOnly) {
			$threads = array_values(array_filter($threads, static fn(array $post): bool => (int)$post['moderated'] > 0));
		}
		usort($threads, static function (array $left, array $right): int {
			$sticky = (int)$right['stickied'] <=> (int)$left['stickied'];
			if ($sticky !== 0) {
				return $sticky;
			}
			$bumped = (int)$right['bumped'] <=> (int)$left['bumped'];
			return $bumped !== 0 ? $bumped : ((int)$right['id'] <=> (int)$left['id']);
		});
		return $threads;
	}

	public function postsInThread(int $threadId, bool $moderatedOnly = true): array {
		if ($threadId <= 0 || !is_file($this->threadPath($threadId))) {
			return array();
		}
		$posts = array_values($this->readThread($threadId)['posts']);
		if ($moderatedOnly) {
			$posts = array_values(array_filter($posts, static fn(array $post): bool => (int)$post['moderated'] > 0));
		}
		usort($posts, static fn(array $left, array $right): int => (int)$left['id'] <=> (int)$right['id']);
		return $posts;
	}

	public function postsByFileHash(string $hash, int $limit = 1): array {
		$ids = $this->readPostIndex()['file_posts'][$hash] ?? array();
		$posts = array();
		foreach ($ids as $id) {
			$post = $this->postById((int)$id);
			if ($post !== array()) {
				$posts[] = $post;
				if ($limit > 0 && count($posts) >= $limit) {
					break;
				}
			}
		}
		return $posts;
	}

	public function latestPosts(bool $moderated, int $limit = 10): array {
		$ids = array_map('intval', array_keys($this->readPostIndex()['post_threads']));
		rsort($ids, SORT_NUMERIC);
		$posts = array();
		foreach ($ids as $id) {
			$post = $this->postById($id);
			$isModerated = (int)($post['moderated'] ?? 0) > 0;
			if ($post !== array() && $isModerated === $moderated) {
				$posts[] = $post;
				if (count($posts) >= $limit) {
					break;
				}
			}
		}
		return $posts;
	}

	private function readCollection(string $collection): array {
		$this->assertCollection($collection);
		return $this->readDocument($this->collectionPath($collection), $this->emptyCollection());
	}

	private function collectionPath(string $collection): string {
		$this->assertCollection($collection);
		return $this->dataDirectory . DIRECTORY_SEPARATOR . $collection . '.php';
	}

	private function assertCollection(string $collection): void {
		if (!in_array($collection, self::COLLECTIONS, true)) {
			throw new RuntimeException('Unknown flat-file collection: ' . $collection);
		}
	}

	private function threadPath(int $threadId): string {
		if ($threadId <= 0) {
			throw new RuntimeException('Thread identifiers must be positive integers.');
		}
		return $this->threadDirectory . DIRECTORY_SEPARATOR . $threadId . '.php';
	}

	private function readThread(int $threadId): array {
		return $this->readDocument($this->threadPath($threadId), $this->emptyThread($threadId));
	}

	private function readPostIndex(): array {
		return $this->readDocument($this->postIndexPath, $this->emptyPostIndex());
	}

	private function mutatePosts(callable $mutation): mixed {
		return $this->withExclusiveLock(function () use ($mutation): mixed {
			$this->cache = array();
			$this->writeRecoveryMarker();
			try {
				$result = $mutation();
				$this->clearRecoveryMarker();
				return $result;
			} catch (Throwable $error) {
				// Leave the marker in place so the next request rebuilds derived indexes.
				throw $error;
			}
		});
	}

	private function purgeLegacyNetworkDataLocked(): bool {
		$changed = false;
		$legacyBansPath = $this->dataDirectory . DIRECTORY_SEPARATOR . 'bans.php';
		if (is_file($legacyBansPath)) {
			if (!unlink($legacyBansPath)) {
				throw new RuntimeException('Unable to remove legacy ban data.');
			}
			unset($this->cache[$legacyBansPath]);
			$changed = true;
		}

		$reports = $this->readCollection('reports');
		$reportsChanged = false;
		foreach ($reports['records'] as &$report) {
			if (array_key_exists('ip', $report)) {
				unset($report['ip']);
				$reportsChanged = true;
			}
		}
		unset($report);
		if ($reportsChanged) {
			$this->writeDocument($this->collectionPath('reports'), $reports);
			$changed = true;
		}

		$keywords = $this->readCollection('keywords');
		$keywordsChanged = false;
		foreach ($keywords['records'] as &$keyword) {
			if (str_starts_with((string)($keyword['action'] ?? ''), 'ban')) {
				$keyword['action'] = 'delete';
				$keywordsChanged = true;
			}
		}
		unset($keyword);
		if ($keywordsChanged) {
			$this->writeDocument($this->collectionPath('keywords'), $keywords);
			$changed = true;
		}

		$files = scandir($this->threadDirectory);
		if ($files === false) {
			throw new RuntimeException('Unable to scan flat-file thread directory.');
		}
		foreach ($files as $file) {
			if (!preg_match('/^([1-9][0-9]*)\.php$/', $file, $matches)) {
				continue;
			}
			$threadId = (int)$matches[1];
			$path = $this->threadPath($threadId);
			$thread = $this->readDocument($path, $this->emptyThread($threadId));
			$threadChanged = false;
			foreach ($thread['posts'] as $postId => $post) {
				$normalized = $this->normalizePost($post);
				if ($normalized !== $post) {
					$thread['posts'][$postId] = $normalized;
					$threadChanged = true;
				}
			}
			if ($threadChanged) {
				$this->writeDocument($path, $thread);
				$changed = true;
			}
		}

		return $changed;
	}

	private function rebuildPostIndexLocked(): void {
		$index = $this->emptyPostIndex();
		$maxPostId = 0;
		$files = scandir($this->threadDirectory);
		if ($files === false) {
			throw new RuntimeException('Unable to scan flat-file thread directory.');
		}

		foreach ($files as $file) {
			if (!preg_match('/^([1-9][0-9]*)\.php$/', $file, $matches)) {
				continue;
			}
			$threadId = (int)$matches[1];
			$thread = $this->readDocument($this->threadPath($threadId), $this->emptyThread($threadId));
			$posts = $thread['posts'] ?? array();
			$op = $posts[(string)$threadId] ?? null;
			if (!is_array($op) || (int)($op['parent'] ?? -1) !== 0) {
				throw new RuntimeException('Thread ' . $threadId . ' does not contain a valid opening post.');
			}
			$index['threads'][(string)$threadId] = $this->normalizePost($op);

			foreach ($posts as $postId => $post) {
				if (!is_array($post)) {
					throw new RuntimeException('Thread ' . $threadId . ' contains an invalid post record.');
				}
				$post = $this->normalizePost($post);
				$id = (int)$post['id'];
				$maxPostId = max($maxPostId, $id);
				$index['post_threads'][(string)$id] = $threadId;
				if ($post['file_hex'] !== '') {
					$index['file_posts'][$post['file_hex']] ??= array();
					$index['file_posts'][$post['file_hex']][] = $id;
				}
			}
		}

		$index['next_id'] = $maxPostId + 1;
		$this->sortPostMap($index['post_threads']);
		ksort($index['threads'], SORT_NUMERIC);
		foreach ($index['file_posts'] as &$postIds) {
			sort($postIds, SORT_NUMERIC);
		}
		unset($postIds);
		$this->writeDocument($this->postIndexPath, $index);
	}

	private function normalizePost(array $post): array {
		$stringFields = array('name', 'tripcode', 'email', 'nameblock', 'subject', 'message', 'password', 'file', 'file_hex', 'file_original', 'file_size_formatted', 'thumb');
		$integerFields = array('id', 'parent', 'timestamp', 'bumped', 'file_size', 'image_width', 'image_height', 'thumb_width', 'thumb_height', 'stickied', 'locked', 'moderated');
		$normalized = array();
		foreach ($integerFields as $field) {
			$normalized[$field] = (int)($post[$field] ?? 0);
		}
		foreach ($stringFields as $field) {
			$normalized[$field] = (string)($post[$field] ?? '');
		}
		return $normalized;
	}

	private function emptyCollection(): array {
		return array('version' => self::FORMAT_VERSION, 'next_id' => 1, 'records' => array());
	}

	private function emptyThread(int $threadId): array {
		return array('version' => self::FORMAT_VERSION, 'thread_id' => $threadId, 'posts' => array());
	}

	private function emptyPostIndex(): array {
		return array(
			'version' => self::FORMAT_VERSION,
			'next_id' => 1,
			'threads' => array(),
			'post_threads' => array(),
			'file_posts' => array()
		);
	}

	private function sortPostMap(array &$posts): void {
		ksort($posts, SORT_NUMERIC);
	}

	private function readDocument(string $path, array $default): array {
		if (isset($this->cache[$path])) {
			return $this->cache[$path];
		}
		if (!is_file($path)) {
			return $default;
		}

		$contents = file_get_contents($path);
		if ($contents === false || !str_starts_with($contents, self::PHP_GUARD)) {
			throw new RuntimeException('Invalid flat-file document: ' . basename($path));
		}
		$json = substr($contents, strlen(self::PHP_GUARD));
		try {
			$document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $error) {
			throw new RuntimeException('Corrupt flat-file document: ' . basename($path), 0, $error);
		}
		if (!is_array($document) || (int)($document['version'] ?? 0) !== self::FORMAT_VERSION) {
			throw new RuntimeException('Unsupported flat-file format in ' . basename($path));
		}
		$this->cache[$path] = $document;
		return $document;
	}

	private function writeDocument(string $path, array $document): void {
		try {
			$json = json_encode(
				$document,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
			);
		} catch (JsonException $error) {
			throw new RuntimeException('Unable to encode flat-file document: ' . basename($path), 0, $error);
		}

		$tempPath = $path . '.' . bin2hex(random_bytes(8)) . '.tmp.php';
		$handle = fopen($tempPath, 'xb');
		if ($handle === false) {
			throw new RuntimeException('Unable to create temporary flat-file document.');
		}

		try {
			$contents = self::PHP_GUARD . $json . "\n";
			$offset = 0;
			$length = strlen($contents);
			while ($offset < $length) {
				$written = fwrite($handle, substr($contents, $offset));
				if ($written === false || $written === 0) {
					throw new RuntimeException('Unable to write temporary flat-file document.');
				}
				$offset += $written;
			}
			if (!fflush($handle)) {
				throw new RuntimeException('Unable to flush temporary flat-file document.');
			}
			if (function_exists('fsync')) {
				@fsync($handle);
			}
		} catch (Throwable $error) {
			fclose($handle);
			@unlink($tempPath);
			throw $error;
		}
		fclose($handle);

		if (!rename($tempPath, $path)) {
			@unlink($tempPath);
			throw new RuntimeException('Unable to atomically replace flat-file document: ' . basename($path));
		}
		@chmod($path, 0660);
		$this->cache[$path] = $document;
	}

	private function withExclusiveLock(callable $operation): mixed {
		$handle = fopen($this->writeLockPath, 'c+b');
		if ($handle === false) {
			throw new RuntimeException('Unable to open the flat-file write lock.');
		}
		if (!flock($handle, LOCK_EX)) {
			fclose($handle);
			throw new RuntimeException('Unable to acquire the flat-file write lock.');
		}

		try {
			return $operation();
		} finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	private function writeRecoveryMarker(): void {
		if (file_put_contents($this->recoveryMarkerPath, (string)time(), LOCK_EX) === false) {
			throw new RuntimeException('Unable to create flat-file recovery marker.');
		}
	}

	private function clearRecoveryMarker(): void {
		if (is_file($this->recoveryMarkerPath) && !unlink($this->recoveryMarkerPath)) {
			throw new RuntimeException('Unable to clear flat-file recovery marker.');
		}
	}

	private function ensureDirectory(string $path): void {
		if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
			throw new RuntimeException('Unable to create flat-file directory: ' . $path);
		}
		if (!is_writable($path)) {
			throw new RuntimeException('Flat-file directory is not writable: ' . $path);
		}
	}

	private function installWebProtection(string $directory): void {
		$indexPath = $directory . DIRECTORY_SEPARATOR . 'index.php';
		if (!is_file($indexPath)) {
			file_put_contents($indexPath, "<?php http_response_code(404); exit;\n", LOCK_EX);
		}

		$htaccessPath = $directory . DIRECTORY_SEPARATOR . '.htaccess';
		if (!is_file($htaccessPath)) {
			$rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
			file_put_contents($htaccessPath, $rules, LOCK_EX);
		}
	}
}
