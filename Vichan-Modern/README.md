# Vichan Modern

A new English-language PHP 8.5 application using the original Vichan styles and familiar board/moderator layout. No Twig, Composer, gettext, IP collection or banning subsystem.

## Desktop start

Double-click **Start Vichan.bat** inside this folder. It finds PHP beside the app in `php/`, under `C:\php`, or on PATH. The folder can be moved or renamed, including paths containing spaces.

- Board: http://127.0.0.1:8088/
- Moderator area: http://127.0.0.1:8088/mod.php
- First-run username/password: `var/first-login.txt`, outside the public directory.
- Change the administrator password after signing in. The initial credential file is removed automatically when that account changes its password.
- Keep the server window open. Press Ctrl+C to stop it.

The local server listens only on your computer. It is a development/desktop launcher; use nginx and PHP-FPM for an Internet-facing site. If port 8088 is already occupied, close the other instance first.

## What is included

- Boards, static HTML indexes and thread pages, pagination and a catalog.
- Long text/article posts, replies, quoting, bold/italic/code/spoiler formatting and clickable links.
- Up to four JPEG, PNG, animated GIF, WebP or MP4 attachments per post.
- Image thumbnails, click-to-expand originals, native video playback and byte-range seeking.
- Quick reply, optional thread auto-refresh, search and 35 selectable original Vichan styles.
- Posting and report/deletion forms also work without JavaScript.
- Local image CAPTCHA, temporary session-based posting limits and shared traffic budgets.
- Optional deletion passwords for posters; reports for moderator review.
- Moderator dashboard, board management, recent-post search, post editing, file/post deletion, sticky/locked threads, reports, staff management, moderator activity log, theme selection and static rebuilding.
- Administrator, moderator and janitor roles, enforced on the server.
- Threads remain until explicitly deleted; there is no automatic pruning of old discussions.

The requested board features are implemented. This is not a drop-in replacement for every old Vichan plugin, theme generator, API, staff messaging feature or database. Existing Vichan databases are not imported automatically. The original Desktop `vichan` folder was not modified.

## Requirements

PHP **8.5.10 or a newer 8.5 patch**, 64-bit, with PDO SQLite, mbstring, GD, Fileinfo and Argon2id support. The installed `C:\php` runtime has these. No older PHP compatibility layer is included. Moving to a future PHP minor release requires a deliberate compatibility review and version-gate update.

There are no PHP package dependencies or package-manager installation steps. PHPStan and formatting tools were used during development; they are not shipped or required to run the app. The integration test additionally uses PHP cURL and process creation.

## Configuration and storage

`config.php` contains hosting paths, CAPTCHA and resource limits. The moderator Configuration page changes the site title and default style. Defaults are 32 MB per file, four attachments, 1 GB total media, 16 million pixels per image, 1,000 replies per thread, ten threads per page, a ten-second session posting window and 30 post attempts per minute site-wide. Limits deliberately use no network addresses.

The application stores its SQLite database, media, sessions, generated-page manifest and redacted diagnostic log under `var/`. **Only `public/` may be the web document root.** Never expose the whole application directory. Keep backups of the complete application, especially `var/` and `config.php`; stop the server before making a filesystem copy so SQLite and its journal files are consistent.

An image CAPTCHA is a modest spam hurdle, not a guarantee against automation. Clearing cookies resets session-based limits; shared request budgets still apply. The host/CDN can apply its own protection independently.

## nginx / shared hosting

1. Upload the application and set the website document root to its `public/` directory. A subdomain with its own document root is the simplest arrangement on shared hosting.
2. Enable the required PHP 8.5 extensions. Grant the PHP user write access to `var/` and generated locations under `public/`; do not grant write access to `src/`, `views/`, `resources/` or `config.php` in production. Pre-create `var/media` and `var/sessions` if necessary. Keep private directory permissions restrictive.
3. Run `php bin/install.php` using the same account that runs PHP-FPM. The installer is CLI-only and does not overwrite an existing installation.
4. Adapt `deploy/nginx.conf` for the site's name, filesystem location and PHP-FPM socket. The included server block is a domain-root example. A URL subdirectory additionally needs matching nginx locations and `base_path` in `config.php`.
5. Apply the relevant settings from `deploy/php.ini`. Configure HTTPS and set `secure_cookies` to `true`, including when TLS terminates at Cloudflare. Configure the proxy/FPM scheme consistently; the application does not inspect forwarded headers.
6. Keep `/mod.php`, `/session.php`, `/captcha.php`, `/compose.php`, `/action.php` and other dynamic PHP responses out of CDN caches. Do not use a blanket “cache everything” rule. Public static HTML may be served by nginx; a CDN must revalidate/purge it when content changes if you choose to cache it.
7. Sign in and change the initial password. Review `SECURITY-REVIEW.md` for the tested scope and remaining limits.

No `.htaccess` is required. On Apache/shared hosting, set the document root correctly and translate the security/cache headers and executable-file restrictions into the host's server configuration. The included production configuration was tested with nginx/FastCGI on Windows; Linux PHP-FPM deployment still needs a check on the actual host.

## Media behaviour

JPEG, PNG and WebP are decoded and re-encoded, dropping original metadata. GIFs preserve animation; their containers, frame count and pixel budgets are checked. MP4s receive MIME, brand, bounded container, track, sample-description and dimension checks. Originals live outside the document root and are served under explicit media types with `nosniff`.

MP4 video is not transcoded. Use H.264/AAC MP4 for broad browser support. Other accepted video codecs depend on the visitor's browser. Video thumbnails use a generic play icon; FFmpeg is not required. GIFs and MP4s retain embedded metadata. Container validation does not certify every compressed media bitstream as harmless or fully decodable.

Remote URL uploads, raw HTML, embedded third-party players, external CAPTCHA and server-side fetching of posted URLs are absent. Links in posts do not trigger server-side requests.

## Maintenance and tests

```text
php bin/console.php check
php bin/console.php rebuild
php bin/console.php reset-password admin
php tests/run.php
```

Password reset requires local CLI access, invalidates existing sessions for that staff account and writes a fresh private first-login file. The test command creates its own temporary installation and never operates on this installation's posts. It prints the temporary location for debugging. Tests do not provide any production CAPTCHA bypass.

See `LICENSE.md`, `LICENSE.Tinyboard.md` and `resources/FONT-LICENSE.txt` for retained licenses. The original Vichan/Tinyboard footer notices are preserved. Two remote font imports and references to three missing upstream background images were removed from the theme assets; the other styles and bundled images are retained.
