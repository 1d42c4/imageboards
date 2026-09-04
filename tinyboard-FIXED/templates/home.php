<?php
/** @var callable $e */
/** @var callable $url */
/** @var list<array<string,mixed>> $boards */
/** @var string $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1><?= $e($config->string('name', 'Newboard')) ?></h1>
	<div class="subtitle">Tinyboard-inspired · SQLite · never stores client IPs</div>
</header>
<hr>
<div class="home-boards">
	<table class="board-table">
		<tr><th>Board</th><th>Title</th><th>Subtitle</th></tr>
		<?php foreach ($boards as $b): ?>
			<tr>
				<td><a href="<?= $e($url('/' . $b['uri'])) ?>">/<?= $e($b['uri']) ?>/</a></td>
				<td><?= $e($b['title']) ?></td>
				<td><?= $e($b['subtitle']) ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
</div>
<p class="hint">Posts are anonymous. Abuse controls: CSRF, honeypot, session cooldown, optional board password / approval — <strong>never IP</strong>.</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
