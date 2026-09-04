<?php

declare(strict_types=1);

namespace Newboard\Service;

use Newboard\Config;
use Newboard\Repository\ModRepository;
use Newboard\Repository\PostRepository;

/**
 * Archive system modeled on vichanBEST1:
 * - Manual archive (mod)
 * - Auto-archive threads that fall past max_pages * threads_per_page
 * - Read-only; images stay on disk
 * - No IP involved
 */
final class ArchiveService
{
    public function __construct(
        private readonly Config $config,
        private readonly PostRepository $posts,
        private readonly ?ModRepository $mods = null,
    ) {
    }

    public function enabled(): bool
    {
        return $this->config->bool('archive.enabled', true);
    }

    /**
     * Mark OP + keep replies; leave live board; set archived_at.
     */
    public function archiveThread(string $boardUri, int $threadId, bool $log = false, string $reason = 'manual'): bool
    {
        if (!$this->enabled()) {
            return false;
        }
        $ok = $this->posts->archiveThread($boardUri, $threadId);
        if ($ok && $log && $this->mods !== null) {
            $this->mods->log(null, 'system', $boardUri, 'auto_archive', "thread #{$threadId} ({$reason})");
        }

        return $ok;
    }

    /**
     * After a new OP, archive overflow threads (sticky never auto-archived).
     *
     * @return list<int> archived thread ids
     */
    public function pruneBoard(string $boardUri, ?int $becauseOfNewOp = null): array
    {
        if (!$this->enabled() || !$this->config->bool('archive.auto', true)) {
            return [];
        }

        $keep = $this->config->int('board.max_pages', 10) * $this->config->int('board.threads_per_page', 10);
        $overflow = $this->posts->threadsPastLimit($boardUri, $keep);
        $archived = [];
        foreach ($overflow as $row) {
            // Never auto-archive stickies
            if ((int) ($row['sticky'] ?? 0) === 1) {
                continue;
            }
            $id = (int) $row['id'];
            $reason = $becauseOfNewOp !== null
                ? "overflow after new thread #{$becauseOfNewOp}"
                : 'overflow';
            if ($this->archiveThread($boardUri, $id, true, $reason)) {
                $archived[] = $id;
            }
        }

        return $archived;
    }
}
