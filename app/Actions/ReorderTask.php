<?php

namespace App\Actions;

use App\Markdown\TaskBlock;
use App\Markdown\TasksEditor;
use App\Markdown\TasksFile;

/** To a 0-based position among its section's tasks. */
final readonly class ReorderTask extends TaskEdit
{
    public function __construct(
        int $number,
        public int $position,
    ) {
        parent::__construct($number);
    }

    public function operation(): Operation
    {
        return Operation::ReorderTask;
    }

    public function summary(): string
    {
        return "Reorder T{$this->number} to position ".($this->position + 1);
    }

    /** A reorder depends on every block in its section and their order, since a position counts them. */
    public function dependsOn(string $bytes): ?string
    {
        $file = TasksFile::parse($bytes);
        $block = $file->task($this->number);
        if ($block === null) {
            return null;
        }

        return $block->section->value."\n".implode('', array_map(
            fn (TaskBlock $task): string => self::blockBytes($file, $task),
            $file->tasksIn($block->section),
        ));
    }

    protected function edit(TasksEditor $editor, TasksFile $file): string
    {
        return $editor->reorder($file, $this->number, $this->position);
    }
}
