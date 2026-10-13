<?php

use App\Night\Costs;
use App\Night\Queue;
use App\Night\StatusLog;
use App\Night\StatusRow;
use App\Night\Triage;
use App\Night\Verdict;
use App\Night\WorkOrder;

/*
| The night run's files (docs/spec.md, Night), parsed without booting the app.
*/

describe('StatusLog', function () {
    $tsv = "lantern\tT10\tready\t2026-10-05 23:40\t4f1c2aa\tverify passed\n"
        ."lantern\tT10\tmerged\t2026-10-06 08:15\t9b0e7d1\tmerged into main\n"
        ."lantern\tT12\tfailed\t2026-10-06 22:30\t\tno report\n"
        ."lantern\tT12\tready\t2026-10-06 23:18\t7c3d9e2\tverify passed; usage worth \$3.40 at API prices over 2 round(s)\n"
        ."fieldnotes\tT3\tstuck\t2026-10-07 00:40\t2a8b6f0\tthree rounds\n"
        ."not a row\n";

    it('puts a time in the night it belongs to: 21:00 to 21:00', function () {
        expect(StatusLog::nightOf('2026-10-06 21:00'))->toBe('2026-10-06')
            ->and(StatusLog::nightOf('2026-10-07 00:40'))->toBe('2026-10-06')
            ->and(StatusLog::nightOf('2026-10-07 20:59'))->toBe('2026-10-06')
            ->and(StatusLog::nightOf('2026-10-06 08:15'))->toBe('2026-10-05');
    });

    it('lists the nights newest first, skipping lines that are not rows', function () use ($tsv) {
        expect(StatusLog::parse($tsv)->nights())->toBe(['2026-10-06', '2026-10-05']);
    });

    it('gives each task its latest row in the night, in first-seen order', function () use ($tsv) {
        $night = StatusLog::parse($tsv)->night('2026-10-06');

        expect(array_map(fn (StatusRow $row): string => "{$row->task} {$row->outcome}", $night))->toBe(['T12 ready', 'T3 stuck'])
            ->and($night[0]->rounds())->toBe(2)
            ->and($night[1]->rounds())->toBe(1);
    });

    it('a merge the morning after belongs to the night before', function () use ($tsv) {
        expect(array_map(fn (StatusRow $row): string => $row->outcome, StatusLog::parse($tsv)->night('2026-10-05')))->toBe(['merged']);
    });

    it('finds a task\'s latest row in any night', function () use ($tsv) {
        expect(StatusLog::parse($tsv)->latest('lantern', 'T10')?->outcome)->toBe('merged')
            ->and(StatusLog::parse($tsv)->latest('lantern', 'T99'))->toBeNull();
    });

    it('writes a row as one line with no stray tabs or line breaks', function () {
        $line = StatusLog::line(new StatusRow('lantern', 'T12', 'merged', '2026-10-07 09:00', 'abc1234', "merged\tby hand\n"));

        expect($line)->toBe("lantern\tT12\tmerged\t2026-10-07 09:00\tabc1234\tmerged by hand \n")
            ->and(StatusLog::parse($line)->rows[0]->reason)->toBe('merged by hand ');
    });
});

describe('Costs', function () {
    it('adds the night\'s usage at API prices, by the header\'s columns', function () {
        $tsv = "time\tproject\ttask\trole\tround\tmodel\teffort\tapi_equiv_usd\n"
            ."2026-10-06 22:05\thub\ttriage\tchief-of-staff\t1\topus\thigh\t2.10\n"
            ."2026-10-07 00:30\tlantern\tT12\tbuilder\t1\topus\thigh\t1.60\n"
            ."2026-10-07 21:30\tlantern\tT13\tbuilder\t1\topus\thigh\t9.99\n"
            ."2026-10-06 23:00\tlantern\tT12\tverifier\t1\tsonnet\thigh\t\n";

        expect(Costs::forNight($tsv, '2026-10-06'))->toBe(3.7)
            ->and(Costs::forNight("time\tcost_usd\n2026-10-06 22:05\t1.00\n", '2026-10-06'))->toBe(0.0);
    });
});

describe('Queue', function () {
    it('reads queue lines with their model and effort, skipping comments', function () {
        expect(Queue::parse("# a comment\nlantern T12 opus xhigh\nmuse-lab T2  # mechanical\n\n"))->toBe([
            ['project' => 'lantern', 'task' => 'T12', 'model' => 'opus', 'effort' => 'xhigh'],
            ['project' => 'muse-lab', 'task' => 'T2', 'model' => 'opus', 'effort' => 'high'],
        ]);
    });
});

