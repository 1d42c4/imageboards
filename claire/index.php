<?php
declare(strict_types=1);

/**
 * Claire Imageboard — Modernized for PHP 8.4+
 * Lightweight, strictly-typed imageboard based on the TinyIB/Claire lineage.
 */

// ─── Configuration ───────────────────────────────────────────────────────────
define('CLAIRE_TEXTMODE', false);          // true = disallow images
define('CLAIRE_BLOGMODE', false);          // true = only staff can create threads
define('TINYIB_PAGETITLE', 'Claire Imageboard');
define('TINYIB_ADMINPASS', 'adminpassword'); // CHANGE THIS
define('TINYIB_MODPASS', 'modpassword');     // Leave empty to disable mod account
define('TINYIB_THREADSPERPAGE', 8);
define('TINYIB_REPLIESTOSHOW', 3);
define('TINYIB_MAXTHREADS', 0);              // 0 = never auto-delete old threads
define('TINYIB_DELETE_TIMEOUT', 1200);       // seconds to delete own posts
define('TINYIB_MAXPOSTSIZE', 16000);
define('TINYIB_RATELIMIT', 7);               // seconds between posts from same IP
define('TINYIB_TRIPSEED', '1231');           // change this
define('TINYIB_USECAPTCHA', true);
define('TINYIB_CAPTCHASALT', 'CAPTCHASALT'); // change this
define('TINYIB_THUMBWIDTH', 200);
define('TINYIB_THUMBHEIGHT', 300);
define('TINYIB_REPLYWIDTH', 200);
define('TINYIB_REPLYHEIGHT', 300);
define('TINYIB_TIMEZONE', '');              // empty = server default
define('TINYIB_DATEFORMAT', 'D Y-m-d g:ia');
define('TINYIB_DBPOSTS', 'posts');
define('TINYIB_DBBANS', 'bans');
define('TINYIB_DBLOCKS', 'locked_threads');
define('TINYIB_DBPATH', __DIR__ . '/db/database.db');
define('TINYIB_MAX_FILE_SIZE', 2 * 1024 * 1024); // 2 MB

// ─── Bootstrap ───────────────────────────────────────────────────────────────
if (TINYIB_TIMEZONE !== '') {
    date_default_timezone_set(TINYIB_TIMEZONE);
}

if (!is_dir(__DIR__ . '/db')) {
    mkdir(__DIR__ . '/db', 0755, true);
}

// Secure session
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; form-action 'self';");

// ─── Database ────────────────────────────────────────────────────────────────
try {
    $db = new PDO('sqlite:' . TINYIB_DBPATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    validateDatabaseSchema($db);
} catch (PDOException $e) {
    fancyDie('Could not connect to database: ' . $e->getMessage());
}

function validateDatabaseSchema(PDO $db): void
{
    $db->exec('
        CREATE TABLE IF NOT EXISTS ' . TINYIB_DBPOSTS . ' (
            id INTEGER PRIMARY KEY,
            parent INTEGER NOT NULL DEFAULT 0,
            timestamp INTEGER NOT NULL,
            bumped INTEGER NOT NULL,
            ip TEXT NOT NULL,
            name TEXT NOT NULL,
            tripcode TEXT NOT NULL,
            email TEXT NOT NULL DEFAULT "",
            nameblock TEXT NOT NULL,
            subject TEXT NOT NULL DEFAULT "",
            message TEXT NOT NULL,
            password TEXT NOT NULL DEFAULT "",
            file TEXT NOT NULL DEFAULT "",
            file_hex TEXT NOT NULL DEFAULT "",
            file_original TEXT NOT NULL DEFAULT "",
            file_size INTEGER NOT NULL DEFAULT 0,
            file_size_formatted TEXT NOT NULL DEFAULT "",
            image_width INTEGER NOT NULL DEFAULT 0,
            image_height INTEGER NOT NULL DEFAULT 0,
            thumb TEXT NOT NULL DEFAULT "",
            thumb_width INTEGER NOT NULL DEFAULT 0,
            thumb_height INTEGER NOT NULL DEFAULT 0
        )
    ');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_posts_parent ON ' . TINYIB_DBPOSTS . '(parent)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_posts_bumped ON ' . TINYIB_DBPOSTS . '(bumped DESC)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_posts_ip ON ' . TINYIB_DBPOSTS . '(ip)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_posts_file_hex ON ' . TINYIB_DBPOSTS . '(file_hex)');

    $db->exec('
        CREATE TABLE IF NOT EXISTS ' . TINYIB_DBBANS . ' (
            id INTEGER PRIMARY KEY,
            ip TEXT NOT NULL,
            timestamp INTEGER NOT NULL,
            expire INTEGER NOT NULL DEFAULT 0,
            reason TEXT NOT NULL DEFAULT ""
        )
    ');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_bans_ip ON ' . TINYIB_DBBANS . '(ip)');

    $db->exec('
        CREATE TABLE IF NOT EXISTS ' . TINYIB_DBLOCKS . ' (
            id INTEGER PRIMARY KEY,
            thread INTEGER NOT NULL UNIQUE
        )
    ');
}

function fetchAndExecute(string $sql, array $parameters = []): array
{
    global $db;
    $stmt = $db->prepare($sql);
    $stmt->execute($parameters);
    return $stmt->fetchAll();
}

// ─── Auth helpers ────────────────────────────────────────────────────────────
function manageCheckLogIn(): array
{
    $loggedin = false;
    $isadmin  = false;

    if (isset($_POST['password'])) {
        if ($_POST['password'] === TINYIB_ADMINPASS) {
            $_SESSION['tinyib'] = TINYIB_ADMINPASS;
        } elseif (TINYIB_MODPASS !== '' && $_POST['password'] === TINYIB_MODPASS) {
            $_SESSION['tinyib'] = TINYIB_MODPASS;
        }
    }

    if (isset($_SESSION['tinyib'])) {
        if ($_SESSION['tinyib'] === TINYIB_ADMINPASS) {
            $loggedin = true;
            $isadmin  = true;
        } elseif (TINYIB_MODPASS !== '' && $_SESSION['tinyib'] === TINYIB_MODPASS) {
            $loggedin = true;
        }
    }

    return [$loggedin, $isadmin];
}

list($loggedin, $isadmin) = manageCheckLogIn();
define('LOGGED_IN', $loggedin);
define('IS_ADMIN', $isadmin);

// ─── CSRF ────────────────────────────────────────────────────────────────────
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
}

function validateCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        fancyDie('Invalid security token. Please go back and try again.');
    }
}

