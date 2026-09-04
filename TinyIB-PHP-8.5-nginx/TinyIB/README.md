# TinyIB — PHP 8.5 / nginx edition

This is a deliberately small TinyIB deployment for one operator on one nginx
VPS. It targets PHP 8.5, SQLite3, and English only.

## Deliberate removals

The following original TinyIB features are not part of this edition:

- Apache and `.htaccess` support
- IP bans and ban-message controls
- hCaptcha and reCAPTCHA
- backlinks
- catalog pages
- generated JSON index, catalog, and thread files
- dates and times in rendered posts
- database-driver and old-settings compatibility layers
- translations and the web-based Git updater

The self-hosted simple CAPTCHA remains available. Post timestamps remain in the
database because thread ordering, bumping, flood control, moderation logs, and
account activity need them; they are not rendered with posts.

## Requirements

- nginx
- PHP 8.5.x with PHP-FPM
- PHP extensions: `sqlite3`, `fileinfo`, and `gd`
- `mbstring` is recommended for correct multibyte text handling
- cURL is recommended when URL embeds or URL uploads are enabled

## Install

1. Extract the project into the nginx document root.
2. Edit `settings.php`.
   - Set `TINYIB_TRIPSEED` to a long random value before the first request.
     Never change it after the board contains data.
   - Set unique bootstrap values for `TINYIB_ADMINPASS` and
     `TINYIB_MODPASS`. Blank them after the accounts are created.
   - Use only `simple` or an empty string for each CAPTCHA setting.
3. Adapt `nginx.conf.example` to the board's URI prefix and PHP 8.5-FPM
   socket, then include the locations in the board's nginx server block.
4. Give the PHP-FPM user write access to the board root and the `src/`,
   `thumb/`, and `res/` directories.
5. Open `imgboard.php`. TinyIB creates the SQLite schema and static board
   pages, then redirects to `index.html`.

The nginx rules must prevent direct access to `settings.php`, the SQLite
database and its WAL/SHM files, `tinyib.lock`, and PHP files under `inc/` other
than `inc/captcha.php`. The supplied example does this.

## Upgrade this edition

1. Back up `settings.php`, the SQLite database, `src/`, `thumb/`, and `res/`.
2. Replace application files while preserving those paths.
3. Delete stale files made by older releases if present:
   `catalog.html`, `catalog.json`, `threads.json`, and `res/*.json`.
4. Open the management panel and run **Rebuild All**.

Existing unused ban tables in an upgraded SQLite database are left untouched.
This edition neither reads nor writes them.

## Operation

- The management panel supports accounts, moderation, keyword actions,
  reports, staff posts, thread sticky/lock controls, logs, and rebuilds.
- Reports store the submitted reason and post number, not the reporter's IP
  address. Upgrades preserve old report counts while discarding old reporter
  identifiers.
- Keyword actions are limited to delete, hide-until-approved, and report when
  reporting is enabled.
- User uploads are configured in `$tinyib_uploads`; the supplied configuration
  accepts JPEG, PNG, and GIF images.
- Keep PHP error display disabled in production and send errors to the PHP-FPM
  log.
