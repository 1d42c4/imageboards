<?php
/** @var callable $e */
/** @var callable $url */
/** @var array<string,mixed> $board */
/** @var array<string,mixed> $post */
/** @var string $csrf_field */
/** @var string|null $error */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>Report post #<?= (int) $post['id'] ?></h1>
	<div class="subtitle">/<?= $e($board['uri']) ?>/ — your IP is <strong>not</strong> recorded</div>
</header>
<hr>
<?php if ($error): ?><p class="error"><?= $e($error) ?></p><?php endif; ?>
<p class="hint">Teaser: <?= $e(mb_substr((string) $post['body'], 0, 120)) ?></p>
<form method="post" action="<?= $e($url('/report/' . $board['uri'] . '/' . $post['id'])) ?>">
	<?= $csrf_field ?>
	<div class="hp" aria-hidden="true" style="position:absolute;left:-9999px">
		<input type="text" name="website" tabindex="-1" autocomplete="off">
	</div>
	<table>
		<tr>
			<th>Reason</th>
			<td><textarea name="reason" rows="4" cols="40" required maxlength="500"></textarea></td>
		</tr>
		<tr><td></td><td><input type="submit" value="Submit report"></td></tr>
	</table>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
