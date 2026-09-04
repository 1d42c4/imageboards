# Chessboard Lite

Chessboard Lite is a small, modern imageboard-style discussion app for chess communities. It keeps the useful board → thread → reply flow while replacing the old Tinyboard-era stack with a clean PHP 8.5 and SQLite application.

There is no Composer, Twig, Node.js, MySQL, build step, or external JavaScript/CSS dependency.

## What is included

- SQLite with numbered, repeatable migrations
- Board-local post numbers and familiar `>>123` references
- Cross-board references such as `>>>/analysis/42`
- Safe plain-text posts plus `[pgn]...[/pgn]` blocks
- JPEG, PNG, and WebP uploads with decoding, re-encoding, pixel limits, and thumbnails
- Reply backlinks, sticky and locked threads, reports, bans, and soft deletion
- Moderator dashboard and board management
- CSRF protection, hardened sessions, prepared queries, output escaping, security headers, keyed client identities, and SQLite-backed rate limits
- Responsive, chess-oriented interface with no third-party assets

This is an independent clean-room application. It does not bundle Tinyboard, Twig, jQuery, or any of the archive's legacy vendor code.

## Requirements

- PHP 8.5 or newer
- Extensions: PDO_SQLite, mbstring, fileinfo, GD, session, and JSON
- A web server that can route non-file requests to `public/index.php`

On Debian-family systems, the exact package names depend on the PHP repository in use. Enable the SQLite, mbstring, and GD modules for your PHP 8.5 CLI and FPM/Apache installations.

## Install

Unpack the release, enter its directory, and run:

```sh
php bin/install.php
```

### Windows

Open Command Prompt or PowerShell and invoke the CLI executable explicitly:

```powershell
C:\path\to\php.exe C:\tinyib\ib1\bin\install.php
```

If `php.exe` is on your `PATH`, this shorter form works from `C:\tinyib\ib1`:

```powershell
php bin\install.php
```

Do not open `bin\install.php` in a browser, double-click the PHP file, or run it with `php-cgi.exe`. Those use a web/CGI PHP runtime rather than PHP CLI. You can confirm the executable with `php -r "echo PHP_SAPI;"`; it should print `cli`.

The installer creates:

- `var/chessboard.sqlite`
- `var/app.key`
- upload and log directories
- a default `/chess/` board
- an `admin` moderator with a one-time generated password

Save the printed password. It is only stored as a password hash.

To choose the initial credentials non-interactively:

```sh
CHESSBOARD_ADMIN_USER=keeper \
CHESSBOARD_ADMIN_PASSWORD='use-a-long-unique-password' \
php bin/install.php
```

The install command is safe to run again. It applies missing migrations without replacing an existing database, key, board, or moderator.

## Try it locally

PHP's development server is enough for a local test:

```sh
php -S 127.0.0.1:8080 -t public public/router.php
```

Open `http://127.0.0.1:8080/`. Moderator sign-in is at `/mod/login`.

Do not expose PHP's development server directly to the public internet.

## Production deployment

Set the web root to the app's `public/` directory, not the project directory. An Apache rewrite file is included in `public/.htaccess`; a starting Nginx server block is in `docs/nginx.conf.example`.

The PHP worker must be able to read the source and read/write:

- `var/chessboard.sqlite` and its containing directory
- `var/uploads/`
- `var/log/`
- `var/app.key`

Use HTTPS. When TLS terminates at a reverse proxy, forward the original scheme so PHP sees `HTTPS=on`; secure session cookies are enabled automatically for HTTPS requests.

For a subdirectory installation, set a base path:

```sh
CHESSBOARD_BASE_PATH=/community
```

Environment variables are preferred in production. For simple hosting, copy `config/local.php.example` to `config/local.php` and override settings there. That file is ignored by Git.

Useful settings include:

| Variable | Purpose |
|---|---|
| `CHESSBOARD_NAME` | Site name |
| `CHESSBOARD_TAGLINE` | Home-page tagline |
| `CHESSBOARD_TIMEZONE` | PHP time zone, such as `America/New_York` |
| `CHESSBOARD_BASE_PATH` | Optional URL prefix |
| `CHESSBOARD_DB_PATH` | SQLite database location |
| `CHESSBOARD_STORAGE_PATH` | Image storage location |
| `CHESSBOARD_KEY_PATH` | Key used to derive privacy-preserving client identities |
| `CHESSBOARD_LOG_PATH` | Application error log |
| `CHESSBOARD_DEBUG` | Detailed errors; leave false in production |

## Operations

Check the installation:

```sh
php bin/doctor.php
```

Apply database updates after replacing the application files:

```sh
php bin/migrate.php
```

Reset a moderator password:

```sh
php bin/set-password.php admin
```

Set `CHESSBOARD_ADMIN_PASSWORD` on that command to avoid printing a generated password.

### Backup

The database runs in WAL mode. Use SQLite's online backup command while the site is live:

```sh
sqlite3 var/chessboard.sqlite ".backup '/safe/place/chessboard.sqlite'"
```

Also back up `var/uploads/` and `var/app.key`. The key must be preserved or existing bans and rate-limit identities will no longer match.

For a small site, a short maintenance window is another safe option: stop PHP-FPM, copy the database and uploads, then restart it. Do not copy only the main SQLite file while active writes may be occurring.

## Tests

The test runner uses a temporary SQLite database and has no external dependencies:

```sh
php tests/run.php
```

It exercises migrations, posting and board-local numbering, references and backlinks, safe rendering, reports, bans, rate limits, soft deletion, image processing (when GD is available), and a complete board-page request.

## Design limits

Chessboard Lite is deliberately compact. It does not include plug-ins, themes, remote image fetching, webhooks, a public API, federation, or legacy Tinyboard compatibility. SQLite is a strong fit for a modest single-server community; if sustained write concurrency becomes unusually high, moving the repository layer to a client/server database would be the next architectural step.

## License

MIT. See `LICENSE.md` and `NOTICE.md`.
