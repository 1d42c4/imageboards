<?php

declare(strict_types=1);

/**
 * Default configuration. Override keys in config/local.php (optional).
 */
return [
    'name' => 'Newboard',
    'timezone' => 'UTC',
    'base_path' => '',

    'db' => [
        'path' => dirname(__DIR__) . '/data/board.sqlite',
    ],

    'paths' => [
        'root' => dirname(__DIR__),
        'data' => dirname(__DIR__) . '/data',
        'uploads' => dirname(__DIR__) . '/data/uploads',
        'templates' => dirname(__DIR__) . '/templates',
    ],

    'board' => [
        'threads_per_page' => 10,
        'max_pages' => 10,
        'preview_replies' => 5,
        'max_body' => 8000,
        'max_filename' => 200,
        'allow_images' => true,
        'max_image_bytes' => 5 * 1024 * 1024,
        'thumb_max' => 255,
        'allowed_mime' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ],
    ],

    // Threads past max_pages * threads_per_page are auto-archived (read-only).
    'archive' => [
        'enabled' => true,
        'threads_per_page' => 50,
        'auto' => true, // prune on new OP
    ],

    // Top nav: label => path. Empty boards from DB are merged at runtime if this is empty.
    'nav' => [
        'Home' => '/',
    ],

    'abuse' => [
        'session_cooldown' => 15,
        'honeypot_field' => 'website',
    ],

    'session' => [
        'name' => 'newboard_sess',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    'csrf' => [
        'key' => 'csrf_token',
    ],

    // Base + theme (vichanBEST1 skins under public/stylesheets/)
    'stylesheets' => [
        'Yotsuba B' => 'yotsuba_b.css',
        'Yotsuba' => 'yotsuba.css',
        'Futaba' => 'futaba.css',
        'Futaba Light' => 'futaba-light.css',
        'Burichan' => 'burichan.css',
        'Dark' => 'dark.css',
        'Tomorrow' => 'tomorrow.css',
        'Photon' => 'photon.css',
        'Miku' => 'miku.css',
        'Notsuba' => 'notsuba.css',
        'Pink' => 'pink.css',
        'Terminal' => 'terminal2.css',
        'Greendark' => 'greendark.css',
    ],
    'default_stylesheet' => 'Yotsuba B',
];
