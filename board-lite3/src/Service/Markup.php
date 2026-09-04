<?php

declare(strict_types=1);

namespace Chessboard\Service;

use Chessboard\Config;

final readonly class Markup
{
    public function __construct(private Config $config)
    {
    }

    public function render(string $body, string $boardSlug): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body) ?? $body;
        $segments = preg_split(
            '/(\[pgn\].*?\[\/pgn\])/is',
            $clean,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        ) ?: [$clean];

        $output = '';
        foreach ($segments as $segment) {
            if (preg_match('/^\[pgn\](.*?)\[\/pgn\]$/is', $segment, $match)) {
                $output .= '<pre class="pgn" aria-label="PGN notation">' .
                    htmlspecialchars(trim($match[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
                    '</pre>';
                continue;
            }

            $output .= $this->renderText($segment, $boardSlug);
        }

        return $output;
    }

    public function references(string $body, string $currentBoard): array
    {
        preg_match_all(
            '/>>>\/(?P<board>[a-z0-9][a-z0-9-]{0,31})\/(?P<cross>\d+)|(?<!>)>>(?P<local>\d+)/i',
            $body,
            $matches,
            PREG_SET_ORDER,
        );

        $references = [];
        foreach ($matches as $match) {
            $board = ($match['board'] ?? '') !== '' ? strtolower($match['board']) : $currentBoard;
            $postNo = (int) (($match['cross'] ?? '') !== '' ? $match['cross'] : $match['local']);
            if ($postNo < 1) {
                continue;
            }

            $references[$board . ':' . $postNo] = ['board' => $board, 'post_no' => $postNo];
        }

        return array_values($references);
    }

    private function renderText(string $text, string $boardSlug): string
    {
        $links = [];
        $tokenized = preg_replace_callback(
            '/>>>\/(?P<board>[a-z0-9][a-z0-9-]{0,31})\/(?P<cross>\d+)|(?<!>)>>(?P<local>\d+)/i',
            function (array $match) use ($boardSlug, &$links): string {
                $targetBoard = ($match['board'] ?? '') !== '' ? strtolower($match['board']) : $boardSlug;
                $postNo = (int) (($match['cross'] ?? '') !== '' ? $match['cross'] : $match['local']);
                $label = ($match['board'] ?? '') !== ''
                    ? sprintf('>>>/%s/%d', $targetBoard, $postNo)
                    : sprintf('>>%d', $postNo);
                $token = sprintf('___CHESSBOARD_LINK_%d___', count($links));
                $links[$token] = sprintf(
                    '<a class="post-reference" href="%s">%s</a>',
                    htmlspecialchars(
                        $this->url(sprintf('/%s/post/%d', rawurlencode($targetBoard), $postNo)),
                        ENT_QUOTES,
                        'UTF-8',
                    ),
                    htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                );

                return $token;
            },
            $text,
        ) ?? $text;

        $escaped = htmlspecialchars($tokenized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lines = preg_split('/\R/u', $escaped) ?: [$escaped];
        foreach ($lines as &$line) {
            if (str_starts_with($line, '&gt;')) {
                $line = '<span class="quote">' . $line . '</span>';
            }
        }
        unset($line);

        return strtr(implode('<br>', $lines), $links);
    }

    private function url(string $path): string
    {
        return $this->config->basePath() . $path;
    }
}
