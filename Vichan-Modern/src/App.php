<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class App
{
    public Config $config;
    public Db $db;
    public Security $security;
    public Captcha $captcha;
    public Media $media;
    public View $view;
    public BoardService $boards;
    public Builder $builder;
    /**
     * @param array<string, mixed> $values */
    public function __construct(string $root, array $values)
    {
        $this->config = new Config($root, $values);
        foreach (['var', 'var/media', 'var/sessions'] as $directory) {
            $path = $this->config->path($directory);
            if (!is_dir($path)) {
                mkdir($path, 0700, true);
            }
        }
        $this->db = new Db($this->config->path('var/board.sqlite'));
        $this->security = new Security($this->config, $this->db);
        $this->captcha = new Captcha($this->config, $this->security);
        $this->media = new Media($this->config, $this->db);
        $this->view = new View($this);
        $this->boards = new BoardService($this);
        $this->builder = new Builder($this);
    }
    public function installed(): bool
    {
        return $this->db->one("SELECT name FROM sqlite_master WHERE type='table' AND name='staff'") !== null && $this->db->one('SELECT id FROM staff LIMIT 1') !== null;
    }
    public function setting(string $key, string $default = ''): string
    {
        return (string) ($this->db->one('SELECT value FROM settings WHERE key=?', [$key])['value'] ?? $default);
    }
    public function run(string $route): never
    {
        Security::headers();
        try {
            if (!$this->installed()) {
                throw new HttpError('Run the desktop launcher or bin/install.php first to create this board.', 503);
            }
            if ($route === 'mod') {
                (new Moderator($this))->run();
            } else {
                (new PublicController($this))->run($route);
            }
        } catch (HttpError $error) {
            http_response_code($error->status);
            header_remove('Content-Length');
            if (isset($_POST['json_response']) || $route === 'session') {
                $this->json(['error' => $error->getMessage()], $error->status);
            }
            header('Content-Type: text/html; charset=UTF-8');
            echo $this->view->error($error->getMessage(), $error->status);
        } catch (\Throwable $error) {
            $id = bin2hex(random_bytes(6));
            // Never record request headers, network addresses, payloads, credentials or exception messages.
            $record = gmdate('c') . ' ' . $id . ' ' . $error::class . ' ' . basename($error->getFile()) . ':' . $error->getLine() . "\n";
            file_put_contents($this->config->path('var/errors.log'), $record, FILE_APPEND | LOCK_EX);
            http_response_code(500);
            if (isset($_POST['json_response']) || $route === 'session') {
                $this->json(['error' => 'The request could not be completed. Reference: ' . $id], 500);
            }
            header('Content-Type: text/html; charset=UTF-8');
            echo $this->view->error('The request could not be completed. Reference: ' . $id, 500);
        }
        exit;
    }
    /**
     * @param array<string, mixed> $data */
    public function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    public function redirect(string $path): never
    {
        header('Location: ' . $this->config->url($path), true, 303);
        exit;
    }
}
