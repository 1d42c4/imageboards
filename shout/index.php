<?php
declare(strict_types=1);

/**
 * Chess Message Board
 * A clean, modern single-file message board for chess discussion.
 * Supports optional image uploads (great for positions, puzzles, games).
 * Requires PHP 8.1+ with PDO SQLite and fileinfo extensions.
 */

// ─── Configuration ───────────────────────────────────────────────────────────
const DB_PATH        = __DIR__ . '/chessboard.db';
const UPLOAD_DIR     = __DIR__ . '/uploads/';
const MAX_IMAGE_SIZE = 3 * 1024 * 1024; // 3 MB
const ALLOWED_MIME   = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

// ─── Bootstrap ───────────────────────────────────────────────────────────────
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

$pdo = new PDO('sqlite:' . DB_PATH, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS posts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    body       TEXT    NOT NULL DEFAULT '',
    image      TEXT,
    created_at TEXT    NOT NULL
)
SQL);

// ─── Helpers ─────────────────────────────────────────────────────────────────
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function formatBody(string $text): string
{
    // Text was already escaped on insert; newlines are preserved.
    $text  = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $out   = [];

    foreach ($lines as $line) {
        // Greentext: lines that started with ">" become &gt; after escaping
        if (str_starts_with($line, '&gt;')) {
            $out[] = '<span class="greentext">' . $line . '</span>';
        } else {
            $out[] = $line;
        }
    }

    return implode("<br>\n", $out);
}

// ─── Handle new post ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));

    if ($name === '') {
        $name = 'Anonymous';
    }

    // Require at least some text or an image
    $hasImage = isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($body === '' && !$hasImage) {
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Escape for storage (we re-escape on output too for safety)
    $name = h($name);
    $body = h($body);

    $imagePath = null;

    if ($hasImage && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];

        if ($file['size'] <= MAX_IMAGE_SIZE && $file['size'] > 0) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);

            if (isset(ALLOWED_MIME[$mime])) {
                $ext      = ALLOWED_MIME[$mime];
                $filename = bin2hex(random_bytes(12)) . '.' . $ext;
                $dest     = UPLOAD_DIR . $filename;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $imagePath = 'uploads/' . $filename;
                }
            }
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO posts (name, body, image, created_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $name,
        $body,
        $imagePath,
        date('Y-m-d H:i:s'),
    ]);

    // Prevent form resubmission
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// ─── Fetch posts (newest first) ──────────────────────────────────────────────
$posts = $pdo->query('SELECT * FROM posts ORDER BY id DESC')->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chess Message Board</title>
<style>
:root {
    --board-light: #eeeed2;
    --board-dark:  #769656;
    --board-darker:#5d7c45;
    --surface:     #ffffff;
    --bg:          #f4f4ec;
    --text:        #2c2c2c;
    --muted:       #6b6b6b;
    --border:      #c8c8b8;
    --green:       #789922;
}

*, *::before, *::after { box-sizing: border-box; }

body {
    margin: 0;
    padding: 16px;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 15px;
    line-height: 1.5;
    color: var(--text);
    background: var(--bg);
}

.wrap {
    max-width: 760px;
    margin: 0 auto;
}

header {
    text-align: center;
    margin-bottom: 24px;
}

header h1 {
    margin: 0 0 4px;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--board-darker);
    letter-spacing: -0.02em;
}

header p {
    margin: 0;
    color: var(--muted);
    font-size: 0.95rem;
}

/* Post form */
#post-form {
    background: var(--board-light);
    border: 2px solid var(--board-dark);
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 28px;
}

#post-form label {
    display: block;
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 4px;
    color: var(--board-darker);
}

#post-form .row {
    margin-bottom: 12px;
}

#post-form input[type="text"],
#post-form textarea {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font: inherit;
    background: #fff;
}

#post-form textarea {
    min-height: 90px;
    resize: vertical;
}

#post-form input[type="file"] {
    font-size: 0.9rem;
}

#post-form .hint {
    font-size: 0.8rem;
    color: var(--muted);
    margin-top: 2px;
}

#post-form button {
    display: inline-block;
    margin-top: 4px;
    padding: 9px 22px;
    background: var(--board-dark);
    color: #fff;
    border: none;
    border-radius: 6px;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}

#post-form button:hover {
    background: var(--board-darker);
}

/* Individual posts */
.post {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}

.post-meta {
    font-size: 0.88rem;
    margin-bottom: 6px;
    color: var(--muted);
}

.post-name {
    color: var(--board-darker);
    font-weight: 700;
}

.post-num {
    font-weight: 600;
    color: var(--text);
}

.post-body {
    margin: 0;
    word-wrap: break-word;
    overflow-wrap: anywhere;
}

.greentext {
    color: var(--green);
}

.post-image {
    display: block;
    max-width: 100%;
    max-height: 420px;
    margin-top: 10px;
    border-radius: 6px;
    border: 1px solid var(--border);
}

/* Empty state */
.empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--muted);
    background: var(--surface);
    border: 1px dashed var(--border);
    border-radius: 8px;
}

/* Footer */
footer {
    margin-top: 32px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    text-align: center;
    font-size: 0.8rem;
    color: var(--muted);
}

footer a {
    color: var(--board-dark);
    text-decoration: none;
}

footer a:hover {
    text-decoration: underline;
}
</style>
</head>
<body>
<div class="wrap">

<header>
    <h1>♟ Chess Message Board</h1>
    <p>Discuss games, share positions, ask questions. Images welcome.</p>
</header>

<section id="post-form">
    <form method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="row">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="Anonymous" maxlength="40">
        </div>
        <div class="row">
            <label for="body">Comment</label>
            <textarea id="body" name="body" maxlength="4000" placeholder="Share a thought, game analysis, or puzzle idea…"></textarea>
        </div>
        <div class="row">
            <label for="image">Image (optional)</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
            <div class="hint">JPEG, PNG, GIF or WebP · max 3 MB · ideal for board screenshots</div>
        </div>
        <button type="submit">Post</button>
    </form>
</section>

<main>
<?php if (count($posts) === 0): ?>
    <div class="empty">
        No posts yet. Be the first to share something!
    </div>
<?php else: ?>
    <?php foreach ($posts as $p): ?>
    <article class="post" id="p<?= (int)$p['id'] ?>">
        <div class="post-meta">
            <span class="post-name"><?= $p['name'] ?></span>
            · <time datetime="<?= h($p['created_at']) ?>"><?= h($p['created_at']) ?></time>
            · <span class="post-num">No. <?= (int)$p['id'] ?></span>
        </div>
        <?php if ($p['body'] !== ''): ?>
        <p class="post-body"><?= formatBody($p['body']) ?></p>
        <?php endif; ?>
        <?php if ($p['image']): ?>
        <img class="post-image" src="<?= h($p['image']) ?>" alt="Attached image" loading="lazy">
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
<?php endif; ?>
</main>

<footer>
    Chess Message Board · simple · fast · made for chess discussion
</footer>

</div>
</body>
</html>
