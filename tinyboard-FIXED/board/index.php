<?php
/** @var callable $e */
/** @var callable $url */
/** @var array<string,mixed> $board */
/** @var list<array<string,mixed>> $threads */
/** @var int $page */
/** @var int $pages */
/** @var string $csrf_field */
/** @var string $title */
/** @var mixed $mod */
/** @var \Newboard\Config $config */
$archived = false;

ob_start();
?>
<header>
	<h1>/<?= $e($board['uri']) ?>/ - <?= $e($board['title']) ?></h1>
	<?php if (!empty($board['subtitle'])): ?>
		<div class="subtitle"><?= $e($board['subtitle']) ?></div>
	<?php endif; ?>
</header>

<?php
$threadId = null;
require __DIR__ . '/../partials/post_form.php';
?>

<hr>
<p class="board-links">
	[<a href="<?= $e($url('/' . $board['uri'] . '/catalog')) ?>">Catalog</a>]
	[<a href="<?= $e($url('/' . $board['uri'] . '/archive')) ?>">Archive</a>]
	[<a href="<?= $e($url('/')) ?>">Home</a>]
	<?php if (!empty($mod)): ?>
		[<a href="<?= $e($url('/mod')) ?>">Dashboard</a>]
		[<a href="<?= $e($url('/mod/edit/' . $board['uri'])) ?>">Edit board</a>]
	<?php endif; ?>
</p>
<hr>

<?php foreach ($threads as $block): ?>
	<?php
	$post = $block['op'];
	$isOp = true;
	require __DIR__ . '/../partials/post.php';
	?>
	<?php if ($block['omitted'] > 0): ?>
		<span class="omitted">
			<?= (int) $block['omitted'] ?> post(s) omitted.
			<a href="<?= $e($url('/' . $board['uri'] . '/res/' . $block['op']['id'])) ?>">Click here</a> to view.
		</span>
	<?php endif; ?>
	<?php foreach ($block['replies'] as $post): ?>
		<?php $isOp = false; require __DIR__ . '/../partials/post.php'; ?>
	<?php endforeach; ?>
	<br class="clear">
	<hr>
<?php endforeach; ?>

<div class="pages">
	<?php for ($i = 1; $i <= $pages; $i++): ?>
		[<a class="<?= $i === $page ? 'selected' : '' ?>" href="<?= $e($url('/' . $board['uri'] . '?page=' . $i)) ?>"><?= $i ?></a>]
	<?php endfor; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
