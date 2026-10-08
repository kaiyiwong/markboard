<?php

use App\Markdown\Brief;

it('splits a brief into its title, intro and sections, counting each section\'s list items', function () {
    $brief = Brief::parse("# Today, 2026-10-08\n\nA line before the sections.\n\n## Top today (max 5)\n1. [a] T1 first\n2. [b] second\n\n## Changed\n\n## Notes\nPlain text, no list.\n");

    expect($brief->title)->toBe('Today, 2026-10-08')
        ->and($brief->intro)->toBe('A line before the sections.')
        ->and($brief->sections)->toBe([
            ['title' => 'Top today (max 5)', 'markdown' => "1. [a] T1 first\n2. [b] second", 'items' => 2],
            ['title' => 'Changed', 'markdown' => '', 'items' => 0],
            ['title' => 'Notes', 'markdown' => 'Plain text, no list.', 'items' => 0],
        ]);
});

it('never reads a heading or a list item inside a fenced code block', function () {
    $brief = Brief::parse("# T\n\n## Code\n```\n## not a section\n- not an item\n```\n- an item\n");

    expect($brief->sections)->toHaveCount(1)
        ->and($brief->sections[0]['items'])->toBe(1)
        ->and($brief->sections[0]['markdown'])->toContain('## not a section');
});

it('has no title or sections for a brief with neither, reading CRLF line breaks', function () {
    $brief = Brief::parse("Just a note.\r\nSecond line.\r\n");

    expect($brief->title)->toBeNull()
        ->and($brief->intro)->toBe("Just a note.\nSecond line.")
        ->and($brief->sections)->toBe([]);
});
