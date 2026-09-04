<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= $this->e(($title ?? '') !== '' ? $title . ' · ' . $appName : $appName) ?></title>
    <link rel="stylesheet" href="<?= $this->e($this->url('/assets/app.css')) ?>">
    <script src="<?= $this->e($this->url('/assets/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
    <div class="site-header__board" aria-hidden="true"></div>
    <div class="shell site-header__inner">
        <a class="brand" href="<?= $this->e($this->url('/')) ?>">
            <span class="brand__mark" aria-hidden="true">♞</span>
            <span>
                <strong><?= $this->e($appName) ?></strong>
                <small><?= $this->e($tagline) ?></small>
            </span>
        </a>
        <nav class="site-nav" aria-label="Boards">
            <a href="<?= $this->e($this->url('/')) ?>">Boards</a>
            <?php foreach ($navigationBoards as $navBoard): ?>
                <a href="<?= $this->e($this->url('/' . rawurlencode($navBoard['slug']) . '/')) ?>">
                    /<?= $this->e($navBoard['slug']) ?>/
                </a>
            <?php endforeach; ?>
            <?php if ($currentModerator !== null): ?>
                <a class="site-nav__mod" href="<?= $this->e($this->url('/mod')) ?>">Mod desk</a>
            <?php else: ?>
                <a class="site-nav__muted" href="<?= $this->e($this->url('/mod/login')) ?>">Staff</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php if ($flashMessages !== []): ?>
    <div class="shell flash-stack" aria-live="polite">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="flash flash--<?= $this->e($flash['type'] ?? 'info') ?>">
                <?= $this->e($flash['message'] ?? '') ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main id="main-content" class="shell page">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="shell site-footer__inner">
        <span><span aria-hidden="true">♙</span> Small, focused, and dependency-free.</span>
        <span>PHP 8.5 · SQLite 3</span>
    </div>
</footer>
</body>
</html>

