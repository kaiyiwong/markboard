<?php

use App\Markdown\EditRefused;
use App\Markdown\FileNotEditable;
use App\Markdown\Line;
use App\Markdown\Section;
use App\Markdown\TaskBlock;
use App\Markdown\TaskNotFound;
use App\Markdown\TasksEditor;
use App\Markdown\TasksFile;
use Tests\Support\Checker;
use Tests\Support\Fixtures;

function editor(): TasksEditor
{
    return new TasksEditor(new DateTimeImmutable('2026-10-06'));
}

function basic(): TasksFile
{
    return TasksFile::parse(Fixtures::tasks('basic.md'));
}

/** Runs the result through both parsers, so every expected output here is also proven valid. */
function expectValid(string $result): string
{
    expect(TasksFile::parse($result)->isEditable())->toBeTrue()
        ->and(Checker::errors([$result])[0])->toBe([]);

    return $result;
}

describe('add', function () {
    it('puts a new task at the bottom of Up next, numbered one past the highest ID', function () {
        expect(expectValid(editor()->add(basic(), 'Import from JSON', '2026-11-01', 'a sample file imports cleanly')))->toBe(<<<'MD'
            # Lantern tasks

            Free text is allowed here, before the first section.

            ## Up next
            - [ ] T12 Export to CSV (due 2026-10-20)
              proof: the export opens in a spreadsheet with one row per task
            - [ ] T15 Keyboard shortcuts (v2)
            - [ ] T16 Import from JSON (due 2026-11-01)
              proof: a sample file imports cleanly

            ## In progress
            - [ ] T11 Settings page (started 2026-10-01)
              note: waiting on the new icon set for the toggles

            ## Waiting on
            - [ ] T09 Copy review (waiting Ana, since 2026-09-28)

            ## Done
            - [x] T10 Sign-in flow (started 2026-09-22, done 2026-09-27, evidence a1b2c3d, from In progress)
            - [x] T07 Offline mode (cancelled 2026-09-24, from Up next)

            MD);
    });

    it('puts it right after the heading when Up next is empty, and keeps a missing final newline missing', function () {
        $file = TasksFile::parse(Fixtures::tasks('empty-done-last.md'));

        expect(expectValid(editor()->add($file, 'Pick a cover')))->toBe(<<<'MD'
            # Quill tasks

            ## Up next
            - [ ] T2 Pick a cover

            ## In progress
            - [ ] T1 Draft the outline (started 2026-10-02)
              proof: every chapter has a one-line summary

            ## Waiting on

            ## Done
            MD);
    });

    it('gives the new lines the file\'s line break', function (string $fixture, string $eol) {
        $result = editor()->add(TasksFile::parse(Fixtures::tasks($fixture)), 'New task', proof: 'done when it is done');

        expect($result)->toContain("- [ ] T6 New task{$eol}  proof: done when it is done{$eol}");
    })->with([
        ['crlf.md', "\r\n"],
        ['mixed-line-endings.md', "\n"],
    ]);

    it('continues from the highest number, padded or not', function () {
        expect(editor()->add(TasksFile::parse(Fixtures::tasks('padded-ids.md')), 'Ship it'))->toContain("- [ ] T11 Ship it\n");
    });
});

