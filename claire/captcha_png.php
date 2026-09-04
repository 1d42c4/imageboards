<?php
declare(strict_types=1);

/**
 * Modern CAPTCHA generator for Claire Imageboard
 * Requires PHP 8.4+ and GD extension.
 * Generates a slightly harder-to-OCR image with noise and better contrast.
 */

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$key = $_GET['key'] ?? '';
if ($key === '' || !preg_match('/^[a-f0-9]{32}$/', $key)) {
    $phrase = 'err';
} else {
    // Keep original 4-char derivation for compatibility with existing form logic
    $phrase = substr(md5($key), 0, 4);
}

$width = 120;
$height = 40;

$im = imagecreatetruecolor($width, $height);
if ($im === false) {
    http_response_code(500);
    exit;
}

imagesavealpha($im, true);
$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefill($im, 0, 0, $transparent);

// Background noise (subtle)
for ($i = 0; $i < 80; $i++) {
    $noiseColor = imagecolorallocatealpha($im, random_int(40, 90), random_int(40, 90), random_int(40, 90), random_int(60, 100));
    imagesetpixel($im, random_int(0, $width - 1), random_int(0, $height - 1), $noiseColor);
}

// Draw a few random lines for distortion
for ($i = 0; $i < 4; $i++) {
    $lineColor = imagecolorallocatealpha($im, random_int(60, 140), random_int(60, 140), random_int(60, 140), random_int(40, 80));
    imageline(
        $im,
        random_int(0, $width),
        random_int(0, $height),
        random_int(0, $width),
        random_int(0, $height),
        $lineColor
    );
}

// Text color - bright for dark themes
$textColor = imagecolorallocate($im, 240, 240, 240);

// Use built-in font 5 (larger) and center-ish
$font = 5;
$charWidth = imagefontwidth($font);
$charHeight = imagefontheight($font);
$totalWidth = $charWidth * strlen($phrase);
$x = (int) (($width - $totalWidth) / 2);
$y = (int) (($height - $charHeight) / 2) - 2;

// Slight per-character jitter
$chars = str_split($phrase);
foreach ($chars as $i => $char) {
    $offsetX = $x + ($i * $charWidth) + random_int(-1, 1);
    $offsetY = $y + random_int(-2, 2);
    imagestring($im, $font, $offsetX, $offsetY, $char, $textColor);
}

imagepng($im, null, 6);
imagedestroy($im);
