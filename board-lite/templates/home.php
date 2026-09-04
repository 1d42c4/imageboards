<section class="welcome">
    <div class="welcome__copy">
        <p class="eyebrow">A quiet corner for serious chess talk</p>
        <h1>Every position has a story.</h1>
        <p>
            Start a discussion, share a board image, or wrap game notation in
            <code>[pgn]</code> tags. No accounts are needed to participate.
        </p>
    </div>
    <div class="position-card" aria-label="Decorative chess position">
        <?php
        $pieces = ['♜', '', '♝', '♛', '', '♜', '♟', '♟', '', '', '♝', '♟', '', '', '♞', '', '', '', '♙', '', '', '', '', '', '', '♙', '♘', '', '', '', '', '', '', '', '', '', '♗', '', '', '', '♙', '♙', '', '', '', '♙', '♙', '♙', '♖', '', '♗', '♕', '♖', '♔', '', ''];
        foreach ($pieces as $piece):
        ?>
            <span><?= $piece ?></span>
        <?php endforeach; ?>
    </div>
</section>

<section class="section-heading">
    <div>
        <p class="eyebrow">Choose a board</p>
        <h2>Current discussions</h2>
    </div>
</section>

<?php if ($boards === []): ?>
    <div class="empty-state">
        <span aria-hidden="true">♔</span>
        <h2>No boards yet</h2>
        <p>The administrator can create the first board from the moderator desk.</p>
    </div>
<?php else: ?>
    <div class="board-grid">
        <?php foreach ($boards as $board): ?>
            <a class="board-card" href="<?= $this->e($this->url('/' . rawurlencode($board['slug']) . '/')) ?>">
                <span class="board-card__address">/<?= $this->e($board['slug']) ?>/</span>
                <h2><?= $this->e($board['title']) ?></h2>
                <p><?= $this->e($board['description']) ?></p>
                <span class="board-card__stats">
                    <?= (int) $board['thread_count'] ?> threads
                    <span aria-hidden="true">·</span>
                    <?= (int) $board['post_count'] ?> posts
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="principles">
    <article>
        <span aria-hidden="true">01</span>
        <h3>Built for conversation</h3>
        <p>Fast threads, familiar quote links, backlinks, and optional board images.</p>
    </article>
    <article>
        <span aria-hidden="true">02</span>
        <h3>Chess-friendly</h3>
        <p>PGN blocks stay readable, and screenshots are safely processed before display.</p>
    </article>
    <article>
        <span aria-hidden="true">03</span>
        <h3>Deliberately small</h3>
        <p>No framework, package manager, template engine, trackers, or third-party scripts.</p>
    </article>
</section>

