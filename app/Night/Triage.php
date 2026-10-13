<?php

namespace App\Night;

/**
 * The chief of staff's triage note (`orders/triage-<date>.md`): what it picked for the night, what it
 * carried over, what it sent back to Kai, what it passed over and why, and the tasks that look done.
 *
 * Its sections hold bullets naming tasks (`- **fieldnotes T3**: …`, `- **T6** …`, `- T4, T11: …`).
 * Optional `key: value` lines indented under a bullet add structure for the page (docs/spec.md, Night):
 * `short:` and `run:` on picked and carried-over tasks, `reason:` on passed-over ones, and under
 * `## Quick closes`, `short:` and `why:`, plus the `kept: <date>` line Markboard writes when Kai keeps one.
 */
final readonly class Triage
{
    /** Section headings, by the key the page uses. */
    public const array SECTIONS = [
        'Picked' => 'picked',
        'Carried over' => 'carried',
        'Sent back to Kai' => 'sent_back',
        'Passed over' => 'passed_over',
        'Quick closes' => 'quick_closes',
        'Seen, not a task' => 'seen',
        'Held' => 'held',
    ];

    /** A night task ID as the runner writes it: T0085, T12, 0048-action-errors. */
    private const string ID = '(?:T\d+|\d+(?:-[A-Za-z0-9-]+)?)';

    /**
     * @param  array<string, list<array{project: string, tasks: list<string>, text: string, fields: array<string, string>}>>  $sections  by key
     */
    public function __construct(public array $sections) {}

    public static function parse(string $text): self
    {
        $sections = [];
        $current = null;
        foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $line) {
            if (preg_match('/^## +(.+?)\s*$/', $line, $match) === 1) {
                $current = self::SECTIONS[$match[1]] ?? null;
                if ($current !== null) {
                    $sections[$current] = [];
                }
            } elseif ($current !== null && str_starts_with($line, '- ')) {
                $sections[$current][] = self::item(substr($line, 2));
            } elseif ($current !== null && $sections[$current] !== [] && preg_match('/^\s+([a-z][a-z ]*): *(.*?)\s*$/', $line, $match) === 1) {
                $sections[$current][array_key_last($sections[$current])]['fields'][$match[1]] = $match[2];
            }
        }

        return new self($sections);
    }

    /** Whether the chief of staff wrote any of the structure the page lays out. */
    public function structured(): bool
    {
        foreach ($this->sections as $key => $items) {
            if ($key === 'quick_closes' && $items !== []) {
                return true;
            }
            foreach ($items as $item) {
                if ($item['fields'] !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The note with a `kept: <date>` line under the quick close for this task, after its other
     * indented lines; every other byte unchanged. Null when there's no such quick close, or it's kept.
     */
    public static function withKept(string $text, string $project, string $task, string $date): ?string
    {
        $lines = preg_split('/(?<=\n)/', $text) ?: [];
        $section = null;
        $item = null;
        foreach ($lines as $i => $line) {
            $bare = rtrim($line, "\r\n");
            if (preg_match('/^## +(.+?)\s*$/', $bare, $match) === 1) {
                $section = $match[1];
                if ($item !== null) {
                    break;
                }

                continue;
            }
            if ($item !== null) {
                if (preg_match('/^\s+kept:/', $bare) === 1) {
                    return null;
                }
                if (preg_match('/^\s+\S/', $bare) !== 1) {
                    break;
                }
                $item = $i;

                continue;
            }
            if ($section === 'Quick closes' && str_starts_with($bare, '- ')) {
                $parsed = self::item(substr($bare, 2));
                if ($parsed['tasks'] === [$task] && in_array($parsed['project'], [$project, ''], true)) {
                    $item = $i;
                }
            }
        }
        if ($item === null) {
            return null;
        }
        $newline = str_contains($text, "\r\n") ? "\r\n" : "\n";
        if (! str_ends_with($lines[$item], "\n")) {
            $lines[$item] .= $newline;
        }
        array_splice($lines, $item + 1, 0, ["  kept: {$date}{$newline}"]);

        return implode('', $lines);
    }

    /**
     * One bullet: the project and task IDs it names, bold or leading, and the rest of its text.
     *
     * @return array{project: string, tasks: list<string>, text: string, fields: array<string, string>}
     */
    private static function item(string $bullet): array
    {
        $id = self::ID;
        if (preg_match("/^\*\*(?:([a-z0-9][a-z0-9-]*) )?({$id})\*\*:? *(.*)$/", $bullet, $match) === 1) {
            return ['project' => $match[1], 'tasks' => [$match[2]], 'text' => $match[3], 'fields' => []];
        }
        if (preg_match("/^({$id}(?:, *{$id})*)(?=[ :])[ :] *(.*)$/", $bullet, $match) === 1) {
            return ['project' => '', 'tasks' => preg_split('/, */', $match[1]) ?: [], 'text' => $match[2], 'fields' => []];
        }

        return ['project' => '', 'tasks' => [], 'text' => $bullet, 'fields' => []];
    }
}
