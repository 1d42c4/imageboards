<?php declare(strict_types=1); ?>
<div class="error-panel"><h2><?= $v::e($status) ?></h2><p><?= $v::e($message) ?></p><p><a href="<?= $v->url() ?>">Home</a> · <a href="<?= $v->url('mod.php') ?>">Moderator sign-in</a></p><p>You can use your browser’s Back button to return to the form.</p></div>
