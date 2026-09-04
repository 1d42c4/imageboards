<?php
declare(strict_types=1);

/**
 * Create a new thread – PHP 8.5+ compatible
 * Image is mandatory (so every thread has a clickable thumbnail on the board)
 */

$file_name = '';
$errors    = [];

if (!isset($_FILES['image']) || !is_array($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    exit('An image is required to create a thread.');
}

$file          = $_FILES['image'];
$file_name_raw = $file['name'] ?? '';
$file_tmp      = $file['tmp_name'] ?? '';
$file_error    = $file['error'] ?? UPLOAD_ERR_NO_FILE;

if ($file_error !== UPLOAD_ERR_OK || !is_uploaded_file($file_tmp)) {
    http_response_code(400);
    exit('Upload failed (error ' . $file_error . ').');
}

$file_ext = strtolower(pathinfo($file_name_raw, PATHINFO_EXTENSION));
$allowed  = ['jpeg', 'jpg', 'png', 'gif'];

if (!in_array($file_ext, $allowed, true)) {
    http_response_code(400);
    exit('extension not allowed, please choose a picture file.');
}

$file_name = basename($file_name_raw);
$dest = 'cdn/' . $file_name;
if (!move_uploaded_file($file_tmp, $dest)) {
    http_response_code(500);
    exit('failed to move uploaded file.');
}

// Name / tripcode
$name = 'Anonymous';
if (isset($_POST['name']) && is_string($_POST['name']) && $_POST['name'] !== '') {
    $name = strip_tags($_POST['name']);
    $arr  = explode('#', $name, 2);
    if (count($arr) > 1) {
        $tripcode = crypt(
            crypt($arr[1], crypt('your', 'own')),
            crypt(phpversion(), 'hash')
        );
        $name = $arr[0] . '!' . $tripcode;
    }
}

$title = strip_tags($_POST['title'] ?? '');
$body  = strip_tags($_POST['body'] ?? '');
$date  = date('m/d/y (D) H:i:s');

$metainfo = '[name="' . $name . '", date="' . $date . '", title="' . $title . '", include="' . $file_name . '"]';

// Next thread id
$threads = [];
if (is_dir('thread/')) {
    $raw = scandir('thread/') ?: [];
    $threads = array_values(array_filter($raw, static function (string $f): bool {
        return is_file('thread/' . $f) && str_ends_with($f, '.txt');
    }));
    natsort($threads);
    $threads = array_values($threads);
}

$latestthread = 0;
if (!empty($threads)) {
    $latestthread = (int) str_replace('.txt', '', $threads[array_key_last($threads)]);
}
$latestthread += 1;

$filepath = 'thread/' . $latestthread . '.txt';
$fh = fopen($filepath, 'w+');
if ($fh === false) {
    http_response_code(500);
    exit('Could not create thread file');
}

fwrite($fh, $metainfo . "\n");
fwrite($fh, '#' . $body . "\n");
fclose($fh);

header('Location: thread.php?=' . $latestthread);
exit;
