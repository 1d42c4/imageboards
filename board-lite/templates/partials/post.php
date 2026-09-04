<?php
$isOp = $post['thread_id'] === null;
$isDeleted = (int) $post['is_deleted'] === 1;
$permalink = $this->postUrl($board['slug'], $post['thread_no'], $post['post_no']);
?>
<article id="p<?= (int) $post['post_no'] ?>" class="post <?= $isOp ? 'post--op' : 'post--reply' ?><?= $isDeleted ? ' post--deleted' : '' ?>">
    <header class="post__header">
        <div class="post__identity">
            <?php if (!$isDeleted && ($post['subject'] ?? '') !== ''): ?>
                <strong class="post__subject"><?= $this->e($post['subject']) ?></strong>
            <?php endif; ?>
            <strong class="post__name"><?= $this->e($post['name']) ?></strong>
            <time datetime="<?= $this->e(date(DATE_ATOM, (int) $post['created_at'])) ?>">
                <?= $this->e($this->time($post['created_at'])) ?>
            </time>
        </div>
        <div class="post__meta">
            <?php if ($isOp && (int) $post['sticky'] === 1): ?><span class="badge">Pinned</span><?php endif; ?>
            <?php if ($isOp && (int) $post['locked'] === 1): ?><span class="badge badge--locked">Locked</span><?php endif; ?>
            <a
                class="post__number<?= $quoteEnabled ? ' quote-link' : '' ?>"
                href="<?= $this->e($quoteEnabled ? '#reply' : $permalink) ?>"
                <?php if ($quoteEnabled): ?>data-quote="&gt;&gt;<?= (int) $post['post_no'] ?>"<?php endif; ?>
            >No. <?= (int) $post['post_no'] ?></a>
        </div>
    </header>

    <?php if ($isDeleted): ?>
        <p class="post__deleted-message">Post deleted.</p>
    <?php else: ?>
        <?php if (($post['stored_name'] ?? null) !== null): ?>
            <figure class="attachment">
                <figcaption>
                    <a href="<?= $this->e($this->mediaUrl('original', $post['stored_name'])) ?>">
                        <?= $this->e($post['original_name']) ?>
                    </a>
                    <span>
                        <?= $this->e($this->bytes($post['byte_size'])) ?>,
                        <?= (int) $post['width'] ?>×<?= (int) $post['height'] ?>
                    </span>
                </figcaption>
                <a href="<?= $this->e($this->mediaUrl('original', $post['stored_name'])) ?>">
                    <img
                        src="<?= $this->e($this->mediaUrl('thumb', $post['thumb_name'])) ?>"
                        width="<?= (int) $post['thumb_width'] ?>"
                        height="<?= (int) $post['thumb_height'] ?>"
                        alt="Attachment: <?= $this->e($post['original_name']) ?>"
                        loading="lazy"
                    >
                </a>
            </figure>
        <?php endif; ?>

        <?php if ($post['body'] !== ''): ?>
            <div class="post__body"><?= $this->body($post['body'], $board['slug']) ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (($post['backlinks'] ?? []) !== []): ?>
        <nav class="backlinks" aria-label="Replies citing this post">
            <span>Referenced by</span>
            <?php foreach ($post['backlinks'] as $backlink): ?>
                <a href="<?= $this->e($this->postUrl($backlink['board_slug'], $backlink['thread_no'], $backlink['post_no'])) ?>">
                    &gt;&gt;<?= $this->e($backlink['board_slug']) ?>/<?= (int) $backlink['post_no'] ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if (!$isDeleted): ?>
        <div class="post__actions">
            <a href="<?= $this->e($permalink) ?>">Permalink</a>

            <details>
                <summary>Delete</summary>
                <form action="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/post/' . (int) $post['post_no'] . '/delete')) ?>" method="post">
                    <?= $this->csrfField() ?>
                    <input class="deletion-password" type="password" name="password" maxlength="128" placeholder="Deletion password" required>
                    <input class="honeypot" type="text" name="website" tabindex="-1" autocomplete="off">
                    <button class="button button--danger button--small" type="submit">Delete post</button>
                </form>
            </details>

            <details>
                <summary>Report</summary>
                <form action="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/post/' . (int) $post['post_no'] . '/report')) ?>" method="post">
                    <?= $this->csrfField() ?>
                    <input type="text" name="reason" maxlength="500" placeholder="Reason for moderator review" required>
                    <input class="honeypot" type="text" name="website" tabindex="-1" autocomplete="off">
                    <button class="button button--small" type="submit">Send report</button>
                </form>
            </details>

            <?php if ($currentModerator !== null): ?>
                <details class="moderator-actions">
                    <summary>Moderate</summary>
                    <div class="moderator-actions__forms">
                        <form action="<?= $this->e($this->url('/mod/' . rawurlencode($board['slug']) . '/post/' . (int) $post['post_no'] . '/delete')) ?>" method="post">
                            <?= $this->csrfField() ?>
                            <button class="button button--danger button--small" type="submit">Remove</button>
                        </form>
                        <form action="<?= $this->e($this->url('/mod/' . rawurlencode($board['slug']) . '/post/' . (int) $post['post_no'] . '/ban')) ?>" method="post">
                            <?= $this->csrfField() ?>
                            <input type="text" name="reason" maxlength="500" placeholder="Ban reason" required>
                            <select name="duration" aria-label="Ban duration">
                                <option value="3600">1 hour</option>
                                <option value="86400" selected>1 day</option>
                                <option value="604800">1 week</option>
                                <option value="2592000">30 days</option>
                                <option value="0">Permanent</option>
                            </select>
                            <button class="button button--small" type="submit">Ban author</button>
                        </form>
                        <?php if ($isOp): ?>
                            <form action="<?= $this->e($this->url('/mod/' . rawurlencode($board['slug']) . '/thread/' . (int) $post['post_no'] . '/lock')) ?>" method="post">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="enabled" value="<?= (int) $post['locked'] === 1 ? '0' : '1' ?>">
                                <button class="button button--small" type="submit">
                                    <?= (int) $post['locked'] === 1 ? 'Unlock' : 'Lock' ?>
                                </button>
                            </form>
                            <form action="<?= $this->e($this->url('/mod/' . rawurlencode($board['slug']) . '/thread/' . (int) $post['post_no'] . '/sticky')) ?>" method="post">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="enabled" value="<?= (int) $post['sticky'] === 1 ? '0' : '1' ?>">
                                <button class="button button--small" type="submit">
                                    <?= (int) $post['sticky'] === 1 ? 'Unpin' : 'Pin' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</article>

