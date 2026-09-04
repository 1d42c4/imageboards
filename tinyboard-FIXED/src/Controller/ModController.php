<?php

declare(strict_types=1);

namespace Newboard\Controller;

use Newboard\Config;
use Newboard\Http\Request;
use Newboard\Http\Response;
use Newboard\Repository\BoardRepository;
use Newboard\Repository\ModRepository;
use Newboard\Repository\PostRepository;
use Newboard\Repository\ReportRepository;
use Newboard\Security\Csrf;
use Newboard\Security\PasswordHasher;
use Newboard\Service\ArchiveService;
use Newboard\Service\ModAuthService;
use Newboard\Support\ImageProcessor;
use Newboard\Support\Markup;
use Newboard\View\Renderer;

/**
 * Moderator panel — feature parity with vichanBEST1 mod tools (no IP features).
 */
final class ModController
{
    public function __construct(
        private readonly Config $config,
        private readonly ModAuthService $auth,
        private readonly BoardRepository $boards,
        private readonly PostRepository $posts,
        private readonly ModRepository $mods,
        private readonly ReportRepository $reports,
        private readonly Csrf $csrf,
        private readonly PasswordHasher $passwords,
        private readonly Markup $markup,
        private readonly ImageProcessor $images,
        private readonly Renderer $view,
        private readonly ?ArchiveService $archive = null,
    ) {
    }

    // ── Auth ──────────────────────────────────────────────────────────

    public function loginForm(Request $request, array $params): Response
    {
        if ($this->auth->check()) {
            return Response::redirect($this->view->url('/mod'));
        }

        return $this->html('mod/login', [
            'csrf_field' => $this->csrf->field(),
            'error' => null,
            'title' => 'Mod login',
        ]);
    }

    public function login(Request $request, array $params): Response
    {
        if (!$this->csrf->validate($request->string('csrf'))) {
            return $this->html('mod/login', [
                'csrf_field' => $this->csrf->field(),
                'error' => 'Invalid token.',
                'title' => 'Mod login',
            ], 400);
        }
        if (!$this->auth->attempt($request->string('username'), (string) ($request->body['password'] ?? ''))) {
            return $this->html('mod/login', [
                'csrf_field' => $this->csrf->field(),
                'error' => 'Invalid credentials.',
                'title' => 'Mod login',
            ], 401);
        }

        return Response::redirect($this->view->url('/mod'));
    }

    public function logout(Request $request, array $params): Response
    {
        $this->auth->logout();

        return Response::redirect($this->view->url('/mod/login'));
    }

    // ── Dashboard ─────────────────────────────────────────────────────

    public function dashboard(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }

        return $this->html('mod/dashboard', [
            'mod' => $this->auth->user(),
            'boards' => $this->boards->all(),
            'pending_count' => $this->posts->countPending(),
            'report_count' => $this->reports->count(),
            'pending' => $this->posts->pending(10),
            'log' => $this->mods->recentLog(15),
            'csrf_field' => $this->csrf->field(),
            'title' => 'Dashboard',
        ]);
    }

    // ── Unified POST actions (thread tools) ───────────────────────────

    public function action(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        if (!$this->csrf->validate($request->string('csrf'))) {
            return $this->error('Invalid token.', 400);
        }

        $mod = $this->auth->user();
        $action = $request->string('do');
        $board = strtolower($request->string('board'));
        $id = $request->int('id');

        match ($action) {
            'delete' => $this->posts->delete($board, $id),
            'sticky' => $this->posts->setSticky($board, $id, true),
            'unsticky' => $this->posts->setSticky($board, $id, false),
            'lock' => $this->posts->setLocked($board, $id, true),
            'unlock' => $this->posts->setLocked($board, $id, false),
            'bumplock' => $this->posts->setBumplock($board, $id, true),
            'unbumplock' => $this->posts->setBumplock($board, $id, false),
            'approve' => $this->posts->approve($board, $id),
            'reject' => $this->posts->rejectPending($board, $id),
            'archive' => $this->archive !== null
                ? $this->archive->archiveThread($board, $id)
                : $this->posts->archiveThread($board, $id),
            'deletefile' => $this->deleteFile($board, $id),
            default => null,
        };

        $this->writeLog($mod, $board !== '' ? $board : null, $action, 'post #' . $id);

        $back = $request->string('back', $this->view->url('/mod'));

        return Response::redirect($this->safeBack($back));
    }

    // ── Boards ────────────────────────────────────────────────────────

    public function newBoardForm(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }

        return $this->html('mod/board_form', [
            'new' => true,
            'board' => null,
            'csrf_field' => $this->csrf->field(),
            'error' => null,
            'title' => 'New board',
        ]);
    }

    public function newBoard(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        if (!$this->csrf->validate($request->string('csrf'))) {
            return $this->error('Invalid token.', 400);
        }

        $uri = strtolower(trim($request->string('uri')));
        $title = $request->string('title');
        $subtitle = $request->string('subtitle');

        if ($uri === '' || $title === '') {
            return $this->html('mod/board_form', [
                'new' => true,
                'board' => null,
                'csrf_field' => $this->csrf->field(),
                'error' => 'URI and title are required.',
                'title' => 'New board',
            ], 400);
        }
        if (!preg_match('/^[a-z0-9_-]{1,30}$/', $uri) || in_array($uri, ['mod', 'post', 'uploads', 'assets', 'report'], true)) {
            return $this->html('mod/board_form', [
                'new' => true,
                'board' => null,
                'csrf_field' => $this->csrf->field(),
                'error' => 'Invalid or reserved board URI.',
                'title' => 'New board',
            ], 400);
        }
        if ($this->boards->find($uri) !== null) {
            return $this->html('mod/board_form', [
                'new' => true,
                'board' => null,
                'csrf_field' => $this->csrf->field(),
                'error' => 'Board already exists.',
                'title' => 'New board',
            ], 400);
        }

        $this->boards->create($uri, $title, $subtitle);
        $this->writeLog($this->auth->user(), $uri, 'new_board', $title);

        return Response::redirect($this->view->url('/mod'));
    }

    public function editBoardForm(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        $board = $this->boards->find(strtolower($params['board'] ?? ''));
        if ($board === null) {
            return $this->error('Board not found.', 404);
        }

        return $this->html('mod/board_form', [
            'new' => false,
            'board' => $board,
            'csrf_field' => $this->csrf->field(),
            'error' => null,
            'title' => 'Edit /' . $board['uri'] . '/',
        ]);
    }

    public function editBoard(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        if (!$this->csrf->validate($request->string('csrf'))) {
            return $this->error('Invalid token.', 400);
        }
        $uri = strtolower($params['board'] ?? '');
        $board = $this->boards->find($uri);
        if ($board === null) {
            return $this->error('Board not found.', 404);
        }

        if (!empty($request->body['delete_board'])) {
            $this->boards->delete($uri);
            $this->writeLog($this->auth->user(), $uri, 'delete_board', '');
            $this->removeBoardUploads($uri);

            return Response::redirect($this->view->url('/mod'));
        }

        $requirePassword = !empty($request->body['require_password']) ? 1 : 0;
        $hash = $board['password_hash'] ?? null;
        if ($requirePassword === 0) {
            $hash = null;
        } elseif ($request->string('board_password') !== '') {
            $hash = $this->passwords->hash($request->string('board_password'));
        } elseif (empty($hash)) {
            return $this->html('mod/board_form', [
                'new' => false,
                'board' => $board,
                'csrf_field' => $this->csrf->field(),
                'error' => 'Enter a board password or turn the option off.',
                'title' => 'Edit /' . $uri . '/',
            ], 400);
        }

        $this->boards->update($uri, [
            'title' => $request->string('title') !== '' ? $request->string('title') : (string) $board['title'],
            'subtitle' => $request->string('subtitle'),
            'require_approval' => !empty($request->body['require_approval']) ? 1 : 0,
            'require_password' => $requirePassword,
            'password_hash' => $hash,
            'force_image_op' => !empty($request->body['force_image_op']) ? 1 : 0,
        ]);
        $this->writeLog($this->auth->user(), $uri, 'edit_board', $request->string('title'));

        return Response::redirect($this->view->url('/mod'));
    }

    // ── Users ─────────────────────────────────────────────────────────

    public function users(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }

        return $this->html('mod/users', [
            'users' => $this->mods->all(),
            'csrf_field' => $this->csrf->field(),
            'title' => 'Users',
        ]);
    }

    public function userNewForm(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }

        return $this->html('mod/user_form', [
            'new' => true,
            'user' => null,
            'boards' => $this->boards->all(),
            'csrf_field' => $this->csrf->field(),
            'error' => null,
            'title' => 'New user',
        ]);
    }

    public function userNew(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        if (!$this->csrf->validate($request->string('csrf'))) {
            return $this->error('Invalid token.', 400);
        }
        $username = $request->string('username');
        $password = (string) ($request->body['password'] ?? '');
        $type = $request->string('type', 'mod');
        if ($username === '' || $password === '') {
            return $this->html('mod/user_form', [
                'new' => true,
                'user' => null,
                'boards' => $this->boards->all(),
                'csrf_field' => $this->csrf->field(),
                'error' => 'Username and password required.',
                'title' => 'New user',
            ], 400);
        }
        if (!in_array($type, ['admin', 'mod', 'janitor'], true)) {
            $type = 'mod';
        }
        if ($this->mods->findByUsername($username) !== null) {
            return $this->html('mod/user_form', [
                'new' => true,
                'user' => null,
                'boards' => $this->boards->all(),
                'csrf_field' => $this->csrf->field(),
                'error' => 'Username taken.',
                'title' => 'New user',
            ], 400);
        }
        $boards = !empty($request->body['allboards']) ? '*' : $this->boardsFromPost($request);
        $id = $this->mods->create($username, $this->passwords->hash($password), $type, $boards);
        $this->writeLog($this->auth->user(), null, 'user_new', $username . ' #' . $id);

        return Response::redirect($this->view->url('/mod/users'));
    }

    public function userEditForm(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        $user = $this->mods->findById((int) ($params['id'] ?? 0));
        if ($user === null) {
            return $this->error('User not found.', 404);
        }

        return $this->html('mod/user_form', [
            'new' => false,
            'user' => $user,
            'boards' => $this->boards->all(),
            'csrf_field' => $this->csrf->field(),
            'error' => null,
            'title' => 'Edit user',
        ]);
    }

    public function userEdit(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        if (!$this->csrf->validate($request->string('csrf'))) {
            return $this->error('Invalid token.', 400);
        }
        $id = (int) ($params['id'] ?? 0);
        $user = $this->mods->findById($id);
        if ($user === null) {
            return $this->error('User not found.', 404);
        }

        if (!empty($request->body['delete_user'])) {
            $me = $this->auth->user();
            if ($me && (int) $me['id'] === $id) {
                return $this->error('Cannot delete your own account.', 400);
            }
            $this->mods->delete($id);
            $this->writeLog($me, null, 'user_delete', (string) $user['username']);

            return Response::redirect($this->view->url('/mod/users'));
        }

        $username = $request->string('username');
        $type = $request->string('type', (string) $user['type']);
        if (!in_array($type, ['admin', 'mod', 'janitor'], true)) {
            $type = (string) $user['type'];
        }
        $boards = !empty($request->body['allboards']) ? '*' : $this->boardsFromPost($request);
        $this->mods->update($id, $username !== '' ? $username : (string) $user['username'], $type, $boards);

        $pass = (string) ($request->body['password'] ?? '');
        if ($pass !== '') {
            $this->mods->setPassword($id, $this->passwords->hash($pass));
            $this->writeLog($this->auth->user(), null, 'user_password', '#' . $id);
        }
        $this->writeLog($this->auth->user(), null, 'user_edit', $username . ' #' . $id);

        return Response::redirect($this->view->url('/mod/users'));
    }

    // ── Log / pending / reports / recent ──────────────────────────────

    public function modLog(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        $page = max(1, $request->int('page', 1));
        $per = 50;
        $total = $this->mods->countLog();
        $pages = max(1, (int) ceil($total / $per));

        return $this->html('mod/log', [
            'entries' => $this->mods->recentLog($per, ($page - 1) * $per),
            'page' => $page,
            'pages' => $pages,
            'title' => 'Mod log',
        ]);
    }

    public function pending(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }

        return $this->html('mod/pending', [
            'posts' => $this->posts->pending(100),
            'csrf_field' => $this->csrf->field(),
            'title' => 'Post approval queue',
        ]);
    }

    public function reports(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }

        return $this->html('mod/reports', [
            'reports' => $this->reports->all(100),
            'csrf_field' => $this->csrf->field(),
            'title' => 'Report queue',
        ]);
    }

    public function reportAction(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        if (!$this->csrf->validate($request->string('csrf'))) {
            return $this->error('Invalid token.', 400);
        }
        $id = $request->int('id');
        $do = $request->string('do');
        $report = $this->reports->find($id);
        if ($report === null) {
            return Response::redirect($this->view->url('/mod/reports'));
        }
        $board = (string) $report['board_uri'];
        $postId = (int) $report['post_id'];

        match ($do) {
            'dismiss' => $this->reports->dismiss($id),
            'dismiss_post' => $this->reports->dismissForPost($board, $postId),
            'delete_post' => (function () use ($board, $postId, $id): void {
                $this->posts->delete($board, $postId);
                $this->reports->dismissForPost($board, $postId);
            })(),
            default => $this->reports->dismiss($id),
        };
        $this->writeLog($this->auth->user(), $board, 'report_' . $do, 'report #' . $id . ' post #' . $postId);

        return Response::redirect($this->view->url('/mod/reports'));
    }

    public function recent(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        $lim = min(100, max(10, $request->int('n', 25)));

        return $this->html('mod/recent', [
            'posts' => $this->posts->recent($lim),
            'csrf_field' => $this->csrf->field(),
            'title' => 'Recent posts',
        ]);
    }

    // ── Edit post ─────────────────────────────────────────────────────

    public function editPostForm(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        $board = strtolower($params['board'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $post = $this->posts->findAny($board, $id);
        if ($post === null) {
            return $this->error('Post not found.', 404);
        }

        return $this->html('mod/edit_post', [
            'board_uri' => $board,
            'post' => $post,
            'csrf_field' => $this->csrf->field(),
            'error' => null,
            'title' => 'Edit post #' . $id,
        ]);
    }

    public function editPost(Request $request, array $params): Response
    {
        if ($deny = $this->requireMod()) {
            return $deny;
        }
        if (!$this->csrf->validate($request->string('csrf'))) {
            return $this->error('Invalid token.', 400);
        }
        $board = strtolower($params['board'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $post = $this->posts->findAny($board, $id);
        if ($post === null) {
            return $this->error('Post not found.', 404);
        }

        $body = (string) ($request->body['body'] ?? '');
        $fields = [
            'name' => mb_substr($request->string('name'), 0, 50),
            'email' => mb_substr($request->string('email'), 0, 60),
            'subject' => mb_substr($request->string('subject'), 0, 100),
            'body' => $body,
            'body_html' => $this->markup->format($body, $board),
        ];

        if (!empty($request->body['remove_file'])) {
            $this->deleteFile($board, $id);
        }

        $file = $request->files['file'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $this->deleteFile($board, $id);
                $img = $this->images->store($file, $board);
                if ($img !== null) {
                    $fields = array_merge($fields, $img);
                }
            } catch (\Throwable $e) {
                return $this->html('mod/edit_post', [
                    'board_uri' => $board,
                    'post' => $post,
                    'csrf_field' => $this->csrf->field(),
                    'error' => $e->getMessage(),
                    'title' => 'Edit post #' . $id,
                ], 400);
            }
        }

        $this->posts->updatePost($board, $id, $fields);
        $this->writeLog($this->auth->user(), $board, 'edit_post', '#' . $id);

        $thread = $post['thread_id'] !== null ? (int) $post['thread_id'] : $id;

        return Response::redirect($this->view->url('/' . $board . '/res/' . $thread . '#p' . $id));
    }

    // ── helpers ───────────────────────────────────────────────────────

    private function deleteFile(string $board, int $id): void
    {
        $post = $this->posts->clearFile($board, $id);
        if ($post === null) {
            return;
        }
        $root = $this->config->string('paths.uploads');
        foreach (['file_path', 'thumb_path'] as $k) {
            if (!empty($post[$k])) {
                $path = $root . '/' . $post[$k];
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    private function removeBoardUploads(string $uri): void
    {
        $dir = $this->config->string('paths.uploads') . '/' . $uri;
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    private function boardsFromPost(Request $request): string
    {
        $all = $this->boards->all();
        $picked = [];
        foreach ($all as $b) {
            $key = 'board_' . $b['uri'];
            if (!empty($request->body[$key])) {
                $picked[] = $b['uri'];
            }
        }

        return $picked === [] ? '*' : implode(',', $picked);
    }

    private function writeLog(?array $mod, ?string $board, string $action, string $detail): void
    {
        $this->mods->log(
            $mod ? (int) $mod['id'] : null,
            $mod ? (string) $mod['username'] : '',
            $board,
            $action,
            $detail
        );
    }

    private function requireMod(): ?Response
    {
        if (!$this->auth->check()) {
            return Response::redirect($this->view->url('/mod/login'));
        }

        return null;
    }

    private function safeBack(string $back): string
    {
        if ($back === '' || str_contains($back, "\n") || str_contains($back, "\r")) {
            return $this->view->url('/mod');
        }
        if (str_starts_with($back, '/') && !str_starts_with($back, '//')) {
            return $back;
        }

        return $this->view->url('/mod');
    }

    /** @param array<string, mixed> $data */
    private function html(string $template, array $data, int $status = 200): Response
    {
        $data['mod'] = $data['mod'] ?? $this->auth->user();

        return Response::html($this->view->render($template, $data), $status);
    }

    private function error(string $message, int $status = 400): Response
    {
        return Response::html($this->view->render('error', [
            'message' => $message,
            'title' => 'Error',
        ]), $status);
    }
}