describe('edit', function () {
    it('changes the title, removes the due date, and replaces the proof and notes', function () {
        $result = editor()->edit(basic(), 12, [
            'title' => 'Export to CSV and TSV',
            'due' => null,
            'proof' => 'both files open in a spreadsheet',
            'notes' => ['TSV for the finance team'],
        ]);

        expect(expectValid($result))->toBe(str_replace(<<<'MD'
            - [ ] T12 Export to CSV (due 2026-10-20)
              proof: the export opens in a spreadsheet with one row per task

            MD, <<<'MD'
            - [ ] T12 Export to CSV and TSV
              proof: both files open in a spreadsheet
              note: TSV for the finance team

            MD, Fixtures::tasks('basic.md')));
    });

    it('adds a due date after any parentheses in the title, and a proof right under the task line', function () {
        $result = editor()->edit(basic(), 15, ['due' => '2026-12-01', 'proof' => 'every action has a shortcut']);

        expect(expectValid($result))->toBe(str_replace(
            "- [ ] T15 Keyboard shortcuts (v2)\n",
            "- [ ] T15 Keyboard shortcuts (v2) (due 2026-12-01)\n  proof: every action has a shortcut\n",
            Fixtures::tasks('basic.md'),
        ));
    });

    it('removes the proof with null and every note with an empty list', function () {
        $file = basic();
        $withoutProof = editor()->edit($file, 12, ['proof' => null]);
        $withoutNotes = editor()->edit($file, 11, ['notes' => []]);

        expect(expectValid($withoutProof))->toBe(str_replace("  proof: the export opens in a spreadsheet with one row per task\n", '', Fixtures::tasks('basic.md')))
            ->and(expectValid($withoutNotes))->toBe(str_replace("  note: waiting on the new icon set for the toggles\n", '', Fixtures::tasks('basic.md')));
    });

    it('replaces the last of several proofs in place and drops the others', function () {
        $result = editor()->edit(TasksFile::parse(Fixtures::tasks('several-proofs.md')), 3, ['proof' => 'the books close with no differences']);

        expect(expectValid($result))->toContain(<<<'MD'
            - [ ] T3 Reconcile the March accounts
              note: start with the card payments
              proof: the books close with no differences

            ## In progress
            MD);
    });

    it('puts new notes where the first note was', function () {
        $result = editor()->edit(TasksFile::parse(Fixtures::tasks('note-before-proof.md')), 4, ['notes' => ['use the hourly samples only']]);

        expect(expectValid($result))->toContain(<<<'MD'
            - [ ] T4 Signal strength chart
              note: use the hourly samples only
              proof: the chart shows the last 24 hours
            MD);
    });

    it('leaves the file unchanged when nothing changes', function () {
        expect(editor()->edit(basic(), 12, ['title' => 'Export to CSV', 'due' => '2026-10-20']))->toBe(Fixtures::tasks('basic.md'));
    });

    it('keeps the task line\'s own line break when it rewrites it', function () {
        $result = editor()->edit(TasksFile::parse(Fixtures::tasks('crlf.md')), 4, ['title' => 'Add a light theme']);

        expect($result)->toBe(str_replace('Add a dark theme', 'Add a light theme', Fixtures::tasks('crlf.md')));
    });
});

describe('move', function () {
    it('moves a task to the top of In progress and sets started', function () {
        expect(expectValid(editor()->move(basic(), 12, Section::InProgress)))->toContain(<<<'MD'
            ## Up next
            - [ ] T15 Keyboard shortcuts (v2)

            ## In progress
            - [ ] T12 Export to CSV (due 2026-10-20, started 2026-10-06)
              proof: the export opens in a spreadsheet with one row per task
            - [ ] T11 Settings page (started 2026-10-01)
            MD);
    });

    it('moves a task to Waiting on with who it waits on, since today', function () {
        expect(expectValid(editor()->move(basic(), 11, Section::WaitingOn, 'the design team')))->toContain(<<<'MD'
            ## In progress

            ## Waiting on
            - [ ] T11 Settings page (started 2026-10-01, waiting the design team, since 2026-10-06)
              note: waiting on the new icon set for the toggles
            - [ ] T09 Copy review (waiting Ana, since 2026-09-28)
            MD);
    });

    it('moves a task to Up next without changing its metadata', function () {
        expect(expectValid(editor()->move(basic(), 9, Section::UpNext)))->toContain(<<<'MD'
            ## Up next
            - [ ] T09 Copy review (waiting Ana, since 2026-09-28)
            - [ ] T12 Export to CSV (due 2026-10-20)
            MD);
    });
});

describe('reorder', function () {
    $swapped = <<<'MD'
        ## Up next
        - [ ] T15 Keyboard shortcuts (v2)
        - [ ] T12 Export to CSV (due 2026-10-20)
          proof: the export opens in a spreadsheet with one row per task

        ## In progress
        MD;

    it('moves a task up', function () use ($swapped) {
        expect(expectValid(editor()->reorder(basic(), 15, 0)))->toContain($swapped);
    });

    it('moves a task down', function () use ($swapped) {
        expect(expectValid(editor()->reorder(basic(), 12, 1)))->toContain($swapped);
    });

    it('leaves the file unchanged for the task\'s own position', function () {
        expect(editor()->reorder(basic(), 10, 0))->toBe(Fixtures::tasks('basic.md'));
    });

    it('keeps blank lines that sit between tasks where they are', function () {
        $result = editor()->reorder(TasksFile::parse(Fixtures::tasks('blank-before-proof.md')), 6, 2);

        expect(expectValid($result))->toContain(<<<'MD'
            ## Up next
            - [ ] T7 Frost alerts

              note: the weather feed has hourly lows

              proof: an alert fires below 2 degrees


            - [ ] T8 Export a harvest report
            - [ ] T6 Import last season's yields

              proof: every field shows a 2025 total

            ## In progress
            MD);
    });
});

