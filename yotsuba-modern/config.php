<?php
/**
 * Modern Yotsuba-style Imageboard - Configuration
 * PHP 8.2+ / SQLite3
 */

declare(strict_types=1);

// Board identity
define('BOARD_TITLE', 'Yotsuba Image Board');
define('BOARD_SUBTITLE', 'Anonymous Imageboard');
define('BOARD_DIR', 'b');                 // URI segment, e.g. /b/

// Paths (relative to this file)
define('ROOT', __DIR__ . '/');
define('IMG_DIR', ROOT . 'src/');
define('THUMB_DIR', ROOT . 'thumb/');
define('DB_FILE', ROOT . 'board.sqlite');

// Upload limits
define('MAX_KB', 4096);                   // Max upload size in KB
define('MAX_W', 250);                     // OP thumbnail max width
define('MAX_H', 250);                     // OP thumbnail max height
define('MAXR_W', 125);                    // Reply thumbnail max width
define('MAXR_H', 125);                    // Reply thumbnail max height
define('MAX_IMGRES', 300);                // Max image replies per thread
define('PAGE_DEF', 10);                   // Threads per page
define('LOG_MAX', 500);                   // Soft max threads before pruning
define('REPLIES_SHOWN', 5);               // Replies shown on index
define('MAX_LINES', 100);                 // Max comment lines

// Flood control (seconds)
define('RENZOKU', 15);                    // Between any posts
define('RENZOKU2', 30);                   // Between image posts
define('RENZOKU3', 60);                   // Between new threads

// Security
define('ADMIN_PASS', 'changeme');         // Change this!
define('SALT', 'change-this-to-a-long-random-string-please');

// Features
define('USE_THUMB', true);
define('FORCED_ANON', false);
define('SHOW_SECONDS', true);
define('ENABLE_SPOILERS', true);

// Timezone
date_default_timezone_set('UTC');

// Error reporting (turn off in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');
