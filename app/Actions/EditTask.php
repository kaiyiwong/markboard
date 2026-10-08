<?php

namespace App\Actions;

use App\Markdown\TasksEditor;
use App\Markdown\TasksFile;

/** Changes only the fields given: title, due, proof and notes (spec, Edit fields). */
final readonly class EditTask extends TaskEdit
{
    /**
     * @param  array{title?: string, due?: string|null, proof?: string|null, notes?: list<string>}  $changes
     */
    public function __construct(
        int $number,
        public array $changes,
    ) {
        parent::__construct($number);
    }

    public function operation(): Operation
    {
        return Operation::EditTask;
    }

    public function summary(): string
    {
        return "Edit T{$this->number}";
    }

    protected function edit(TasksEditor $editor, TasksFile $file): string
    {
        return $editor->edit($file, $this->number, $this->changes);
    }
}
