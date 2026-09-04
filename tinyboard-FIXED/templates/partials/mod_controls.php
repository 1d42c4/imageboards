<?php
/**
 * Inline mod controls (shown when $mod is set).
 * @var callable $e
 * @var callable $url
 * @var array<string,mixed> $post
 * @var array<string,mixed> $board
 * @var string $csrf_field
 * @var bool $isOp
 * @var bool $archived
 */
if (empty($mod)) {
    return;
}
$archived = !empty($archived);
$pid = (int) $post['id'];
$threadForBack = (int) ($post['thread_id'] ?? $post['id']);
$back = $url('/' . $board['uri'] . '/res/' . $threadForBack);
if ($archived) {
    $back = $url('/' . $board['uri'] . '/archive/' . $threadForBack);
}
$action = $url('/mod/action');
?>
<span class="mod-controls">
	<form class="inline" method="post" action="<?= $e($action) ?>" onsubmit="return confirm('Delete post #<?= $pid ?>?');">
		<?= $csrf_field ?>
		<input type="hidden" name="do" value="delete">
		<input type="hidden" name="board" value="<?= $e($board['uri']) ?>">
		<input type="hidden" name="id" value="<?= $pid ?>">
		<input type="hidden" name="back" value="<?= $e($back) ?>">
		<button type="submit" title="Delete">[D]</button>
	</form>
	<a href="<?= $e($url('/mod/edit-post/' . $board['uri'] . '/' . $pid)) ?>" title="Edit">[E]</a>
	<?php if (!empty($post['file_path'])): ?>
	<form class="inline" method="post" action="<?= $e($action) ?>">
		<?= $csrf_field ?>
		<input type="hidden" name="do" value="deletefile">
		<input type="hidden" name="board" value="<?= $e($board['uri']) ?>">
		<input type="hidden" name="id" value="<?= $pid ?>">
		<input type="hidden" name="back" value="<?= $e($back) ?>">
		<button type="submit" title="Delete file">[F]</button>
	</form>
	<?php endif; ?>
	<?php if ($isOp && !$archived): ?>
		<?php if (empty($post['sticky'])): ?>
		<form class="inline" method="post" action="<?= $e($action) ?>">
			<?= $csrf_field ?>
			<input type="hidden" name="do" value="sticky">
			<input type="hidden" name="board" value="<?= $e($board['uri']) ?>">
			<input type="hidden" name="id" value="<?= $pid ?>">
			<input type="hidden" name="back" value="<?= $e($back) ?>">
			<button type="submit" title="Sticky">[S]</button>
		</form>
		<?php else: ?>
		<form class="inline" method="post" action="<?= $e($action) ?>">
			<?= $csrf_field ?>
			<input type="hidden" name="do" value="unsticky">
			<input type="hidden" name="board" value="<?= $e($board['uri']) ?>">
			<input type="hidden" name="id" value="<?= $pid ?>">
			<input type="hidden" name="back" value="<?= $e($back) ?>">
			<button type="submit" title="Unsticky">[US]</button>
		</form>
		<?php endif; ?>
		<?php if (empty($post['locked'])): ?>
		<form class="inline" method="post" action="<?= $e($action) ?>">
			<?= $csrf_field ?>
			<input type="hidden" name="do" value="lock">
			<input type="hidden" name="board" value="<?= $e($board['uri']) ?>">
			<input type="hidden" name="id" value="<?= $pid ?>">
			<input type="hidden" name="back" value="<?= $e($back) ?>">
			<button type="submit" title="Lock">[L]</button>
		</form>
		<?php else: ?>
		<form class="inline" method="post" action="<?= $e($action) ?>">
			<?= $csrf_field ?>
			<input type="hidden" name="do" value="unlock">
			<input type="hidden" name="board" value="<?= $e($board['uri']) ?>">
			<input type="hidden" name="id" value="<?= $pid ?>">
			<input type="hidden" name="back" value="<?= $e($back) ?>">
			<button type="submit" title="Unlock">[UL]</button>
		</form>
		<?php endif; ?>
		<?php if (empty($post['bumplock'])): ?>
		<form class="inline" method="post" action="<?= $e($action) ?>">
			<?= $csrf_field ?>
			<input type="hidden" name="do" value="bumplock">
			<input type="hidden" name="board" value="<?= $e($board['uri']) ?>">
			<input type="hidden" name="id" value="<?= $pid ?>">
			<input type="hidden" name="back" value="<?= $e($back) ?>">
			<button type="submit" title="Bumplock">[BL]</button>
		</form>
		<?php else: ?>
		<form class="inline" method="post" action="<?= $e($action) ?>">
			<?= $csrf_field ?>
			<input type="hidden" name="do" value="unbumplock">
			<input type="hidden" name="board" value="<?= $e($board['uri']) ?>">
			<input type="hidden" name="id" value="<?= $pid ?>">
			<input type="hidden" name="back" value="<?= $e($back) ?>">
			<button type="submit" title="Unbumplock">[UBL]</button>
		</form>
		<?php endif; ?>
		<form class="inline" method="post" action="<?= $e($action) ?>" onsubmit="return confirm('Archive this thread?');">
			<?= $csrf_field ?>
			<input type="hidden" name="do" value="archive">
			<input type="hidden" name="board" value="<?= $e($board['uri']) ?>">
			<input type="hidden" name="id" value="<?= $pid ?>">
			<input type="hidden" name="back" value="<?= $e($url('/' . $board['uri'] . '/archive/' . $pid)) ?>">
			<button type="submit" title="Archive">[A]</button>
		</form>
	<?php endif; ?>
</span>
