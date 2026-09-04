<?php

declare(strict_types=1);

namespace Newboard\View;

use Newboard\Config;
use Newboard\Repository\BoardRepository;

final class Renderer
{
    private ?BoardRepository $boards = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function setBoards(BoardRepository $boards): void
    {
        $this->boards = $boards;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $file = $this->config->string('paths.templates') . '/' . ltrim($template, '/');
        if (!str_ends_with($file, '.php')) {
            $file .= '.php';
        }
        if (!is_readable($file)) {
            throw new \RuntimeException('Template not found: ' . $template);
        }

        $data['config'] = $this->config;
        $data['e'] = static fn (mixed $v): string => self::e($v);
        $data['url'] = fn (string $path = ''): string => $this->url($path);
        $data['stylesheets'] = $this->config->get('stylesheets', []);
        $data['stylesheet'] = $this->resolveStylesheet();
        $data['stylesheet_name'] = $this->resolveStylesheetName();
        if (!isset($data['boardlist']) && $this->boards !== null) {
            $data['boardlist'] = $this->boards->all();
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    public function resolveStylesheetName(): string
    {
        $default = $this->config->string('default_stylesheet', 'Yotsuba B');
        $cookie = $_COOKIE['newboard_style'] ?? '';
        /** @var array<string, string> $styles */
        $styles = $this->config->get('stylesheets', []);
        if (is_string($cookie) && isset($styles[$cookie])) {
            return $cookie;
        }

        return $default;
    }

    public function resolveStylesheet(): string
    {
        /** @var array<string, string> $styles */
        $styles = $this->config->get('stylesheets', []);
        $name = $this->resolveStylesheetName();

        return $styles[$name] ?? (string) reset($styles) ?: 'yotsuba_b.css';
    }

    public function url(string $path = ''): string
    {
        $base = rtrim($this->config->string('base_path'), '/');
        if ($path === '' || $path === '/') {
            return $base === '' ? '/' : $base . '/';
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $base . $path;
    }

    public static function e(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
