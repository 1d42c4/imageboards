<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class Builder
{
    public function __construct(private App $app)
    {
    }
    /**
     * @template T
     * @param callable(): T $work
     * @return T */
    public function change(callable $work): mixed
    {
        $handle = fopen($this->app->config->path('var/build.lock'), 'c');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Could not lock page generation.');
        }
        try {
            $result = $work();
            $this->build();
            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
    public function rebuild(): void
    {
        $this->change(static function (): void {
        });
    }
    private function build(): void
    {
        $boards = $this->app->db->all('SELECT * FROM boards ORDER BY slug');
        $paths = ['index.html'];
        $this->write('index.html', $this->app->view->home($boards));
        $this->write('privacy.html', $this->app->view->page('Privacy', 'privacy', []));
        $paths[] = 'privacy.html';
        foreach ($boards as $board) {
            $slug = Input::slug((string) $board['slug']);
            $threads = $this->app->db->all('SELECT p.*,? AS slug FROM posts p WHERE board_id=? AND thread_id IS NULL ORDER BY sticky DESC,bumped DESC,id DESC', [$slug, $board['id']]);
            $pages = max(1, (int) ceil(count($threads) / $this->app->config->int('threads_per_page')));
            for ($page = 1; $page <= $pages; $page++) {
                $path = $slug . '/' . ($page === 1 ? 'index.html' : $page . '.html');
                $paths[] = $path;
                $this->write($path, $this->app->view->board($board, array_slice($threads, ($page - 1) * $this->app->config->int('threads_per_page'), $this->app->config->int('threads_per_page')), $page, $pages));
            }
            $path = $slug . '/catalog.html';
            $paths[] = $path;
            $this->write($path, $this->app->view->page('/' . $slug . '/ — Catalog', 'catalog', ['board' => $board, 'threads' => $threads], board: $board));
            foreach ($threads as $thread) {
                $path = $slug . '/res/' . $thread['id'] . '.html';
                $paths[] = $path;
                $this->write($path, $this->app->view->thread($board, $thread));
            }
        }
        $manifest = $this->app->config->path('var/generated.json');
        $old = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR) : [];
        foreach (array_diff($old, $paths) as $path) {
            if (preg_match('~\A[a-z][a-z0-9_]{0,23}/(?:res/)?(?:index|catalog|[0-9]+)\.html\z~D', $path)) {
                $file = $this->app->config->path('public/' . $path);
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        file_put_contents($manifest, json_encode($paths, JSON_THROW_ON_ERROR), LOCK_EX);
    }
    private function write(string $relative, string $html): void
    {
        $target = $this->app->config->path('public/' . $relative);
        $directory = dirname($target);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $temp = $directory . '/.build-' . bin2hex(random_bytes(8)) . '.tmp';
        try {
            if (file_put_contents($temp, $html, LOCK_EX) !== strlen($html) || !rename($temp, $target)) {
                throw new \RuntimeException('Page publication failed.');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }
}
