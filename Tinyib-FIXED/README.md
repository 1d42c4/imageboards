# TinyIB PHP 8.5 — lightweight two-storage edition

This repository is a deliberately slimmed-down TinyIB board for small deployments. It keeps the familiar TinyIB appearance and board behavior while reducing the optional subsystems to the ones useful for this installation:

- PHP 8.5 is the supported runtime.
- Storage is either the new built-in flat-file engine or SQLite3.
- The self-hosted simple CAPTCHA remains available.
- The active interface is English-only.
- MySQL, MySQLi, PDO, legacy SQLite, database migration, gettext, hCaptcha, and reCAPTCHA have been removed.

The flat-file engine is new code written specifically for TinyIB. It is not the old generic flat-file database with new syntax, and it does not attempt to import the old `.posts`, `.accounts`, or related table files.

## Project status and scope

This edition is based on the official TinyIB `master` branch at commit `b7f8c3cb87885cd96bf29ba1fa2c5351aae604fc`, dated March 18, 2026. The comparison and recovery bundle were prepared on August 1, 2026.

This is a focused modernization, not yet a line-by-line rewrite of the entire historical board. The new flat-file storage layer uses strict types and PHP 8-era language features. Existing board rendering, upload, moderation, and request-handling code was retained where it still serves the small-board goal. That distinction matters: this version requires and has been tested with PHP 8.5, but it should not be described as an entirely new application.

## What changed from official TinyIB

| Area | Official version | This edition | Result |
| --- | --- | --- | --- |
| Database modes | `flatfile`, `mysql`, `mysqli`, `pdo`, `sqlite`, `sqlite3` | `flatfile`, `sqlite3` | Two appropriate choices for a small board |
| Flat-file posts | One generic `.posts` table and a SQL-like query layer | Canonical per-thread documents plus compact indexes | Thread work no longer requires treating every post as one monolithic table |
| Flat-file safety | Legacy table serialization and lock files | Exclusive locking, temporary writes, flush/sync, atomic replacement, recovery marker | Better resistance to partial writes and interrupted index updates |
| Flat-file schema | Numeric column offsets and many table/query helper classes | Named fields and a TinyIB-specific storage class | Easier to understand, validate, and maintain |
| Database migration | Cross-engine migration mode and management-panel workflow | Removed | Less configuration and no dead paths between retired engines |
| SQLite | Old `sqlite` and current `sqlite3` adapters | SQLite3 only | Removes the deprecated legacy extension |
| Localization | Bundled gettext library and 35 locale catalogs | English identity translator | Removes a large dependency tree that this board did not use |
| CAPTCHA | Simple, hCaptcha, and reCAPTCHA | Simple only | No hosted CAPTCHA account, keys, verification requests, or widget scripts |
| Public JSON output | Optional generated JSON files | Retained | Existing integrations can still consume board/thread JSON when enabled |
| Internal flat-file format | Custom legacy table files | Guarded JSON payloads in `.php` documents | Human-inspectable data without exposing it as a public JSON endpoint |

### Removed source inventory

The modernization removed 114 files from the official source tree:

- `inc/gettext.php` and all 57 files under `inc/gettext/`.
- All 35 translation catalogs under `locale/`.
- All 11 files under `inc/recaptcha/`.
- The MySQL adapter and connection file.
- The MySQLi adapter and connection file.
- The PDO adapter and connection file.
- The deprecated SQLite adapter and connection file.
- The old generic flat-file engine and its utility file.

The retired database adapters represented 1,848 lines of source. The complete old flat-file stack contained 1,594 lines across its adapter, link, generic engine, and utilities. The replacement contains 954 lines across its adapter, link, and purpose-built engine: 640 fewer lines while adding explicit recovery and atomic-write behavior.

The removed gettext, locale, and reCAPTCHA trees alone accounted for 103 files and roughly 790 KiB.

### Recovery copies

The removed official sources were reconstructed from the baseline commit and placed in the Windows Recycle Bin. They are not present in the active project.

- Main recovery folder: `TinyIB-removed-originals-2026-08-01-b7f8c3cb-0c04bc1f`
- Apache-rule supplement: `TinyIB-removed-originals-supplement-htaccess-2026-08-01-24bd6392`

The main folder contains:

