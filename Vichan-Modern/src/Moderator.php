<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class Moderator
{
    public function __construct(private App $app)
    {
    }
    public function run(): never
    {
        $staff = $this->app->security->staff();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $this->app->security->requirePost();
            $action = Input::text($_POST, 'action', 30);
            if ($action === 'login') {
                $this->app->security->login(Input::text($_POST, 'username', 32), Input::text($_POST, 'password', 128));
                $this->app->redirect('mod.php');
            }
            if ($action === 'logout') {
                $this->app->security->logout();
                $this->app->redirect('mod.php');
            }
            if ($staff === null) {
                throw new HttpError('Please sign in.', 401);
            }
            $this->mutate($action, $staff);
        }
        if ($staff === null) {
            echo $this->app->view->page('Moderator sign-in', 'login', []);
            exit;
        }
        $view = Input::text($_GET, 'view', 20) ?: 'dashboard';
        $permission = match ($view) {
            'dashboard' => 'password', 'post', 'posts' => 'posts', 'boards' => 'boards', 'staff' => 'staff', 'reports' => 'reports', 'log' => 'log', 'settings' => 'settings', 'password' => 'password', default => throw new HttpError('Page not found.', 404)
        };
        $this->app->security->requireStaff($permission);
        $data = ['staff' => $staff, 'role' => Role::from((string) $staff['role'])];
        $title = match ($view) {
            'dashboard' => 'Dashboard', 'post' => 'Manage post', 'posts' => 'Recent posts', 'boards' => 'Manage boards', 'staff' => 'Manage staff', 'reports' => 'Report queue', 'log' => 'Moderation log', 'settings' => 'Configuration', 'password' => 'Change password'
        };
        switch ($view) {
            case 'dashboard':
                $data['boards'] = $this->app->db->all('SELECT b.*,(SELECT COUNT(*) FROM posts p WHERE p.board_id=b.id) AS posts FROM boards b ORDER BY slug');
                $data['reports'] = (int) ($this->app->db->one('SELECT COUNT(*) AS n FROM reports')['n'] ?? 0);
                break;
            case 'post': $data['post'] = $this->app->boards->post(Input::id($_GET, 'id'));
                break;
            case 'posts':
                $data['query'] = Input::text($_GET, 'q', 100);
                $data['posts'] = $this->app->db->all('SELECT p.*,b.slug FROM posts p JOIN boards b ON b.id=p.board_id WHERE p.body LIKE ? OR p.subject LIKE ? ORDER BY p.id DESC LIMIT 100', ['%' . $data['query'] . '%', '%' . $data['query'] . '%']);
                break;
            case 'boards':
                $data['boards'] = $this->app->db->all('SELECT * FROM boards ORDER BY slug');
                $id = Input::id($_GET, 'id');
                $data['edit'] = $id ? ($this->app->db->one('SELECT * FROM boards WHERE id=?', [$id]) ?? throw new HttpError('Board not found.', 404)) : null;
                break;
            case 'staff': $data['users'] = $this->app->db->all('SELECT id,username,role FROM staff ORDER BY username');
                break;
            case 'reports': $data['reports'] = $this->app->db->all('SELECT r.*,p.subject,b.slug FROM reports r JOIN posts p ON p.id=r.post_id JOIN boards b ON b.id=p.board_id ORDER BY r.id DESC LIMIT 200');
                break;
            case 'log': $data['entries'] = $this->app->db->all('SELECT a.*,s.username FROM audit a LEFT JOIN staff s ON s.id=a.actor ORDER BY a.id DESC LIMIT 200');
                break;
        }
        echo $this->app->view->page($title, 'mod_' . $view, $data, true);
        exit;
    }
    /**
     * @param array<string, mixed> $staff */
    private function mutate(string $action, array $staff): never
    {
        $permission = match ($action) {
            'edit' => 'edit', 'delete' => 'delete', 'files' => 'files', 'sticky' => 'sticky', 'lock' => 'lock', 'board-save', 'board-delete' => 'boards', 'staff-save', 'staff-delete' => 'staff', 'report-dismiss' => 'reports', 'rebuild' => 'rebuild', 'settings' => 'settings', 'password' => 'password', default => throw new HttpError('Unknown moderator action.')
        };
        $this->app->security->requireStaff($permission);
        $this->app->security->throttle('mod:' . $staff['id'], 90, 60);
        $id = Input::id($_POST, 'id');
        $return = 'mod.php';
        if (in_array($action, ['edit', 'delete', 'files', 'sticky', 'lock'], true)) {
            $this->app->builder->change(function () use ($action, $id, $staff): void {
                $post = $this->app->boards->post($id);
                if ($action === 'delete' || $action === 'files') {
                    if (Input::text($_POST, 'confirm', 10) !== (string) $id) {
                        throw new HttpError('Type the post number to confirm deletion.');
                    }
                    $this->app->boards->delete($id, $action === 'files');
                } elseif ($action === 'edit') {
                    $body = Input::text($_POST, 'body', 30000);
                    $this->app->db->execute('UPDATE posts SET name=?,subject=?,body=?,edited=? WHERE id=?', [Input::text($_POST, 'name', 50) ?: 'Anonymous', Input::text($_POST, 'subject', 120), $body, time(), $id]);
                } else {
                    if ($post['thread_id'] !== null) {
                        throw new HttpError('This control applies to an opening post.');
                    }
                    // Column comes from the fixed action allowlist, never arbitrary request text.
                    $column = $action === 'sticky' ? 'sticky' : 'locked';
                    $this->app->db->execute('UPDATE posts SET ' . $column . '=1-' . $column . ' WHERE id=?', [$id]);
                }
                $this->app->security->audit((int) $staff['id'], $action, 'post:' . $id);
            });
            $return = $action === 'delete' ? 'mod.php?view=posts' : 'mod.php?view=post&id=' . $id;
        } elseif ($action === 'board-save') {
            $this->app->builder->change(function () use ($id, $staff): void {
                $title = Input::text($_POST, 'title', 100);
                $subtitle = Input::text($_POST, 'subtitle', 300);
                $locked = Input::text($_POST, 'locked', 1) === '1' ? 1 : 0;
                if ($title === '') {
                    throw new HttpError('Enter a board title.');
                }
                if ($id) {
                    if ($this->app->db->one('SELECT id FROM boards WHERE id=?', [$id]) === null) {
                        throw new HttpError('Board not found.', 404);
                    }
                    $this->app->db->execute('UPDATE boards SET title=?,subtitle=?,locked=? WHERE id=?', [$title, $subtitle, $locked, $id]);
                } else {
                    $slug = Input::slug(Input::text($_POST, 'slug', 24));
                    if ($this->app->db->one('SELECT id FROM boards WHERE slug=?', [$slug]) !== null) {
                        throw new HttpError('That board already exists.', 409);
                    }
                    $this->app->db->execute('INSERT INTO boards(slug,title,subtitle,locked) VALUES(?,?,?,?)', [$slug, $title, $subtitle, $locked]);
                }
                $this->app->security->audit((int) $staff['id'], 'board-save', 'board:' . ($id ?: (int) $this->app->db->pdo->lastInsertId()));
            });
            $return = 'mod.php?view=boards';
        } elseif ($action === 'board-delete') {
            $this->reauthenticate($staff);
            $this->app->builder->change(function () use ($id, $staff): void {
                $board = $this->app->db->one('SELECT * FROM boards WHERE id=?', [$id]) ?? throw new HttpError('Board not found.', 404);
                if (Input::text($_POST, 'confirm', 24) !== $board['slug']) {
                    throw new HttpError('Type the board name to confirm deletion.');
                }
                $this->app->boards->deleteBoard($id);
                $this->app->security->audit((int) $staff['id'], 'board-delete', 'board:' . $id);
            });
            $return = 'mod.php?view=boards';
        } elseif (in_array($action, ['staff-save', 'staff-delete'], true)) {
            $this->reauthenticate($staff);
            $this->app->db->transaction(function () use ($action, $id, $staff): void {
                $current = $id ? ($this->app->db->one('SELECT * FROM staff WHERE id=?', [$id]) ?? throw new HttpError('Staff account not found.', 404)) : null;
                $role = $action === 'staff-save' ? (Role::tryFrom(Input::text($_POST, 'role', 20)) ?? throw new HttpError('Invalid role.')) : null;
                if ($current !== null && $current['role'] === 'admin' && $role !== Role::Admin) {
                    if ((int) ($this->app->db->one("SELECT COUNT(*) AS n FROM staff WHERE role='admin'")['n'] ?? 0) <= 1) {
                        throw new HttpError('The last administrator cannot be removed or demoted.');
                    }
                }
                if ($action === 'staff-delete') {
                    $this->app->db->execute('DELETE FROM staff WHERE id=?', [$id]);
                } elseif ($current !== null) {
                    $this->app->db->execute('UPDATE staff SET role=?,version=version+1 WHERE id=?', [($role->value ?? throw new \LogicException('Missing staff role.')), $id]);
                    $password = Input::text($_POST, 'new_password', 128);
                    if ($password !== '') {
                        $this->app->db->execute('UPDATE staff SET password_hash=? WHERE id=?', [Security::hashPassword(Input::password($password)), $id]);
                    }
                } else {
                    $username = Input::text($_POST, 'username', 32);
                    if (!preg_match('/\A[a-zA-Z0-9_-]{3,32}\z/D', $username)) {
                        throw new HttpError('Staff usernames must be 3–32 letters, numbers, underscores or hyphens.');
                    }
                    if ($this->app->db->one('SELECT id FROM staff WHERE username=? COLLATE NOCASE', [$username]) !== null) {
                        throw new HttpError('That username already exists.', 409);
                    }
                    $this->app->db->execute('INSERT INTO staff(username,password_hash,role) VALUES(?,?,?)', [$username, Security::hashPassword(Input::password(Input::text($_POST, 'new_password', 128))), ($role->value ?? throw new \LogicException('Missing staff role.'))]);
                }
                $this->app->security->audit((int) $staff['id'], $action, 'staff:' . ($id ?: (int) $this->app->db->pdo->lastInsertId()));
            });
            $return = 'mod.php?view=staff';
        } elseif ($action === 'password') {
            $this->reauthenticate($staff);
            $this->app->db->execute('UPDATE staff SET password_hash=?,version=version+1 WHERE id=?', [Security::hashPassword(Input::password(Input::text($_POST, 'new_password', 128))), $staff['id']]);
            $this->app->security->audit((int) $staff['id'], 'password', 'staff:' . $staff['id']);
            $firstLogin = $this->app->config->path('var/first-login.txt');
            if (is_file($firstLogin) && str_contains((string) file_get_contents($firstLogin), 'Username: ' . $staff['username'] . "\n")) {
                unlink($firstLogin);
            }
            $this->app->security->logout();
        } elseif ($action === 'report-dismiss') {
            $this->app->db->execute('DELETE FROM reports WHERE id=?', [$id]);
            $this->app->security->audit((int) $staff['id'], $action, 'report:' . $id);
            $return = 'mod.php?view=reports';
        } elseif ($action === 'settings') {
            $title = Input::text($_POST, 'title', 100);
            $theme = Input::text($_POST, 'theme', 50);
            if ($title === '' || !isset($this->app->view->themes()[$theme])) {
                throw new HttpError('Enter a title and select an available theme.');
            }
            $this->app->builder->change(function () use ($title, $theme, $staff): void {
                foreach (['title' => $title, 'theme' => $theme] as $key => $value) {
                    $this->app->db->execute('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$key, $value]);
                }
                $this->app->security->audit((int) $staff['id'], 'settings', 'site');
            });
            $return = 'mod.php?view=settings';
        } elseif ($action === 'rebuild') {
            $this->app->builder->rebuild();
            $this->app->security->audit((int) $staff['id'], 'rebuild', 'site');
        }
        $this->app->redirect($return);
    }
    /**
     * @param array<string, mixed> $staff */
    private function reauthenticate(array $staff): void
    {
        $this->app->security->throttle('reauth:' . $staff['id'], 10, 300);
        $row = $this->app->db->one('SELECT password_hash FROM staff WHERE id=?', [$staff['id']]);
        if ($row === null || !Security::verifyPassword(Input::text($_POST, 'current_password', 128), (string) $row['password_hash'])) {
            throw new HttpError('Your current password is incorrect.', 403);
        }
    }
}
