<?php

declare(strict_types=1);

namespace Newboard\Http;

/**
 * HTTP request snapshot.
 *
 * Policy: REMOTE_ADDR and client IP headers are intentionally NOT exposed
 * and MUST never be written to the database or logs.
 */
final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $server
     * @param array<string, array<string, mixed>> $files
     * @param array<string, string> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $server,
        public readonly array $files,
        public readonly array $cookies,
    ) {
    }

    public static function fromGlobals(string $basePath = ''): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        /** @var array<string, array<string, mixed>> $files */
        $files = $_FILES;

        return new self(
            $method,
            $path === '' ? '/' : $path,
            self::stringMap($_GET),
            $_POST,
            $_SERVER,
            $files,
            self::stringMap($_COOKIE),
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);

        return is_string($v) || is_numeric($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, $default);

        return is_numeric($v) ? (int) $v : $default;
    }

    /**
     * @param array<mixed> $src
     * @return array<string, string>
     */
    private static function stringMap(array $src): array
    {
        $out = [];
        foreach ($src as $k => $v) {
            if (is_string($k) && (is_string($v) || is_numeric($v))) {
                $out[$k] = (string) $v;
            }
        }

        return $out;
    }
}
