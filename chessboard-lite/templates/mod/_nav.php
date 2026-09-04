<nav class="mod-nav" aria-label="Moderator tools">
    <a href="<?= $this->e($this->url('/mod')) ?>">Dashboard</a>
    <a href="<?= $this->e($this->url('/mod/bans')) ?>">Bans</a>
    <?php if (($currentModerator['role'] ?? '') === 'admin'): ?>
        <a href="<?= $this->e($this->url('/mod/boards')) ?>">Boards</a>
    <?php endif; ?>
    <form action="<?= $this->e($this->url('/mod/logout')) ?>" method="post">
        <?= $this->csrfField() ?>
        <button type="submit">Sign out <?= $this->e($currentModerator['username'] ?? '') ?></button>
    </form>
</nav>

