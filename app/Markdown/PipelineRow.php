<?php

namespace App\Markdown;

/**
 * One data row of a pipeline table, with its cells read by column name. Values are as written:
 * in a file with errors, `stage` may not be a known stage and `date` may not be a real date.
 */
final readonly class PipelineRow
{
    public function __construct(
        public int $position,
        public int $index,
        public string $company,
        public string $role,
        public string $stage,
        public string $nextAction,
        public string $date,
    ) {}

    /**
     * @param  int  $position  1-based, among the table's data rows
     * @param  int  $index  the row's line index in the file
     * @param  array<string, string>  $cells  keyed by column name
     */
    public static function fromCells(int $position, int $index, array $cells): self
    {
        return new self($position, $index, $cells['company'], $cells['role'], $cells['stage'], $cells['next action'], $cells['date']);
    }

    /** @return array<string, string> keyed by column name */
    public function cells(): array
    {
        return [
            'company' => $this->company,
            'role' => $this->role,
            'stage' => $this->stage,
            'next action' => $this->nextAction,
            'date' => $this->date,
        ];
    }
}
