<?php

declare(strict_types=1);

namespace Chessboard;

use Chessboard\Controller\ModeratorController;
use Chessboard\Controller\PublicController;
use Chessboard\Http\HttpException;
use Chessboard\Http\Request;
use Chessboard\Http\Response;
use Chessboard\Http\Router;
use Chessboard\Module\ModeratorModule;
use Chessboard\Module\PublicBoardModule;
use Chessboard\Repository\BoardRepository;
use Chessboard\Repository\ModerationRepository;
use Chessboard\Repository\PostRepository;
use Chessboard\Security\Captcha;
use Chessboard\Security\Csrf;
use Chessboard\Security\ModeratorAuth;
use Chessboard\Service\Markup;
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
        $csrf = new Csrf();
        $captcha = new Captcha();
        $auth = new ModeratorAuth($moderation);
        $markup = new Markup();
        $uploads = new UploadService($config);
        $this->view = new View($config, $csrf, $captcha, $auth, $boards, $markup);

        $public = new PublicController(
            $config,
            $boards,
            $posts,
            $moderation,
            $csrf,
            $captcha,
            $uploads,
            $this->view,
        );
        $moderator = new ModeratorController(
            $config,
            $boards,
            $posts,
            $moderation,
            $auth,
            $csrf,
            $uploads,
            $this->view,
        );

        $this->router = new Router();
        (new ModeratorModule($moderator))->register($this->router);
        (new PublicBoardModule($public))->register($this->router);
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

