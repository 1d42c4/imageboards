<?php $fieldPrefix = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $formId) ?: 'post'; ?>
<form id="<?= $this->e($formId) ?>" class="post-form" action="<?= $this->e($action) ?>" method="post" enctype="multipart/form-data">
    <?= $this->csrfField() ?>
    <div class="honeypot" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>
    <div class="post-form__identity">
        <label class="visually-hidden" for="<?= $this->e($fieldPrefix) ?>-name">Name</label>
        <input id="<?= $this->e($fieldPrefix) ?>-name" type="text" name="name" maxlength="60" autocomplete="nickname" placeholder="Name">
        <label class="visually-hidden" for="<?= $this->e($fieldPrefix) ?>-subject">Subject</label>
        <input id="<?= $this->e($fieldPrefix) ?>-subject" type="text" name="subject" maxlength="120" placeholder="Subject">
    </div>
    <label class="visually-hidden" for="<?= $this->e($textareaId) ?>">Comment</label>
    <textarea
        id="<?= $this->e($textareaId) ?>"
        class="post-body-input"
        name="body"
        maxlength="12000"
        rows="3"
        placeholder="Comment"
    ></textarea>
    <div class="post-form__footer">
        <label class="post-form__file" for="<?= $this->e($fieldPrefix) ?>-image">
            <span>Image</span>
            <input id="<?= $this->e($fieldPrefix) ?>-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
        </label>
        <button type="submit"><?= $this->e($submitLabel) ?></button>
    </div>
    <p class="post-form__hint">Text or image required · JPEG, PNG, WebP · <code>[pgn]...[/pgn]</code></p>
</form>
