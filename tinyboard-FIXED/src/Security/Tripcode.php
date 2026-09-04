<?php

declare(strict_types=1);

namespace Newboard\Security;

/**
 * Secure tripcode: Name#secret → name + !trip
 */
final class Tripcode
{
    public function __construct(private readonly string $salt)
    {
    }

    /**
     * @return array{name: string, trip: string}
     */
    public function parse(string $rawName): array
    {
        $rawName = trim($rawName);
        if ($rawName === '') {
            return ['name' => '', 'trip' => ''];
        }

        if (!str_contains($rawName, '#')) {
            return ['name' => mb_substr($rawName, 0, 50), 'trip' => ''];
        }

        [$name, $secret] = explode('#', $rawName, 2);
        $name = mb_substr(trim($name), 0, 50);
        $secret = trim($secret);
        if ($secret === '') {
            return ['name' => $name, 'trip' => ''];
        }

        // Secure trip only (## or #)
        $secret = ltrim($secret, '#');
        $hash = substr(hash_hmac('sha256', $secret, $this->salt), 0, 10);

        return ['name' => $name, 'trip' => '!' . $hash];
    }
}
