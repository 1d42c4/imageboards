<?php

declare(strict_types=1);

namespace Newboard\Support;

use Newboard\Database;

/**
 * Lightweight SQLite migrations (additive only). Never adds IP columns.
 */
final class Migrator
{
    public function __construct(private readonly Database $db)
    {
    }

    public function run(): void
    {
        $this->ensureColumn('posts', 'bumplock', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumn('posts', 'archived', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumn('posts', 'archived_at', 'INTEGER');
        $this->ensureColumn('mods', 'boards', "TEXT NOT NULL DEFAULT '*'");

        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS idx_posts_archived ON posts(board_uri, archived)'
        );
        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS idx_reports_post ON reports(board_uri, post_id)'
        );
    }

    private function ensureColumn(string $table, string $column, string $ddl): void
    {
        $cols = $this->db->fetchAll('PRAGMA table_info(' . $table . ')');
        foreach ($cols as $c) {
            if (strcasecmp((string) $c['name'], $column) === 0) {
                return;
            }
        }
        $this->db->pdo()->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$ddl}");
    }
}