- `deleted-source-paths/` with the original removed directories and adapters.
- `original-versions-of-edited-files/` with upstream copies of the core files from which integrations were removed.
- The official `settings.default.php` for configuration reference.
- A recovery manifest identifying the source commit.

The legacy runtime-generated `.accounts` and `.accounts.lock` files were not part of the official repository and had already been permanently deleted before the recovery bundle was requested. They could not be reproduced byte-for-byte. There were no previous posts, and configured staff accounts are initialized in the new store.

## Retained board features

Slimming the dependencies and storage choices did not turn TinyIB into a minimal posting demo. The normal board capabilities remain available.

### Posting and threads

- New threads and replies.
- Text-only threads when `TINYIB_NOFILEOK` is enabled.
- Password-based post deletion.
- Secure tripcodes and hashed passwords using the configured trip seed.
- `>>post` reference links and optional backlinks.
- Thread bumping, sage behavior, bump limits, thread limits, and reply limits.
- Sticky and locked threads.
- Optional spoiler text and spoiler thumbnails.
- Configurable anonymous names, capcodes, hidden post fields, length limits, word breaking, and index truncation.
- Automatic reply fetching through the existing thread refresh endpoint.

### Uploads and embeds

- JPEG, PNG, and GIF uploads are enabled in the supplied settings.
- Additional audio, video, WebM, MP4, and SWF types can be enabled in `$tinyib_uploads`.
- GD, FFmpeg, or ImageMagick thumbnail generation.
- Optional metadata stripping through ExifTool.
- Optional upload-by-URL support.
- YouTube, Vimeo, and SoundCloud oEmbed support.

Hosted embeds are separate from hosted CAPTCHA. Enabling an embed provider or upload-by-URL still causes outbound network requests to the configured service.

### Rendering and presentation

- Static board index and thread HTML generation.
- Futaba and Burichan stylesheets.
- Optional catalog page.
- Optional public JSON index, catalog, and thread documents.
- Configurable thread previews, threads per page, expansion width, logo HTML, and default style.

### Moderation

- Super-administrator, administrator, and moderator account roles.
- Account creation and management.
- Post approval and moderation queues.
- Post and image deletion.
- Keyword actions for reporting, hiding, or deleting matching posts.
- User reports and optional automatic hiding after a report threshold.
- Staff logs.
- Raw HTML staff posts.
- Optional hidden management URL through `TINYIB_MANAGEKEY`.

### Spam controls

- Self-hosted simple CAPTCHA for threads, replies, reports, and management login.
- Keyword rules and automatic actions.
- Optional moderation of all posts or posts containing files.

hCaptcha and reCAPTCHA are not valid settings in this edition. Use `simple` or an empty string for each CAPTCHA setting.

### Network privacy

TinyIB does not read, hash, store, display, or act on visitor IP addresses or proxy-forwarding headers. Posts and reports contain no network-address field, and posting is not subject to address-based delays or bans. Duplicate report suppression is scoped to the visitor's existing PHP session.

When either storage engine opens older data, it removes legacy ban records and stored address hashes. Legacy keyword actions that combined deletion with a ban are converted to plain deletion rules.

## Choosing a storage engine

| Consideration | Flat-file | SQLite3 |
| --- | --- | --- |
| PHP extension | No database extension | `sqlite3` extension required |
| Database server | None | None |
| Portability | Highest | High |
| Backup unit | Data directory | One database file, plus any journal/WAL state |
| Human inspection | Guarded, pretty-printed JSON payloads | SQLite tools |
| Concurrent writes | Serialized by one file lock | Managed by SQLite with a 60-second busy timeout |
| Best fit | Tiny boards and restrictive shared hosts | Small boards wanting stronger query/database semantics |
| Data compatibility | New format only | Existing SQLite3 schema retained |

Set `TINYIB_DBMODE` in `settings.php` to either `flatfile` or `sqlite3`. Any other value is rejected before a database adapter is loaded.

There is intentionally no live migration tool. Changing the mode does not copy data from one engine to the other. Choose a mode before opening the board to users, or perform a planned custom export/import later.

## New flat-file engine

The new engine is implemented by `inc/database/flatfile/FlatFileDatabase.php`. `inc/database/flatfile.php` adapts it to the same board-facing functions used by SQLite3, and `inc/database/flatfile_link.php` initializes it.

### On-disk layout

With the default `TINYIB_FLATFILE_PATH`, runtime data is stored under `inc/database/flatfile/data/`:

