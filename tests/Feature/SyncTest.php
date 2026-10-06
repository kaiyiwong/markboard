<?php

use App\Markdown\Section;
use App\Markdown\Stage;
use App\Models\Conflict;
use App\Models\FileVersion;
use App\Models\PipelineRow;
use App\Models\Project;
use App\Models\SourceFile;
use App\Models\Task;
use App\Sync\HubSync;
use App\Sync\SourceKind;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

/*
| Each test runs on its own temp copy of demo/ ($this->hub, see TestCase): 7 projects, of which
| 6 have a TASKS.md and 1 a pipeline.md, plus projects.md and priorities.md: 9 files.
*/

function syncHub(bool $fresh = false): int
{
    return app(HubSync::class)->run($fresh);
}

/** Replaces text in a hub file, keeping its mtime if asked (to imitate a write within the same second). */
function editHubFile(string $path, string $search, string $replace, ?int $mtime = null): void
{
    $bytes = (string) file_get_contents($path);
    expect($bytes)->toContain($search);
    file_put_contents($path, str_replace($search, $replace, $bytes));
    if ($mtime !== null) {
        touch($path, $mtime);
    }
    clearstatcache();
}

function lanternTasks(): string
{
    return test()->hub.'/projects/lantern/TASKS.md';
}

function task(string $project, int $number): Task
{
    return Task::where('project_id', $project)->where('number', $number)->sole();
}

describe('the first sync', function () {
    it('indexes every file in the hub', function () {
        expect(syncHub())->toBe(9)
            ->and(SourceFile::count())->toBe(9)
            ->and(FileVersion::count())->toBe(9)
            ->and(SourceFile::where('kind', SourceKind::Registry)->sole()->project_id)->toBeNull();
    });

    it('stores projects in priority order with their paths resolved against a demo hub', function () {
        syncHub();

        expect(Project::orderBy('category_rank')->orderBy('registry_order')->pluck('category_rank', 'id')->all())->toBe([
            'fieldnotes' => 0, 'bramble-bakery' => 1, 'job-search' => 2, 'lantern' => 3, 'tidepool' => 3, 'muse-lab' => 3, 'reading-list' => 4,
        ])
            ->and(Project::find('lantern'))
            ->path->toBe($this->hub.'/projects/lantern')
            ->status->toBe('active')
            ->next_milestone->toBe('Public beta')
            ->docs->toBe('docs/spec.md')
            ->folder_found->toBeTrue();
    });

    it('stores each task with its metadata copied into columns', function () {
        syncHub();

        expect(task('lantern', 12))
            ->task_id->toBe('T12')
            ->section->toBe(Section::UpNext)
            ->position->toBe(0)
            ->checked->toBeFalse()
            ->title->toBe('Export to CSV')
            ->due->toDateString()->toBe('2026-10-20')
            ->proof->toBe('the export opens in a spreadsheet with one row per task')
            ->line_start->toBe(6)
            ->line_end->toBe(8);

        expect(task('lantern', 10))
            ->section->toBe(Section::Done)
            ->checked->toBeTrue()
            ->metadata->toBe([['started', '2026-09-22'], ['done', '2026-09-27'], ['evidence', 'a1b2c3d'], ['from', 'In progress']])
            ->done->toDateString()->toBe('2026-09-27')
            ->evidence->toBe('a1b2c3d')
            ->from_section->toBe(Section::InProgress);

        expect(task('lantern', 7))->position->toBe(1)->cancelled->toDateString()->toBe('2026-09-24')->done->toBeNull();
        expect(task('lantern', 9))->waiting->toBe('Ana')->since->toDateString()->toBe('2026-09-28');
        expect(task('lantern', 11))
            ->notes->toBe(['waiting on the new icon set for the toggles'])
            ->notes_text->toBe('waiting on the new icon set for the toggles');

        expect(SourceFile::firstWhere('path', lanternTasks()))
            ->kind->toBe(SourceKind::Tasks)
            ->hash->toBe(hash_file('sha256', lanternTasks()))
            ->editable->toBeTrue()
            ->errors->toBe([]);
    });

    it('stores pipeline rows by position, with an empty date as null', function () {
        syncHub();

        expect(PipelineRow::where('project_id', 'job-search')->orderBy('position')->get()->map(fn (PipelineRow $row): array => [
            $row->position, $row->company, $row->stage, $row->date?->toDateString(),
        ])->all())->toBe([
            [1, 'Northwind', Stage::Interviewing, '2026-10-14'],
            [2, 'Tailspin', Stage::Screening, '2026-10-09'],
            [3, 'Contoso Labs', Stage::Applied, '2026-10-12'],
            [4, 'Fabrikam', Stage::Offer, '2026-10-08'],
            [5, 'Litware', Stage::Rejected, null],
        ]);
    });

    it('marks a file with format errors read-only, and indexes what it can read', function () {
        syncHub();

        expect(SourceFile::firstWhere('path', $this->hub.'/projects/muse-lab/TASKS.md'))
            ->editable->toBeFalse()
            ->errors->toBe([
                ['line' => 7, 'message' => 'bad date for "due": 2026-13-01'],
                ['line' => 10, 'message' => 'In progress task needs started'],
                ['line' => 12, 'message' => 'unknown section "Ideas"'],
                ['line' => 13, 'message' => 'duplicate ID T1 (first on line 6)'],
                ['line' => 13, 'message' => 'In progress task needs started'],
            ]);

        // The repeated T1 indexes only its first task; the bad date stays in the metadata only.
        expect(Task::where('project_id', 'muse-lab')->pluck('title', 'number')->all())
            ->toBe([1 => 'Collect twenty example prompts', 2 => 'Tag prompts by style', 3 => 'Prompt library page'])
            ->and(task('muse-lab', 2))->due->toBeNull()->metadata->toBe([['due', '2026-13-01']]);
    });

    it('indexes nothing for a project with no TASKS.md', function () {
        syncHub();

        expect(Project::find('reading-list')->folder_found)->toBeTrue()
            ->and(SourceFile::where('project_id', 'reading-list')->count())->toBe(0);
    });

    it('runs before every page request', function () {
        $this->get('/')->assertOk();

        expect(Project::count())->toBe(7);
    });
});

