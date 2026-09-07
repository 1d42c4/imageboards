<?php declare(strict_types=1); ?>
<p class="board-controls">[ <a href="<?= $v->url($board['slug'] . '/index.html') ?>">Index</a> / <a href="<?= $v->url($board['slug'] . '/catalog.html') ?>">Catalog</a> / <a href="#bottom">Bottom</a> ] <?php if ($thread !== null): ?><label><input id="auto-refresh" type="checkbox"> Auto-refresh</label><?php endif ?></p>
<?= $v->fragment('post_form', ['board' => $board, 'thread' => $thread]) ?>
<hr><div id="threads">
<?php foreach ($threads as $op): ?><section class="thread" id="thread_<?= $v::e($op['id']) ?>" data-board="<?= $v::e($board['slug']) ?>">
<?= $v->post($op, preview: $thread === null) ?>
<?php $replies = $v->app->db->all('SELECT p.*,? AS slug FROM posts p WHERE thread_id=? ORDER BY id', [$board['slug'], $op['id']]); $omitted = $thread === null ? max(0, count($replies) - $v->app->config->int('preview_replies')) : 0; ?>
<?php if ($omitted > 0): ?><div class="omitted"><?= $v::e($omitted) ?> replies omitted. <a href="<?= $v->url($board['slug'] . '/res/' . $op['id'] . '.html') ?>">View the full thread.</a></div><?php endif ?>
<?php foreach (array_slice($replies, $omitted) as $reply): ?><?= $v->post($reply, true) ?><?php endforeach ?>
<div class="clear"></div></section><hr><?php endforeach ?>
<?php if ($threads === []): ?><p class="center-text empty">No threads yet. Start the first discussion above.</p><?php endif ?>
</div>
<?php if ($thread === null): ?><nav class="pages" aria-label="Board pages"><?php for ($n = 1; $n <= $pages; $n++): ?>[ <?php if ($page === $n): ?><strong><?= $n ?></strong><?php else: ?><a href="<?= $v->url($board['slug'] . '/' . ($n === 1 ? 'index' : $n) . '.html') ?>"><?= $n ?></a><?php endif ?> ] <?php endfor ?></nav><?php endif ?>
<form class="post-action" action="<?= $v->url('action.php') ?>" method="post" data-ajax="action"><input type="hidden" name="csrf" value=""><fieldset><legend>Post actions</legend><label>Post number <input type="number" name="post_id" min="1" required></label> <label>Action <select name="action"><option value="report">Report</option><option value="delete">Delete post</option><option value="files">Delete files only</option></select></label> <label>Reason <input type="text" name="reason" maxlength="500"></label> <label>Deletion password <input type="password" name="password" maxlength="128" autocomplete="off"></label> <button>Submit</button><span class="form-status" role="status"></span></fieldset></form>
<noscript><p><a href="<?= $v->url('action.php') ?>">Open the report/deletion form without JavaScript</a>.</p></noscript>
<form class="board-search" action="<?= $v->url('search.php') ?>" method="get"><input type="hidden" name="board" value="<?= $v::e($board['slug']) ?>"><label>Search this board <input name="q" type="search" maxlength="100" required></label> <button>Search</button></form>
<p id="bottom">[ <a href="#">Top</a> / <a href="<?= $v->url($board['slug'] . '/index.html') ?>">Index</a> / <a href="<?= $v->url($board['slug'] . '/catalog.html') ?>">Catalog</a> ]</p>
