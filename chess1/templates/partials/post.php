<?php
$isOp = $post['thread_id'] === null;
$isDeleted = (int) $post['is_deleted'] === 1;
$threadUrl = $this->url('/' . rawurlencode($board['slug']) . '/thread/' . (int) $post['thread_no']);
$captchaPurpose = sprintf('report:%s:%d', $board['slug'], (int) $post['post_no']);
$reportCaptcha = $isDeleted ? null : $this->captchaChallenge($captchaPurpose);
?>
<article id="p<?= (int) $post['post_no'] ?>" class="post <?= $isOp ? 'post--op' : 'post--reply' ?><?= $isDeleted ? ' post--deleted' : '' ?>">
    <?php if (!$isDeleted && ($post['stored_name'] ?? null) !== null): ?>
        <figure class="attachment">
            <figcaption>
                File:
                <a href="<?= $this->e($this->mediaUrl('original', $post['stored_name'])) ?>">
                    <?= $this->e($post['original_name']) ?>
                </a>
                <span>
                    (<?= $this->e($this->bytes($post['byte_size'])) ?>,
                    <?= (int) $post['width'] ?>×<?= (int) $post['height'] ?>)
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

    <header class="post__header">
        <?php if (!$isDeleted && ($post['subject'] ?? '') !== ''): ?>
            <strong class="post__subject"><?= $this->e($post['subject']) ?></strong>
        <?php endif; ?>
        <strong class="post__name"><?= $this->e($post['name']) ?></strong>
        <time datetime="<?= $this->e(date(DATE_ATOM, (int) $post['created_at'])) ?>">
            <?= $this->e(date('m/d/y', (int) $post['created_at'])) ?>
        </time>
        <?php if ($isOp && (int) $post['sticky'] === 1): ?>
            <span class="post__status" title="Sticky thread">Sticky</span>
        <?php endif; ?>
        <?php if ($isOp && (int) $post['locked'] === 1): ?>
            <span class="post__status" title="Locked thread">Locked</span>
        <?php endif; ?>
        <?php if ($isOp && ($showReplyLink ?? false)): ?>
            <a class="post__reply-link" href="<?= $this->e($threadUrl) ?>">[Reply]</a>
        <?php endif; ?>

        <?php if (!$isDeleted): ?>
            <details class="post-menu">
                <summary title="Post actions" aria-label="Post actions">▶</summary>
                <div class="post-menu__panel">
                    <div class="post__actions">
                        <details>
                            <summary>Report</summary>
                            <form action="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/post/' . (int) $post['post_no'] . '/report')) ?>" method="post">
                                <?= $this->csrfField() ?>
                                <input type="text" name="reason" maxlength="500" placeholder="Reason" required>
                                <label class="report-captcha">
                                    <span>Human check: <strong><?= $this->e($reportCaptcha['question']) ?></strong></span>
                                    <input
                                        type="text"
                                        name="captcha_answer"
                                        inputmode="numeric"
                                        pattern="[0-9]+"
                                        maxlength="2"
                                        autocomplete="off"
                                        aria-label="Human-check answer"
                                        required
                                    >
                                </label>
                                <input type="hidden" name="captcha_token" value="<?= $this->e($reportCaptcha['token']) ?>">
                                <input class="honeypot" type="text" name="website" tabindex="-1" autocomplete="off">
                                <button class="button button--small" type="submit">Send report</button>
                            </form>
                        </details>

                        <?php if ($currentModerator !== null): ?>
                            <details class="moderator-actions">
                                <summary>Moderate</summary>
                                <div class="moderator-actions__forms">
                                    <details class="moderator-edit">
                                        <summary>Edit post</summary>
                                        <form
                                            class="moderator-edit-form"
                                            action="<?= $this->e($this->url('/mod/' . rawurlencode($board['slug']) . '/post/' . (int) $post['post_no'] . '/edit')) ?>"
                                            method="post"
                                            enctype="multipart/form-data"
                                        >
                                            <?= $this->csrfField() ?>
                                            <label>
                                                Name
                                                <input type="text" name="name" maxlength="60" value="<?= $this->e($post['name']) ?>">
                                            </label>
                                            <label>
                                                Subject
                                                <input type="text" name="subject" maxlength="120" value="<?= $this->e($post['subject'] ?? '') ?>">
                                            </label>
                                            <label>
                                                Comment
                                                <textarea name="body" maxlength="12000" rows="5"><?= $this->e($post['body']) ?></textarea>
                                            </label>
                                            <label>
                                                <?= ($post['stored_name'] ?? null) !== null ? 'Replace image' : 'Add image' ?>
                                                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                                            </label>
                                            <?php if (($post['stored_name'] ?? null) !== null): ?>
                                                <label class="moderator-edit-form__check">
                                                    <input type="checkbox" name="remove_image" value="1">
                                                    Remove current image
                                                </label>
                                            <?php endif; ?>
                                            <button class="button button--small" type="submit">Save changes</button>
                                        </form>
                                    </details>

                                    <form action="<?= $this->e($this->url('/mod/' . rawurlencode($board['slug']) . '/post/' . (int) $post['post_no'] . '/delete')) ?>" method="post">
                                        <?= $this->csrfField() ?>
                                        <button class="button button--danger button--small" type="submit">Remove</button>
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
                                                <?= (int) $post['sticky'] === 1 ? 'Unsticky' : 'Sticky' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                </div>
            </details>
        <?php endif; ?>
    </header>

    <?php if ($isDeleted): ?>
        <p class="post__deleted-message">Post deleted.</p>
    <?php elseif ($post['body'] !== ''): ?>
        <div class="post__body"><?= $this->body($post['body'], $board['slug']) ?></div>
    <?php endif; ?>
</article>
