<?php

use App\Actions\Operation;
use App\Models\Conflict;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\Support\Checker;

/*
| Answering a task waiting on Kai (docs/spec.md, Review), on a temp copy of demo/ (see TestCase).
| Fieldnotes T4 has a pick card, T5 no card; Bramble Bakery T7 an approve card; Job search T5 a
| choose card. Each answer is one write: the task's note and move, nothing else, and the card goes.
*/

beforeEach(function () {
    $this->travelTo('2026-10-07 12:00:00');
    foreach (['header-1', 'header-2', 'header-3'] as $image) {
        File::put($this->hub."/projects/fieldnotes/designs/{$image}.png", "\x89PNG");
    }
    foreach (['order-before', 'order-after'] as $image) {
        File::put($this->hub."/projects/bramble-bakery/designs/{$image}.png", "\x89PNG");
    }
    $this->fieldnotes = $this->hub.'/projects/fieldnotes/TASKS.md';
});

function answer(string $project, string $task, array $body): TestResponse
{
    $tasks = test()->hub."/projects/{$project}/TASKS.md";

    return test()->withHeaders(ifMatch($tasks))->postJson("/api/v1/review/{$project}/{$task}/answer", $body);
}

function fieldnotesUpNext(): string
{
    $bytes = (string) file_get_contents(test()->fieldnotes);
    expect(Checker::errors([$bytes]))->toBe([[]]);

    return substr($bytes, strpos($bytes, "## Up next\n") + 11, strpos($bytes, '- [ ] T2') - strpos($bytes, "## Up next\n") - 11);
}

it('writes a pick as a note, moves the task to the top of Up next without waiting and since, and deletes the card', function () {
    answer('fieldnotes', 'T4', ['answer' => 'pick', 'value' => '2 Title first'])
        ->assertOk()
        ->assertJsonPath('data.task_id', 'T4')
        ->assertJsonPath('data.section', 'Up next')
        ->assertHeader('ETag');

    expect(fieldnotesUpNext())->toBe("- [ ] T4 Pick the case study header\n  note: Kai 2026-10-07: picked 2 Title first\n")
        ->and(file_get_contents($this->fieldnotes))->not->toContain('(waiting Kai, since 2026-10-06)')
        ->and(is_file($this->hub.'/projects/fieldnotes/review/T4.json'))->toBeFalse();
});

it('adds Kai\'s note after the answer, and keeps the task\'s own notes first', function () {
    answer('fieldnotes', 'T5', ['answer' => 'reply', 'note' => 'cut it to one screen'])->assertOk();

    expect(fieldnotesUpNext())->toBe("- [ ] T5 Keep the kiln story long, or cut it to one screen\n"
        ."  note: the long version has the firing photos; the short one ends on the result\n"
        ."  note: Kai 2026-10-07: cut it to one screen\n");

    answer('bramble-bakery', 'T7', ['answer' => 'redo', 'note' => 'the pay button is cut off'])->assertOk();
    expect(file_get_contents($this->hub.'/projects/bramble-bakery/TASKS.md'))
        ->toContain("## Up next\n- [ ] T7 New order page\n  note: Kai 2026-10-07: redo; the pay button is cut off\n");
});

it('writes approve and choose answers', function () {
    answer('bramble-bakery', 'T7', ['answer' => 'approve'])->assertOk();
    expect(file_get_contents($this->hub.'/projects/bramble-bakery/TASKS.md'))->toContain("- [ ] T7 New order page\n  note: Kai 2026-10-07: approved\n");

    answer('job-search', 'T5', ['answer' => 'choose', 'value' => 'Thu Oct 16'])->assertOk();
    expect(file_get_contents($this->hub.'/projects/job-search/TASKS.md'))->toContain("  note: Kai 2026-10-07: chose Thu Oct 16\n")
        ->and(is_file($this->hub.'/projects/job-search/review/T5.json'))->toBeFalse();
});

