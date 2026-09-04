<?php
// Chesschan - Simple PHP Imageboard
// Config

define('BOARD_NAME', 'chess');
define('BOARD_TITLE', 'Chesschan');
define('BOARD_DESC', 'Anonymous chess discussion board');
define('SITE_NAME', 'Chesschan');

// Database (SQLite - zero config)
define('DB_FILE', __DIR__ . '/chesschan.db');

// Upload settings
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 8 * 1024 * 1024); // 8 MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('THUMB_WIDTH', 250);

// Posting
define('MAX_SUBJECT', 100);
define('MAX_NAME', 50);
define('MAX_COMMENT', 4000);
define('THREADS_PER_PAGE', 15);
define('REPLIES_SHOWN', 5); // on index

// Security
define('DELETE_PASSWORD_SALT', 'chesschan_salt_change_me'); // change this

// Timezone
date_default_timezone_set('UTC');

// Create upload dir if needed
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