describe('later syncs', function () {
    it('skips unchanged files without reading them', function () {
        syncHub();
        $ids = Task::orderBy('id')->pluck('id')->all();

        // The first check after a sync made within 2 seconds of the mtime still hashes; the hash matches.
        $this->travel(10)->seconds();
        expect(syncHub())->toBe(0);
        $checkedAt = SourceFile::firstWhere('path', lanternTasks())->synced_at;
        expect($checkedAt->getTimestamp())->toBe(now()->getTimestamp());

        // From then on mtime and size are enough: the file isn't hashed, so synced_at stays.
        $this->travel(10)->seconds();
        expect(syncHub())->toBe(0)
            ->and(SourceFile::firstWhere('path', lanternTasks())->synced_at->getTimestamp())->toBe($checkedAt->getTimestamp())
            ->and(Task::orderBy('id')->pluck('id')->all())->toBe($ids);
    });

    it('hashes a touched file and only updates its mtime when the bytes are the same', function () {
        syncHub();
        $ids = Task::where('project_id', 'lantern')->pluck('id')->all();

        touch(lanternTasks(), $mtime = filemtime(lanternTasks()) - 100);
        clearstatcache();

        expect(syncHub())->toBe(0)
            ->and(SourceFile::firstWhere('path', lanternTasks())->mtime)->toBe($mtime)
            ->and(Task::where('project_id', 'lantern')->pluck('id')->all())->toBe($ids);
    });

    it('re-syncs a changed file by its hash, and only that file', function () {
        syncHub();
        editHubFile(lanternTasks(), 'Export to CSV', 'Export to a spreadsheet');

        expect(syncHub())->toBe(1)
            ->and(task('lantern', 12)->title)->toBe('Export to a spreadsheet')
            ->and(SourceFile::firstWhere('path', lanternTasks())->hash)->toBe(hash_file('sha256', lanternTasks()))
            ->and(FileVersion::where('path', lanternTasks())->count())->toBe(2);
    });

    it('hashes a file whose mtime is within 2 seconds of the last sync, catching a same-size write in the same second', function () {
        $mtime = time() - 100;
        touch(lanternTasks(), $mtime);
        $this->travelTo(Carbon::createFromTimestamp($mtime + 2));
        syncHub();

        editHubFile(lanternTasks(), 'Keyboard shortcuts', 'Keyboard shortkeys', $mtime);

        expect(syncHub())->toBe(1)
            ->and(task('lantern', 15)->title)->toBe('Keyboard shortkeys (v2)');
    });

    it('skips a file whose mtime is more than 2 seconds older than the last sync', function () {
        $mtime = time() - 100;
        touch(lanternTasks(), $mtime);
        $this->travelTo(Carbon::createFromTimestamp($mtime + 3));
        syncHub();

        // Only a write in the same second as the mtime could look like this, and that second is long past.
        editHubFile(lanternTasks(), 'Keyboard shortcuts', 'Keyboard shortkeys', $mtime);

        expect(syncHub())->toBe(0)
            ->and(task('lantern', 15)->title)->toBe('Keyboard shortcuts (v2)');
    });

    it('skips a file whose lock is held, since the holder re-syncs it', function () {
        syncHub();
        editHubFile(lanternTasks(), 'Export to CSV', 'Export to a spreadsheet');
        $lock = app(HubSync::class)->lock(lanternTasks());
        $lock->get();

        expect(syncHub())->toBe(0)
            ->and(task('lantern', 12)->title)->toBe('Export to CSV');

        $lock->release();

        expect(syncHub())->toBe(1)
            ->and(task('lantern', 12)->title)->toBe('Export to a spreadsheet');
    });

    it('keeps the old rows and hash together when a sync fails, records why, and tries again', function () {
        Exceptions::fake();
        syncHub();
        $pipeline = $this->hub.'/projects/job-search/pipeline.md';
        $oldHash = hash_file('sha256', $pipeline);
        editHubFile($pipeline, '| interviewing |', '| offer |');

        $failing = true;
        DB::listen(function (QueryExecuted $query) use (&$failing): void {
            if ($failing && str_starts_with($query->sql, 'insert into') && str_contains($query->sql, 'pipeline_rows')) {
                throw new RuntimeException('disk full');
            }
        });

        expect(syncHub())->toBe(0)
            ->and(SourceFile::firstWhere('path', $pipeline))->hash->toBe($oldHash)->sync_error->toBe('disk full')
            ->and(PipelineRow::where('position', 1)->sole()->stage)->toBe(Stage::Interviewing);
        Exceptions::assertReported(RuntimeException::class);

        $failing = false;

        expect(syncHub())->toBe(1)
            ->and(SourceFile::firstWhere('path', $pipeline))->sync_error->toBeNull()
            ->and(PipelineRow::where('position', 1)->sole()->stage)->toBe(Stage::Offer);
    });

    it('removes the rows of a file that is gone', function () {
        syncHub();
        unlink(lanternTasks());

        expect(syncHub())->toBe(1)
            ->and(Task::where('project_id', 'lantern')->count())->toBe(0)
            ->and(SourceFile::where('project_id', 'lantern')->count())->toBe(0);
    });

    it('shows a project whose folder is missing with no tasks, until the folder is back', function () {
        syncHub();
        rename($this->hub.'/projects/tidepool', $this->hub.'/tidepool-moved');

        syncHub();
        expect(Project::find('tidepool')->folder_found)->toBeFalse()
            ->and(Task::where('project_id', 'tidepool')->count())->toBe(0);

        rename($this->hub.'/tidepool-moved', $this->hub.'/projects/tidepool');

        syncHub();
        expect(Project::find('tidepool')->folder_found)->toBeTrue()
            ->and(Task::where('project_id', 'tidepool')->count())->toBe(4);
    });

    it('deletes write temp files older than a minute in the folders it reads', function () {
        $old = $this->hub.'/projects/lantern/.TASKS.md.markboard-a1b2.tmp';
        $recent = $this->hub.'/projects/lantern/.TASKS.md.markboard-c3d4.tmp';
        file_put_contents($old, 'left by a crash');
        file_put_contents($recent, 'a write in progress');
        touch($old, now()->getTimestamp() - 61);

        syncHub();

        expect(file_exists($old))->toBeFalse()
            ->and(file_exists($recent))->toBeTrue();
    });
});