describe('WorkOrder', function () {
    $order = "# bramble-bakery T6: Pickup times\n\nstatus: needs-kai\n\n## GOAL\nPick a time.\n\n## MISSING\n1. Which slots?\n";

    it('reads its title, status and sections', function () use ($order) {
        $parsed = WorkOrder::parse($order);

        expect($parsed->title)->toBe('bramble-bakery T6: Pickup times')
            ->and($parsed->needsKai())->toBeTrue()
            ->and($parsed->sections)->toBe(['GOAL' => 'Pick a time.', 'MISSING' => '1. Which slots?']);
    });

    it('adds the answers at the end, keeping every other byte', function () use ($order) {
        $answered = WorkOrder::withAnswers($order, "  Every 30 minutes from 8:00.\n");

        expect($answered)->toBe($order."\n## KAI'S ANSWERS\n\nEvery 30 minutes from 8:00.\n")
            ->and(WorkOrder::parse($answered)->sections["KAI'S ANSWERS"])->toBe('Every 30 minutes from 8:00.');
    });

    it('replaces earlier answers in place, keeping the sections after them', function () use ($order) {
        $first = WorkOrder::withAnswers($order, 'Morning only.')."\n## NOTES\nKeep me.\n";
        $second = WorkOrder::withAnswers($first, 'Every 30 minutes.');

        expect($second)->toBe($order."\n## KAI'S ANSWERS\n\nEvery 30 minutes.\n\n## NOTES\nKeep me.\n");
    });

    it('adds a final newline first when the file has none', function () {
        expect(WorkOrder::withAnswers("# x\n\nstatus: needs-kai", 'Yes.'))->toBe("# x\n\nstatus: needs-kai\n\n## KAI'S ANSWERS\n\nYes.\n");
    });
});

describe('WorkOrder questions', function () {
    it('reads a headline, context and each question with its choices and why', function () {
        $order = WorkOrder::parse(implode("\n", [
            '# shop T6: Pickup times', '', 'status: needs-kai', '', '## MISSING',
            'headline: Pickup times', 'context: The form is built.',
            '1. Which slots?', '   - Morning', '   - Every 30 minutes', '   why: The brief and the email disagree.',
            '2. Same day?', '   - Yes', '',
        ]));

        expect($order->questions())->toBe([
            'headline' => 'Pickup times',
            'context' => 'The form is built.',
            'asks' => [
                ['ask' => 'Which slots?', 'choices' => ['Morning', 'Every 30 minutes'], 'why' => 'The brief and the email disagree.'],
                ['ask' => 'Same day?', 'choices' => ['Yes'], 'why' => ''],
            ],
        ])->and($order->questionCount())->toBe(2);
    });

    it('gives no structure for questions with no choices, so they show as written', function () {
        $order = WorkOrder::parse("# x\n\nstatus: needs-kai\n\n## MISSING\n1. **Which slots?** The brief disagrees.\n2. **Same day?** Blocks ACCEPTANCE.\n");

        expect($order->questions())->toBeNull()->and($order->questionCount())->toBe(2);
    });
});

describe('Triage', function () {
    $note = implode("\n", [
        '# Triage, 2026-10-06', '', 'One project.', '',
        '## Picked', '- **lantern T12**: the CSV export.', '  short: Export to CSV', '  run: sonnet · high', '',
        '## Sent back to Kai', '- **T6** Pickup times: which slots?', '',
        '## Passed over', '- T4, T11: in progress.', '  reason: Already in progress', '- None of the rest.', '',
        '## Quick closes', '- **tidepool T4**: looks superseded.', '  short: Sound for the crab enemies', '  why: Nothing reads it.',
        '- **tidepool T3**: looks done.', '',
        '## Held', '- None.', '',
    ]);

    it('reads each section\'s tasks, text and indented fields', function () use ($note) {
        $triage = Triage::parse($note);

        expect($triage->sections['picked'])->toBe([['project' => 'lantern', 'tasks' => ['T12'], 'text' => 'the CSV export.', 'fields' => ['short' => 'Export to CSV', 'run' => 'sonnet · high']]])
            ->and($triage->sections['sent_back'][0])->toMatchArray(['project' => '', 'tasks' => ['T6'], 'text' => 'Pickup times: which slots?'])
            ->and($triage->sections['passed_over'][0])->toMatchArray(['tasks' => ['T4', 'T11'], 'text' => 'in progress.', 'fields' => ['reason' => 'Already in progress']])
            ->and($triage->sections['passed_over'][1])->toMatchArray(['tasks' => [], 'text' => 'None of the rest.'])
            ->and($triage->sections['quick_closes'])->toHaveCount(2)
            ->and($triage->structured())->toBeTrue()
            ->and(Triage::parse("## Picked\n- **lantern T1**: a task.\n")->structured())->toBeFalse();
    });

    it('keeps a quick close with one line after its fields, every other byte unchanged', function () use ($note) {
        $kept = Triage::withKept($note, 'tidepool', 'T4', '2026-10-13');

        expect($kept)->toBe(str_replace("  why: Nothing reads it.\n", "  why: Nothing reads it.\n  kept: 2026-10-13\n", $note))
            ->and(Triage::withKept($note, 'tidepool', 'T3', '2026-10-13'))->toBe(str_replace("- **tidepool T3**: looks done.\n", "- **tidepool T3**: looks done.\n  kept: 2026-10-13\n", $note))
            ->and(Triage::withKept((string) $kept, 'tidepool', 'T4', '2026-10-14'))->toBeNull()
            ->and(Triage::withKept($note, 'lantern', 'T12', '2026-10-13'))->toBeNull()
            ->and(Triage::withKept($note, 'other', 'T4', '2026-10-13'))->toBeNull();
    });

    it('keeps CRLF line endings and a last line with no newline', function () {
        $note = "## Quick closes\r\n- **tidepool T1**: looks done.";

        expect(Triage::withKept($note, 'tidepool', 'T1', '2026-10-13'))->toBe("## Quick closes\r\n- **tidepool T1**: looks done.\r\n  kept: 2026-10-13\r\n");
    });
});

