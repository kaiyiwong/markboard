<?php

namespace App\Actions;

use App\Http\Resources\PipelineRowResource;
use App\Markdown\PipelineEditor;
use App\Markdown\PipelineFile;
use App\Models\SourceFile;
use App\Sync\SourceKind;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/** A new row after the table's last one. */
final readonly class AddPipelineRow implements FileEdit
{
    /**
     * @param  array{company: string, role: string, stage: string, next_action: string, date: string|null}  $fields
     */
    public function __construct(
        public array $fields,
    ) {}

    public function operation(): Operation
    {
        return Operation::AddPipelineRow;
    }

    public function kind(): SourceKind
    {
        return SourceKind::Pipeline;
    }

    public function summary(): string
    {
        return "Add {$this->fields['company']}";
    }

    public function apply(string $bytes, DateTimeInterface $today): string
    {
        return (new PipelineEditor)->addRow(PipelineFile::parse($bytes), $this->fields);
    }

    /** An Add depends on nothing in the file: it goes after whatever rows the version has. */
    public function dependsOn(string $bytes): string
    {
        return '';
    }

    public function current(SourceFile $file): ?JsonResource
    {
        return null;
    }

    public function result(SourceFile $file): JsonResource
    {
        return PipelineRowResource::make($file->pipelineRows()->orderByDesc('position')->firstOrFail());
    }
}
