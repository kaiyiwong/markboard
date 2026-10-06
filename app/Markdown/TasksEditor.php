<?php

namespace App\Markdown;

use DateTimeInterface;

/**
 * The task operations, each one a few line splices on the original file.
 *
 * Nothing is re-rendered: a block is removed, replaced or inserted, and every other line keeps its
 * exact bytes, terminator included. A task line is rewritten only when the operation changes it,
 * and every rewritten line must parse back to exactly what was meant, or the edit is refused.
 * Each method takes the parsed file and returns the new file's bytes.
 */
final readonly class TasksEditor
{
    private string $today;

    public function __construct(DateTimeInterface $today)
    {
        $this->today = $today->format('Y-m-d');
    }

    /** A new task at the bottom of Up next, numbered one past the highest ID. */
    public function add(TasksFile $file, string $title, ?string $due = null, ?string $proof = null): string
    {
        $this->assertEditable($file);
        $number = $file->nextNumber();
        $eol = $file->lines->dominantTerminator();
        $metadata = $due === null ? new Metadata : (new Metadata)->with('due', $due);

        $block = [$this->taskLine(new TaskLine(false, "T{$number}", $number, $title, $metadata), $eol)];
        if ($proof !== null) {
            $block[] = $this->indentedLine('proof', $proof, $eol);
        }

        $upNext = $file->tasksIn(Section::UpNext);
        $at = $upNext === [] ? $this->topOf($file, Section::UpNext) : $upNext[count($upNext) - 1]->end + 1;

        return $this->splice($file, null, $block, $at);
    }

    /**
     * Changes only the fields given: `due` and `proof` are removed with null, `notes` replaces every note.
     *
     * @param  array{title?: string, due?: string|null, proof?: string|null, notes?: list<string>}  $changes
     */
    public function edit(TasksFile $file, int $number, array $changes): string
    {
        $block = $this->findTask($file, $number);
        $line = $block->line;
        if (array_key_exists('title', $changes)) {
            $line = $line->withTitle($changes['title']);
        }
        if (array_key_exists('due', $changes)) {
            $due = $changes['due'];
            $line = $line->withMetadata($due === null ? $line->metadata->without('due') : $line->metadata->with('due', $due));
        }

        $lines = $file->lines->forEditing();
        $eol = $file->lines->dominantTerminator();
        $proofIndexes = array_column($block->proofs, 'index');
        $noteIndexes = array_column($block->notes, 'index');
        $newProof = $changes['proof'] ?? null;
        $newNotes = array_map(fn (string $note): Line => $this->indentedLine('note', $note, $eol), $changes['notes'] ?? []);

        $out = [];
        for ($i = $block->start; $i <= $block->end; $i++) {
            if ($i === $block->start) {
                $out[] = $this->changedTaskLine($block, $line, $lines[$i]);
                if ($newProof !== null && $proofIndexes === []) {
                    $out[] = $this->indentedLine('proof', $newProof, $eol);
                }
            } elseif (array_key_exists('proof', $changes) && in_array($i, $proofIndexes, true)) {
                if ($newProof !== null && $i === $proofIndexes[count($proofIndexes) - 1]) {
                    $out[] = $this->indentedLine('proof', $newProof, $lines[$i]->terminator);
                }
            } elseif (array_key_exists('notes', $changes) && in_array($i, $noteIndexes, true)) {
                if ($i === $noteIndexes[0]) {
                    array_push($out, ...$newNotes);
                }
            } else {
                $out[] = $lines[$i];
            }
        }
        if ($noteIndexes === []) {
            array_push($out, ...$newNotes);
        }

        return $this->splice($file, $block, $out, $block->start);
    }

    /** Between the open sections, to the top of the target. Into Waiting on needs who or what it waits on. */
    public function move(TasksFile $file, int $number, Section $to, ?string $waiting = null): string
    {
        $block = $this->openTask($file, $number, 'moved');
        if (! $to->isOpen()) {
            throw new EditRefused('A task goes to Done by being ticked or cancelled, not moved.');
        }
        if ($block->section === $to) {
            throw new EditRefused("{$block->line->id} is already in {$to->value}.");
        }

        $metadata = $block->line->metadata;
        if ($to === Section::InProgress && ! $metadata->has('started')) {
            $metadata = $metadata->with('started', $this->today);
        }
        if ($to === Section::WaitingOn) {
            if ($waiting === null) {
                throw new EditRefused('Moving a task to Waiting on needs who or what it is waiting on.');
            }
            $metadata = $metadata->with('waiting', $waiting)->with('since', $this->today);
        }

        return $this->toTopOf($file, $block, $block->line->withMetadata($metadata), $to);
    }

    /** To a 0-based position among its section's tasks. */
    public function reorder(TasksFile $file, int $number, int $position): string
    {
        $block = $this->findTask($file, $number);
        $section = $file->tasksIn($block->section);
        if ($position < 0 || $position >= count($section)) {
            throw new EditRefused("Position {$position} is outside {$block->section->value}, which has ".count($section).' tasks.');
        }
        $current = array_search($block, $section, true);
        if ($position === $current) {
            return $file->lines->finish($file->lines->forEditing());
        }

        $others = array_values(array_filter($section, fn (TaskBlock $task): bool => $task !== $block));
        // Moving up, it goes right before the task it will precede; moving down, right after the one it will follow.
        $at = $position < $current ? $others[$position]->start : $others[$position - 1]->end + 1;

        return $this->splice($file, $block, $this->blockLines($file, $block, $block->line), $at);
    }

    public function tick(TasksFile $file, int $number, ?string $evidence = null): string
    {
        $block = $this->openTask($file, $number, 'ticked');
        $metadata = $block->line->metadata->with('done', $this->today);
        if ($evidence !== null) {
            $metadata = $metadata->with('evidence', $evidence);
        }
        $metadata = $metadata->with('from', $block->section->value)->without('cancelled');

        return $this->toTopOf($file, $block, $block->line->withChecked(true)->withMetadata($metadata), Section::Done);
    }

    public function cancel(TasksFile $file, int $number): string
    {
        $block = $this->openTask($file, $number, 'cancelled');
        $metadata = $block->line->metadata
            ->with('cancelled', $this->today)
            ->with('from', $block->section->value)
            ->without('done', 'evidence');

        return $this->toTopOf($file, $block, $block->line->withChecked(true)->withMetadata($metadata), Section::Done);
    }

    /** Back from Done to the section in its `from`. Into Waiting on needs `waiting` if the task has none. */
    public function undo(TasksFile $file, int $number, ?string $waiting = null): string
    {
        $block = $this->findTask($file, $number);
        $from = $block->line->metadata->get('from');
        if ($block->section !== Section::Done || $from === null) {
            throw new EditRefused("{$block->line->id} can't be undone: only a task in Done with a from section can.");
        }
        $to = Section::from($from);

        $metadata = $block->line->metadata->without('done', 'cancelled', 'evidence', 'from');
        if ($to === Section::InProgress && ! $metadata->has('started')) {
            $metadata = $metadata->with('started', $this->today);
        }
        if ($to === Section::WaitingOn) {
            if (! $metadata->has('waiting')) {
                if ($waiting === null) {
                    throw new EditRefused('Undoing into Waiting on needs who or what the task is waiting on.');
                }
                $metadata = $metadata->with('waiting', $waiting);
            }
            if (! $metadata->has('since')) {
                $metadata = $metadata->with('since', $this->today);
            }
        }

        return $this->toTopOf($file, $block, $block->line->withChecked(false)->withMetadata($metadata), $to);
    }

    private function assertEditable(TasksFile $file): void
    {
        if (! $file->isEditable()) {
            throw new FileNotEditable;
        }
    }

    private function findTask(TasksFile $file, int $number): TaskBlock
    {
        $this->assertEditable($file);

        return $file->task($number) ?? throw new TaskNotFound($number);
    }

    private function openTask(TasksFile $file, int $number, string $verb): TaskBlock
    {
        $block = $this->findTask($file, $number);
        if (! $block->section->isOpen()) {
            throw new EditRefused("{$block->line->id} is in Done, so it can't be {$verb}.");
        }

        return $block;
    }

    /** "Top of a section" is directly after its heading. */
    private function topOf(TasksFile $file, Section $section): int
    {
        return (int) $file->headingIndex($section) + 1;
    }

    private function toTopOf(TasksFile $file, TaskBlock $block, TaskLine $line, Section $to): string
    {
        return $this->splice($file, $block, $this->blockLines($file, $block, $line), $this->topOf($file, $to));
    }

    /**
     * The block's lines with its task line rewritten, if the task line changed.
     *
     * @return list<Line>
     */
    private function blockLines(TasksFile $file, TaskBlock $block, TaskLine $line): array
    {
        $lines = array_slice($file->lines->forEditing(), $block->start, $block->end - $block->start + 1);
        $lines[0] = $this->changedTaskLine($block, $line, $lines[0]);

        return $lines;
    }

    /**
     * Removes the block (if any) and inserts the new lines at $at, an index in the original lines.
     *
     * @param  list<Line>  $insert
     */
    private function splice(TasksFile $file, ?TaskBlock $remove, array $insert, int $at): string
    {
        $lines = $file->lines->forEditing();
        if ($remove !== null) {
            $length = $remove->end - $remove->start + 1;
            array_splice($lines, $remove->start, $length);
            if ($at > $remove->start) {
                $at -= $length;
            }
        }
        array_splice($lines, $at, 0, $insert);

        return $file->lines->finish($lines);
    }

    /** A task line the operation doesn't change keeps its original bytes. */
    private function changedTaskLine(TaskBlock $block, TaskLine $line, Line $original): Line
    {
        return $line->equals($block->line) ? $original : $this->taskLine($line, $original->terminator);
    }

    private function taskLine(TaskLine $line, string $terminator): Line
    {
        $text = $line->render();
        $readBack = TaskLine::parse($text);
        if ($readBack === null || ! $readBack->equals($line)) {
            throw new EditRefused(
                "{$line->id} would not read back as written: its title \"{$line->title}\" would change. Change the title first.",
            );
        }

        return new Line($text, $terminator);
    }

    private function indentedLine(string $kind, string $text, string $terminator): Line
    {
        if ($text === '' || Text::strip($text) !== $text) {
            throw new EditRefused("A {$kind} can't be empty or start or end with spaces.");
        }

        return new Line("  {$kind}: {$text}", $terminator);
    }
}
