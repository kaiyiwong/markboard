<?php

namespace App\Actions;

use App\Markdown\TasksEditor;
use App\Markdown\TasksFile;

/**
 * Kai's answer to a task waiting on him (docs/spec.md, Review): an optional note line after the
 * task's own notes, then the task back to Up next, ticked or cancelled.
 */
final readonly class AnswerTask extends TaskEdit
{
    public const string UP_NEXT = 'up-next';

    public const string TICK = 'tick';

    public const string CANCEL = 'cancel';

    /**
     * @param  self::UP_NEXT|self::TICK|self::CANCEL  $outcome
     */
    public function __construct(
        int $number,
        public string $outcome,
        public ?string $note = null,
    ) {
        parent::__construct($number);
    }

    public function operation(): Operation
    {
        return Operation::AnswerTask;
    }

    public function summary(): string
    {
        return "Answer T{$this->number}";
    }

    protected function edit(TasksEditor $editor, TasksFile $file): string
    {
        if ($this->note !== null) {
            $notes = [...($file->task($this->number)?->notes() ?? []), $this->note];
            $file = TasksFile::parse($editor->edit($file, $this->number, ['notes' => $notes]));
        }

        return match ($this->outcome) {
            self::UP_NEXT => $editor->answer($file, $this->number),
            self::TICK => $editor->tick($file, $this->number),
            self::CANCEL => $editor->cancel($file, $this->number),
        };
    }
}
