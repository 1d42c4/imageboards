<section class="auth-card">
    <div class="auth-card__mark" aria-hidden="true">♜</div>
    <p class="eyebrow">Staff area</p>
    <h1>Moderator login</h1>
    <p>Use the credentials created by the command-line installer.</p>
    <form class="stack-form" action="<?= $this->e($this->url('/mod/login')) ?>" method="post">
        <?= $this->csrfField() ?>
        <label>
            <span>Username</span>
            <input type="text" name="username" maxlength="60" autocomplete="username" required autofocus>
        </label>
        <label>
            <span>Password</span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="button" type="submit">Enter moderator desk</button>
    </form>
</section>

