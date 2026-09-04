<header class="board-heading">
    <div>
        <p class="board-address">/<?= $this->e($board['slug']) ?>/</p>
        <h1><?= $this->e($board['title']) ?></h1>
        <?php if ($board['description'] !== ''): ?>
            <p><?= $this->e($board['description']) ?></p>
        <?php endif; ?>
    </div>
    <a class="button button--quiet" href="<?= $this->e($this->url('/')) ?>">All boards</a>
</header>

<details class="composer composer--thread"<?= $threads === [] ? ' open' : '' ?>>
    <summary>Start a new thread</summary>
    <?= $this->partial('partials/post-form', [
        'action' => $this->url('/' . rawurlencode($board['slug']) . '/threads'),
        'submitLabel' => 'Start thread',
        'formId' => 'new-thread',
        'textareaId' => 'new-thread-body',
    ]) ?>
</details>

<?php if ($threads === []): ?>
    <div class="empty-state">
        <span aria-hidden="true">♙</span>
        <h2>The board is open</h2>
        <p>Make the first move by starting a thread.</p>
    </div>
<?php else: ?>
    <div class="thread-list">
        <?php foreach ($threads as $thread): ?>
            <section class="thread-preview" aria-labelledby="p<?= (int) $thread['post_no'] ?>">
                <?= $this->partial('partials/post', [
                    'board' => $board,
                    'post' => $thread,
                    'currentModerator' => $currentModerator,
                    'quoteEnabled' => false,
                ]) ?>

                <?php
                $omitted = max(0, (int) $thread['reply_count'] - count($thread['replies']));
                if ($omitted > 0):
                ?>
                    <p class="omitted">
                        <?= $omitted ?> earlier <?= $omitted === 1 ? 'reply' : 'replies' ?> omitted.
                        <a href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/thread/' . (int) $thread['post_no'])) ?>">
                            Read the full thread
                        </a>
                    </p>
                <?php endif; ?>

                <?php foreach ($thread['replies'] as $reply): ?>
                    <?= $this->partial('partials/post', [
                        'board' => $board,
                        'post' => $reply,
                        'currentModerator' => $currentModerator,
                        'quoteEnabled' => false,
                    ]) ?>
                <?php endforeach; ?>

                <div class="thread-preview__footer">
                    <a class="button button--small" href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/thread/' . (int) $thread['post_no'])) ?>">
                        Open thread · <?= (int) $thread['reply_count'] ?> replies
                    </a>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Board pages">
            <?php for ($number = 1; $number <= $pages; $number++): ?>
                <?php if ($number === $page): ?>
                    <span aria-current="page"><?= $number ?></span>
                <?php else: ?>
                    <a href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/?page=' . $number)) ?>"><?= $number ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

