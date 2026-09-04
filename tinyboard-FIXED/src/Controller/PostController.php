<?php

declare(strict_types=1);

namespace Newboard\Controller;

use Newboard\Http\Request;
use Newboard\Http\Response;
use Newboard\Service\PostService;
use Newboard\View\Renderer;

final class PostController
{
    public function __construct(
        private readonly PostService $posts,
        private readonly Renderer $view,
    ) {
    }

    public function create(Request $request, array $params): Response
    {
        $file = $request->files['file'] ?? null;
        if (!is_array($file)) {
            $file = null;
        }

        $result = $this->posts->create($request->body, $file);
        if ($result['ok'] === false) {
            return Response::html($this->view->render('error', [
                'message' => $result['error'],
                'title' => 'Error',
            ]), 400);
        }

        $url = $this->view->url('/' . $result['board'] . '/res/' . $result['thread'] . '#p' . $result['id']);

        return Response::redirect($url);
    }
}
