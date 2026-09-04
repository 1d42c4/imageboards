<?php
declare(strict_types=1);

/**
 * Reply to an existing thread – PHP 8.5+ compatible
 * Replies are appended in chronological order.
 */

// Extract thread id from Referer (original behaviour) with safe fallback
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$arr     = explode('=', $referer, 2);
$id      = preg_replace('/[^0-9]/', '', $arr[1] ?? '') ?? '';

if ($id === '') {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $id = preg_replace('/[^0-9]/', '', $qs) ?? '';
}

if ($id === '') {
    http_response_code(400);
    exit('Missing thread id');
}

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
            $file_name = basename($file_name_raw);
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

$filepath = 'thread/' . $id . '.txt';
if (!is_file($filepath)) {
    http_response_code(404);
    exit('Thread not found');
}

$fh = fopen($filepath, 'a');
if ($fh === false) {
    http_response_code(500);
    exit('Could not open thread file');
}

fwrite($fh, $metainfo . "\n");
fwrite($fh, '#' . $body . "\n");
fclose($fh);

header('Location: thread.php?=' . $id);
exit;
