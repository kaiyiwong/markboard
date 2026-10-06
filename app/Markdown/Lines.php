<?php

namespace App\Markdown;

/**
 * A file split into lines at the same boundaries check-tasks.py uses, so line numbers match its errors.
 *
 * Python opens the file with universal newlines (\r\n and \r become \n) and then calls splitlines(),
 * which also splits at \v, \f, \x1c, \x1d, \x1e, U+0085, U+2028 and U+2029. Each line keeps its own
 * terminator, so joining the lines gives back the exact bytes. Only \n and \r\n files are editable;
 * any other line break, or invalid UTF-8, is a file error.
 */
final readonly class Lines
{
    /** The boundaries as UTF-8 byte sequences, so a file with invalid UTF-8 still splits. */
    private const string BOUNDARY = '/(\r\n|[\n\r\x0B\x0C\x1C\x1D\x1E]|\xC2\x85|\xE2\x80\xA8|\xE2\x80\xA9)/';

    /**
     * @param  list<Line>  $lines
     * @param  list<FormatError>  $errors  Markboard's own file errors (encoding and line breaks)
     */
    private function __construct(
        public array $lines,
        public array $errors,
    ) {}

    public static function split(string $bytes): self
    {
        $parts = preg_split(self::BOUNDARY, $bytes, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [''];
        $lines = [];
        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            $lines[] = new Line($parts[$i], $parts[$i + 1]);
        }
        $last = $parts[count($parts) - 1];
        if ($last !== '') {
            $lines[] = new Line($last, '');
        }

        $errors = [];
        foreach ($lines as $index => $line) {
            if (! mb_check_encoding($line->content, 'UTF-8')) {
                $errors[] = new FormatError($index + 1, 'not valid UTF-8');
            }
            if (! in_array($line->terminator, ["\n", "\r\n", ''], true)) {
                $errors[] = new FormatError($index + 1, sprintf(
                    'line break U+%04X: only \n and \r\n line breaks can be edited',
                    mb_ord($line->terminator, 'UTF-8'),
                ));
            }
        }

        return new self($lines, $errors);
    }

    /**
     * New lines get the more common of \n and \r\n; ties and one-line files get \n.
     */
    public function dominantTerminator(): string
    {
        if (count($this->lines) < 2) {
            return "\n";
        }
        $counts = array_count_values(array_map(fn (Line $line): string => $line->terminator, $this->lines));

        return ($counts["\r\n"] ?? 0) > ($counts["\n"] ?? 0) ? "\r\n" : "\n";
    }

    public function endsWithLineBreak(): bool
    {
        return $this->lines !== [] && $this->lines[count($this->lines) - 1]->terminator !== '';
    }

    /**
     * The lines to edit: if the last line has no terminator, it gets the dominant one here,
     * so moving it can never join it to the next line. finish() takes it off again.
     *
     * @return list<Line>
     */
    public function forEditing(): array
    {
        $lines = $this->lines;
        if ($lines !== [] && ! $this->endsWithLineBreak()) {
            $last = count($lines) - 1;
            $lines[$last] = new Line($lines[$last]->content, $this->dominantTerminator());
        }

        return $lines;
    }

    /**
     * Joins edited lines into the file's bytes, keeping the original's final-newline state.
     *
     * @param  list<Line>  $lines
     */
    public function finish(array $lines): string
    {
        if ($lines !== [] && ! $this->endsWithLineBreak()) {
            $last = count($lines) - 1;
            $lines[$last] = new Line($lines[$last]->content, '');
        }

        return implode('', array_map(fn (Line $line): string => $line->bytes(), $lines));
    }
}
