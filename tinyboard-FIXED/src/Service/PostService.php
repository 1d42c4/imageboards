<?php

declare(strict_types=1);

namespace Newboard\Service;

use Newboard\Config;
use Newboard\Repository\BoardRepository;
use Newboard\Repository\PostRepository;
use Newboard\Security\Csrf;
use Newboard\Security\SessionCooldown;
use Newboard\Security\Tripcode;
use Newboard\Support\ImageProcessor;
use Newboard\Support\Markup;

final class PostService
{
    public function __construct(
        private readonly Config $config,
        private readonly BoardRepository $boards,
        private readonly PostRepository $posts,
        private readonly Markup $markup,
        private readonly ImageProcessor $images,
        private readonly Tripcode $tripcode,
        private readonly Csrf $csrf,
        private readonly SessionCooldown $cooldown,
        private readonly ?ArchiveService $archive = null,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file $_FILES['file'] or null
     * @return array{ok: true, board: string, thread: int, id: int}|array{ok: false, error: string}
     */
    public function create(array $input, ?array $file): array
    {
        if (!$this->csrf->validate(isset($input['csrf']) ? (string) $input['csrf'] : null)) {
            return ['ok' => false, 'error' => 'Invalid security token. Reload and try again.'];
        }

        $honeypot = $this->config->string('abuse.honeypot_field', 'website');
        if ($honeypot !== '' && !empty($input[$honeypot])) {
            return ['ok' => false, 'error' => 'Rejected.'];
        }

        if ($msg = $this->cooldown->check()) {
            return ['ok' => false, 'error' => $msg];
        }

        $boardUri = strtolower(trim((string) ($input['board'] ?? '')));
        if ($boardUri === '' || !preg_match('/^[a-z0-9_-]{1,30}$/', $boardUri)) {
            return ['ok' => false, 'error' => 'Invalid board.'];
        }

        $board = $this->boards->find($boardUri);
        if ($board === null) {
            return ['ok' => false, 'error' => 'Board not found.'];
        }

        if ((int) ($board['require_password'] ?? 0) === 1) {
            $hash = (string) ($board['password_hash'] ?? '');
            $given = (string) ($input['board_password'] ?? '');
            if ($hash === '' || !password_verify($given, $hash)) {
                return ['ok' => false, 'error' => 'Board password required or incorrect.'];
            }
        }

        $threadId = isset($input['thread']) && $input['thread'] !== '' && $input['thread'] !== null
            ? (int) $input['thread']
            : null;

        $op = null;
        if ($threadId !== null) {
            $op = $this->posts->find($boardUri, $threadId);
            if ($op === null || $op['thread_id'] !== null) {
                return ['ok' => false, 'error' => 'Thread not found.'];
            }
            if ((int) $op['locked'] === 1) {
                return ['ok' => false, 'error' => 'Thread is locked.'];
            }
            if ((int) ($op['archived'] ?? 0) === 1) {
                return ['ok' => false, 'error' => 'Thread is archived (read-only).'];
            }
        }

        $body = trim((string) ($input['body'] ?? ''));
        $maxBody = $this->config->int('board.max_body', 8000);
        if ($body === '' && ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
            return ['ok' => false, 'error' => 'Comment or image required.'];
        }
        if (mb_strlen($body) > $maxBody) {
            return ['ok' => false, 'error' => 'Comment too long.'];
        }

        $parsed = $this->tripcode->parse((string) ($input['name'] ?? ''));
        $email = mb_substr(trim((string) ($input['email'] ?? '')), 0, 60);
        $subject = mb_substr(trim((string) ($input['subject'] ?? '')), 0, 100);
        $sage = strtolower($email) === 'sage' || !empty($input['sage']);

        $image = null;
        try {
            if ($file !== null) {
                $image = $this->images->store($file, $boardUri);
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if ($threadId === null && (int) ($board['force_image_op'] ?? 0) === 1 && $image === null) {
            return ['ok' => false, 'error' => 'Image required to start a thread.'];
        }

        $now = time();
        $pending = (int) ($board['require_approval'] ?? 0) === 1 ? 1 : 0;
        $bodyHtml = $this->markup->format($body, $boardUri);

        $row = [
            'board_uri' => $boardUri,
            'thread_id' => $threadId,
            'time' => $now,
            'bump' => $now,
            'name' => $parsed['name'],
            'trip' => $parsed['trip'],
            'email' => $email,
            'subject' => $subject,
            'body' => $body,
            'body_html' => $bodyHtml,
            'file_path' => $image['file_path'] ?? null,
            'file_orig' => $image['file_orig'] ?? null,
            'file_size' => $image['file_size'] ?? null,
            'file_width' => $image['file_width'] ?? null,
            'file_height' => $image['file_height'] ?? null,
            'thumb_path' => $image['thumb_path'] ?? null,
            'thumb_width' => $image['thumb_width'] ?? null,
            'thumb_height' => $image['thumb_height'] ?? null,
            'sticky' => 0,
            'locked' => 0,
            'sage' => $sage ? 1 : 0,
            'pending' => $pending,
            'capcode' => '',
        ];

        $id = $this->posts->insert($row);

        if ($pending === 0 && $threadId !== null && !$sage) {
            // bumplock on OP prevents bump (enforced in repository)
            $this->posts->bumpThread($boardUri, $threadId, $now);
        }

        $this->cooldown->touch();

        // New OP may push old threads off the board → archive (vichanBEST1 clean())
        if ($threadId === null && $pending === 0 && $this->archive !== null) {
            $this->archive->pruneBoard($boardUri, $id);
        }

        $thread = $threadId ?? $id;

        return ['ok' => true, 'board' => $boardUri, 'thread' => $thread, 'id' => $id];
    }
}
