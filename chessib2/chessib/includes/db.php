<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO(DB_DSN, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
        init_schema($pdo);
    }
    return $pdo;
}

function init_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            parent      INTEGER NOT NULL DEFAULT 0,
            timestamp   INTEGER NOT NULL,
            bumped      INTEGER NOT NULL,
            ip          TEXT    NOT NULL DEFAULT '',
            name        TEXT    NOT NULL DEFAULT '',
            trip        TEXT    NOT NULL DEFAULT '',
            subject     TEXT    NOT NULL DEFAULT '',
            comment     TEXT    NOT NULL DEFAULT '',
            password    TEXT    NOT NULL DEFAULT '',
            file        TEXT    NOT NULL DEFAULT '',
            file_orig   TEXT    NOT NULL DEFAULT '',
            file_size   INTEGER NOT NULL DEFAULT 0,
            image_w     INTEGER NOT NULL DEFAULT 0,
            image_h     INTEGER NOT NULL DEFAULT 0,
            thumb       TEXT    NOT NULL DEFAULT '',
            stickied    INTEGER NOT NULL DEFAULT 0,
            locked      INTEGER NOT NULL DEFAULT 0
        );
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_posts_parent ON posts(parent);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_posts_bumped ON posts(bumped DESC);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_posts_stickied ON posts(stickied DESC, bumped DESC);");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bans (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            ip        TEXT    NOT NULL,
            reason    TEXT    NOT NULL DEFAULT '',
            expires   INTEGER NOT NULL DEFAULT 0,
            created   INTEGER NOT NULL
        );
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bans_ip ON bans(ip);");
}
?>
