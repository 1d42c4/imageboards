<?php

declare(strict_types=1);

namespace Chessboard\Security;

use Chessboard\Http\HttpException;

final class Captcha
{
    private const SESSION_KEY = '_report_captchas';
    private const LIFETIME = 1800;
    private const MAX_CHALLENGES = 64;

    public function challenge(string $purpose): array
    {
        $this->cleanup();

        $left = random_int(1, 9);
        $right = random_int(1, 9);
        $token = bin2hex(random_bytes(16));
        $challenges = $this->challenges();
        $challenges[$token] = [
            'answer' => (string) ($left + $right),
            'purpose' => $purpose,
            'created_at' => time(),
        ];

        if (count($challenges) > self::MAX_CHALLENGES) {
            uasort(
                $challenges,
                static fn (array $a, array $b): int => ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0),
            );
            $challenges = array_slice($challenges, -self::MAX_CHALLENGES, null, true);
        }

        $_SESSION[self::SESSION_KEY] = $challenges;

        return [
            'token' => $token,
            'question' => sprintf('%d + %d = ?', $left, $right),
        ];
    }

    public function validate(string $token, string $answer, string $purpose): void
    {
        $this->cleanup();
        $challenges = $this->challenges();
        $challenge = $token === '' ? null : ($challenges[$token] ?? null);

        if ($token !== '') {
            unset($challenges[$token]);
            $_SESSION[self::SESSION_KEY] = $challenges;
        }

        $valid = is_array($challenge)
            && ($challenge['purpose'] ?? null) === $purpose
            && is_string($challenge['answer'] ?? null)
            && $answer !== ''
            && hash_equals($challenge['answer'], $answer);

        if (!$valid) {
            throw new HttpException(422, 'The human-check answer was incorrect. Go back, reopen Report, and try again.');
        }
    }

    private function cleanup(): void
    {
        $cutoff = time() - self::LIFETIME;
        $challenges = array_filter(
            $this->challenges(),
            static fn (mixed $challenge): bool => is_array($challenge)
                && is_int($challenge['created_at'] ?? null)
                && $challenge['created_at'] >= $cutoff,
        );
        $_SESSION[self::SESSION_KEY] = $challenges;
    }

    private function challenges(): array
    {
        $challenges = $_SESSION[self::SESSION_KEY] ?? [];

        return is_array($challenges) ? $challenges : [];
    }
}
