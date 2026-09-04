<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$total_threads = count_threads();
$total_pages = max(1, (int)ceil($total_threads / THREADS_PER_PAGE));
$page = min($page, $total_pages);
$threads = get_threads($page);

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

$csrf = generate_csrf();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(SITE_TITLE) ?> — <?= e(SITE_NAME) ?></title>
  <meta name="description" content="<?= e(SITE_DESCRIPTION) ?>">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if (is_admin()): ?>
  <div class="admin-bar">
    <div class="container">
      <strong>Admin mode</strong>
      <a href="admin.php">Panel</a>
      <a href="admin.php?logout=1">Logout</a>
    </div>
  </div>
<?php endif; ?>

<header class="site-header">
  <div class="container">
    <div class="logo"><?= e(SITE_NAME) ?> <span>/chess/</span></div>
    <div class="tagline">Discuss openings, tactics, endgames &amp; more</div>
  </div>
</header>

<main class="container">
  <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>

  <!-- New thread form -->
  <section class="post-form">
    <h2>Start a new thread</h2>
    <form action="post.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="parent" value="0">
      <div class="form-row">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" maxlength="<?= MAX_NAME_LENGTH ?>" placeholder="<?= e(DEFAULT_NAME) ?> (optional #trip)">
      </div>
      <div class="form-row">
        <label for="subject">Subject</label>
        <input type="text" id="subject" name="subject" maxlength="<?= MAX_SUBJECT_LENGTH ?>">
      </div>
      <div class="form-row">
        <label for="comment">Comment <span style="color:var(--danger)">*</span></label>
        <textarea id="comment" name="comment" required maxlength="<?= MAX_COMMENT_LENGTH ?>" placeholder="Share a position, ask about an opening, post a puzzle..."></textarea>
      </div>
      <?php if (ALLOW_IMAGES): ?>
      <div class="form-row">
        <label for="file">Image (optional)</label>
        <input type="file" id="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
        <div class="hint">Max <?= human_size(MAX_FILE_SIZE) ?> · JPG, PNG, GIF, WebP</div>
      </div>
      <?php endif; ?>
      <div class="form-row">
        <label for="password">Password (for deletion)</label>
        <input type="password" id="password" name="password" maxlength="32" autocomplete="off">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn">Post Thread</button>
        <span class="hint">Supports **bold**, *italic*, ||spoilers||, &gt;greentext, &gt;&gt;123</span>
      </div>
    </form>
  </section>

  <!-- Thread list -->
  <?php if (empty($threads)): ?>
    <div class="alert alert-info">No threads yet. Be the first to post about chess!</div>
  <?php else: ?>
    <?php foreach ($threads as $op): ?>
      <?php
        $replies = get_preview_replies((int)$op['id']);
        $reply_count = get_reply_count((int)$op['id']);
        $omitted = max(0, $reply_count - count($replies));
      ?>
      <article class="thread<?= $op['stickied'] ? ' sticky' : '' ?>">
        <div class="thread-op">
          <?php if ($op['thumb']): ?>
            <div class="thumb">
              <a href="uploads/<?= e($op['file']) ?>" target="_blank">
                <img src="thumbs/<?= e($op['thumb']) ?>" alt="" width="<?= min((int)$op['image_w'], THUMB_MAX_W) ?>" loading="lazy">
              </a>
            </div>
          <?php endif; ?>
          <div class="thread-body">
            <div class="post-header">
              <?php if ($op['subject']): ?>
                <span class="subject"><?= e($op['subject']) ?></span>
              <?php endif; ?>
              <span class="name"><?= e($op['name']) ?></span>
              <?php if ($op['trip']): ?><span class="trip"><?= e($op['trip']) ?></span><?php endif; ?>
              <span class="post-id"><a href="thread.php?id=<?= (int)$op['id'] ?>#p<?= (int)$op['id'] ?>">No.<?= (int)$op['id'] ?></a></span>
              <span title="<?= date(DATE_FORMAT, (int)$op['timestamp']) ?>"><?= time_ago((int)$op['timestamp']) ?></span>
              <?php if ($op['stickied']): ?><span style="color:var(--accent)">📌 Sticky</span><?php endif; ?>
              <?php if ($op['locked']): ?><span style="color:var(--warning)">🔒 Locked</span><?php endif; ?>
            </div>
            <?php if ($op['file']): ?>
              <div class="file-info">
                File: <a href="uploads/<?= e($op['file']) ?>" target="_blank"><?= e($op['file_orig']) ?></a>
                (<?= human_size((int)$op['file_size']) ?>, <?= (int)$op['image_w'] ?>×<?= (int)$op['image_h'] ?>)
              </div>
            <?php endif; ?>
            <div class="post-comment"><?= format_comment($op['comment'], (int)$op['id']) ?></div>
          </div>
        </div>

        <?php if ($replies || $omitted): ?>
          <div class="reply-preview">
            <?php if ($omitted > 0): ?>
              <div class="hint" style="margin-bottom:0.5rem"><?= $omitted ?> <?= $omitted === 1 ? 'reply' : 'replies' ?> omitted. <a href="thread.php?id=<?= (int)$op['id'] ?>">View thread</a></div>
            <?php endif; ?>
            <?php foreach ($replies as $r): ?>
              <div class="post" id="p<?= (int)$r['id'] ?>">
                <?php if ($r['thumb']): ?>
                  <div class="thumb">
                    <a href="uploads/<?= e($r['file']) ?>" target="_blank">
                      <img src="thumbs/<?= e($r['thumb']) ?>" alt="" loading="lazy">
                    </a>
                  </div>
                <?php endif; ?>
                <div>
                  <div class="post-header">
                    <span class="name"><?= e($r['name']) ?></span>
                    <?php if ($r['trip']): ?><span class="trip"><?= e($r['trip']) ?></span><?php endif; ?>
                    <span class="post-id"><a href="thread.php?id=<?= (int)$op['id'] ?>#p<?= (int)$r['id'] ?>">No.<?= (int)$r['id'] ?></a></span>
                    <span title="<?= date(DATE_FORMAT, (int)$r['timestamp']) ?>"><?= time_ago((int)$r['timestamp']) ?></span>
                  </div>
                  <div class="post-comment"><?= format_comment($r['comment'], (int)$op['id']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="thread-meta">
          <a href="thread.php?id=<?= (int)$op['id'] ?>"><strong>View thread</strong></a>
          <span><?= $reply_count ?> <?= $reply_count === 1 ? 'reply' : 'replies' ?></span>
          <span>Last activity <?= time_ago((int)$op['bumped']) ?></span>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($total_pages > 1): ?>
    <nav class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>">← Prev</a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?>">Next →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</main>

<footer class="site-footer">
  <div><?= e(SITE_NAME) ?> · Lightweight chess imageboard · Powered by PHP + SQLite</div>
  <div style="margin-top:0.4rem"><a href="admin.php">Mod</a></div>
</footer>
</body>
</html>
