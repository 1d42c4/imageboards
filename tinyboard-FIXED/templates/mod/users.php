<?php
/** @var callable $e */
/** @var callable $url */
/** @var list<array<string,mixed>> $users */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>Users</h1>
	<div class="subtitle">
		[<a href="<?= $e($url('/mod')) ?>">Dashboard</a>]
		[<a href="<?= $e($url('/mod/users/new')) ?>">New user</a>]
	</div>
</header>
<hr>
<table class="log">
	<tr><th>ID</th><th>Username</th><th>Type</th><th>Boards</th><th></th></tr>
	<?php foreach ($users as $u): ?>
		<tr>
			<td><?= (int) $u['id'] ?></td>
			<td><?= $e($u['username']) ?></td>
			<td><?= $e($u['type']) ?></td>
			<td><?= $e($u['boards'] ?? '*') ?></td>
			<td><a href="<?= $e($url('/mod/users/' . $u['id'])) ?>">Edit</a></td>
		</tr>
	<?php endforeach; ?>
</table>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
