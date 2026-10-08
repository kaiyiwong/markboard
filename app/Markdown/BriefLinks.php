<?php

namespace App\Markdown;

/**
 * Turns a brief's task references, such as `[lantern] T12`, into Markdown links to that task's
 * page, before the brief is rendered. The link text keeps the reference as written.
 */
final class BriefLinks
{
    private const string REFERENCE = '/\[([a-z0-9]+(?:-[a-z0-9]+)*)\] (T[0-9]+)\b/';

    public static function apply(string $markdown): string
    {
        return (string) preg_replace_callback(
            self::REFERENCE,
            fn (array $match): string => "[\\[{$match[1]}\\] {$match[2]}](/projects/{$match[1]}/tasks/{$match[2]})",
            $markdown,
        );
    }
}
