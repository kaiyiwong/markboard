<?php

use App\Markdown\EditRefused;
use App\Markdown\FileNotEditable;
use App\Markdown\Line;
use App\Markdown\Lines;
use App\Markdown\PipelineEditor;
use App\Markdown\PipelineFile;
use App\Markdown\RowNotFound;
use Tests\Support\Fixtures;

function pipeline(string $name): PipelineFile
{
    return PipelineFile::parse(Fixtures::pipeline($name));
}

/** Every result here must parse back as an editable pipeline, so each expected output is also proven valid. */
function expectEditable(string $result): string
{
    expect(PipelineFile::parse($result)->isEditable())->toBeTrue();

    return $result;
}

/**
 * The line indexes at which two files differ, for files with the same number of lines.
 *
 * @return list<int>
 */
function changedLines(string $before, string $after): array
{
    $old = Lines::split($before)->lines;
    $new = Lines::split($after)->lines;
    expect($new)->toHaveCount(count($old));

    return array_keys(array_filter($old, fn (Line $line, int $i): bool => $line != $new[$i], ARRAY_FILTER_USE_BOTH));
}

/** @return array{company: string, role: string, stage: string, next_action: string, date: string|null} */
function tailspin(): array
{
    return ['company' => 'Tailspin', 'role' => 'Lead Developer', 'stage' => 'applied', 'next_action' => 'wait for a reply', 'date' => '2026-10-20'];
}

describe('edit a row', function () {
    it('rewrites only that row, changing only the fields given', function () {
        $result = expectEditable((new PipelineEditor)->editRow(pipeline('basic.md'), 2, ['stage' => 'screening', 'next_action' => 'book a call']));

        expect($result)->toBe(<<<'MD'
            # Pipeline

            | company | role | stage | next action | date |
            |---|---|---|---|---|
            | Northwind | Senior Engineer | interviewing | prepare system design | 2026-10-14 |
            | Contoso | Backend Developer | screening | book a call | 2026-10-09 |
            | Fabrikam | Staff Engineer | rejected | ask for feedback | 2026-09-30 |

            MD)->and(changedLines(Fixtures::pipeline('basic.md'), $result))->toBe([5]);
    });

    it("writes the row in the header's column order", function () {
        $result = expectEditable((new PipelineEditor)->editRow(pipeline('columns-reordered.md'), 1, ['stage' => 'interviewing', 'date' => '2026-10-21']));

        expect(Lines::split($result)->lines[4]->content)->toBe('| interviewing | Northwind | 2026-10-21 | Senior Engineer | send the portfolio link |')
            ->and(changedLines(Fixtures::pipeline('columns-reordered.md'), $result))->toBe([4]);
    });

    it('empties the date with null or an empty string', function (?string $date) {
        $result = expectEditable((new PipelineEditor)->editRow(pipeline('basic.md'), 1, ['date' => $date]));

        expect(Lines::split($result)->lines[4]->content)->toBe('| Northwind | Senior Engineer | interviewing | prepare system design |  |')
            ->and(PipelineFile::parse($result)->row(1)->date)->toBe('');
    })->with([null, '']);

    it('rewrites a loosely written row in the fixed form, leaving the loose lines around it as they are', function () {
        $result = expectEditable((new PipelineEditor)->editRow(pipeline('loose-pipes.md'), 1, ['date' => '2026-10-15']));

        expect($result)->toBe(str_replace(
            '|Northwind|Senior Engineer|interviewing|prepare system design|2026-10-14',
            '| Northwind | Senior Engineer | interviewing | prepare system design | 2026-10-15 |',
            Fixtures::pipeline('loose-pipes.md'),
        ));
    });

    it('keeps the original bytes when nothing changes', function () {
        $bytes = Fixtures::pipeline('loose-pipes.md');

        expect((new PipelineEditor)->editRow(PipelineFile::parse($bytes), 2, ['company' => 'Contoso', 'date' => '2026-10-09']))->toBe($bytes);
    });

    it("keeps the row's own \\r\\n", function () {
        $result = expectEditable((new PipelineEditor)->editRow(pipeline('crlf.md'), 1, ['stage' => 'offer']));

        expect($result)->toBe(str_replace('| interviewing |', '| offer |', Fixtures::pipeline('crlf.md')))
            ->and(substr_count($result, "\r\n"))->toBe(substr_count(Fixtures::pipeline('crlf.md'), "\r\n"));
    });

    it('keeps a missing final newline when the last row changes', function () {
        $result = expectEditable((new PipelineEditor)->editRow(pipeline('no-final-newline.md'), 2, ['stage' => 'rejected']));

        expect($result)->toEndWith('| Contoso | Backend Developer | rejected | wait for a reply | 2026-10-09 |');
    });
});

