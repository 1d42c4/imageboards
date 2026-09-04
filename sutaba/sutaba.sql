-- Sutaba SQLite3 schema, version 1.
-- The application creates this schema automatically on its first request.

PRAGMA foreign_keys = ON;

CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board TEXT NOT NULL,
    parent_id INTEGER,
    created_at INTEGER NOT NULL,
    ip TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL DEFAULT '',
    subject TEXT NOT NULL DEFAULT '',
    comment TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    pinned INTEGER NOT NULL DEFAULT 0 CHECK (pinned IN (0, 1)),
    locked INTEGER NOT NULL DEFAULT 0 CHECK (locked IN (0, 1)),
    FOREIGN KEY (parent_id) REFERENCES posts (id) ON DELETE CASCADE
);

CREATE INDEX posts_board_threads_idx
    ON posts (board, parent_id, pinned, created_at);
CREATE INDEX posts_parent_idx ON posts (parent_id, created_at);

CREATE TABLE images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL UNIQUE,
    filename TEXT NOT NULL UNIQUE,
    original_name TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    size INTEGER NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    original_data BLOB NOT NULL,
    thumbnail_data BLOB NOT NULL,
    FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
);

CREATE TABLE bans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board TEXT NOT NULL,
    post_id INTEGER,
    created_at INTEGER NOT NULL,
    ip TEXT NOT NULL,
    expires_at INTEGER NOT NULL DEFAULT 0,
    reason TEXT NOT NULL,
    UNIQUE (board, ip),
    FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE SET NULL
);

CREATE INDEX bans_board_expiry_idx ON bans (board, expires_at);

CREATE TABLE reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board TEXT NOT NULL,
    post_id INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    ip TEXT NOT NULL,
    UNIQUE (board, post_id, ip),
    FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
);

CREATE INDEX reports_board_post_idx ON reports (board, post_id);

CREATE TABLE spam (
    board TEXT NOT NULL,
    ip TEXT NOT NULL,
    available_at INTEGER NOT NULL,
    PRIMARY KEY (board, ip)
);

CREATE INDEX spam_expiry_idx ON spam (available_at);

CREATE TABLE wordfilters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board TEXT NOT NULL,
    word TEXT NOT NULL,
    replacement TEXT NOT NULL
);

CREATE INDEX wordfilters_board_idx ON wordfilters (board, id);

PRAGMA user_version = 1;
