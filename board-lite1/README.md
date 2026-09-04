# Chessboard Lite

Chessboard Lite is a small, modern imageboard-style discussion app for chess communities. It keeps the useful board → thread → reply flow while replacing the old Tinyboard-era stack with a clean PHP 8.5 and SQLite application.

There is no Composer, Twig, Node.js, MySQL, build step, or external JavaScript/CSS dependency.

## What is included

- First-run browser setup; no Composer or database command is required
- SQLite with numbered, repeatable migrations
- Board-local post numbers and familiar `>>123` references
- Cross-board references such as `>>>/analysis/42`
- Safe plain-text posts plus `[pgn]...[/pgn]` blocks
- JPEG, PNG, and WebP uploads with decoding, re-encoding, pixel limits, and thumbnails
- Reply backlinks, sticky and locked threads, reports, bans, and soft deletion
- Moderator dashboard and board management
- CSRF protection, hardened sessions, prepared queries, output escaping, security headers, keyed client identities, and SQLite-backed rate limits
- Compact, content-first imageboard interface with no third-party assets

This is an independent clean-room application. It does not bundle Tinyboard, Twig, jQuery, or any of the archive's legacy vendor code.

## Requirements

- PHP 8.5 or newer
- Extensions: PDO_SQLite, mbstring, fileinfo, GD, session, and JSON
- A web server that can route non-file requests to `public/index.php`

On Debian-family systems, the exact package names depend on the PHP repository in use. Enable the SQLite, mbstring, and GD modules for your PHP 8.5 CLI and FPM/Apache installations.

## Quick start

Unpack the release, enter its directory, and start PHP's local server:

```sh
php -S 127.0.0.1:8080 -t public public/router.php
```

Open `http://127.0.0.1:8080/`. A fresh copy automatically opens the browser setup page. Choose an administrator username and password, then press **Create site**.

Browser setup creates:

- `var/chessboard.sqlite`
- `var/app.key`
- upload and log directories
- a default `/chess/` board
- the administrator account you chose

Afterward, setup locks itself and normal visitors go straight to the site. Moderator sign-in is at `/mod/login`.

Do not expose PHP's development server directly to the public internet.

### Windows

From Command Prompt or PowerShell in `C:\tinyib\ib1`:

```powershell
php -S 127.0.0.1:8000 -t public public\router.php
```

Then open `http://127.0.0.1:8000/`. There is no separate install command. If `php` is not on your `PATH`, use the full path to `php.exe`; use `php.exe`, not `php-cgi.exe`.

## Optional command-line installation

Server administrators may prefer non-interactive setup:

```sh
CHESSBOARD_ADMIN_USER=keeper \
CHESSBOARD_ADMIN_PASSWORD='use-a-long-unique-password' \
php bin/install.php
```

Without those environment variables, the CLI installer creates an `admin` account and prints a generated password once. The command remains idempotent and does not replace an existing database, key, board, or moderator.

## Production deployment

Set the web root to the app's `public/` directory, not the project directory. An Apache rewrite file is included in `public/.htaccess`; a starting Nginx server block is in `docs/nginx.conf.example`.

For an internet-facing deployment, complete setup before opening the site to the public. The CLI route above is the safest way to do that; like any browser installer, an unconfigured public instance could otherwise be claimed by its first visitor.

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

Chessboard Lite is deliberately compact in both code and presentation. Posting forms stay collapsed until requested, replies use a dense imageboard layout, and decorative interface elements are kept out of the way of discussions. It does not include plug-ins, themes, remote image fetching, webhooks, a public API, federation, or legacy Tinyboard compatibility. SQLite is a strong fit for a modest single-server community; if sustained write concurrency becomes unusually high, moving the repository layer to a client/server database would be the next architectural step.

## License

MIT. See `LICENSE.md` and `NOTICE.md`.
