<?php

use App\Markdown\Line;
use App\Markdown\Lines;

it('splits at every line boundary Python uses and keeps each terminator', function () {
    $boundaries = ["\r\n", "\n", "\r", "\x0b", "\x0c", "\x1c", "\x1d", "\x1e", "\u{85}", "\u{2028}", "\u{2029}"];
    $text = implode('', array_map(fn (string $eol, int $i): string => "line {$i}{$eol}", $boundaries, array_keys($boundaries)));

    $lines = Lines::split($text)->lines;

    expect(array_map(fn (Line $line): string => $line->terminator, $lines))->toBe($boundaries)
        ->and($lines[4]->content)->toBe('line 4');
});

it('treats \r\r\n as two line breaks, as universal newlines do', function () {
    expect(Lines::split("a\r\r\nb")->lines)->toEqual([new Line('a', "\r"), new Line('', "\r\n"), new Line('b', '')]);
});

it('gives an empty file no lines and a last line without a break an empty terminator', function () {
    expect(Lines::split('')->lines)->toBe([])
        ->and(Lines::split("a\n")->lines)->toEqual([new Line('a', "\n")])
        ->and(Lines::split("a\n\n")->lines)->toEqual([new Line('a', "\n"), new Line('', "\n")])
        ->and(Lines::split("a\nb")->lines)->toEqual([new Line('a', "\n"), new Line('b', '')]);
});

it('reports line breaks other than \n and \r\n as file errors on their line', function () {
    $errors = Lines::split("a\nb\rc\r\nd\u{2028}e")->errors;

    expect($errors)->toHaveCount(2)
        ->and([$errors[0]->line, $errors[0]->message])->toBe([2, 'line break U+000D: only \n and \r\n line breaks can be edited'])
        ->and([$errors[1]->line, $errors[1]->message])->toBe([4, 'line break U+2028: only \n and \r\n line breaks can be edited']);
});

it('reports invalid UTF-8 on its line and still splits the file', function () {
    $lines = Lines::split("ok\nbad \xff byte\nok\n");

    expect($lines->lines)->toHaveCount(3)
        ->and($lines->errors)->toHaveCount(1)
        ->and([$lines->errors[0]->line, $lines->errors[0]->message])->toBe([2, 'not valid UTF-8']);
});

it('picks the more common line break for new lines, with ties and one-line files going to \n', function (string $text, string $expected) {
    expect(Lines::split($text)->dominantTerminator())->toBe($expected);
})->with([
    'all \n' => ["a\nb\n", "\n"],
    'mostly \r\n' => ["a\r\nb\r\nc\n", "\r\n"],
    'a tie' => ["a\r\nb\n", "\n"],
    'one \r\n line' => ["a\r\n", "\n"],
    'no final break, \r\n before it' => ["a\r\nb\r\nc", "\r\n"],
]);

it('lets the last line move without joining lines, then restores the missing final newline', function () {
    $lines = Lines::split("a\nb");
    $editing = $lines->forEditing();

    expect($editing[1]->terminator)->toBe("\n")
        ->and($lines->finish(array_reverse($editing)))->toBe("b\na");
});
