<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class Security
{
    public function __construct(private Config $config, private Db $db)
    {
    }
    public static function headers(bool $private = true): void
    {
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self'; connect-src 'self'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('X-Frame-Options: DENY');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        if ($private) {
            header('Cache-Control: no-store, private');
            header('Vary: Cookie');
        }
    }
    public function session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '7200');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        session_save_path($this->config->path('var/sessions'));
        session_name('vichan_modern');
        session_set_cookie_params(['lifetime' => 0, 'path' => $this->config->url(), 'secure' => $this->config->bool('secure_cookies'), 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
        if (!isset($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        if (!isset($_SESSION['visitor'])) {
            $_SESSION['visitor'] = bin2hex(random_bytes(32));
        }
    }
    public function csrf(): string
    {
        $this->session();
        return (string) $_SESSION['csrf'];
    }
    public function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            throw new HttpError('Use the form to perform this action.', 405);
        }
        if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
            throw new HttpError('Cross-site request rejected.', 403);
        }
        if (!hash_equals($this->csrf(), Input::text($_POST, 'csrf', 64))) {
            throw new HttpError('The form expired. Reload the page and try again.', 403);
        }
    }
    public function visitor(): string
    {
        $this->session();
        return hash('sha256', (string) $_SESSION['visitor']);
    }
    public static function hashPassword(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1]);
    }
    public static function verifyPassword(#[\SensitiveParameter] string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    public function throttle(string $scope, int $limit, int $seconds): void
    {
        if ($limit <= 0 || $seconds <= 0) {
            return;
        }
        $now = time();
        $bucket = intdiv($now, $seconds);
        $this->db->transaction(function () use ($scope, $limit, $seconds, $bucket, $now): void {
            $this->db->execute('DELETE FROM throttle WHERE expires < ?', [$now]);
            $this->db->execute('INSERT INTO throttle(scope,bucket,hits,expires) VALUES(?,?,1,?) ON CONFLICT(scope,bucket) DO UPDATE SET hits=hits+1', [$scope, $bucket, ($bucket + 1) * $seconds]);
            $row = $this->db->one('SELECT hits FROM throttle WHERE scope=? AND bucket=?', [$scope, $bucket]);
            if ((int) ($row['hits'] ?? 0) > $limit) {
                throw new HttpError('Too many requests. Please wait a little and try again.', 429);
            }
        });
    }
    /**
     * @return array<string, mixed>|null */
    public function staff(): ?array
    {
        $this->session();
        if (!isset($_SESSION['staff_id']) || time() - (int) ($_SESSION['staff_seen'] ?? 0) > 1800 || time() - (int) ($_SESSION['staff_started'] ?? 0) > 28800) {
            unset($_SESSION['staff_id']);
            return null;
        }
        $staff = $this->db->one('SELECT id,username,role,version FROM staff WHERE id=?', [(int) $_SESSION['staff_id']]);
        if ($staff === null || (int) $staff['version'] !== (int) ($_SESSION['staff_version'] ?? -1)) {
            unset($_SESSION['staff_id']);
            return null;
        }
        $_SESSION['staff_seen'] = time();
        return $staff;
    }
    /**
     * @return array<string, mixed> */
    public function requireStaff(string $permission): array
    {
        $staff = $this->staff();
        if ($staff === null) {
            throw new HttpError('Please sign in to the moderator area.', 401);
        }
        if (!Role::from((string) $staff['role'])->allows($permission)) {
            throw new HttpError('Your staff role does not allow this action.', 403);
        }
        return $staff;
    }
    public function login(string $username, #[\SensitiveParameter] string $password): void
    {
        $this->throttle('login:global', 100, 300);
        $this->throttle('login:' . hash('sha256', mb_strtolower($username)), 10, 300);
        $staff = $this->db->one('SELECT * FROM staff WHERE username=? COLLATE NOCASE', [$username]);
        $dummy = '$argon2id$v=19$m=19456,t=2,p=1$dTFTRWJBVFdGa3FKZE9lQw$oKZc1S79PtD+ATdiio9EQK9JEw24wiQXVlZxDPAPVUc';
        if (!self::verifyPassword($password, (string) ($staff['password_hash'] ?? $dummy)) || $staff === null) {
            throw new HttpError('Incorrect username or password.', 403);
        }
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['staff_id'] = (int) $staff['id'];
        $_SESSION['staff_version'] = (int) $staff['version'];
        $_SESSION['staff_seen'] = $_SESSION['staff_started'] = time();
        if (password_needs_rehash((string) $staff['password_hash'], PASSWORD_ARGON2ID, ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1])) {
            $this->db->execute('UPDATE staff SET password_hash=? WHERE id=?', [self::hashPassword($password), $staff['id']]);
        }
        $this->audit((int) $staff['id'], 'login', 'staff');
    }
    public function logout(): void
    {
        $this->session();
        $_SESSION = [];
        session_regenerate_id(true);
    }
    public function audit(int $actor, string $action, string $target): void
    {
        $this->db->execute('INSERT INTO audit(actor,action,target,created) VALUES(?,?,?,?)', [$actor, $action, $target, time()]);
    }
}
