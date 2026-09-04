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
    <div class="reply-mode">
        Posting mode: Reply
        <a href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/')) ?>">[Return]</a>
    </div>
    <section id="reply" class="post-area" aria-label="Post a reply">
        <?= $this->partial('partials/post-form', [
            'action' => $this->url('/' . rawurlencode($board['slug']) . '/thread/' . (int) $thread['thread_no'] . '/replies'),
            'submitLabel' => 'New Reply',
            'formId' => 'reply-form',
            'textareaId' => 'reply-body',
        ]) ?>
    </section>
<?php endif; ?>
<hr class="board-rule">

<section class="thread">
    <?php foreach ($thread['posts'] as $post): ?>
        <?= $this->partial('partials/post', [
            'board' => $board,
            'post' => $post,
            'currentModerator' => $currentModerator,
            'quoteEnabled' => !$thread['locked'],
        ]) ?>
    <?php endforeach; ?>
</section>

<nav class="thread-return">
    [<a href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/')) ?>">Return</a>]
    [<a href="#top">Top</a>]
</nav>
