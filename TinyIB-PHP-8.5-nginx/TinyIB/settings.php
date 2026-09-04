<?php
declare(strict_types=1);

define('TINYIB_BOARD', 'b');          // Unique identifier for this board using only letters and numbers
define('TINYIB_BOARDDESC', 'TinyIB'); // Displayed below logo in page headers
define('TINYIB_BOARDTITLE', '');      // Title of board pages.  When blank, defaults to TINYIB_BOARDDESC (when set) or "TinyIB"
define('TINYIB_ALWAYSNOKO', false);   // Redirect to thread after posting
define('TINYIB_CAPTCHA', 'simple');   // Self-hosted CAPTCHA for new threads: simple  ['' to disable]
define('TINYIB_REPLYCAPTCHA', 'simple'); // Self-hosted CAPTCHA for replies: simple  ['' to disable]
define('TINYIB_REPORTCAPTCHA', '');   // Self-hosted CAPTCHA for reports: simple  ['' to disable]
define('TINYIB_MANAGECAPTCHA', '');   // Self-hosted CAPTCHA for management login: simple  ['' to disable]
define('TINYIB_REPORT', false);       // Allow users to report posts
define('TINYIB_AUTOHIDE', 0);         // Amount of reports which will cause a post to be hidden until it is approved  [0 to disable]
define('TINYIB_REQMOD', '');          // Require moderation before displaying posts: files / all  ['' to disable]
define('TINYIB_SPOILERTEXT', true);  // Allow users to hide text until it is hovered over using the tags <s>text here</s> or <spoiler>text here</spoiler>
define('TINYIB_SPOILERIMAGE', true); // Allow users to blur thumbnails via a "Spoiler" checkbox
define('TINYIB_AUTOREFRESH', 0);     // Delay (in seconds) between attempts to refresh a thread automatically  [0 to disable]
define('TINYIB_CLOUDFLARE', false);   // Only enable when the site is served via Cloudflare to identify IP addresses correctly
define('TINYIB_DISALLOWTHREADS', ''); // When set, users attempting to post a new thread are shown this message instead  ['' to disable]
define('TINYIB_DISALLOWREPLIES', ''); // When set, users attempting to post a reply are shown this message instead  ['' to disable]

// Board appearance
define('TINYIB_INDEX', 'index.html'); // Index file
define('TINYIB_LOGO', '');            // Logo HTML
define('TINYIB_THREADSPERPAGE', 10);  // Amount of threads shown per index page
define('TINYIB_PREVIEWREPLIES', 3);   // Amount of replies previewed on index pages
define('TINYIB_TRUNCATE', 15);        // Messages are truncated to this many lines on board index pages  [0 to disable]
define('TINYIB_WORDBREAK', 80);       // Words longer than this many characters will be broken apart  [0 to disable]
define('TINYIB_EXPANDWIDTH', 85);     // Expanded content size as a percentage of the screen's width
define('TINYIB_DEFAULTSTYLE', 'burichan'); // Default page style
$tinyib_hidefieldsop = array();       // Fields to hide when creating a new thread - e.g. array('name', 'email', 'subject', 'message', 'file', 'embed', 'password')
$tinyib_hidefields = array();         // Fields to hide when replying
$tinyib_anonymous = array('Anonymous'); // Default name (or names)
$tinyib_capcodes = array(array('Admin', 'red'), array('Mod', 'purple')); // Administrator and moderator capcode label and color
// Stylesheets (located in css)
//   Format: File name excluding extension => Title
$tinyib_stylesheets = array(
	'futaba' => 'Futaba',
	'burichan' => 'Burichan'
);

// Post control
define('TINYIB_DELAY', 30);           // Delay (in seconds) between posts from the same IP address to help control flooding  [0 to disable]
define('TINYIB_MAXTHREADS', 100);     // Oldest threads are discarded when the thread count passes this limit  [0 to disable]
define('TINYIB_MAXREPLIES', 0);       // Maximum replies before a thread stops bumping  [0 to disable]
define('TINYIB_MAXNAME', 75);         // Maximum name length  [0 to disable]
define('TINYIB_MAXEMAIL', 320);       // Maximum email length  [0 to disable]
define('TINYIB_MAXSUBJECT', 75);      // Maximum subject length  [0 to disable]
define('TINYIB_MAXMESSAGE', 8000);    // Maximum message length  [0 to disable]

