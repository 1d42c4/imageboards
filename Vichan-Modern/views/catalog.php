<?php declare(strict_types=1); ?>
<p>[ <a href="<?= $v->url($board['slug'] . '/index.html') ?>">Return to index</a> / <a href="<?= $v->url('compose.php?board=' . $board['slug']) ?>">New topic</a> ]</p>
<div class="theme-catalog"><div class="threads">
<?php foreach ($threads as $post): $file = $v->app->db->one('SELECT * FROM files WHERE post_id=? ORDER BY id LIMIT 1', [$post['id']]); ?><div class="thread"><a href="<?= $v->url($v->app->boards->postPath($post)) ?>"><?php if ($file !== null): ?><img class="thread-image" src="<?= $v->url($file['thumb'] ? 'media.php?id=' . $file['token'] . '&thumb=1' : 'static/video.png') ?>" alt=""><?php endif ?><strong><?= $v::e($post['subject'] ?: 'Thread ' . $post['id']) ?></strong></a><p><?= $v::e(mb_substr($post['body'], 0, 180)) ?></p></div><?php endforeach ?>
</div></div>
