<?php
/**
 * Core helper functions
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cleanStr(string $str): string
{
    $str = trim($str);
    $str = str_replace(["\r\n", "\r"], "\n", $str);
    $str = htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // Basic bidirectional override mitigation
    $str .= str_repeat("\xE2\x80\xAC", substr_count($str, "\xE2\x80\xAE"));
    return $str;
}

function error(string $msg, ?string $dest = null): never
{
    if ($dest && is_file($dest)) {
        @unlink($dest);
    }
    http_response_code(400);
    head('Error');
    echo '<div class="error"><b>Error:</b> ' . h($msg) . '</div>';
    echo '<br>[<a href="./">Return</a>]';
    foot();
    exit;
}

function head(string $title = ''): void
{
    $title = $title ? h($title) . ' - ' . BOARD_TITLE : BOARD_TITLE;
    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $title . '</title>
<link rel="stylesheet" href="style.css">
<script>
function quote(no) {
    var ta = document.getElementsByName("com")[0];
    if (ta) {
        ta.value += ">>" + no + "\n";
        ta.focus();
    }
}
function get_pass(key) {
    var m = document.cookie.match(new RegExp("(?:^|; )" + key + "=([^;]*)"));
    return m ? decodeURIComponent(m[1]) : "";
}
</script>
</head>
<body>
<div class="boardbanner">
  <div class="boardtitle">' . h(BOARD_TITLE) . '</div>
  <div class="boardsubtitle">' . h(BOARD_SUBTITLE) . '</div>
</div>
<hr>
';
}

function foot(): void
{
    echo '
<hr>
<div class="footer">
  <span>Yotsuba Modern · PHP ' . PHP_VERSION . ' · SQLite3</span>
</div>
</body>
</html>';
}

function formatComment(string $com): string
{
    // >>quotes
    $com = preg_replace(
        '/&gt;&gt;(\d+)/',
        '<a href="#$1" class="quotelink">&gt;&gt;$1</a>',
        $com
    );
    // greentext
    $com = preg_replace(
        '/(^|<br \/>)(&gt;.*?)(?=<br \/>|$)/m',
        '$1<span class="quote">$2</span>',
        $com
    );
    // spoilers [spoiler]...[/spoiler]
    if (ENABLE_SPOILERS) {
        $com = preg_replace(
            '/\[spoiler\](.*?)\[\/spoiler\]/is',
            '<span class="spoiler">$1</span>',
            $com
        );
    }
    return $com;
}

function makeTripcode(string $name): array
{
    $trip = '';
    $secure = '';
    if (str_contains($name, '#')) {
        $parts = explode('#', $name, 3);
        $name  = $parts[0];
        $trip  = $parts[1] ?? '';
        $secure = $parts[2] ?? '';
    }

    $name = cleanStr($name);

    if ($trip !== '') {
        $salt = strtr(substr($trip . 'H.', 1, 2), ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
        $trip = '!' . substr(crypt($trip, $salt), -10);
    }

    if ($secure !== '') {
        $hash = substr(base64_encode(hash_hmac('sha1', $secure, SALT, true)), 0, 11);
        $trip .= '!!' . $hash;
    }

    if ($name === '') {
        $name = 'Anonymous';
    }

    return [$name, $trip];
}

function makeThumb(string $srcPath, string $destPath, int $maxW, int $maxH): array
{
    if (!extension_loaded('gd')) {
        return [0, 0];
    }

    $info = @getimagesize($srcPath);
    if (!$info) {
        return [0, 0];
    }

    [$w, $h, $type] = $info;

    switch ($type) {
        case IMAGETYPE_JPEG:
            $im = @imagecreatefromjpeg($srcPath);
            break;
        case IMAGETYPE_PNG:
            $im = @imagecreatefrompng($srcPath);
            break;
        case IMAGETYPE_GIF:
            $im = @imagecreatefromgif($srcPath);
            break;
        case IMAGETYPE_WEBP:
            $im = @imagecreatefromwebp($srcPath);
            break;
        default:
            return [0, 0];
    }

    if (!$im) {
        return [0, 0];
    }

    $ratio = min($maxW / $w, $maxH / $h, 1.0);
    $tnW = (int)ceil($w * $ratio);
    $tnH = (int)ceil($h * $ratio);

    $thumb = imagecreatetruecolor($tnW, $tnH);
    imagecopyresampled($thumb, $im, 0, 0, 0, 0, $tnW, $tnH, $w, $h);
    imagejpeg($thumb, $destPath, 80);

    imagedestroy($im);
    imagedestroy($thumb);

    return [$tnW, $tnH];
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' M';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' K';
    }
    return $bytes . ' ';
}

function formatTime(int $ts): string
{
    $youbi = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $d = $youbi[(int)date('w', $ts)];
    if (SHOW_SECONDS) {
        return date('m/d/y', $ts) . "($d)" . date('H:i:s', $ts);
    }
    return date('m/d/y', $ts) . "($d)" . date('H:i', $ts);
}

function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isFlooding(string $host, bool $hasImage, bool $isThread): void
{
    $pdo = db();
    $now = time();

    // Any post
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE host = ? AND time > ?');
    $stmt->execute([$host, $now - RENZOKU]);
    if ((int)$stmt->fetchColumn() > 0) {
        error('Flood detected. Please wait a few seconds.');
    }

    if ($hasImage) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE host = ? AND time > ? AND fsize > 0');
        $stmt->execute([$host, $now - RENZOKU2]);
        if ((int)$stmt->fetchColumn() > 0) {
            error('Image flood detected. Please wait.');
        }
    }

    if ($isThread) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE host = ? AND time > ? AND resto = 0');
        $stmt->execute([$host, $now - RENZOKU3]);
        if ((int)$stmt->fetchColumn() > 0) {
            error('You are creating threads too quickly.');
        }
    }
}

function pruneOldThreads(): void
{
    $pdo = db();
    $count = (int)$pdo->query('SELECT COUNT(*) FROM posts WHERE resto = 0')->fetchColumn();
    if ($count <= LOG_MAX) {
        return;
    }

    $excess = $count - LOG_MAX;
    $stmt = $pdo->query(
        'SELECT no FROM posts WHERE resto = 0 AND sticky = 0 ORDER BY time ASC LIMIT ' . (int)$excess
    );
    $toDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($toDelete as $no) {
        deletePost((int)$no, '', true);
    }
}

function deletePost(int $no, string $pwd, bool $force = false): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE no = ?');
    $stmt->execute([$no]);
    $row = $stmt->fetch();
    if (!$row) {
        error('Post not found.');
    }

    $ok = $force
        || ($pwd === ADMIN_PASS)
        || (substr(md5($pwd), 2, 8) === $row['pwd'])
        || ($row['host'] === clientIp());

    if (!$ok) {
        error('Incorrect password.');
    }

    // Delete files
    if ($row['tim'] && $row['ext']) {
        $img = IMG_DIR . $row['tim'] . $row['ext'];
        $tn  = THUMB_DIR . $row['tim'] . 's.jpg';
        if (is_file($img)) @unlink($img);
        if (is_file($tn))  @unlink($tn);
    }

    // If OP, delete whole thread
    if ((int)$row['resto'] === 0) {
        $children = $pdo->prepare('SELECT no, tim, ext FROM posts WHERE resto = ?');
        $children->execute([$no]);
        while ($c = $children->fetch()) {
            if ($c['tim'] && $c['ext']) {
                $img = IMG_DIR . $c['tim'] . $c['ext'];
                $tn  = THUMB_DIR . $c['tim'] . 's.jpg';
                if (is_file($img)) @unlink($img);
                if (is_file($tn))  @unlink($tn);
            }
        }
        $pdo->prepare('DELETE FROM posts WHERE no = ? OR resto = ?')->execute([$no, $no]);
    } else {
        $pdo->prepare('DELETE FROM posts WHERE no = ?')->execute([$no]);
    }
}
