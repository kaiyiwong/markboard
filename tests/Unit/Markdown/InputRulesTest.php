<?php

use App\Markdown\InputRules;
use Tests\Support\Checker;

/**
 * A TASKS.md with the given lines in Up next, Waiting on and Done, built by plain string pasting:
 * what a writer without InputRules would produce.
 */
function pasted(string $upNext = '', string $waitingOn = '', string $done = ''): string
{
    return "# Rules\n\n## Up next\n{$upNext}\n\n## In progress\n\n## Waiting on\n{$waitingOn}\n\n## Done\n{$done}\n";
}

/** The checker's error messages for a pasted file. @return list<string> */
function checkerMessages(string $text): array
{
    return array_column(Checker::errors([$text])[0] ?? [], 'message');
}

describe('trim', function () {
    it('trims every character Python counts as whitespace, no-break and ideographic spaces included', function () {
        expect(InputRules::trim("\u{a0}\u{3000} Ship it \u{2003}\u{202f}"))->toBe('Ship it')
            ->and(InputRules::trim("Ship\u{a0}it"))->toBe("Ship\u{a0}it");
    });

    it('guards the format: a proof of only a no-break space breaks the file if it is not trimmed away', function () {
        expect(InputRules::proseLine(InputRules::trim("\u{a0}")))->not->toBeNull()
            ->and(checkerMessages(pasted("- [ ] T1 Task\n  proof: \u{a0}")))
            ->toBe(['indented line must be "  proof: ..." or "  note: ..."']);
    });
});

describe('title', function () {
    it('accepts an ordinary title, and parentheses that are not metadata', function (string $title) {
        expect(InputRules::title($title))->toBeNull();
    })->with(['Export to CSV', 'Keyboard shortcuts (v2)', 'Meet the map team (Ana)', 'Refactor (parser, writer)', 'Due dates (due soon, maybe later)']);

    it('refuses an empty title, which the checker would not read as a task', function () {
        expect(InputRules::title(''))->toBe('The :attribute must not be empty.')
            ->and(InputRules::title(InputRules::trim(" \u{a0}\u{3000}")))->toBe('The :attribute must not be empty.')
            ->and(checkerMessages(pasted('- [ ] T1 ')))->toBe(['text between sections']);
    });

    it('refuses line breaks, which would split the task in two', function (string $break) {
        expect(InputRules::title("First{$break}Second"))->toContain('line breaks')
            ->and(checkerMessages(pasted("- [ ] T1 First{$break}Second")))->toBe(['text between sections']);
    })->with(["\n", "\r", "\x0b", "\x0c", "\x1c", "\x1d", "\x1e", "\u{85}", "\u{2028}", "\u{2029}"]);

    it('refuses other control characters', function (string $control) {
        expect(InputRules::title("Bell{$control}"))->toContain('control characters');
    })->with(["\x00", "\t", "\x1b", "\x1f", "\x7f"]);

    it('refuses a title ending in parentheses that read as metadata', function (string $title) {
        expect(InputRules::title($title))->toContain('read as task metadata');
    })->with(['Ship (due 2026-11-01)', 'Release (done)', 'Chase (waiting Bob)', 'Both (due 2026-11-01, evidence abc)']);

    it('guards the format: such a title turns into metadata the checker rejects', function () {
        expect(checkerMessages(pasted('- [ ] T1 Release (done)')))->toBe(['metadata key "done" has no value']);
    });

    it('allows 300 characters, counting characters rather than bytes', function () {
        expect(InputRules::title(str_repeat('é', 300)))->toBeNull()
            ->and(InputRules::title(str_repeat('é', 301)))->toBe('The :attribute must be at most 300 characters.');
    });

    it('refuses invalid UTF-8', function () {
        expect(InputRules::title("Caf\xe9"))->toBe('The :attribute must be valid UTF-8.');
    });
});

