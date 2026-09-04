<?php

declare(strict_types=1);

namespace Chessboard;

use Chessboard\Controller\ModeratorController;
use Chessboard\Controller\PublicController;
use Chessboard\Http\HttpException;
use Chessboard\Http\Request;
use Chessboard\Http\Response;
use Chessboard\Http\Router;
use Chessboard\Repository\BoardRepository;
use Chessboard\Repository\ModerationRepository;
use Chessboard\Repository\PostRepository;
use Chessboard\Security\ClientIdentity;
use Chessboard\Security\Csrf;
use Chessboard\Security\ModeratorAuth;
use Chessboard\Service\Markup;
use Chessboard\Service\RateLimiter;
use Chessboard\Service\UploadService;
use Throwable;

final class Application
{
    private Router $router;
    private View $view;

    public function __construct(private readonly Config $config)
    {
        $database = new Database($config);
        $boards = new BoardRepository($database);
        $posts = new PostRepository($database);
        $moderation = new ModerationRepository($database);
        $identity = new ClientIdentity($config);
        $csrf = new Csrf();
        $auth = new ModeratorAuth($moderation);
        $rateLimiter = new RateLimiter($database);
        $markup = new Markup($config);
        $uploads = new UploadService($config);
        $this->view = new View($config, $csrf, $auth, $boards, $markup);

        $public = new PublicController(
            $config,
            $boards,
            $posts,
            $moderation,
            $identity,
            $csrf,
            $rateLimiter,
            $markup,
            $uploads,
            $this->view,
        );
        $moderator = new ModeratorController(
            $config,
            $boards,
            $posts,
            $moderation,
            $identity,
            $auth,
            $csrf,
            $rateLimiter,
            $uploads,
            $this->view,
        );

        $this->router = new Router();
        $this->routes($public, $moderator);
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (HttpException $error) {
            $response = Response::html($this->view->render('error', [
                'title' => $this->statusTitle($error->status),
                'message' => $error->getMessage(),
            ], $error->status), $error->status);
        } catch (Throwable $error) {
            $this->log($error);
            $message = (bool) $this->config->get('debug')
                ? $error::class . ': ' . $error->getMessage()
                : 'Something went wrong while processing the request.';
            $response = Response::html($this->view->render('error', [
                'title' => 'Server error',
                'message' => $message,
            ], 500), 500);
        }

        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'same-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; " .
                "base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
            );
    }

    private function routes(PublicController $public, ModeratorController $moderator): void
    {
        $board = '(?P<board>[a-z0-9][a-z0-9-]{0,31})';
        $thread = '(?P<thread>[1-9][0-9]*)';
        $post = '(?P<post>[1-9][0-9]*)';

        $this->router->add('GET', '#^/mod/login/?$#', [$moderator, 'loginForm']);
        $this->router->add('POST', '#^/mod/login/?$#', [$moderator, 'login']);
        $this->router->add('POST', '#^/mod/logout/?$#', [$moderator, 'logout']);
        $this->router->add('GET', '#^/mod/?$#', [$moderator, 'dashboard']);
        $this->router->add('POST', '#^/mod/reports/(?P<report>[1-9][0-9]*)/dismiss/?$#', [$moderator, 'dismissReport']);
        $this->router->add('GET', '#^/mod/bans/?$#', [$moderator, 'bans']);
        $this->router->add('POST', '#^/mod/bans/?$#', [$moderator, 'createBan']);
        $this->router->add('POST', '#^/mod/bans/(?P<ban>[1-9][0-9]*)/delete/?$#', [$moderator, 'deleteBan']);
        $this->router->add('GET', '#^/mod/boards/?$#', [$moderator, 'boards']);
        $this->router->add('POST', '#^/mod/boards/?$#', [$moderator, 'createBoard']);
        $this->router->add('POST', "#^/mod/$board/post/$post/delete/?$#", [$moderator, 'deletePost']);
        $this->router->add('POST', "#^/mod/$board/post/$post/ban/?$#", [$moderator, 'banPost']);
        $this->router->add('POST', "#^/mod/$board/thread/$thread/lock/?$#", [$moderator, 'setThreadLock']);
        $this->router->add('POST', "#^/mod/$board/thread/$thread/sticky/?$#", [$moderator, 'setThreadSticky']);

        $this->router->add('GET', '#^/media/(?P<kind>original|thumb)/(?P<name>[a-f0-9]{32}\.(?:jpg|png|webp))$#', [$public, 'media']);
        $this->router->add('GET', '#^/$#', [$public, 'home']);
        $this->router->add('GET', "#^/$board/post/$post/?$#", [$public, 'locatePost']);
        $this->router->add('GET', "#^/$board/thread/$thread/?$#", [$public, 'thread']);
        $this->router->add('POST', "#^/$board/threads/?$#", [$public, 'createThread']);
        $this->router->add('POST', "#^/$board/thread/$thread/replies/?$#", [$public, 'createReply']);
        $this->router->add('POST', "#^/$board/post/$post/delete/?$#", [$public, 'deletePost']);
        $this->router->add('POST', "#^/$board/post/$post/report/?$#", [$public, 'reportPost']);
        $this->router->add('GET', "#^/$board/?$#", [$public, 'board']);
    }

    private function statusTitle(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Sign in required',
            403 => 'Access denied',
            404 => 'Not found',
            405 => 'Method not allowed',
            409 => 'Conflict',
            410 => 'Gone',
            419 => 'Form expired',
            422 => 'Please check your input',
            423 => 'Thread locked',
            429 => 'Please slow down',
            default => 'Request error',
        };
    }

    private function log(Throwable $error): void
    {
        $path = $this->config->requireString('log_path');
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0770, true);
        }

        $entry = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            date(DATE_ATOM),
            $error::class,
            $error->getMessage(),
            $error->getFile(),
            $error->getLine(),
            $error->getTraceAsString(),
        );
        @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
    }
}

