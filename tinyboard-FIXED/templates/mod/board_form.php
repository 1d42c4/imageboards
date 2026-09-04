<?php
/** @var callable $e */
/** @var callable $url */
/** @var bool $new */
/** @var array<string,mixed>|null $board */
/** @var string $csrf_field */
/** @var string|null $error */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1><?= $new ? 'New board' : 'Edit board' ?></h1>
	<div class="subtitle">[<a href="<?= $e($url('/mod')) ?>">Dashboard</a>]</div>
</header>
<hr>
<?php if ($error): ?><p class="error"><?= $e($error) ?></p><?php endif; ?>
<form method="post" action="<?= $e($new ? $url('/mod/new-board') : $url('/mod/edit/' . ($board['uri'] ?? ''))) ?>">
	<?= $csrf_field ?>
	<table>
		<?php if ($new): ?>
		<tr>
			<th>URI</th>
			<td><input type="text" name="uri" size="20" maxlength="30" required pattern="[a-zA-Z0-9_-]+" placeholder="b"> (path /uri/)</td>
		</tr>
		<?php else: ?>
		<tr><th>URI</th><td>/<?= $e($board['uri'] ?? '') ?>/</td></tr>
		<?php endif; ?>
		<tr>
			<th>Title</th>
			<td><input type="text" name="title" size="40" required value="<?= $e($board['title'] ?? '') ?>"></td>
		</tr>
		<tr>
			<th>Subtitle</th>
			<td><input type="text" name="subtitle" size="40" value="<?= $e($board['subtitle'] ?? '') ?>"></td>
		</tr>
		<?php if (!$new): ?>
		<tr>
			<th>Post approval</th>
			<td><label><input type="checkbox" name="require_approval" value="1" <?= !empty($board['require_approval']) ? 'checked' : '' ?>> Hold new posts for mod approval</label></td>
		</tr>
		<tr>
			<th>Board password</th>
			<td>
				<label><input type="checkbox" name="require_password" value="1" <?= !empty($board['require_password']) ? 'checked' : '' ?>> Require password to post</label><br>
				<input type="password" name="board_password" size="25" autocomplete="new-password" placeholder="<?= !empty($board['password_hash']) ? 'leave blank to keep' : 'set password' ?>">
			</td>
		</tr>
		<tr>
			<th>Force image OP</th>
			<td><label><input type="checkbox" name="force_image_op" value="1" <?= !empty($board['force_image_op']) ? 'checked' : '' ?>> New threads need an image</label></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td></td>
			<td>
				<input type="submit" value="<?= $new ? 'Create' : 'Save' ?>">
				<?php if (!$new): ?>
					<button type="submit" name="delete_board" value="1" onclick="return confirm('Delete board and ALL posts?');" style="color:#c00">Delete board</button>
				<?php endif; ?>
			</td>
		</tr>
	</table>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
