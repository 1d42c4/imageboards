<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= $this->e($this->url('/')) ?>">Boards</a>
    <span aria-hidden="true">›</span>
    <a href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/')) ?>">
        /<?= $this->e($board['slug']) ?>/
    </a>
    <span aria-hidden="true">›</span>
    <span>Thread <?= (int) $thread['thread_no'] ?></span>
</nav>

<header class="thread-heading">
    <div>
        <h1><?= $this->e($board['title']) ?> — Thread No. <?= (int) $thread['thread_no'] ?></h1>
    </div>
    <div class="thread-heading__badges">
        <?php if ($thread['sticky']): ?><span class="badge">Pinned</span><?php endif; ?>
        <?php if ($thread['locked']): ?><span class="badge badge--locked">Locked</span><?php endif; ?>
    </div>
</header>

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

<?php if ($thread['locked']): ?>
    <div class="locked-notice">
        <div>
            <strong>This thread is locked.</strong>
            <p>Existing posts remain available, but new replies are closed.</p>
        </div>
    </div>
<?php else: ?>
    <details id="reply" class="composer composer--reply">
        <summary>[Post a reply]</summary>
        <?= $this->partial('partials/post-form', [
            'action' => $this->url('/' . rawurlencode($board['slug']) . '/thread/' . (int) $thread['thread_no'] . '/replies'),
            'submitLabel' => 'Post reply',
            'formId' => 'reply-form',
            'textareaId' => 'reply-body',
        ]) ?>
    </details>
<?php endif; ?>
