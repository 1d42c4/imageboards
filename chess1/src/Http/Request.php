<?php

declare(strict_types=1);

namespace Chessboard\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public array $post,
        public array $files,
        public array $server,
    ) {
    }

    public static function fromGlobals(string $basePath = ''): self
    {
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($uriPath) ? rawurldecode($uriPath) : '/';
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }

        if ($path === '' || str_contains($path, "\0")) {
            $path = '/';
        }

        $https = $_SERVER['HTTPS'] ?? '';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
            $_FILES,
            ['HTTPS' => is_string($https) ? $https : ''],
        );
    }

    public function input(string $name, string $default = ''): string
    {
        $value = $this->post[$name] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public function queryInt(string $name, int $default = 1): int
    {
        $value = filter_var($this->query[$name] ?? null, FILTER_VALIDATE_INT);

        return is_int($value) ? $value : $default;
    }

    public function file(string $name): ?array
    {
        $file = $this->files[$name] ?? null;

        return is_array($file) ? $file : null;
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') !== '' && ($this->server['HTTPS'] ?? '') !== 'off';
    }
}

