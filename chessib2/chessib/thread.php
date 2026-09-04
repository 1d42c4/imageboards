<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$thread_id = (int)($_GET['id'] ?? 0);
if ($thread_id < 1) {
    header('Location: index.php');
    exit;
}

$op = get_thread_op($thread_id);
if (!$op) {
    header('Location: index.php?error=' . urlencode('Thread not found'));
    exit;
}

$posts = get_posts_in_thread($thread_id);
$csrf = generate_csrf();
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$locked = (bool)$op['locked'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $op['subject'] ? e($op['subject']) . ' — ' : '' ?>Thread No.<?= $thread_id ?> — <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="thread-page">
<?php if (is_admin()): ?>
  <div class="admin-bar">
    <div class="container">
      <strong>Admin mode</strong>
      <a href="admin.php">Panel</a>
      <a href="admin.php?action=sticky&id=<?= $thread_id ?>">Toggle sticky</a>
      <a href="admin.php?action=lock&id=<?= $thread_id ?>">Toggle lock</a>
      <a href="admin.php?logout=1">Logout</a>
    </div>
  </div>
<?php endif; ?>

<header class="site-header">
  <div class="container">
    <div class="logo"><a href="index.php" style="color:inherit"><?= e(SITE_NAME) ?></a> <span>/chess/</span></div>
    <div class="tagline"><a href="index.php">← Back to board</a></div>
  </div>
</header>

<main class="container">
  <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>

  <!-- Reply form stays on top -->
  <?php if ($locked && !is_admin()): ?>
    <div class="alert alert-info">This thread is locked. You cannot reply.</div>
  <?php else: ?>
    <section class="post-form">
      <h2>Reply to thread</h2>
      <form action="post.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="parent" value="<?= $thread_id ?>">
        <div class="form-grid">
          <div class="form-row">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" maxlength="<?= MAX_NAME_LENGTH ?>" placeholder="<?= e(DEFAULT_NAME) ?>">
          </div>
          <div class="form-row">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" maxlength="32" autocomplete="off" placeholder="For deletion">
          </div>
          <div class="form-row full">
            <label for="comment">Comment <span style="color:var(--danger)">*</span></label>
            <textarea id="comment" name="comment" required maxlength="<?= MAX_COMMENT_LENGTH ?>"></textarea>
          </div>
          <?php if (ALLOW_IMAGES): ?>
          <div class="form-row full">
            <label for="file">Image (optional)</label>
            <input type="file" id="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
          </div>
          <?php endif; ?>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn">Post Reply</button>
          <span class="hint">**bold** *italic* ||spoiler|| &gt;greentext &gt;&gt;123</span>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <!-- Original post, then replies -->
  <?php foreach ($posts as $p): ?>
    <article class="post<?= $p['parent'] == 0 ? ' op' : '' ?>" id="p<?= (int)$p['id'] ?>">
      <?php if ($p['thumb']): ?>
        <div class="thumb">
          <a href="uploads/<?= e($p['file']) ?>" target="_blank">
            <img src="thumbs/<?= e($p['thumb']) ?>" alt="" loading="lazy">
          </a>
        </div>
      <?php endif; ?>
      <div class="post-body">
        <div class="post-header">
          <?php if ($p['subject'] && $p['parent'] == 0): ?>
            <span class="subject"><?= e($p['subject']) ?></span>
          <?php endif; ?>
          <span class="name"><?= e($p['name']) ?></span>
          <?php if ($p['trip']): ?><span class="trip"><?= e($p['trip']) ?></span><?php endif; ?>
          <span class="post-id">
            <a href="#p<?= (int)$p['id'] ?>">No.<?= (int)$p['id'] ?></a>
          </span>
          <span title="<?= date(DATE_FORMAT, (int)$p['timestamp']) ?>"><?= time_ago((int)$p['timestamp']) ?></span>
          <?php if ($p['parent'] == 0 && $p['stickied']): ?><span style="color:var(--accent)">📌</span><?php endif; ?>
          <?php if ($p['parent'] == 0 && $p['locked']): ?><span style="color:var(--warning)">🔒</span><?php endif; ?>
          <?php if (is_admin()): ?>
            <a href="admin.php?action=delete&id=<?= (int)$p['id'] ?>" style="color:var(--danger)" onclick="return confirm('Delete this post?')">Delete</a>
          <?php endif; ?>
        </div>
        <?php if ($p['file']): ?>
          <div class="file-info">
            File: <a href="uploads/<?= e($p['file']) ?>" target="_blank"><?= e($p['file_orig']) ?></a>
            (<?= human_size((int)$p['file_size']) ?>, <?= (int)$p['image_w'] ?>×<?= (int)$p['image_h'] ?>)
          </div>
        <?php endif; ?>
        <div class="post-comment"><?= format_comment($p['comment'], $thread_id) ?></div>
      </div>
    </article>
  <?php endforeach; ?>

  <!-- Compact delete form at bottom -->
  <section class="post-form" style="margin-top:1.5rem; max-width:320px">
    <h2>Delete a post</h2>
    <form action="post.php" method="post">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="delete">
      <div class="form-grid">
        <div class="form-row">
          <label for="del_id">Post #</label>
          <input type="text" id="del_id" name="post_id" required pattern="\d+" placeholder="123">
        </div>
        <div class="form-row">
          <label for="del_pass">Password</label>
          <input type="password" id="del_pass" name="password" required>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-danger">Delete</button>
      </div>
    </form>
  </section>
</main>

<footer class="site-footer">
  <div><a href="index.php">← Back to /chess/</a></div>
</footer>
</body>
</html>
