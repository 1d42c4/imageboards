<?php

declare(strict_types=1);

namespace VichanModern;

enum Role: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Janitor = 'janitor';

    public function allows(string $action): bool
    {
        return match ($this) {
            self::Admin => true,
            self::Moderator => in_array($action, ['posts', 'edit', 'delete', 'files', 'sticky', 'lock', 'reports', 'log', 'rebuild', 'password'], true),
            self::Janitor => in_array($action, ['posts', 'delete', 'files', 'reports', 'password'], true),
        };
    }
}
