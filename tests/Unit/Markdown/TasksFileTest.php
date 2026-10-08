<?php

use App\Markdown\FormatError;
use App\Markdown\Registry;
use App\Markdown\Section;
use App\Markdown\TaskBlock;
use App\Markdown\TasksFile;
use App\Sync\Hub;
use Tests\Support\Checker;
use Tests\Support\Fixtures;

/** @return list<array{line: int, message: string}> */
function errorsOf(TasksFile $file): array
{
    return array_map(fn (FormatError $error): array => ['line' => $error->line, 'message' => $error->message], $file->errors);
}

it('reads and writes back every fixture byte for byte', function (string $name) {
    $bytes = Fixtures::tasks($name);
    $lines = TasksFile::parse($bytes)->lines;

    expect($lines->finish($lines->forEditing()))->toBe($bytes);
})->with(Fixtures::taskDataset());

it('reports the same format errors as check-tasks.py', function (string $name) {
    $bytes = Fixtures::tasks($name);

    expect(errorsOf(TasksFile::parse($bytes)))->toBe(Checker::errors([$bytes])[0]);
})->with(Fixtures::taskDataset());

it('reports the same format errors as check-tasks.py on every TASKS.md in a hub', function (string|false $hub) {
    if (! is_string($hub) || $hub === '') {
        $this->markTestSkipped('MARKBOARD_HUB_PATH is not set (CI never sets it).');
    }

    $hub = new Hub($hub);
    $registry = Registry::parse((string) file_get_contents($hub->registryPath()), $hub->path, $hub->isDemoKind());
    $texts = [];
    foreach ($registry->projects as ['path' => $path]) {
        if (is_file("{$path}/TASKS.md")) {
            $texts[$path] = (string) file_get_contents("{$path}/TASKS.md");
        }
    }

    expect($texts)->not->toBeEmpty();
    foreach (Checker::errors($texts) as $path => $expected) {
        expect(errorsOf(TasksFile::parse($texts[$path])))->toBe($expected, $path);
    }
})->with([
    'the demo hub' => [__DIR__.'/../../../demo'],
    'the local hub in MARKBOARD_HUB_PATH' => [getenv('MARKBOARD_HUB_PATH')],
]);

it('reads sections, tasks, metadata, proofs and notes', function () {
    $file = TasksFile::parse(Fixtures::tasks('basic.md'));

    expect($file->isEditable())->toBeTrue()
        ->and(array_map(fn (TaskBlock $task): string => $task->line->id, $file->tasks))->toBe(['T12', 'T15', 'T11', 'T09', 'T10', 'T07'])
        ->and($file->headingIndex(Section::Done))->toBe(16);

    $export = $file->task(12);
    expect($export->section)->toBe(Section::UpNext)
        ->and($export->line->title)->toBe('Export to CSV')
        ->and($export->line->metadata->pairs)->toBe([['due', '2026-10-20']])
        ->and($export->proof())->toBe('the export opens in a spreadsheet with one row per task')
        ->and([$export->start, $export->end])->toBe([5, 6]);

    $signIn = $file->task(10);
    expect($signIn->line->checked)->toBeTrue()
        ->and($signIn->line->metadata->get('from'))->toBe('In progress')
        ->and($signIn->line->metadata->get('evidence'))->toBe('a1b2c3d');

    expect($file->task(11)->notes())->toBe(['waiting on the new icon set for the toggles'])
        ->and($file->task(15)->line->title)->toBe('Keyboard shortcuts (v2)')
        ->and($file->nextNumber())->toBe(16);
});

it('finds a padded ID by number and keeps it as written', function () {
    $file = TasksFile::parse(Fixtures::tasks('padded-ids.md'));

    expect($file->task(1)->line->id)->toBe('T01')
        ->and($file->task(9)->line->id)->toBe('T009')
        ->and($file->nextNumber())->toBe(11);
});

it('ignores task-like lines in the preamble', function () {
    $file = TasksFile::parse(Fixtures::tasks('preamble.md'));

    expect($file->isEditable())->toBeTrue()
        ->and($file->task(40))->toBeNull()
        ->and($file->nextNumber())->toBe(3)
        ->and($file->preamble())->toHaveCount(8);
});

it('keeps blank lines between a task and its proof inside the block, and blank lines after it outside', function () {
    $file = TasksFile::parse(Fixtures::tasks('blank-before-proof.md'));

    expect([$file->task(6)->start, $file->task(6)->end])->toBe([3, 5])
        ->and([$file->task(7)->start, $file->task(7)->end])->toBe([6, 10])
        ->and($file->task(8)->start)->toBe(13);
});

it('counts the last of several proof lines, and keeps notes in order around them', function () {
    $several = TasksFile::parse(Fixtures::tasks('several-proofs.md'))->task(3);
    $noteFirst = TasksFile::parse(Fixtures::tasks('note-before-proof.md'))->task(4);

    expect($several->proof())->toBe('the March close report shows no differences')
        ->and($several->proofs)->toHaveCount(3)
        ->and($several->notes())->toBe(['start with the card payments'])
        ->and($noteFirst->notes())->toBe(['use the hourly samples', 'the chart library is already installed'])
        ->and($noteFirst->proof())->toBe('the chart shows the last 24 hours');
});

it('reads final parentheses as metadata only when every part starts with a known key', function () {
    $file = TasksFile::parse(Fixtures::tasks('title-parens.md'));
    $titles = array_map(fn (TaskBlock $task): string => $task->line->title, $file->tasks);

    expect($titles)->toBe([
        'Keyboard shortcuts (v2)',
        'Meet the map team (Ana)',
        'Refactor (parser, writer)',
        'Ship the importer (v2)',
        'Empty parentheses ()',
        'Due dates (due soon, maybe later)',
    ])->and($file->task(4)->line->metadata->get('due'))->toBe('2026-10-20');
});

it('keeps every pair of a repeated key and uses the first value', function () {
    $file = TasksFile::parse(Fixtures::tasks('repeated-keys.md'));

    expect($file->isEditable())->toBeFalse()
        ->and($file->task(1)->line->metadata->pairs)->toBe([['due', '2026-10-20'], ['due', '2026-10-27']])
        ->and($file->task(1)->line->metadata->get('due'))->toBe('2026-10-20');
});

it('still reads every task it can from a file with errors', function () {
    $file = TasksFile::parse(Fixtures::tasks('errors.md'));

    expect($file->isEditable())->toBeFalse()
        ->and($file->tasks)->toHaveCount(13)
        ->and($file->task(6)->section)->toBe(Section::UpNext);
});

it('makes a file with odd line breaks read-only, with line numbers that match the checker', function (string $name, int $breaks) {
    $file = TasksFile::parse(Fixtures::tasks($name));

    expect($file->isEditable())->toBeFalse()
        ->and($file->lines->errors)->toHaveCount($breaks)
        ->and($file->tasks)->toHaveCount(1);
})->with([
    ['lone-cr.md', 1],
    ['other-line-breaks.md', 8],
]);

it('makes a file with invalid UTF-8 read-only and still reads its tasks', function () {
    $file = TasksFile::parse("# T\n\n## Up next\n- [ ] T1 Caf\xe9 menu\n\n## In progress\n\n## Waiting on\n\n## Done\n");

    expect($file->errors)->toBe([])
        ->and($file->isEditable())->toBeFalse()
        ->and($file->lines->errors[0]->line)->toBe(4)
        ->and($file->task(1)->line->title)->toBe('Caf? menu');
});

it('reports an empty file as missing every section, like the checker', function () {
    expect(errorsOf(TasksFile::parse('')))->toBe(Checker::errors([''])[0]);
});
