<?php
/** @var callable $e */
/** @var callable $url */
/** @var array<string,mixed> $board */
/** @var list<array<string,mixed>> $threads */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>/<?= $e($board['uri']) ?>/ - Catalog</h1>
	<div class="subtitle">[<a href="<?= $e($url('/' . $board['uri'])) ?>">Return</a>]</div>
</header>
<hr>
<div class="catalog">
<?php foreach ($threads as $t): ?>
	<div class="catalog-thread">
		<a href="<?= $e($url('/' . $board['uri'] . '/res/' . $t['id'])) ?>">
			<?php if (!empty($t['thumb_path'])): ?>
				<img src="<?= $e($url('/uploads/' . $t['thumb_path'])) ?>" alt=""
					width="<?= (int) ($t['thumb_width'] ?? 150) ?>"
					height="<?= (int) ($t['thumb_height'] ?? 150) ?>">
			<?php else: ?>
				<div class="no-image">No image</div>
			<?php endif; ?>
		</a>
		<div class="meta">R: <?= (int) ($t['reply_count'] ?? 0) ?></div>
		<div class="teaser">
			<?php if (!empty($t['subject'])): ?><strong><?= $e($t['subject']) ?></strong> <?php endif; ?>
			<?= $e(mb_substr(strip_tags((string) $t['body']), 0, 120)) ?>
		</div>
	</div>
<?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
