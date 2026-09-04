<?php

declare(strict_types=1);

namespace Newboard\Security;

use Newboard\Config;

/**
 * Session wrapper. Does not store IP. Cookie-only identity for cooldown/mod login.
 */
final class Session
{
    private bool $started = false;

    public function __construct(private readonly Config $config)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        session_name($this->config->string('session.name', 'newboard_sess'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->config->bool('session.secure', false),
            'httponly' => $this->config->bool('session.httponly', true),
            'samesite' => $this->config->string('session.samesite', 'Lax'),
        ]);
        session_start();
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }
}
