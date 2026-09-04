<?php
require_once 'config.php';

function get_db() {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA journal_mode = WAL;');
        $db->exec('PRAGMA foreign_keys = ON;');
        init_db($db);
    }
    return $db;
}

function init_db($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent INTEGER DEFAULT 0,          -- 0 = OP / thread
            name TEXT DEFAULT 'Anonymous',
            subject TEXT DEFAULT '',
            comment TEXT NOT NULL,
            password TEXT DEFAULT '',
            filename TEXT DEFAULT '',
            original_name TEXT DEFAULT '',
            filesize INTEGER DEFAULT 0,
            width INTEGER DEFAULT 0,
            height INTEGER DEFAULT 0,
            ip TEXT DEFAULT '',
            sticky INTEGER DEFAULT 0,
            locked INTEGER DEFAULT 0,
            created_at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_parent ON posts(parent);
        CREATE INDEX IF NOT EXISTS idx_created ON posts(created_at);
    ");
}

function time_ago($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return $diff . 's';
    if ($diff < 3600) return floor($diff/60) . 'm';
    if ($diff < 86400) return floor($diff/3600) . 'h';
    if ($diff < 604800) return floor($diff/86400) . 'd';
    return date('Y-m-d', $timestamp);
}

function clean($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function format_comment($text) {
    $text = clean($text);
    // Greentext
    $text = preg_replace('/^(&gt;.*)$/m', '<span class="greentext">$1</span>', $text);
    // Quote links >>123
    $text = preg_replace('/&gt;&gt;(\d+)/', '<a href="#p$1" class="quote-link">&gt;&gt;$1</a>', $text);
    // Newlines
    $text = nl2br($text);
    return $text;
}

function make_thumb($src, $dest, $max_w = 250) {
    $info = getimagesize($src);
    if (!$info) return false;

    list($w, $h) = $info;
    $mime = $info['mime'];

    if ($w <= $max_w) {
        copy($src, $dest);
        return [$w, $h];
    }

    $ratio = $max_w / $w;
    $new_w = $max_w;
    $new_h = (int)($h * $ratio);

    $src_img = null;
    switch ($mime) {
        case 'image/jpeg': $src_img = imagecreatefromjpeg($src); break;
        case 'image/png':  $src_img = imagecreatefrompng($src); break;
        case 'image/gif':  $src_img = imagecreatefromgif($src); break;
        case 'image/webp': $src_img = imagecreatefromwebp($src); break;
        default: return false;
    }
    if (!$src_img) return false;

    $thumb = imagecreatetruecolor($new_w, $new_h);
    // Preserve transparency
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);

    imagecopyresampled($thumb, $src_img, 0, 0, 0, 0, $new_w, $new_h, $w, $h);

    imagejpeg($thumb, $dest, 85);
    imagedestroy($src_img);
    imagedestroy($thumb);

    return [$new_w, $new_h];
}
