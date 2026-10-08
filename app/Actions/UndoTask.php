<?php

namespace App\Actions;

use App\Markdown\TasksEditor;
use App\Markdown\TasksFile;

/** Back from Done to the top of its `from` section; into Waiting on needs `waiting` if the task has none. */
final readonly class UndoTask extends TaskEdit
{
    public function __construct(
        int $number,
        public ?string $waiting = null,
    ) {
        parent::__construct($number);
    }

    public function operation(): Operation
    {
        return Operation::UndoTask;
    }

    public function summary(): string
    {
        return "Undo T{$this->number}";
    }

    protected function edit(TasksEditor $editor, TasksFile $file): string
    {
        return $editor->undo($file, $this->number, $this->waiting);
    }
}
