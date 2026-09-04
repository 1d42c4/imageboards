<?php

declare(strict_types=1);

namespace Newboard;

use Newboard\Controller\BoardController;
use Newboard\Controller\HomeController;
use Newboard\Controller\ModController;
use Newboard\Controller\PostController;
use Newboard\Controller\ReportController;
use Newboard\Http\Request;
use Newboard\Http\Response;
use Newboard\Http\Router;
use Newboard\Repository\BoardRepository;
use Newboard\Repository\ModRepository;
use Newboard\Repository\PostRepository;
use Newboard\Repository\ReportRepository;
use Newboard\Security\Csrf;
use Newboard\Security\PasswordHasher;
use Newboard\Security\Session;
use Newboard\Security\SessionCooldown;
use Newboard\Security\Tripcode;
use Newboard\Service\ArchiveService;
use Newboard\Service\ModAuthService;
use Newboard\Service\PostService;
use Newboard\Support\ImageProcessor;
use Newboard\Support\Markup;
use Newboard\Support\Migrator;
use Newboard\View\Renderer;

final class App
{
    private Config $config;
    private Database $db;
    private Session $session;
    private Renderer $view;

    public function __construct(string $root)
    {
        $this->config = Config::load($root);
        date_default_timezone_set($this->config->string('timezone', 'UTC'));

        $dbPath = $this->config->string('db.path');
        if (!is_file($dbPath)) {
            throw new \RuntimeException('Database missing. Run: php bin/install.php');
        }
        $this->db = new Database($dbPath);
        (new Migrator($this->db))->run();
        $this->session = new Session($this->config);
        $this->session->start();
        $this->view = new Renderer($this->config);
    }

    public function run(Request $request): Response
    {
        $this->sendSecurityHeaders();

        $boards = new BoardRepository($this->db);
        $this->view->setBoards($boards);
        $posts = new PostRepository($this->db);
        $mods = new ModRepository($this->db);
        $reports = new ReportRepository($this->db);
        $csrf = new Csrf($this->session);
        $passwords = new PasswordHasher();
        $tripcode = new Tripcode($this->metaSalt());
        $cooldown = new SessionCooldown($this->session, $this->config->int('abuse.session_cooldown', 15));
        $markup = new Markup();
        $images = new ImageProcessor($this->config);
        $archive = new ArchiveService($this->config, $posts, $mods);

        $postService = new PostService(
            $this->config,
            $boards,
            $posts,
            $markup,
            $images,
            $tripcode,
            $csrf,
            $cooldown,
            $archive,
        );
        $modAuth = new ModAuthService($mods, $passwords, $this->session);

        $home = new HomeController($boards, $this->view);
        $board = new BoardController($this->config, $boards, $posts, $this->view, $csrf, $modAuth);
        $post = new PostController($postService, $this->view);
        $mod = new ModController(
            $this->config,
            $modAuth,
            $boards,
            $posts,
            $mods,
            $reports,
            $csrf,
            $passwords,
            $markup,
            $images,
            $this->view,
            $archive,
        );
        $report = new ReportController($boards, $posts, $reports, $csrf, $this->view);

        $router = new Router();
        $router->add('GET', '/', $home->index(...));
        $router->add('POST', '/post', $post->create(...));

        $router->add('GET', '/mod/login', $mod->loginForm(...));
        $router->add('POST', '/mod/login', $mod->login(...));
        $router->add('GET', '/mod/logout', $mod->logout(...));
        $router->add('GET', '/mod', $mod->dashboard(...));
        $router->add('POST', '/mod/action', $mod->action(...));
        $router->add('GET', '/mod/new-board', $mod->newBoardForm(...));
        $router->add('POST', '/mod/new-board', $mod->newBoard(...));
        $router->add('GET', '/mod/edit/{board}', $mod->editBoardForm(...));
        $router->add('POST', '/mod/edit/{board}', $mod->editBoard(...));
        $router->add('GET', '/mod/users', $mod->users(...));
        $router->add('GET', '/mod/users/new', $mod->userNewForm(...));
        $router->add('POST', '/mod/users/new', $mod->userNew(...));
        $router->add('GET', '/mod/users/{id}', $mod->userEditForm(...));
        $router->add('POST', '/mod/users/{id}', $mod->userEdit(...));
        $router->add('GET', '/mod/log', $mod->modLog(...));
        $router->add('GET', '/mod/pending', $mod->pending(...));
        $router->add('GET', '/mod/reports', $mod->reports(...));
        $router->add('POST', '/mod/reports', $mod->reportAction(...));
        $router->add('GET', '/mod/recent', $mod->recent(...));
        $router->add('GET', '/mod/edit-post/{board}/{id}', $mod->editPostForm(...));
        $router->add('POST', '/mod/edit-post/{board}/{id}', $mod->editPost(...));

        $router->add('GET', '/report/{board}/{id}', $report->form(...));
        $router->add('POST', '/report/{board}/{id}', $report->submit(...));

        $router->add('GET', '/{board}', $board->index(...));
        $router->add('GET', '/{board}/catalog', $board->catalog(...));
        $router->add('GET', '/{board}/archive', $board->archiveIndex(...));
        $router->add('GET', '/{board}/archive/{id}', $board->archiveThread(...));
        $router->add('GET', '/{board}/res/{id}', $board->thread(...));

        try {
            return $router->dispatch($request);
        } catch (\Throwable $e) {
            $debug = (bool) $this->config->get('debug', false);
            $msg = $debug ? $e->getMessage() . "\n" . $e->getTraceAsString() : 'Internal error.';

            return Response::html($this->view->render('error', [
                'message' => $msg,
                'title' => 'Error',
            ]), 500);
        }
    }

    private function metaSalt(): string
    {
        $row = $this->db->fetchOne("SELECT value FROM meta WHERE key = 'trip_salt'");
        if ($row !== null) {
            return (string) $row['value'];
        }
        $salt = bin2hex(random_bytes(32));
        $this->db->query('INSERT INTO meta (key, value) VALUES (?, ?)', ['trip_salt', $salt]);

        return $salt;
    }

    private function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: interest-cohort=()');
    }
}