describe('the registry and priorities', function () {
    it('lists malformed and duplicate rows, and drops the projects no longer registered', function () {
        syncHub();
        file_put_contents($this->hub.'/projects.md', <<<'MD'
            # Projects

            | id | name | path | category | status | next milestone | docs |
            |---|---|---|---|---|---|---|
            | lantern | Lantern | projects/lantern | product | active | Public beta | |
            | Tide_Pool | Tidepool | projects/tidepool | game | paused | | |
            | fieldnotes | Fieldnotes | projects/fieldnotes | blog | active | | |
            | muse-lab | Muse Lab | projects/muse-lab | gen-ai | archived | | |
            | job-search | Job search |  | job | active | | |
            | bramble-bakery | Bramble Bakery | projects/bramble-bakery | client |
            | lantern | Lantern again | projects/fieldnotes | product | active | | |
            | lantern-copy | Lantern copy | projects/lantern/ | product | active | | |

            MD);

        expect(syncHub())->toBeGreaterThan(0)
            ->and(Project::pluck('id')->all())->toBe(['lantern'])
            ->and(Task::distinct()->pluck('project_id')->all())->toBe(['lantern'])
            ->and(SourceFile::where('kind', SourceKind::Registry)->sole()->errors)->toBe([
                ['line' => 6, 'message' => 'id "Tide_Pool" is not kebab-case'],
                ['line' => 7, 'message' => 'category "blog" is not one of own-site, client, job, product, game, gen-ai, personal'],
                ['line' => 8, 'message' => 'status "archived" is not one of active, paused, done'],
                ['line' => 9, 'message' => 'path is empty'],
                ['line' => 10, 'message' => 'row has 4 cells, the header has 7'],
                ['line' => 11, 'message' => 'duplicate id lantern (first on line 5)'],
                ['line' => 12, 'message' => "duplicate path {$this->hub}/projects/lantern (first on line 5)"],
            ]);
    });

    it('refuses relative paths in a hub that is not demo-kind', function () {
        unlink($this->hub.'/.markboard-demo');

        syncHub();

        expect(Project::count())->toBe(0)
            ->and(SourceFile::where('kind', SourceKind::Registry)->sole()->errors)->toHaveCount(7)
            ->and(SourceFile::where('kind', SourceKind::Registry)->sole()->errors[0])
            ->toBe(['line' => 7, 'message' => 'path "projects/fieldnotes" is relative: only a demo hub may use relative paths']);
    });

    it('re-ranks the projects when priorities.md changes, without re-reading their files', function () {
        syncHub();
        file_put_contents($this->hub.'/priorities.md', "Order: gen-ai = game, product\n");

        expect(syncHub())->toBe(1)
            ->and(Project::orderBy('id')->pluck('category_rank', 'id')->all())->toBe([
                'bramble-bakery' => 2, 'fieldnotes' => 2, 'job-search' => 2, 'lantern' => 1, 'muse-lab' => 0, 'reading-list' => 2, 'tidepool' => 0,
            ]);
    });

    it('ranks every category equal when priorities.md is gone', function () {
        syncHub();
        unlink($this->hub.'/priorities.md');

        expect(syncHub())->toBe(1)
            ->and(Project::distinct()->pluck('category_rank')->all())->toBe([0]);
    });

    it('reads a moved project from its new folder', function () {
        syncHub();
        rename($this->hub.'/projects/lantern', $this->hub.'/projects/lantern-app');
        editHubFile($this->hub.'/projects.md', 'projects/lantern ', 'projects/lantern-app ');

        syncHub();

        expect(Project::find('lantern')->path)->toBe($this->hub.'/projects/lantern-app')
            ->and(SourceFile::where('project_id', 'lantern')->pluck('path')->all())->toBe([$this->hub.'/projects/lantern-app/TASKS.md'])
            ->and(Task::where('project_id', 'lantern')->count())->toBe(6);
    });
});

