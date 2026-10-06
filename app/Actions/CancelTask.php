<?php

namespace App\Actions;

use App\Markdown\TasksEditor;
use App\Markdown\TasksFile;

/** To the top of Done as [x], with `cancelled` today and `from` its section. */
final readonly class CancelTask extends TaskEdit
{
    public function operation(): Operation
    {
        return Operation::CancelTask;
    }

    public function summary(): string
    {
        return "Cancel T{$this->number}";
    }

    protected function edit(TasksEditor $editor, TasksFile $file): string
    {
        return $editor->cancel($file, $this->number);
    }
}
