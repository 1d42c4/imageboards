<?php
declare(strict_types=1);

/**
 * Create a new thread – PHP 8.5+ compatible
 * Newest threads appear at the top of the index.
 */

$file_name = '';
$errors    = [];

if (isset($_FILES['image']) && is_array($_FILES['image'])) {
    $file          = $_FILES['image'];
    $file_name_raw = $file['name'] ?? '';
    $file_tmp      = $file['tmp_name'] ?? '';
    $file_error    = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($file_error === UPLOAD_ERR_OK && is_uploaded_file($file_tmp)) {
        $file_ext = strtolower(pathinfo($file_name_raw, PATHINFO_EXTENSION));
        $allowed  = ['jpeg', 'jpg', 'png', 'gif'];

        if (!in_array($file_ext, $allowed, true)) {
            $errors[] = 'extension not allowed, please choose a picture file.';
        } else {
            $file_name = basename($file_name_raw); // keep original name for compatibility
            $dest = 'cdn/' . $file_name;
            if (!move_uploaded_file($file_tmp, $dest)) {
                $errors[]  = 'failed to move uploaded file.';
                $file_name = '';
            } else {
                echo 'Success';
            }
        }
    } elseif ($file_error !== UPLOAD_ERR_NO_FILE) {
        $errors[] = 'upload error code: ' . $file_error;
    }
} else {
    // Preserve original behaviour message
    echo 'kys off fam';
}

if (!empty($errors)) {
    print_r($errors);
}

// Name / tripcode
$name = 'Anonymous';
if (isset($_POST['name']) && is_string($_POST['name']) && $_POST['name'] !== '') {
    $name = strip_tags($_POST['name']);
    $arr  = explode('#', $name, 2);
    if (count($arr) > 1) {
        // Nested crypt kept for original tripcode compatibility (salt always supplied)
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

// Safely find next thread id
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
