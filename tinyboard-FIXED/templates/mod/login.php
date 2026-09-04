<?php
/** @var callable $e */
/** @var callable $url */
/** @var string $csrf_field */
/** @var string|null $error */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>Moderator login</h1>
</header>
<hr>
<?php if ($error): ?>
	<p class="error"><?= $e($error) ?></p>
<?php endif; ?>
<form method="post" action="<?= $e($url('/mod/login')) ?>">
	<?= $csrf_field ?>
	<table>
		<tr><th>Username</th><td><input type="text" name="username" required autocomplete="username"></td></tr>
		<tr><th>Password</th><td><input type="password" name="password" required autocomplete="current-password"></td></tr>
		<tr><td></td><td><input type="submit" value="Login"></td></tr>
	</table>
</form>
<p class="hint">No IP is logged on login. Change the default password after first install.</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
