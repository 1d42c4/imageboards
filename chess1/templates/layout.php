<!doctype html>
<html lang="en" data-style="checkmate" data-text-size="normal" data-text-weight="normal">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $this->e(($title ?? '') !== '' ? $title . ' · ' . $appName : $appName) ?></title>
    <link rel="stylesheet" href="<?= $this->e($this->url('/assets/css/app.css')) ?>">
    <link
        id="theme-stylesheet"
        rel="stylesheet"
        href="<?= $this->e($this->url('/assets/css/themes/checkmate.css')) ?>"
        data-theme-base="<?= $this->e($this->url('/assets/css/themes')) ?>"
    >
    <script type="module" src="<?= $this->e($this->url('/assets/js/app.js')) ?>"></script>
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
        <label class="style-switcher" for="style-switcher">Theme
            <select id="style-switcher" aria-label="Board theme">
                <?php foreach ($themeGroups as $groupLabel => $themes): ?>
                    <optgroup label="<?= $this->e($groupLabel) ?>">
                        <?php foreach ($themes as $themeSlug => $themeName): ?>
                            <option value="<?= $this->e($themeSlug) ?>"><?= $this->e($themeName) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </label>
        <span class="reading-controls" role="group" aria-label="Reading controls">
            <button type="button" class="reading-control" id="text-smaller" aria-label="Make site text smaller" title="Smaller text">A−</button>
            <button type="button" class="reading-control" id="text-larger" aria-label="Make site text larger" title="Larger text">A+</button>
            <button type="button" class="reading-control reading-control--weight" id="text-weight" aria-label="Use thicker, darker text" aria-pressed="false" title="Thicker text">A</button>
        </span>
        <span id="reading-status" class="visually-hidden" aria-live="polite"></span>
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
    - <?= $this->e($appName) ?> · PHP 8.4 + SQLite 3 -
</footer>
</body>
</html>
