<?php

use App\Markdown\LineDiff;

/*
| The conflict panel's line diff: changes with two lines of context, removals before additions,
| line numbers on both sides.
*/

it('is empty for identical versions', function () {
    expect(LineDiff::between("a\nb\n", "a\nb\n"))->toBe([]);
});

it('shows a changed line as removed then added, with two lines of context', function () {
    expect(LineDiff::between("1\n2\n3\n4\n5\n6\n7\n", "1\n2\n3\nfour\n5\n6\n7\n"))->toBe([
        ['op' => 'same', 'old' => 2, 'new' => 2, 'text' => '2'],
        ['op' => 'same', 'old' => 3, 'new' => 3, 'text' => '3'],
        ['op' => 'removed', 'old' => 4, 'new' => null, 'text' => '4'],
        ['op' => 'added', 'old' => null, 'new' => 4, 'text' => 'four'],
        ['op' => 'same', 'old' => 5, 'new' => 5, 'text' => '5'],
        ['op' => 'same', 'old' => 6, 'new' => 6, 'text' => '6'],
    ]);
});

it('numbers lines on both sides after an insertion, and leaves out far-away context', function () {
    $diff = LineDiff::between("a\nb\nc\nd\ne\nf\ng\nh\n", "new\na\nb\nc\nd\ne\nf\ng\nh\nend\n");

    expect($diff)->toBe([
        ['op' => 'added', 'old' => null, 'new' => 1, 'text' => 'new'],
        ['op' => 'same', 'old' => 1, 'new' => 2, 'text' => 'a'],
        ['op' => 'same', 'old' => 2, 'new' => 3, 'text' => 'b'],
        ['op' => 'same', 'old' => 7, 'new' => 8, 'text' => 'g'],
        ['op' => 'same', 'old' => 8, 'new' => 9, 'text' => 'h'],
        ['op' => 'added', 'old' => null, 'new' => 10, 'text' => 'end'],
    ]);
});

it('keeps the longest run of common lines when blocks move', function () {
    $diff = LineDiff::between("x\nA\nB\nC\ny\n", "x\nB\nC\nA\ny\n");

    expect(array_map(fn (array $line): string => $line['op'][0].$line['text'], $diff))
        ->toBe(['sx', 'rA', 'sB', 'sC', 'aA', 'sy']);
});

it('shows invalid UTF-8 and other line breaks as text it can send as JSON', function () {
    $diff = LineDiff::between("a\r\nb\n", "a\r\n\xFFb\n");

    expect(json_encode($diff))->not->toBeFalse()
        ->and($diff[1])->toBe(['op' => 'removed', 'old' => 2, 'new' => null, 'text' => 'b']);
});
