<?php

declare(strict_types=1);

namespace Newboard\Security;

/**
 * Privacy policy helpers. Client addresses must never be read for storage.
 */
final class Privacy
{
    /** Headers that must never be used for identity, bans, or rate limits. */
    private const FORBIDDEN_SERVER_KEYS = [
        'REMOTE_ADDR',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CF_CONNECTING_IP',
        'HTTP_TRUE_CLIENT_IP',
        'HTTP_X_CLIENT_IP',
        'HTTP_CLIENT_IP',
        'HTTP_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
    ];

    /**
     * Returns null always — API exists so call sites cannot "forget" and use REMOTE_ADDR.
     * Intentionally does not read any client address.
     */
    public static function clientAddress(): never
    {
        throw new \LogicException(
            'Client IP must never be collected. Use captcha, board password, approval, or session cooldown.'
        );
    }

    /** @param array<string, mixed> $row */
    public static function assertNoIpColumns(array $row): void
    {
        foreach (array_keys($row) as $key) {
            $k = strtolower((string) $key);
            if ($k === 'ip' || str_ends_with($k, '_ip') || str_contains($k, 'ip_address') || $k === 'remote_addr') {
                throw new \LogicException('IP-like column detected in data row: ' . $key);
            }
        }
    }

    /** @return list<string> */
    public static function forbiddenServerKeys(): array
    {
        return self::FORBIDDEN_SERVER_KEYS;
    }
}
