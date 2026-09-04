<header class="board-heading">
    <h1>/<?= $this->e($board['slug']) ?>/ - <?= $this->e($board['title']) ?></h1>
    <?php if ($board['description'] !== ''): ?>
        <div class="board-subtitle"><?= $this->e($board['description']) ?></div>
    <?php endif; ?>
</header>

<section class="new-post" aria-label="Start a new thread">
    <button
        class="new-post__toggle"
        type="button"
        aria-expanded="false"
        aria-controls="new-thread-panel"
        data-post-form-toggle
    >New</button>
    <div id="new-thread-panel" class="new-post__panel" hidden>
        <?= $this->partial('partials/post-form', [
            'action' => $this->url('/' . rawurlencode($board['slug']) . '/threads'),
            'submitLabel' => 'Post',
            'formId' => 'new-thread',
            'textareaId' => 'new-thread-body',
        ]) ?>
    </div>
</section>
<hr class="board-rule">

<?php if ($threads === []): ?>
    <div class="empty-state">
        <h2>No threads yet</h2>
        <p>Start the first discussion on this board.</p>
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
                        <?= $omitted ?> <?= $omitted === 1 ? 'post' : 'posts' ?> omitted.
                        <a href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/thread/' . (int) $thread['post_no'])) ?>">
                            Click Reply to view
                        </a>
                    </p>
                <?php endif; ?>

                <?php if ($thread['replies'] !== []): ?>
                    <div class="thread-preview__replies" aria-label="Latest replies">
                        <?php foreach ($thread['replies'] as $reply): ?>
                            <?= $this->partial('partials/post', [
                                'board' => $board,
                                'post' => $reply,
                                'currentModerator' => $currentModerator,
                                'quoteEnabled' => false,
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
