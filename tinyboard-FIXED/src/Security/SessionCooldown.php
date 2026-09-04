<?php

declare(strict_types=1);

namespace Newboard\Security;

/**
 * Cooldown between posts for this browser session — not IP-based.
 */
final class SessionCooldown
{
    private const KEY = 'last_post_at';

    public function __construct(
        private readonly Session $session,
        private readonly int $seconds,
    ) {
    }

    public function check(): ?string
    {
        if ($this->seconds <= 0) {
            return null;
        }
        $last = $this->session->get(self::KEY);
        if (!is_int($last) && !is_numeric($last)) {
            return null;
        }
        $last = (int) $last;
        $elapsed = time() - $last;
        if ($elapsed < $this->seconds) {
            $wait = $this->seconds - $elapsed;

            return "Please wait {$wait}s before posting again.";
        }

        return null;
    }

    public function touch(): void
    {
        if ($this->seconds <= 0) {
            return;
        }
        $this->session->set(self::KEY, time());
    }
}
