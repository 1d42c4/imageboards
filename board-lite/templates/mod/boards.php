<?= $this->partial('mod/_nav', ['currentModerator' => $currentModerator]) ?>

<header class="admin-heading">
    <div>
        <p class="eyebrow">Site structure</p>
        <h1>Boards</h1>
        <p>Create focused spaces for openings, analysis, puzzles, tournaments, or general discussion.</p>
    </div>
</header>

<section class="admin-section admin-section--form">
    <h2>Create a board</h2>
    <form class="stack-form" action="<?= $this->e($this->url('/mod/boards')) ?>" method="post">
        <?= $this->csrfField() ?>
        <div class="form-row form-row--split">
            <label>
                <span>Address</span>
                <input type="text" name="slug" maxlength="32" pattern="[a-z0-9][a-z0-9-]{0,31}" placeholder="analysis" required>
            </label>
            <label>
                <span>Title</span>
                <input type="text" name="title" maxlength="80" placeholder="Game Analysis" required>
            </label>
        </div>
        <label>
            <span>Description</span>
            <input type="text" name="description" maxlength="280" placeholder="Share positions and review complete games.">
        </label>
        <button class="button" type="submit">Create board</button>
    </form>
</section>

<section class="admin-section">
    <div class="board-grid">
        <?php foreach ($boards as $board): ?>
            <a class="board-card" href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/')) ?>">
                <span class="board-card__address">/<?= $this->e($board['slug']) ?>/</span>
                <h2><?= $this->e($board['title']) ?></h2>
                <p><?= $this->e($board['description']) ?></p>
                <span class="board-card__stats"><?= (int) $board['post_count'] ?> posts</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

