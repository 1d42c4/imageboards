<?php

declare(strict_types=1);

namespace Chessboard;

use InvalidArgumentException;

final readonly class Config
{
    public function __construct(
        private array $settings,
        private string $rootPath,
    ) {
        date_default_timezone_set((string) $this->get('timezone'));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function requireString(string $key): string
    {
        $value = $this->settings[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Configuration value "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    public function requireInt(string $key): int
    {
        $value = $this->settings[$key] ?? null;
        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf('Configuration value "%s" must be an integer.', $key));
        }

        return $value;
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function basePath(): string
    {
        return (string) $this->get('base_path', '');
    }
}

