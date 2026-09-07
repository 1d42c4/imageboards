<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class BoardService
{
    public function __construct(private App $app)
    {
    }
    /**
     * @return array<string, mixed> */
    public function board(string $slug): array
    {
        Input::slug($slug);
        return $this->app->db->one('SELECT * FROM boards WHERE slug=?', [$slug]) ?? throw new HttpError('Board not found.', 404);
    }
    /**
     * @return array<string, mixed> */
    public function post(int $id): array
    {
        return $this->app->db->one('SELECT p.*,b.slug FROM posts p JOIN boards b ON b.id=p.board_id WHERE p.id=?', [$id]) ?? throw new HttpError('Post not found.', 404);
    }
    /**
     * @param array<string, mixed> $post */
    public function postPath(array $post): string
    {
        return $post['slug'] . '/res/' . ($post['thread_id'] ?? $post['id']) . '.html#' . $post['id'];
    }
    /**
     * @param array<string, mixed> $upload */
    public function create(PostDraft $draft, array $upload): int
    {
        $board = $this->board($draft->board);
        if ((bool) $board['locked']) {
            throw new HttpError('This board is read-only.', 403);
        }
        if ($draft->password !== '' && strlen($draft->password) < 8) {
            throw new HttpError('Use at least 8 characters for a deletion password, or leave it blank.');
        }
        $files = $this->app->media->receive($upload);
        try {
            if ($draft->body === '' && $files === []) {
                throw new HttpError('Write a comment or attach a file.');
            }
            return $this->app->db->transaction(function () use ($draft, $board, $files): int {
                if ($draft->thread !== 0) {
                    $thread = $this->post($draft->thread);
                    if ($thread['thread_id'] !== null || (int) $thread['board_id'] !== (int) $board['id']) {
                        throw new HttpError('Thread not found on this board.', 404);
                    }
                    if ((bool) $thread['locked']) {
                        throw new HttpError('This thread is locked.', 403);
                    }
                    $count = $this->app->db->one('SELECT COUNT(*) AS n FROM posts WHERE thread_id=?', [$draft->thread]);
                    if ((int) ($count['n'] ?? 0) >= $this->app->config->int('max_replies')) {
                        throw new HttpError('This thread has reached its reply limit. Please start another thread.');
                    }
                }
                $used = (int) ($this->app->db->one('SELECT COALESCE(SUM(bytes),0) AS n FROM files')['n'] ?? 0);
                if ($used + array_sum(array_column($files, 'bytes')) > $this->app->config->int('max_total_upload_bytes')) {
                    throw new HttpError('The board media storage limit has been reached.', 507);
                }
                $duplicate = $this->app->db->one('SELECT id FROM posts WHERE board_id=? AND body=? AND created>? AND body<>? LIMIT 1', [$board['id'], $draft->body, time() - 60, '']);
                if ($duplicate !== null) {
                    throw new HttpError('This comment was just posted. Please check the thread before posting again.', 409);
                }
                $now = time();
                $this->app->db->execute('INSERT INTO posts(board_id,thread_id,name,subject,body,created,bumped,deletion_hash) VALUES(?,?,?,?,?,?,?,?)', [$board['id'], $draft->thread ?: null, $draft->name, $draft->subject, $draft->body, $now, $now, $draft->password !== '' ? Security::hashPassword($draft->password) : '']);
                $id = (int) $this->app->db->pdo->lastInsertId();
                foreach ($files as $file) {
                    $this->app->db->execute('INSERT INTO files(post_id,token,filename,mime,extension,bytes,width,height,thumb) VALUES(?,?,?,?,?,?,?,?,?)', [$id, $file['token'], $file['filename'], $file['mime'], $file['extension'], $file['bytes'], $file['width'], $file['height'], $file['thumb']]);
                }
                if ($draft->thread !== 0 && !$draft->sage) {
                    $this->app->db->execute('UPDATE posts SET bumped=? WHERE id=?', [$now, $draft->thread]);
                }
                return $id;
            });
        } catch (\Throwable $error) {
            $this->app->media->remove($files);
            throw $error;
        }
    }
    public function delete(int $id, bool $filesOnly = false): void
    {
        $post = $this->post($id);
        $files = $this->app->db->all($filesOnly ? 'SELECT * FROM files WHERE post_id=?' : 'SELECT f.* FROM files f JOIN posts p ON p.id=f.post_id WHERE p.id=? OR p.thread_id=?', $filesOnly ? [$id] : [$id, $id]);
        $this->app->db->transaction(function () use ($id, $filesOnly, $post): void {
            $this->app->db->execute($filesOnly ? 'DELETE FROM files WHERE post_id=?' : 'DELETE FROM posts WHERE id=?', [$id]);
            if (!$filesOnly && $post['thread_id'] !== null) {
                $this->app->db->execute('UPDATE posts SET bumped=MAX(created,COALESCE((SELECT MAX(created) FROM posts r WHERE r.thread_id=?),created)) WHERE id=?', [$post['thread_id'], $post['thread_id']]);
            }
        });
        $this->app->media->remove($files);
    }
    public function deleteBoard(int $id): void
    {
        $files = $this->app->db->all('SELECT f.* FROM files f JOIN posts p ON p.id=f.post_id WHERE p.board_id=?', [$id]);
        $this->app->db->execute('DELETE FROM boards WHERE id=?', [$id]);
        $this->app->media->remove($files);
    }
}
