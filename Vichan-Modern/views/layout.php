<?php declare(strict_types=1); /** @var \VichanModern\View $v */
$theme = $v->app->installed() ? $v->app->setting('theme', $v->app->config->string('theme')) : $v->app->config->string('theme');
if (!isset($v->themes()[$theme])) { $theme = 'yotsuba'; }
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer"><meta name="app-root" content="<?= $v->url() ?>">
<title><?= $v::e($title) ?></title>
<link rel="stylesheet" href="<?= $v->url('stylesheets/style.css') ?>">
<link id="stylesheet" rel="stylesheet" href="<?= $v->url('stylesheets/' . rawurlencode($theme) . '.css') ?>">
<link rel="stylesheet" href="<?= $v->url('assets/app.css') ?>">
<script defer src="<?= $v->url('assets/app.js') ?>"></script></head>
<body class="8chan vichan <?= $mod ? 'is-moderator' : 'is-not-moderator' ?>" data-stylesheet="<?= $v::e($theme) ?>">
<div class="boardlist"><nav aria-label="Boards">[ <a href="<?= $v->url() ?>">Home</a> / <?php foreach ($boards as $navBoard): ?><a href="<?= $v->url($navBoard['slug'] . '/index.html') ?>" title="<?= $v::e($navBoard['title']) ?>"><?= $v::e($navBoard['slug']) ?></a> / <?php endforeach ?><a href="<?= $v->url('mod.php') ?>">Mod</a> ]</nav>
<label class="theme-picker">Style <select id="theme-select" aria-label="Choose theme"><?php foreach ($v->themes() as $key => $label): ?><option value="<?= $v::e($key) ?>" <?= $key === $theme ? 'selected' : '' ?>><?= $v::e($label) ?></option><?php endforeach ?></select></label></div>
<header><h1><?= $v::e($title) ?></h1><?php if ($board !== null): ?><div class="subtitle"><?= $v::e($board['subtitle']) ?></div><?php endif ?></header>
<?php if ($mod): ?><p class="mod-nav">[ <a href="<?= $v->url('mod.php') ?>">Return to dashboard</a> ]</p><?php endif ?>
<main><?= $body ?></main>
<footer><p class="unimportant" style="margin-top:20px;text-align:center;">- Tinyboard + <a href="https://github.com/vichan-devel/vichan" rel="noreferrer">vichan</a> · Modern PHP edition -
<br>Tinyboard Copyright &copy; 2010-2014 Tinyboard Development Group
<br><a href="https://github.com/vichan-devel/vichan" rel="noreferrer">vichan</a> Copyright &copy; 2012-2025 vichan-devel</p>
<p class="unimportant center-text"><a href="<?= $v->url('privacy.html') ?>">Privacy</a> · <a href="<?= $v->url('search.php') ?>">Search</a></p></footer>
<div id="live-message" role="status" aria-live="polite"></div>
</body></html>