```text
data/
  .htaccess
  .write.lock
  index.php
  accounts.php
  keywords.php
  logs.php
  posts.php
  reports.php
  threads/
    .htaccess
    index.php
    <thread-id>.php
```

A `.recover` marker appears only while a post mutation is in progress or after an interrupted mutation. Temporary files use unpredictable names and are removed or renamed as part of the write process.

### Canonical and derived data

Each `threads/<thread-id>.php` document is the canonical record for one complete thread, including its opening post and replies. Operations that work within one thread read one thread document instead of loading a global posts table.

`posts.php` is a compact derived index containing:

- The next post ID.
- Opening-post summaries for thread ordering.
- A post-ID-to-thread-ID map.
- A file-hash-to-post-ID map for duplicate-file lookup.

Because the index is derived, it can be rebuilt from the canonical thread documents.

Accounts, keywords, logs, and reports are small independent collection documents. Each collection tracks its own next ID and named records.

### Read behavior

- Documents are cached for the lifetime of a request.
- File contents must start with the expected PHP denial guard.
- JSON parsing uses exception-based error handling.
- Every document must declare the supported format version.
- Unknown collection names are rejected.
- Post records are normalized to the expected integer and string fields.
- Thread and post ordering is numeric and deterministic.

Corrupt, unguarded, or unsupported documents cause an explicit storage error instead of being silently treated as valid data.

### Write behavior

All mutations acquire the same exclusive `.write.lock` with `flock`. That serializes writers and prevents two requests from allocating the same identifier or replacing related documents simultaneously.

Each document update follows this sequence:

1. Encode the complete new document with JSON exceptions enabled.
2. Create a uniquely named temporary file using exclusive creation.
3. Write until the full payload has been accepted.
4. Flush the stream and call `fsync` when available.
5. Close the temporary file.
6. Atomically rename it over the destination.
7. Apply restrictive file permissions where the platform supports them.

Readers therefore see either the previous complete document or the replacement complete document, rather than an in-place half-write.

### Recovery behavior

Post mutations can affect both a canonical thread document and the compact index. Before starting such a mutation, the engine writes `.recover`. It removes the marker only after the operation completes.

If PHP, the web server, or the host stops between those writes, the marker remains. The next initialization scans canonical thread documents and reconstructs:

- Thread summaries.
- Post-to-thread mappings.
- File-hash mappings.
- The next post ID.

A missing `posts.php` also triggers the same reconstruction. Recovery does not invent missing canonical threads and cannot repair arbitrary corruption inside a thread or collection document; those cases require a backup.

### Deletion behavior

Deleting a reply updates only its thread and the affected index entries. Deleting an opening post removes the complete canonical thread and every index reference for that thread. File-hash entries are corrected as part of deletion.

### Why it is more efficient than the old flat-file mode

The old implementation emulated a small relational database in PHP. It represented every table as a hidden file, every row as numeric columns, and implemented generic where clauses, list clauses, composite clauses, ordering, table utilities, and migration helpers. Board operations frequently worked through that general layer over the shared `.posts` table.

The new implementation is specialized for TinyIB:

- A thread read is isolated to one canonical thread document.
- Thread listings use compact opening-post summaries.
- Direct post lookups use a post-to-thread map.
- Duplicate-file checks use a file-hash index.
- Unrelated account, keyword, log, and report data is not loaded for post reads.
- Deleted replies no longer force a full post-index rebuild.
- Generic SQL-like parser and query-object classes are gone.

This is a substantial improvement for a small board. It is not intended to beat SQLite under high write concurrency or very large datasets. All flat-file writes are deliberately serialized, and some global operations still traverse index entries or thread documents. For a busy or rapidly growing board, SQLite3 is the better choice.

### Flat-file security

Every data document begins with executable PHP that returns HTTP 404 and exits before the JSON payload. The engine also creates:

- Apache access-denial rules in the data and thread directories.
- `index.php` denial files to prevent directory entry points.
- Best-effort `0660` file permissions and `0770` directories.

Direct HTTP requests to current data documents were tested and returned 404 with no payload.

These are defense-in-depth measures, not a substitute for server configuration. The safest deployment places `TINYIB_FLATFILE_PATH` outside the public document root. This is especially important on servers that do not honor `.htaccess`, or if PHP source files might ever be served as plain text.

The entire data directory is ignored by Git through `.gitignore`.

