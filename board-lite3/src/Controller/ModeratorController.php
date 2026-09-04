<?php

declare(strict_types=1);

namespace Chessboard\Controller;

use Chessboard\Config;
use Chessboard\Http\HttpException;
use Chessboard\Http\Request;
use Chessboard\Http\Response;
use Chessboard\Repository\BoardRepository;
use Chessboard\Repository\ModerationRepository;
use Chessboard\Repository\PostRepository;
use Chessboard\Security\Csrf;
use Chessboard\Security\ModeratorAuth;
use Chessboard\Security\Session;
use Chessboard\Service\UploadService;
use Chessboard\View;

final readonly class ModeratorController
{
    public function __construct(
        private Config $config,
        private BoardRepository $boards,
        private PostRepository $posts,
        private ModerationRepository $moderation,
        private ModeratorAuth $auth,
        private Csrf $csrf,
        private UploadService $uploads,
        private View $view,
    ) {
    }

    public function loginForm(Request $request, array $parameters): Response
    {
        if ($this->auth->user() !== null) {
            return Response::redirect($this->view->url('/mod'));
        }

        return Response::html($this->view->render('mod/login', ['title' => 'Moderator login']));
    }

    public function login(Request $request, array $parameters): Response
    {
        $this->csrf->validate($request->input('_token'));
        if (!$this->auth->attempt($request->input('username'), $request->input('password'))) {
            throw new HttpException(401, 'Invalid moderator credentials.');
        }

        Session::flash('success', 'Welcome back.');

        return Response::redirect($this->view->url('/mod'));
    }

    public function logout(Request $request, array $parameters): Response
    {
        $this->csrf->validate($request->input('_token'));
        $this->auth->logout();
        Session::flash('success', 'You have been signed out.');

        return Response::redirect($this->view->url('/'));
    }

    public function dashboard(Request $request, array $parameters): Response
    {
        $this->auth->requireUser();

        return Response::html($this->view->render('mod/dashboard', [
            'title' => 'Moderator dashboard',
            'reports' => $this->moderation->openReports(),
            'counts' => $this->moderation->counts(),
            'logEntries' => $this->moderation->recentLog(),
        ]));
    }

    public function dismissReport(Request $request, array $parameters): Response
    {
        $moderator = $this->requireWrite($request);
        $reportId = (int) $parameters['report'];
        $this->moderation->dismissReport($reportId, (int) $moderator['id']);
        $this->moderation->log((int) $moderator['id'], 'dismiss-report', details: 'Report #' . $reportId);
        Session::flash('success', 'Report dismissed.');

        return Response::redirect($this->view->url('/mod'));
    }

    public function deletePost(Request $request, array $parameters): Response
    {
        $moderator = $this->requireWrite($request);
        $board = $this->requireBoard($parameters['board']);
        $post = $this->requirePost($board, (int) $parameters['post']);
        $attachment = $this->posts->softDelete((int) $post['id']);
        $this->uploads->remove($attachment);
        $this->moderation->dismissReportsForPost((int) $post['id'], (int) $moderator['id']);
        $this->moderation->log(
            (int) $moderator['id'],
            'delete-post',
            (int) $board['id'],
            (int) $post['id'],
            sprintf('/%s/ No.%d', $board['slug'], $post['post_no']),
        );
        Session::flash('success', 'Post removed.');

        return Response::redirect(
            $this->view->postUrl($board['slug'], $post['thread_no'], $post['thread_no']),
        );
    }

    public function setThreadLock(Request $request, array $parameters): Response
    {
        return $this->setThreadFlag($request, $parameters, 'locked');
    }

    public function setThreadSticky(Request $request, array $parameters): Response
    {
        return $this->setThreadFlag($request, $parameters, 'sticky');
    }

    public function boards(Request $request, array $parameters): Response
    {
        $moderator = $this->auth->requireUser();
        if ($moderator['role'] !== 'admin') {
            throw new HttpException(403, 'Administrator access required.');
        }

        return Response::html($this->view->render('mod/boards', [
            'title' => 'Boards',
            'boards' => $this->boards->all(),
        ]));
    }

    public function createBoard(Request $request, array $parameters): Response
    {
        $moderator = $this->requireWrite($request);
        if ($moderator['role'] !== 'admin') {
            throw new HttpException(403, 'Administrator access required.');
        }

        $slug = strtolower($request->input('slug'));
        $title = $request->input('title');
        $description = $request->input('description');
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,31}$/', $slug) ||
            in_array($slug, ['mod', 'media', 'assets'], true)) {
            throw new HttpException(422, 'Use a short board address made from letters, numbers, and hyphens.');
        }
        if ($title === '' || mb_strlen($title) > 80 || mb_strlen($description) > 280) {
            throw new HttpException(422, 'Give the board a title and a concise description.');
        }
        if ($this->boards->exists($slug)) {
            throw new HttpException(409, 'That board address is already in use.');
        }

        $board = $this->boards->create($slug, $title, $description);
        $this->moderation->log(
            (int) $moderator['id'],
            'create-board',
            (int) $board['id'],
            details: '/' . $slug . '/',
        );
        Session::flash('success', 'Board created.');

        return Response::redirect($this->view->url('/mod/boards'));
    }

    private function setThreadFlag(
        Request $request,
        array $parameters,
        string $flag,
    ): Response {
        $moderator = $this->requireWrite($request);
        $board = $this->requireBoard($parameters['board']);
        $enabled = $request->input('enabled') === '1';
        $post = $this->posts->setThreadFlag(
            (int) $board['id'],
            (int) $parameters['thread'],
            $flag,
            $enabled,
        );
        $this->moderation->log(
            (int) $moderator['id'],
            ($enabled ? 'enable-' : 'disable-') . $flag,
            (int) $board['id'],
            (int) $post['id'],
        );
        Session::flash('success', ucfirst($flag) . ' setting updated.');

        return Response::redirect(
            $this->view->postUrl($board['slug'], $post['post_no'], $post['post_no']),
        );
    }

    private function requireWrite(Request $request): array
    {
        $moderator = $this->auth->requireUser();
        $this->csrf->validate($request->input('_token'));

        return $moderator;
    }

    private function requireBoard(string $slug): array
    {
        $board = $this->boards->find(strtolower($slug));
        if ($board === null) {
            throw new HttpException(404, 'Board not found.');
        }

        return $board;
    }

    private function requirePost(array $board, int $postNo): array
    {
        $post = $this->posts->find((int) $board['id'], $postNo);
        if ($post === null) {
            throw new HttpException(404, 'Post not found.');
        }

        return $post;
    }
}