describe('file versions', function () {
    it('keeps the 10 most recently seen versions of a file, plus any an open conflict refers to', function () {
        syncHub();
        $first = hash_file('sha256', lanternTasks());
        Conflict::create([
            'path' => lanternTasks(), 'path_hash' => hash('sha256', lanternTasks()), 'operation' => 'tick',
            'parameters' => ['task' => 'T12'], 'base_hash' => $first, 'disk_hash' => $first, 'status' => 'open',
        ]);

        foreach (range(1, 12) as $version) {
            $this->travel(1)->seconds();
            editHubFile(lanternTasks(), 'Export to CSV', "Export to CSV {$version}", now()->getTimestamp());
            syncHub();
            editHubFile(lanternTasks(), "Export to CSV {$version}", 'Export to CSV');
        }

        $hashes = FileVersion::where('path', lanternTasks())->pluck('hash');
        expect($hashes)->toHaveCount(11)->toContain($first);
    });

    it('refreshes last_seen_at when a version comes back, instead of storing it twice', function () {
        syncHub();
        editHubFile(lanternTasks(), 'Export to CSV', 'Export to a spreadsheet');
        $this->travel(5)->seconds();
        syncHub();
        editHubFile(lanternTasks(), 'Export to a spreadsheet', 'Export to CSV');
        $this->travel(5)->seconds();
        syncHub();

        expect(FileVersion::where('path', lanternTasks())->count())->toBe(2)
            ->and(FileVersion::where('hash', hash_file('sha256', lanternTasks()))->sole()->last_seen_at->getTimestamp())
            ->toBe(now()->getTimestamp());
    });
});

