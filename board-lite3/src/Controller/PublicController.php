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
use Chessboard\Security\Session;
use Chessboard\Service\Markup;
use Chessboard\Service\UploadService;
use Chessboard\View;

final readonly class PublicController
{
    public function __construct(
        private Config $config,
        private BoardRepository $boards,
        private PostRepository $posts,
        private ModerationRepository $moderation,
        private Csrf $csrf,
        private Markup $markup,
        private UploadService $uploads,
        private View $view,
    ) {
    }

    public function home(Request $request, array $parameters): Response
    {
        return Response::html($this->view->render('home', [
            'title' => 'Boards',
            'boards' => $this->boards->all(),
        ]));
    }

    public function board(Request $request, array $parameters): Response
    {
        $board = $this->requireBoard($parameters['board']);
        $page = max(1, $request->queryInt('page'));
        $result = $this->posts->boardThreads(
            (int) $board['id'],
            $page,
            $this->config->requireInt('threads_per_page'),
            $this->config->requireInt('recent_replies'),
        );

        if ($page > $result['pages']) {
            throw new HttpException(404, 'Board page not found.');
        }

        return Response::html($this->view->render('board', [
            'title' => '/' . $board['slug'] . '/ — ' . $board['title'],
            'board' => $board,
            'threads' => $result['threads'],
            'page' => $result['page'],
            'pages' => $result['pages'],
        ]));
    }

    public function thread(Request $request, array $parameters): Response
    {
        $board = $this->requireBoard($parameters['board']);
        $threadNo = (int) $parameters['thread'];
        $thread = $this->posts->thread((int) $board['id'], $threadNo);
        if ($thread === null) {
            throw new HttpException(404, 'Thread not found.');
        }

        return Response::html($this->view->render('thread', [
            'title' => sprintf('/%s/ — Thread %d', $board['slug'], $threadNo),
            'board' => $board,
            'thread' => $thread,
        ]));
    }

    public function locatePost(Request $request, array $parameters): Response
    {
        $board = $this->requireBoard($parameters['board']);
        $post = $this->posts->find((int) $board['id'], (int) $parameters['post']);
        if ($post === null) {
            throw new HttpException(404, 'Post not found.');
        }

        return Response::redirect(
            $this->view->postUrl($board['slug'], $post['thread_no'], $post['post_no']),
            302,
        );
    }

    public function createThread(Request $request, array $parameters): Response
    {
        $board = $this->requireBoard($parameters['board']);
        $this->guardWrite($request);
        $input = $this->postInput($request);
        $attachment = $this->uploads->process($request->file('image'));
        if ($input['body'] === '' && $attachment === null) {
            throw new HttpException(422, 'A post needs text or an image.');
        }

        try {
            $post = $this->posts->create(
                $board,
                null,
                $input['subject'],
                $input['name'],
                $input['body'],
                $attachment,
                $this->markup->references($input['body'], $board['slug']),
            );
        } catch (\Throwable $error) {
            $this->uploads->remove($attachment);
            throw $error;
        }

        Session::flash('success', 'Thread created.');

        return Response::redirect(
            $this->view->postUrl($board['slug'], $post['thread_no'], $post['post_no']),
        );
    }

    public function createReply(Request $request, array $parameters): Response
    {
        $board = $this->requireBoard($parameters['board']);
        $this->guardWrite($request);
        $input = $this->postInput($request);
        $attachment = $this->uploads->process($request->file('image'));
        if ($input['body'] === '' && $attachment === null) {
            throw new HttpException(422, 'A reply needs text or an image.');
        }

        try {
            $post = $this->posts->create(
                $board,
                (int) $parameters['thread'],
                $input['subject'],
                $input['name'],
                $input['body'],
                $attachment,
                $this->markup->references($input['body'], $board['slug']),
            );
        } catch (\Throwable $error) {
            $this->uploads->remove($attachment);
            throw $error;
        }

        Session::flash('success', 'Reply posted.');

        return Response::redirect(
            $this->view->postUrl($board['slug'], $post['thread_no'], $post['post_no']),
        );
    }

    public function reportPost(Request $request, array $parameters): Response
    {
        $this->csrf->validate($request->input('_token'));
        if ($request->input('website') !== '') {
            throw new HttpException(400, 'Unable to process this request.');
        }

        $board = $this->requireBoard($parameters['board']);
        $post = $this->posts->find((int) $board['id'], (int) $parameters['post']);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new HttpException(404, 'Post not found.');
        }

        $reason = $request->input('reason');
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new HttpException(422, 'Give a report reason of 500 characters or fewer.');
        }

        $this->moderation->createReport((int) $post['id'], $reason);
        Session::flash('success', 'Report submitted for moderator review.');

        return Response::redirect(
            $this->view->postUrl($board['slug'], $post['thread_no'], $post['post_no']),
        );
    }

    public function media(Request $request, array $parameters): Response
    {
        $file = $this->uploads->resolve($parameters['kind'], $parameters['name']);

        return Response::file($file['path'], $file['mime']);
    }

    private function requireBoard(string $slug): array
    {
        $board = $this->boards->find(strtolower($slug));
        if ($board === null) {
            throw new HttpException(404, 'Board not found.');
        }

        return $board;
    }

    private function guardWrite(Request $request): void
    {
        $this->csrf->validate($request->input('_token'));
        if ($request->input('website') !== '') {
            throw new HttpException(400, 'Unable to process this request.');
        }
    }

    private function postInput(Request $request): array
    {
        $body = $request->input('body');
        $subject = $request->input('subject');
        $name = $request->input('name', 'Anonymous');

        if (mb_strlen($body) > $this->config->requireInt('max_body_length')) {
            throw new HttpException(422, 'The post body is too long.');
        }
        if (mb_strlen($subject) > 120) {
            throw new HttpException(422, 'The subject is too long.');
        }
        if (mb_strlen($name) > 60) {
            throw new HttpException(422, 'The display name is too long.');
        }
        return [
            'body' => $body,
            'subject' => $subject,
            'name' => $name === '' ? 'Anonymous' : $name,
        ];
    }
}
