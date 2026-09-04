<?= $this->partial('mod/_nav', ['currentModerator' => $currentModerator]) ?>

<header class="admin-heading">
    <div>
        <p class="eyebrow">Access control</p>
        <h1>Bans</h1>
        <p>Addresses are transformed into keyed hashes; raw visitor IP addresses are not retained.</p>
    </div>
</header>

<section class="admin-section admin-section--form">
    <h2>Create an exact-address ban</h2>
    <form class="stack-form stack-form--horizontal" action="<?= $this->e($this->url('/mod/bans')) ?>" method="post">
        <?= $this->csrfField() ?>
        <label>
            <span>IPv4 or IPv6 address</span>
            <input type="text" name="ip" placeholder="203.0.113.10" required>
        </label>
        <label>
            <span>Reason</span>
            <input type="text" name="reason" maxlength="500" required>
        </label>
        <label>
            <span>Duration</span>
            <select name="duration">
                <option value="3600">1 hour</option>
                <option value="86400" selected>1 day</option>
                <option value="604800">1 week</option>
                <option value="2592000">30 days</option>
                <option value="0">Permanent</option>
            </select>
        </label>
        <button class="button" type="submit">Create ban</button>
    </form>
</section>

<section class="admin-section">
    <div class="section-heading"><div><p class="eyebrow">History</p><h2>Ban records</h2></div></div>
    <?php if ($bans === []): ?>
        <p class="muted">No bans have been created.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Identity</th><th>Reason</th><th>Created</th><th>Expires</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($bans as $ban): ?>
                    <tr class="<?= $ban['expires_at'] !== null && (int) $ban['expires_at'] <= time() ? 'is-expired' : '' ?>">
                        <td><code><?= $this->e(substr($ban['ip_hash'], 0, 12)) ?>…</code></td>
                        <td><?= $this->e($ban['reason']) ?><small>by <?= $this->e($ban['moderator_name']) ?></small></td>
                        <td><?= $this->e($this->time($ban['created_at'])) ?></td>
                        <td><?= $ban['expires_at'] === null ? 'Permanent' : $this->e($this->time($ban['expires_at'])) ?></td>
                        <td>
                            <form action="<?= $this->e($this->url('/mod/bans/' . (int) $ban['id'] . '/delete')) ?>" method="post">
                                <?= $this->csrfField() ?>
                                <button class="text-button text-button--danger" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

