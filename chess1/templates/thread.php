<header class="board-heading">
    <h1>/<?= $this->e($board['slug']) ?>/ - <?= $this->e($board['title']) ?></h1>
    <div class="board-subtitle">Thread No. <?= (int) $thread['thread_no'] ?></div>
</header>

<?php if ($thread['locked']): ?>
    <div class="reply-mode reply-mode--locked">
        Thread locked
        <a href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/')) ?>">[Return]</a>
    </div>
<?php else: ?>
    <section id="reply" class="post-area post-area--reply" aria-label="Post a reply">
        <?= $this->partial('partials/post-form', [
            'action' => $this->url('/' . rawurlencode($board['slug']) . '/thread/' . (int) $thread['thread_no'] . '/replies'),
            'submitLabel' => 'Reply',
            'formId' => 'reply-form',
            'textareaId' => 'reply-body',
        ]) ?>
    </section>
    <div class="reply-mode">
        Posting mode: Reply
        <a href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/')) ?>">[Return]</a>
    </div>
<?php endif; ?>
<hr class="board-rule">

<?php
$originalPost = $thread['posts'][0] ?? null;
$replies = array_slice($thread['posts'], 1);
?>
<section class="thread">
    <?php if ($originalPost !== null): ?>
        <div class="thread__original">
            <?= $this->partial('partials/post', [
                'board' => $board,
                'post' => $originalPost,
                'currentModerator' => $currentModerator,
                'showReplyLink' => false,
            ]) ?>
        </div>
    <?php endif; ?>

    <?php if ($replies !== []): ?>
        <div class="thread__replies">
            <?php foreach ($replies as $reply): ?>
                <?= $this->partial('partials/post', [
                    'board' => $board,
                    'post' => $reply,
                    'currentModerator' => $currentModerator,
                    'showReplyLink' => false,
                ]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<nav class="thread-return">
    [<a href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/')) ?>">Return</a>]
    [<a href="#top">Top</a>]
</nav>
