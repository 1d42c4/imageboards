<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class PublicController
{
    public function __construct(private App $app)
    {
    }
    public function run(string $route): never
    {
        switch ($route) {
            case 'session':
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
                    throw new HttpError('Method not allowed.', 405);
                }
                $this->app->json(['csrf' => $this->app->security->csrf(), 'captcha' => $this->app->config->bool('captcha'), 'staff' => $this->app->security->staff() !== null]);
                // no break
            case 'captcha': $this->app->captcha->image();
                // no break
            case 'media': $this->app->media->serve(Input::text($_GET, 'id', 48), Input::text($_GET, 'thumb', 1) === '1');
                // no break
            case 'post': $this->create();
                // no break
            case 'action':
                if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
                    echo $this->app->view->page('Post actions', 'actions', ['id' => Input::id($_GET, 'post_id')]);
                    exit;
                }
                $this->action();
                // no break
            case 'read':
                $post = $this->app->boards->post(Input::id($_GET, 'post'));
                if ($post['slug'] !== Input::text($_GET, 'board', 24)) {
                    throw new HttpError('Post not found on this board.', 404);
                }
                $this->app->redirect($this->app->boards->postPath($post));
                // no break
            case 'compose':
                $board = $this->app->boards->board(Input::text($_GET, 'board', 24));
                $threadId = Input::id($_GET, 'thread');
                $thread = $threadId ? $this->app->boards->post($threadId) : null;
                if ($thread !== null && ($thread['thread_id'] !== null || (int) $thread['board_id'] !== (int) $board['id'])) {
                    throw new HttpError('Thread not found.', 404);
                }
                echo $this->app->view->page('Write a post', 'compose', compact('board', 'thread'), board: $board);
                exit;
            case 'search':
                $query = Input::text($_GET, 'q', 100);
                $slug = Input::text($_GET, 'board', 24);
                $posts = [];
                if ($query !== '') {
                    $this->app->security->throttle('search:' . $this->app->security->visitor(), 30, 60);
                    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
                    $posts = $this->app->db->all("SELECT p.*,b.slug FROM posts p JOIN boards b ON b.id=p.board_id WHERE (p.body LIKE ? ESCAPE '\' OR p.subject LIKE ? ESCAPE '\') AND (?='' OR b.slug=?) ORDER BY p.id DESC LIMIT 100", [$like, $like, $slug, $slug]);
                }
                echo $this->app->view->page('Search', 'search', compact('query', 'posts', 'slug'));
                exit;
            default: throw new HttpError('Page not found.', 404);
        }
    }
    private function create(): never
    {
        $this->app->security->requirePost();
        if (Input::text($_POST, 'website', 200) !== '') {
            throw new HttpError('The form could not be submitted.');
        }
        $visitor = $this->app->security->visitor();
        $this->app->security->throttle('post:visitor:' . $visitor, 1, $this->app->config->int('post_interval'));
        $this->app->security->throttle('post:global', $this->app->config->int('posts_per_minute'), 60);
        $this->app->captcha->verify(Input::text($_POST, 'captcha', 10));
        $draft = PostDraft::from($_POST);
        $upload = $_FILES['files'] ?? [];
        if (!is_array($upload)) {
            throw new HttpError('Invalid upload.');
        }
        $id = $this->app->builder->change(fn (): int => $this->app->boards->create($draft, $upload));
        $path = $this->app->boards->postPath($this->app->boards->post($id));
        if (isset($_POST['json_response'])) {
            $this->app->json(['success' => true, 'id' => $id, 'redirect' => $this->app->config->url($path)]);
        }
        $this->app->redirect($path);
    }
    private function action(): never
    {
        $this->app->security->requirePost();
        $this->app->security->throttle('action:' . $this->app->security->visitor(), 15, 60);
        $this->app->security->throttle('action:global', 120, 60);
        $id = Input::id($_POST, 'post_id');
        $post = $this->app->boards->post($id);
        $action = Input::text($_POST, 'action', 20);
        if ($action === 'report') {
            $reason = Input::text($_POST, 'reason', 500);
            if (mb_strlen($reason) < 3) {
                throw new HttpError('Please give a short reason for your report.');
            }
            $this->app->db->transaction(function () use ($id, $reason): void {
                $count = $this->app->db->one('SELECT COUNT(*) AS n FROM reports WHERE post_id=?', [$id]);
                if ((int) ($count['n'] ?? 0) >= 10) {
                    throw new HttpError('This post has already been reported.');
                }
                $this->app->db->execute('INSERT INTO reports(post_id,reason,created) VALUES(?,?,?)', [$id, $reason, time()]);
            });
        } elseif (in_array($action, ['delete', 'files'], true)) {
            $password = Input::text($_POST, 'password', 128);
            if ($post['deletion_hash'] === '' || !Security::verifyPassword($password, (string) $post['deletion_hash'])) {
                throw new HttpError('Incorrect deletion password.', 403);
            }
            $this->app->builder->change(fn () => $this->app->boards->delete($id, $action === 'files'));
        } else {
            throw new HttpError('Unknown post action.');
        }
        if (isset($_POST['json_response'])) {
            $this->app->json(['success' => true, 'message' => $action === 'report' ? 'Report submitted.' : 'Post updated.', 'redirect' => $this->app->config->url($post['slug'] . '/index.html')]);
        }
        $this->app->redirect($post['slug'] . '/index.html');
    }
}
