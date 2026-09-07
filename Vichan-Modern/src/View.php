<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class View
{
    public Formatter $formatter;
    public function __construct(public App $app)
    {
        $this->formatter = new Formatter($app->config);
    }
    public static function e(mixed $value): string
    {
        return Formatter::escape($value);
    }
    public function url(string $path = ''): string
    {
        return self::e($this->app->config->url($path));
    }
    /**
     * @param array<string, mixed> $data */
    public function fragment(string $template, array $data): string
    {
        // Only internal callers select these fixed template names; request data never selects a file.
        if (!preg_match('/\A[a-z_]+\z/D', $template)) {
            throw new \LogicException('Invalid view.');
        }
        ob_start();
        try {
            (function () use ($template, $data): void {
                $v = $this;
                extract($data, EXTR_SKIP);
                require $this->app->config->path('views/' . $template . '.php');
            })();
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $board */
    public function page(string $title, string $template, array $data, bool $mod = false, ?array $board = null): string
    {
        return $this->fragment('layout', ['title' => $title, 'body' => $this->fragment($template, $data), 'mod' => $mod, 'board' => $board, 'boards' => $this->app->installed() ? $this->app->db->all('SELECT * FROM boards ORDER BY slug') : []]);
    }
    public function error(string $message, int $status): string
    {
        return $this->page('Request could not be completed', 'error', ['message' => $message, 'status' => $status]);
    }
    /**
     * @param list<array<string, mixed>> $boards */
    public function home(array $boards): string
    {
        return $this->page($this->app->setting('title', $this->app->config->string('title')), 'home', ['boards' => $boards]);
    }
    /**
     * @param array<string, mixed> $board
     * @param list<array<string, mixed>> $threads */
    public function board(array $board, array $threads, int $page, int $pages): string
    {
        return $this->page('/' . $board['slug'] . '/ — ' . $board['title'], 'board', compact('board', 'threads', 'page', 'pages') + ['thread' => null], board: $board);
    }
    /**
     * @param array<string, mixed> $board
     * @param array<string, mixed> $thread */
    public function thread(array $board, array $thread): string
    {
        return $this->page((string) ($thread['subject'] ?: '/' . $board['slug'] . '/ — Thread ' . $thread['id']), 'board', ['board' => $board, 'threads' => [$thread], 'thread' => $thread, 'page' => 1, 'pages' => 1], board: $board);
    }
    /**
     * @param array<string, mixed> $post */
    public function post(array $post, bool $reply = false, bool $preview = false): string
    {
        return $this->fragment('post', ['post' => $post, 'reply' => $reply, 'preview' => $preview, 'files' => $this->app->db->all('SELECT * FROM files WHERE post_id=? ORDER BY id', [$post['id']])]);
    }
    /**
     * @return array<string, string> */
    public function themes(): array
    {
        $themes = ['style' => 'Yotsuba B (original)'];
        foreach (glob($this->app->config->path('public/stylesheets/*.css')) ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if ($name !== 'style') {
                $themes[$name] = ucwords(str_replace(['_', '-'], ' ', $name));
            }
        }
        return $themes;
    }
    public function csrf(): string
    {
        return '<input type="hidden" name="csrf" value="' . self::e($this->app->security->csrf()) . '">';
    }
}
