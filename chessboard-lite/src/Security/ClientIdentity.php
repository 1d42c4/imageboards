<?php

declare(strict_types=1);

namespace Chessboard\Security;

use Chessboard\Config;
use RuntimeException;

final class ClientIdentity
{
    private string $key;

    public function __construct(Config $config)
    {
        $path = $config->requireString('key_path');
        if (!is_file($path)) {
            throw new RuntimeException('The application key is missing. Run: php bin/install.php');
        }

        $key = file_get_contents($path);
        if ($key === false || strlen(trim($key)) < 32) {
            throw new RuntimeException('The application key is invalid. Run the installer again.');
        }

        $this->key = trim($key);
    }

    public function hashIp(string $ip): string
    {
        $packed = @inet_pton($ip);
        $normalized = $packed === false ? $ip : bin2hex($packed);

        return hash_hmac('sha256', $normalized, $this->key);
    }
}

