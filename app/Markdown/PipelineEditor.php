<?php

namespace App\Markdown;

/**
 * The pipeline operations: edit a row in place, or add one at the end of the table.
 *
 * Like TasksEditor, nothing is re-rendered: one row line is replaced or inserted, and every other
 * line keeps its exact bytes. A rewritten row is rendered in the header's column order, and the
 * result must parse back with that row exactly as meant, or the edit is refused.
 */
final readonly class PipelineEditor
{
    /** Request fields to column names. */
    private const array FIELDS = [
        'company' => 'company',
        'role' => 'role',
        'stage' => 'stage',
        'next_action' => 'next action',
        'date' => 'date',
    ];

    /**
     * Changes only the fields given; a null or empty `date` empties it.
     *
     * @param  array{company?: string, role?: string, stage?: string, next_action?: string, date?: string|null}  $changes
     */
    public function editRow(PipelineFile $file, int $position, array $changes): string
    {
        $table = $this->table($file);
        $row = $file->row($position) ?? throw new RowNotFound($position);
        $cells = $this->withChanges($row->cells(), $changes);
        if ($cells === $row->cells()) {
            return $file->lines->finish($file->lines->forEditing());
        }

        $lines = $file->lines->forEditing();
        $lines[$row->index] = new Line($this->render($table, $cells), $lines[$row->index]->terminator);

        return $this->readBack($file, $lines, $position, $cells);
    }

    /**
     * A new row after the last one, or directly after the separator if the table has none.
     *
     * @param  array{company: string, role: string, stage: string, next_action: string, date: string|null}  $fields
     */
    public function addRow(PipelineFile $file, array $fields): string
    {
        $table = $this->table($file);
        $cells = $this->withChanges(array_fill_keys(PipelineFile::COLUMNS, ''), $fields);

        $lines = $file->lines->forEditing();
        array_splice($lines, $table->lastIndex() + 1, 0, [
            new Line($this->render($table, $cells), $file->lines->dominantTerminator()),
        ]);

        return $this->readBack($file, $lines, count($table->rows) + 1, $cells);
    }

    private function table(PipelineFile $file): Table
    {
        if (! $file->isEditable() || $file->table === null) {
            throw new FileNotEditable;
        }

        return $file->table;
    }

    /**
     * @param  array<string, string>  $cells  keyed by column name
     * @param  array<string, string|null>  $changes  keyed by request field
     * @return array<string, string>
     */
    private function withChanges(array $cells, array $changes): array
    {
        foreach ($changes as $field => $value) {
            $cells[self::FIELDS[$field]] = $value ?? '';
        }

        return $cells;
    }

    /** @param  array<string, string>  $cells */
    private function render(Table $table, array $cells): string
    {
        return Table::render(array_map(fn (string $column): string => $cells[$column], $table->columns));
    }

    /**
     * Joins the lines, and refuses the result unless it parses with no errors and the row reads back as meant.
     *
     * @param  list<Line>  $lines
     * @param  array<string, string>  $cells
     */
    private function readBack(PipelineFile $file, array $lines, int $position, array $cells): string
    {
        $result = $file->lines->finish($lines);
        $after = PipelineFile::parse($result);
        if ($after->errors !== []) {
            throw new EditRefused("Row {$position} would not be valid: {$after->errors[0]->message}.");
        }
        if ($after->row($position)?->cells() !== $cells) {
            throw new EditRefused("Row {$position} would not read back as written: a value has a | or spaces around it.");
        }

        return $result;
    }
}
