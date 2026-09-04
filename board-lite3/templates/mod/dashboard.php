<?= $this->partial('mod/_nav', ['currentModerator' => $currentModerator]) ?>

<header class="admin-heading">
    <div>
        <p class="eyebrow">Moderator desk</p>
        <h1>Community overview</h1>
    </div>
</header>

<section class="metric-grid" aria-label="Community counts">
    <article><strong><?= (int) $counts['reports'] ?></strong><span>Open reports</span></article>
    <article><strong><?= (int) $counts['posts'] ?></strong><span>Total posts</span></article>
</section>

<section class="admin-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Queue</p>
            <h2>Open reports</h2>
        </div>
    </div>

    <?php if ($reports === []): ?>
        <div class="empty-state empty-state--compact">
            <span aria-hidden="true">♙</span>
            <p>No reports are waiting.</p>
        </div>
    <?php else: ?>
        <div class="report-list">
            <?php foreach ($reports as $report): ?>
                <article class="report-card">
                    <div>
                        <p class="report-card__meta">
                            Report #<?= (int) $report['id'] ?> ·
                            <a href="<?= $this->e($this->postUrl($report['board_slug'], $report['thread_no'], $report['post_no'])) ?>">
                                /<?= $this->e($report['board_slug']) ?>/ No. <?= (int) $report['post_no'] ?>
                            </a>
                            · <?= $this->e($this->time($report['created_at'])) ?>
                        </p>
                        <strong><?= $this->e($report['reason']) ?></strong>
                        <?php if ((int) $report['is_deleted'] === 0 && $report['body'] !== ''): ?>
                            <p><?= $this->e(mb_strimwidth($report['body'], 0, 240, '…')) ?></p>
                        <?php endif; ?>
                    </div>
                    <form action="<?= $this->e($this->url('/mod/reports/' . (int) $report['id'] . '/dismiss')) ?>" method="post">
                        <?= $this->csrfField() ?>
                        <button class="button button--quiet button--small" type="submit">Dismiss</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Audit trail</p>
            <h2>Recent moderator actions</h2>
        </div>
    </div>
    <?php if ($logEntries === []): ?>
        <p class="muted">No moderator actions recorded yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Time</th><th>Moderator</th><th>Action</th><th>Context</th></tr></thead>
                <tbody>
                <?php foreach ($logEntries as $entry): ?>
                    <tr>
                        <td><?= $this->e($this->time($entry['created_at'])) ?></td>
                        <td><?= $this->e($entry['username']) ?></td>
                        <td><?= $this->e($entry['action']) ?></td>
                        <td>
                            <?php if (($entry['board_slug'] ?? null) !== null): ?>
                                /<?= $this->e($entry['board_slug']) ?>/
                            <?php endif; ?>
                            <?= $this->e($entry['details']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