## SQLite3 engine

SQLite3 remains the conventional local-database option. It requires PHP’s `sqlite3` extension and uses the path in `TINYIB_DBPATH`, which defaults to `.tinyib.db`.

On initialization it opens the database, applies a 60-second busy timeout, and creates five tables when needed:

- Accounts.
- Keywords.
- Logs.
- Board posts.
- Reports.

The compatibility checks for the `moderated`, `stickied`, and `locked` post columns remain. Initialization also rebuilds legacy post and report tables without their address columns and drops the legacy ban table.

The root Apache configuration denies direct requests for `.tinyib.db`. On other web servers, add an equivalent rule or store the database outside the document root.

## Shared storage contract

The flat-file and SQLite3 adapters expose the same board operations. The shared surface covers:

- Account lookup, listing, insertion, update, and deletion.
- Keyword lookup, listing, insertion, and deletion.
- Log insertion and pagination.
- Post insertion, lookup, moderation, editing, bumping, sticky/lock state, listing, trimming, and deletion.
- Thread existence, ordering, replies, images, and recent-post lookup.
- Duplicate file-hash lookup.
- Report listing, insertion, and deletion.

The rest of the board selects an adapter by `TINYIB_DBMODE`; it does not need storage-specific branches for those operations.

## Public JSON output versus internal flat-file JSON

`TINYIB_JSON` controls public generated output. When enabled, TinyIB writes:

- `threads.json` for the board index.
- `catalog.json` for catalog consumers.
- `res/<thread-id>.json` for individual threads.

These files are intended for clients, scripts, or alternate front ends and are rebuilt with the generated HTML.

The JSON payloads inside flat-file `.php` documents are unrelated. They are private storage records with a PHP denial guard and must never be treated as public API endpoints. `TINYIB_JSON` can be disabled while using flat-file storage, or enabled while using SQLite3.

## Requirements

### Required

- PHP 8.5.
- A web server capable of running PHP, or PHP’s built-in server for local testing.
- PHP sessions.
- Writable output/upload directories.

### Conditional

- `sqlite3` PHP extension when `TINYIB_DBMODE` is `sqlite3`.
- GD for the default image-thumbnail path.
- cURL for the most reliable outbound URL and oEmbed requests.
- FFmpeg and `ffprobe` when using FFmpeg thumbnails or video uploads.
- ImageMagick when selecting the ImageMagick thumbnail method.
- ExifTool when metadata stripping is enabled.

The application has no Composer installation step and the new flat-file engine has no third-party package dependency.

## Quick start on this Windows machine

PHP is expected at `C:\php\php.exe`. Double-click `start-tinyib.bat`, or run it from a Command Prompt. It starts PHP’s development server on `127.0.0.1:8000`, opens the board in the default browser, and remains attached to the console until Ctrl+C is pressed.

The built-in PHP server is for local testing. Do not expose it directly to the internet.

Before first real use:

1. Open `settings.php`.
2. Set a unique, high-entropy `TINYIB_TRIPSEED` before any posts or accounts are created.
3. Set temporary administrator and moderator passwords.
4. Choose `flatfile` or `sqlite3`.
5. Review upload, embed, CAPTCHA, moderation, and board-limit settings.
6. Start the board and sign in to verify the staff accounts were created.
7. Blank the installation password constants after account creation.

Changing the trip seed later invalidates secure tripcodes and changes password hashing behavior. Back it up securely and do not commit `settings.php`.

## Web-host installation

1. Upload the active project files to a PHP 8.5-capable host.
2. Configure `settings.php` directly; this customized tree already contains the active settings file.
3. Ensure the project root is writable when TinyIB needs to rebuild `index.html`, catalog files, or public JSON files.
4. Ensure `src/`, `thumb/`, and `res/` are writable.
5. For flat-file mode, ensure `TINYIB_FLATFILE_PATH` can be created and written. Prefer a path outside the document root.
6. For SQLite3 mode, ensure the directory containing `TINYIB_DBPATH` is writable and the PHP SQLite3 extension is loaded.
7. Visit `imgboard.php`. TinyIB initializes storage and rebuilds the board index.
8. Open the management panel and use Rebuild All after settings that affect generated pages are changed.

Apache can use the supplied `.htaccess` files. Nginx, Caddy, IIS, and other servers need equivalent denial rules for the SQLite database and any storage directory left under the document root.

