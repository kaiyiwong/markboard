<?php

namespace App\Actions;

use App\Http\Resources\TaskResource;
use App\Markdown\TaskBlock;
use App\Markdown\TasksEditor;
use App\Markdown\TasksFile;
use App\Models\SourceFile;
use App\Sync\SourceKind;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An edit to one existing task, found by its number. By default it depends on the task's block
 * and the section it is in: if either changed on disk, the edit can't be applied to the new version.
 */
abstract readonly class TaskEdit implements FileEdit
{
    public function __construct(
        public int $number,
    ) {}

    abstract protected function edit(TasksEditor $editor, TasksFile $file): string;

    public function kind(): SourceKind
    {
        return SourceKind::Tasks;
    }

    public function apply(string $bytes, DateTimeInterface $today): string
    {
        return $this->edit(new TasksEditor($today), TasksFile::parse($bytes));
    }

    public function dependsOn(string $bytes): ?string
    {
        $file = TasksFile::parse($bytes);
        $block = $file->task($this->number);

        return $block === null ? null : $block->section->value."\n".self::blockBytes($file, $block);
    }

    public function current(SourceFile $file): ?JsonResource
    {
        $task = $file->tasks()->where('number', $this->number)->first();

        return $task === null ? null : TaskResource::make($task);
    }

    public function result(SourceFile $file): JsonResource
    {
        return TaskResource::make($file->tasks()->where('number', $this->number)->sole());
    }

    protected static function blockBytes(TasksFile $file, TaskBlock $block): string
    {
        $lines = array_slice($file->lines->lines, $block->start, $block->end - $block->start + 1);

        return implode('', array_map(fn ($line): string => $line->bytes(), $lines));
    }
}
