<?php

namespace App\Actions;

use App\Http\Resources\PipelineRowResource;
use App\Markdown\PipelineEditor;
use App\Markdown\PipelineFile;
use App\Models\SourceFile;
use App\Sync\SourceKind;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/** Changes only the fields given in row `position`, rewritten in the header's column order. */
final readonly class EditPipelineRow implements FileEdit
{
    /**
     * @param  array{company?: string, role?: string, stage?: string, next_action?: string, date?: string|null}  $changes
     */
    public function __construct(
        public int $position,
        public array $changes,
    ) {}

    public function operation(): Operation
    {
        return Operation::EditPipelineRow;
    }

    public function kind(): SourceKind
    {
        return SourceKind::Pipeline;
    }

    public function summary(): string
    {
        return "Edit row {$this->position}";
    }

    public function apply(string $bytes, DateTimeInterface $today): string
    {
        return (new PipelineEditor)->editRow(PipelineFile::parse($bytes), $this->position, $this->changes);
    }

    /** Rows have no IDs, so the edit depends on the row line at the same position [D9]. */
    public function dependsOn(string $bytes): ?string
    {
        $file = PipelineFile::parse($bytes);
        $row = $file->row($this->position);

        return $row === null ? null : $file->lines->lines[$row->index]->bytes();
    }

    public function current(SourceFile $file): ?JsonResource
    {
        $row = $file->pipelineRows()->where('position', $this->position)->first();

        return $row === null ? null : PipelineRowResource::make($row);
    }

    public function result(SourceFile $file): JsonResource
    {
        return PipelineRowResource::make($file->pipelineRows()->where('position', $this->position)->sole());
    }
}
