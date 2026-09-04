<header class="index-heading">
    <h1><?= $this->e($appName) ?></h1>
    <p><?= $this->e($tagline) ?></p>
    <p class="muted">
        Anonymous chess discussion. Use <code>[pgn]...[/pgn]</code> for notation.
    </p>
</header>

<?php if ($boards === []): ?>
    <div class="empty-state">
        <h2>No boards yet</h2>
        <p>The administrator can create the first board from the moderator desk.</p>
    </div>
<?php else: ?>
    <section class="board-index" aria-labelledby="boards-heading">
        <h2 id="boards-heading">Boards</h2>
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
<?php endif; ?>
