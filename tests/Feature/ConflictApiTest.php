<?php

use App\Models\Conflict;
use App\Models\FileVersion;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Conflicts: a stale edit is refused with a 412 and recorded; the page shows it above the tasks
| or board; Apply runs it on the version on disk now if what it changes is unchanged, and
| Discard closes it. On a temp copy of demo/ (see TestCase).
*/

beforeEach(function () {
    $this->travelTo('2026-10-07 12:00:00');
    $this->tasks = $this->hub.'/projects/lantern/TASKS.md';
    $this->original = (string) file_get_contents($this->tasks);
});

/** Opens the page, changes the file on disk, then submits the edit with the page's etag: a 412. */
function staleEdit(string $uri, array $body, string $search, string $replace): Conflict
{
    $test = test();
    $test->get('/projects/lantern');
    $seen = ifMatch($test->tasks);
    file_put_contents($test->tasks, str_replace($search, $replace, $test->original));
    $test->withHeaders($seen)->postJson($uri, $body)->assertStatus(412);

    return Conflict::latest('id')->firstOrFail();
}

describe('the conflict panel', function () {
    it('shows open conflicts above the tasks, with the diff and the etag Apply must send', function () {
        $conflict = staleEdit('/api/v1/projects/lantern/tasks/T12/tick', [], 'Keyboard shortcuts', 'Keyboard bindings');

        $this->get('/projects/lantern')->assertInertia(fn (Assert $page) => $page
            ->has('conflicts', 1)
            ->where('conflicts.0.id', $conflict->id)
            ->where('conflicts.0.summary', 'Tick T12')
            ->where('conflicts.0.etag', hash_file('sha256', $this->tasks))
            ->where('conflicts.0.applicable', true)
            ->where('conflicts.0.current.task_id', 'T12')
            ->where('conflicts.0.diff.2', ['op' => 'removed', 'old' => 9, 'new' => null, 'text' => '- [ ] T15 Keyboard shortcuts (v2)'])
            ->where('conflicts.0.diff.3', ['op' => 'added', 'old' => null, 'new' => 9, 'text' => '- [ ] T15 Keyboard bindings (v2)']));
    });

    it('shows open conflicts above the pipeline board', function () {
        $pipeline = $this->hub.'/projects/job-search/pipeline.md';
        $this->get('/pipeline');
        $seen = ifMatch($pipeline);
        file_put_contents($pipeline, file_get_contents($pipeline)."\nNotes added later.\n");
        $this->withHeaders($seen)->patchJson('/api/v1/projects/job-search/pipeline/rows/1', ['stage' => 'offer'])->assertStatus(412);

        $this->get('/pipeline')->assertInertia(fn (Assert $page) => $page
            ->has('boards.0.conflicts', 1)
            ->where('boards.0.conflicts.0.summary', 'Edit row 1')
            ->where('boards.0.conflicts.0.applicable', true));
    });

    it('can not apply an edit whose task changed, and the page says so', function () {
        staleEdit('/api/v1/projects/lantern/tasks/T12/tick', [], 'Export to CSV', 'Export to CSV files');

        $this->get('/projects/lantern')->assertInertia(fn (Assert $page) => $page
            ->where('conflicts.0.applicable', false)
            ->where('conflicts.0.current.title', 'Export to CSV files'));
    });

    it('shows no diff and no Apply when the version the user edited is no longer stored', function () {
        $conflict = staleEdit('/api/v1/projects/lantern/tasks/T12/tick', [], 'Keyboard shortcuts', 'Keyboard bindings');
        FileVersion::where('hash', $conflict->base_hash)->delete();

        $this->get('/projects/lantern')->assertInertia(fn (Assert $page) => $page
            ->where('conflicts.0.applicable', false)
            ->where('conflicts.0.diff', null));
    });

    it('stops showing resolved and superseded conflicts', function () {
        $conflict = staleEdit('/api/v1/projects/lantern/tasks/T12/tick', [], 'Keyboard shortcuts', 'Keyboard bindings');
        $conflict->update(['status' => 'resolved']);

        $this->get('/projects/lantern')->assertInertia(fn (Assert $page) => $page->where('conflicts', []));
    });
});

