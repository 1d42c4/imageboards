<?php
declare(strict_types=1);

/**
 * ChessIB - Chess Imageboard
 * Lightweight imageboard for chess discussion, inspired by TinyIB / Vichan
 */

// Site
define('SITE_NAME', 'ChessIB');
define('SITE_TITLE', '/chess/ - Chess Discussion');
define('SITE_DESCRIPTION', 'A lightweight imageboard for chess players. Discuss openings, tactics, games, and more.');
define('BOARD_SLUG', 'chess');

// Paths (relative to this file)
define('ROOT_DIR', __DIR__);
define('UPLOAD_DIR', ROOT_DIR . '/uploads');
define('THUMB_DIR', ROOT_DIR . '/thumbs');
define('DB_PATH', ROOT_DIR . '/data/chessib.sqlite');

// Database
define('DB_DSN', 'sqlite:' . DB_PATH);

// Posting limits
define('MAX_NAME_LENGTH', 50);
define('MAX_SUBJECT_LENGTH', 100);
define('MAX_COMMENT_LENGTH', 8000);
define('MAX_FILE_SIZE', 4 * 1024 * 1024); // 4 MB
define('ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('THUMB_MAX_W', 250);
define('THUMB_MAX_H', 250);
define('THREADS_PER_PAGE', 15);
define('REPLIES_PREVIEW', 3); // replies shown under OP on index
define('MAX_REPLIES_BUMP', 300); // after this, no more bump (sage-like)

// Timezone
date_default_timezone_set('UTC');

// Admin password (change this!)
// Used for sticky/lock/delete any post. Leave empty to disable admin panel features beyond password delete.
define('ADMIN_PASSWORD', 'chessadmin'); // CHANGE ME

// Security
define('CSRF_TOKEN_NAME', 'chessib_csrf');

// Display
define('DATE_FORMAT', 'Y-m-d H:i:s');
define('DEFAULT_NAME', 'Anonymous');

// Enable image uploads (set false for pure textboard)
define('ALLOW_IMAGES', true);

// Require image for new threads?
define('REQUIRE_IMAGE_FOR_THREAD', false);
?>
