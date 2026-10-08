<?php

use App\Markdown\BriefLinks;

const BRIEF_PROJECTS = ['lantern' => 'Lantern', 'a-b' => 'A & B', 'c' => 'C', 'odd' => 'Odd [name]*'];

it('links a project tag to the project by name, and a task ID after it to the task', function () {
    expect(BriefLinks::apply('- [lantern] T12 is next.', BRIEF_PROJECTS))
        ->toBe('- [Lantern](/projects/lantern) [T12](/projects/lantern/tasks/T12) is next.');
});

it('links a project tag with no task ID to the project', function () {
    expect(BriefLinks::apply('- [lantern] 0052 data run', BRIEF_PROJECTS))
        ->toBe('- [Lantern](/projects/lantern) 0052 data run');
});

it('links every tag in a line, padded IDs included', function () {
    expect(BriefLinks::apply('[a-b] T09 and [c] T1', BRIEF_PROJECTS))
        ->toBe('[A & B](/projects/a-b) [T09](/projects/a-b/tasks/T09) and [C](/projects/c) [T1](/projects/c/tasks/T1)');
});

it('keeps Markdown punctuation in a project name as text', function () {
    expect(BriefLinks::apply('[odd] T3', BRIEF_PROJECTS))->toBe('[Odd \[name\]\*](/projects/odd) [T3](/projects/odd/tasks/T3)');
});

it('leaves anything that is not a registered project tag', function (string $text) {
    expect(BriefLinks::apply($text, BRIEF_PROJECTS))->toBe($text);
})->with([
    'unknown project' => '[tidepool] T4 is paused',
    'a checkbox' => '- [x] done',
    'not kebab-case' => '[Lantern] T12',
    'no space after' => '[lantern]T12',
    'a link already' => '[lantern](https://example.com) T12',
]);

it('links the project but not a task ID that is not one', function () {
    expect(BriefLinks::apply('[lantern] T12x', BRIEF_PROJECTS))->toBe('[Lantern](/projects/lantern) T12x');
});
