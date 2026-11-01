<?php

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/*
| The Tasks page, on a temp copy of demo/ (see TestCase). Tidepool is paused, so its tasks stay off
| the list; muse-lab's TASKS.md has format errors, so its tasks show without an etag.
*/

/**
 * The page's tasks as "<project> <task>", per group.
 *
 * @return array<string, list<string>>
 */
function taskGroups($test): array
{
    $tasks = $test->get('/tasks')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tasks/Index'))
        ->inertiaProps('tasks');

    return collect($tasks)
        ->groupBy('group')
        ->map(fn ($items) => $items->map(fn (array $item): string => $item['project_id'].' '.$item['task']['task_id'])->all())
        ->all();
}

it('groups the open tasks of active projects, waiting on Kai first', function () {
    expect(array_keys(taskGroups($this)))->toBe(['waiting-you', 'in-progress', 'up-next', 'waiting-others']);
});

it('orders waiting tasks by since, oldest first, with ties in priority order', function () {
    $groups = taskGroups($this);

    expect($groups['waiting-you'])->toBe(['fieldnotes T5', 'bramble-bakery T7', 'fieldnotes T4', 'job-search T5'])
        ->and($groups['waiting-others'])->toBe(['lantern T9', 'bramble-bakery T2', 'job-search T2']);
});

it('orders in-progress tasks by started, oldest first, with undated ones last', function () {
    expect(taskGroups($this)['in-progress'])
        ->toBe(['bramble-bakery T3', 'lantern T11', 'fieldnotes T1', 'job-search T3', 'muse-lab T3']);
});

it('keeps up next in priority order, then file order', function () {
    expect(taskGroups($this)['up-next'])->toBe([
        'fieldnotes T2', 'fieldnotes T3',
        'bramble-bakery T4', 'bramble-bakery T5', 'bramble-bakery T6',
        'job-search T4',
        'lantern T12', 'lantern T15',
        'muse-lab T1', 'muse-lab T2',
    ]);
});

it('leaves paused projects and done tasks out', function () {
    $keys = collect(taskGroups($this))->flatten();

    expect($keys->filter(fn (string $key): bool => str_starts_with($key, 'tidepool ')))->toBeEmpty()
        ->and($keys)->not->toContain('lantern T10');
});

it('sends each task its age date and each project its etag, null for a file with errors', function () {
    $response = $this->get('/tasks')->assertOk();
    $tasks = collect($response->inertiaProps('tasks'))->keyBy(fn (array $item): string => $item['project_id'].' '.$item['task']['task_id']);
    $projects = collect($response->inertiaProps('projects'))->keyBy('id');

    expect($tasks['fieldnotes T5']['age_from'])->toBe('2026-09-29')
        ->and($tasks['lantern T11']['age_from'])->toBe('2026-10-01')
        ->and($tasks['lantern T12']['age_from'])->toBeNull()
        ->and($tasks['lantern T12']['task']['due'])->toBe('2026-10-20')
        ->and($projects->keys()->all())->toBe(['fieldnotes', 'bramble-bakery', 'job-search', 'lantern', 'muse-lab', 'reading-list'])
        ->and($projects['fieldnotes'])->toBe(['id' => 'fieldnotes', 'name' => 'Fieldnotes', 'etag' => hash_file('sha256', $this->hub.'/projects/fieldnotes/TASKS.md')])
        ->and($projects['muse-lab']['etag'])->toBeNull()
        ->and($projects['reading-list']['etag'])->toBeNull();
});

it('treats any of Kai\'s names, in any case, as waiting on you', function () {
    $file = $this->hub.'/projects/lantern/TASKS.md';
    File::put($file, str_replace('(waiting Ana, since 2026-09-28)', '(waiting KAIYI WONG, since 2026-09-28)', File::get($file)));

    expect(taskGroups($this)['waiting-you'])->toContain('lantern T9');
});