describe('proof and note', function () {
    it('accepts ordinary text', function () {
        expect(InputRules::proseLine('the export opens in a spreadsheet, with (v2) columns'))->toBeNull();
    });

    it('refuses empty text, which the checker rejects as an indented line', function () {
        expect(InputRules::proseLine(''))->toBe('The :attribute must not be empty.')
            ->and(checkerMessages(pasted("- [ ] T1 Task\n  note: ")))->toBe(['indented line must be "  proof: ..." or "  note: ..."']);
    });

    it('refuses line breaks and control characters, which would leave text between sections', function (string $control) {
        expect(InputRules::proseLine("one{$control}two"))->toContain('line breaks')
            ->and(InputRules::metadataValue("one{$control}two"))->toContain('line breaks');
    })->with(["\n", "\r", "\x0b", "\x0c", "\x1c", "\x1d", "\x1e", "\u{85}", "\u{2028}", "\u{2029}", "\x00", "\t", "\x7f"]);

    it('guards the format: a line break in a proof or in waiting breaks the file', function () {
        expect(checkerMessages(pasted("- [ ] T1 Task\n  proof: one\ntwo")))->toBe(['text between sections'])
            ->and(checkerMessages(pasted(waitingOn: "- [ ] T1 Task (waiting Ana\nBob, since 2026-10-01)")))
            ->toBe(['Waiting on task needs waiting', 'Waiting on task needs since', 'text between sections']);
    });

    it('allows 500 characters', function () {
        expect(InputRules::proseLine(str_repeat('a', 500)))->toBeNull()
            ->and(InputRules::proseLine(str_repeat('a', 501)))->toBe('The :attribute must be at most 500 characters.');
    });
});

describe('waiting and evidence', function () {
    it('accepts ordinary values', function (string $value) {
        expect(InputRules::metadataValue($value))->toBeNull();
    })->with(['Ana', 'the design team', 'a1b2c3d', 'PR #42']);

    it('refuses commas, which split the value into a part that is not a key', function () {
        expect(InputRules::metadataValue('Ana, Bob'))->toBe('The :attribute must not contain commas or parentheses.')
            ->and(checkerMessages(pasted(waitingOn: '- [ ] T1 Task (waiting Ana, Bob, since 2026-10-01)')))
            ->toBe(['Waiting on task needs waiting', 'Waiting on task needs since'])
            ->and(checkerMessages(pasted(done: '- [x] T1 Task (done 2026-10-01, evidence a, b, from Up next)')))
            ->toBe(['Done task needs done (or cancelled)']);
    });

    it('refuses parentheses, which stop the metadata from being read at all', function (string $value) {
        expect(InputRules::metadataValue($value))->toBe('The :attribute must not contain commas or parentheses.')
            ->and(checkerMessages(pasted(waitingOn: "- [ ] T1 Task (waiting {$value}, since 2026-10-01)")))
            ->toBe(['Waiting on task needs waiting', 'Waiting on task needs since']);
    })->with(['Ana (PM)', 'Ana (', 'Ana )']);

    it('refuses an empty value', function () {
        expect(InputRules::metadataValue(''))->toBe('The :attribute must not be empty.')
            ->and(checkerMessages(pasted(waitingOn: '- [ ] T1 Task (waiting , since 2026-10-01)')))
            ->toBe(['metadata key "waiting" has no value']);
    });

    it('refuses line breaks and allows 500 characters', function () {
        expect(InputRules::metadataValue("Ana\nBob"))->toContain('line breaks')
            ->and(InputRules::metadataValue(str_repeat('a', 500)))->toBeNull()
            ->and(InputRules::metadataValue(str_repeat('a', 501)))->toBe('The :attribute must be at most 500 characters.');
    });
});

describe('due', function () {
    it('accepts a real date written as YYYY-MM-DD', function (string $date) {
        expect(InputRules::date($date))->toBeNull();
    })->with(['2026-10-20', '2028-02-29']);

    it('refuses anything else, which the checker reports as a bad date', function (string $date) {
        expect(InputRules::date($date))->toBe('The :attribute must be a real date written as YYYY-MM-DD.')
            ->and(checkerMessages(pasted("- [ ] T1 Task (due {$date})")))->toBe(["bad date for \"due\": {$date}"]);
    })->with(['2026-02-30', '2027-02-29', '2026-2-3', '26-10-20', 'tomorrow', '0000-01-01', '2026-10-20x']);
});
