<?php
/**
 * @var callable $e
 * @var callable $url
 * @var array<string,mixed> $board
 * @var string $csrf_field
 * @var int|null $threadId
 */
$threadId = $threadId ?? null;
?>
<form name="post" class="post-form" action="<?= $e($url('/post')) ?>" method="post" enctype="multipart/form-data">
	<?= $csrf_field ?>
	<input type="hidden" name="board" value="<?= $e($board['uri']) ?>">
	<?php if ($threadId): ?>
		<input type="hidden" name="thread" value="<?= (int) $threadId ?>">
	<?php endif; ?>
	<div class="hp" aria-hidden="true" style="position:absolute;left:-9999px;height:0;overflow:hidden">
		<label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
	</div>
	<table>
		<tr>
			<th>Name</th>
			<td><input type="text" name="name" size="25" maxlength="50" autocomplete="nickname" placeholder="Anonymous"></td>
		</tr>
		<tr>
			<th>Email</th>
			<td><input type="text" name="email" size="25" maxlength="60" autocomplete="off" placeholder="sage = do not bump"></td>
		</tr>
		<tr>
			<th>Subject</th>
			<td>
				<input style="float:left" type="text" name="subject" size="25" maxlength="100" autocomplete="off">
				<input accesskey="s" style="margin-left:2px" type="submit" name="post" value="<?= $threadId ? 'New Reply' : 'New Topic' ?>">
			</td>
		</tr>
		<tr>
			<th>Comment</th>
			<td><textarea name="body" id="body" rows="5" cols="35"></textarea></td>
		</tr>
		<tr>
			<th>File</th>
			<td><input type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp"></td>
		</tr>
		<?php if (!empty($board['require_password'])): ?>
		<tr>
			<th>Password</th>
			<td><input type="password" name="board_password" size="25" autocomplete="off"></td>
		</tr>
		<?php endif; ?>
	</table>
</form>
