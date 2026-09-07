<?php

declare(strict_types=1);

return [
    'title' => 'Chessboard',
    'base_path' => '', // Empty for a domain root; '/forum' for a subdirectory.
    'secure_cookies' => false, // Set true when serving through HTTPS, including Cloudflare.
    'theme' => 'style',
    'captcha' => true,
    'post_interval' => 10,
    'posts_per_minute' => 30, // Shared board-wide budget; no network identifiers.
    'max_upload_bytes' => 33554432,
    'max_total_upload_bytes' => 1073741824,
    'max_image_pixels' => 16000000,
    'max_files' => 4,
    'threads_per_page' => 10,
    'preview_replies' => 3,
    'max_replies' => 1000,
];
