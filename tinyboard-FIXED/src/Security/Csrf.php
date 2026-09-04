<?php

declare(strict_types=1);

namespace Newboard\Security;

final class Csrf
{
    private const SESSION_KEY = '_csrf';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $t = $this->session->get(self::SESSION_KEY);
        if (!is_string($t) || strlen($t) < 32) {
            $t = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $t);
        }

        return $t;
    }

    public function field(): string
    {
        $name = 'csrf';
        $val = htmlspecialchars($this->token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<input type="hidden" name="' . $name . '" value="' . $val . '">';
    }

    public function validate(?string $submitted): bool
    {
        if ($submitted === null || $submitted === '') {
            return false;
        }
        $expected = $this->session->get(self::SESSION_KEY);
        if (!is_string($expected)) {
            return false;
        }

        return hash_equals($expected, $submitted);
    }
}
