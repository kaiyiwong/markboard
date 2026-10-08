<?php

use App\Actions\WriteFile;
use App\Models\Conflict;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\Support\Checker;

/*
| The task endpoints of /api/v1 on a temp copy of demo/ (see TestCase), one test per status code
| in docs/spec.md's API section. Each write is checked byte for byte: the file must equal the
| original with only the edited task's lines changed, and pass check-tasks.py.
*/

beforeEach(function () {
    $this->travelTo('2026-10-07 12:00:00');
    $this->tasks = $this->hub.'/projects/lantern/TASKS.md';
    $this->original = (string) file_get_contents($this->tasks);
});

/** Lantern's TASKS.md with each search replaced, in order; each must be found exactly once. */
function lanternWith(string ...$replacements): string
{
    $bytes = test()->original;
    foreach (array_chunk($replacements, 2) as [$search, $replace]) {
        expect(substr_count($bytes, $search))->toBe(1);
        $bytes = str_replace($search, $replace, $bytes);
    }

    return $bytes;
}

function expectWritten(string $path, string $expected): void
{
    expect(file_get_contents($path))->toBe($expected)
        ->and(Checker::errors([$expected]))->toBe([[]]);
}

describe('200: written', function () {
    it('adds a task at the bottom of Up next with the next ID, and returns it with the new ETag', function () {
        $response = $this->withHeaders(ifMatch($this->tasks))
            ->postJson('/api/v1/projects/lantern/tasks', ['title' => 'Release notes', 'due' => '2026-11-01', 'proof' => 'the notes are on the site'])
            ->assertOk()
            ->assertJsonPath('data.task_id', 'T16')
            ->assertJsonPath('data.section', 'Up next')
            ->assertJsonPath('data.due', '2026-11-01')
            ->assertJsonPath('data.proof', 'the notes are on the site');

        expectWritten($this->tasks, lanternWith(
            "- [ ] T15 Keyboard shortcuts (v2)\n",
            "- [ ] T15 Keyboard shortcuts (v2)\n- [ ] T16 Release notes (due 2026-11-01)\n  proof: the notes are on the site\n",
        ));
        expect($response->headers->get('ETag'))->toBe('"'.hash_file('sha256', $this->tasks).'"');
    });

    it('edits only the fields given, and removes the due date with null', function () {
        $this->withHeaders(ifMatch($this->tasks))
            ->patchJson('/api/v1/projects/lantern/tasks/T12', ['title' => 'Export to CSV and TSV', 'due' => null, 'notes' => ['both formats']])
            ->assertOk()
            ->assertJsonPath('data.title', 'Export to CSV and TSV')
            ->assertJsonPath('data.due', null)
            ->assertJsonPath('data.notes', ['both formats']);

        expectWritten($this->tasks, lanternWith(
            "- [ ] T12 Export to CSV (due 2026-10-20)\n\n  proof: the export opens in a spreadsheet with one row per task\n",
            "- [ ] T12 Export to CSV and TSV\n\n  proof: the export opens in a spreadsheet with one row per task\n  note: both formats\n",
        ));
    });

    it('replaces the proof, and removes it with null', function () {
        $this->withHeaders(ifMatch($this->tasks))->patchJson('/api/v1/projects/lantern/tasks/T12', ['proof' => 'it opens in Numbers'])->assertOk();
        expectWritten($this->tasks, lanternWith('  proof: the export opens in a spreadsheet with one row per task', '  proof: it opens in Numbers'));

        $this->withHeaders(ifMatch($this->tasks))->patchJson('/api/v1/projects/lantern/tasks/T12', ['proof' => null])->assertOk()->assertJsonPath('data.proof', null);
        expectWritten($this->tasks, lanternWith("\n  proof: the export opens in a spreadsheet with one row per task\n", "\n"));
    });

    it('moves a task to the top of In progress with started today', function () {
        $this->withHeaders(ifMatch($this->tasks))
            ->postJson('/api/v1/projects/lantern/tasks/T15/move', ['section' => 'In progress'])
            ->assertOk()
            ->assertJsonPath('data.section', 'In progress')
            ->assertJsonPath('data.position', 0);

        expectWritten($this->tasks, lanternWith(
            "- [ ] T15 Keyboard shortcuts (v2)\n", '',
            "## In progress\n", "## In progress\n- [ ] T15 Keyboard shortcuts (v2) (started 2026-10-07)\n",
        ));
    });

    it('moves a task into Waiting on with who it waits on and since today', function () {
        $this->withHeaders(ifMatch($this->tasks))
            ->postJson('/api/v1/projects/lantern/tasks/T15/move', ['section' => 'Waiting on', 'waiting' => 'Ana'])
            ->assertOk();

        expectWritten($this->tasks, lanternWith(
            "- [ ] T15 Keyboard shortcuts (v2)\n", '',
            "## Waiting on\n", "## Waiting on\n- [ ] T15 Keyboard shortcuts (v2) (waiting Ana, since 2026-10-07)\n",
        ));
    });

    it('reorders a task within its section', function () {
        $this->withHeaders(ifMatch($this->tasks))
            ->putJson('/api/v1/projects/lantern/tasks/T15/position', ['position' => 0])
            ->assertOk()
            ->assertJsonPath('data.position', 0);

        expectWritten($this->tasks, lanternWith(
            "## Up next\n", "## Up next\n- [ ] T15 Keyboard shortcuts (v2)\n",
            "- [ ] T15 Keyboard shortcuts (v2)\n\n## In progress", "\n## In progress",
        ));
    });

    it('ticks a task into the top of Done, found by number whatever its padding', function () {
        $this->withHeaders(ifMatch($this->tasks))
            ->postJson('/api/v1/projects/lantern/tasks/T09/tick', ['evidence' => 'copy-v2.pdf'])
            ->assertOk()
            ->assertJsonPath('data.task_id', 'T9')
            ->assertJsonPath('data.checked', true);

        expectWritten($this->tasks, lanternWith(
            "- [ ] T9 Copy review (waiting Ana, since 2026-09-28)\n", '',
            "## Done\n", "## Done\n- [x] T9 Copy review (waiting Ana, since 2026-09-28, done 2026-10-07, evidence copy-v2.pdf, from Waiting on)\n",
        ));
    });

    it('cancels a task, moving its proof with it', function () {
        $this->withHeaders(ifMatch($this->tasks))->postJson('/api/v1/projects/lantern/tasks/T12/cancel')->assertOk();

        expectWritten($this->tasks, lanternWith(
            "- [ ] T12 Export to CSV (due 2026-10-20)\n\n  proof: the export opens in a spreadsheet with one row per task\n", '',
            "## Done\n", "## Done\n- [x] T12 Export to CSV (due 2026-10-20, cancelled 2026-10-07, from Up next)\n\n  proof: the export opens in a spreadsheet with one row per task\n",
        ));
    });

    it('undoes a task back to its from section', function () {
        $this->withHeaders(ifMatch($this->tasks))
            ->postJson('/api/v1/projects/lantern/tasks/T10/undo')
            ->assertOk()
            ->assertJsonPath('data.section', 'In progress');

        expectWritten($this->tasks, lanternWith(
            "- [x] T10 Sign-in flow (started 2026-09-22, done 2026-09-27, evidence a1b2c3d, from In progress)\n", '',
            "## In progress\n", "## In progress\n- [ ] T10 Sign-in flow (started 2026-09-22)\n",
        ));
    });

    it('accepts a weak or unquoted If-Match', function (string $format) {
        $this->withHeader('If-Match', sprintf($format, hash_file('sha256', $this->tasks)))
            ->postJson('/api/v1/projects/lantern/tasks/T12/cancel')
            ->assertOk();
    })->with(['W/"%s"', '%s']);

    it('dates writes in MARKBOARD_TIMEZONE', function () {
        config(['markboard.timezone' => 'Pacific/Kiritimati']); // UTC+14: already 2026-10-08

        $this->withHeaders(ifMatch($this->tasks))->postJson('/api/v1/projects/lantern/tasks/T15/move', ['section' => 'In progress'])->assertOk();

        expect(file_get_contents($this->tasks))->toContain('(started 2026-10-08)');
    });
});

