<?php

namespace App\Markdown;

/**
 * The rules a typed value must pass before it goes into a TASKS.md or a pipeline.md, so that anything
 * valid here also passes check-tasks.py, or Markboard's own pipeline checks. The Form Requests call these; they are plain PHP so they are
 * unit-tested without booting the app.
 *
 * Callers trim with trim() first. Each rule returns the problem as a validation message
 * (with Laravel's :attribute placeholder), or null when the value is fine.
 */
final class InputRules
{
    public const int TITLE_MAX = 300;

    public const int TEXT_MAX = 500;

    public const int CELL_MAX = 200;

    /** Line boundaries from the line model and every other control character. */
    private const string CONTROL = '/[\x{0}-\x{1f}\x{7f}\x{85}\x{2028}\x{2029}]/u';

    /** Trims what Python's str.strip() trims, no-break space included, so the checker sees the same text. */
    public static function trim(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? Text::strip($value) : $value;
    }

    public static function title(string $value): ?string
    {
        return self::text($value, self::TITLE_MAX)
            ?? (TaskLine::splitMetadata($value)[1] !== null
                ? 'The :attribute must not end in parentheses that read as task metadata, such as "(due 2026-11-01)".'
                : null);
    }

    /** A proof or a note. */
    public static function proseLine(string $value): ?string
    {
        return self::text($value, self::TEXT_MAX);
    }

    /** `waiting` and `evidence`: they sit inside the metadata parentheses, between commas. */
    public static function metadataValue(string $value): ?string
    {
        return self::text($value, self::TEXT_MAX)
            ?? (strpbrk($value, ',()') !== false ? 'The :attribute must not contain commas or parentheses.' : null);
    }

    /** A pipeline row's company, role or next action: a `|` would split the cell in two. */
    public static function pipelineCell(string $value): ?string
    {
        return self::text($value, self::CELL_MAX)
            ?? (str_contains($value, '|') ? 'The :attribute must not contain a | character.' : null);
    }

    public static function date(string $value): ?string
    {
        return TasksFile::isDate($value) ? null : 'The :attribute must be a real date written as YYYY-MM-DD.';
    }

    private static function text(string $value, int $max): ?string
    {
        return match (true) {
            ! mb_check_encoding($value, 'UTF-8') => 'The :attribute must be valid UTF-8.',
            $value === '' => 'The :attribute must not be empty.',
            mb_strlen($value) > $max => "The :attribute must be at most {$max} characters.",
            preg_match(self::CONTROL, $value) === 1 => 'The :attribute must not contain line breaks or other control characters.',
            default => null,
        };
    }
}
