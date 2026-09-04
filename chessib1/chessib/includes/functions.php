<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function generate_csrf(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verify_csrf(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], (string)$token);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_banned(string $ip): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM bans WHERE ip = ? AND (expires = 0 OR expires > ?) ORDER BY id DESC LIMIT 1');
    $stmt->execute([$ip, time()]);
    $ban = $stmt->fetch();
    return $ban ?: null;
}

function make_tripcode(string $name): array
{
    $trip = '';
    $plain = $name;
    if (str_contains($name, '#')) {
        [$plain, $secret] = explode('#', $name, 2);
        $plain = trim($plain);
        $secret = trim($secret);
        if ($secret !== '') {
            // Classic-style tripcode (simple, not crypt for modern simplicity)
            $trip = '!' . substr(base64_encode(hash('sha256', 'chessib' . $secret, true)), 0, 10);
        }
    }
    $plain = mb_substr($plain, 0, MAX_NAME_LENGTH);
    if ($plain === '') {
        $plain = DEFAULT_NAME;
    }
    return [$plain, $trip];
}

function format_comment(string $text, int $thread_id = 0): string
{
    $text = e($text);
    // Greentext
    $text = preg_replace('/^(&gt;.*)$/m', '<span class="greentext">$1</span>', $text);
    // >>123 post links
    $text = preg_replace_callback('/&gt;&gt;(\d+)/', function ($m) use ($thread_id) {
        $id = (int)$m[1];
        return '<a href="thread.php?id=' . ($thread_id ?: $id) . '#p' . $id . '" class="post-link">&gt;&gt;' . $id . '</a>';
    }, $text);
    // Basic markdown-ish: **bold** *italic*
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text);
    // Spoilers ||text||
    $text = preg_replace('/\|\|(.+?)\|\|/s', '<span class="spoiler">$1</span>', $text);
    // Newlines
    $text = nl2br($text, false);
    return $text;
}

function time_ago(int $ts): string
{
    $diff = time() - $ts;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date(DATE_FORMAT, $ts);
}

function human_size(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function process_upload(array $file): array
{
    if (!ALLOW_IMAGES || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['file' => '', 'file_orig' => '', 'file_size' => 0, 'image_w' => 0, 'image_h' => 0, 'thumb' => ''];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error code: ' . $file['error']);
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new RuntimeException('File too large (max ' . human_size(MAX_FILE_SIZE) . ')');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_MIME, true)) {
        throw new RuntimeException('Invalid file type. Allowed: jpg, png, gif, webp');
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default      => throw new RuntimeException('Unsupported mime'),
    };

    $orig_name = mb_substr(basename($file['name']), 0, 100);
    $unique = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $unique;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to save uploaded file');
    }

    [$w, $h] = getimagesize($dest) ?: [0, 0];
    if ($w < 1 || $h < 1) {
        @unlink($dest);
        throw new RuntimeException('Invalid image');
    }

    // Create thumbnail
    $thumb_name = 't_' . $unique;
    $thumb_path = THUMB_DIR . '/' . $thumb_name;
    create_thumbnail($dest, $thumb_path, $mime, $w, $h);

    return [
        'file'      => $unique,
        'file_orig' => $orig_name,
        'file_size' => (int)$file['size'],
        'image_w'   => $w,
        'image_h'   => $h,
        'thumb'     => $thumb_name,
    ];
}

function create_thumbnail(string $src, string $dest, string $mime, int $ow, int $oh): void
{
    $max_w = THUMB_MAX_W;
    $max_h = THUMB_MAX_H;
    $ratio = min($max_w / $ow, $max_h / $oh, 1.0);
    $nw = (int)round($ow * $ratio);
    $nh = (int)round($oh * $ratio);

    $src_img = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($src),
        'image/png'  => imagecreatefrompng($src),
        'image/gif'  => imagecreatefromgif($src),
        'image/webp' => imagecreatefromwebp($src),
        default      => false,
    };
    if (!$src_img) {
        throw new RuntimeException('Could not process image');
    }

    $thumb = imagecreatetruecolor($nw, $nh);
    if ($mime === 'image/png' || $mime === 'image/webp' || $mime === 'image/gif') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
        imagefilledrectangle($thumb, 0, 0, $nw, $nh, $transparent);
    }

    imagecopyresampled($thumb, $src_img, 0, 0, 0, 0, $nw, $nh, $ow, $oh);

    match ($mime) {
        'image/jpeg' => imagejpeg($thumb, $dest, 85),
        'image/png'  => imagepng($thumb, $dest, 6),
        'image/gif'  => imagegif($thumb, $dest),
        'image/webp' => imagewebp($thumb, $dest, 85),
    };

    imagedestroy($src_img);
    imagedestroy($thumb);
}

