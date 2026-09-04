<?php
/**
 * vichanBEST1-like post markup for style.css
 * @var callable $e
 * @var callable $url
 * @var array<string,mixed> $post
 * @var array<string,mixed> $board
 * @var bool $isOp
 * @var bool|null $archived
 */
$id = (int) $post['id'];
$threadId = (int) ($post['thread_id'] ?? $id);
$cls = $isOp ? 'post op' : 'post reply';
$name = (string) ($post['name'] ?? '');
if ($name === '') {
    $name = 'Anonymous';
}
$time = (int) ($post['time'] ?? 0);
$when = gmdate('m/d/y (D) H:i:s', $time);
$resBase = !empty($archived)
    ? $url('/' . $board['uri'] . '/archive/' . $threadId)
    : $url('/' . $board['uri'] . '/res/' . $threadId);
?>
<div class="<?= $e($cls) ?>" id="p<?= $id ?>">
	<p class="intro">
		<input type="checkbox" class="delete" name="delete_<?= $id ?>" value="1" disabled title="Use mod tools">
		<?php if (!empty($post['subject'])): ?>
			<span class="subject"><?= $e($post['subject']) ?></span>
		<?php endif; ?>
		<span class="name"><?= $e($name) ?></span><?php if (!empty($post['trip'])): ?><span class="trip"><?= $e($post['trip']) ?></span><?php endif; ?>
		<?php if (!empty($post['capcode'])): ?><span class="capcode"> ## <?= $e($post['capcode']) ?></span><?php endif; ?>
		<time datetime="<?= $e(gmdate('c', $time)) ?>"><?= $e($when) ?></time>
		<a class="post_no" href="<?= $e($resBase) ?>#p<?= $id ?>">No.</a><a class="post_no" href="<?= $e($resBase) ?>#p<?= $id ?>"><?= $id ?></a>
		<?php if ($isOp && empty($archived)): ?>
			[<a href="<?= $e($resBase) ?>">Reply</a>]
		<?php endif; ?>
		<?php if ($isOp && !empty($post['sticky'])): ?> <img src="<?= $e($url('/stylesheets/img/arrow.png')) ?>" alt="Sticky" title="Sticky" class="icon"><?php endif; ?>
		<?php if ($isOp && !empty($post['locked'])): ?> <span class="locked" title="Locked">🔒</span><?php endif; ?>
		<?php if ($isOp && !empty($post['bumplock'])): ?> <span class="bumplock" title="Bumplocked">🔕</span><?php endif; ?>
		<?php if (empty($archived)): ?>
			[<a href="<?= $e($url('/report/' . $board['uri'] . '/' . $id)) ?>">Report</a>]
		<?php endif; ?>
		<?php require __DIR__ . '/mod_controls.php'; ?>
	</p>
	<?php if (!empty($post['file_path'])): ?>
		<div class="files">
			<div class="file">
				<p class="fileinfo">File: <a href="<?= $e($url('/uploads/' . $post['file_path'])) ?>" target="_blank" rel="noopener"><?= $e($post['file_orig'] ?? 'image') ?></a>
					<span class="unimportant">(<?= $e(number_format((int) ($post['file_size'] ?? 0))) ?> B,
					<?= (int) ($post['file_width'] ?? 0) ?>x<?= (int) ($post['file_height'] ?? 0) ?>)</span>
				</p>
				<a class="file-link" href="<?= $e($url('/uploads/' . $post['file_path'])) ?>" target="_blank" rel="noopener">
					<img class="post-image" src="<?= $e($url('/uploads/' . ($post['thumb_path'] ?? $post['file_path']))) ?>"
						width="<?= (int) ($post['thumb_width'] ?? 0) ?>"
						height="<?= (int) ($post['thumb_height'] ?? 0) ?>"
						alt="">
				</a>
			</div>
		</div>
	<?php endif; ?>
	<div class="body">
		<?= $post['body_html'] ?>
	</div>
</div>
