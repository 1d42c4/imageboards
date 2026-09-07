<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class Config
{
    /**
     * @param array<string, mixed> $values */
    public function __construct(public string $root, private array $values)
    {
        if (!preg_match('~\A(?:/[a-zA-Z0-9_-]+)*\z~D', $this->string('base_path'))) {
            throw new \RuntimeException('base_path must be empty or a path such as /forum.');
        }
    }

    public function string(string $key): string
    {
        return (string) ($this->values[$key] ?? '');
    }
    public function int(string $key): int
    {
        return (int) ($this->values[$key] ?? 0);
    }
    public function bool(string $key): bool
    {
        return (bool) ($this->values[$key] ?? false);
    }
    public function path(string $path): string
    {
        return $this->root . '/' . $path;
    }
    public function url(string $path = ''): string
    {
        return $this->string('base_path') . '/' . ltrim($path, '/');
    }
}
