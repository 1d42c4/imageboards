CREATE TABLE IF NOT EXISTS posts_{{ board }} (
   id serial PRIMARY KEY,
   thread integer DEFAULT NULL,
   subject varchar(100) DEFAULT NULL,
   email varchar(30) DEFAULT NULL,
   name varchar(35) DEFAULT NULL,
   trip varchar(15) DEFAULT NULL,
   capcode varchar(50) DEFAULT NULL,
   body text NOT NULL,
   body_nomarkup text,
   time integer NOT NULL,
   bump integer DEFAULT NULL,
   files text DEFAULT NULL,
   num_files integer DEFAULT 0,
   filehash text,
   password varchar(64) DEFAULT NULL,
   sticky smallint NOT NULL DEFAULT 0,
   locked smallint NOT NULL DEFAULT 0,
   cycle smallint NOT NULL DEFAULT 0,
   sage smallint NOT NULL DEFAULT 0,
   embed text,
   slug varchar(256) DEFAULT NULL,
   pending smallint NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS posts_{{ board }}_thread_id_idx ON posts_{{ board }} (thread, id);
CREATE INDEX IF NOT EXISTS posts_{{ board }}_filehash_idx ON posts_{{ board }} (filehash);
CREATE INDEX IF NOT EXISTS posts_{{ board }}_time_idx ON posts_{{ board }} (time);
CREATE INDEX IF NOT EXISTS posts_{{ board }}_list_threads_idx ON posts_{{ board }} (thread, sticky, bump);
CREATE INDEX IF NOT EXISTS posts_{{ board }}_pending_idx ON posts_{{ board }} (pending) WHERE pending = 1;
