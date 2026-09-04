<form id="<?= $this->e($formId) ?>" class="post-form" action="<?= $this->e($action) ?>" method="post" enctype="multipart/form-data">
    <?= $this->csrfField() ?>
    <div class="honeypot" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>
    <div class="form-row form-row--split">
        <label>
            <span>Name <small>optional</small></span>
            <input type="text" name="name" maxlength="60" placeholder="Anonymous" autocomplete="nickname">
        </label>
        <label>
            <span>Subject <small>optional</small></span>
            <input type="text" name="subject" maxlength="120" placeholder="Position, opening, or topic">
        </label>
    </div>
    <label>
        <span>Comment</span>
        <textarea
            id="<?= $this->e($textareaId) ?>"
            class="post-body-input"
            name="body"
            maxlength="12000"
            rows="7"
            placeholder="Share your analysis… Use [pgn]1. e4 e5 2. Nf3[/pgn] for notation."
        ></textarea>
    </label>
    <div class="form-row form-row--split form-row--compact">
        <label>
            <span>Image <small>JPEG, PNG, or WebP</small></span>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        </label>
        <label>
            <span>Deletion password <small>saved in this browser</small></span>
            <input class="deletion-password" type="password" name="password" maxlength="128" autocomplete="off">
        </label>
    </div>
    <div class="form-actions">
        <p>Text or an image is required. Images are cleaned and re-encoded.</p>
        <button class="button" type="submit"><?= $this->e($submitLabel) ?></button>
    </div>
</form>

