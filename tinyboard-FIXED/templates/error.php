<?php
/** @var callable $e */
/** @var callable $url */
/** @var string $message */
/** @var string|null $title */
/** @var \Newboard\Config $config */

ob_start();
?>
<header>
	<h1>Error</h1>
</header>
<hr>
<div class="ban">
	<p><?= nl2br($e($message ?? 'Unknown error'), false) ?></p>
	<p>[<a href="<?= $e($url('/')) ?>">Home</a>]</p>
</div>
<?php
$content = ob_get_clean();
$title = $title ?? 'Error';
require __DIR__ . '/layout.php';
