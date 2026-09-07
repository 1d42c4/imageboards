<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class PostDraft
{
    public function __construct(public string $board, public int $thread, public string $name, public string $subject, public string $body, #[\SensitiveParameter] public string $password, public bool $sage)
    {
    }
    /**
     * @param array<string, mixed> $input */
    public static function from(array $input): self
    {
        return new self(Input::slug(Input::text($input, 'board', 24)), Input::id($input, 'thread'), Input::text($input, 'name', 50) ?: 'Anonymous', Input::text($input, 'subject', 120), Input::text($input, 'body', 30000), Input::text($input, 'password', 128), Input::text($input, 'sage', 1) === '1');
    }
}
