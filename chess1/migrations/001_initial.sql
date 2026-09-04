CREATE TABLE boards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL COLLATE NOCASE UNIQUE,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    created_at INTEGER NOT NULL
);
-- migrate:split
CREATE TABLE board_counters (
    board_id INTEGER PRIMARY KEY,
    next_post_no INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
);
-- migrate:split
CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board_id INTEGER NOT NULL,
    post_no INTEGER NOT NULL,
    thread_id INTEGER,
    subject TEXT,
    name TEXT NOT NULL DEFAULT 'Anonymous',
    body TEXT NOT NULL DEFAULT '',
    created_at INTEGER NOT NULL,
    bumped_at INTEGER NOT NULL,
    sticky INTEGER NOT NULL DEFAULT 0 CHECK (sticky IN (0, 1)),
    locked INTEGER NOT NULL DEFAULT 0 CHECK (locked IN (0, 1)),
    is_deleted INTEGER NOT NULL DEFAULT 0 CHECK (is_deleted IN (0, 1)),
    UNIQUE (board_id, post_no),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (thread_id) REFERENCES posts(id) ON DELETE CASCADE
);
-- migrate:split
CREATE INDEX posts_board_threads ON posts(board_id, thread_id, sticky DESC, bumped_at DESC);
-- migrate:split
CREATE INDEX posts_thread_order ON posts(thread_id, post_no);
-- migrate:split
CREATE TABLE attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL UNIQUE,
    original_name TEXT NOT NULL,
    stored_name TEXT NOT NULL UNIQUE,
    thumb_name TEXT NOT NULL UNIQUE,
    mime_type TEXT NOT NULL,
    byte_size INTEGER NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    thumb_width INTEGER NOT NULL,
    thumb_height INTEGER NOT NULL,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);
-- migrate:split
CREATE TABLE moderators (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL COLLATE NOCASE UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'moderator' CHECK (role IN ('moderator', 'admin')),
    created_at INTEGER NOT NULL,
    last_login_at INTEGER
);
-- migrate:split
CREATE TABLE reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    reason TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'dismissed')),
    created_at INTEGER NOT NULL,
    handled_by INTEGER,
    handled_at INTEGER,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (handled_by) REFERENCES moderators(id) ON DELETE SET NULL
);
-- migrate:split
CREATE INDEX reports_status_time ON reports(status, created_at DESC);
-- migrate:split
CREATE TABLE moderation_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    moderator_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    board_id INTEGER,
    post_id INTEGER,
    details TEXT NOT NULL DEFAULT '',
    created_at INTEGER NOT NULL,
    FOREIGN KEY (moderator_id) REFERENCES moderators(id) ON DELETE RESTRICT,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE SET NULL,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL
);
-- migrate:split
CREATE INDEX moderation_log_time ON moderation_log(created_at DESC);