describe('Verdict', function () {
    it('reads the result, commit, summary, items with their evidence, scope problems and what to look at', function () {
        $verdict = Verdict::parse(implode("\n", [
            'FAIL', '7c3d9e2', '', 'Two problems.', '', '## Items',
            '- PASS: 1. Rows in order (the test checks 3 rows)',
            '- FAIL: 2. Quoting (a title like "Salt, Fat (Acid)" (with parens) breaks)',
            '- INCONCLUSIVE: 3. Looks right with no evidence',
            '', '## Scope problems', '- src/db.ts changed', '', '## Look at', '- the quoting rule', '',
            '(verifier sonnet, plan login)',
        ]));

        expect($verdict->result)->toBe('FAIL')
            ->and($verdict->commit)->toBe('7c3d9e2')
            ->and($verdict->summary)->toBe('Two problems.')
            ->and($verdict->items)->toBe([
                ['result' => 'PASS', 'item' => '1. Rows in order', 'evidence' => 'the test checks 3 rows', 'short' => '', 'says' => ''],
                ['result' => 'FAIL', 'item' => '2. Quoting', 'evidence' => 'a title like "Salt, Fat (Acid)" (with parens) breaks', 'short' => '', 'says' => ''],
                ['result' => 'INCONCLUSIVE', 'item' => '3. Looks right with no evidence', 'evidence' => '', 'short' => '', 'says' => ''],
            ])
            ->and($verdict->scopeProblems)->toBe(['src/db.ts changed'])
            ->and($verdict->lookAt)->toBe(['the quoting rule'])
            ->and($verdict->headline)->toBe('')
            ->and($verdict->confirm)->toBe([]);
    });

    it('reads the optional headline, short names and evidence, and the blocks to confirm by eye', function () {
        $verdict = Verdict::parse(implode("\n", [
            'PASS', '7c3d9e2', '', 'All pass.', '', '## Headline', '', 'Reading lists export to CSV', 'a second line is ignored', '',
            '## Items',
            '- PASS: 1. Rows in order (the test checks 3 rows)',
            '  short: Rows in order',
            '  says: 3 books in, 3 rows out',
            '- PASS: 2. Quoting (round-trips)',
            '', '## Confirm: Check the quoting', 'columns: In the list | In the CSV',
            '- Plain | Plain',
            '### Commas', '- Salt, Fat | "Salt, Fat"', '- A | B | C',
            'note: Plain titles are written as they are.',
            '', '## Confirm: Check the label', '- Export reading list',
        ]));

        expect($verdict->headline)->toBe('Reading lists export to CSV')
            ->and($verdict->items[0])->toMatchArray(['item' => '1. Rows in order', 'short' => 'Rows in order', 'says' => '3 books in, 3 rows out'])
            ->and($verdict->items[1])->toMatchArray(['short' => '', 'says' => ''])
            ->and($verdict->confirm)->toBe([
                [
                    'title' => 'Check the quoting',
                    'columns' => ['In the list', 'In the CSV'],
                    'groups' => [
                        ['label' => '', 'rows' => [['Plain', 'Plain']]],
                        ['label' => 'Commas', 'rows' => [['Salt, Fat', '"Salt, Fat"'], ['A', 'B', 'C']]],
                    ],
                    'note' => 'Plain titles are written as they are.',
                ],
                ['title' => 'Check the label', 'columns' => [], 'groups' => [['label' => '', 'rows' => [['Export reading list']]]], 'note' => ''],
            ]);
    });

    it('reads anything it can\'t make out as INCONCLUSIVE', function () {
        expect(Verdict::parse("maybe\n")->result)->toBe('INCONCLUSIVE');
    });
});
