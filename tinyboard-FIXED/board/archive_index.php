<?php
/** @var callable $e */
/** @var callable $url */
/** @var array<string,mixed> $board */
/** @var list<array<string,mixed>> $threads */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>/<?= $e($board['uri']) ?>/ - Archive</h1>
	<div class="subtitle">
		Read-only threads that have left the board
		— <a href="<?= $e($url('/' . $board['uri'])) ?>">[Back to board]</a>
		<a href="<?= $e($url('/' . $board['uri'] . '/catalog')) ?>">[Catalog]</a>
	</div>
</header>

<div class="banner archive-banner">
	Archive<?php if (!empty($total)): ?> — <?= (int) $total ?> thread<?= $total === 1 ? '' : 's' ?><?php endif; ?>
</div>

<?php if ($threads === []): ?>
	<p class="archive-empty">No archived threads yet. When threads fall off the board (past max pages) or a mod archives them, they appear here read-only. Images stay on disk.</p>
<?php else: ?>
	<ul class="archive-list">
	<?php foreach ($threads as $t): ?>
		<li>
			<?php if (!empty($t['thumb_path'])): ?>
				<a class="athumb" href="<?= $e($url('/' . $board['uri'] . '/archive/' . $t['id'])) ?>">
					<img src="<?= $e($url('/uploads/' . $t['thumb_path'])) ?>" alt="">
				</a>
			<?php endif; ?>
			<div>
				<a href="<?= $e($url('/' . $board['uri'] . '/archive/' . $t['id'])) ?>">
					<strong><?php if (!empty($t['subject'])): ?><?= $e($t['subject']) ?><?php else: ?>No.<?= (int) $t['id'] ?><?php endif; ?></strong>
				</a>
				<span class="ameta">
					— <?= $e(($t['name'] ?? '') !== '' ? $t['name'] : 'Anonymous') ?>
					— No.<?= (int) $t['id'] ?>
					— <?= (int) ($t['reply_count'] ?? 0) ?> replies
					— posted <time datetime="<?= $e(gmdate('c', (int) $t['time'])) ?>"><?= $e(gmdate('Y-m-d H:i', (int) $t['time'])) ?></time>
					<?php if (!empty($t['archived_at'])): ?>
						— archived <time datetime="<?= $e(gmdate('c', (int) $t['archived_at'])) ?>"><?= $e(gmdate('Y-m-d H:i', (int) $t['archived_at'])) ?></time>
					<?php endif; ?>
				</span>
				<div class="asnippet"><?= $e($t['snippet'] ?? '') ?></div>
			</div>
		</li>
	<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php if ($pages > 1): ?>
<div class="pages">
	<?php for ($i = 1; $i <= $pages; $i++): ?>
		[<a class="<?= $i === $page ? 'selected' : '' ?>" href="<?= $e($url('/' . $board['uri'] . '/archive?page=' . $i)) ?>"><?= $i ?></a>]
	<?php endfor; ?>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
