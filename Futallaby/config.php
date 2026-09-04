<?php
/**
 * Futallaby configuration - modernized for PHP 8.5+ with SQLite3
 * Original: Futallaby 040103 (2004)
 *
 * No MySQL required. Uses built-in SQLite3 extension (almost always enabled).
 */

define('TITLE', 'Futallaby-powered image board');		// Name of this image board
define('SQLLOG', 'posts');		// Table name used by image board (inside the SQLite file)
define('SQLITE_FILE', __DIR__ . '/futallaby.db');	// Path to the SQLite database file (will be created automatically)
define('ADMIN_PASS', 'CHANGEME');	// Janitor password  (CHANGE THIS!)
define('SHOWTITLETXT', '1');		// Show TITLE at top (1: yes  0: no)
define('SHOWTITLEIMG', '0');		// Show image at top (0: no, 1: single, 2: rotating)
define('TITLEIMG', 'title.jpg');	// Title image (point to php file if rotating)
define('IMG_DIR', 'src/');		// Image directory (needs to be writable)
define('THUMB_DIR', 'thumb/');		// Thumbnail directory (needs to be writable)
define('HOME', '../');			// Site home directory (up one level by default)
define('MAX_KB', '500');			// Maximum upload size in KB
define('MAX_W', '250');			// Images exceeding this width will be thumbnailed
define('MAX_H', '250');			// Images exceeding this height will be thumbnailed
define('PAGE_DEF', 5);			// Images/threads per page
define('LOG_MAX', 500);			// Maximum number of entries
//define('RE_COL', '789922');
define('PHP_SELF', 'imgboard.php');	// Name of main script file
define('PHP_SELF2', 'imgboard.htm');	// Name of main htm file
define('PHP_EXT', '.htm');		// Extension used for board pages after first
define('RENZOKU', 5);			// Seconds between posts (floodcheck)
define('RENZOKU2', 10);			// Seconds between image posts (floodcheck)
define('MAX_RES', 30);			// Maximum topic bumps
define('USE_THUMB', 1);			// Use thumbnails (1: yes  0: no)
define('PROXY_CHECK', 0);		// Enable proxy check (1: yes  0: no)
define('DISP_ID', 0);			// Display user IDs (1: yes  0: no)
define('BR_CHECK', 15);			// Max lines per post (0 = no limit)
define('TRIPKEY', '#');			// Character displayed before tripcodes
define('CSSFILE', 'futaba.css');	// Location of the css file
