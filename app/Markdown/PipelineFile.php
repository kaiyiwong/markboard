<?php

namespace App\Markdown;

/**
 * A parsed pipeline.md: its lines, the pipeline table and its rows, and the format errors.
 *
 * No external checker exists for this format, so Markboard owns its checks. Parsing is best-effort:
 * a file with errors still yields every row it can read, but only a file with no errors is editable.
 */
final readonly class PipelineFile
{
    /** The header's names; the file may put them in any order. */
    public const array COLUMNS = ['company', 'role', 'stage', 'next action', 'date'];

    /**
     * @param  list<PipelineRow>  $rows  in file order; a row with the wrong number of cells is left out
     * @param  list<FormatError>  $errors  format errors, by line
     */
    private function __construct(
        public Lines $lines,
        public ?Table $table,
        public array $rows,
        public array $errors,
    ) {}

    public static function parse(string $bytes): self
    {
        $lines = Lines::split($bytes);
        $table = Table::find($lines, self::COLUMNS);
        if ($table === null) {
            return new self($lines, null, [], [
                new FormatError(0, 'no table with the columns '.implode(', ', self::COLUMNS)),
            ]);
        }

        $errors = [];
        if (! $table->hasSeparator) {
            $errors[] = new FormatError($table->headerIndex + 1, 'no separator line under the table header');
        }

        $rows = [];
        foreach ($table->rows as $i => ['index' => $index, 'cells' => $cells]) {
            $n = $index + 1;
            if (count($cells) !== count($table->columns)) {
                $errors[] = new FormatError($n, sprintf('row has %d %s, the header has %d', count($cells), count($cells) === 1 ? 'cell' : 'cells', count($table->columns)));

                continue;
            }
            $row = PipelineRow::fromCells($i + 1, $index, array_combine($table->columns, $cells));
            if (Stage::tryFrom($row->stage) === null) {
                $errors[] = new FormatError($n, "stage \"{$row->stage}\" is not one of ".implode(', ', array_column(Stage::cases(), 'value')));
            }
            if ($row->date !== '' && ! TasksFile::isDate($row->date)) {
                $errors[] = new FormatError($n, "bad date: {$row->date}");
            }
            $rows[] = $row;
        }

        return new self($lines, $table, $rows, $errors);
    }

    /** Only a file with a table, no format errors and no file errors (encoding, line breaks) is ever written. */
    public function isEditable(): bool
    {
        return $this->table !== null && $this->errors === [] && $this->lines->errors === [];
    }

    /** The row at this 1-based position among the table's data rows. */
    public function row(int $position): ?PipelineRow
    {
        foreach ($this->rows as $row) {
            if ($row->position === $position) {
                return $row;
            }
        }

        return null;
    }
}
