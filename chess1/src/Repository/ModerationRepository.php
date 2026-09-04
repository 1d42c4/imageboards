<?php

declare(strict_types=1);

namespace Chessboard\Repository;

use Chessboard\Database;
use PDO;

final readonly class ModerationRepository
{
    public function __construct(private Database $database)
    {
    }

    public function moderatorByUsername(string $username): ?array
    {
        $query = $this->database->pdo()->prepare(
            'SELECT * FROM moderators WHERE username = :username COLLATE NOCASE LIMIT 1'
        );
        $query->execute(['username' => $username]);
        $moderator = $query->fetch();

        return $moderator === false ? null : $moderator;
    }

    public function moderatorById(int $id): ?array
    {
        $query = $this->database->pdo()->prepare(
            'SELECT id, username, role, created_at, last_login_at
             FROM moderators WHERE id = :id LIMIT 1'
        );
        $query->execute(['id' => $id]);
        $moderator = $query->fetch();

        return $moderator === false ? null : $moderator;
    }

    public function createModerator(string $username, string $passwordHash, string $role = 'admin'): int
    {
        $query = $this->database->pdo()->prepare(
            'INSERT INTO moderators (username, password_hash, role, created_at)
             VALUES (:username, :password_hash, :role, :created_at)'
        );
        $query->execute([
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => $role,
            'created_at' => time(),
        ]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function markLogin(int $id, ?string $newHash = null): void
    {
        if ($newHash !== null) {
            $query = $this->database->pdo()->prepare(
                'UPDATE moderators
                 SET last_login_at = :time, password_hash = :password_hash
                 WHERE id = :id'
            );
            $query->execute(['time' => time(), 'password_hash' => $newHash, 'id' => $id]);

            return;
        }

        $query = $this->database->pdo()->prepare(
            'UPDATE moderators SET last_login_at = :time WHERE id = :id'
        );
        $query->execute(['time' => time(), 'id' => $id]);
    }

    public function createReport(int $postId, string $reason): int
    {
        $query = $this->database->pdo()->prepare(
            'INSERT INTO reports (post_id, reason, created_at)
             VALUES (:post_id, :reason, :created_at)'
        );
        $query->execute([
            'post_id' => $postId,
            'reason' => $reason,
            'created_at' => time(),
        ]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function openReports(int $limit = 100): array
    {
        $query = $this->database->pdo()->prepare(
            "SELECT r.*, p.post_no, p.body, p.is_deleted, b.slug AS board_slug,
                    COALESCE(op.post_no, p.post_no) AS thread_no
             FROM reports r
             JOIN posts p ON p.id = r.post_id
             JOIN boards b ON b.id = p.board_id
             LEFT JOIN posts op ON op.id = p.thread_id
             WHERE r.status = 'open'
             ORDER BY r.created_at DESC
             LIMIT :limit"
        );
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function dismissReport(int $id, int $moderatorId): void
    {
        $query = $this->database->pdo()->prepare(
            "UPDATE reports
             SET status = 'dismissed', handled_by = :moderator_id, handled_at = :handled_at
             WHERE id = :id AND status = 'open'"
        );
        $query->execute([
            'moderator_id' => $moderatorId,
            'handled_at' => time(),
            'id' => $id,
        ]);
    }

    public function dismissReportsForPost(int $postId, int $moderatorId): void
    {
        $query = $this->database->pdo()->prepare(
            "UPDATE reports
             SET status = 'dismissed', handled_by = :moderator_id, handled_at = :handled_at
             WHERE post_id = :post_id AND status = 'open'"
        );
        $query->execute([
            'moderator_id' => $moderatorId,
            'handled_at' => time(),
            'post_id' => $postId,
        ]);
    }

    public function counts(): array
    {
        return [
            'reports' => (int) $this->database->pdo()
                ->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")
                ->fetchColumn(),
            'posts' => (int) $this->database->pdo()
                ->query('SELECT COUNT(*) FROM posts')
                ->fetchColumn(),
        ];
    }

    public function log(
        int $moderatorId,
        string $action,
        ?int $boardId = null,
        ?int $postId = null,
        string $details = '',
    ): void {
        $query = $this->database->pdo()->prepare(
            'INSERT INTO moderation_log (
                moderator_id, action, board_id, post_id, details, created_at
             ) VALUES (
                :moderator_id, :action, :board_id, :post_id, :details, :created_at
             )'
        );
        $query->bindValue(':moderator_id', $moderatorId, PDO::PARAM_INT);
        $query->bindValue(':action', $action);
        $query->bindValue(':board_id', $boardId, $boardId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $query->bindValue(':post_id', $postId, $postId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $query->bindValue(':details', $details);
        $query->bindValue(':created_at', time(), PDO::PARAM_INT);
        $query->execute();
    }

    public function recentLog(int $limit = 30): array
    {
        $query = $this->database->pdo()->prepare(
            'SELECT l.*, m.username, b.slug AS board_slug, p.post_no
             FROM moderation_log l
             JOIN moderators m ON m.id = l.moderator_id
             LEFT JOIN boards b ON b.id = l.board_id
             LEFT JOIN posts p ON p.id = l.post_id
             ORDER BY l.created_at DESC
             LIMIT :limit'
        );
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }
}

