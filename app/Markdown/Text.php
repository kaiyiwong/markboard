<?php

namespace App\Markdown;

/**
 * Python's idea of whitespace, so PHP reads a line exactly as check-tasks.py does.
 *
 * PCRE's \s, even with /u, misses some characters Python counts (U+001C to U+001F, U+0085),
 * so the set is spelled out.
 */
final class Text
{
    /** Every character for which Python's str.isspace() is true: what str.strip(), \s and \S use. */
    public const string SPACE = '\x{9}-\x{d}\x{1c}-\x{20}\x{85}\x{a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}';

    /** Python's str.strip(). Expects valid UTF-8. */
    public static function strip(string $text): string
    {
        return (string) preg_replace('/\A['.self::SPACE.']+|['.self::SPACE.']+\z/u', '', $text);
    }
}
