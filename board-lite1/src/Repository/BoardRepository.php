<?php

declare(strict_types=1);

namespace Chessboard\Repository;

use Chessboard\Database;
use PDO;

final readonly class BoardRepository
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        $query = $this->database->pdo()->query(
            'SELECT b.*,
                (SELECT COUNT(*) FROM posts p WHERE p.board_id = b.id) AS post_count,
                (SELECT COUNT(*) FROM posts p WHERE p.board_id = b.id AND p.thread_id IS NULL) AS thread_count
             FROM boards b
             ORDER BY b.slug COLLATE NOCASE'
        );

        return $query->fetchAll();
    }

    public function find(string $slug): ?array
    {
        $query = $this->database->pdo()->prepare(
            'SELECT b.*,
                (SELECT COUNT(*) FROM posts p WHERE p.board_id = b.id) AS post_count,
                (SELECT COUNT(*) FROM posts p WHERE p.board_id = b.id AND p.thread_id IS NULL) AS thread_count
             FROM boards b
             WHERE b.slug = :slug COLLATE NOCASE
             LIMIT 1'
        );
        $query->execute(['slug' => $slug]);
        $board = $query->fetch();

        return $board === false ? null : $board;
    }

    public function create(string $slug, string $title, string $description): array
    {
        return $this->database->immediate(function (PDO $pdo) use ($slug, $title, $description): array {
            $query = $pdo->prepare(
                'INSERT INTO boards (slug, title, description, created_at)
                 VALUES (:slug, :title, :description, :created_at)'
            );
            $query->execute([
                'slug' => $slug,
                'title' => $title,
                'description' => $description,
                'created_at' => time(),
            ]);

            $boardId = (int) $pdo->lastInsertId();
            $counter = $pdo->prepare(
                'INSERT INTO board_counters (board_id, next_post_no) VALUES (:board_id, 1)'
            );
            $counter->execute(['board_id' => $boardId]);

            return [
                'id' => $boardId,
                'slug' => $slug,
                'title' => $title,
                'description' => $description,
                'post_count' => 0,
                'thread_count' => 0,
            ];
        });
    }

    public function exists(string $slug): bool
    {
        $query = $this->database->pdo()->prepare(
            'SELECT 1 FROM boards WHERE slug = :slug COLLATE NOCASE'
        );
        $query->execute(['slug' => $slug]);

        return (bool) $query->fetchColumn();
    }
}

