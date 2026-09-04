# Chessboard Lite

Chessboard Lite is a small imageboard-style discussion app for chess communities. It keeps the familiar board → thread → reply flow while using a content-first interface and a modular PHP 8.4 codebase.

There is no Composer, Twig, Node.js, frontend framework, build step, remote JavaScript, or remote CSS dependency.

## What is included

- First-run browser setup; no Composer or database command is required
- SQLite with numbered, repeatable migrations
- Board-local thread and reply numbering handled internally
- Safe plain-text posts plus `[pgn]...[/pgn]` blocks
- JPEG, PNG, and WebP uploads with decoding, re-encoding, pixel limits, and thumbnails
- Sticky and locked threads, CAPTCHA-protected reports, moderator editing, image replacement, and soft deletion
- Moderator dashboard and board management
- CSRF protection, hardened sessions, prepared queries, output escaping, security headers, and no IP-address collection
- Collapsed **New** forms, top-of-thread replies, two-reply board previews, and content-focused post sizing
- 36 standalone themes grouped as light, mid-tone, dark, traditional, and experimental
- Persistent visitor controls for five text sizes and normal or thicker text

## Requirements

- PHP 8.4 or newer
- SQLite 3.35 or newer
- Extensions: PDO_SQLite, mbstring, fileinfo, GD, session, and JSON
- A web server that can route non-file requests to `public/index.php`

PHP 8.4 is the compatibility baseline so the same release can be developed on Windows and deployed to an ordinary Linux VPS. Newer supported PHP releases should work, but update the tests before raising the minimum version.

## Quick start on any platform

Unpack the release, enter its directory, and start PHP's local server:

```sh
php -S 127.0.0.1:8080 -t public public/router.php
```

Open `http://127.0.0.1:8080/`. A fresh copy opens the setup page. Choose an administrator username and password, then press **Create site**.

Browser setup creates the SQLite database, upload and log directories, a default `/chess/` board, and the administrator account. Setup then locks itself. Moderator sign-in is at `/mod/login`.

Do not expose PHP's development server directly to the public internet.

### Optional Windows launcher

`start-windows.bat` runs the same PHP development-server command and opens the browser. It is only a convenience; no application code depends on Windows or the batch file.

## Production deployment

Set the web root to `public/`, not the project directory. Apache rewrite rules are included in `public/.htaccess`; an Nginx/PHP-FPM starting point is in `docs/nginx.conf.example`.

For an internet-facing deployment:

- Complete setup before opening the site publicly.
- Use HTTPS.
- Give the PHP worker read access to source and read/write access only to `var/`.
- Keep `CHESSBOARD_DEBUG` off.
- Back up both the database and `var/uploads/`.

For a subdirectory installation, set `CHESSBOARD_BASE_PATH`, for example `/community`. Environment variables are preferred in production. Small installations may copy `config/local.php.example` to `config/local.php`; it is ignored by Git.

## Modular structure

The app is intentionally divided by responsibility:

- `src/Module/` registers public and moderator routes independently.
- `src/Controller/` handles HTTP behavior.
- `src/Repository/` owns SQL and data access.
- `src/Security/` contains sessions, CSRF, moderator authentication, and CAPTCHA.
- `src/Service/` contains post markup and upload processing.
- `templates/mod/` isolates moderator templates.
- `public/assets/css/` contains small feature CSS modules.
- `public/assets/css/themes/` contains one real CSS file per theme.
- `public/assets/js/` contains small browser modules with no build process.

Future AI tools and maintainers should read `AI-README.md`. Theme authors should read `docs/THEMES.md`.

## Theme system

The existing default design is **Checkmate**. Theme categories are practical rather than based on opening families:

- Light themes
- Mid-tone themes
- Dark themes
- Traditional themes
- Experimental themes

Traditional choices include adapted Yotsuba, Yotsuba B, Miku, Tomorrow, Pink, and Green Dark palettes from the supplied stylesheet reference archive. They use Chessboard Lite's own selectors and modular theme variables.

Add a palette by creating one file in `public/assets/css/themes/` and one entry in `config/themes.php`. Theme and reading preferences stay only in browser `localStorage`.

## Operations

Check the installation:

```sh
php bin/doctor.php
php bin/check-themes.php
```

Apply database updates after replacing application files:

```sh
php bin/migrate.php
```

Reset a moderator password:

```sh
php bin/set-password.php admin
```

### Backup

The database runs in WAL mode. Use SQLite's online backup command while the site is live:

```sh
sqlite3 var/chessboard.sqlite ".backup '/safe/place/chessboard.sqlite'"
```

Also back up `var/uploads/`. Do not copy only the main SQLite file during active writes.

## Tests

Run:

```sh
php tests/run.php
```

The suite covers migrations, posting, board-local numbering, safe rendering, report CAPTCHA behavior, reports, moderator editing, attachment replacement and removal, soft deletion, two-reply previews, privacy-safe schema checks, image processing when GD is available, and full board/thread requests.

## Privacy model

The public board never reads or stores visitor IP addresses or IP-derived identifiers. It has no IP bans or IP-backed rate limits. Reports contain no reporter identity and use a short-lived, one-time arithmetic CAPTCHA stored only in the visitor session. No external CAPTCHA provider receives board activity.

## Design limits

Chessboard Lite deliberately avoids plugins, remote image fetching, webhooks, a public API, federation, and legacy Tinyboard compatibility. SQLite is a strong fit for a modest single-server community. If sustained concurrent writes become a real limitation, the repository layer is the intended boundary for a later PostgreSQL implementation.

## License

MIT. See `LICENSE.md` and `NOTICE.md`.
