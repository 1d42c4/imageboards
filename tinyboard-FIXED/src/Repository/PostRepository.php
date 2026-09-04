<?php

declare(strict_types=1);

namespace Newboard\Repository;

use Newboard\Database;

final class PostRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function find(string $boardUri, int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM posts WHERE board_uri = ? COLLATE NOCASE AND id = ? AND pending = 0 AND archived = 0',
            [$boardUri, $id]
        );
    }

    public function findAny(string $boardUri, int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM posts WHERE board_uri = ? COLLATE NOCASE AND id = ?',
            [$boardUri, $id]
        );
    }

    public function findArchivedOp(string $boardUri, int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM posts WHERE board_uri = ? COLLATE NOCASE AND id = ? AND thread_id IS NULL AND archived = 1',
            [$boardUri, $id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function threads(string $boardUri, int $limit, int $offset): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM posts
             WHERE board_uri = ? COLLATE NOCASE AND thread_id IS NULL AND pending = 0 AND archived = 0
             ORDER BY sticky DESC, bump DESC
             LIMIT ? OFFSET ?',
            [$boardUri, $limit, $offset]
        );
    }

    public function countThreads(string $boardUri): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM posts
             WHERE board_uri = ? COLLATE NOCASE AND thread_id IS NULL AND pending = 0 AND archived = 0',
            [$boardUri]
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @return list<array<string, mixed>> */
    public function replies(string $boardUri, int $threadId, ?int $limit = null, bool $includeArchived = false): array
    {
        $arch = $includeArchived ? '' : ' AND pending = 0';
        if ($limit !== null) {
            return $this->db->fetchAll(
                "SELECT * FROM (
                    SELECT * FROM posts
                    WHERE board_uri = ? COLLATE NOCASE AND thread_id = ? {$arch}
                    ORDER BY id DESC
                    LIMIT ?
                ) AS t ORDER BY id ASC",
                [$boardUri, $threadId, $limit]
            );
        }

        return $this->db->fetchAll(
            "SELECT * FROM posts
             WHERE board_uri = ? COLLATE NOCASE AND thread_id = ? {$arch}
             ORDER BY id ASC",
            [$boardUri, $threadId]
        );
    }

    public function countReplies(string $boardUri, int $threadId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM posts
             WHERE board_uri = ? COLLATE NOCASE AND thread_id = ? AND pending = 0',
            [$boardUri, $threadId]
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @return list<array<string, mixed>> */
    public function catalog(string $boardUri): array
    {
        return $this->db->fetchAll(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM posts r
                     WHERE r.board_uri = p.board_uri AND r.thread_id = p.id AND r.pending = 0) AS reply_count
             FROM posts p
             WHERE p.board_uri = ? COLLATE NOCASE AND p.thread_id IS NULL AND p.pending = 0 AND p.archived = 0
             ORDER BY p.sticky DESC, p.bump DESC',
            [$boardUri]
        );
    }

    /** @return list<array<string, mixed>> */
    public function archiveIndex(string $boardUri, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM posts r
                     WHERE r.board_uri = p.board_uri AND r.thread_id = p.id) AS reply_count
             FROM posts p
             WHERE p.board_uri = ? COLLATE NOCASE AND p.thread_id IS NULL AND p.archived = 1
             ORDER BY COALESCE(p.archived_at, p.time) DESC, p.id DESC
             LIMIT ? OFFSET ?',
            [$boardUri, $limit, $offset]
        );
    }

    public function countArchived(string $boardUri): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM posts
             WHERE board_uri = ? COLLATE NOCASE AND thread_id IS NULL AND archived = 1',
            [$boardUri]
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        if (!array_key_exists('bumplock', $data)) {
            $data['bumplock'] = 0;
        }
        if (!array_key_exists('archived', $data)) {
            $data['archived'] = 0;
        }
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = sprintf(
            'INSERT INTO posts (%s) VALUES (%s)',
            implode(', ', $cols),
            implode(', ', $placeholders)
        );
        $this->db->query($sql, array_values($data));

        return $this->db->lastInsertId();
    }

    public function bumpThread(string $boardUri, int $threadId, int $time): void
    {
        $this->db->query(
            'UPDATE posts SET bump = ?
             WHERE board_uri = ? COLLATE NOCASE AND id = ? AND thread_id IS NULL
               AND COALESCE(bumplock, 0) = 0',
            [$time, $boardUri, $threadId]
        );
    }

    public function delete(string $boardUri, int $id): void
    {
        $post = $this->findAny($boardUri, $id);
        if ($post === null) {
            return;
        }
        if ($post['thread_id'] === null) {
            $ids = $this->db->fetchAll(
                'SELECT id FROM posts WHERE board_uri = ? COLLATE NOCASE AND (id = ? OR thread_id = ?)',
                [$boardUri, $id, $id]
            );
            foreach ($ids as $row) {
                $this->db->query(
                    'DELETE FROM reports WHERE board_uri = ? COLLATE NOCASE AND post_id = ?',
                    [$boardUri, (int) $row['id']]
                );
            }
            $this->db->query(
                'DELETE FROM posts WHERE board_uri = ? COLLATE NOCASE AND (id = ? OR thread_id = ?)',
                [$boardUri, $id, $id]
            );
        } else {
            $this->db->query(
                'DELETE FROM reports WHERE board_uri = ? COLLATE NOCASE AND post_id = ?',
                [$boardUri, $id]
            );
            $this->db->query(
                'DELETE FROM posts WHERE board_uri = ? COLLATE NOCASE AND id = ?',
                [$boardUri, $id]
            );
        }
    }

    public function setSticky(string $boardUri, int $id, bool $sticky): void
    {
        $this->db->query(
            'UPDATE posts SET sticky = ? WHERE board_uri = ? COLLATE NOCASE AND id = ? AND thread_id IS NULL',
            [$sticky ? 1 : 0, $boardUri, $id]
        );
    }

    public function setLocked(string $boardUri, int $id, bool $locked): void
    {
        $this->db->query(
            'UPDATE posts SET locked = ? WHERE board_uri = ? COLLATE NOCASE AND id = ? AND thread_id IS NULL',
            [$locked ? 1 : 0, $boardUri, $id]
        );
    }

    public function setBumplock(string $boardUri, int $id, bool $bumplock): void
    {
        $this->db->query(
            'UPDATE posts SET bumplock = ? WHERE board_uri = ? COLLATE NOCASE AND id = ? AND thread_id IS NULL',
            [$bumplock ? 1 : 0, $boardUri, $id]
        );
    }

    public function archiveThread(string $boardUri, int $id): bool
    {
        $op = $this->findAny($boardUri, $id);
        if ($op === null || $op['thread_id'] !== null) {
            return false;
        }
        if ((int) ($op['archived'] ?? 0) === 1) {
            return true;
        }
        $now = time();
        $this->db->query(
            'UPDATE posts SET archived = 1, sticky = 0, archived_at = ?
             WHERE board_uri = ? COLLATE NOCASE AND id = ?',
            [$now, $boardUri, $id]
        );

        return true;
    }

    /**
     * Non-sticky live OPs past the keep window (for auto-archive).
     *
     * @return list<array<string, mixed>>
     */
    public function threadsPastLimit(string $boardUri, int $keep): array
    {
        if ($keep < 1) {
            $keep = 1;
        }

        return $this->db->fetchAll(
            'SELECT * FROM posts
             WHERE board_uri = ? COLLATE NOCASE
               AND thread_id IS NULL AND pending = 0 AND archived = 0
             ORDER BY sticky DESC, bump DESC
             LIMIT 10000 OFFSET ?',
            [$boardUri, $keep]
        );
    }

    public function approve(string $boardUri, int $id): void
    {
        $post = $this->findAny($boardUri, $id);
        if ($post === null) {
            return;
        }
        $this->db->query(
            'UPDATE posts SET pending = 0 WHERE board_uri = ? COLLATE NOCASE AND id = ?',
            [$boardUri, $id]
        );
        if ($post['thread_id'] !== null && (int) ($post['sage'] ?? 0) === 0) {
            $this->bumpThread($boardUri, (int) $post['thread_id'], (int) $post['time']);
        }
    }

    public function rejectPending(string $boardUri, int $id): void
    {
        $post = $this->findAny($boardUri, $id);
        if ($post === null || (int) ($post['pending'] ?? 0) !== 1) {
            return;
        }
        $this->delete($boardUri, $id);
    }

    /** @return list<array<string, mixed>> */
    public function pending(int $limit = 100): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM posts WHERE pending = 1 ORDER BY time ASC LIMIT ?',
            [$limit]
        );
    }

    public function countPending(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS c FROM posts WHERE pending = 1');

        return (int) ($row['c'] ?? 0);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM posts WHERE pending = 0 ORDER BY time DESC LIMIT ?',
            [$limit]
        );
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function updatePost(string $boardUri, int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            $sets[] = $k . ' = ?';
            $vals[] = $v;
        }
        $vals[] = $boardUri;
        $vals[] = $id;
        $this->db->query(
            'UPDATE posts SET ' . implode(', ', $sets) . ' WHERE board_uri = ? COLLATE NOCASE AND id = ?',
            $vals
        );
    }

    public function clearFile(string $boardUri, int $id): ?array
    {
        $post = $this->findAny($boardUri, $id);
        if ($post === null) {
            return null;
        }
        $this->db->query(
            'UPDATE posts SET file_path = NULL, file_orig = NULL, file_size = NULL,
                file_width = NULL, file_height = NULL, thumb_path = NULL,
                thumb_width = NULL, thumb_height = NULL
             WHERE board_uri = ? COLLATE NOCASE AND id = ?',
            [$boardUri, $id]
        );

        return $post;
    }
}
