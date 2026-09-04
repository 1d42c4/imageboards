<?php

declare(strict_types=1);

namespace Chessboard\Service;

final readonly class Markup
{
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

            $output .= $this->renderText($segment);
        }

        return $output;
    }

    private function renderText(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lines = preg_split('/\R/u', $escaped) ?: [$escaped];
        foreach ($lines as &$line) {
            if (str_starts_with($line, '&gt;')) {
                $line = '<span class="quote">' . $line . '</span>';
            }
        }
        unset($line);

        return implode('<br>', $lines);
    }
}
