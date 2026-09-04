<?php
/**
 * SQLite database layer
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $isNew = !file_exists(DB_FILE);

    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNew) {
        initSchema($pdo);
    }

    return $pdo;
}

function initSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS posts (
    no          INTEGER PRIMARY KEY AUTOINCREMENT,
    resto       INTEGER NOT NULL DEFAULT 0,   -- 0 = OP, otherwise parent no
    time        INTEGER NOT NULL,
    name        TEXT    NOT NULL DEFAULT '',
    trip        TEXT    NOT NULL DEFAULT '',
    email       TEXT    NOT NULL DEFAULT '',
    sub         TEXT    NOT NULL DEFAULT '',
    com         TEXT    NOT NULL DEFAULT '',
    host        TEXT    NOT NULL DEFAULT '',
    pwd         TEXT    NOT NULL DEFAULT '',
    filename    TEXT    NOT NULL DEFAULT '',
    ext         TEXT    NOT NULL DEFAULT '',
    w           INTEGER NOT NULL DEFAULT 0,
    h           INTEGER NOT NULL DEFAULT 0,
    tn_w        INTEGER NOT NULL DEFAULT 0,
    tn_h        INTEGER NOT NULL DEFAULT 0,
    tim         TEXT    NOT NULL DEFAULT '',
    md5         TEXT    NOT NULL DEFAULT '',
    fsize       INTEGER NOT NULL DEFAULT 0,
    sticky      INTEGER NOT NULL DEFAULT 0,
    closed      INTEGER NOT NULL DEFAULT 0,
    permasage   INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_resto ON posts(resto);
CREATE INDEX IF NOT EXISTS idx_time  ON posts(time);
CREATE INDEX IF NOT EXISTS idx_md5   ON posts(md5);
CREATE INDEX IF NOT EXISTS idx_root  ON posts(resto, sticky, time);
SQL
    );
}
