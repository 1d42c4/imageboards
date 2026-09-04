<?php
/**
 * Modern Yotsuba Imageboard - Main entry
 * PHP 8.2+ / SQLite3
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Ensure directories exist and are writable
foreach ([IMG_DIR, THUMB_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$mode = $_POST['mode'] ?? $_GET['mode'] ?? '';
$res  = isset($_GET['res']) ? (int)$_GET['res'] : 0;

// --------------------------------------------------------------------------
// POST handling
// --------------------------------------------------------------------------
if ($mode === 'regist' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleRegist();
    exit;
}

if ($mode === 'usrdel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleDelete();
    exit;
}

// --------------------------------------------------------------------------
// Display
// --------------------------------------------------------------------------
if ($res > 0) {
    showThread($res);
} else {
    showIndex(isset($_GET['page']) ? max(0, (int)$_GET['page']) : 0);
}

// ==========================================================================
// Handlers
// ==========================================================================

function handleRegist(): void
{
    $name    = (string)($_POST['name'] ?? '');
    $email   = (string)($_POST['email'] ?? '');
    $sub     = (string)($_POST['sub'] ?? '');
    $com     = (string)($_POST['com'] ?? '');
    $pwd     = (string)($_POST['pwd'] ?? '');
    $resto   = (int)($_POST['resto'] ?? 0);
    $spoiler = !empty($_POST['spoiler']);

    $upfile     = $_FILES['upfile']['tmp_name'] ?? '';
    $upfileName = $_FILES['upfile']['name'] ?? '';
    $hasImage   = $upfile && is_uploaded_file($upfile);

    $host = clientIp();
    $time = time();
    $tim  = $time . substr((string)microtime(), 2, 3);

    // Basic validation
    if ($resto === 0 && !$hasImage) {
        error('New threads require an image.');
    }
    if (trim($com) === '' && !$hasImage) {
        error('Comment or image required.');
    }
    if (strlen($com) > 4000) {
        error('Comment too long.');
    }

    // Flood
    isFlooding($host, $hasImage, $resto === 0);

    // Check parent exists / not closed
    $pdo = db();
    if ($resto > 0) {
        $stmt = $pdo->prepare('SELECT closed FROM posts WHERE no = ? AND resto = 0');
        $stmt->execute([$resto]);
        $parent = $stmt->fetch();
        if (!$parent) {
            error('Thread does not exist.');
        }
        if ((int)$parent['closed'] === 1) {
            error('Thread is closed.');
        }

        // Image reply limit
        if ($hasImage) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE resto = ? AND fsize > 0');
            $stmt->execute([$resto]);
            if ((int)$stmt->fetchColumn() >= MAX_IMGRES) {
                error('Max image replies reached.');
            }
        }
    }

    // Process name / trip
    [$name, $trip] = makeTripcode($name);
    $email = cleanStr($email);
    $sub   = cleanStr($sub);
    $com   = cleanStr($com);
    $com   = nl2br($com, false);

    if ($spoiler && ENABLE_SPOILERS) {
        $sub = 'SPOILER<>' . $sub;
    }

    // Password
    if ($pwd === '') {
        $pwd = (string)random_int(10000000, 99999999);
    }
    $passHash = substr(md5($pwd), 2, 8);

    // Image handling
    $ext = $filename = $md5 = '';
    $w = $h = $tnW = $tnH = $fsize = 0;

    if ($hasImage) {
        $fsize = filesize($upfile);
        if ($fsize > MAX_KB * 1024) {
            error('File too large (max ' . MAX_KB . ' KB).', $upfile);
        }

        $info = @getimagesize($upfile);
        if (!$info) {
            error('Invalid image.', $upfile);
        }

        [$w, $h, $type] = $info;
        $ext = match ($type) {
            IMAGETYPE_JPEG => '.jpg',
            IMAGETYPE_PNG  => '.png',
            IMAGETYPE_GIF  => '.gif',
            IMAGETYPE_WEBP => '.webp',
            default        => '',
        };
        if ($ext === '') {
            error('Unsupported image type.', $upfile);
        }

        $md5 = md5_file($upfile);
        // Dupe check
        $stmt = $pdo->prepare('SELECT no, resto FROM posts WHERE md5 = ?');
        $stmt->execute([$md5]);
        if ($dup = $stmt->fetch()) {
            $link = $dup['resto'] ? $dup['resto'] : $dup['no'];
            error('Duplicate image. <a href="?res=' . (int)$link . '#' . (int)$dup['no'] . '">View existing</a>', $upfile);
        }

        $filename = pathinfo($upfileName, PATHINFO_FILENAME);
        $filename = preg_replace('/[^\w\.\-]/', '_', $filename) ?: 'file';

        $dest = IMG_DIR . $tim . $ext;
        if (!move_uploaded_file($upfile, $dest)) {
            error('Failed to save image.');
        }

        // Thumbnail
        if (USE_THUMB) {
            $maxW = $resto ? MAXR_W : MAX_W;
            $maxH = $resto ? MAXR_H : MAX_H;
            [$tnW, $tnH] = makeThumb($dest, THUMB_DIR . $tim . 's.jpg', $maxW, $maxH);
        }
    }

    // Insert
    $stmt = $pdo->prepare(
        'INSERT INTO posts
         (resto, time, name, trip, email, sub, com, host, pwd,
          filename, ext, w, h, tn_w, tn_h, tim, md5, fsize)
         VALUES
         (?, ?, ?, ?, ?, ?, ?, ?, ?,
          ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $resto, $time, $name, $trip, $email, $sub, $com, $host, $passHash,
        $filename, $ext, $w, $h, $tnW, $tnH, $tim, $md5, $fsize
    ]);

    $newNo = (int)$pdo->lastInsertId();

    // Bump thread (unless sage)
    if ($resto > 0 && stripos($email, 'sage') === false) {
        $pdo->prepare('UPDATE posts SET time = ? WHERE no = ? AND sticky = 0')
            ->execute([$time, $resto]);
    }

    // Cookies
    $domain = '';
    setcookie('4chan_name', $name, time() + 7 * 86400, '/', $domain);
    setcookie('4chan_pass', $pwd, time() + 7 * 86400, '/', $domain);
    if ($email && stripos($email, 'sage') === false) {
        setcookie('4chan_email', $email, time() + 7 * 86400, '/', $domain);
    }

    // Prune
    if ($resto === 0) {
        pruneOldThreads();
    }

    // Redirect
    if ($resto) {
        header('Location: ?res=' . $resto . '#' . $newNo);
    } else {
        header('Location: ?res=' . $newNo);
    }
    exit;
}

function handleDelete(): void
{
    $pwd = (string)($_POST['pwd'] ?? '');
    $onlyImg = !empty($_POST['onlyimgdel']);

    $toDelete = [];
    foreach ($_POST as $k => $v) {
        if ($v === 'delete' && ctype_digit((string)$k)) {
            $toDelete[] = (int)$k;
        }
    }

    if (!$toDelete) {
        error('No posts selected.');
    }

    foreach ($toDelete as $no) {
        deletePost($no, $pwd, false);
    }

    header('Location: ./');
    exit;
}

// ==========================================================================
// Rendering
// ==========================================================================

function showIndex(int $page): void
{
    $pdo = db();
    $offset = $page * PAGE_DEF;

    // Count threads
    $totalThreads = (int)$pdo->query('SELECT COUNT(*) FROM posts WHERE resto = 0')->fetchColumn();
    $totalPages = max(1, (int)ceil($totalThreads / PAGE_DEF));

    // Fetch OPs (sticky first, then by last activity)
    $stmt = $pdo->prepare(
        'SELECT * FROM posts
         WHERE resto = 0
         ORDER BY sticky DESC, time DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, PAGE_DEF, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $ops = $stmt->fetchAll();

    head();
    postForm(0);

    echo '<form name="delform" action="./" method="post">';
    echo '<input type="hidden" name="mode" value="usrdel">';

    foreach ($ops as $op) {
        renderPost($op, true);

        // Fetch replies and show only the last N
        $stmt = $pdo->prepare(
            'SELECT * FROM posts WHERE resto = ? ORDER BY no ASC'
        );
        $stmt->execute([$op['no']]);
        $replies = $stmt->fetchAll();

        $totalReplies = count($replies);
        $shown = array_slice($replies, -REPLIES_SHOWN);
        $omitted = $totalReplies - count($shown);

        if ($omitted > 0) {
            echo '<span class="omittedposts">' . $omitted .
                 ' post' . ($omitted > 1 ? 's' : '') .
                 ' omitted. Click <a href="?res=' . (int)$op['no'] . '">Reply</a> to view.</span>';
        }

        foreach ($shown as $r) {
            renderPost($r, false);
        }

        echo '<br clear="left"><hr>';
    }

    // Delete controls + pagination
    echo '<table class="deletebox"><tr><td>
        Delete Post: [<label><input type="checkbox" name="onlyimgdel" value="on">File Only</label>]
        Password <input type="password" name="pwd" size="8" maxlength="8">
        <input type="submit" value="Delete">
        </td></tr></table>';
    echo '<script>document.delform.pwd.value=get_pass("4chan_pass");</script>';
    echo '</form>';

    // Pages
    echo '<div class="pages">';
    if ($page > 0) {
        echo '[<a href="?page=' . ($page - 1) . '">Previous</a>] ';
    }
    for ($i = 0; $i < $totalPages; $i++) {
        if ($i === $page) {
            echo '[<b>' . $i . '</b>] ';
        } else {
            echo '[<a href="?page=' . $i . '">' . $i . '</a>] ';
        }
    }
    if ($page < $totalPages - 1) {
        echo '[<a href="?page=' . ($page + 1) . '">Next</a>]';
    }
    echo '</div>';

    foot();
}

function showThread(int $resno): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE no = ? AND resto = 0');
    $stmt->execute([$resno]);
    $op = $stmt->fetch();
    if (!$op) {
        error('Thread not found.');
    }

    $stmt = $pdo->prepare('SELECT * FROM posts WHERE resto = ? ORDER BY no ASC');
    $stmt->execute([$resno]);
    $replies = $stmt->fetchAll();

    head('Thread No.' . $resno);
    echo '[<a href="./">Return</a>] [<a href="?res=' . $resno . '">Entire Thread</a>]';
    echo '<hr>';

    postForm($resno);

    echo '<form name="delform" action="./" method="post">';
    echo '<input type="hidden" name="mode" value="usrdel">';

    renderPost($op, true, true);
    foreach ($replies as $r) {
        renderPost($r, false, true);
    }

    echo '<br clear="left"><hr>';
    echo '<table class="deletebox"><tr><td>
        Delete Post: [<label><input type="checkbox" name="onlyimgdel" value="on">File Only</label>]
        Password <input type="password" name="pwd" size="8" maxlength="8">
        <input type="submit" value="Delete">
        </td></tr></table>';
    echo '<script>document.delform.pwd.value=get_pass("4chan_pass");</script>';
    echo '</form>';

    foot();
}

function postForm(int $resto): void
{
    $name  = $_COOKIE['4chan_name']  ?? '';
    $email = $_COOKIE['4chan_email'] ?? '';
    $pwd   = $_COOKIE['4chan_pass']  ?? '';

    $submitLabel = $resto ? 'Reply' : 'Submit';

    echo '<div class="postarea">';
    echo '<form action="./" method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="mode" value="regist">';
    echo '<input type="hidden" name="resto" value="' . $resto . '">';
    echo '<table cellpadding="1" cellspacing="1">';
    echo '<tr><td class="postblock">Name</td><td><input type="text" name="name" size="28" value="' . h($name) . '"></td></tr>';
    echo '<tr><td class="postblock">Email</td><td><input type="text" name="email" size="28" value="' . h($email) . '"></td></tr>';
    echo '<tr><td class="postblock">Subject</td><td><input type="text" name="sub" size="35"> <input type="submit" value="' . $submitLabel . '"></td></tr>';
    echo '<tr><td class="postblock">Comment</td><td><textarea name="com" cols="48" rows="4" wrap="soft"></textarea></td></tr>';
    echo '<tr><td class="postblock">File</td><td><input type="file" name="upfile" size="35">';
    if (ENABLE_SPOILERS) {
        echo ' <label><input type="checkbox" name="spoiler" value="1"> Spoiler</label>';
    }
    echo '</td></tr>';
    echo '<tr><td class="postblock">Password</td><td><input type="password" name="pwd" size="8" maxlength="8" value="' . h($pwd) . '"> <span class="passhint">(for post deletion)</span></td></tr>';
    echo '</table>';
    echo '</form>';
    echo '</div>';
    echo '<hr>';
}

function renderPost(array $p, bool $isOp, bool $inThread = false): void
{
    $no   = (int)$p['no'];
    $name = h($p['name']);
    if ($p['trip']) {
        $name .= ' <span class="postertrip">' . h($p['trip']) . '</span>';
    }
    if ($p['email']) {
        $name = '<a href="mailto:' . h($p['email']) . '">' . $name . '</a>';
    }

    $sub = h($p['sub']);
    if (str_starts_with($p['sub'], 'SPOILER<>')) {
        $sub = h(substr($p['sub'], 9));
        $spoiler = true;
    } else {
        $spoiler = false;
    }

    $com = formatComment($p['com']);
    $now = formatTime((int)$p['time']);

    // Image
    $imgHtml = '';
    if ($p['ext']) {
        $src   = 'src/' . h($p['tim'] . $p['ext']);
        $thumb = 'thumb/' . h($p['tim']) . 's.jpg';
        $size  = formatBytes((int)$p['fsize']);
        $dim   = (int)$p['w'] . 'x' . (int)$p['h'];
        $fname = h($p['filename'] . $p['ext']);

        if ($spoiler) {
            $imgHtml = '<span class="filesize">File: <a href="' . $src . '" target="_blank">' .
                       h($p['tim'] . $p['ext']) . '</a>-(' . $size . 'B, ' . $dim . ', Spoiler)</span><br>' .
                       '<a href="' . $src . '" target="_blank"><span class="spoiler-thumb">SPOILER</span></a>';
        } else {
            $imgHtml = '<span class="filesize">File: <a href="' . $src . '" target="_blank">' .
                       h($p['tim'] . $p['ext']) . '</a>-(' . $size . 'B, ' . $dim . ', ' . $fname . ')</span><br>';
            if (is_file(THUMB_DIR . $p['tim'] . 's.jpg')) {
                $imgHtml .= '<a href="' . $src . '" target="_blank">' .
                            '<img src="' . $thumb . '" width="' . (int)$p['tn_w'] . '" height="' . (int)$p['tn_h'] .
                            '" alt="' . $size . 'B" class="thumb"></a>';
            } else {
                $imgHtml .= '<a href="' . $src . '" target="_blank">[Image]</a>';
            }
        }
    }

    if ($isOp) {
        echo '<div class="op" id="' . $no . '">';
        echo $imgHtml;
        echo '<input type="checkbox" name="' . $no . '" value="delete"> ';
        echo '<span class="filetitle">' . $sub . '</span> ';
        echo '<span class="postername">' . $name . '</span> ';
        echo $now . ' ';
        echo '<span class="reflink">';
        if ($inThread) {
            echo '<a href="#' . $no . '" class="quotejs">No.</a>' .
                 '<a href="javascript:quote(\'' . $no . '\')" class="quotejs">' . $no . '</a>';
        } else {
            echo '<a href="?res=' . $no . '#' . $no . '" class="quotejs">No.</a>' .
                 '<a href="?res=' . $no . '#q' . $no . '" class="quotejs">' . $no . '</a> ' .
                 '[<a href="?res=' . $no . '">Reply</a>]';
        }
        if ($p['sticky']) echo ' <img src="data:image/gif;base64,R0lGODlhCgAKAIABAAAAAP///yH5BAEAAAEALAAAAAAKAAoAAAIIjI+ZwK3XXgYAOw==" alt="sticky" title="Sticky">';
        if ($p['closed']) echo ' <span title="Closed">🔒</span>';
        echo '</span>';
        echo '<blockquote>' . $com . '</blockquote>';
        echo '</div>';
    } else {
        echo '<table class="reply"><tr><td class="doubledash">&gt;&gt;</td><td class="reply" id="' . $no . '">';
        echo '<input type="checkbox" name="' . $no . '" value="delete"> ';
        echo '<span class="replytitle">' . $sub . '</span> ';
        echo '<span class="commentpostername">' . $name . '</span> ';
        echo $now . ' ';
        echo '<span class="reflink">';
        if ($inThread) {
            echo '<a href="#' . $no . '" class="quotejs">No.</a>' .
                 '<a href="javascript:quote(\'' . $no . '\')" class="quotejs">' . $no . '</a>';
        } else {
            echo '<a href="?res=' . (int)$p['resto'] . '#' . $no . '" class="quotejs">No.</a>' .
                 '<a href="?res=' . (int)$p['resto'] . '#q' . $no . '" class="quotejs">' . $no . '</a>';
        }
        echo '</span>';
        if ($imgHtml) {
            echo '<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $imgHtml;
        }
        echo '<blockquote>' . $com . '</blockquote>';
        echo '</td></tr></table>';
    }
}
