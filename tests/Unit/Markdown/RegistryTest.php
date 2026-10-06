<?php

use App\Markdown\FormatError;
use App\Markdown\Registry;

const REGISTRY_HEADER = "| id | name | path | category | status | next milestone | docs |\n|---|---|---|---|---|---|---|\n";

it('reads each row by header name and resolves relative paths in a demo hub', function () {
    $registry = Registry::parse(
        "# Projects\n\n| docs | status | id | path | name | category | next milestone |\n|---|---|---|---|---|---|---|\n"
        ."| docs/spec.md | active | lantern | projects/lantern/ | Lantern | product | Public beta |\n"
        ."| | paused | tidepool | /srv/tidepool | Tidepool | game | |\n\nFree text after.\n",
        '/hub/',
        demoKind: true,
    );

    expect($registry->errors)->toBe([])
        ->and($registry->projects)->toBe([
            ['id' => 'lantern', 'name' => 'Lantern', 'path' => '/hub/projects/lantern', 'category' => 'product', 'status' => 'active', 'next_milestone' => 'Public beta', 'docs' => 'docs/spec.md'],
            ['id' => 'tidepool', 'name' => 'Tidepool', 'path' => '/srv/tidepool', 'category' => 'game', 'status' => 'paused', 'next_milestone' => '', 'docs' => ''],
        ]);
});

it('skips and lists each malformed row', function (string $row, string $message) {
    $registry = Registry::parse(REGISTRY_HEADER."| lantern | Lantern | /p/lantern | product | active | | |\n{$row}\n", '/hub', demoKind: false);

    expect(array_column($registry->projects, 'id'))->toBe(['lantern'])
        ->and($registry->errors)->toEqual([new FormatError(4, $message)]);
})->with([
    'too few cells' => ['| ember | Ember | /p/ember | game | active |', 'row has 5 cells, the header has 7'],
    'too many cells' => ['| ember | Ember | /p/ember | game | active | | | extra |', 'row has 8 cells, the header has 7'],
    'empty id' => ['|  | Ember | /p/ember | game | active | | |', 'id "" is not kebab-case'],
    'id not kebab-case' => ['| Ember_2 | Ember | /p/ember | game | active | | |', 'id "Ember_2" is not kebab-case'],
    'id with a trailing dash' => ['| ember- | Ember | /p/ember | game | active | | |', 'id "ember-" is not kebab-case'],
    'id too long' => ['| '.str_repeat('a', 65).' | Ember | /p/ember | game | active | | |', 'id is longer than 64 characters'],
    'unknown category' => ['| ember | Ember | /p/ember | blog | active | | |', 'category "blog" is not one of own-site, client, job, product, game, gen-ai, personal'],
    'unknown status' => ['| ember | Ember | /p/ember | game | archived | | |', 'status "archived" is not one of active, paused, done'],
    'empty path' => ['| ember | Ember |  | game | active | | |', 'path is empty'],
    'relative path in a real hub' => ['| ember | Ember | projects/ember | game | active | | |', 'path "projects/ember" is relative: only a demo hub may use relative paths'],
    'repeated id' => ['| lantern | Lantern again | /p/other | product | active | | |', 'duplicate id lantern (first on line 3)'],
    'repeated path' => ['| ember | Ember | /p/lantern/ | game | active | | |', 'duplicate path /p/lantern (first on line 3)'],
]);

it('accepts an id of exactly 64 characters', function () {
    $id = str_repeat('a', 64);

    expect(Registry::parse(REGISTRY_HEADER."| {$id} | A | /p/a | game | active | | |\n", '/hub', demoKind: false)->projects)
        ->toHaveCount(1);
});

it('reports a file with no registry table', function () {
    $registry = Registry::parse("# Projects\n\n| id | name |\n|---|---|\n| a | A |\n", '/hub', demoKind: true);

    expect($registry->projects)->toBe([])
        ->and($registry->errors)->toEqual([new FormatError(0, 'no table with the columns id, name, path, category, status, next milestone, docs')]);
});

it('reads the rows under a header with no separator, and says so', function () {
    $registry = Registry::parse("| id | name | path | category | status | next milestone | docs |\n| a | A | /p/a | game | active | | |\n", '/hub', demoKind: false);

    expect(array_column($registry->projects, 'id'))->toBe(['a'])
        ->and($registry->errors)->toEqual([new FormatError(1, 'no separator line under the table header')]);
});