describe('add a row', function () {
    it('puts the new row after the last row, in the header order', function () {
        $result = expectEditable((new PipelineEditor)->addRow(pipeline('columns-reordered.md'), tailspin()));

        expect($result)->toBe(<<<'MD'
            # Pipeline

            | stage | company | date | role | next action |
            | :--- | --- | :---: | --- | ---: |
            | screening | Northwind | 2026-10-14 | Senior Engineer | send the portfolio link |
            | offer | Tailspin |  | Lead Developer | agree a start date |
            | applied | Tailspin | 2026-10-20 | Lead Developer | wait for a reply |

            MD);
    });

    it('puts the first row of an empty table directly after the separator', function () {
        $result = expectEditable((new PipelineEditor)->addRow(pipeline('empty-table.md'), [...tailspin(), 'date' => null]));

        expect($result)->toBe(<<<'MD'
            # Pipeline

            Nothing applied for yet.

            | company | role | stage | next action | date |
            |---|---|---|---|---|
            | Tailspin | Lead Developer | applied | wait for a reply |  |

            MD)->and(PipelineFile::parse($result)->row(1)->company)->toBe('Tailspin');
    });

    it('adds to an empty table that ends the file without a final newline, keeping it without one', function () {
        $result = expectEditable((new PipelineEditor)->addRow(pipeline('empty-table-no-final-newline.md'), tailspin()));

        expect($result)->toEndWith("|---|---|---|---|---|\n| Tailspin | Lead Developer | applied | wait for a reply | 2026-10-20 |");
    });

    it('adds after a last row that has no final newline, keeping the file without one', function () {
        $result = expectEditable((new PipelineEditor)->addRow(pipeline('no-final-newline.md'), tailspin()));

        expect($result)->toBe(Fixtures::pipeline('no-final-newline.md')."\n| Tailspin | Lead Developer | applied | wait for a reply | 2026-10-20 |");
    });

    it('gives the new row the file\'s \r\n', function () {
        $result = expectEditable((new PipelineEditor)->addRow(pipeline('crlf.md'), tailspin()));

        expect($result)->toBe(Fixtures::pipeline('crlf.md')."| Tailspin | Lead Developer | applied | wait for a reply | 2026-10-20 |\r\n");
    });

    it('adds inside the table, before the free text that ends it', function () {
        $result = expectEditable((new PipelineEditor)->addRow(pipeline('free-text-around.md'), tailspin()));
        $lines = Lines::split($result)->lines;

        expect($lines[12]->content)->toBe('| Tailspin | Lead Developer | applied | wait for a reply | 2026-10-20 |')
            ->and($lines[13]->content)->toBe('Last checked on Monday: this line ends the table.')
            ->and(PipelineFile::parse($result)->rows)->toHaveCount(3);
    });
});