describe('apply', function () {
    it('applies the edit to the version on disk now, keeping the other change, and resolves the conflict', function () {
        $conflict = staleEdit('/api/v1/projects/lantern/tasks/T12/tick', ['evidence' => 'abc123'], 'Keyboard shortcuts', 'Keyboard bindings');

        $this->withHeaders(ifMatch($this->tasks))
            ->postJson("/api/v1/conflicts/{$conflict->id}/apply")
            ->assertOk()
            ->assertJsonPath('data.task_id', 'T12')
            ->assertJsonPath('data.section', 'Done')
            ->assertHeader('ETag', '"'.hash_file('sha256', $this->tasks).'"');

        expect(file_get_contents($this->tasks))
            ->toContain('- [ ] T15 Keyboard bindings (v2)')
            ->toContain("## Done\n- [x] T12 Export to CSV (due 2026-10-20, done 2026-10-07, evidence abc123, from Up next)\n")
            ->and($conflict->fresh()->status)->toBe('resolved');
    });

    it('applies an Add, which never depends on the rest of the file', function () {
        $conflict = staleEdit('/api/v1/projects/lantern/tasks', ['title' => 'Release notes'], '- [ ] T15 Keyboard shortcuts (v2)', "- [ ] T15 Keyboard shortcuts (v2)\n- [ ] T16 Added by an agent");

        $this->withHeaders(ifMatch($this->tasks))
            ->postJson("/api/v1/conflicts/{$conflict->id}/apply")
            ->assertOk()
            ->assertJsonPath('data.task_id', 'T17');
    });

    it('refuses with 409, and keeps the conflict open, when what the edit changes is different on disk', function () {
        $conflict = staleEdit('/api/v1/projects/lantern/tasks/T12/tick', [], 'Export to CSV', 'Export to CSV files');
        $before = file_get_contents($this->tasks);

        $this->withHeaders(ifMatch($this->tasks))->postJson("/api/v1/conflicts/{$conflict->id}/apply")->assertConflict();

        expect(file_get_contents($this->tasks))->toBe($before)
            ->and($conflict->fresh()->status)->toBe('open');
    });

    it('refuses a reorder once its section changed', function () {
        $this->get('/projects/lantern');
        $seen = ifMatch($this->tasks);
        file_put_contents($this->tasks, str_replace("- [ ] T15 Keyboard shortcuts (v2)\n", "- [ ] T15 Keyboard shortcuts (v2)\n- [ ] T16 Added by an agent\n", $this->original));
        $this->withHeaders($seen)->putJson('/api/v1/projects/lantern/tasks/T15/position', ['position' => 0])->assertStatus(412);
        $conflict = Conflict::sole();

        $this->get('/projects/lantern')->assertInertia(fn (Assert $page) => $page->where('conflicts.0.applicable', false));
        $this->withHeaders(ifMatch($this->tasks))->postJson("/api/v1/conflicts/{$conflict->id}/apply")->assertConflict();
    });

    it('supersedes the conflict with a new one that keeps its base when the file changed again', function () {
        $conflict = staleEdit('/api/v1/projects/lantern/tasks/T12/tick', [], 'Keyboard shortcuts', 'Keyboard bindings');
        $panel = ifMatch($this->tasks);
        file_put_contents($this->tasks, file_get_contents($this->tasks).'One more change.'."\n");

        $this->withHeaders($panel)->postJson("/api/v1/conflicts/{$conflict->id}/apply")->assertStatus(412);

        $new = Conflict::latest('id')->first();
        expect($conflict->fresh()->status)->toBe('superseded')
            ->and($new->id)->not->toBe($conflict->id)
            ->and($new->status)->toBe('open')
            ->and($new->base_hash)->toBe($conflict->base_hash)
            ->and($new->disk_hash)->toBe(hash_file('sha256', $this->tasks));
    });

    it('refuses a conflict that is already resolved, or unknown, or has no If-Match', function () {
        $conflict = staleEdit('/api/v1/projects/lantern/tasks/T12/tick', [], 'Keyboard shortcuts', 'Keyboard bindings');

        $this->flushHeaders()->postJson("/api/v1/conflicts/{$conflict->id}/apply")->assertStatus(428);
        $this->withHeaders(ifMatch($this->tasks))->postJson('/api/v1/conflicts/999/apply')->assertNotFound();

        $conflict->update(['status' => 'resolved']);
        $this->withHeaders(ifMatch($this->tasks))->postJson("/api/v1/conflicts/{$conflict->id}/apply")->assertConflict();
    });
});

describe('discard', function () {
    it('resolves the conflict without If-Match and changes nothing', function () {
        $conflict = staleEdit('/api/v1/projects/lantern/tasks/T12/tick', [], 'Keyboard shortcuts', 'Keyboard bindings');
        $before = file_get_contents($this->tasks);

        $this->postJson("/api/v1/conflicts/{$conflict->id}/discard")
            ->assertOk()
            ->assertJsonPath('data', ['id' => $conflict->id, 'status' => 'resolved']);

        expect(file_get_contents($this->tasks))->toBe($before);
        $this->postJson("/api/v1/conflicts/{$conflict->id}/discard")->assertConflict();
        $this->postJson('/api/v1/conflicts/999/discard')->assertNotFound();
    });
});