it('ticks or cancels with Done and Drop, with or without a card, adding a note line first when given', function () {
    answer('fieldnotes', 'T5', ['answer' => 'done'])->assertOk()->assertJsonPath('data.section', 'Done');
    expect(file_get_contents($this->fieldnotes))->toContain("## Done\n- [x] T5 Keep the kiln story long, or cut it to one screen (waiting Kai, since 2026-09-29, done 2026-10-07, from Waiting on)\n");

    answer('fieldnotes', 'T4', ['answer' => 'drop', 'note' => 'kept the old header'])->assertOk();
    expect(file_get_contents($this->fieldnotes))
        ->toContain("## Done\n- [x] T4 Pick the case study header (waiting Kai, since 2026-10-06, cancelled 2026-10-07, from Waiting on)\n  note: Kai 2026-10-07: kept the old header\n")
        ->and(is_file($this->hub.'/projects/fieldnotes/review/T4.json'))->toBeFalse();
});

it('refuses a value that is not on the card, and an answer the card does not take (422)', function () {
    $before = file_get_contents($this->fieldnotes);

    answer('fieldnotes', 'T4', ['answer' => 'pick', 'value' => '4 Nope'])->assertUnprocessable()->assertJsonValidationErrors('value');
    answer('fieldnotes', 'T4', ['answer' => 'pick'])->assertUnprocessable()->assertJsonValidationErrors('value');
    answer('fieldnotes', 'T5', ['answer' => 'reply'])->assertUnprocessable()->assertJsonValidationErrors('note');
    answer('fieldnotes', 'T4', ['answer' => 'vote'])->assertUnprocessable()->assertJsonValidationErrors('answer');
    answer('fieldnotes', 'T4', ['answer' => 'reply', 'note' => str_repeat('x', 490)])->assertUnprocessable()->assertJsonValidationErrors('note');
    answer('fieldnotes', 'T2', ['answer' => 'reply', 'note' => 'fine'])->assertUnprocessable();

    expect(file_get_contents($this->fieldnotes))->toBe($before)
        ->and(is_file($this->hub.'/projects/fieldnotes/review/T4.json'))->toBeTrue();
});

it('refuses a card answer when there is no card, a bad one, or one of another kind (409)', function () {
    answer('fieldnotes', 'T5', ['answer' => 'approve'])->assertConflict();
    answer('job-search', 'T5', ['answer' => 'pick', 'value' => 'Thu Oct 16'])->assertConflict();
    File::delete($this->hub.'/projects/fieldnotes/designs/header-2.png');
    answer('fieldnotes', 'T4', ['answer' => 'pick', 'value' => '1 Photo first'])->assertConflict();

    expect(file_get_contents($this->fieldnotes))->toContain('(waiting Kai, since 2026-10-06)');
});

it('keeps the card and records a conflict when TASKS.md changed since the page (412), and needs If-Match (428)', function () {
    $seen = ifMatch($this->fieldnotes);
    file_put_contents($this->fieldnotes, str_replace('- [ ] T3 RSS feed', '- [ ] T3 RSS feed for notes', (string) file_get_contents($this->fieldnotes)));

    $this->withHeaders($seen)->postJson('/api/v1/review/fieldnotes/T4/answer', ['answer' => 'pick', 'value' => '1 Photo first'])->assertStatus(412);
    expect(Conflict::sole()->operation)->toBe(Operation::AnswerTask)
        ->and(Conflict::sole()->parameters)->toMatchArray(['number' => 4, 'outcome' => 'up-next', 'note' => 'Kai 2026-10-07: picked 1 Photo first'])
        ->and(Conflict::sole()->edit()->summary())->toBe('Answer T4')
        ->and(is_file($this->hub.'/projects/fieldnotes/review/T4.json'))->toBeTrue();

    $this->flushHeaders()->postJson('/api/v1/review/fieldnotes/T4/answer', ['answer' => 'approve'])->assertStatus(428);
});
