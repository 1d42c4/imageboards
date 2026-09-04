<?php
/** @var callable $e */
/** @var callable $url */
/** @var bool $new */
/** @var array<string,mixed>|null $user */
/** @var list<array<string,mixed>> $boards */
/** @var string $csrf_field */
/** @var string|null $error */
/** @var string $title */
/** @var \Newboard\Config $config */

$userBoards = [];
if (!$new && !empty($user['boards']) && $user['boards'] !== '*') {
    $userBoards = explode(',', (string) $user['boards']);
}
$allboards = $new || (($user['boards'] ?? '*') === '*');

ob_start();
?>
<header>
	<h1><?= $new ? 'New user' : 'Edit user' ?></h1>
	<div class="subtitle">[<a href="<?= $e($url('/mod/users')) ?>">Users</a>]</div>
</header>
<hr>
<?php if ($error): ?><p class="error"><?= $e($error) ?></p><?php endif; ?>
<form method="post" action="<?= $e($new ? $url('/mod/users/new') : $url('/mod/users/' . ($user['id'] ?? ''))) ?>">
	<?= $csrf_field ?>
	<table>
		<tr>
			<th>Username</th>
			<td><input type="text" name="username" required value="<?= $e($user['username'] ?? '') ?>" autocomplete="off"></td>
		</tr>
		<tr>
			<th>Password</th>
			<td><input type="password" name="password" <?= $new ? 'required' : '' ?> autocomplete="new-password" placeholder="<?= $new ? '' : 'leave blank to keep' ?>"></td>
		</tr>
		<tr>
			<th>Type</th>
			<td>
				<select name="type">
					<?php foreach (['admin', 'mod', 'janitor'] as $t): ?>
						<option value="<?= $t ?>" <?= (($user['type'] ?? 'mod') === $t) ? 'selected' : '' ?>><?= $t ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th>Boards</th>
			<td>
				<label><input type="checkbox" name="allboards" value="1" <?= $allboards ? 'checked' : '' ?>> All boards (*)</label>
				<div class="hint">Or uncheck and pick:</div>
				<?php foreach ($boards as $b): ?>
					<label style="display:inline-block;margin-right:0.5em">
						<input type="checkbox" name="board_<?= $e($b['uri']) ?>" value="1" <?= in_array($b['uri'], $userBoards, true) ? 'checked' : '' ?>>
						/<?= $e($b['uri']) ?>/
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<td></td>
			<td>
				<input type="submit" value="Save">
				<?php if (!$new): ?>
					<button type="submit" name="delete_user" value="1" onclick="return confirm('Delete this user?');" style="color:#c00">Delete user</button>
				<?php endif; ?>
			</td>
		</tr>
	</table>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
