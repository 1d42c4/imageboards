<?php

declare(strict_types=1);

namespace Chessboard\Repository;

use Chessboard\Database;
use Chessboard\Http\HttpException;
use PDO;

final readonly class PostRepository
{
    public function __construct(private Database $database)
    {
    }

    public function create(
        array $board,
        ?int $threadNo,
        string $subject,
        string $name,
        string $body,
        ?array $attachment,
        array $references,
    ): array {
        return $this->database->immediate(function (PDO $pdo) use (
            $board,
            $threadNo,
            $subject,
            $name,
            $body,
            $attachment,
            $references,
        ): array {
            $counter = $pdo->prepare(
                'SELECT next_post_no FROM board_counters WHERE board_id = :board_id'
            );
            $counter->execute(['board_id' => $board['id']]);
            $postNo = $counter->fetchColumn();
            if ($postNo === false) {
                throw new HttpException(500, 'The board counter is missing.');
            }

            $increment = $pdo->prepare(
                'UPDATE board_counters
                 SET next_post_no = next_post_no + 1
                 WHERE board_id = :board_id'
            );
            $increment->execute(['board_id' => $board['id']]);

            $thread = null;
            if ($threadNo !== null) {
                $threadQuery = $pdo->prepare(
                    'SELECT id, post_no, locked
                     FROM posts
                     WHERE board_id = :board_id
                       AND post_no = :post_no
                       AND thread_id IS NULL
                     LIMIT 1'
                );
                $threadQuery->execute([
                    'board_id' => $board['id'],
                    'post_no' => $threadNo,
                ]);
                $thread = $threadQuery->fetch();
                if ($thread === false) {
                    throw new HttpException(404, 'Thread not found.');
                }
                if ((int) $thread['locked'] === 1) {
                    throw new HttpException(423, 'This thread is locked.');
                }
            }

            $now = time();
            $insert = $pdo->prepare(
                'INSERT INTO posts (
                    board_id, post_no, thread_id, subject, name, body,
                    created_at, bumped_at
                 ) VALUES (
                    :board_id, :post_no, :thread_id, :subject, :name, :body,
                    :created_at, :bumped_at
                 )'
            );
            $insert->bindValue(':board_id', (int) $board['id'], PDO::PARAM_INT);
            $insert->bindValue(':post_no', (int) $postNo, PDO::PARAM_INT);
            $insert->bindValue(
                ':thread_id',
                $thread === null ? null : (int) $thread['id'],
                $thread === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
            );
            $insert->bindValue(':subject', $subject === '' ? null : $subject, $subject === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $insert->bindValue(':name', $name);
            $insert->bindValue(':body', $body);
            $insert->bindValue(':created_at', $now, PDO::PARAM_INT);
            $insert->bindValue(':bumped_at', $now, PDO::PARAM_INT);
            $insert->execute();

            $postId = (int) $pdo->lastInsertId();

            if ($thread !== null) {
                $bump = $pdo->prepare('UPDATE posts SET bumped_at = :time WHERE id = :id');
                $bump->execute(['time' => $now, 'id' => $thread['id']]);
            }

            if ($attachment !== null) {
                $this->insertAttachment($pdo, $postId, $attachment);
            }

            $this->insertCitations($pdo, $postId, $references);

            return [
                'id' => $postId,
                'post_no' => (int) $postNo,
                'thread_no' => $thread === null ? (int) $postNo : (int) $thread['post_no'],
                'board_slug' => $board['slug'],
            ];
        });
    }

    public function boardThreads(int $boardId, int $page, int $perPage, int $recentReplies): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $count = $this->database->pdo()->prepare(
            'SELECT COUNT(*) FROM posts WHERE board_id = :board_id AND thread_id IS NULL'
        );
        $count->execute(['board_id' => $boardId]);
        $threadCount = (int) $count->fetchColumn();

        $query = $this->database->pdo()->prepare(
            'SELECT p.*, a.original_name, a.stored_name, a.thumb_name, a.mime_type,
                    a.byte_size, a.width, a.height, a.thumb_width, a.thumb_height,
                    (SELECT COUNT(*) FROM posts r WHERE r.thread_id = p.id) AS reply_count,
                    (SELECT COUNT(*) FROM attachments ra
                        JOIN posts rp ON rp.id = ra.post_id
                        WHERE rp.thread_id = p.id) AS reply_image_count
             FROM posts p
             LEFT JOIN attachments a ON a.post_id = p.id
             WHERE p.board_id = :board_id AND p.thread_id IS NULL
             ORDER BY p.sticky DESC, p.bumped_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $query->bindValue(':board_id', $boardId, PDO::PARAM_INT);
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        $threads = $query->fetchAll();

        $replyQuery = $this->database->pdo()->prepare(
            'SELECT p.*, a.original_name, a.stored_name, a.thumb_name, a.mime_type,
                    a.byte_size, a.width, a.height, a.thumb_width, a.thumb_height
             FROM posts p
             LEFT JOIN attachments a ON a.post_id = p.id
             WHERE p.thread_id = :thread_id
             ORDER BY p.post_no DESC
             LIMIT :limit'
        );

        foreach ($threads as &$thread) {
            $replyQuery->bindValue(':thread_id', (int) $thread['id'], PDO::PARAM_INT);
            $replyQuery->bindValue(':limit', $recentReplies, PDO::PARAM_INT);
            $replyQuery->execute();
            $thread['replies'] = array_reverse($replyQuery->fetchAll());
            $thread['thread_no'] = (int) $thread['post_no'];
            foreach ($thread['replies'] as &$reply) {
                $reply['thread_no'] = (int) $thread['post_no'];
                $reply['backlinks'] = [];
            }
            unset($reply);
            $thread['backlinks'] = [];
        }
        unset($thread);

        return [
            'threads' => $threads,
            'page' => $page,
            'pages' => max(1, (int) ceil($threadCount / $perPage)),
            'total' => $threadCount,
        ];
    }

    public function thread(int $boardId, int $threadNo): ?array
    {
        $opQuery = $this->database->pdo()->prepare(
            'SELECT id, post_no, locked, sticky
             FROM posts
             WHERE board_id = :board_id AND post_no = :post_no AND thread_id IS NULL
             LIMIT 1'
        );
        $opQuery->execute(['board_id' => $boardId, 'post_no' => $threadNo]);
        $op = $opQuery->fetch();
        if ($op === false) {
            return null;
        }

        $query = $this->database->pdo()->prepare(
            'SELECT p.*, a.original_name, a.stored_name, a.thumb_name, a.mime_type,
                    a.byte_size, a.width, a.height, a.thumb_width, a.thumb_height
             FROM posts p
             LEFT JOIN attachments a ON a.post_id = p.id
             WHERE p.id = :thread_id OR p.thread_id = :thread_id
             ORDER BY p.post_no'
        );
        $query->execute(['thread_id' => $op['id']]);
        $posts = $query->fetchAll();

        $ids = array_map(static fn (array $post): int => (int) $post['id'], $posts);
        $backlinks = $this->backlinks($ids);
        foreach ($posts as &$post) {
            $post['thread_no'] = $threadNo;
            $post['backlinks'] = $backlinks[(int) $post['id']] ?? [];
        }
        unset($post);

        return [
            'id' => (int) $op['id'],
            'thread_no' => $threadNo,
            'locked' => (int) $op['locked'] === 1,
            'sticky' => (int) $op['sticky'] === 1,
            'posts' => $posts,
        ];
    }

    public function find(int $boardId, int $postNo): ?array
    {
        $query = $this->database->pdo()->prepare(
            'SELECT p.*, b.slug AS board_slug,
                    COALESCE(op.post_no, p.post_no) AS thread_no,
                    a.original_name, a.stored_name, a.thumb_name, a.mime_type,
                    a.byte_size, a.width, a.height, a.thumb_width, a.thumb_height
             FROM posts p
             JOIN boards b ON b.id = p.board_id
             LEFT JOIN posts op ON op.id = p.thread_id
             LEFT JOIN attachments a ON a.post_id = p.id
             WHERE p.board_id = :board_id AND p.post_no = :post_no
             LIMIT 1'
        );
        $query->execute(['board_id' => $boardId, 'post_no' => $postNo]);
        $post = $query->fetch();

        return $post === false ? null : $post;
    }

    public function softDelete(int $postId): ?array
    {
        return $this->database->immediate(function (PDO $pdo) use ($postId): ?array {
            $attachmentQuery = $pdo->prepare(
                'SELECT stored_name, thumb_name FROM attachments WHERE post_id = :post_id'
            );
            $attachmentQuery->execute(['post_id' => $postId]);
            $attachment = $attachmentQuery->fetch();

            $deleteAttachment = $pdo->prepare('DELETE FROM attachments WHERE post_id = :post_id');
            $deleteAttachment->execute(['post_id' => $postId]);

            $delete = $pdo->prepare(
                "UPDATE posts
                 SET subject = NULL, name = 'Anonymous', body = '', is_deleted = 1
                 WHERE id = :id"
            );
            $delete->execute(['id' => $postId]);

            return $attachment === false ? null : $attachment;
        });
    }

    public function setThreadFlag(int $boardId, int $threadNo, string $flag, bool $enabled): array
    {
        if (!in_array($flag, ['locked', 'sticky'], true)) {
            throw new HttpException(400, 'Unknown thread setting.');
        }

        $post = $this->find($boardId, $threadNo);
        if ($post === null || $post['thread_id'] !== null) {
            throw new HttpException(404, 'Thread not found.');
        }

        $query = $this->database->pdo()->prepare(
            sprintf('UPDATE posts SET %s = :enabled WHERE id = :id', $flag)
        );
        $query->execute(['enabled' => $enabled ? 1 : 0, 'id' => $post['id']]);

        return $post;
    }

    private function insertAttachment(PDO $pdo, int $postId, array $attachment): void
    {
        $query = $pdo->prepare(
            'INSERT INTO attachments (
                post_id, original_name, stored_name, thumb_name, mime_type,
                byte_size, width, height, thumb_width, thumb_height
             ) VALUES (
                :post_id, :original_name, :stored_name, :thumb_name, :mime_type,
                :byte_size, :width, :height, :thumb_width, :thumb_height
             )'
        );
        $query->execute([
            'post_id' => $postId,
            'original_name' => $attachment['original_name'],
            'stored_name' => $attachment['stored_name'],
            'thumb_name' => $attachment['thumb_name'],
            'mime_type' => $attachment['mime_type'],
            'byte_size' => $attachment['byte_size'],
            'width' => $attachment['width'],
            'height' => $attachment['height'],
            'thumb_width' => $attachment['thumb_width'],
            'thumb_height' => $attachment['thumb_height'],
        ]);
    }

    private function insertCitations(PDO $pdo, int $postId, array $references): void
    {
        $lookup = $pdo->prepare(
            'SELECT p.id
             FROM posts p
             JOIN boards b ON b.id = p.board_id
             WHERE b.slug = :slug COLLATE NOCASE AND p.post_no = :post_no
             LIMIT 1'
        );
        $insert = $pdo->prepare(
            'INSERT OR IGNORE INTO citations (post_id, target_post_id)
             VALUES (:post_id, :target_post_id)'
        );

        foreach ($references as $reference) {
            $lookup->execute([
                'slug' => $reference['board'],
                'post_no' => $reference['post_no'],
            ]);
            $targetId = $lookup->fetchColumn();
            if ($targetId === false || (int) $targetId === $postId) {
                continue;
            }

            $insert->execute(['post_id' => $postId, 'target_post_id' => (int) $targetId]);
        }
    }

    private function backlinks(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $query = $this->database->pdo()->prepare(
            "SELECT c.target_post_id, source.post_no, source_board.slug AS board_slug,
                    COALESCE(source_op.post_no, source.post_no) AS thread_no
             FROM citations c
             JOIN posts source ON source.id = c.post_id
             JOIN boards source_board ON source_board.id = source.board_id
             LEFT JOIN posts source_op ON source_op.id = source.thread_id
             WHERE c.target_post_id IN ($placeholders)
             ORDER BY source.created_at, source.post_no"
        );
        foreach ($postIds as $index => $postId) {
            $query->bindValue($index + 1, $postId, PDO::PARAM_INT);
        }
        $query->execute();

        $backlinks = [];
        foreach ($query->fetchAll() as $row) {
            $backlinks[(int) $row['target_post_id']][] = $row;
        }

        return $backlinks;
    }
}

