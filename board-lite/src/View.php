<?php

declare(strict_types=1);

namespace Chessboard;

use Chessboard\Repository\BoardRepository;
use Chessboard\Security\Csrf;
use Chessboard\Security\ModeratorAuth;
use Chessboard\Security\Session;
use Chessboard\Service\Markup;
use RuntimeException;

final readonly class View
{
    public function __construct(
        private Config $config,
        private Csrf $csrf,
        private ModeratorAuth $auth,
        private BoardRepository $boards,
        private Markup $markup,
    ) {
    }

    public function render(string $template, array $data = [], int $status = 200): string
    {
        $common = [
            'appName' => $this->config->requireString('app_name'),
            'tagline' => $this->config->requireString('tagline'),
            'navigationBoards' => $this->boards->all(),
            'currentModerator' => $this->auth->user(),
            'flashMessages' => Session::consumeFlash(),
            'status' => $status,
        ];
        $pageData = array_replace($common, $data);
        $content = $this->capture($template, $pageData);

        return $this->capture('layout', array_replace($pageData, ['content' => $content]));
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->capture($template, $data);
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function url(string $path): string
    {
        if ($path === '') {
            $path = '/';
        }

        return $this->config->basePath() . $path;
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . $this->e($this->csrf->token()) . '">';
    }

    public function body(string $body, string $boardSlug): string
    {
        return $this->markup->render($body, $boardSlug);
    }

    public function time(int|string $timestamp): string
    {
        return date('Y-m-d H:i:s T', (int) $timestamp);
    }

    public function bytes(int|string $bytes): string
    {
        $bytes = (int) $bytes;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KiB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MiB';
    }

    public function postUrl(string $board, int|string $threadNo, int|string $postNo): string
    {
        $suffix = (int) $threadNo === (int) $postNo ? '' : '#p' . (int) $postNo;

        return $this->url(sprintf('/%s/thread/%d%s', rawurlencode($board), (int) $threadNo, $suffix));
    }

    public function mediaUrl(string $kind, string $name): string
    {
        return $this->url(sprintf('/media/%s/%s', $kind, rawurlencode($name)));
    }

    private function capture(string $template, array $data): string
    {
        $path = $this->config->rootPath() . '/templates/' . $template . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('View not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $path;

            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}

