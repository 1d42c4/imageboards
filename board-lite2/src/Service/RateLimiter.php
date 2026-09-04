<?php

declare(strict_types=1);

namespace Chessboard\Service;

use Chessboard\Database;
use Chessboard\Http\HttpException;
use PDO;

final readonly class RateLimiter
{
    public function __construct(private Database $database)
    {
    }

    public function assertAllowed(string $identity, string $action, int $windowSeconds, int $limit): void
    {
        $this->database->immediate(function (PDO $pdo) use (
            $identity,
            $action,
            $windowSeconds,
            $limit,
        ): void {
            $now = time();
            $query = $pdo->prepare(
                'SELECT window_started_at, hits
                 FROM rate_limits
                 WHERE identity_key = :identity AND action = :action'
            );
            $query->execute(['identity' => $identity, 'action' => $action]);
            $record = $query->fetch();

            if ($record === false || (int) $record['window_started_at'] <= $now - $windowSeconds) {
                $reset = $pdo->prepare(
                    'INSERT INTO rate_limits (identity_key, action, window_started_at, hits)
                     VALUES (:identity, :action, :started_at, 1)
                     ON CONFLICT(identity_key, action) DO UPDATE SET
                        window_started_at = excluded.window_started_at,
                        hits = 1'
                );
                $reset->execute([
                    'identity' => $identity,
                    'action' => $action,
                    'started_at' => $now,
                ]);

                return;
            }

            if ((int) $record['hits'] >= $limit) {
                $retryAfter = max(1, (int) $record['window_started_at'] + $windowSeconds - $now);
                throw new HttpException(
                    429,
                    sprintf('Too many attempts. Please wait %d seconds and try again.', $retryAfter),
                );
            }

            $update = $pdo->prepare(
                'UPDATE rate_limits
                 SET hits = hits + 1
                 WHERE identity_key = :identity AND action = :action'
            );
            $update->execute(['identity' => $identity, 'action' => $action]);
        });
    }
}

