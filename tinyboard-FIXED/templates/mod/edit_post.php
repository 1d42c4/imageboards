<?php
/** @var callable $e */
/** @var callable $url */
/** @var string $board_uri */
/** @var array<string,mixed> $post */
/** @var string $csrf_field */
/** @var string|null $error */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>Edit post #<?= (int) $post['id'] ?></h1>
	<div class="subtitle">/<?= $e($board_uri) ?>/ [<a href="<?= $e($url('/mod')) ?>">Dashboard</a>]</div>
</header>
<hr>
<?php if ($error): ?><p class="error"><?= $e($error) ?></p><?php endif; ?>
<form method="post" action="<?= $e($url('/mod/edit-post/' . $board_uri . '/' . $post['id'])) ?>" enctype="multipart/form-data">
	<?= $csrf_field ?>
	<table>
		<tr><th>Name</th><td><input type="text" name="name" size="25" value="<?= $e($post['name']) ?>"></td></tr>
		<tr><th>Email</th><td><input type="text" name="email" size="25" value="<?= $e($post['email']) ?>"></td></tr>
		<tr><th>Subject</th><td><input type="text" name="subject" size="35" value="<?= $e($post['subject']) ?>"></td></tr>
		<tr><th>Body</th><td><textarea name="body" rows="8" cols="50"><?= $e($post['body']) ?></textarea></td></tr>
		<tr>
			<th>Image</th>
			<td>
				<?php if (!empty($post['file_path'])): ?>
					Current: <?= $e($post['file_orig'] ?? $post['file_path']) ?>
					<label><input type="checkbox" name="remove_file" value="1"> Remove file</label><br>
				<?php endif; ?>
				Replace: <input type="file" name="file" accept="image/*">
			</td>
		</tr>
		<tr><td></td><td><input type="submit" value="Save"></td></tr>
	</table>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
