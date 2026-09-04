<?php

declare(strict_types=1);

namespace Newboard\Support;

/**
 * Minimal markup: escape HTML, newlines → <br>, >>123 quote links, **bold**, *em*, spoiler.
 */
final class Markup
{
    public function format(string $body, string $boardUri): string
    {
        $body = str_replace("\r\n", "\n", $body);
        $body = str_replace("\r", "\n", $body);
        $escaped = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // greentext
        $escaped = preg_replace_callback(
            '/^(&gt;(?!&gt;).*)$/m',
            static fn (array $m): string => '<span class="quote">' . $m[1] . '</span>',
            $escaped
        ) ?? $escaped;

        // >>123
        $escaped = preg_replace(
            '/&gt;&gt;(\d+)/',
            '<a class="post-quote" href="#p$1">&gt;&gt;$1</a>',
            $escaped
        ) ?? $escaped;

        // **bold**
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        // *em*
        $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped) ?? $escaped;
        // %%spoiler%%
        $escaped = preg_replace('/%%(.+?)%%/s', '<span class="spoiler">$1</span>', $escaped) ?? $escaped;

        return nl2br($escaped, false);
    }
}