describe('markboard:sync', function () {
    it('rebuilds the index with --fresh, keeping file_versions and conflicts', function () {
        syncHub();
        $conflict = Conflict::create([
            'path' => lanternTasks(), 'path_hash' => hash('sha256', lanternTasks()), 'operation' => 'tick',
            'parameters' => ['task' => 'T12'], 'base_hash' => str_repeat('a', 64), 'disk_hash' => str_repeat('b', 64), 'status' => 'open',
        ]);
        $versions = FileVersion::pluck('id')->all();

        // An index that drifted from the files: a plain sync sees unchanged files and leaves it.
        Task::query()->update(['title' => 'drifted']);
        Project::find('tidepool')->delete();
        $this->artisan('markboard:sync')->expectsOutput('Re-indexed 0 files.')->assertSuccessful();
        expect(Task::where('title', 'drifted')->count())->toBe(Task::count());

        $this->artisan('markboard:sync --fresh')->expectsOutput('Re-indexed 9 files.')->assertSuccessful();

        expect(Task::where('title', 'drifted')->count())->toBe(0)
            ->and(task('lantern', 12)->title)->toBe('Export to CSV')
            ->and(Project::find('tidepool'))->not->toBeNull()
            ->and(FileVersion::pluck('id')->all())->toBe($versions)
            ->and($conflict->fresh())->not->toBeNull();
    });

    it('fails when the hub has no projects.md', function () {
        config(['markboard.hub_path' => $this->hub.'/projects']);

        $this->artisan('markboard:sync')
            ->expectsOutput("Hub not found: no projects.md in {$this->hub}/projects")
            ->assertFailed();
    });

    it('is scheduled every minute', function () {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'markboard:sync'));

        expect($events)->toHaveCount(1)
            ->and($events->first()->expression)->toBe('* * * * *');
    });
});
