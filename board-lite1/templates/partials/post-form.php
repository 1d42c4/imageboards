<?php $fieldPrefix = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $formId) ?: 'post'; ?>
<form id="<?= $this->e($formId) ?>" class="post-form" action="<?= $this->e($action) ?>" method="post" enctype="multipart/form-data">
    <?= $this->csrfField() ?>
    <div class="honeypot" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>
    <div class="post-form__row">
        <label for="<?= $this->e($fieldPrefix) ?>-name">Name</label>
        <input id="<?= $this->e($fieldPrefix) ?>-name" type="text" name="name" maxlength="60" placeholder="Anonymous" autocomplete="nickname">
    </div>
    <div class="post-form__row">
        <label for="<?= $this->e($fieldPrefix) ?>-subject">Subject</label>
        <input id="<?= $this->e($fieldPrefix) ?>-subject" type="text" name="subject" maxlength="120">
    </div>
    <div class="post-form__row post-form__row--comment">
        <label for="<?= $this->e($textareaId) ?>">Comment</label>
        <textarea
            id="<?= $this->e($textareaId) ?>"
            class="post-body-input"
            name="body"
            maxlength="12000"
            rows="4"
        ></textarea>
    </div>
    <div class="post-form__row">
        <label for="<?= $this->e($fieldPrefix) ?>-image">Image</label>
        <span class="post-form__control">
            <input id="<?= $this->e($fieldPrefix) ?>-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <small>JPEG, PNG, or WebP</small>
        </span>
    </div>
    <div class="post-form__row">
        <label for="<?= $this->e($fieldPrefix) ?>-password">Password</label>
        <span class="post-form__control">
            <input id="<?= $this->e($fieldPrefix) ?>-password" class="deletion-password" type="password" name="password" maxlength="128" autocomplete="off">
            <small>for deleting your post</small>
        </span>
    </div>
    <div class="post-form__row post-form__row--submit">
        <span aria-hidden="true"></span>
        <button class="button" type="submit"><?= $this->e($submitLabel) ?></button>
    </div>
</form>
