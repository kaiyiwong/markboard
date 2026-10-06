<?php

use App\Markdown\FormatError;
use App\Markdown\PipelineFile;
use App\Markdown\PipelineRow;
use Tests\Support\Fixtures;

/**
 * @param  list<FormatError>  $errors
 * @return list<array{int, string}>
 */
function lineErrors(array $errors): array
{
    return array_map(fn (FormatError $error): array => [$error->line, $error->message], $errors);
}

/**
 * One case per format error: the fixture, its format errors, its file errors and how many rows it still reads.
 *
 * @return array<string, array{string, list<array{int, string}>, list<array{int, string}>, int}>
 */
function errorFixtures(): array
{
    return [
        'no table' => ['error-no-table.md', [[0, 'no table with the columns company, role, stage, next action, date']], [], 0],
        'no separator' => ['error-no-separator.md', [[3, 'no separator line under the table header']], [], 1],
        'wrong cell count' => ['error-cell-count.md', [[5, 'row has 4 cells, the header has 5'], [6, 'row has 6 cells, the header has 5']], [], 1],
        'unknown stage' => ['error-stage.md', [[5, 'stage "ghosted" is not one of applied, screening, interviewing, offer, accepted, rejected, withdrawn, closed']], [], 1],
        'bad dates' => ['error-date.md', [[5, 'bad date: 2026-02-30'], [6, 'bad date: 9 Oct']], [], 2],
        'invalid UTF-8' => ['error-invalid-utf8.md', [], [[5, 'not valid UTF-8']], 1],
        'a lone \r' => ['error-line-break.md', [], [[3, 'line break U+000D: only \n and \r\n line breaks can be edited']], 1],
    ];
}

/** @return list<array<string, string>> */
function rowCells(PipelineFile $file): array
{
    return array_map(fn (PipelineRow $row): array => $row->cells(), $file->rows);
}

it('reads and writes back every fixture byte for byte', function (string $name) {
    $bytes = Fixtures::pipeline($name);
    $lines = PipelineFile::parse($bytes)->lines;

    expect($lines->finish($lines->forEditing()))->toBe($bytes);
})->with(Fixtures::pipelineDataset());

it('makes every fixture without a format error editable', function (string $name) {
    $file = PipelineFile::parse(Fixtures::pipeline($name));

    expect($file->errors)->toBe([])
        ->and($file->lines->errors)->toBe([])
        ->and($file->isEditable())->toBeTrue();
})->with(array_filter(Fixtures::pipelineDataset(), fn (array $case): bool => ! str_starts_with($case[0], 'error-')));

it('reads each row by column name, numbered by position from 1', function () {
    $file = PipelineFile::parse(Fixtures::pipeline('basic.md'));
    $northwind = $file->row(1);

    expect($file->table->headerIndex)->toBe(2)
        ->and($file->rows)->toHaveCount(3)
        ->and([$northwind->company, $northwind->role, $northwind->stage, $northwind->nextAction, $northwind->date])
        ->toBe(['Northwind', 'Senior Engineer', 'interviewing', 'prepare system design', '2026-10-14'])
        ->and($northwind->index)->toBe(4)
        ->and($file->row(3)->company)->toBe('Fabrikam')
        ->and($file->row(0))->toBeNull()
        ->and($file->row(4))->toBeNull();
});

it('reads columns in whatever order the header gives them, with alignment colons in the separator', function () {
    $file = PipelineFile::parse(Fixtures::pipeline('columns-reordered.md'));

    expect($file->table->columns)->toBe(['stage', 'company', 'date', 'role', 'next action'])
        ->and(rowCells($file))->toBe([
            ['company' => 'Northwind', 'role' => 'Senior Engineer', 'stage' => 'screening', 'next action' => 'send the portfolio link', 'date' => '2026-10-14'],
            ['company' => 'Tailspin', 'role' => 'Lead Developer', 'stage' => 'offer', 'next action' => 'agree a start date', 'date' => ''],
        ]);
});

it('reads an empty table as no rows', function (string $name) {
    $file = PipelineFile::parse(Fixtures::pipeline($name));

    expect($file->isEditable())->toBeTrue()
        ->and($file->rows)->toBe([])
        ->and($file->table->lastIndex())->toBe($file->table->headerIndex + 1);
})->with(['empty-table.md', 'empty-table-no-final-newline.md']);

it('reads an empty date as empty, with or without spaces between the pipes', function () {
    $file = PipelineFile::parse(Fixtures::pipeline('empty-date.md'));

    expect(array_map(fn (PipelineRow $row): string => $row->date, $file->rows))->toBe(['', '']);
});

it('reads only the first table with the pipeline columns, and ends it at the first line that is not a table line', function () {
    $file = PipelineFile::parse(Fixtures::pipeline('free-text-around.md'));

    expect($file->table->headerIndex)->toBe(8)
        ->and(array_map(fn (PipelineRow $row): string => $row->company, $file->rows))->toBe(['Northwind', 'Contoso']);
});

it('reads table lines with leading spaces, no outer pipes or uneven spacing', function () {
    $file = PipelineFile::parse(Fixtures::pipeline('loose-pipes.md'));

    expect($file->isEditable())->toBeTrue()
        ->and(rowCells($file)[1])->toBe(['company' => 'Contoso', 'role' => 'Backend Developer', 'stage' => 'applied', 'next action' => 'wait for a reply', 'date' => '2026-10-09']);
});

it('makes each format-error fixture read-only, with its errors and line numbers', function (string $name, array $formatErrors, array $fileErrors, int $rows) {
    $file = PipelineFile::parse(Fixtures::pipeline($name));

    expect($file->isEditable())->toBeFalse()
        ->and(lineErrors($file->errors))->toBe($formatErrors)
        ->and(lineErrors($file->lines->errors))->toBe($fileErrors)
        ->and($file->rows)->toHaveCount($rows);
})->with(errorFixtures());

it('has a case above for every error fixture', function () {
    $errorFixtures = array_values(array_filter(Fixtures::pipelineNames(), fn (string $name): bool => str_starts_with($name, 'error-')));
    $cases = array_column(errorFixtures(), 0);
    sort($cases);

    expect($cases)->toBe($errorFixtures);
});

it('keeps positions counting a row with the wrong cell count, so later rows keep their numbers', function () {
    $file = PipelineFile::parse(Fixtures::pipeline('error-cell-count.md'));

    expect($file->row(3)->company)->toBe('Fabrikam')
        ->and($file->row(1))->toBeNull();
});

it('reports an empty file as having no table', function () {
    $file = PipelineFile::parse('');

    expect($file->table)->toBeNull()
        ->and($file->isEditable())->toBeFalse();
});
