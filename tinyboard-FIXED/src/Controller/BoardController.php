<?php

declare(strict_types=1);

namespace Newboard\Controller;

use Newboard\Config;
use Newboard\Http\Request;
use Newboard\Http\Response;
use Newboard\Repository\BoardRepository;
use Newboard\Repository\PostRepository;
use Newboard\Security\Csrf;
use Newboard\Service\ModAuthService;
use Newboard\View\Renderer;

final class BoardController
{
    public function __construct(
        private readonly Config $config,
        private readonly BoardRepository $boards,
        private readonly PostRepository $posts,
        private readonly Renderer $view,
        private readonly Csrf $csrf,
        private readonly ModAuthService $modAuth,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $uri = strtolower($params['board'] ?? '');
        $board = $this->boards->find($uri);
        if ($board === null) {
            return Response::html($this->view->render('error', ['message' => 'Board not found.']), 404);
        }

        $perPage = $this->config->int('board.threads_per_page', 10);
        $page = max(1, $request->int('page', 1));
        $total = $this->posts->countThreads($uri);
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;
        $threads = $this->posts->threads($uri, $perPage, $offset);
        $preview = $this->config->int('board.preview_replies', 5);

        $enriched = [];
        foreach ($threads as $t) {
            $tid = (int) $t['id'];
            $replyCount = $this->posts->countReplies($uri, $tid);
            $replies = $this->posts->replies($uri, $tid, $preview);
            $omitted = max(0, $replyCount - count($replies));
            $enriched[] = [
                'op' => $t,
                'replies' => $replies,
                'reply_count' => $replyCount,
                'omitted' => $omitted,
            ];
        }

        return Response::html($this->view->render('board/index', [
            'board' => $board,
            'threads' => $enriched,
            'page' => $page,
            'pages' => $pages,
            'csrf_field' => $this->csrf->field(),
            'mod' => $this->modAuth->user(),
            'active_page' => 'index',
            'title' => '/' . $board['uri'] . '/ - ' . $board['title'],
        ]));
    }

    public function thread(Request $request, array $params): Response
    {
        $uri = strtolower($params['board'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $board = $this->boards->find($uri);
        if ($board === null) {
            return Response::html($this->view->render('error', ['message' => 'Board not found.']), 404);
        }
        $op = $this->posts->find($uri, $id);
        if ($op === null || $op['thread_id'] !== null) {
            // maybe archived
            $arch = $this->posts->findArchivedOp($uri, $id);
            if ($arch !== null) {
                return Response::redirect($this->view->url('/' . $uri . '/archive/' . $id));
            }

            return Response::html($this->view->render('error', ['message' => 'Thread not found.']), 404);
        }
        $replies = $this->posts->replies($uri, $id);

        return Response::html($this->view->render('board/thread', [
            'board' => $board,
            'op' => $op,
            'replies' => $replies,
            'csrf_field' => $this->csrf->field(),
            'mod' => $this->modAuth->user(),
            'archived' => false,
            'active_page' => 'thread',
            'title' => '/' . $board['uri'] . '/ - ' . ($op['subject'] !== '' ? $op['subject'] : 'Thread #' . $id),
        ]));
    }

    public function catalog(Request $request, array $params): Response
    {
        $uri = strtolower($params['board'] ?? '');
        $board = $this->boards->find($uri);
        if ($board === null) {
            return Response::html($this->view->render('error', ['message' => 'Board not found.']), 404);
        }

        return Response::html($this->view->render('board/catalog', [
            'board' => $board,
            'threads' => $this->posts->catalog($uri),
            'mod' => $this->modAuth->user(),
            'active_page' => 'catalog',
            'title' => '/' . $board['uri'] . '/ - Catalog',
        ]));
    }

    public function archiveIndex(Request $request, array $params): Response
    {
        $uri = strtolower($params['board'] ?? '');
        $board = $this->boards->find($uri);
        if ($board === null) {
            return Response::html($this->view->render('error', ['message' => 'Board not found.']), 404);
        }

        $per = $this->config->int('archive.threads_per_page', 50);
        $page = max(1, $request->int('page', 1));
        $total = $this->posts->countArchived($uri);
        $pages = max(1, (int) ceil($total / max(1, $per)));
        if ($page > $pages) {
            $page = $pages;
        }
        $threads = $this->posts->archiveIndex($uri, $per, ($page - 1) * $per);

        // Snippets for list (like vichanBEST1)
        $list = [];
        foreach ($threads as $t) {
            $snippet = preg_replace('/\s+/', ' ', strip_tags((string) ($t['body'] ?? ''))) ?? '';
            $list[] = array_merge($t, [
                'snippet' => mb_substr($snippet, 0, 160),
            ]);
        }

        return Response::html($this->view->render('board/archive_index', [
            'board' => $board,
            'threads' => $list,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'active_page' => 'archive',
            'title' => '/' . $board['uri'] . '/ - Archive',
        ]));
    }

    public function archiveThread(Request $request, array $params): Response
    {
        $uri = strtolower($params['board'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $board = $this->boards->find($uri);
        if ($board === null) {
            return Response::html($this->view->render('error', ['message' => 'Board not found.']), 404);
        }
        $op = $this->posts->findArchivedOp($uri, $id);
        if ($op === null) {
            return Response::html($this->view->render('error', ['message' => 'Archived thread not found.']), 404);
        }
        $replies = $this->posts->replies($uri, $id, null, true);

        return Response::html($this->view->render('board/thread', [
            'board' => $board,
            'op' => $op,
            'replies' => $replies,
            'csrf_field' => $this->csrf->field(),
            'mod' => $this->modAuth->user(),
            'archived' => true,
            'active_page' => 'archive',
            'title' => '/' . $board['uri'] . '/ archive - #' . $id,
        ]));
    }
}