describe('tick, cancel and undo', function () {
    it('ticks a task to the top of Done with done, evidence and from', function () {
        expect(expectValid(editor()->tick(basic(), 12, 'f00dcafe')))->toContain(<<<'MD'
            ## Done
            - [x] T12 Export to CSV (due 2026-10-20, done 2026-10-06, evidence f00dcafe, from Up next)
              proof: the export opens in a spreadsheet with one row per task
            - [x] T10 Sign-in flow
            MD);
    });

    it('cancels a task to the top of Done with cancelled and from', function () {
        expect(expectValid(editor()->cancel(basic(), 11)))->toContain(<<<'MD'
            ## In progress

            ## Waiting on
            - [ ] T09 Copy review (waiting Ana, since 2026-09-28)

            ## Done
            - [x] T11 Settings page (started 2026-10-01, cancelled 2026-10-06, from In progress)
              note: waiting on the new icon set for the toggles
            MD);
    });

    it('undoes a ticked task back to its from section, removing done, evidence and from', function () {
        expect(expectValid(editor()->undo(basic(), 10)))->toContain(<<<'MD'
            ## In progress
            - [ ] T10 Sign-in flow (started 2026-09-22)
            - [ ] T11 Settings page (started 2026-10-01)
            MD);
    });

    it('undoes a cancelled task and drops the parentheses with its last key', function () {
        expect(expectValid(editor()->undo(basic(), 7)))->toContain("## Up next\n- [ ] T07 Offline mode\n- [ ] T12");
    });

    it('restores the file exactly when a task at the top of its section is ticked and undone', function (int $number) {
        $ticked = editor()->tick(basic(), $number);

        expect(editor()->undo(TasksFile::parse($ticked), $number))->toBe(Fixtures::tasks('basic.md'));
    })->with([12, 11, 9]);

    it('undoes into Waiting on with the waiting value given, since today', function () {
        $file = TasksFile::parse(str_replace(
            '- [x] T07 Offline mode (cancelled 2026-09-24, from Up next)',
            '- [x] T07 Offline mode (cancelled 2026-09-24, from Waiting on)',
            Fixtures::tasks('basic.md'),
        ));

        expect(fn () => editor()->undo($file, 7))->toThrow(EditRefused::class)
            ->and(expectValid(editor()->undo($file, 7, 'Ana')))->toContain(<<<'MD'
                ## Waiting on
                - [ ] T07 Offline mode (waiting Ana, since 2026-10-06)
                - [ ] T09 Copy review
                MD);
    });

    it('keeps dates and evidence the task already has, and drops the opposite outcome', function () {
        $file = TasksFile::parse(Fixtures::tasks('basic.md'));
        $line = fn (string $result, int $number): string => TasksFile::parse($result)->task($number)->line->render();

        expect($line(editor()->move($file, 9, Section::InProgress), 9))
            ->toBe('- [ ] T09 Copy review (waiting Ana, since 2026-09-28, started 2026-10-06)')
            ->and($line(editor()->move(TasksFile::parse(editor()->move($file, 11, Section::UpNext)), 11, Section::InProgress), 11))
            ->toBe('- [ ] T11 Settings page (started 2026-10-01)')
            ->and($line(editor()->undo($file, 10), 10))->toBe('- [ ] T10 Sign-in flow (started 2026-09-22)');

        $cancelledWithEvidence = TasksFile::parse(str_replace(
            '- [ ] T12 Export to CSV (due 2026-10-20)',
            '- [ ] T12 Export to CSV (due 2026-10-20, evidence abc, cancelled 2026-10-01)',
            Fixtures::tasks('basic.md'),
        ));
        expect($line(editor()->tick($cancelledWithEvidence, 12), 12))
            ->toBe('- [x] T12 Export to CSV (due 2026-10-20, evidence abc, done 2026-10-06, from Up next)')
            ->and($line(editor()->cancel(TasksFile::parse(editor()->undo(TasksFile::parse(editor()->tick($file, 12, 'abc')), 12)), 12), 12))
            ->toBe('- [x] T12 Export to CSV (due 2026-10-20, cancelled 2026-10-06, from Up next)');

        $waitingDone = TasksFile::parse(str_replace(
            '- [x] T07 Offline mode (cancelled 2026-09-24, from Up next)',
            '- [x] T07 Offline mode (waiting Ana, since 2026-09-01, done 2026-09-24, evidence f00, from Waiting on)',
            Fixtures::tasks('basic.md'),
        ));
        expect($line(editor()->undo($waitingDone, 7, 'Bob'), 7))->toBe('- [ ] T07 Offline mode (waiting Ana, since 2026-09-01)');
    });

    it('ticks the last line of a file with no final newline, and keeps it without one', function () {
        $file = TasksFile::parse(Fixtures::tasks('no-final-newline.md'));

        expect(expectValid(editor()->undo($file, 1)))->toEndWith("- [ ] T5 Logo files (waiting Studio Nine, since 2026-09-30)\n\n## Done")
            ->and(expectValid(editor()->tick($file, 4)))->toEndWith("## Done\n- [x] T4 Add a dark theme (due 2026-11-02, done 2026-10-06, from Up next)\n- [x] T1 Project skeleton (done 2026-09-29, from In progress)");
    });

    it('ticks a task under an empty Done that ends the file', function () {
        $result = editor()->tick(TasksFile::parse(Fixtures::tasks('empty-done-last.md')), 1);

        expect(expectValid($result))->toBe(<<<'MD'
            # Quill tasks

            ## Up next

            ## In progress

            ## Waiting on

            ## Done
            - [x] T1 Draft the outline (started 2026-10-02, done 2026-10-06, from In progress)
              proof: every chapter has a one-line summary
            MD);
    });

    it('finds a padded ID by number and keeps it as written', function () {
        expect(editor()->tick(TasksFile::parse(Fixtures::tasks('padded-ids.md')), 1))
            ->toContain("## Done\n- [x] T01 Calibrate the needle (done 2026-10-06, from Up next)\n");
    });
});

