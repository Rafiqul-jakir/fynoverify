<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Support;

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Output sanitizers. Route metadata and config strings are untrusted input.
 */
final class Text
{
    /**
     * Collapse control characters (including newlines and ANSI escapes) to single spaces.
     */
    public static function plain(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));
    }

    /**
     * Safe for console table cells: no control characters and no formatter tags.
     */
    public static function terminal(string $value): string
    {
        return OutputFormatter::escape(self::plain($value));
    }

    /**
     * Safe for a Markdown table cell or inline text: cannot break the table,
     * inject links/emphasis, or emit raw HTML.
     */
    public static function markdown(string $value): string
    {
        return strtr(self::plain($value), [
            '\\' => '\\\\',
            '|' => '\\|',
            '`' => '\\`',
            '*' => '\\*',
            '_' => '\\_',
            '[' => '\\[',
            ']' => '\\]',
            '<' => '&lt;',
            '>' => '&gt;',
            '&' => '&amp;',
        ]);
    }

    public static function truncate(string $value, int $max): string
    {
        return mb_strimwidth($value, 0, $max, '…');
    }
}
