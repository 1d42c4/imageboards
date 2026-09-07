<?php declare(strict_types=1); ?>
<section class="home-panel"><fieldset><legend>Boards</legend><ul class="board-directory">
<?php foreach ($boards as $b): ?><li><a href="<?= $v->url($b['slug'] . '/index.html') ?>"><strong>/<?= $v::e($b['slug']) ?>/ — <?= $v::e($b['title']) ?></strong></a><p><?= $v::e($b['subtitle']) ?></p></li><?php endforeach ?>
</ul></fieldset>
<p>Post an article, share a game or join a discussion. Text, pictures, animated GIFs and MP4 videos are welcome.</p>
<form action="<?= $v->url('search.php') ?>" method="get"><label for="home-search">Search discussions</label> <input id="home-search" name="q" type="search" maxlength="100" required> <button>Search</button></form>
</section>
