-- vichan PostgreSQL schema (PHP 8.5+ / PostgreSQL 12+)
-- Converted from install.sql for modern deployments.

SET client_encoding = 'UTF8';
SET timezone = 'UTC';

-- --------------------------------------------------------
-- Table: boards
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS boards (
  uri varchar(58) NOT NULL,
  title varchar(255) NOT NULL,
  subtitle varchar(255) DEFAULT NULL,
  post_password varchar(255) DEFAULT NULL,
  require_approval smallint NOT NULL DEFAULT 0,
  PRIMARY KEY (uri)
);

INSERT INTO boards (uri, title, subtitle) VALUES
('b', 'Random', NULL)
ON CONFLICT (uri) DO NOTHING;

-- --------------------------------------------------------
-- Table: cites
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS cites (
  board varchar(58) NOT NULL,
  post integer NOT NULL,
  target_board varchar(58) NOT NULL,
  target integer NOT NULL
);
CREATE INDEX IF NOT EXISTS cites_target_idx ON cites (target_board, target);
CREATE INDEX IF NOT EXISTS cites_post_idx ON cites (board, post);

-- --------------------------------------------------------
-- Table: modlogs
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS modlogs (
  mod integer NOT NULL,
  board varchar(58) DEFAULT NULL,
  time integer NOT NULL,
  text text NOT NULL
);
CREATE INDEX IF NOT EXISTS modlogs_time_idx ON modlogs (time);
CREATE INDEX IF NOT EXISTS modlogs_mod_idx ON modlogs (mod);

-- --------------------------------------------------------
-- Table: mods
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS mods (
  id serial PRIMARY KEY,
  username varchar(30) NOT NULL,
  password varchar(256) NOT NULL,
  version varchar(64) NOT NULL,
  type smallint NOT NULL,
  boards text NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS mods_username_uidx ON mods (username);

-- Default admin / password (bcrypt; change immediately after install)
-- password_hash('password', PASSWORD_DEFAULT)
INSERT INTO mods (id, username, password, version, type, boards) VALUES
(1, 'admin', '$2y$12$FMLDoIqGNr0NTwKfkaJSW.WyU3u6w6wt.CO0B1MrGW8trh.bg6Q6O', '2', 30, '*')
ON CONFLICT DO NOTHING;

-- Ensure sequence is past seed id
SELECT setval(pg_get_serial_sequence('mods', 'id'), GREATEST((SELECT MAX(id) FROM mods), 1));

-- --------------------------------------------------------
-- Table: reports
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS reports (
  id serial PRIMARY KEY,
  time integer NOT NULL,
  board varchar(58) DEFAULT NULL,
  post integer NOT NULL,
  reason text NOT NULL
);

-- --------------------------------------------------------
-- Table: captchas
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS captchas (
  cookie varchar(50) NOT NULL,
  extra varchar(200) NOT NULL DEFAULT '',
  text varchar(255),
  created_at integer,
  PRIMARY KEY (cookie, extra)
);
