<?php

declare(strict_types=1);

namespace VichanModern;

final class Input
{
    /**
     * @param array<string, mixed> $source */
    public static function text(array $source, string $key, int $max = 200): string
    {
        $value = $source[$key] ?? '';
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || strlen($value) > $max * 4 || mb_strlen($value) > $max) {
            throw new HttpError('Invalid or too long field: ' . $key);
        }
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", $value)) ?? '');
    }
    /**
     * @param array<string, mixed> $source */
    public static function id(array $source, string $key, int $default = 0): int
    {
        $value = $source[$key] ?? (string) $default;
        if (!is_scalar($value) || !preg_match('/\A[0-9]{1,10}\z/D', (string) $value)) {
            throw new HttpError('Invalid number: ' . $key);
        }
        return (int) $value;
    }
    public static function slug(string $value): string
    {
        if (!preg_match('/\A[a-z][a-z0-9_]{0,23}\z/D', $value) || in_array($value, ['stylesheets', 'static', 'assets', 'var', 'src', 'bin', 'views', 'deploy', 'tests'], true)) {
            throw new HttpError('Use a board name of 1–24 lowercase letters, numbers or underscores, beginning with a letter.');
        }
        return $value;
    }
    public static function password(#[\SensitiveParameter] string $password): string
    {
        if (strlen($password) < 12 || strlen($password) > 128) {
            throw new HttpError('Passwords must be 12–128 bytes long.');
        }
        return $password;
    }
}