// ─── Utility ─────────────────────────────────────────────────────────────────
function cleanString(string $string): string
{
    return str_replace(['<', '>', '"'], ['&lt;', '&gt;', '&quot;'], $string);
}

function fancyDie(string $message, int $depth = 1): never
{
    http_response_code(400);
    $safe = str_replace("\n", '<br>', htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Error</title><style>body{background:#111;color:#ccc;font-family:system-ui,sans-serif;max-width:600px;margin:3rem auto;padding:1rem;line-height:1.5}</style></head><body>'
       . $safe
       . '<div style="margin-top:2rem;opacity:.6;font-size:.85rem">Powered by: <a href="https://github.com/ClaireIsAlive/Claire" style="color:#aaa">Claire</a></div>'
       . '</body></html>';
    exit;
}

function convertBytes(int $number): string
{
    if ($number < 1024) {
        return $number . 'B';
    }
    if ($number < 1048576) {
        return sprintf('%.2fKB', $number / 1024);
    }
    if ($number < 1073741824) {
        return sprintf('%.2fMB', $number / 1048576);
    }
    return sprintf('%.2fGB', $number / 1073741824);
}

function redirect(string $url = '?do=page&p=0'): never
{
    header('Location: ' . $url);
    exit;
}

// ─── Post model helpers ──────────────────────────────────────────────────────
function newPost(): array
{
    return [
        'parent' => '0', 'timestamp' => '0', 'bumped' => '0', 'ip' => '',
        'name' => '', 'tripcode' => '', 'email' => '', 'nameblock' => '',
        'subject' => '', 'message' => '', 'password' => '',
        'file' => '', 'file_hex' => '', 'file_original' => '',
        'file_size' => '0', 'file_size_formatted' => '',
        'image_width' => '0', 'image_height' => '0',
        'thumb' => '', 'thumb_width' => '0', 'thumb_height' => '0',
    ];
}

function nameAndTripcode(string $name): array
{
    if (preg_match('/(#|!)(.*)/', $name, $regs)) {
        $cap = $regs[2];
        $cap_full = '#' . $regs[2];

        if (function_exists('mb_convert_encoding')) {
            $recoded = mb_convert_encoding($cap, 'SJIS', 'UTF-8');
            if ($recoded !== '') {
                $cap = $recoded;
            }
        }

        if (strpos($name, '#') === false) {
            $cap_delimiter = '!';
        } elseif (strpos($name, '!') === false) {
            $cap_delimiter = '#';
        } else {
            $cap_delimiter = (strpos($name, '#') < strpos($name, '!')) ? '#' : '!';
        }

        $is_secure_trip = false;
        $cap_secure = '';
        if (preg_match('/(.*)(' . preg_quote($cap_delimiter, '/') . ')(.*)/', $cap, $regs_secure)) {
            $cap = $regs_secure[1];
            $cap_secure = $regs_secure[3];
            $is_secure_trip = true;
        }

        $tripcode = '';
        if ($cap !== '') {
            $cap = strtr($cap, '&amp;', '&');
            $cap = strtr($cap, '&#44;', ', ');
            $salt = substr($cap . 'H.', 1, 2);
            $salt = preg_replace('/[^\.-z]/', '.', $salt);
            $salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
            $tripcode = substr(crypt($cap, $salt), -10);
        }

        if ($is_secure_trip) {
            if ($cap !== '') {
                $tripcode .= '!';
            }
            $tripcode .= '!' . substr(md5($cap_secure . TINYIB_TRIPSEED), 2, 10);
        }

        return [preg_replace('/(' . preg_quote($cap_delimiter, '/') . ')(.*)/', '', $name), $tripcode];
    }
    return [$name, ''];
}

function nameBlock(string $name, string $tripcode, string $email, int $timestamp, string $modposttext): string
{
    $output = '<span class="postername">';
    $output .= ($name === '' && $tripcode === '') ? 'Anonymous' : htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($tripcode !== '') {
        $output .= '</span><span class="postertrip">!' . htmlspecialchars($tripcode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    $output .= '</span>';
    if ($email !== '') {
        $output = '<a href="mailto:' . htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $output . '</a>';
    }
    return $output . $modposttext . ' ' . date(TINYIB_DATEFORMAT, $timestamp);
}

function _postLink(array $matches): string
{
    $post = postByID((int)$matches[1]);
    if ($post) {
        $thread = ($post['parent'] == 0) ? $post['id'] : $post['parent'];
        return '<a href="?do=thread&id=' . $thread . '#' . $matches[1] . '">' . $matches[0] . '</a>';
    }
    return $matches[0];
}

function postLink(string $message): string
{
    return preg_replace_callback('/&gt;&gt;([0-9]+)/', '_postLink', $message) ?? $message;
}

function colorQuote(string $message): string
{
    if (!str_ends_with($message, "\n")) {
        $message .= "\n";
    }
    return preg_replace('/^(&gt;[^\>](.*))\n/m', '<span class="unkfunc">$1</span>' . "\n", $message) ?? $message;
}

// ─── Rendering ───────────────────────────────────────────────────────────────
function pageHeader(): string
{
    $title = htmlspecialchars(TINYIB_PAGETITLE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="pragma" content="no-cache">
<title>{$title}</title>
<link rel="icon" href="favicon.ico">
<style>
:root {
  --bg: #111;
  --bg2: rgba(20,20,20,.75);
  --text: #ccc;
  --muted: #707070;
  --accent: #cc1105;
  --link: #aaa;
  --link-hover: #fff;
  --green: #117743;
  --trip: #228854;
  --border: #444;
  --highlight: #2a1a14;
}
* { box-sizing: border-box; }
html { background: var(--bg); color: var(--text); font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 15px; line-height: 1.45; margin: 0; }
body { max-width: 920px; margin: 0 auto; padding: 0 12px 40px; }
a { color: var(--link); text-decoration: none; }
a:hover { color: var(--link-hover); text-decoration: underline; }
.logo { text-align: center; font-size: 1.8rem; font-weight: 600; color: #888; margin: 1.2rem 0; }
.logo a { color: inherit; }
.adminbar { text-align: right; font-size: .9rem; margin: .5rem 0; }
.replymode { background: #c03900; color: #fff; text-align: center; padding: .4rem; margin-bottom: 1rem; border-radius: 4px; }
#postarea { text-align: center; margin: 1.5rem 0; }
#postarea table { margin: 0 auto; text-align: left; border-collapse: collapse; }
.postblock { color: #fff; font-size: .8rem; text-align: right; padding-right: 8px; white-space: nowrap; }
input[type="text"], input[type="password"], textarea, select {
  background: var(--bg2); border: 1px solid #666; color: #fff; border-radius: 3px; padding: 5px 8px; font: inherit;
}
textarea { width: min(100%, 480px); resize: vertical; }
input[type="submit"], .managebutton {
  background: #333; color: #eee; border: 1px solid #666; border-radius: 4px; padding: 6px 14px; cursor: pointer; font: inherit;
}
input[type="submit"]:hover, .managebutton:hover { background: #444; }
.reply {
  background: var(--bg2); border: 1px solid var(--border); border-radius: 4px;
  max-width: 720px; padding: 8px 12px; margin: 6px 0; word-wrap: break-word;
}
.thumb { border: none; float: left; margin: 2px 16px 8px 0; max-width: 200px; height: auto; border-radius: 2px; }
.filesize { font-size: .85rem; color: var(--muted); }
.filetitle, .replytitle { color: var(--accent); font-size: 1.15em; font-weight: 600; }
.postername, .commentpostername { color: var(--green); font-weight: 600; }
.postertrip { color: var(--trip); }
.unkfunc { color: #789922; }
.spoiler { background: #444; color: #444; }
.spoiler:hover { background: #000; color: #fff; }
.omittedposts, .abbrev { color: var(--muted); font-style: italic; }
.doubledash { float: left; clear: both; color: var(--muted); margin-right: 4px; }
hr { border: none; border-top: 1px solid #333; margin: 1.2rem 0; }
.pagelinks { text-align: center; margin: 1.5rem 0; }
.footer { text-align: center; font-size: .8rem; color: var(--muted); margin-top: 2rem; }
.rules { font-size: .75rem; max-width: 360px; margin: .5rem auto; text-align: left; opacity: .8; }
@media (max-width: 640px) {
  body { padding: 0 8px 30px; }
  .thumb { max-width: 120px; margin-right: 10px; }
  #postarea table { width: 100%; }
  textarea { width: 100%; }
}
</style>
<script>
function quote(id) {
  const t = document.forms.postform?.message;
  if (!t) return;
  const text = ">>" + id + "\\n";
  if (t.selectionStart !== undefined) {
    const start = t.selectionStart, end = t.selectionEnd;
    t.value = t.value.substring(0, start) + text + t.value.substring(end);
    t.selectionStart = t.selectionEnd = start + text.length;
  } else {
    t.value += text;
  }
  t.focus();
}
</script>
</head>
HTML;
}

function pageFooter(): string
{
    return '</body></html>';
}

function buildPost(array $post, bool $isrespage): string
{
    // Lightweight markup
    $msg = $post['message'];
    $msg = preg_replace('#\*\*(.*?)\*\*#', '<b>$1</b>', $msg) ?? $msg;
    $msg = preg_replace('#\[s\](.*?)\[/s\]#', '<s>$1</s>', $msg) ?? $msg;
    $msg = preg_replace('#\*(.*?)\*#', '<i>$1</i>', $msg) ?? $msg;
    $msg = preg_replace('#\[u\](.*?)\[/u\]#', '<span style="text-decoration:underline">$1</span>', $msg) ?? $msg;
    $msg = preg_replace('#\%\%(.*?)\%\%#', '<span class="spoiler">$1</span>', $msg) ?? $msg;
    $msg = preg_replace("#\'\'(.*?)\'\'#", '<code>$1</code>', $msg) ?? $msg;

    $threadid = ($post['parent'] == 0) ? $post['id'] : $post['parent'];
    $postlink = '?do=thread&id=' . $threadid . '#' . $post['id'];
    $return = '';

    $image_desc = '';
    if ($post['file'] !== '') {
        $image_desc = cleanString($post['file_original']) . ' (' . $post['image_width'] . 'x' . $post['image_height'] . ', ' . $post['file_size_formatted'] . ')';
    }

    if ($post['parent'] == 0 && !$isrespage) {
        $note = isLocked((int)$threadid) ? '<em>(locked)</em> ' : '';
        $return .= '<span class="replylink">' . $note . '[<a href="?do=thread&id=' . $post['id'] . '">View thread</a>]&nbsp;</span>';
    }

    if ($post['parent'] != 0) {
        $return .= '<table><tbody><tr><td class="doubledash">&gt;&gt;</td><td class="reply" id="reply' . $post['id'] . '">';
    } elseif ($post['file'] !== '') {
        $return .= '<a target="_blank" href="db/' . htmlspecialchars($post['file'], ENT_QUOTES) . '">'
                 . '<img title="' . htmlspecialchars($image_desc, ENT_QUOTES) . '" src="db/' . htmlspecialchars($post['thumb'], ENT_QUOTES)
                 . '" alt="' . $post['id'] . '" class="thumb" width="' . $post['thumb_width'] . '" height="' . $post['thumb_height'] . '" loading="lazy"></a>';
    }

    $return .= '<a href="?do=delpost&id=' . $post['id'] . '" title="Delete">×</a> <a name="' . $post['id'] . '"></a> ';

    if ($post['subject'] !== '') {
        $return .= '<span class="filetitle">' . htmlspecialchars($post['subject'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span> ';
    }

    $return .= $post['nameblock'] . ' ';

    if (IS_ADMIN) {
        $return .= ' [<a href="?do=manage&p=bans&bans=' . urlencode($post['ip']) . '" title="Ban poster">' . htmlspecialchars($post['ip'], ENT_QUOTES) . '</a>]';
    }

    $return .= '<span class="reflink"><a href="' . $postlink . '">No.</a><a href="javascript:quote(\'' . $post['id'] . '\')">' . $post['id'] . '</a></span>';

    if ($post['parent'] != 0 && $post['file'] !== '') {
        $return .= '<br><a target="_blank" href="db/' . htmlspecialchars($post['file'], ENT_QUOTES) . '">'
                 . '<img title="' . htmlspecialchars($image_desc, ENT_QUOTES) . '" src="db/' . htmlspecialchars($post['thumb'], ENT_QUOTES)
                 . '" alt="' . $post['id'] . '" class="thumb" width="' . $post['thumb_width'] . '" height="' . $post['thumb_height'] . '" loading="lazy"></a>';
    }

    $return .= '<blockquote>' . $msg . '</blockquote>';

    if ($post['parent'] == 0) {
        if (!$isrespage && isset($post['omitted']) && $post['omitted'] > 0) {
            $return .= '<span class="omittedposts">' . $post['omitted'] . ' post(s) omitted. <a href="?do=thread&id=' . $post['id'] . '">Click here</a> to view.</span>';
        }
    } else {
        $return .= '</td></tr></tbody></table>';
    }

    return $return;
}

function buildPostBlock(int|string $parent): string
{
    if (CLAIRE_BLOGMODE && !$parent && !LOGGED_IN) {
        return '';
    }

    $body = '<div id="postarea"><form name="postform" id="postform" action="?do=post" method="post" enctype="multipart/form-data">'
          . '<input type="hidden" name="parent" value="' . htmlspecialchars((string)$parent, ENT_QUOTES) . '">'
          . csrfField()
          . '<table class="postform"><tbody>'
          . '<tr><td class="postblock">Name</td><td><input type="text" name="name" size="28" maxlength="75" placeholder="Anonymous"></td></tr>';

    if (!$parent) {
        $body .= '<tr><td class="postblock">Subject</td><td><input type="text" name="subject" size="40" maxlength="75"></td></tr>';
    }

    $body .= '<tr><td class="postblock">Message</td><td><textarea name="message" cols="48" rows="5" required></textarea></td></tr>';

    if (TINYIB_USECAPTCHA && !LOGGED_IN) {
        $captcha_key = md5((string)mt_rand());
        $captcha_expect = md5(TINYIB_CAPTCHASALT . substr(md5($captcha_key), 0, 4));
        $body .= '<tr><td class="postblock"><img src="captcha_png.php?key=' . htmlspecialchars($captcha_key, ENT_QUOTES) . '" alt="CAPTCHA" width="120" height="40"></td>'
               . '<td><input type="hidden" name="captcha_ex" value="' . htmlspecialchars($captcha_expect, ENT_QUOTES) . '">'
               . '<input type="text" name="captcha_out" size="10" maxlength="8" placeholder="CAPTCHA" required autocomplete="off"></td></tr>';
    }

    if (!CLAIRE_TEXTMODE) {
        $body .= '<tr><td class="postblock">Image</td><td><input type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp"></td></tr>';
    }

    $post_button = $parent ? 'Post Reply' : 'Create Thread';
    $opt_bump = $parent ? '<label><input type="checkbox" name="bump" id="bump" checked> Bump</label> ' : '';
    $opt_mod  = LOGGED_IN ? '<label><input type="checkbox" name="modpost" id="modpost"> Modpost</label> ' : '';
    $opt_raw  = LOGGED_IN ? '<label><input type="checkbox" name="rawhtml" id="rawhtml"> Raw HTML</label>' : '';

    $body .= '<tr><td class="postblock"></td><td><input type="submit" value="' . $post_button . '"> ' . $opt_bump . $opt_mod . $opt_raw . '</td></tr>'
           . '</tbody></table></form></div><hr>';

    return $body;
}

function buildPage(string $htmlposts, int|string $parent, int $pages = 0, int $thispage = 0): string
{
    $locked = $parent ? isLocked((int)$parent) : false;
    $returnlink = '';
    $pagelinks = '';

    if ($parent == 0) {
        $pages = max($pages, 0);
        $pagelinks = ($thispage === 0) ? '[ Previous ]' : '[ <a href="?do=page&p=' . ($thispage - 1) . '">Previous</a> ]';
        for ($i = 0; $i <= $pages; $i++) {
            $pagelinks .= ($thispage === $i) ? "[ $i ]" : "[ <a href=\"?do=page&p=$i\">$i</a> ]";
        }
        $pagelinks .= ($pages <= $thispage) ? '[ Next ]' : '[ <a href="?do=page&p=' . ($thispage + 1) . '">Next</a> ]';
    } else {
        $returnlink = '<span class="replylink">[<a href="?">Return</a>';
        if (LOGGED_IN) {
            $returnlink .= isLocked((int)$parent)
                ? ' | <a href="?do=lock&id=' . $parent . '">Unlock Thread</a>'
                : ' | <a href="?do=lock&id=' . $parent . '">Lock Thread</a>';
        }
        $returnlink .= ']</span>';
    }

    $body = '<body><div class="logo"><a href="?">' . htmlspecialchars(TINYIB_PAGETITLE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a></div>';

    if ($locked) {
        $body .= '<div class="replymode">This thread is locked. You can\'t reply any more.</div>';
    }

    if ($parent) {
        $body .= $returnlink . "\n" . $htmlposts;
    }

    if (!$locked) {
        $body .= buildPostBlock($parent);
    }

    if (!$parent) {
        $body .= $returnlink . "\n" . $htmlposts;
    }

    $body .= '<div class="adminbar">Powered by: <a href="https://github.com/ClaireIsAlive/Claire">Claire</a> (modernized)</div>'
           . '<div class="pagelinks">' . $pagelinks . '</div>';

    return pageHeader() . $body . pageFooter();
}

// ─── Data access ─────────────────────────────────────────────────────────────
function postByID(int $id): ?array
{
    $result = fetchAndExecute('SELECT * FROM ' . TINYIB_DBPOSTS . ' WHERE id = ? LIMIT 1', [$id]);
    return $result[0] ?? null;
}

function insertPost(array $post): int
{
    global $db;
    fetchAndExecute('
        INSERT INTO ' . TINYIB_DBPOSTS . ' (
            parent, timestamp, bumped, ip, name, tripcode, email, nameblock,
            subject, message, password, file, file_hex, file_original,
            file_size, file_size_formatted, image_width, image_height,
            thumb, thumb_width, thumb_height
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $post['parent'], time(), time(), $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $post['name'], $post['tripcode'], $post['email'], $post['nameblock'],
            $post['subject'], $post['message'], $post['password'],
            $post['file'], $post['file_hex'], $post['file_original'],
            $post['file_size'], $post['file_size_formatted'],
            $post['image_width'], $post['image_height'], $post['thumb'],
            $post['thumb_width'], $post['thumb_height'],
        ]
    );
    return (int)$db->lastInsertId();
}

function countPosts(): int
{
    $r = fetchAndExecute('SELECT COUNT(*) AS c FROM ' . TINYIB_DBPOSTS);
    return (int)$r[0]['c'];
}

function countThreads(): int
{
    $r = fetchAndExecute('SELECT COUNT(id) AS c FROM ' . TINYIB_DBPOSTS . ' WHERE parent = 0');
    return (int)$r[0]['c'];
}

function uniquePosts(): int
{
    $r = fetchAndExecute('SELECT COUNT(DISTINCT ip) AS c FROM ' . TINYIB_DBPOSTS);
    return (int)$r[0]['c'];
}

function postsInThreadByID(int $id): array
{
    return fetchAndExecute('SELECT * FROM ' . TINYIB_DBPOSTS . ' WHERE id = ? OR parent = ? ORDER BY id ASC', [$id, $id]);
}

function latestRepliesInThreadByID(int $id): array
{
    return fetchAndExecute(
        'SELECT * FROM ' . TINYIB_DBPOSTS . ' WHERE parent = ? ORDER BY id DESC LIMIT ' . (int)TINYIB_REPLIESTOSHOW,
        [$id]
    );
}

function getThreadRange(int $count, int $offset): array
{
    return fetchAndExecute(
        'SELECT * FROM ' . TINYIB_DBPOSTS . ' WHERE parent = 0 ORDER BY bumped DESC LIMIT ' . (int)$offset . ',' . (int)$count
    );
}

function lastPostByIP(): ?array
{
    $r = fetchAndExecute('SELECT * FROM ' . TINYIB_DBPOSTS . ' WHERE ip = ? ORDER BY id DESC LIMIT 1', [$_SERVER['REMOTE_ADDR'] ?? '']);
    return $r[0] ?? null;
}

function threadExistsByID(int $id): bool
{
    $r = fetchAndExecute('SELECT COUNT(id) AS c FROM ' . TINYIB_DBPOSTS . ' WHERE id = ? AND parent = 0', [$id]);
    return (int)$r[0]['c'] > 0;
}

function bumpThreadByID(int $id): void
{
    fetchAndExecute('UPDATE ' . TINYIB_DBPOSTS . ' SET bumped = ? WHERE id = ?', [time(), $id]);
}

function postsByHex(string $hex): array
{
    return fetchAndExecute('SELECT id, parent FROM ' . TINYIB_DBPOSTS . ' WHERE file_hex = ? LIMIT 1', [$hex]);
}

function deletePostImages(array $post): void
{
    if ($post['file'] !== '') {
        @unlink(__DIR__ . '/db/' . $post['file']);
    }
    if ($post['thumb'] !== '') {
        @unlink(__DIR__ . '/db/' . $post['thumb']);
    }
}

function deletePostByID(int $id): void
{
    $posts = postsInThreadByID($id);
    foreach ($posts as $post) {
        if ((int)$post['id'] !== $id) {
            deletePostImages($post);
            fetchAndExecute('DELETE FROM ' . TINYIB_DBPOSTS . ' WHERE id = ?', [$post['id']]);
        } else {
            $thispost = $post;
        }
    }
    if (isset($thispost)) {
        deletePostImages($thispost);
        fetchAndExecute('DELETE FROM ' . TINYIB_DBPOSTS . ' WHERE id = ?', [$thispost['id']]);
    }
}

function trimThreads(): void
{
    if (TINYIB_MAXTHREADS > 0) {
        $result = fetchAndExecute(
            'SELECT id FROM ' . TINYIB_DBPOSTS . ' WHERE parent = 0 ORDER BY bumped DESC LIMIT ' . TINYIB_MAXTHREADS . ',10'
        );
        foreach ($result as $post) {
            deletePostByID((int)$post['id']);
        }
    }
}

// Bans & locks
function banByIP(string $ip): ?array
{
    $r = fetchAndExecute('SELECT * FROM ' . TINYIB_DBBANS . ' WHERE ip = ? LIMIT 1', [$ip]);
    return $r[0] ?? null;
}

function banByID(int $id): ?array
{
    $r = fetchAndExecute('SELECT * FROM ' . TINYIB_DBBANS . ' WHERE id = ? LIMIT 1', [$id]);
    return $r[0] ?? null;
}

function allBans(): array
{
    return fetchAndExecute('SELECT * FROM ' . TINYIB_DBBANS . ' ORDER BY timestamp DESC');
}

function insertBan(array $ban): int
{
    global $db;
    fetchAndExecute('INSERT INTO ' . TINYIB_DBBANS . ' (ip, timestamp, expire, reason) VALUES (?,?,?,?)',
        [$ban['ip'], time(), $ban['expire'], $ban['reason']]);
    return (int)$db->lastInsertId();
}

function deleteBanByID(int $id): void
{
    fetchAndExecute('DELETE FROM ' . TINYIB_DBBANS . ' WHERE id = ?', [$id]);
}

function clearExpiredBans(): void
{
    $result = fetchAndExecute('SELECT id FROM ' . TINYIB_DBBANS . ' WHERE expire > 0 AND expire <= ?', [time()]);
    foreach ($result as $ban) {
        deleteBanByID((int)$ban['id']);
    }
}

function isLocked(int $thread): bool
{
    $r = fetchAndExecute('SELECT COUNT(*) AS c FROM ' . TINYIB_DBLOCKS . ' WHERE thread = ?', [$thread]);
    return (int)$r[0]['c'] > 0;
}

function lockThread(int $thread): void
{
    if (!isLocked($thread)) {
        fetchAndExecute('INSERT INTO ' . TINYIB_DBLOCKS . ' (thread) VALUES (?)', [$thread]);
    }
}

function unlockThread(int $thread): void
{
    fetchAndExecute('DELETE FROM ' . TINYIB_DBLOCKS . ' WHERE thread = ?', [$thread]);
}

function getAllLocks(): array
{
    $result = fetchAndExecute('SELECT thread FROM ' . TINYIB_DBLOCKS);
    return array_column($result, 'thread');
}

// ─── Validation helpers ──────────────────────────────────────────────────────
function checkBanned(): void
{
    $ban = banByIP($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ban) {
        if ((int)$ban['expire'] === 0 || (int)$ban['expire'] > time()) {
            $expire = ((int)$ban['expire'] > 0)
                ? 'Your ban will expire ' . date(TINYIB_DATEFORMAT, (int)$ban['expire'])
                : 'The ban on your IP address is permanent.';
            $reason = $ban['reason'] !== '' ? '<br>Reason: ' . htmlspecialchars($ban['reason'], ENT_QUOTES) : '';
            fancyDie('You have been banned from posting. ' . $expire . $reason);
        }
        clearExpiredBans();
    }
}

function checkFlood(): void
{
    $last = lastPostByIP();
    if ($last && (time() - (int)$last['timestamp']) < TINYIB_RATELIMIT) {
        $wait = TINYIB_RATELIMIT - (time() - (int)$last['timestamp']);
        fancyDie("Please wait $wait second(s) before posting again.");
    }
}

function checkMessageSize(): void
{
    if (strlen($_POST['message'] ?? '') > TINYIB_MAXPOSTSIZE) {
        fancyDie('Message too long (max ' . TINYIB_MAXPOSTSIZE . ' characters).');
    }
}

function setParent(): string
{
    if (isset($_POST['parent']) && $_POST['parent'] !== '0') {
        if (!threadExistsByID((int)$_POST['parent'])) {
            fancyDie('Invalid parent thread ID.');
        }
        return (string)$_POST['parent'];
    }
    return '0';
}

function checkDuplicateImage(string $hex): void
{
    $matches = postsByHex($hex);
    if ($matches) {
        $loc = ($matches[0]['parent'] == 0) ? $matches[0]['id'] : $matches[0]['parent'];
        fancyDie('That file has already been posted <a href="?do=thread&id=' . $loc . '#' . $matches[0]['id'] . '">here</a>.');
    }
}

// ─── Image handling ──────────────────────────────────────────────────────────
function thumbnailDimensions(int $width, int $height, bool $is_reply): array
{
    $max_w = $is_reply ? TINYIB_REPLYWIDTH : TINYIB_THUMBWIDTH;
    $max_h = $is_reply ? TINYIB_REPLYHEIGHT : TINYIB_THUMBHEIGHT;
    if ($width > $max_w || $height > $max_h) {
        return [$max_w, $max_h];
    }
    return [$width, $height];
}

function createThumbnail(string $src, string $dest, int $new_w, int $new_h): bool
{
    $info = @getimagesize($src);
    if ($info === false) {
        return false;
    }

    $mime = $info['mime'];
    $src_img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($src),
        'image/png'  => @imagecreatefrompng($src),
        'image/gif'  => @imagecreatefromgif($src),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default      => false,
    };

    if ($src_img === false) {
        return false;
    }

    $old_x = imagesx($src_img);
    $old_y = imagesy($src_img);
    $percent = ($old_x > $old_y) ? ($new_w / $old_x) : ($new_h / $old_y);
    $thumb_w = (int)round($old_x * $percent);
    $thumb_h = (int)round($old_y * $percent);

    $dst = imagecreatetruecolor($thumb_w, $thumb_h);
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $thumb_w, $thumb_h, $transparent);
    }

    imagecopyresampled($dst, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y);

    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($dst, $dest, 75),
        'image/png'  => imagepng($dst, $dest, 6),
        'image/gif'  => imagegif($dst, $dest),
        'image/webp' => function_exists('imagewebp') ? imagewebp($dst, $dest, 80) : false,
        default      => false,
    };

    imagedestroy($dst);
    imagedestroy($src_img);
    return $ok;
}

// ─── Page views ──────────────────────────────────────────────────────────────
function viewPage(int $pagenum): string
{
    $pagecount = max(0, (int)ceil(countThreads() / TINYIB_THREADSPERPAGE) - 1);
    if ($pagenum < 0 || $pagenum > $pagecount) {
        fancyDie('Invalid page number.');
    }

    $htmlposts = [];
    $threads = getThreadRange(TINYIB_THREADSPERPAGE, $pagenum * TINYIB_THREADSPERPAGE);

    foreach ($threads as $thread) {
        $replies = latestRepliesInThreadByID((int)$thread['id']);
        $htmlreplies = [];
        foreach ($replies as $reply) {
            $htmlreplies[] = buildPost($reply, false);
        }
        $thread['omitted'] = (count($htmlreplies) === 3)
            ? (count(postsInThreadByID((int)$thread['id'])) - 4)
            : 0;
        $htmlposts[] = buildPost($thread, false) . implode('', array_reverse($htmlreplies)) . "<br clear=\"left\">\n<hr>";
    }

    return buildPage(implode('', $htmlposts), 0, $pagecount, $pagenum);
}

function viewThread(int $id): string
{
    $htmlposts = [];
    foreach (postsInThreadByID($id) as $post) {
        $htmlposts[] = buildPost($post, true);
    }
    $htmlposts[] = "<br clear=\"left\">\n<hr>";
    return buildPage(implode('', $htmlposts), $id);
}

// ─── Management UI ───────────────────────────────────────────────────────────
function adminBar(): string
{
    if (!LOGGED_IN) {
        return '[<a href="?">Return</a>]';
    }
    $text = IS_ADMIN ? '[<a href="?do=manage&p=bans">Bans</a>] ' : '';
    $text .= '[<a href="?do=manage&p=threads">Thread list</a>] '
           . '[<a href="?do=manage&p=moderate">Moderate Post</a>] '
           . '[<a href="?do=manage&p=logout">Log Out</a>] '
           . '[<a href="?">Return</a>]';
    return $text;
}

function managePage(string $text): string
{
    $adminbar = adminBar();
    $body = '<body><div class="adminbar">' . $adminbar . '</div><div class="logo"></div><hr>'
          . '<div class="replymode">Manage mode</div>' . $text . '<hr>';
    return pageHeader() . $body . pageFooter();
}

function manageLogInForm(): string
{
    return '<form method="post" action="?do=manage&p=home">'
         . csrfField()
         . '<fieldset style="max-width:320px;margin:2rem auto;padding:1.5rem;border:1px solid #555;border-radius:6px">'
         . '<legend>Staff login</legend>'
         . '<input type="password" name="password" autofocus placeholder="Password" style="width:100%;margin-bottom:1rem">'
         . '<input type="submit" value="Login" class="managebutton" style="width:100%">'
         . '</fieldset></form>';
}

function manageBanForm(): string
{
    $banstr = $_GET['bans'] ?? '';
    return '<form method="post" action="?do=manage&p=bans">'
         . csrfField()
         . '<fieldset><legend>Ban an IP</legend>'
         . '<label>IP: <input type="text" name="ip" value="' . htmlspecialchars($banstr, ENT_QUOTES) . '" required></label> '
         . '<input type="submit" value="Ban" class="managebutton"><br><br>'
         . '<label>Expire (sec): <input type="text" name="expire" value="0" size="8"></label> '
         . '<small><a href="#" onclick="document.querySelector(\'[name=expire]\').value=\'3600\';return false">1h</a> · '
         . '<a href="#" onclick="document.querySelector(\'[name=expire]\').value=\'86400\';return false">1d</a> · '
         . '<a href="#" onclick="document.querySelector(\'[name=expire]\').value=\'604800\';return false">1w</a> · '
         . '<a href="#" onclick="document.querySelector(\'[name=expire]\').value=\'0\';return false">never</a></small><br><br>'
         . '<label>Reason: <input type="text" name="reason" size="40"></label>'
         . '</fieldset></form><br>';
}

function manageBansTable(): string
{
    $all = allBans();
    if (!$all) {
        return '<p>No active bans.</p>';
    }
    $text = '<table border="1" cellpadding="6" style="border-collapse:collapse;width:100%"><tr><th>IP</th><th>Set</th><th>Expires</th><th>Reason</th><th></th></tr>';
    foreach ($all as $ban) {
        $expire = ((int)$ban['expire'] > 0) ? date('y/m/d H:i', (int)$ban['expire']) : 'Never';
        $reason = $ban['reason'] === '' ? '—' : htmlspecialchars($ban['reason'], ENT_QUOTES);
        $text .= '<tr><td>' . htmlspecialchars($ban['ip'], ENT_QUOTES) . '</td>'
               . '<td>' . date('y/m/d H:i', (int)$ban['timestamp']) . '</td>'
               . '<td>' . $expire . '</td><td>' . $reason . '</td>'
               . '<td><a href="?do=manage&p=bans&lift=' . $ban['id'] . '">lift</a></td></tr>';
    }
    return $text . '</table>';
}

function manageModeratePostForm(): string
{
    return '<form method="get"><input type="hidden" name="do" value="manage"><input type="hidden" name="p" value="moderate">'
         . '<fieldset><legend>Moderate a post</legend>'
         . 'Post ID: <input type="text" name="moderate" autofocus> <input type="submit" value="Go" class="managebutton">'
         . '</fieldset></form><br>';
}

function manageModeratePost(array $post): string
{
    $ban = banByIP($post['ip']);
    $ban_disabled = (!$ban && IS_ADMIN) ? '' : ' disabled';
    $post_html = buildPost($post, true);
    $type = ($post['parent'] == 0) ? 'Thread' : 'Post';

    return '<fieldset><legend>Moderating ' . $type . ' No.' . $post['id'] . '</legend>'
         . '<div style="float:right;max-width:50%">' . $post_html . '</div>'
         . '<form method="get" style="margin-bottom:1rem"><input type="hidden" name="do" value="manage"><input type="hidden" name="p" value="delete">'
         . '<input type="hidden" name="delete" value="' . $post['id'] . '"><input type="submit" value="Delete ' . $type . '" class="managebutton"></form>'
         . '<form method="get"><input type="hidden" name="do" value="manage"><input type="hidden" name="p" value="bans">'
         . '<input type="hidden" name="bans" value="' . htmlspecialchars($post['ip'], ENT_QUOTES) . '">'
         . '<input type="submit" value="Ban Poster" class="managebutton"' . $ban_disabled . '></form>'
         . '</fieldset><br style="clear:both">';
}

function manageAllThreads(): string
{
    $threads = getThreadRange(10000, 0);
    $locks = getAllLocks();
    $ret = '<table style="width:100%;border-collapse:collapse"><thead style="background:#400;color:#fff"><tr>'
         . '<th>#</th><th>Subject</th><th>First post</th><th>Created</th><th>Last Bump</th><th>Locked</th></tr></thead><tbody>';

    foreach ($threads as $thread) {
        $locked = in_array($thread['id'], $locks, true);
        $bump = ((int)$thread['bumped'] > 1000) ? date(TINYIB_DATEFORMAT, (int)$thread['bumped']) : '—';
        $ret .= '<tr><td><a href="?do=thread&id=' . $thread['id'] . '">#' . $thread['id'] . '</a></td>'
              . '<td>' . htmlspecialchars($thread['subject'], ENT_QUOTES) . '</td>'
              . '<td>' . htmlspecialchars(mb_substr($thread['message'], 0, 60), ENT_QUOTES) . '</td>'
              . '<td>' . date(TINYIB_DATEFORMAT, (int)$thread['timestamp']) . '</td>'
              . '<td><a href="?do=manage&p=bump&id=' . $thread['id'] . '" title="Bump">' . $bump . '</a></td>'
              . '<td>' . ($locked ? 'Locked' : '—') . '</td></tr>';
    }
    return $ret . '</tbody></table>';
}

// ─── Controllers ─────────────────────────────────────────────────────────────
function handlePost(): void
{
    global $redirect;
    if (!isset($_POST['message']) && !isset($_FILES['file'])) {
        fancyDie('Invalid request');
    }

    if (!LOGGED_IN) {
        checkBanned();
        checkMessageSize();
        checkFlood();
        validateCsrf();
    } else {
        validateCsrf();
    }

    if (TINYIB_USECAPTCHA && !LOGGED_IN) {
        $expect = $_POST['captcha_ex'] ?? '';
        $out = $_POST['captcha_out'] ?? '';
        if ($expect !== md5(TINYIB_CAPTCHASALT . $out)) {
            fancyDie('Incorrect CAPTCHA.');
        }
    }

    $modpost = LOGGED_IN && isset($_POST['modpost']);
    $rawhtml = LOGGED_IN && isset($_POST['rawhtml']);
    $bump    = isset($_POST['bump']);

    $post = newPost();
    $post['parent'] = setParent();
    $post['ip'] = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    list($post['name'], $post['tripcode']) = nameAndTripcode($_POST['name'] ?? '');
    $post['name'] = cleanString(substr($post['name'], 0, 75));
    $post['subject'] = isset($_POST['subject']) ? cleanString(substr($_POST['subject'], 0, 75)) : '';

    $modposttext = $modpost
        ? (IS_ADMIN ? ' <span class="moderator">## Admin</span>' : ' <span class="moderator">## Mod</span>')
        : '';

    if ($rawhtml) {
        $post['message'] = $_POST['message'] ?? '';
    } else {
        $raw = rtrim($_POST['message'] ?? '');
        $post['message'] = str_replace("\n", '<br>', colorQuote(postLink(cleanString($raw))));
    }

    $post['nameblock'] = nameBlock($post['name'], $post['tripcode'], '', time(), $modposttext);

    // File upload
    if (isset($_FILES['file']) && ($_FILES['file']['name'] ?? '') !== '') {
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            fancyDie('Upload error (code ' . $file['error'] . ').');
        }
        if ($file['size'] > TINYIB_MAX_FILE_SIZE) {
            fancyDie('File larger than ' . convertBytes(TINYIB_MAX_FILE_SIZE) . '.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            fancyDie('Invalid upload.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => '.jpg',
            'image/png'  => '.png',
            'image/gif'  => '.gif',
            'image/webp' => '.webp',
        ];
        if (!isset($allowed[$mime])) {
            fancyDie('Only JPEG, PNG, GIF and WebP are allowed.');
        }

        $post['file_original'] = substr(htmlspecialchars($file['name'], ENT_QUOTES), 0, 50);
        $post['file_hex'] = md5_file($file['tmp_name']);
        $post['file_size'] = (string)$file['size'];
        $post['file_size_formatted'] = convertBytes((int)$file['size']);

        checkDuplicateImage($post['file_hex']);

        $ext = $allowed[$mime];
        $basename = bin2hex(random_bytes(8)) . '_' . time();
        $post['file'] = $basename . $ext;
        $post['thumb'] = 'thumb_' . $basename . $ext;
        $file_location = __DIR__ . '/db/' . $post['file'];
        $thumb_location = __DIR__ . '/db/' . $post['thumb'];

        if (!move_uploaded_file($file['tmp_name'], $file_location)) {
            fancyDie('Could not store uploaded file.');
        }

        $info = getimagesize($file_location);
        if ($info === false) {
            unlink($file_location);
            fancyDie('Failed to read image.');
        }
        $post['image_width'] = (string)$info[0];
        $post['image_height'] = (string)$info[1];

        list($tw, $th) = thumbnailDimensions((int)$info[0], (int)$info[1], $post['parent'] !== '0');
        if (!createThumbnail($file_location, $thumb_location, $tw, $th)) {
            unlink($file_location);
            fancyDie('Could not create thumbnail.');
        }
        $tinfo = getimagesize($thumb_location);
        $post['thumb_width'] = (string)$tinfo[0];
        $post['thumb_height'] = (string)$tinfo[1];
    }

    if (!CLAIRE_TEXTMODE && $post['file'] === '' && $post['parent'] === '0') {
        fancyDie('An image is required to start a thread.');
    }
    if (str_replace('<br>', '', $post['message']) === '') {
        fancyDie('Please enter a message.');
    }

    $post['id'] = insertPost($post);
    $redirect = '?do=thread&id=' . ($post['parent'] === '0' ? $post['id'] : $post['parent']) . '#' . $post['id'];
    trimThreads();
    if ($post['parent'] !== '0' && $bump) {
        bumpThreadByID((int)$post['parent']);
    }
}

function handleDeletePost(): void
{
    global $redirect;
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        fancyDie('No post selected.');
    }
    $post = postByID((int)$_GET['id']);
    if (!$post) {
        fancyDie('Post not found.');
    }

    $allowed = LOGGED_IN || (
        (time() - (int)$post['timestamp'] < TINYIB_DELETE_TIMEOUT) &&
        ($post['ip'] === ($_SERVER['REMOTE_ADDR'] ?? ''))
    );

    if (!$allowed) {
        fancyDie('You have ' . TINYIB_DELETE_TIMEOUT . ' seconds to delete your own posts.');
    }

    if (isset($_GET['force']) && $_GET['force'] === '1') {
        deletePostByID((int)$post['id']);
        fancyDie('Post successfully deleted.', 2);
    }

    $msg = 'Are you sure you want to delete post #' . $post['id'] . "?\n";
    if ($post['parent'] == 0) {
        $msg .= "Deleting this post will delete the entire thread.\n";
    }
    $msg .= 'Click <a href="?do=delpost&id=' . $post['id'] . '&force=1">here</a> to confirm.';
    fancyDie($msg);
}

function handleManage(): string
{
    global $redirect;
    $redirect = false;
    $text = '';

    if (!isset($_GET['p'])) {
        redirect('?do=manage&p=home');
    }

    if (!LOGGED_IN) {
        return managePage(manageLogInForm());
    }

    switch ($_GET['p']) {
        case 'bans':
            if (!IS_ADMIN) {
                redirect('?do=manage&p=home');
            }
            clearExpiredBans();
            if (isset($_POST['ip']) && $_POST['ip'] !== '') {
                validateCsrf();
                if (banByIP($_POST['ip'])) {
                    fancyDie('That IP is already banned.');
                }
                $ban = [
                    'ip' => $_POST['ip'],
                    'expire' => ((int)($_POST['expire'] ?? 0) > 0) ? time() + (int)$_POST['expire'] : 0,
                    'reason' => $_POST['reason'] ?? '',
                ];
                insertBan($ban);
                $text .= '<b>Banned ' . htmlspecialchars($ban['ip'], ENT_QUOTES) . '</b><br>';
            } elseif (isset($_GET['lift'])) {
                $ban = banByID((int)$_GET['lift']);
                if ($ban) {
                    deleteBanByID((int)$_GET['lift']);
                    $text .= '<b>Lifted ban on ' . htmlspecialchars($ban['ip'], ENT_QUOTES) . '</b><br>';
                }
            }
            $text .= manageBanForm() . manageBansTable();
            break;

        case 'delete':
            $post = postByID((int)($_GET['delete'] ?? 0));
            if ($post) {
                deletePostByID((int)$post['id']);
                $text .= '<b>Post No.' . $post['id'] . ' deleted.</b>';
            } else {
                fancyDie('Post not found.');
            }
            break;

        case 'moderate':
            if (isset($_GET['moderate']) && (int)$_GET['moderate'] > 0) {
                $post = postByID((int)$_GET['moderate']);
                $text .= $post ? manageModeratePost($post) : fancyDie('Post not found.');
            } else {
                $text .= manageModeratePostForm();
            }
            break;

        case 'bump':
            if (!isset($_GET['id'])) {
                fancyDie('Invalid request.');
            }
            bumpThreadByID((int)$_GET['id']);
            redirect('?do=manage&p=threads');
            break;

        case 'logout':
            $_SESSION['tinyib'] = '';
            session_destroy();
            redirect('?do=manage&p=login');
            break;

        case 'home':
            $text .= 'Currently ' . countPosts() . ' posts in ' . countThreads()
                   . ' threads, made by ' . uniquePosts() . ' users.<br>'
                   . 'There are ' . count(allBans()) . ' ban(s).';
            break;

        case 'threads':
            $text = manageAllThreads();
            break;

        default:
            fancyDie('Invalid request.');
    }

    return managePage($text);
}

// ─── Router ──────────────────────────────────────────────────────────────────
if (TINYIB_TRIPSEED === '' || TINYIB_ADMINPASS === '') {
    fancyDie('Error: TINYIB_TRIPSEED and TINYIB_ADMINPASS must be configured.');
}
if (!is_writable(__DIR__ . '/db')) {
    fancyDie("Error: Can't write to directory 'db'.");
}

$redirect = true;

if (!isset($_GET['do'])) {
    redirect('?do=page&p=0');
}

switch ($_GET['do']) {
    case 'page':
        $p = isset($_GET['p']) ? (int)$_GET['p'] : 0;
        echo viewPage($p);
        break;

    case 'thread':
        if (!isset($_GET['id'])) {
            redirect('?do=page&p=0');
        }
        echo viewThread((int)$_GET['id']);
        break;

    case 'post':
        handlePost();
        redirect($redirect);
        break;

    case 'delpost':
        handleDeletePost();
        break;

    case 'lock':
        if (!isset($_GET['id']) || (int)$_GET['id'] <= 0) {
            redirect('?do=page&p=0');
        }
        $tid = (int)$_GET['id'];
        if (isLocked($tid)) {
            unlockThread($tid);
        } else {
            lockThread($tid);
        }
        redirect('?do=thread&id=' . $tid);
        break;

    case 'manage':
        echo handleManage();
        break;

    default:
        fancyDie('Invalid request.');
}
