<?php

declare(strict_types=1);

namespace Chessboard\Security;

use Chessboard\Http\HttpException;
use Chessboard\Repository\ModerationRepository;

final class ModeratorAuth
{
    private array|false|null $cachedUser = null;

    public function __construct(private readonly ModerationRepository $moderation)
    {
    }

    public function attempt(string $username, string $password): bool
    {
        $moderator = $this->moderation->moderatorByUsername($username);
        if ($moderator === null || !password_verify($password, $moderator['password_hash'])) {
            password_verify($password, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');

            return false;
        }

        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $newHash = password_needs_rehash($moderator['password_hash'], $algorithm)
            ? password_hash($password, $algorithm)
            : null;
        $this->moderation->markLogin((int) $moderator['id'], $newHash);
        session_regenerate_id(true);
        $_SESSION['moderator_id'] = (int) $moderator['id'];
        $this->cachedUser = null;

        return true;
    }

    public function user(): ?array
    {
        if ($this->cachedUser === false) {
            return null;
        }
        if (is_array($this->cachedUser)) {
            return $this->cachedUser;
        }

        $id = $_SESSION['moderator_id'] ?? null;
        if (!is_int($id) && !is_numeric($id)) {
            $this->cachedUser = false;

            return null;
        }

        $user = $this->moderation->moderatorById((int) $id);
        if ($user === null) {
            unset($_SESSION['moderator_id']);
            $this->cachedUser = false;

            return null;
        }

        $this->cachedUser = $user;

        return $user;
    }

    public function requireUser(): array
    {
        $user = $this->user();
        if ($user === null) {
            throw new HttpException(401, 'Moderator login required.');
        }

        return $user;
    }

    public function logout(): void
    {
        unset($_SESSION['moderator_id']);
        $this->cachedUser = false;
        session_regenerate_id(true);
    }
}

