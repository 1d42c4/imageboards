<?php
/** @var callable $e */
/** @var callable $url */
/** @var list<array<string,mixed>> $posts */
/** @var string $csrf_field */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>Post approval queue</h1>
	<div class="subtitle">[<a href="<?= $e($url('/mod')) ?>">Dashboard</a>]</div>
</header>
<hr>
<?php if ($posts === []): ?>
	<p>No posts waiting for approval.</p>
<?php else: ?>
	<?php foreach ($posts as $p): ?>
		<div class="post reply pending-item">
			<p class="intro">
				<strong>/<?= $e($p['board_uri']) ?>/</strong>
				#<?= (int) $p['id'] ?>
				<?php if ($p['thread_id']): ?> (reply to <?= (int) $p['thread_id'] ?>)<?php else: ?> (new thread)<?php endif; ?>
				— <?= $e(gmdate('Y-m-d H:i', (int) $p['time'])) ?> UTC
				<span class="name"><?= $e($p['name'] !== '' ? $p['name'] : 'Anonymous') ?></span>
				<?php if (!empty($p['subject'])): ?><span class="subject"><?= $e($p['subject']) ?></span><?php endif; ?>
			</p>
			<?php if (!empty($p['thumb_path'])): ?>
				<img src="<?= $e($url('/uploads/' . $p['thumb_path'])) ?>" alt="" style="max-width:120px;float:left;margin:4px">
			<?php endif; ?>
			<div class="body"><?= $e(mb_substr((string) $p['body'], 0, 500)) ?></div>
			<br class="clear">
			<form class="inline" method="post" action="<?= $e($url('/mod/action')) ?>">
				<?= $csrf_field ?>
				<input type="hidden" name="do" value="approve">
				<input type="hidden" name="board" value="<?= $e($p['board_uri']) ?>">
				<input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
				<input type="hidden" name="back" value="<?= $e($url('/mod/pending')) ?>">
				<button type="submit">Approve</button>
			</form>
			<form class="inline" method="post" action="<?= $e($url('/mod/action')) ?>">
				<?= $csrf_field ?>
				<input type="hidden" name="do" value="reject">
				<input type="hidden" name="board" value="<?= $e($p['board_uri']) ?>">
				<input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
				<input type="hidden" name="back" value="<?= $e($url('/mod/pending')) ?>">
				<button type="submit">Reject</button>
			</form>
		</div>
		<hr>
	<?php endforeach; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
