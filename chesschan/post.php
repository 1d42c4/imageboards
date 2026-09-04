<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db = get_db();

$parent   = (int)($_POST['parent'] ?? 0);
$name     = trim($_POST['name'] ?? '');
$subject  = trim($_POST['subject'] ?? '');
$comment  = trim($_POST['comment'] ?? '');
$password = trim($_POST['password'] ?? '');

// Validation
if ($comment === '' || mb_strlen($comment) > MAX_COMMENT) {
    $redir = $parent ? "thread.php?id=$parent&error=" . urlencode('Comment is required') : "index.php?error=" . urlencode('Comment is required');
    header("Location: $redir");
    exit;
}

if (mb_strlen($name) > MAX_NAME) $name = mb_substr($name, 0, MAX_NAME);
if (mb_strlen($subject) > MAX_SUBJECT) $subject = mb_substr($subject, 0, MAX_SUBJECT);
if ($name === '') $name = 'Anonymous';

// File handling
$filename = '';
$original_name = '';
$filesize = 0;
$width = 0;
$height = 0;

if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];

    if ($file['size'] > MAX_FILE_SIZE) {
        $redir = $parent ? "thread.php?id=$parent&error=" . urlencode('File too large (max 8MB)') : "index.php?error=" . urlencode('File too large');
        header("Location: $redir");
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_TYPES)) {
        $redir = $parent ? "thread.php?id=$parent&error=" . urlencode('Invalid file type') : "index.php?error=" . urlencode('Invalid file type');
        header("Location: $redir");
        exit;
    }

    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default      => 'bin'
    };

    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $redir = $parent ? "thread.php?id=$parent&error=" . urlencode('Upload failed') : "index.php?error=" . urlencode('Upload failed');
        header("Location: $redir");
        exit;
    }

    $original_name = $file['name'];
    $filesize = $file['size'];
    $info = getimagesize($dest);
    if ($info) {
        $width = $info[0];
        $height = $info[1];
    }
}

// New threads require a file (classic chan style)
if ($parent === 0 && $filename === '') {
    header('Location: index.php?error=' . urlencode('New threads require an image'));
    exit;
}

// Insert
$stmt = $db->prepare("
    INSERT INTO posts (parent, name, subject, comment, password, filename, original_name, filesize, width, height, ip, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$hashed_pass = $password !== '' ? hash('sha256', $password . DELETE_PASSWORD_SALT) : '';

$stmt->execute([
    $parent,
    $name,
    $subject,
    $comment,
    $hashed_pass,
    $filename,
    $original_name,
    $filesize,
    $width,
    $height,
    $_SERVER['REMOTE_ADDR'] ?? '',
    time()
]);

$new_id = $db->lastInsertId();

if ($parent > 0) {
    header("Location: thread.php?id=$parent#p$new_id");
} else {
    header("Location: thread.php?id=$new_id");
}
exit;
