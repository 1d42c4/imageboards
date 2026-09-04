<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verify_csrf($_POST['csrf'] ?? null)) {
    header('Location: index.php?error=' . urlencode('Invalid CSRF token. Please try again.'));
    exit;
}

$ip = client_ip();
$ban = is_banned($ip);
if ($ban) {
    $msg = 'You are banned' . ($ban['reason'] ? ': ' . $ban['reason'] : '');
    header('Location: index.php?error=' . urlencode($msg));
    exit;
}

$action = $_POST['action'] ?? 'post';

// ---------- DELETE ----------
if ($action === 'delete') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    $password = (string)($_POST['password'] ?? '');

    if ($post_id < 1 || $password === '') {
        header('Location: index.php?error=' . urlencode('Missing post ID or password'));
        exit;
    }

    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        header('Location: index.php?error=' . urlencode('Post not found'));
        exit;
    }

    $ok = false;
    if (is_admin()) {
        $ok = true;
    } elseif ($post['password'] !== '' && password_verify($password, $post['password'])) {
        $ok = true;
    }

    if (!$ok) {
        $redir = $post['parent'] ? 'thread.php?id=' . $post['parent'] : 'index.php';
        header('Location: ' . $redir . '&error=' . urlencode('Wrong password'));
        exit;
    }

    // If OP, delete whole thread
    if ((int)$post['parent'] === 0) {
        $thread_posts = get_posts_in_thread($post_id);
        foreach ($thread_posts as $tp) {
            delete_post_files($tp);
            $db->prepare('DELETE FROM posts WHERE id = ?')->execute([$tp['id']]);
        }
        header('Location: index.php?success=' . urlencode('Thread deleted'));
        exit;
    } else {
        delete_post_files($post);
        $db->prepare('DELETE FROM posts WHERE id = ?')->execute([$post_id]);
        header('Location: thread.php?id=' . $post['parent'] . '&success=' . urlencode('Post deleted'));
        exit;
    }
}

// ---------- NEW POST / REPLY ----------
$parent = (int)($_POST['parent'] ?? 0);
$name_raw = trim((string)($_POST['name'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$comment = trim((string)($_POST['comment'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($comment === '') {
    $redir = $parent ? "thread.php?id=$parent" : 'index.php';
    header("Location: $redir&error=" . urlencode('Comment is required'));
    exit;
}

if (mb_strlen($comment) > MAX_COMMENT_LENGTH) {
    $redir = $parent ? "thread.php?id=$parent" : 'index.php';
    header("Location: $redir&error=" . urlencode('Comment too long'));
    exit;
}

$subject = mb_substr($subject, 0, MAX_SUBJECT_LENGTH);

// Check locked thread
if ($parent > 0) {
    $op = get_thread_op($parent);
    if (!$op) {
        header('Location: index.php?error=' . urlencode('Thread not found'));
        exit;
    }
    if ($op['locked'] && !is_admin()) {
        header("Location: thread.php?id=$parent&error=" . urlencode('Thread is locked'));
        exit;
    }
}

// Image required for new threads?
if ($parent === 0 && REQUIRE_IMAGE_FOR_THREAD && empty($_FILES['file']['name'])) {
    header('Location: index.php?error=' . urlencode('Image is required to start a new thread'));
    exit;
}

try {
    $file_data = process_upload($_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
} catch (RuntimeException $ex) {
    $redir = $parent ? "thread.php?id=$parent" : 'index.php';
    header("Location: $redir&error=" . urlencode($ex->getMessage()));
    exit;
}

[$name, $trip] = make_tripcode($name_raw);
$now = time();

// Hash password for storage (simple, reversible not needed)
$pass_hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '';

$data = [
    'parent'    => $parent,
    'timestamp' => $now,
    'bumped'    => $now,
    'ip'        => $ip,
    'name'      => $name,
    'trip'      => $trip,
    'subject'   => $subject,
    'comment'   => $comment,
    'password'  => $pass_hash,
    'file'      => $file_data['file'],
    'file_orig' => $file_data['file_orig'],
    'file_size' => $file_data['file_size'],
    'image_w'   => $file_data['image_w'],
    'image_h'   => $file_data['image_h'],
    'thumb'     => $file_data['thumb'],
    'stickied'  => 0,
    'locked'    => 0,
];

$new_id = insert_post($data);

// Bump logic
if ($parent > 0) {
    $reply_count = get_reply_count($parent);
    // Don't bump if sage in email (we don't have email field) or over limit
    // Simple: always bump unless too many replies
    if ($reply_count < MAX_REPLIES_BUMP) {
        bump_thread($parent, $now);
    }
    header("Location: thread.php?id=$parent#p$new_id");
} else {
    // New thread — the post itself is the OP, already has bumped = now
    header("Location: thread.php?id=$new_id");
}
exit;
?>
