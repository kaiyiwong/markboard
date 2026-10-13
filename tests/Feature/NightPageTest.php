<?php

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/*
| The Night page, on a temp copy of demo/ (see TestCase): a made-up night run in its orders/ folder.
| The demo projects aren't git repos, so the ready task's branch can't be shown; NightApiTest builds
| a real repo for the branch actions.
*/

it('shows the latest night, its tasks grouped by what they need, and the night\'s usage', function () {
    $response = $this->get('/night')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Night/Show')
            ->where('date', null)
            ->where('night', '2026-10-06')
            ->where('nights', ['2026-10-06', '2026-10-05'])
            ->where('running', false)
            ->where('stopped', false)
            ->where('usage', 6.7));

    $tasks = $response->inertiaProps('tasks');
    expect(array_map(fn (array $group): array => array_column($group, 'task'), $tasks))->toBe([
        'ready' => ['T12'], 'blocked' => [], 'stuck' => ['T3'], 'skipped' => ['T2'], 'done' => [],
    ]);

    $ready = $tasks['ready'][0];
    expect($ready['project_name'])->toBe('Lantern')
        ->and($ready['title'])->toBe('lantern T12: Export to CSV')
        ->and($ready['rounds'])->toBe(2)
        ->and($ready['verdict']['result'])->toBe('PASS')
        ->and($ready['verdict']['items'])->toHaveCount(3)
        ->and($ready['verdict']['look_at'][0])->toContain('src/export/csv.ts')
        ->and($ready['report'])->toContain('<h2>What changed</h2>')
        ->and($ready['branch'])->toBe(['name' => 'night/T12', 'state' => 'no-repo']);
    expect($tasks['stuck'][0])->not->toHaveKey('verdict');
});

it('lists the orders that wait on Kai with their questions and the etag an answer sends', function () {
    $questions = $this->get('/night')->inertiaProps('questions');

    expect($questions)->toHaveCount(1)
        ->and($questions[0]['project'])->toBe('bramble-bakery')
        ->and($questions[0]['task'])->toBe('T6')
        ->and($questions[0]['missing'])->toContain('<strong>Which slots?</strong>')
        ->and($questions[0]['answers'])->toBeNull()
        ->and($questions[0]['etag'])->toBe(hash_file('sha256', $this->hub.'/orders/bramble-bakery/T6.md'));
});

it('shows the triage note, the queue with each line\'s last outcome, and past nights by date', function () {
    $page = $this->get('/night');
    expect($page->inertiaProps('triage'))->toContain('<h2>Picked</h2>')
        ->and(array_map(fn (array $item): string => "{$item['task']} {$item['last']}", $page->inertiaProps('queue')))
        ->toBe(['T12 ready', 'T3 stuck', 'T2 skipped', 'T15 ']);

    $earlier = $this->get('/night/2026-10-05')->assertOk();
    expect($earlier->inertiaProps('night'))->toBe('2026-10-05')
        ->and(array_column($earlier->inertiaProps('tasks.done'), 'outcome'))->toBe(['merged']);
});

it('says whether a run is going and whether tonight is skipped', function () {
    File::ensureDirectoryExists($this->hub.'/.run');
    File::put($this->hub.'/.run/night.lock', '123');
    File::put($this->hub.'/orders/STOP', '');

    $this->get('/night')->assertInertia(fn (Assert $page) => $page->where('running', true)->where('stopped', true));
});

it('returns 404 for a night with no rows or a date that is not a date', function () {
    $this->get('/night/2026-09-01')->assertNotFound();
    $this->get('/night/last')->assertNotFound();
});

it('shows an empty night page when the hub has no night run yet', function () {
    File::deleteDirectory($this->hub.'/orders');

    $this->get('/night')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('night', null)->where('nights', [])->where('questions', [])->where('queue', []));
});

it('shows a needs-kai order\'s earlier answers and escapes raw HTML', function () {
    File::append($this->hub.'/orders/bramble-bakery/T6.md', "\n## KAI'S ANSWERS\n\nMorning only. <script>x</script>\n");
    File::put($this->hub.'/orders/triage-2026-10-06.md', "<script>alert(1)</script>\n");

    $page = $this->get('/night');
    expect($page->inertiaProps('questions.0.answers'))->toBe('Morning only. <script>x</script>')
        ->and($page->inertiaProps('triage'))->toContain('&lt;script&gt;')->not->toContain('<script>');
});
