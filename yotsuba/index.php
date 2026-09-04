<?php
declare(strict_types=1);

/**
 * Single-file Message Board – PHP 8.5+
 *
 * Setup:
 * 1. Place this file as index.php
 * 2. Ensure the web server can write to the same directory (for message_board.db + uploads/)
 * 3. Generate a password hash (run once locally):
 *    php -r "echo password_hash('your-secret-password', PASSWORD_DEFAULT), PHP_EOL;"
 *    Then paste it into MODERATOR_PASSWORD_HASH below.
 * 4. Require PHP 8.5+ with SQLite3 + GD extensions.
 */

const MODERATOR_PASSWORD_HASH = 'your_hashed_password_here'; // REPLACE THIS

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure'   => true,          // set false only for pure HTTP testing
    'cookie_samesite' => 'Strict',
]);

if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
$db = new SQLite3(__DIR__ . '/message_board.db');
$db->enableExceptions(true);            // modern default behaviour (8.3+)

$db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS posts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT    NOT NULL,
    message    TEXT    NOT NULL,
    media      TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS replies (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    message TEXT    NOT NULL,
    FOREIGN KEY(post_id) REFERENCES posts(id)
);
CREATE TRIGGER IF NOT EXISTS update_timestamp
AFTER UPDATE ON posts
FOR EACH ROW
WHEN NEW.updated_at <= OLD.updated_at
BEGIN
    UPDATE posts SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;
SQL);

$uploadsDir = __DIR__ . '/uploads/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function getUniqueFilename(string $directory, string $filename): string
{
    $info      = pathinfo($filename);
    $basename  = $info['filename'] ?? 'file';
    $extension = isset($info['extension']) ? '.' . $info['extension'] : '';
    $candidate = $basename . $extension;
    $i = 1;
    while (file_exists($directory . $candidate)) {
        $candidate = $basename . '-' . $i++ . $extension;
    }
    return $candidate;
}