## Configuration guide

### Storage

- `TINYIB_DBMODE`: `flatfile` or `sqlite3`.
- `TINYIB_FLATFILE_PATH`: directory used only by flat-file mode.
- `TINYIB_DBPATH`: database file used only by SQLite3 mode.
- `TINYIB_DBACCOUNTS`, `TINYIB_DBKEYWORDS`, `TINYIB_DBLOGS`, `TINYIB_DBPOSTS`, `TINYIB_DBREPORTS`: SQLite table names.

Do not reuse an old flat-file directory. This format starts fresh and has no legacy importer.

### CAPTCHA

The following settings accept `simple` or an empty string:

- `TINYIB_CAPTCHA` for new threads.
- `TINYIB_REPLYCAPTCHA` for replies.
- `TINYIB_REPORTCAPTCHA` for reports.
- `TINYIB_MANAGECAPTCHA` for management login.

The image and session solution are handled locally by `inc/captcha.php`. There are no hCaptcha/reCAPTCHA site keys or secrets.

### Generated pages and data

- `TINYIB_INDEX` controls the board index filename.
- `TINYIB_CATALOG` enables `catalog.html`.
- `TINYIB_JSON` enables public JSON output.
- `TINYIB_AUTOREFRESH` controls client-side reply refresh frequency.
- Rebuild All refreshes generated indexes, catalogs, JSON, and thread pages as applicable.

### Moderation and posting

- `TINYIB_REQMOD` can moderate file posts or all posts.
- `TINYIB_REPORT` enables user reports.
- `TINYIB_AUTOHIDE` hides posts after the configured report count.
- `TINYIB_MAXTHREADS` trims the oldest non-sticky threads beyond the limit.
- `TINYIB_MAXREPLIES` stops bumping after the configured reply count.
- `TINYIB_DISALLOWTHREADS` and `TINYIB_DISALLOWREPLIES` can temporarily close posting with a message.

## Backups and restore

A full board backup needs more than the database because uploaded files and generated pages live outside storage.

Back up at least:

- `settings.php` in a secure location.
- The complete flat-file data directory or SQLite database.
- `src/` for uploaded originals.
- `thumb/` for generated thumbnails.
- `res/`, the board index, catalog files, and public JSON if preserving generated output is useful.

Generated HTML and public JSON can be rebuilt from storage and uploads, but keeping them can shorten recovery.

### Flat-file backup

Stop posting or stop the PHP/web process, then copy the complete `TINYIB_FLATFILE_PATH` directory. Copying while writes are active can capture documents from different logical moments even though each individual document is atomic.

To restore, replace the full data directory while the board is stopped and restore writable permissions. If the canonical thread files are sound but `posts.php` is missing, the engine rebuilds the index on the next request.

### SQLite3 backup

Stop writes before copying the database, or use a SQLite-aware backup method. If the deployment enables a journaling mode that creates sidecar files, copying only the main database while it is active may be incomplete.

To restore, put the database back at `TINYIB_DBPATH`, restore permissions, and run Rebuild All for generated pages.

## Testing and verification

The repository includes three storage tests:

```text
tests/flatfile_test.php
tests/flatfile_adapter_test.php
tests/sqlite3_adapter_test.php
```

Run them on this machine with:

```text
C:\php\php.exe -d error_reporting=E_ALL tests\flatfile_test.php
C:\php\php.exe -d error_reporting=E_ALL tests\flatfile_adapter_test.php
C:\php\php.exe -d error_reporting=E_ALL tests\sqlite3_adapter_test.php
```

The completed modernization was verified with PHP 8.5.8:

- PHP lint passed for every PHP file in the active tree.
- Flat-file engine tests passed for collections, post IDs, threads, replies, moderation filtering, indexes, edits, deletion, and crash recovery.
- The procedural flat-file adapter passed account, keyword, post, report, and deletion operations.
- The SQLite3 adapter passed account, thread, reply, report, and deletion operations.
- Both adapters exposed the same board functions.
- A disposable full application test initialized flat-file storage, created a thread, created a reply, rebuilt thread HTML, and deleted the thread.
- A disposable full application test started in SQLite3 mode, returned HTTP 200, and created all five expected tables.
- Direct requests to guarded flat-file documents returned HTTP 404 with no data.
- Repository scans found no active MySQL, MySQLi, PDO, legacy SQLite, database-migration, gettext, hCaptcha, or reCAPTCHA references.

