<?php

declare(strict_types=1);

namespace Chessboard\Module;

use Chessboard\Controller\PublicController;
use Chessboard\Http\Router;

/**
 * Public board routes only.
 *
 * Keep visitor-facing endpoints here so public posting, reading, uploads, and
 * reports can be reviewed without opening moderator routing code.
 */
final readonly class PublicBoardModule
{
    public function __construct(private PublicController $controller)
    {
    }

    public function register(Router $router): void
    {
        $board = '(?P<board>[a-z0-9][a-z0-9-]{0,31})';
        $thread = '(?P<thread>[1-9][0-9]*)';
        $post = '(?P<post>[1-9][0-9]*)';

        $router->add(
            'GET',
            '#^/media/(?P<kind>original|thumb)/(?P<name>[a-f0-9]{32}\.(?:jpg|png|webp))$#',
            [$this->controller, 'media'],
        );
        $router->add('GET', '#^/$#', [$this->controller, 'home']);
        $router->add('GET', "#^/$board/thread/$thread/?$#", [$this->controller, 'thread']);
        $router->add('POST', "#^/$board/threads/?$#", [$this->controller, 'createThread']);
        $router->add('POST', "#^/$board/thread/$thread/replies/?$#", [$this->controller, 'createReply']);
        $router->add('POST', "#^/$board/post/$post/report/?$#", [$this->controller, 'reportPost']);
        $router->add('GET', "#^/$board/?$#", [$this->controller, 'board']);
    }
}
