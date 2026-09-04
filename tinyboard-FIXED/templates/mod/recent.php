<?php
/** @var callable $e */
/** @var callable $url */
/** @var list<array<string,mixed>> $posts */
/** @var string $csrf_field */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>Recent posts</h1>
	<div class="subtitle">[<a href="<?= $e($url('/mod')) ?>">Dashboard</a>]</div>
</header>
<hr>
<table class="log">
	<tr><th>Time</th><th>Board</th><th>#</th><th>Name</th><th>Teaser</th><th></th></tr>
	<?php foreach ($posts as $p): ?>
		<?php
		$tid = $p['thread_id'] !== null ? (int) $p['thread_id'] : (int) $p['id'];
		$link = !empty($p['archived'])
			? $url('/' . $p['board_uri'] . '/archive/' . $tid)
			: $url('/' . $p['board_uri'] . '/res/' . $tid . '#p' . $p['id']);
		?>
		<tr>
			<td><?= $e(gmdate('Y-m-d H:i', (int) $p['time'])) ?></td>
			<td>/<?= $e($p['board_uri']) ?>/</td>
			<td><a href="<?= $e($link) ?>"><?= (int) $p['id'] ?></a></td>
			<td><?= $e($p['name'] !== '' ? $p['name'] : 'Anonymous') ?></td>
			<td><?= $e(mb_substr((string) $p['body'], 0, 60)) ?></td>
			<td>
				<form class="inline" method="post" action="<?= $e($url('/mod/action')) ?>" onsubmit="return confirm('Delete?');">
					<?= $csrf_field ?>
					<input type="hidden" name="do" value="delete">
					<input type="hidden" name="board" value="<?= $e($p['board_uri']) ?>">
					<input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
					<input type="hidden" name="back" value="<?= $e($url('/mod/recent')) ?>">
					<button type="submit">Del</button>
				</form>
				<a href="<?= $e($url('/mod/edit-post/' . $p['board_uri'] . '/' . $p['id'])) ?>">Edit</a>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