The tests use temporary storage and remove it after completion. They do not populate the live board with posts.

## File-by-file modernization ledger

### Added

- `inc/database/flatfile/FlatFileDatabase.php`: strict, purpose-built storage engine.
- `tests/flatfile_test.php`: engine and recovery tests.
- `tests/flatfile_adapter_test.php`: board-facing flat-file integration tests.
- `tests/sqlite3_adapter_test.php`: SQLite3 integration tests.
- `start-tinyib.bat`: local Windows PHP launcher.

### Replaced or substantially revised

- `inc/database/flatfile.php`: procedural adapter rewritten over the new engine.
- `inc/database/flatfile_link.php`: initializes the configured data path and reports initialization errors.
- `settings.php`: only flat-file and SQLite3 configuration remains; only simple CAPTCHA is advertised.
- `imgboard.php`: two-mode allowlist, flat-file directory creation/checks, English identity translation, and removal of database migration and hosted-CAPTCHA request paths.
- `inc/defines.php`: removed fallbacks for retired database, migration, locale, and hosted-CAPTCHA settings; added the flat-file path fallback.
- `inc/functions.php`: simple CAPTCHA validation remains; hosted CAPTCHA verification and translation integration were removed.
- `inc/html.php`: hosted CAPTCHA widgets, migration controls, and retired database recommendations were removed.
- `inc/database/sqlite3_link.php`: retained SQLite schema initialization while removing migration helper functions.
- `.gitignore`: ignores the new flat-file data directory and no longer carries patterns for legacy hidden tables.
- `.htaccess`: retains `.tinyib.db` protection and removes rules for legacy flat-file table names.
- `README.md`: documents this edition rather than the six-engine upstream layout.

### Deleted

- `inc/database/mysql.php`
- `inc/database/mysql_link.php`
- `inc/database/mysqli.php`
- `inc/database/mysqli_link.php`
- `inc/database/pdo.php`
- `inc/database/pdo_link.php`
- `inc/database/sqlite.php`
- `inc/database/sqlite_link.php`
- `inc/database/flatfile/flatfile.php`
- `inc/database/flatfile/flatfile_utils.php`
- `inc/gettext.php`
- `inc/gettext/`
- `inc/recaptcha/`
- `locale/`

## Deliberate limitations

- English is the only bundled interface language.
- Simple CAPTCHA is the only CAPTCHA implementation.
- Flat-file and SQLite3 are the only storage modes.
- There is no migration tool between storage engines.
- There is no importer for the old flat-file format.
- The flat-file engine is optimized for small boards, not unbounded scale or high write concurrency.
- Internal flat-file JSON is an implementation detail, not a supported external API.
- The rest of historical TinyIB has not yet received a complete line-by-line PHP 8.5 rewrite.

These limits are intentional. They keep the board understandable, portable, and appropriate for its intended size.

## Troubleshooting

### “Unknown database mode specified”

Set `TINYIB_DBMODE` to exactly `flatfile` or `sqlite3`.

### Flat-file directory cannot be created or written

Check `TINYIB_FLATFILE_PATH`, its parent-directory permissions, and the account running PHP. Moving the path outside the web root is recommended, but PHP must still be able to create and write it.

### “Invalid”, “corrupt”, or “unsupported” flat-file document

Do not remove the PHP guard or hand-edit documents without preserving valid JSON and format version 1. Restore the affected canonical document from backup. Deleting only `posts.php` is safe when canonical thread documents are valid because the index will be rebuilt.

### SQLite3 extension is not installed or loaded

Enable the `sqlite3` extension in the PHP installation used by the web server. CLI PHP and web-server PHP can load different `php.ini` files, so verify the server runtime as well.

### Direct storage request returns 404

That is expected. Flat-file documents are private storage and deliberately deny direct web access.

### Settings changed but pages look unchanged

Use Rebuild All in the management panel. TinyIB serves generated static HTML, so settings affecting rendering may not appear until pages are rebuilt.

### Posting works but images do not

Verify `src/` and `thumb/` are writable, check the configured MIME types and file-size limit, and confirm the selected thumbnail tool is installed.

## Upstream and license

Original project: [TinyIB on Codeberg](https://codeberg.org/tslocum/tinyib)

TinyIB remains under the license in `LICENSE`. This customized edition preserves that file and the original project attribution.