describe('404: unknown project, task or route', function () {
    it('refuses an unknown project or task number', function () {
        $this->withHeaders(ifMatch($this->tasks))->postJson('/api/v1/projects/nope/tasks/T12/tick')->assertNotFound();
        $this->withHeaders(ifMatch($this->tasks))->postJson('/api/v1/projects/lantern/tasks/T99/tick')
            ->assertNotFound()
            ->assertJsonPath('message', 'No task T99 in the file.');
        $this->withHeaders(ifMatch($this->tasks))->postJson('/api/v1/projects/lantern/tasks/12/tick')->assertNotFound();

        expect(file_get_contents($this->tasks))->toBe($this->original);
    });
});

describe('409: no file to write', function () {
    it('refuses a project with no TASKS.md', function () {
        $this->withHeader('If-Match', '"x"')
            ->postJson('/api/v1/projects/reading-list/tasks', ['title' => 'Read more'])
            ->assertConflict()
            ->assertJsonPath('message', "TASKS.md doesn't exist.");

        expect(file_exists($this->hub.'/projects/reading-list/TASKS.md'))->toBeFalse();
    });

    it('refuses a TASKS.md that is not a regular file', function () {
        $folder = $this->hub.'/projects/reading-list/TASKS.md';
        mkdir($folder);

        $this->withHeader('If-Match', '"x"')
            ->postJson('/api/v1/projects/reading-list/tasks', ['title' => 'Read more'])
            ->assertConflict()
            ->assertJsonPath('message', "TASKS.md isn't a regular file.");
    });

    it('refuses a file with format errors, which is read-only', function () {
        $path = $this->hub.'/projects/muse-lab/TASKS.md';
        $before = file_get_contents($path);

        $this->withHeaders(ifMatch($path))->postJson('/api/v1/projects/muse-lab/tasks/T1/tick')->assertConflict();

        expect(file_get_contents($path))->toBe($before);
    });

    it('refuses a write whose file is deleted while the request works on it', function () {
        $this->app->resolving(WriteFile::class, function (WriteFile $write): void {
            $write->beforeReplace = fn (string $path) => unlink($path);
        });

        $this->withHeaders(ifMatch($this->tasks))->postJson('/api/v1/projects/lantern/tasks/T12/tick')->assertConflict();

        expect(file_exists($this->tasks))->toBeFalse()
            ->and(glob($this->hub.'/projects/lantern/.*.tmp'))->toBe([]);
    });

    it('refuses every write when the hub is gone', function () {
        $this->getJson('/')->assertOk(); // the index now has Lantern
        unlink($this->hub.'/projects.md');

        $this->withHeaders(ifMatch($this->tasks))->postJson('/api/v1/projects/lantern/tasks/T12/tick')->assertConflict();
        $this->postJson('/api/v1/conflicts/1/discard')->assertConflict();

        expect(file_get_contents($this->tasks))->toBe($this->original);
    });
});

