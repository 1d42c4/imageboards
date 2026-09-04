# Sutaba

Sutaba is a lightweight, standalone imageboard. This edition is SQLite3-only
and targets PHP 8.5.8 or newer.

## Requirements

- PHP 8.5.8 or newer
- PHP extensions: `sqlite3`, `gd`, `mbstring`, and `fileinfo`
- Write access to the configured SQLite database location

No MySQL server, PDO driver, Composer install, image directory, or thumbnail
directory is used. Uploaded images and generated thumbnails are stored inside
SQLite.

## Install

1. Upload the project files.
2. Edit the configuration block near the top of `sutaba.php`.
3. Set `SUTABA_DATABASE_PATH` to a writable path outside the public web
   directory when possible.
4. Open `sutaba.php` in a browser. The SQLite database and schema are created
   automatically.

If `SUTABA_DATABASE_PATH` is not set, the database is created as
`sutaba.sqlite3` beside `sutaba.php`. Configure the web server to deny direct
access to SQLite database, WAL, and shared-memory files. The included
`.htaccess` supplies this protection for Apache 2.4.

The `sutaba.sql` file is provided for inspection or manual database creation;
the application does not require a manual import.

## Notes

- All database operations use PHP's native `SQLite3` extension and prepared
  statements.
- The first request upgrades an empty SQLite database to schema version 1.
- Existing MySQL dumps are not read by this build.
- Post deletion passwords are stored with `password_hash()`.
- The original classic 10-character tripcode format remains supported so the
  configured moderator and administrator tripcodes continue to work.