function delete_post_files(array $post): void
{
    if ($post['file'] !== '') {
        @unlink(UPLOAD_DIR . '/' . $post['file']);
    }
    if ($post['thumb'] !== '') {
        @unlink(THUMB_DIR . '/' . $post['thumb']);
    }
}

function get_thread_op(int $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM posts WHERE id = ? AND parent = 0');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_posts_in_thread(int $thread_id): array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM posts WHERE id = ? OR parent = ? ORDER BY id ASC');
    $stmt->execute([$thread_id, $thread_id]);
    return $stmt->fetchAll();
}

function get_threads(int $page = 1): array
{
    $db = get_db();
    $offset = ($page - 1) * THREADS_PER_PAGE;
    $stmt = $db->prepare('
        SELECT * FROM posts
        WHERE parent = 0
        ORDER BY stickied DESC, bumped DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->bindValue(1, THREADS_PER_PAGE, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function count_threads(): int
{
    $db = get_db();
    return (int)$db->query('SELECT COUNT(*) FROM posts WHERE parent = 0')->fetchColumn();
}

function get_reply_count(int $thread_id): int
{
    $db = get_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM posts WHERE parent = ?');
    $stmt->execute([$thread_id]);
    return (int)$stmt->fetchColumn();
}

function get_preview_replies(int $thread_id, int $limit = REPLIES_PREVIEW): array
{
    $db = get_db();
    // Get last N replies
    $stmt = $db->prepare('
        SELECT * FROM posts
        WHERE parent = ?
        ORDER BY id DESC
        LIMIT ?
    ');
    $stmt->bindValue(1, $thread_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    return array_reverse($rows);
}

function insert_post(array $data): int
{
    $db = get_db();
    $stmt = $db->prepare('
        INSERT INTO posts (
            parent, timestamp, bumped, ip, name, trip, subject, comment, password,
            file, file_orig, file_size, image_w, image_h, thumb, stickied, locked
        ) VALUES (
            :parent, :timestamp, :bumped, :ip, :name, :trip, :subject, :comment, :password,
            :file, :file_orig, :file_size, :image_w, :image_h, :thumb, :stickied, :locked
        )
    ');
    $stmt->execute([
        ':parent'    => $data['parent'],
        ':timestamp' => $data['timestamp'],
        ':bumped'    => $data['bumped'],
        ':ip'        => $data['ip'],
        ':name'      => $data['name'],
        ':trip'      => $data['trip'],
        ':subject'   => $data['subject'],
        ':comment'   => $data['comment'],
        ':password'  => $data['password'],
        ':file'      => $data['file'],
        ':file_orig' => $data['file_orig'],
        ':file_size' => $data['file_size'],
        ':image_w'   => $data['image_w'],
        ':image_h'   => $data['image_h'],
        ':thumb'     => $data['thumb'],
        ':stickied'  => $data['stickied'] ?? 0,
        ':locked'    => $data['locked'] ?? 0,
    ]);
    return (int)$db->lastInsertId();
}

function bump_thread(int $thread_id, int $time): void
{
    $db = get_db();
    $stmt = $db->prepare('UPDATE posts SET bumped = ? WHERE id = ? AND parent = 0');
    $stmt->execute([$time, $thread_id]);
}

function is_admin(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return !empty($_SESSION['chessib_admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        header('Location: admin.php');
        exit;
    }
}
?>
