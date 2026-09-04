<?php

declare(strict_types=1);

namespace Newboard\Service;

use Newboard\Repository\ModRepository;
use Newboard\Security\PasswordHasher;
use Newboard\Security\Session;

final class ModAuthService
{
    private const SESSION_KEY = 'mod_id';

    public function __construct(
        private readonly ModRepository $mods,
        private readonly PasswordHasher $passwords,
        private readonly Session $session,
    ) {
    }

    public function attempt(string $username, string $password): bool
    {
        $mod = $this->mods->findByUsername($username);
        if ($mod === null) {
            return false;
        }
        if (!$this->passwords->verify($password, (string) $mod['password_hash'])) {
            return false;
        }
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, (int) $mod['id']);
        $this->mods->log((int) $mod['id'], (string) $mod['username'], null, 'login', 'ok');

        return true;
    }

    public function logout(): void
    {
        $mod = $this->user();
        if ($mod !== null) {
            $this->mods->log((int) $mod['id'], (string) $mod['username'], null, 'logout', '');
        }
        $this->session->remove(self::SESSION_KEY);
        $this->session->regenerate();
    }

    public function user(): ?array
    {
        $id = $this->session->get(self::SESSION_KEY);
        if (!is_int($id) && !is_numeric($id)) {
            return null;
        }

        return $this->mods->findById((int) $id);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }
}