describe('refusals', function () {
    it('refuses a rewrite whose title would turn into metadata', function () {
        $file = TasksFile::parse(Fixtures::tasks('title-would-become-metadata.md'));

        expect(fn () => editor()->edit($file, 3, ['due' => null]))->toThrow(EditRefused::class, 'Change the title first')
            ->and(fn () => editor()->undo($file, 4))->toThrow(EditRefused::class, 'Change the title first');
    });

    it('refuses every operation on a file with errors', function () {
        $file = TasksFile::parse(Fixtures::tasks('errors.md'));

        expect(fn () => editor()->add($file, 'x'))->toThrow(FileNotEditable::class)
            ->and(fn () => editor()->edit($file, 2, ['title' => 'x']))->toThrow(FileNotEditable::class)
            ->and(fn () => editor()->tick($file, 2))->toThrow(FileNotEditable::class);
    });

    it('refuses an unknown task number', function () {
        expect(fn () => editor()->tick(basic(), 99))->toThrow(TaskNotFound::class, 'No task T99');
    });

    it('refuses operations from the wrong section', function () {
        $file = basic();

        expect(fn () => editor()->tick($file, 10))->toThrow(EditRefused::class, 'T10 is in Done')
            ->and(fn () => editor()->cancel($file, 10))->toThrow(EditRefused::class, 'T10 is in Done')
            ->and(fn () => editor()->move($file, 10, Section::UpNext))->toThrow(EditRefused::class, 'T10 is in Done')
            ->and(fn () => editor()->move($file, 12, Section::Done))->toThrow(EditRefused::class, 'ticked or cancelled')
            ->and(fn () => editor()->move($file, 12, Section::UpNext))->toThrow(EditRefused::class, 'already in Up next')
            ->and(fn () => editor()->move($file, 12, Section::WaitingOn))->toThrow(EditRefused::class, 'who or what')
            ->and(fn () => editor()->undo($file, 12))->toThrow(EditRefused::class, "can't be undone")
            ->and(fn () => editor()->reorder($file, 12, -1))->toThrow(EditRefused::class, 'outside Up next')
            ->and(fn () => editor()->reorder($file, 12, 2))->toThrow(EditRefused::class, 'outside Up next');
    });
});

/**
 * Lines as bytes, the last one without its break: the final-newline state is checked on its own.
 *
 * @param  list<Line>  $lines
 * @return list<string>
 */
function comparable(array $lines): array
{
    $bytes = array_map(fn (Line $line): string => $line->bytes(), $lines);
    if ($bytes !== []) {
        $bytes[count($bytes) - 1] = rtrim($bytes[count($bytes) - 1], "\r\n");
    }

    return $bytes;
}

/**
 * The lines outside one task's block.
 *
 * @return list<string>
 */
function linesOutside(TasksFile $file, ?TaskBlock $block): array
{
    return comparable(array_values(array_filter(
        $file->lines->forEditing(),
        fn (int $index): bool => $block === null || $index < $block->start || $index > $block->end,
        ARRAY_FILTER_USE_KEY,
    )));
}

