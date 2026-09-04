<header class="board-heading board-heading--home">
    <h1><?= $this->e($appName) ?></h1>
    <div class="board-subtitle"><?= $this->e($tagline) ?></div>
</header>
<hr class="board-rule">

<?php if ($boards === []): ?>
    <div class="empty-state">
        <h2>No boards yet</h2>
        <p>The administrator can create the first board from the moderator desk.</p>
    </div>
<?php else: ?>
    <section class="board-index box" aria-labelledby="boards-heading">
        <h2 id="boards-heading" class="box-title">Boards</h2>
        <div class="table-wrap">
            <table class="board-table">
                <thead>
                    <tr>
                        <th>Board</th>
                        <th>Description</th>
                        <th>Threads</th>
                        <th>Posts</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($boards as $board): ?>
                    <tr>
                        <td>
                            <a href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/')) ?>">
                                <strong>/<?= $this->e($board['slug']) ?>/</strong>
                                <?= $this->e($board['title']) ?>
                            </a>
                        </td>
                        <td><?= $this->e($board['description']) ?></td>
                        <td><?= (int) $board['thread_count'] ?></td>
                        <td><?= (int) $board['post_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <p class="home-note">
        Anonymous chess discussion. Use <code>[pgn]...[/pgn]</code> for notation.
    </p>
<?php endif; ?>
