<?php

namespace App\Markdown;

/**
 * A task as it sits in the file: its task line plus the `proof:` and `note:` lines that belong to it.
 *
 * The block runs from the task line to its last proof or note line, so blank lines between them
 * move with it. Indexes are 0-based positions in the file's lines.
 */
final readonly class TaskBlock
{
    /**
     * @param  list<array{index: int, text: string}>  $proofs  in file order; the last one counts
     * @param  list<array{index: int, text: string}>  $notes  in file order
     */
    public function __construct(
        public Section $section,
        public int $start,
        public int $end,
        public TaskLine $line,
        public array $proofs,
        public array $notes,
    ) {}

    public function proof(): ?string
    {
        return $this->proofs === [] ? null : $this->proofs[count($this->proofs) - 1]['text'];
    }

    /** @return list<string> */
    public function notes(): array
    {
        return array_column($this->notes, 'text');
    }
}
