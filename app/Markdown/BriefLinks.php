<?php

namespace App\Markdown;

/**
 * Turns a brief's project tags into Markdown links before the brief is rendered: `[lantern]` links
 * to the project, shown by its name, and a task ID right after it (`[lantern] T12`) links to that
 * task. Only registered projects are linked, so a checkbox such as `[x]` or an unknown tag stays
 * text, and the page never links to a 404.
 */
final class BriefLinks
{
    /** A kebab-case tag in brackets, not already a link, then a space or the end, then perhaps a task ID. */
    private const string REFERENCE = '/\[([a-z0-9]+(?:-[a-z0-9]+)*)\](?=\s|$)(?: (T[0-9]+)\b)?/m';

    /**
     * @param  array<string, string>  $projects  the registered projects' names, keyed by id
     */
    public static function apply(string $markdown, array $projects): string
    {
        return (string) preg_replace_callback(
            self::REFERENCE,
            function (array $match) use ($projects): string {
                [$tag, $id] = [$match[0], $match[1]];
                if (! isset($projects[$id])) {
                    return $tag;
                }
                $link = '['.self::escape($projects[$id])."](/projects/{$id})";

                return isset($match[2]) ? "{$link} [{$match[2]}](/projects/{$id}/tasks/{$match[2]})" : $link;
            },
            $markdown,
        );
    }

    /** A project name as Markdown link text: its punctuation stays literal. */
    private static function escape(string $text): string
    {
        return (string) preg_replace('/[\\\\`*_\[\]<>!]/', '\\\\$0', $text);
    }
}