describe('412: the file changed since the user saw it', function () {
    it('writes nothing, re-syncs the file and records a conflict', function () {
        $this->get('/projects/lantern'); // the page the user saw: sync stores that version
        $seen = ifMatch($this->tasks);
        $changed = str_replace('T15 Keyboard shortcuts (v2)', 'T15 Keyboard shortcuts (v3)', $this->original);
        file_put_contents($this->tasks, $changed);

        $this->withHeaders($seen)
            ->postJson('/api/v1/projects/lantern/tasks/T12/tick')
            ->assertStatus(412)
            ->assertJsonPath('conflict.summary', 'Tick T12')
            ->assertJsonPath('conflict.etag', hash('sha256', $changed))
            ->assertJsonPath('conflict.applicable', true)
            ->assertJsonPath('conflict.current.task_id', 'T12')
            ->assertJsonPath('conflict.diff', [
                ['op' => 'same', 'old' => 7, 'new' => 7, 'text' => ''],
                ['op' => 'same', 'old' => 8, 'new' => 8, 'text' => '  proof: the export opens in a spreadsheet with one row per task'],
                ['op' => 'removed', 'old' => 9, 'new' => null, 'text' => '- [ ] T15 Keyboard shortcuts (v2)'],
                ['op' => 'added', 'old' => null, 'new' => 9, 'text' => '- [ ] T15 Keyboard shortcuts (v3)'],
                ['op' => 'same', 'old' => 10, 'new' => 10, 'text' => ''],
                ['op' => 'same', 'old' => 11, 'new' => 11, 'text' => '## In progress'],
            ]);

        expect(file_get_contents($this->tasks))->toBe($changed)
            ->and(Conflict::sole())
            ->path->toBe($this->tasks)
            ->operation->value->toBe('tick')
            ->parameters->toEqual(['number' => 12, 'evidence' => null])
            ->base_hash->toBe(hash('sha256', $this->original))
            ->disk_hash->toBe(hash('sha256', $changed))
            ->status->toBe('open');
        $this->get('/projects/lantern')->assertInertia(fn ($page) => $page
            ->where('file.etag', hash('sha256', $changed))
            ->where('sections.0.tasks.1.title', 'Keyboard shortcuts (v3)'));
    });

    it('has no current task for an Add, and two stale submits make two conflicts', function () {
        $this->get('/projects/lantern');
        $seen = ifMatch($this->tasks);
        file_put_contents($this->tasks, $this->original."\n");

        foreach ([1, 2] as $_) {
            $this->withHeaders($seen)
                ->postJson('/api/v1/projects/lantern/tasks', ['title' => 'Release notes'])
                ->assertStatus(412)
                ->assertJsonPath('conflict.current', null)
                ->assertJsonPath('conflict.applicable', true);
        }

        expect(Conflict::count())->toBe(2);
    });

    it('catches a change made while the request was writing, through the step 7 re-hash', function () {
        $seen = ifMatch($this->tasks);
        $changed = $this->original.'Added by an agent mid-write.'.PHP_EOL;
        $this->app->resolving(WriteFile::class, function (WriteFile $write) use ($changed): void {
            $write->beforeReplace = fn (string $path) => file_put_contents($path, $changed);
        });

        $this->withHeaders($seen)->postJson('/api/v1/projects/lantern/tasks/T12/tick')->assertStatus(412);

        expect(file_get_contents($this->tasks))->toBe($changed)
            ->and(Conflict::sole())
            ->base_hash->toBe(hash('sha256', $this->original))
            ->disk_hash->toBe(hash('sha256', $changed))
            ->and(glob($this->hub.'/projects/lantern/.*.tmp'))->toBe([]);
    });
});

