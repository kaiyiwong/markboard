<?php

namespace App\Markdown;

/**
 * A markdown table found by its column names: the header line, the separator line under it,
 * and the `|` lines that follow without a break. Every other line is free text and is never read.
 *
 * A table line is one that starts with `|`, after any leading whitespace. Its cells come from
 * splitting on `|` after removing one leading and one trailing `|`, each trimmed. Escaped pipes
 * (`\|`) aren't supported.
 */
final readonly class Table
{
    private const string SEPARATOR_CELL = '/\A:?-+:?\z/';

    /**
     * @param  list<string>  $columns  the header's names, in the file's order
     * @param  list<array{index: int, cells: list<string>}>  $rows  each data row's line index and cells
     */
    private function __construct(
        public int $headerIndex,
        public bool $hasSeparator,
        public array $columns,
        public array $rows,
    ) {}

    /**
     * The first table whose header cells are exactly these names, in any order. Without a separator
     * line under the header, the `|` lines straight after it are read as rows, so a broken file still
     * shows what it can.
     *
     * @param  list<string>  $names
     */
    public static function find(Lines $lines, array $names): ?self
    {
        $sorted = $names;
        sort($sorted);
        $count = count($lines->lines);

        for ($header = 0; $header < $count; $header++) {
            $columns = self::cellsOf($lines->lines[$header]);
            if ($columns === null) {
                continue;
            }
            $found = $columns;
            sort($found);
            if ($found !== $sorted) {
                continue;
            }

            $separator = $header + 1 < $count ? self::cellsOf($lines->lines[$header + 1]) : null;
            $hasSeparator = $separator !== null && count($separator) === count($columns)
                && array_filter($separator, fn (string $cell): bool => preg_match(self::SEPARATOR_CELL, $cell) !== 1) === [];

            $rows = [];
            for ($index = $header + ($hasSeparator ? 2 : 1); $index < $count; $index++) {
                $cells = self::cellsOf($lines->lines[$index]);
                if ($cells === null) {
                    break;
                }
                $rows[] = ['index' => $index, 'cells' => $cells];
            }

            return new self($header, $hasSeparator, $columns, $rows);
        }

        return null;
    }

    /**
     * A line's cells, or null if it isn't a table line.
     *
     * @return list<string>|null
     */
    public static function cellsOf(Line $line): ?array
    {
        $text = Text::strip(mb_scrub($line->content, 'UTF-8'));
        if (! str_starts_with($text, '|')) {
            return null;
        }
        $text = substr($text, 1);
        if (str_ends_with($text, '|')) {
            $text = substr($text, 0, -1);
        }

        return array_map(Text::strip(...), explode('|', $text));
    }

    /** @param  list<string>  $cells */
    public static function render(array $cells): string
    {
        return '| '.implode(' | ', $cells).' |';
    }

    /** The line a new row goes after: the last row, or the separator if there are none. */
    public function lastIndex(): int
    {
        return $this->rows === [] ? $this->headerIndex + 1 : $this->rows[count($this->rows) - 1]['index'];
    }
}
