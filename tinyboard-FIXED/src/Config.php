<?php

declare(strict_types=1);

namespace Newboard;

/**
 * Immutable-ish configuration bag loaded from config/app.php (+ optional local.php).
 */
final class Config
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
    }

    public static function load(string $root): self
    {
        $base = require $root . '/config/app.php';
        if (!is_array($base)) {
            throw new \RuntimeException('config/app.php must return an array');
        }
        $localFile = $root . '/config/local.php';
        if (is_readable($localFile)) {
            $local = require $localFile;
            if (is_array($local)) {
                $base = self::merge($base, $local);
            }
        }

        return new self($base);
    }

    /** @return mixed */
    public function get(string $path, mixed $default = null): mixed
    {
        $parts = explode('.', $path);
        $cur = $this->data;
        foreach ($parts as $p) {
            if (!is_array($cur) || !array_key_exists($p, $cur)) {
                return $default;
            }
            $cur = $cur[$p];
        }

        return $cur;
    }

    public function string(string $path, string $default = ''): string
    {
        $v = $this->get($path, $default);

        return is_string($v) || is_numeric($v) ? (string) $v : $default;
    }

    public function int(string $path, int $default = 0): int
    {
        $v = $this->get($path, $default);

        return is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $path, bool $default = false): bool
    {
        $v = $this->get($path, $default);

        return (bool) $v;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    private static function merge(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            if (is_array($v) && isset($a[$k]) && is_array($a[$k])) {
                $a[$k] = self::merge($a[$k], $v);
            } else {
                $a[$k] = $v;
            }
        }

        return $a;
    }
}