describe('422: validation and state checks', function () {
    it('refuses bad input with Form Request errors, and writes nothing', function (array $body, string $field) {
        $this->withHeaders(ifMatch($this->tasks))
            ->postJson('/api/v1/projects/lantern/tasks', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(file_get_contents($this->tasks))->toBe($this->original);
    })->with([
        'no title' => [[], 'title'],
        'an empty title' => [['title' => ''], 'title'],
        'a title of spaces, no-break included' => [['title' => " \u{a0} "], 'title'],
        'a title that would read as metadata' => [['title' => 'Ship (due 2026-11-01)'], 'title'],
        'a title with a line break' => [['title' => "Two\nlines"], 'title'],
        'a title over 300 characters' => [['title' => str_repeat('a', 301)], 'title'],
        'a due date that is not real' => [['title' => 'Ship', 'due' => '2026-02-30'], 'due'],
        'an empty proof' => [['title' => 'Ship', 'proof' => ''], 'proof'],
    ]);

    it('trims text the way the checker does before writing it', function () {
        $this->withHeaders(ifMatch($this->tasks))
            ->postJson('/api/v1/projects/lantern/tasks', ['title' => "\u{a0} Release notes \t"])
            ->assertOk()
            ->assertJsonPath('data.title', 'Release notes');
    });

    it('refuses bad edit, move and tick fields', function (string $uri, array $body, string $field) {
        $this->withHeaders(ifMatch($this->tasks))->postJson($uri, $body)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'evidence with a comma' => ['/api/v1/projects/lantern/tasks/T12/tick', ['evidence' => 'a, b'], 'evidence'],
        'an unknown section' => ['/api/v1/projects/lantern/tasks/T12/move', ['section' => 'Someday'], 'section'],
        'waiting with parentheses' => ['/api/v1/projects/lantern/tasks/T12/move', ['section' => 'Waiting on', 'waiting' => 'Ana (design)'], 'waiting'],
    ]);

    it('refuses an empty note or an empty title in an edit', function (array $body, string $field) {
        $this->withHeaders(ifMatch($this->tasks))->patchJson('/api/v1/projects/lantern/tasks/T12', $body)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        [['notes' => ['fine', '']], 'notes.1'],
        [['notes' => 'not a list'], 'notes'],
        [['title' => ''], 'title'],
    ]);

    it('refuses an edit the task can not take as it stands', function (string $uri, array $body, string $message) {
        $this->withHeaders(ifMatch($this->tasks))
            ->postJson($uri, $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['edit' => $message]);

        expect(file_get_contents($this->tasks))->toBe($this->original);
    })->with([
        'a move to Done' => ['/api/v1/projects/lantern/tasks/T12/move', ['section' => 'Done'], 'not moved'],
        'a move to its own section' => ['/api/v1/projects/lantern/tasks/T12/move', ['section' => 'Up next'], 'already in Up next'],
        'a move into Waiting on without waiting' => ['/api/v1/projects/lantern/tasks/T12/move', ['section' => 'Waiting on'], 'needs who or what'],
        'a tick of a task in Done' => ['/api/v1/projects/lantern/tasks/T10/tick', [], 'in Done'],
        'an undo of an open task' => ['/api/v1/projects/lantern/tasks/T12/undo', [], "can't be undone"],
    ]);

    it('refuses a reorder outside the section', function () {
        $this->withHeaders(ifMatch($this->tasks))
            ->putJson('/api/v1/projects/lantern/tasks/T12/position', ['position' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['edit' => 'outside Up next']);
    });

    it('refuses an undo whose title would turn into metadata', function () {
        file_put_contents($this->tasks, str_replace('- [x] T7 Offline mode (cancelled', '- [x] T7 Chase (waiting Bob) (cancelled', $this->original));

        $this->withHeaders(ifMatch($this->tasks))
            ->postJson('/api/v1/projects/lantern/tasks/T7/undo')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['edit' => 'Change the title first']);
    });
});

describe('428: no If-Match', function () {
    it('refuses a write that does not say which version it saw, before validating it', function () {
        $this->postJson('/api/v1/projects/lantern/tasks', [])->assertStatus(428);
        $this->postJson('/api/v1/projects/lantern/tasks/T12/tick')->assertStatus(428);

        expect(file_get_contents($this->tasks))->toBe($this->original);
    });
});

describe('500: the candidate fails the format check', function () {
    it('writes nothing when the checker prints format errors: a Markboard bug', function () {
        File::ensureDirectoryExists($this->hub.'/scripts');
        file_put_contents($this->hub.'/scripts/check-tasks.py', "print('line 1: made-up error')\n");

        $this->withHeaders(ifMatch($this->tasks))
            ->postJson('/api/v1/projects/lantern/tasks/T12/tick')
            ->assertStatus(500)
            ->assertJsonPath('message', 'The format checker rejected the edit, so nothing was written. This is a Markboard bug: line 1: made-up error');

        expect(file_get_contents($this->tasks))->toBe($this->original)
            ->and(glob(storage_path('app/check/*')))->toBe([]);
    });
});

describe('503: busy, or no verdict from the checker', function () {
    it('answers Retry-After when another write holds the lock', function () {
        $this->getJson('/')->assertOk();
        // The lock wait runs on the real clock, so the lock is taken on it too: taken on the frozen
        // clock, it would already have expired once the real time passed 2026-10-07 12:00:30.
        $this->travelBack();
        $lock = Cache::lock('markboard:file:'.hash('sha256', realpath($this->tasks)), 30);
        $lock->get();

        $this->withHeaders(ifMatch($this->tasks))
            ->postJson('/api/v1/projects/lantern/tasks/T12/tick')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '1');

        $lock->release();
        expect(file_get_contents($this->tasks))->toBe($this->original);
    });

    it('writes nothing when the checker crashes', function () {
        File::ensureDirectoryExists($this->hub.'/scripts');
        file_put_contents($this->hub.'/scripts/check-tasks.py', "raise SystemExit('boom')\n");

        $this->withHeaders(ifMatch($this->tasks))->postJson('/api/v1/projects/lantern/tasks/T12/tick')->assertStatus(503);

        expect(file_get_contents($this->tasks))->toBe($this->original);
    });

    it('refuses TASKS.md writes in a real hub with no checker', function () {
        unlink($this->hub.'/.markboard-demo');
        $registry = $this->hub.'/projects.md';
        file_put_contents($registry, str_replace('| projects/', '| '.$this->hub.'/projects/', (string) file_get_contents($registry)));

        $this->withHeaders(ifMatch($this->tasks))
            ->postJson('/api/v1/projects/lantern/tasks/T12/tick')
            ->assertStatus(503)
            ->assertJsonPath('message', "The hub has no scripts/check-tasks.py, so TASKS.md files can't be written.");
    });
});
