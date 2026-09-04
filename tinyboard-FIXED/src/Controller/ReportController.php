<?php

declare(strict_types=1);

namespace Newboard\Controller;

use Newboard\Http\Request;
use Newboard\Http\Response;
use Newboard\Repository\BoardRepository;
use Newboard\Repository\PostRepository;
use Newboard\Repository\ReportRepository;
use Newboard\Security\Csrf;
use Newboard\View\Renderer;

/**
 * Public report form — no IP stored.
 */
final class ReportController
{
    public function __construct(
        private readonly BoardRepository $boards,
        private readonly PostRepository $posts,
        private readonly ReportRepository $reports,
        private readonly Csrf $csrf,
        private readonly Renderer $view,
    ) {
    }

    public function form(Request $request, array $params): Response
    {
        $boardUri = strtolower($params['board'] ?? '');
        $postId = (int) ($params['id'] ?? 0);
        $board = $this->boards->find($boardUri);
        $post = $this->posts->findAny($boardUri, $postId);
        if ($board === null || $post === null || (int) ($post['pending'] ?? 0) === 1) {
            return Response::html($this->view->render('error', [
                'message' => 'Post not found.',
                'title' => 'Error',
            ]), 404);
        }

        return Response::html($this->view->render('report', [
            'board' => $board,
            'post' => $post,
            'csrf_field' => $this->csrf->field(),
            'error' => null,
            'title' => 'Report post #' . $postId,
        ]));
    }

    public function submit(Request $request, array $params): Response
    {
        if (!$this->csrf->validate($request->string('csrf'))) {
            return Response::html($this->view->render('error', [
                'message' => 'Invalid token.',
                'title' => 'Error',
            ]), 400);
        }
        // honeypot
        if ($request->string('website') !== '') {
            return Response::redirect($this->view->url('/'));
        }

        $boardUri = strtolower($params['board'] ?? '');
        $postId = (int) ($params['id'] ?? 0);
        $board = $this->boards->find($boardUri);
        $post = $this->posts->findAny($boardUri, $postId);
        if ($board === null || $post === null || (int) ($post['pending'] ?? 0) === 1) {
            return Response::html($this->view->render('error', [
                'message' => 'Post not found.',
                'title' => 'Error',
            ]), 404);
        }

        $reason = $request->string('reason');
        if ($reason === '') {
            return Response::html($this->view->render('report', [
                'board' => $board,
                'post' => $post,
                'csrf_field' => $this->csrf->field(),
                'error' => 'Please enter a reason.',
                'title' => 'Report post #' . $postId,
            ]), 400);
        }

        $this->reports->create($boardUri, $postId, $reason);

        $thread = $post['thread_id'] !== null ? (int) $post['thread_id'] : $postId;

        return Response::html($this->view->render('error', [
            'message' => 'Report submitted. Thank you. (No IP was recorded.)',
            'title' => 'Reported',
        ]));
    }
}
