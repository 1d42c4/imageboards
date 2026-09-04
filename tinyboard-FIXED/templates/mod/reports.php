<?php
/** @var callable $e */
/** @var callable $url */
/** @var list<array<string,mixed>> $reports */
/** @var string $csrf_field */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>Report queue</h1>
	<div class="subtitle">[<a href="<?= $e($url('/mod')) ?>">Dashboard</a>] — reporters are not identified by IP</div>
</header>
<hr>
<?php if ($reports === []): ?>
	<p>No reports.</p>
<?php else: ?>
	<?php foreach ($reports as $r): ?>
		<div class="post reply">
			<p class="intro">
				Report #<?= (int) $r['id'] ?>
				— /<?= $e($r['board_uri']) ?>/ post
				<?php if ($r['body'] !== null): ?>
					<a href="<?= $e($url('/' . $r['board_uri'] . '/res/' . ($r['thread_id'] ?: $r['post_id']) . '#p' . $r['post_id'])) ?>">#<?= (int) $r['post_id'] ?></a>
				<?php else: ?>
					#<?= (int) $r['post_id'] ?> <em>(deleted)</em>
				<?php endif; ?>
				— <?= $e(gmdate('Y-m-d H:i', (int) $r['time'])) ?> UTC
			</p>
			<p><strong>Reason:</strong> <?= $e($r['reason']) ?></p>
			<?php if ($r['body'] !== null): ?>
				<div class="body"><?= $e(mb_substr((string) $r['body'], 0, 300)) ?></div>
			<?php endif; ?>
			<form class="inline" method="post" action="<?= $e($url('/mod/reports')) ?>">
				<?= $csrf_field ?>
				<input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
				<input type="hidden" name="do" value="dismiss">
				<button type="submit">Dismiss</button>
			</form>
			<form class="inline" method="post" action="<?= $e($url('/mod/reports')) ?>">
				<?= $csrf_field ?>
				<input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
				<input type="hidden" name="do" value="dismiss_post">
				<button type="submit">Dismiss all for post</button>
			</form>
			<form class="inline" method="post" action="<?= $e($url('/mod/reports')) ?>" onsubmit="return confirm('Delete the post?');">
				<?= $csrf_field ?>
				<input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
				<input type="hidden" name="do" value="delete_post">
				<button type="submit">Delete post</button>
			</form>
		</div>
		<hr>
	<?php endforeach; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
