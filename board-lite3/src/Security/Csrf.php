<?php

declare(strict_types=1);

namespace Chessboard\Security;

use Chessboard\Http\HttpException;

final class Csrf
{
    public function token(): string
    {
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public function validate(string $candidate): void
    {
        if ($candidate === '' || !hash_equals($this->token(), $candidate)) {
            throw new HttpException(419, 'Your form session expired. Refresh the page and try again.');
        }
    }
}