function getReplyCount(SQLite3 $db, int $postId): int
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM replies WHERE post_id = :id');
    $stmt->bindValue(':id', $postId, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return (int) ($row['c'] ?? 0);
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function renderMedia(?string $path): string
{
    if ($path === null || $path === '' || !is_file($path)) {
        return '';
    }
    $mime = mime_content_type($path) ?: '';
    $src  = e(str_replace(__DIR__ . '/', '', $path)); // relative URL
    if (str_starts_with($mime, 'video/')) {
        return '<video class="post-media" controls><source src="' . $src . '"></video>';
    }
    return '<img class="post-media" src="' . $src . '" alt="media" loading="lazy">';
}

function renderPost(array $post, int $replyCount, bool $showReplyLink = true): string
{
    $html  = '<div class="post" data-id="' . (int)$post['id'] . '">';
    $html .= '<hr class="green-hr">';
    $html .= '<div class="post-media-container">' . renderMedia($post['media'] ?? null) . '</div>';
    $html .= '<h2>' . e($post['title']) . '</h2>';
    $html .= '<p class="msg">' . nl2br(e($post['message'])) . '</p>';
    if ($showReplyLink) {
        $html .= '<a class="reply-button" href="?action=reply&post_id=' . (int)$post['id'] . '">[reply-' . $replyCount . ']</a>';
    }
    $html .= '</div>';
    return $html;
}

function renderReply(array $reply, int $index): string
{
    return '<div class="reply"><p><strong>Reply ' . $index . ':</strong> '
         . nl2br(e($reply['message'])) . '</p></div>';
}

// ---------------------------------------------------------------------------
// CAPTCHA image (action=captcha)
// ---------------------------------------------------------------------------
if (($_GET['action'] ?? '') === 'captcha') {
    $image = imagecreatetruecolor(150, 50);
    $bg    = imagecolorallocate($image, 255, 255, 255);
    $fg    = imagecolorallocate($image, 0, 0, 0);
    $line  = imagecolorallocate($image, 64, 64, 64);
    imagefilledrectangle($image, 0, 0, 150, 50, $bg);
    for ($i = 0; $i < 3; $i++) {
        imageline($image, 0, random_int(0, 49), 150, random_int(0, 49), $line);
    }
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $text  = '';
    for ($i = 0; $i < 5; $i++) {
        $text .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $_SESSION['captcha_text'] = $text;
    imagestring($image, 5, 35, 15, $text, $fg);
    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    imagepng($image);
    imagedestroy($image);
    exit;
}

// ---------------------------------------------------------------------------
// Moderator panel (action=mod)
// ---------------------------------------------------------------------------
if (($_GET['action'] ?? '') === 'mod') {
    if (isset($_POST['password'])) {
        if (password_verify((string)$_POST['password'], MODERATOR_PASSWORD_HASH)) {
            $_SESSION['is_moderator'] = true;
        } else {
            $modError = 'Incorrect password.';
        }
    }
    if (isset($_GET['logout'])) {
        unset($_SESSION['is_moderator']);
        header('Location: ?action=mod');
        exit;
    }

    if (!isset($_SESSION['is_moderator'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Moderator Login</title>
            <style>body{font-family:system-ui,sans-serif;max-width:400px;margin:4rem auto;padding:1rem}</style>
        </head>
        <body>
            <h1>Moderator Login</h1>
            <?php if (!empty($modError)): ?><p style="color:red"><?= e($modError) ?></p><?php endif; ?>
            <form method="post">
                <label>Password <input type="password" name="password" required autofocus></label>
                <button type="submit">Login</button>
            </form>
        </body>
        </html>
        <?php
        exit;
    }

    // Soft-delete handlers
    if (isset($_POST['delete_post_id'])) {
        $id = filter_input(INPUT_POST, 'delete_post_id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $db->prepare('UPDATE posts SET title = :t, message = :m WHERE id = :id');
            $stmt->bindValue(':t', 'Post Deleted By Moderator', SQLITE3_TEXT);
            $stmt->bindValue(':m', 'Post Deleted By Moderator', SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
    if (isset($_POST['delete_reply_id'])) {
        $id = filter_input(INPUT_POST, 'delete_reply_id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $db->prepare('UPDATE replies SET message = :m WHERE id = :id');
            $stmt->bindValue(':m', 'Reply Deleted By Moderator', SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
        }
    }

    $posts = $db->query('SELECT * FROM posts ORDER BY updated_at DESC');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Moderator Panel</title>
        <style>
            body{font-family:system-ui,sans-serif;margin:1rem;background:#f5f5f5}
            .post,.reply{margin-bottom:1rem;padding:1rem;background:#e0e0e0;border-radius:6px}
            .delete-button{background:#c00;color:#fff;border:0;padding:.4rem .8rem;border-radius:4px;cursor:pointer}
            a{color:#06c}
        </style>
    </head>
    <body>
        <h1>Moderator Panel</h1>
        <p><a href="?">← Board</a> · <a href="?action=mod&logout=1">Logout</a></p>
        <?php while ($post = $posts->fetchArray(SQLITE3_ASSOC)): ?>
            <div class="post">
                <h2><?= e($post['title']) ?></h2>
                <p><?= nl2br(e($post['message'])) ?></p>
                <form method="post" onsubmit="return confirm('Delete this post?')">
                    <input type="hidden" name="delete_post_id" value="<?= (int)$post['id'] ?>">
                    <button type="submit" class="delete-button">Delete Post</button>
                </form>
            </div>
            <?php
            $replies = $db->prepare('SELECT * FROM replies WHERE post_id = :pid ORDER BY id ASC');
            $replies->bindValue(':pid', $post['id'], SQLITE3_INTEGER);
            $r = $replies->execute();
            while ($reply = $r->fetchArray(SQLITE3_ASSOC)):
            ?>
                <div class="reply">
                    <p><strong>Reply <?= (int)$reply['id'] ?>:</strong> <?= nl2br(e($reply['message'])) ?></p>
                    <form method="post" onsubmit="return confirm('Delete this reply?')">
                        <input type="hidden" name="delete_reply_id" value="<?= (int)$reply['id'] ?>">
                        <button type="submit" class="delete-button">Delete Reply</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php endwhile; ?>
    </body>
    </html>
    <?php
    exit;
}

// ---------------------------------------------------------------------------
// New post (AJAX / form POST)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['post_id'])) {
    header('Content-Type: text/html; charset=utf-8');

    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }

    $title   = trim((string)($_POST['title'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $captcha = trim((string)($_POST['captcha'] ?? ''));

    if ($title === '' || $message === '') {
        http_response_code(400);
        exit('Title and message are required.');
    }
    if (mb_strlen($title) > 20) {
        http_response_code(400);
        exit('Title too long (max 20).');
    }
    if (empty($_SESSION['captcha_text']) || !hash_equals($_SESSION['captcha_text'], $captcha)) {
        http_response_code(400);
        exit('Invalid CAPTCHA.');
    }
    unset($_SESSION['captcha_text']);

    $mediaPath = null;
    if (!empty($_FILES['media']['tmp_name']) && is_uploaded_file($_FILES['media']['tmp_name'])) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp','video/webm','video/mp4'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($_FILES['media']['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            http_response_code(400);
            exit('Invalid file type');
        }
        if ($_FILES['media']['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            exit('File too large (max 5 MB)');
        }
        $unique    = getUniqueFilename($uploadsDir, basename($_FILES['media']['name']));
        $mediaPath = $uploadsDir . $unique;
        if (!move_uploaded_file($_FILES['media']['tmp_name'], $mediaPath)) {
            http_response_code(500);
            exit('Upload failed');
        }
    }

    $stmt = $db->prepare('INSERT INTO posts (title, message, media) VALUES (:t, :m, :media)');
    $stmt->bindValue(':t', $title, SQLITE3_TEXT);
    $stmt->bindValue(':m', $message, SQLITE3_TEXT);
    $stmt->bindValue(':media', $mediaPath, $mediaPath === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->execute();

    $id = (int)$db->lastInsertRowID();
    // Return only the rendered post fragment for AJAX prepend
    echo renderPost([
        'id'      => $id,
        'title'   => $title,
        'message' => $message,
        'media'   => $mediaPath,
    ], 0);
    exit;
}

// ---------------------------------------------------------------------------
// Reply view + post reply
// ---------------------------------------------------------------------------
if (($_GET['action'] ?? '') === 'reply' || isset($_POST['post_id'])) {
    $postId = filter_input(INPUT_GET, 'post_id', FILTER_VALIDATE_INT)
           ?: filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);

    if (!$postId) {
        http_response_code(400);
        exit('Post ID required.');
    }

    // Handle new reply
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }
        $message = trim((string)($_POST['message'] ?? ''));
        $captcha = trim((string)($_POST['captcha'] ?? ''));
        if ($message === '') {
            http_response_code(400);
            exit('Reply message required.');
        }
        if (empty($_SESSION['captcha_text']) || !hash_equals($_SESSION['captcha_text'], $captcha)) {
            http_response_code(400);
            exit('Invalid CAPTCHA.');
        }
        unset($_SESSION['captcha_text']);

        $stmt = $db->prepare('INSERT INTO replies (post_id, message) VALUES (:pid, :m)');
        $stmt->bindValue(':pid', $postId, SQLITE3_INTEGER);
        $stmt->bindValue(':m', $message, SQLITE3_TEXT);
        $stmt->execute();

        // Bump post
        $db->exec('UPDATE posts SET updated_at = CURRENT_TIMESTAMP WHERE id = ' . (int)$postId);

        header('Location: ?action=reply&post_id=' . $postId);
        exit;
    }

    $stmt = $db->prepare('SELECT * FROM posts WHERE id = :id');
    $stmt->bindValue(':id', $postId, SQLITE3_INTEGER);
    $post = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$post) {
        http_response_code(404);
        exit('Post not found.');
    }

    $replies = $db->prepare('SELECT * FROM replies WHERE post_id = :pid ORDER BY id ASC');
    $replies->bindValue(':pid', $postId, SQLITE3_INTEGER);
    $replyResult = $replies->execute();
    $csrf = generateCsrfToken();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reply to Post</title>
        <style>
            :root{--bg:#B0C4DE;--card:#F0F0F0;--post:#E0E0E0;--reply:#D0D0D0}
            body{background:var(--bg);margin:0;font-family:system-ui,sans-serif}
            .board{width:90%;max-width:800px;margin:1.5rem auto;padding:1.5rem;background:var(--card);border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.08)}
            .post{margin-bottom:1.25rem;padding:1rem;background:var(--post);border-radius:6px;position:relative}
            .green-hr{border:none;border-top:5px solid #2e8b57;margin:0 0 .75rem}
            .post-media{max-width:100%;height:auto;cursor:pointer}
            .post-media.expanded{max-width:100%}
            .reply{margin:.5rem 0;padding:.75rem;background:var(--reply);border-radius:5px}
            form textarea,form input[type=text]{width:100%;margin-bottom:.6rem;padding:.5rem;box-sizing:border-box}
            form textarea{height:100px;resize:vertical}
            form button{padding:.6rem 1.2rem;background:#2e8b57;color:#fff;border:0;border-radius:5px;cursor:pointer}
            .back{display:inline-block;margin-bottom:1rem;color:#06c;text-decoration:none}
            .msg{word-wrap:break-word;overflow-wrap:anywhere}
        </style>
    </head>
    <body>
        <div class="board">
            <a class="back" href="./">← Back to Main Board</a>
            <?= renderPost($post, getReplyCount($db, $postId), false) ?>
            <form method="post">
                <input type="hidden" name="post_id" value="<?= $postId ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <textarea name="message" placeholder="Reply message" required maxlength="100000"></textarea>
                <img src="?action=captcha" alt="CAPTCHA" width="150" height="50"><br>
                <input type="text" name="captcha" placeholder="Enter CAPTCHA" required autocomplete="off">
                <button type="submit">Post Reply</button>
            </form>
            <?php
            $i = 1;
            while ($r = $replyResult->fetchArray(SQLITE3_ASSOC)) {
                echo renderReply($r, $i++);
            }
            ?>
        </div>
        <script>
            document.querySelectorAll('.post-media').forEach(el => {
                el.addEventListener('click', () => el.classList.toggle('expanded'));
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ---------------------------------------------------------------------------
// Main board
// ---------------------------------------------------------------------------
$csrf = generateCsrfToken();
$postsPerPage = 10;
$totalPosts   = (int)$db->querySingle('SELECT COUNT(*) FROM posts');
$totalPages   = max(1, (int)ceil($totalPosts / $postsPerPage));
$page         = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset       = ($page - 1) * $postsPerPage;

$result = $db->query("SELECT * FROM posts ORDER BY updated_at DESC LIMIT $postsPerPage OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Board</title>
    <style>
        :root{--bg:#B0C4DE;--card:#F0F0F0;--post:#E0E0E0}
        body{background:var(--bg);margin:0;font-family:system-ui,sans-serif}
        .board{width:90%;max-width:1100px;margin:1.5rem auto;padding:1.5rem;background:var(--card);border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.08)}
        .toggle{text-align:center;margin-bottom:1.25rem}
        .toggle button{padding:.6rem 1.2rem;background:#2e8b57;color:#fff;border:0;border-radius:5px;cursor:pointer;margin:0 .3rem}
        .toggle .close{background:#c00;display:none}
        .form-wrap{display:none;max-width:600px;margin:0 auto 1.5rem;background:#fff;padding:1.25rem;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
        .form-wrap input,.form-wrap textarea,.form-wrap input[type=file]{width:100%;margin-bottom:.6rem;padding:.5rem;box-sizing:border-box}
        .form-wrap textarea{height:110px;resize:vertical}
        .form-wrap button{padding:.6rem 1.2rem;background:#2e8b57;color:#fff;border:0;border-radius:5px;cursor:pointer}
        .post{margin-bottom:1.25rem;padding:1rem;background:var(--post);border-radius:6px;position:relative}
        .green-hr{border:none;border-top:5px solid #2e8b57;margin:0 0 .75rem}
        .post-media{max-width:200px;height:auto;cursor:pointer;object-fit:contain}
        .post-media.expanded{max-width:100%}
        .reply-button{position:absolute;top:10px;right:10px;background:#06c;color:#fff;padding:.3rem .7rem;border-radius:4px;text-decoration:none;font-size:.85rem}
        .msg{word-wrap:break-word;overflow-wrap:anywhere}
        .pagination{text-align:center;margin-top:1.5rem}
        .pagination a{display:inline-block;margin:0 .25rem;padding:.5rem .9rem;background:#ddd;color:#111;text-decoration:none;border-radius:4px}
        .pagination a.active{background:#333;color:#fff}
        .mod-link{float:right;font-size:.85rem;color:#666}
    </style>
</head>
<body>
    <div class="board">
        <a class="mod-link" href="?action=mod">Moderator</a>
        <div class="toggle">
            <button type="button" id="newPostBtn">[NEW POST]</button>
            <button type="button" id="closeBtn" class="close">[X]</button>
        </div>
        <div class="form-wrap" id="formWrap">
            <form id="postForm" enctype="multipart/form-data" method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="text" name="title" placeholder="Title (max 20)" maxlength="20" required>
                <textarea name="message" placeholder="Message" maxlength="100000" required></textarea>
                <input type="file" name="media" accept="image/jpeg,image/png,image/gif,image/webp,video/webm,video/mp4">
                <img src="?action=captcha" alt="CAPTCHA" width="150" height="50" id="captchaImg"><br>
                <input type="text" name="captcha" placeholder="Enter CAPTCHA" required autocomplete="off">
                <button type="submit">Post</button>
            </form>
        </div>
        <div id="posts">
            <?php while ($row = $result->fetchArray(SQLITE3_ASSOC)): ?>
                <?= renderPost($row, getReplyCount($db, (int)$row['id'])) ?>
            <?php endwhile; ?>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
        const formWrap = document.getElementById('formWrap');
        const newBtn   = document.getElementById('newPostBtn');
        const closeBtn = document.getElementById('closeBtn');
        newBtn.addEventListener('click', () => {
            formWrap.style.display = 'block';
            newBtn.style.display = 'none';
            closeBtn.style.display = 'inline-block';
        });
        closeBtn.addEventListener('click', () => {
            formWrap.style.display = 'none';
            closeBtn.style.display = 'none';
            newBtn.style.display = 'inline-block';
        });

        document.getElementById('postForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                const res = await fetch('', { method: 'POST', body: fd });
                if (!res.ok) {
                    const txt = await res.text();
                    alert(txt || 'Error');
                    return;
                }
                const html = await res.text();
                document.getElementById('posts').insertAdjacentHTML('afterbegin', html);
                e.target.reset();
                document.getElementById('captchaImg').src = '?action=captcha&t=' + Date.now();
                formWrap.style.display = 'none';
                closeBtn.style.display = 'none';
                newBtn.style.display = 'inline-block';
            } catch (err) {
                alert('Network error');
            }
        });

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('post-media')) {
                e.target.classList.toggle('expanded');
            }
        });
    </script>
</body>
</html>