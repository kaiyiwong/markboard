<?php

use Inertia\Testing\AssertableInertia as Assert;

/*
| One project's page, on a temp copy of demo/ (see TestCase).
*/

beforeEach(function () {
    $this->travelTo('2026-10-07 12:00:00');
});

it('shows the tasks by section in file order, with the etag of the file read with them', function () {
    $response = $this->get('/projects/lantern')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->where('project.id', 'lantern')
            ->where('project.name', 'Lantern')
            ->where('highlight', null)
            ->where('file.etag', hash_file('sha256', $this->hub.'/projects/lantern/TASKS.md'))
            ->where('file.editable', true)
            ->where('file.errors', []));

    $sections = $response->inertiaProps('sections');
    expect(array_column($sections, 'name'))->toBe(['Up next', 'In progress', 'Waiting on', 'Done'])
        ->and(array_map(fn (array $section): array => array_column($section['tasks'], 'task_id'), $sections))
        ->toBe([['T12', 'T15'], ['T11'], ['T9'], ['T10', 'T7']]);
});

it('gives each task its metadata as written, proof and notes', function () {
    $tasks = collect($this->get('/projects/lantern')->inertiaProps('sections'))->flatMap(fn (array $section): array => $section['tasks'])->keyBy('task_id');

    expect($tasks['T12'])
        ->number->toBe(12)
        ->section->toBe('Up next')
        ->checked->toBeFalse()
        ->title->toBe('Export to CSV')
        ->metadata->toBe([['due', '2026-10-20']])
        ->due->toBe('2026-10-20')
        ->overdue->toBeFalse()
        ->proof->toBe('the export opens in a spreadsheet with one row per task')
        ->notes->toBe([])
        ->line_start->toBe(6)
        ->line_end->toBe(8);

    // A title's own parentheses stay in the title.
    expect($tasks['T15'])->title->toBe('Keyboard shortcuts (v2)')->metadata->toBe([]);
    expect($tasks['T11'])->notes->toBe(['waiting on the new icon set for the toggles'])->proof->toBeNull();
    expect($tasks['T10'])->checked->toBeTrue()->from_section->toBe('In progress')
        ->metadata->toBe([['started', '2026-09-22'], ['done', '2026-09-27'], ['evidence', 'a1b2c3d'], ['from', 'In progress']]);
});

it('marks an open task past its due date as overdue', function () {
    $tasks = collect($this->get('/projects/bramble-bakery')->inertiaProps('sections.0.tasks'))->keyBy('task_id');

    expect($tasks['T4']['overdue'])->toBeTrue()
        ->and($tasks['T5']['overdue'])->toBeFalse();
});

it('highlights the task in the task route, found by number', function () {
    $this->get('/projects/lantern/tasks/T12')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Projects/Show')->where('highlight', 'T12'));

    // T09 and T9 are the same ID; the highlight uses the ID as written in the file.
    $this->get('/projects/lantern/tasks/T09')->assertInertia(fn (Assert $page) => $page->where('highlight', 'T9'));
});

it('returns 404 for an unknown project or task', function () {
    $this->get('/projects/nope')->assertNotFound();
    $this->get('/projects/lantern/tasks/T99')->assertNotFound();
    $this->get('/projects/lantern/tasks/12')->assertNotFound();
});

it('shows a file with format errors read-only, with its errors and line numbers', function () {
    $this->get('/projects/muse-lab')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('file.editable', false)
            ->where('file.errors.0', ['line' => 7, 'message' => 'bad date for "due": 2026-13-01'])
            ->has('file.errors', 5)
            ->has('sections.0.tasks', 2)
            ->has('sections.1.tasks', 1));
});

it('has no file for a project with no TASKS.md', function () {
    $this->get('/projects/reading-list')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('file', null)
            ->where('project.folder_found', true)
            ->where('sections.0.tasks', []));
});
