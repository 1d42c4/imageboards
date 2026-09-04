<?php $fieldPrefix = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $formId) ?: 'post'; ?>
<form id="<?= $this->e($formId) ?>" class="post-form" action="<?= $this->e($action) ?>" method="post" enctype="multipart/form-data">
    <?= $this->csrfField() ?>
    <div class="honeypot" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>
    <table class="post-form__table">
        <tbody>
        <tr>
            <th><label for="<?= $this->e($fieldPrefix) ?>-name">Name</label></th>
            <td>
                <input id="<?= $this->e($fieldPrefix) ?>-name" type="text" name="name" size="28" maxlength="60" autocomplete="nickname">
            </td>
        </tr>
        <tr>
            <th><label for="<?= $this->e($fieldPrefix) ?>-subject">Subject</label></th>
            <td>
                <input id="<?= $this->e($fieldPrefix) ?>-subject" type="text" name="subject" size="28" maxlength="120">
                <button type="submit"><?= $this->e($submitLabel) ?></button>
            </td>
        </tr>
        <tr>
            <th><label for="<?= $this->e($textareaId) ?>">Comment</label></th>
            <td>
                <textarea
                    id="<?= $this->e($textareaId) ?>"
                    class="post-body-input"
                    name="body"
                    maxlength="12000"
                    rows="4"
                    cols="38"
                ></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="<?= $this->e($fieldPrefix) ?>-image">File</label></th>
            <td>
                <input id="<?= $this->e($fieldPrefix) ?>-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </td>
        </tr>
        <tr>
            <th><label for="<?= $this->e($fieldPrefix) ?>-password">Password</label></th>
            <td>
                <input id="<?= $this->e($fieldPrefix) ?>-password" class="deletion-password" type="password" name="password" size="12" maxlength="128" autocomplete="off">
                <span class="unimportant">(For post deletion.)</span>
            </td>
        </tr>
        </tbody>
    </table>
    <ul class="post-form__rules">
        <li>JPEG, PNG, and WebP files are supported.</li>
        <li>Text or an image is required. Use <code>[pgn]...[/pgn]</code> for notation.</li>
    </ul>
</form>