it('gives every operation on every task of every editable fixture a file that passes the checker, touching only that task', function () {
    $editor = editor();
    $cases = [];
    $refused = [];

    foreach (Fixtures::taskNames() as $name) {
        $file = TasksFile::parse(Fixtures::tasks($name));
        if (! $file->isEditable()) {
            continue;
        }

        $operations = ['add' => [$file->nextNumber(), fn () => $editor->add($file, 'Sweep task', '2026-12-01', 'it works')]];
        foreach ($file->tasks as $task) {
            $n = $task->line->number;
            $ops = [
                'edit title' => fn () => $editor->edit($file, $n, ['title' => 'Renamed task']),
                'edit due' => fn () => $editor->edit($file, $n, ['due' => '2026-12-24']),
                'edit no due' => fn () => $editor->edit($file, $n, ['due' => null]),
                'edit proof' => fn () => $editor->edit($file, $n, ['proof' => 'a new proof']),
                'edit no proof' => fn () => $editor->edit($file, $n, ['proof' => null]),
                'edit notes' => fn () => $editor->edit($file, $n, ['notes' => ['first', 'second']]),
                'edit no notes' => fn () => $editor->edit($file, $n, ['notes' => []]),
            ];
            foreach (array_keys($file->tasksIn($task->section)) as $position) {
                $ops["reorder to {$position}"] = fn () => $editor->reorder($file, $n, $position);
            }
            if ($task->section->isOpen()) {
                $ops['tick'] = fn () => $editor->tick($file, $n);
                $ops['tick with evidence'] = fn () => $editor->tick($file, $n, 'abc123');
                $ops['cancel'] = fn () => $editor->cancel($file, $n);
                foreach ([Section::UpNext, Section::InProgress, Section::WaitingOn] as $to) {
                    if ($to !== $task->section) {
                        $ops["move to {$to->value}"] = fn () => $editor->move($file, $n, $to, 'someone');
                    }
                }
            } elseif ($task->line->metadata->has('from')) {
                $ops['undo'] = fn () => $editor->undo($file, $n, 'someone');
            }
            foreach ($ops as $label => $op) {
                $operations["{$task->line->id} {$label}"] = [$n, $op];
            }
        }

        foreach ($operations as $label => [$number, $op]) {
            try {
                $cases["{$name}: {$label}"] = [$file, $number, $op()];
            } catch (EditRefused) {
                $refused[] = "{$name}: {$label}";
            }
        }
    }

    $checker = Checker::errors(array_map(fn (array $case): string => $case[2], $cases));

    foreach ($cases as $label => [$before, $number, $result]) {
        $after = TasksFile::parse($result);
        expect($after->isEditable())->toBeTrue($label)
            ->and($checker[$label])->toBe([], $label)
            ->and($after->lines->endsWithLineBreak())->toBe($before->lines->endsWithLineBreak(), $label);

        // An edit rewrites its block in place, which may leave blank lines behind it: everything before
        // and after the old block is unchanged. Every other operation moves the block whole.
        $old = str_ends_with($label, ': add') ? null : $before->task($number);
        if (str_contains($label, ' edit ')) {
            $beforeLines = $before->lines->forEditing();
            $afterLines = $after->lines->forEditing();
            $tail = count($beforeLines) - $old->end - 1;
            expect(comparable(array_slice($afterLines, 0, $old->start)))->toBe(comparable(array_slice($beforeLines, 0, $old->start)), $label)
                ->and(comparable(array_slice($afterLines, count($afterLines) - $tail)))->toBe(comparable(array_slice($beforeLines, $old->end + 1)), $label);
        } else {
            $new = $after->task($number);
            expect(linesOutside($after, $new))->toBe(linesOutside($before, $old), $label);

            // A moved block keeps every line break and every line but its task line; a reorder keeps the task line too.
            if ($old !== null) {
                $oldBlock = array_slice($before->lines->forEditing(), $old->start, $old->end - $old->start + 1);
                $newBlock = array_slice($after->lines->forEditing(), $new->start, $new->end - $new->start + 1);
                $kept = str_contains($label, ' reorder ') ? 0 : 1;
                expect(array_map(fn (Line $line): string => $line->terminator, $newBlock))
                    ->toBe(array_map(fn (Line $line): string => $line->terminator, $oldBlock), $label)
                    ->and(array_slice($newBlock, $kept))->toEqual(array_slice($oldBlock, $kept), $label);
            }
        }
    }

    expect(count($cases))->toBeGreaterThan(500)
        ->and($refused)->toBe([
            'title-would-become-metadata.md: T3 edit no due',
            'title-would-become-metadata.md: T4 undo',
        ]);
});
