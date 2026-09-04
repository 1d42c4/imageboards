<!doctype html>
<html lang="en" data-style="classic">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $this->e(($title ?? '') !== '' ? $title . ' · ' . $appName : $appName) ?></title>
    <link rel="stylesheet" href="<?= $this->e($this->url('/assets/app.css')) ?>">
    <script src="<?= $this->e($this->url('/assets/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<nav id="top" class="board-list" aria-label="Boards">
    <span>[
        <a href="<?= $this->e($this->url('/')) ?>">home</a>
        <?php foreach ($navigationBoards as $navBoard): ?>
            / <a href="<?= $this->e($this->url('/' . rawurlencode($navBoard['slug']) . '/')) ?>"><?= $this->e($navBoard['slug']) ?></a>
        <?php endforeach; ?>
    ]</span>
    <span class="board-list__staff">
        <label class="style-switcher" for="style-switcher">Style
            <select id="style-switcher" aria-label="Board style">
                <option value="classic">Classic</option>
                <option value="clean">Clean</option>
                <option value="blue">Blue</option>
                <option value="dark">Dark</option>
            </select>
        </label>
        [
        <?php if ($currentModerator !== null): ?>
            <a href="<?= $this->e($this->url('/mod')) ?>">manage</a>
        <?php else: ?>
            <a href="<?= $this->e($this->url('/mod/login')) ?>">staff</a>
        <?php endif; ?>
        ]
    </span>
</nav>

<?php if ($flashMessages !== []): ?>
    <div class="flash-stack" aria-live="polite">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="flash flash--<?= $this->e($flash['type'] ?? 'info') ?>">
                <?= $this->e($flash['message'] ?? '') ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main id="main-content" class="page">
    <?= $content ?>
</main>

<footer class="footer">
    - <?= $this->e($appName) ?> · PHP 8.5 + SQLite 3 -
</footer>
</body>
</html>
