<?php

declare(strict_types=1);

namespace Chessboard\Http;

final class Response
{
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function file(string $path, string $mime): self
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new HttpException(404, 'File not found.');
        }

        return new self($contents, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function send(bool $withoutBody = false): never
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if (!$withoutBody) {
            echo $this->body;
        }

        exit;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }
}

