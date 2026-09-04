<?php
/** @var callable $e */
/** @var callable $url */
/** @var list<array<string,mixed>> $entries */
/** @var int $page */
/** @var int $pages */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>Mod log</h1>
	<div class="subtitle">[<a href="<?= $e($url('/mod')) ?>">Dashboard</a>] — no IPs stored</div>
</header>
<hr>
<table class="log">
	<tr><th>Time (UTC)</th><th>User</th><th>Board</th><th>Action</th><th>Detail</th></tr>
	<?php foreach ($entries as $row): ?>
		<tr>
			<td><?= $e(gmdate('Y-m-d H:i:s', (int) $row['time'])) ?></td>
			<td><?= $e($row['username']) ?></td>
			<td><?= $e($row['board_uri'] ?? '') ?></td>
			<td><?= $e($row['action']) ?></td>
			<td><?= $e($row['detail']) ?></td>
		</tr>
	<?php endforeach; ?>
</table>
<div class="pages">
	<?php for ($i = 1; $i <= $pages; $i++): ?>
		[<a class="<?= $i === $page ? 'selected' : '' ?>" href="<?= $e($url('/mod/log?page=' . $i)) ?>"><?= $i ?></a>]
	<?php endfor; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