describe('refusals', function () {
    it('refuses a row that is not in the table', function (int $position) {
        expect(fn () => (new PipelineEditor)->editRow(pipeline('basic.md'), $position, ['stage' => 'offer']))
            ->toThrow(RowNotFound::class, "No row {$position} in the pipeline table.");
    })->with([0, 4]);

    it('refuses to edit or add to a file with format errors', function (string $name) {
        expect(fn () => (new PipelineEditor)->editRow(pipeline($name), 1, ['stage' => 'offer']))->toThrow(FileNotEditable::class)
            ->and(fn () => (new PipelineEditor)->addRow(pipeline($name), tailspin()))->toThrow(FileNotEditable::class);
    })->with(array_values(array_filter(Fixtures::pipelineNames(), fn (string $name): bool => str_starts_with($name, 'error-'))));

    it('refuses a value the file could not hold', function (array $changes, string $message) {
        expect(fn () => (new PipelineEditor)->editRow(pipeline('basic.md'), 1, $changes))->toThrow(EditRefused::class, $message)
            ->and(fn () => (new PipelineEditor)->addRow(pipeline('basic.md'), [...tailspin(), ...$changes]))->toThrow(EditRefused::class);
    })->with([
        'an unknown stage' => [['stage' => 'ghosted'], 'Row 1 would not be valid: stage "ghosted" is not one of'],
        'a bad date' => [['date' => '2026-02-30'], 'Row 1 would not be valid: bad date: 2026-02-30.'],
        'a |' => [['company' => 'North | wind'], 'Row 1 would not be valid: row has 6 cells, the header has 5.'],
        'a line break' => [['role' => "Senior\nEngineer"], 'Row 1 would not be valid: row has 2 cells, the header has 5.'],
        'spaces around a value' => [['role' => ' Senior Engineer'], 'Row 1 would not read back as written'],
    ]);
});

it('gives every edit of every row, and an add, in every editable fixture a valid file that changes only that row', function () {
    $editor = new PipelineEditor;
    $edits = [
        'company' => ['company' => 'Litware'],
        'role' => ['role' => 'Principal Engineer'],
        'stage' => ['stage' => 'withdrawn'],
        'next action' => ['next_action' => 'send a thank-you note'],
        'date' => ['date' => '2026-12-01'],
        'no date' => ['date' => null],
        'every field' => [...tailspin(), 'stage' => 'accepted'],
    ];
    $cases = 0;

    foreach (Fixtures::pipelineNames() as $name) {
        $before = Fixtures::pipeline($name);
        $file = PipelineFile::parse($before);
        if (! $file->isEditable()) {
            continue;
        }

        foreach ($file->rows as $row) {
            foreach ($edits as $label => $changes) {
                $case = "{$name}: row {$row->position} {$label}";
                $result = $editor->editRow($file, $row->position, $changes);
                $after = PipelineFile::parse($result);
                $expected = $row->cells();
                foreach ($changes as $field => $value) {
                    $expected[str_replace('_', ' ', $field)] = $value ?? '';
                }

                expect($after->isEditable())->toBeTrue($case)
                    ->and(array_diff(changedLines($before, $result), [$row->index]))->toBe([], $case)
                    ->and($after->row($row->position)->cells())->toBe($expected, $case)
                    ->and($after->lines->endsWithLineBreak())->toBe($file->lines->endsWithLineBreak(), $case);
                $cases++;
            }
        }

        $result = $editor->addRow($file, tailspin());
        $after = PipelineFile::parse($result);
        $added = $after->row(count($file->rows) + 1);
        $beforeLines = $file->lines->forEditing();
        $afterLines = $after->lines->forEditing();
        array_splice($afterLines, $added->index, 1);

        expect($after->isEditable())->toBeTrue("{$name}: add")
            ->and($added->company)->toBe('Tailspin')
            ->and($afterLines)->toEqual($beforeLines, "{$name}: add")
            ->and($after->lines->endsWithLineBreak())->toBe($file->lines->endsWithLineBreak());
        $cases++;
    }

    expect($cases)->toBeGreaterThan(100);
});
