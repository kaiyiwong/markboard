<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/*
| The Projects page, on a temp copy of demo/ (see TestCase). "Today" is fixed at 2026-10-07, the
| demo brief's date: Bramble Bakery's T4 (due 2026-10-02) is overdue and Job search's T3 (due
| 2026-10-14) is due within 7 days.
*/

beforeEach(function () {
    $this->travelTo('2026-10-07 12:00:00');
});

/** @return list<array<string, mixed>> */
function projectsOnPage(string $url = '/'): array
{
    return test()->get($url)->assertOk()->inertiaProps('projects');
}

it('lists active projects in priority order, then paused ones', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->where('hub.found', true)
            ->where('registryErrors', [])
            ->where('results', null));

    expect(array_column(projectsOnPage(), 'status', 'id'))->toBe([
        'fieldnotes' => 'active',
        'bramble-bakery' => 'active',
        'job-search' => 'active',
        'lantern' => 'active',
        'muse-lab' => 'active',
        'reading-list' => 'active',
        'tidepool' => 'paused',
    ]);
});

it('gives each project its open-section counts, due badge and TASKS.md state', function () {
    $projects = collect(projectsOnPage())->keyBy('id');

    expect($projects['bramble-bakery'])
        ->name->toBe('Bramble Bakery')
        ->category->toBe('client')
        ->next_milestone->toBe('Online orders live')
        ->counts->toBe(['Up next' => 3, 'In progress' => 1, 'Waiting on' => 1])
        ->due->toBe('overdue')
        ->tasks_file->toBe('ok')
        ->error_count->toBe(0);

    expect($projects['job-search'])->due->toBe('soon');
    expect($projects['lantern'])->due->toBeNull()->counts->toBe(['Up next' => 2, 'In progress' => 1, 'Waiting on' => 1]);

    // Not migrated: one with format errors (read on a best-effort basis), one with no TASKS.md.
    expect($projects['muse-lab'])->tasks_file->toBe('errors')->error_count->toBe(5)
        ->counts->toBe(['Up next' => 2, 'In progress' => 1, 'Waiting on' => 0]);
    expect($projects['reading-list'])->tasks_file->toBe('missing')->folder_found->toBeTrue()
        ->counts->toBe(['Up next' => 0, 'In progress' => 0, 'Waiting on' => 0]);
});

it('ignores Done when choosing the due badge', function () {
    $this->travelTo('2026-10-21 12:00:00');

    // Lantern's T12 is due 2026-10-20 and open; done and cancelled tasks never make a project overdue.
    expect(collect(projectsOnPage())->keyBy('id')['lantern']['due'])->toBe('overdue')
        ->and(collect(projectsOnPage())->keyBy('id')['tidepool']['due'])->toBeNull();
});

it('takes "today" from MARKBOARD_TIMEZONE', function () {
    // 2026-10-20 at noon in UTC is already 2026-10-21 at UTC+14, so Lantern's T12 (due 2026-10-20) is overdue there.
    $this->travelTo('2026-10-20 12:00:00');
    expect(collect(projectsOnPage())->keyBy('id')['lantern']['due'])->toBe('soon');

    config(['markboard.timezone' => 'Pacific/Kiritimati']);
    expect(collect(projectsOnPage())->keyBy('id')['lantern']['due'])->toBe('overdue');
});

it('shows a project whose folder is missing as not found', function () {
    File::deleteDirectory($this->hub.'/projects/fieldnotes');

    expect(collect(projectsOnPage())->keyBy('id')['fieldnotes'])
        ->folder_found->toBeFalse()
        ->tasks_file->toBe('missing');
});

it('lists malformed and duplicate registry rows', function () {
    File::append($this->hub.'/projects.md', "| Bad_Id | Bad | projects/bad | client | active | | |\n| lantern | Lantern again | projects/lantern | product | active | | |\n");

    expect($this->get('/')->inertiaProps('registryErrors'))->toBe([
        ['line' => 14, 'message' => 'id "Bad_Id" is not kebab-case'],
        ['line' => 15, 'message' => 'duplicate id lantern (first on line 10)'],
    ]);
});

it('filters by category and status, ignoring values it does not know', function () {
    expect(array_column(projectsOnPage('/?category=client'), 'id'))->toBe(['bramble-bakery'])
        ->and(array_column(projectsOnPage('/?status=paused'), 'id'))->toBe(['tidepool'])
        ->and(array_column(projectsOnPage('/?category=game&status=active'), 'id'))->toBe([])
        ->and(projectsOnPage('/?category=nonsense'))->toHaveCount(7);

    $this->get('/?category=client&status=bogus')
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['category' => 'client', 'status' => null, 'q' => '']));
});

it('searches task titles, proofs and notes across projects', function () {
    // InnoDB adds rows to a FULLTEXT index only when their transaction commits, so the synced rows are
    // committed, ending the test's transaction; RefreshDatabase then rebuilds the schema for the next test.
    $this->get('/')->assertOk();
    DB::commit();

    // "spreadsheet" is in Lantern T12's proof; "icon" in T11's note; "kickoff" in Bramble T1's title.
    expect($this->get('/?q=spreadsheet')->inertiaProps('results'))->toBe([
        ['project_id' => 'lantern', 'project_name' => 'Lantern', 'task_id' => 'T12', 'title' => 'Export to CSV', 'section' => 'Up next'],
    ]);
    expect(array_column($this->get('/?q=toggles')->inertiaProps('results'), 'task_id'))->toBe(['T11'])
        ->and(array_column($this->get('/?q=Kickoff')->inertiaProps('results'), 'project_id'))->toBe(['bramble-bakery']);

    // Every word must match, and operator characters are only separators.
    expect($this->get('/?q=spreadsheet+checkout')->inertiaProps('results'))->toBe([])
        ->and(array_column($this->get('/?q=%2Bspreadsheet*+"row"')->inertiaProps('results'), 'task_id'))->toBe(['T12']);
});
