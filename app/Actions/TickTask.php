<?php

namespace App\Actions;

use App\Markdown\TasksEditor;
use App\Markdown\TasksFile;

/** To the top of Done as [x], with `done` today, `from` its section and `evidence` if given. */
final readonly class TickTask extends TaskEdit
{
    public function __construct(
        int $number,
        public ?string $evidence = null,
    ) {
        parent::__construct($number);
    }

    public function operation(): Operation
    {
        return Operation::TickTask;
    }

    public function summary(): string
    {
        return "Tick T{$this->number}";
    }

    protected function edit(TasksEditor $editor, TasksFile $file): string
    {
        return $editor->tick($file, $this->number, $this->evidence);
    }
}
