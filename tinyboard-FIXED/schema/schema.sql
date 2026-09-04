-- Newboard schema (SQLite 3). No IP columns. Ever.

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS meta (
    key   TEXT PRIMARY KEY NOT NULL,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS boards (
    uri              TEXT PRIMARY KEY NOT NULL COLLATE NOCASE,
    title            TEXT NOT NULL,
    subtitle         TEXT NOT NULL DEFAULT '',
    created_at       INTEGER NOT NULL,
    require_password INTEGER NOT NULL DEFAULT 0,
    password_hash    TEXT,
    require_approval INTEGER NOT NULL DEFAULT 0,
    force_image_op   INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS posts (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    board_uri     TEXT NOT NULL COLLATE NOCASE,
    thread_id     INTEGER,
    time          INTEGER NOT NULL,
    bump          INTEGER NOT NULL,
    name          TEXT NOT NULL DEFAULT '',
    trip          TEXT NOT NULL DEFAULT '',
    email         TEXT NOT NULL DEFAULT '',
    subject       TEXT NOT NULL DEFAULT '',
    body          TEXT NOT NULL DEFAULT '',
    body_html     TEXT NOT NULL DEFAULT '',
    file_path     TEXT,
    file_orig     TEXT,
    file_size     INTEGER,
    file_width    INTEGER,
    file_height   INTEGER,
    thumb_path    TEXT,
    thumb_width   INTEGER,
    thumb_height  INTEGER,
    sticky        INTEGER NOT NULL DEFAULT 0,
    locked        INTEGER NOT NULL DEFAULT 0,
    sage          INTEGER NOT NULL DEFAULT 0,
    bumplock      INTEGER NOT NULL DEFAULT 0,
    pending       INTEGER NOT NULL DEFAULT 0,
    archived      INTEGER NOT NULL DEFAULT 0,
    archived_at   INTEGER,
    capcode       TEXT NOT NULL DEFAULT '',
    FOREIGN KEY (board_uri) REFERENCES boards(uri) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_posts_board_thread ON posts(board_uri, thread_id);
CREATE INDEX IF NOT EXISTS idx_posts_board_bump ON posts(board_uri, sticky DESC, bump DESC);
CREATE INDEX IF NOT EXISTS idx_posts_pending ON posts(pending);
CREATE INDEX IF NOT EXISTS idx_posts_archived ON posts(board_uri, archived);

CREATE TABLE IF NOT EXISTS mods (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    type          TEXT NOT NULL DEFAULT 'admin',
    boards        TEXT NOT NULL DEFAULT '*',
    created_at    INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS reports (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    board_uri  TEXT NOT NULL COLLATE NOCASE,
    post_id    INTEGER NOT NULL,
    reason     TEXT NOT NULL DEFAULT '',
    time       INTEGER NOT NULL,
    FOREIGN KEY (board_uri) REFERENCES boards(uri) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_reports_post ON reports(board_uri, post_id);

CREATE TABLE IF NOT EXISTS mod_log (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    mod_id    INTEGER,
    username  TEXT NOT NULL DEFAULT '',
    board_uri TEXT,
    action    TEXT NOT NULL,
    detail    TEXT NOT NULL DEFAULT '',
    time      INTEGER NOT NULL
    -- deliberately no IP column
);

CREATE TABLE IF NOT EXISTS cites (
    board_uri        TEXT NOT NULL COLLATE NOCASE,
    post_id          INTEGER NOT NULL,
    target_board_uri TEXT NOT NULL COLLATE NOCASE,
    target_post_id   INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_cites_target ON cites(target_board_uri, target_post_id);
