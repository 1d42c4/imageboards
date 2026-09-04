<?php
/** @var callable $e */
/** @var callable $url */
/** @var array<string,mixed> $board */
/** @var array<string,mixed> $op */
/** @var list<array<string,mixed>> $replies */
/** @var string $csrf_field */
/** @var string $title */
/** @var mixed $mod */
/** @var bool $archived */
/** @var \Newboard\Config $config */

$archived = !empty($archived);
ob_start();
?>
<header>
	<h1>/<?= $e($board['uri']) ?>/ - <?= $e($board['title']) ?><?= $archived ? ' (Archive)' : '' ?></h1>
	<div class="subtitle">
		[<a href="<?= $e($url('/' . $board['uri'])) ?>">Return</a>]
		[<a href="<?= $e($url('/' . $board['uri'] . '/catalog')) ?>">Catalog</a>]
		[<a href="<?= $e($url('/' . $board['uri'] . '/archive')) ?>">Archive</a>]
	</div>
</header>

<?php if ($archived): ?>
	<div class="banner archive-banner">Archived thread — read only</div>
	<p class="unimportant" style="text-align:center">
		[<a href="<?= $e($url('/' . $board['uri'] . '/archive')) ?>">Archive index</a>]
		[<a href="<?= $e($url('/' . $board['uri'])) ?>">Board</a>]
	</p>
<?php else: ?>
<?php
$threadId = (int) $op['id'];
require __DIR__ . '/../partials/post_form.php';
?>
<?php endif; ?>
<hr>

<?php
$post = $op;
$isOp = true;
require __DIR__ . '/../partials/post.php';
?>

<?php foreach ($replies as $post): ?>
	<?php $isOp = false; require __DIR__ . '/../partials/post.php'; ?>
<?php endforeach; ?>

<br class="clear">
<hr>
<p>[<a href="<?= $e($url('/' . $board['uri'])) ?>">Return</a>]</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
