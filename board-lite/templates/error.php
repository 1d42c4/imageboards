<section class="error-page">
    <span class="error-page__piece" aria-hidden="true">♚</span>
    <p class="eyebrow">Error <?= (int) $status ?></p>
    <h1><?= $this->e($title) ?></h1>
    <p><?= $this->e($message) ?></p>
    <div class="error-page__actions">
        <a class="button" href="<?= $this->e($this->url('/')) ?>">Return to the boards</a>
        <button class="button button--quiet history-back" type="button">Go back</button>
    </div>
</section>

