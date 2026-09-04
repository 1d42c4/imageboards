<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['chessib_admin']);
    header('Location: index.php');
    exit;
}

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    if (ADMIN_PASSWORD !== '' && hash_equals(ADMIN_PASSWORD, (string)$_POST['admin_pass'])) {
        $_SESSION['chessib_admin'] = true;
        header('Location: admin.php');
        exit;
    }
    $login_error = 'Wrong password';
}

// Actions (require admin)
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($action && is_admin()) {
    $db = get_db();
    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        if ($post) {
            if ((int)$post['parent'] === 0) {
                $all = get_posts_in_thread($id);
                foreach ($all as $p) {
                    delete_post_files($p);
                    $db->prepare('DELETE FROM posts WHERE id = ?')->execute([$p['id']]);
                }
                header('Location: index.php?success=' . urlencode('Thread deleted'));
            } else {
                delete_post_files($post);
                $db->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
                header('Location: thread.php?id=' . $post['parent'] . '&success=' . urlencode('Post deleted'));
            }
            exit;
        }
    }
    if ($action === 'sticky' && $id > 0) {
        $db->prepare('UPDATE posts SET stickied = 1 - stickied WHERE id = ? AND parent = 0')->execute([$id]);
        header("Location: thread.php?id=$id");
        exit;
    }
    if ($action === 'lock' && $id > 0) {
        $db->prepare('UPDATE posts SET locked = 1 - locked WHERE id = ? AND parent = 0')->execute([$id]);
        header("Location: thread.php?id=$id");
        exit;
    }
    if ($action === 'ban' && $id > 0) {
        $stmt = $db->prepare('SELECT ip FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        $ip = $stmt->fetchColumn();
        if ($ip) {
            $db->prepare('INSERT INTO bans (ip, reason, expires, created) VALUES (?, ?, 0, ?)')
               ->execute([$ip, 'Banned by admin', time()]);
            header('Location: admin.php?msg=' . urlencode("Banned IP $ip"));
            exit;
        }
    }
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin — <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
  <div class="container">
    <div class="logo"><a href="index.php" style="color:inherit"><?= e(SITE_NAME) ?></a> <span>Admin</span></div>
  </div>
</header>

<main class="container">
  <?php if (!is_admin()): ?>
    <section class="post-form" style="max-width:400px;margin:2rem auto">
      <h2>Moderator Login</h2>
      <?php if (!empty($login_error)): ?>
        <div class="alert alert-error"><?= e($login_error) ?></div>
      <?php endif; ?>
      <?php if (ADMIN_PASSWORD === ''): ?>
        <div class="alert alert-info">Admin password is not configured in config.php</div>
      <?php else: ?>
        <form method="post">
          <div class="form-row">
            <label for="admin_pass">Password</label>
            <input type="password" id="admin_pass" name="admin_pass" required autofocus>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn">Login</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <?php if ($msg): ?>
      <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>
    <div class="alert alert-info">
      You are logged in as admin. Use the links on threads/posts to sticky, lock or delete.
      You can also ban by IP from a post (add ?action=ban&id=POSTID).
    </div>
    <p><a href="index.php" class="btn">← Back to board</a>
       <a href="admin.php?logout=1" class="btn btn-secondary">Logout</a></p>

    <h2 style="margin-top:2rem">Recent posts</h2>
    <?php
    $db = get_db();
    $recent = $db->query('SELECT id, parent, name, subject, comment, timestamp, ip FROM posts ORDER BY id DESC LIMIT 30')->fetchAll();
    ?>
    <table style="width:100%;border-collapse:collapse;font-size:0.9rem">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--border)">
          <th style="padding:0.5rem">ID</th>
          <th>Parent</th>
          <th>Name</th>
          <th>Subject / Comment</th>
          <th>IP</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:0.5rem"><a href="thread.php?id=<?= $r['parent'] ?: $r['id'] ?>#p<?= $r['id'] ?>"><?= $r['id'] ?></a></td>
            <td><?= $r['parent'] ?: 'OP' ?></td>
            <td><?= e($r['name']) ?></td>
            <td><?= e(mb_substr($r['subject'] ?: $r['comment'], 0, 60)) ?></td>
            <td><code><?= e($r['ip']) ?></code></td>
            <td>
              <a href="admin.php?action=delete&id=<?= $r['id'] ?>" style="color:var(--danger)" onclick="return confirm('Delete?')">Del</a>
              · <a href="admin.php?action=ban&id=<?= $r['id'] ?>" onclick="return confirm('Ban this IP?')">Ban</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>
</body>
</html>
