<?php

use App\Markdown\BriefLinks;

it('links a project and task reference, keeping the text as written', function () {
    expect(BriefLinks::apply('- [lantern] T12 is next.'))
        ->toBe('- [\[lantern\] T12](/projects/lantern/tasks/T12) is next.');
});

it('links every reference in a line, padded IDs included', function () {
    expect(BriefLinks::apply('[a-b] T09 and [c] T1'))
        ->toBe('[\[a-b\] T09](/projects/a-b/tasks/T09) and [\[c\] T1](/projects/c/tasks/T1)');
});

it('leaves anything that is not a project id followed by a task ID', function (string $text) {
    expect(BriefLinks::apply($text))->toBe($text);
})->with([
    'no task' => '[tidepool] is paused',
    'not kebab-case' => '[Lantern] T12',
    'no space' => '[lantern]T12',
    'not an ID' => '[lantern] T12x',
    'a link already' => '[lantern](https://example.com) T12',
]);