// Upload types
//   Empty array to disable
//   Format: MIME type => (extension, optional thumbnail)
$tinyib_uploads = array('image/jpeg'                    => array('jpg'),
                        'image/pjpeg'                   => array('jpg'),
                        'image/png'                     => array('png'),
                        'image/gif'                     => array('gif'));
//                      'application/x-shockwave-flash' => array('swf', 'swf_thumbnail.png');
//                      'audio/aac'                     => array('aac');
//                      'audio/flac'                    => array('flac');
//                      'audio/ogg'                     => array('ogg');
//                      'audio/opus'                    => array('opus');
//                      'audio/mp3'                     => array('mp3');
//                      'audio/mpeg'                    => array('mp3');
//                      'audio/mp4'                     => array('mp4');
//                      'audio/wav'                     => array('wav');
//                      'audio/webm'                    => array('webm');
//                      'video/mp4'                     => array('mp4'); // Video uploads require ffmpeg  (see README for instructions)
//                      'video/webm'                    => array('webm');

// oEmbed APIs
//   Empty array to disable
$tinyib_embeds = array('SoundCloud' => 'https://soundcloud.com/oembed?format=json&url=TINYIBEMBED',
                       'Vimeo'      => 'https://vimeo.com/api/oembed.json?url=TINYIBEMBED',
                       'YouTube'    => 'https://www.youtube.com/oembed?url=TINYIBEMBED&format=json');

// File control
define('TINYIB_MAXKB', 2048);         // Maximum file size in kilobytes  [0 to disable]
define('TINYIB_MAXKBDESC', '2 MB');   // Human-readable representation of the maximum file size
define('TINYIB_THUMBNAIL', 'gd');     // Thumbnail method to use: gd / ffmpeg / imagemagick  (see README for instructions)
define('TINYIB_UPLOADVIAURL', false); // Allow files to be uploaded via URL
define('TINYIB_STRIPMETADATA', false);// Attempt to strip all metadata from uploaded files  (requires ExifTool)
define('TINYIB_NOFILEOK', true);     // Allow the creation of new threads without uploading a file

// Thumbnail size - new thread
define('TINYIB_MAXWOP', 250);         // Width
define('TINYIB_MAXHOP', 250);         // Height

// Thumbnail size - reply
define('TINYIB_MAXW', 250);           // Width
define('TINYIB_MAXH', 250);           // Height

// Tripcode seed - Must not change once set!
define('TINYIB_TRIPSEED', 'wedfewfwefwef');        // Enter some random text  (used when generating secure tripcodes, hashing passwords and hashing IP addresses)

// Management panel
define('TINYIB_MANAGEKEY', '');       // When set, the [Manage] link is hidden and the management panel may only be accessed via imgboard.php?manage=TINYIB_MANAGEKEY  ['' to disable]
//   Administrator and moderator passwords
//     When TINYIB_ADMINPASS is set, an administrator account is created with username "admin"
//     When TINYIB_MODPASS is set, a moderator account is created with username "moderator"
//     These settings are for installation and anti-lockout purposes only
//     Once the account(s) are created, blank both of these settings
define('TINYIB_ADMINPASS', 'aaa');       // Administrator password
define('TINYIB_MODPASS', 'mmm');         // Moderator password  ['' to disable]

// SQLite3 database
//   Table names
//     Use the same table name across boards for shared accounts, keywords, and logs.
define('TINYIB_DBACCOUNTS', 'accounts'); // Staff accounts
define('TINYIB_DBKEYWORDS', 'keywords'); // Keywords
define('TINYIB_DBLOGS', 'logs');         // Staff logs
define('TINYIB_DBPOSTS', TINYIB_BOARD . '_posts');     // Posts
define('TINYIB_DBREPORTS', TINYIB_BOARD . '_reports'); // Reports
define('TINYIB_DBPATH', '.tinyib.db');            // SQLite database path
