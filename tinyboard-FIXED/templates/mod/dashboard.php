<?php
/** @var callable $e */
/** @var callable $url */
/** @var array<string,mixed> $mod */
/** @var list<array<string,mixed>> $boards */
/** @var int $pending_count */
/** @var int $report_count */
/** @var list<array<string,mixed>> $pending */
/** @var list<array<string,mixed>> $log */
/** @var string $csrf_field */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>Dashboard</h1>
	<div class="subtitle">
		Logged in as <strong><?= $e($mod['username'] ?? '') ?></strong>
		(<?= $e($mod['type'] ?? '') ?>)
		[<a href="<?= $e($url('/mod/logout')) ?>">Logout</a>]
	</div>
</header>
<hr>

<nav class="mod-nav">
	[<a href="<?= $e($url('/mod')) ?>">Dashboard</a>]
	[<a href="<?= $e($url('/mod/new-board')) ?>">New board</a>]
	[<a href="<?= $e($url('/mod/users')) ?>">Users</a>]
	[<a href="<?= $e($url('/mod/log')) ?>">Mod log</a>]
	[<a href="<?= $e($url('/mod/reports')) ?>">Reports (<?= (int) $report_count ?>)</a>]
	[<a href="<?= $e($url('/mod/pending')) ?>">Pending (<?= (int) $pending_count ?>)</a>]
	[<a href="<?= $e($url('/mod/recent')) ?>">Recent posts</a>]
</nav>
<hr>

<h2>Boards</h2>
<table class="board-table">
	<tr><th>URI</th><th>Title</th><th>Flags</th><th></th></tr>
	<?php foreach ($boards as $b): ?>
		<tr>
			<td><a href="<?= $e($url('/' . $b['uri'])) ?>">/<?= $e($b['uri']) ?>/</a></td>
			<td><?= $e($b['title']) ?></td>
			<td class="hint">
				<?php if (!empty($b['require_approval'])): ?>approval <?php endif; ?>
				<?php if (!empty($b['require_password'])): ?>password <?php endif; ?>
				<?php if (!empty($b['force_image_op'])): ?>img-OP <?php endif; ?>
			</td>
			<td>
				<a href="<?= $e($url('/mod/edit/' . $b['uri'])) ?>">Edit</a>
				· <a href="<?= $e($url('/' . $b['uri'] . '/archive')) ?>">Archive</a>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
<p>[<a href="<?= $e($url('/mod/new-board')) ?>">Create board</a>]</p>

<h2>Pending (preview)</h2>
<?php if ($pending === []): ?>
	<p>None. <a href="<?= $e($url('/mod/pending')) ?>">Full queue</a></p>
<?php else: ?>
	<ul>
	<?php foreach ($pending as $p): ?>
		<li>
			/<?= $e($p['board_uri']) ?>/ #<?= (int) $p['id'] ?>
			— <?= $e(mb_substr((string) $p['body'], 0, 60)) ?>
			<form class="inline" method="post" action="<?= $e($url('/mod/action')) ?>">
				<?= $csrf_field ?>
				<input type="hidden" name="do" value="approve">
				<input type="hidden" name="board" value="<?= $e($p['board_uri']) ?>">
				<input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
				<input type="hidden" name="back" value="<?= $e($url('/mod')) ?>">
				<button type="submit">Approve</button>
			</form>
			<form class="inline" method="post" action="<?= $e($url('/mod/action')) ?>">
				<?= $csrf_field ?>
				<input type="hidden" name="do" value="reject">
				<input type="hidden" name="board" value="<?= $e($p['board_uri']) ?>">
				<input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
				<input type="hidden" name="back" value="<?= $e($url('/mod')) ?>">
				<button type="submit">Reject</button>
			</form>
		</li>
	<?php endforeach; ?>
	</ul>
	<p><a href="<?= $e($url('/mod/pending')) ?>">Full approval queue →</a></p>
<?php endif; ?>

<h2>Recent mod log</h2>
<p class="hint">No IP addresses are stored in the log.</p>
<table class="log">
	<tr><th>Time</th><th>User</th><th>Action</th><th>Detail</th></tr>
	<?php foreach ($log as $row): ?>
		<tr>
			<td><?= $e(gmdate('Y-m-d H:i', (int) $row['time'])) ?></td>
			<td><?= $e($row['username']) ?></td>
			<td><?= $e($row['action']) ?></td>
			<td><?= $e($row['detail']) ?><?php if (!empty($row['board_uri'])): ?> (<?= $e($row['board_uri']) ?>)<?php endif; ?></td>
		</tr>
	<?php endforeach; ?>
</table>
<p><a href="<?= $e($url('/mod/log')) ?>">Full log →</a></p>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
