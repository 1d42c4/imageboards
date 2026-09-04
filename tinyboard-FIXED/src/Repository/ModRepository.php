<?php

declare(strict_types=1);

namespace Newboard\Repository;

use Newboard\Database;

final class ModRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM mods WHERE username = ? COLLATE NOCASE',
            [$username]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM mods WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT id, username, type, boards, created_at FROM mods ORDER BY id');
    }

    public function create(string $username, string $passwordHash, string $type = 'mod', string $boards = '*'): int
    {
        $this->db->query(
            'INSERT INTO mods (username, password_hash, type, boards, created_at) VALUES (?, ?, ?, ?, ?)',
            [$username, $passwordHash, $type, $boards, time()]
        );

        return $this->db->lastInsertId();
    }

    public function update(int $id, string $username, string $type, string $boards): void
    {
        $this->db->query(
            'UPDATE mods SET username = ?, type = ?, boards = ? WHERE id = ?',
            [$username, $type, $boards, $id]
        );
    }

    public function setPassword(int $id, string $passwordHash): void
    {
        $this->db->query('UPDATE mods SET password_hash = ? WHERE id = ?', [$passwordHash, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->query('DELETE FROM mods WHERE id = ?', [$id]);
    }

    public function log(?int $modId, string $username, ?string $boardUri, string $action, string $detail = ''): void
    {
        $this->db->query(
            'INSERT INTO mod_log (mod_id, username, board_uri, action, detail, time) VALUES (?, ?, ?, ?, ?, ?)',
            [$modId, $username, $boardUri, $action, $detail, time()]
        );
    }

    /** @return list<array<string, mixed>> */
    public function recentLog(int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM mod_log ORDER BY id DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public function countLog(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS c FROM mod_log');

        return (int) ($row['c'] ?? 0);
    }
}
