<?php

namespace App\Actions;

use App\Http\Resources\TaskResource;
use App\Markdown\TasksEditor;
use App\Markdown\TasksFile;
use App\Models\SourceFile;
use App\Sync\SourceKind;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/** A new task at the bottom of Up next, numbered one past the highest ID. */
final readonly class AddTask implements FileEdit
{
    public function __construct(
        public string $title,
        public ?string $due = null,
        public ?string $proof = null,
    ) {}

    public function operation(): Operation
    {
        return Operation::AddTask;
    }

    public function kind(): SourceKind
    {
        return SourceKind::Tasks;
    }

    public function summary(): string
    {
        return "Add \"{$this->title}\"";
    }

    public function apply(string $bytes, DateTimeInterface $today): string
    {
        return (new TasksEditor($today))->add(TasksFile::parse($bytes), $this->title, $this->due, $this->proof);
    }

    /** An Add depends on nothing in the file: it takes the next free ID in whatever version it lands in. */
    public function dependsOn(string $bytes): string
    {
        return '';
    }

    public function current(SourceFile $file): ?JsonResource
    {
        return null;
    }

    /** IDs are never reused, so the new task has the highest number. */
    public function result(SourceFile $file): JsonResource
    {
        return TaskResource::make($file->tasks()->orderByDesc('number')->firstOrFail());
    }
}
