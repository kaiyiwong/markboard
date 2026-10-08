<?php

namespace App\Actions;

use App\Markdown\Section;
use App\Markdown\TasksEditor;
use App\Markdown\TasksFile;

/** To the top of another open section; into Waiting on needs who or what it waits on. */
final readonly class MoveTask extends TaskEdit
{
    public function __construct(
        int $number,
        public string $section,
        public ?string $waiting = null,
    ) {
        parent::__construct($number);
    }

    public function operation(): Operation
    {
        return Operation::MoveTask;
    }

    public function summary(): string
    {
        return "Move T{$this->number} to {$this->section}";
    }

    protected function edit(TasksEditor $editor, TasksFile $file): string
    {
        return $editor->move($file, $this->number, Section::from($this->section), $this->waiting);
    }
}
