<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class Formatter
{
    public function __construct(private Config $config)
    {
    }
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
    public function render(string $body, string $board): string
    {
        $lines = explode("\n", $body);
        $html = [];
        foreach ($lines as $line) {
            $content = $this->inline($line, $board);
            $html[] = str_starts_with($line, '>') && !str_starts_with($line, '>>') ? '<span class="quote">' . $content . '</span>' : $content;
        }
        return implode('<br>', $html);
    }
    private function inline(string $line, string $board): string
    {
        $pattern = '~(`[^`\n]+`|\*\*[^*\n]+\*\*|__[^_\n]+__|\[spoiler\].*?\[/spoiler\]|>>[0-9]{1,10}\b|https?://[^\s<>"\x27]+)~u';
        $pieces = preg_split($pattern, $line, flags: PREG_SPLIT_DELIM_CAPTURE) ?: [$line];
        $result = '';
        foreach ($pieces as $index => $piece) {
            if ($index % 2 === 0) {
                $result .= self::escape($piece);
                continue;
            }
            $result .= match (true) {
                str_starts_with($piece, '`') => '<code>' . self::escape(substr($piece, 1, -1)) . '</code>',
                str_starts_with($piece, '**') => '<strong>' . self::escape(substr($piece, 2, -2)) . '</strong>',
                str_starts_with($piece, '__') => '<em>' . self::escape(substr($piece, 2, -2)) . '</em>',
                str_starts_with($piece, '[spoiler]') => '<span class="spoiler">' . self::escape(substr($piece, 9, -10)) . '</span>',
                str_starts_with($piece, '>>') => '<a class="quotelink" href="' . self::escape($this->config->url('read.php?board=' . rawurlencode($board) . '&post=' . substr($piece, 2))) . '">' . self::escape($piece) . '</a>',
                default => '<a href="' . self::escape($piece) . '" rel="nofollow noreferrer noopener ugc">' . self::escape($piece) . '</a>',
            };
        }
        return $result;
    }
}
