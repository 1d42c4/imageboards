<?php

declare(strict_types=1);

namespace Chessboard\Module;

use Chessboard\Controller\ModeratorController;
use Chessboard\Http\Router;

/**
 * Moderator routes only.
 *
 * Authentication, reports, board management, and post actions stay behind
 * this small routing boundary so future changes do not disturb public routes.
 */
final readonly class ModeratorModule
{
    public function __construct(private ModeratorController $controller)
    {
    }

    public function register(Router $router): void
    {
        $board = '(?P<board>[a-z0-9][a-z0-9-]{0,31})';
        $thread = '(?P<thread>[1-9][0-9]*)';
        $post = '(?P<post>[1-9][0-9]*)';

        $router->add('GET', '#^/mod/login/?$#', [$this->controller, 'loginForm']);
        $router->add('POST', '#^/mod/login/?$#', [$this->controller, 'login']);
        $router->add('POST', '#^/mod/logout/?$#', [$this->controller, 'logout']);
        $router->add('GET', '#^/mod/?$#', [$this->controller, 'dashboard']);
        $router->add(
            'POST',
            '#^/mod/reports/(?P<report>[1-9][0-9]*)/dismiss/?$#',
            [$this->controller, 'dismissReport'],
        );
        $router->add('GET', '#^/mod/boards/?$#', [$this->controller, 'boards']);
        $router->add('POST', '#^/mod/boards/?$#', [$this->controller, 'createBoard']);
        $router->add('POST', "#^/mod/$board/post/$post/edit/?$#", [$this->controller, 'editPost']);
        $router->add('POST', "#^/mod/$board/post/$post/delete/?$#", [$this->controller, 'deletePost']);
        $router->add('POST', "#^/mod/$board/thread/$thread/lock/?$#", [$this->controller, 'setThreadLock']);
        $router->add('POST', "#^/mod/$board/thread/$thread/sticky/?$#", [$this->controller, 'setThreadSticky']);
    }
}
