<?php

declare(strict_types=1);

namespace Newboard\Repository;

use Newboard\Database;

final class ReportRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(string $boardUri, int $postId, string $reason): void
    {
        $this->db->query(
            'INSERT INTO reports (board_uri, post_id, reason, time) VALUES (?, ?, ?, ?)',
            [$boardUri, $postId, mb_substr($reason, 0, 500), time()]
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(int $limit = 100): array
    {
        return $this->db->fetchAll(
            'SELECT r.*, p.body, p.subject, p.name, p.thread_id, p.thumb_path, p.file_path, p.pending, p.archived
             FROM reports r
             LEFT JOIN posts p ON p.board_uri = r.board_uri AND p.id = r.post_id
             ORDER BY r.time DESC
             LIMIT ?',
            [$limit]
        );
    }

    public function count(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS c FROM reports');

        return (int) ($row['c'] ?? 0);
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM reports WHERE id = ?', [$id]);
    }

    public function dismiss(int $id): void
    {
        $this->db->query('DELETE FROM reports WHERE id = ?', [$id]);
    }

    public function dismissForPost(string $boardUri, int $postId): void
    {
        $this->db->query(
            'DELETE FROM reports WHERE board_uri = ? COLLATE NOCASE AND post_id = ?',
            [$boardUri, $postId]
        );
    }
}
