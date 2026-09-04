<?php

declare(strict_types=1);

namespace Newboard\Controller;

use Newboard\Http\Request;
use Newboard\Http\Response;
use Newboard\Repository\BoardRepository;
use Newboard\View\Renderer;

final class HomeController
{
    public function __construct(
        private readonly BoardRepository $boards,
        private readonly Renderer $view,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $html = $this->view->render('home', [
            'boards' => $this->boards->all(),
            'title' => 'Boards',
        ]);

        return Response::html($html);
    }
}
