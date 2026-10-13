<?php

use App\Markdown\Section;
use App\Models\Task;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
| The Night page's actions (docs/spec.md, Night, Actions). Answers and STOP run on the temp demo
| hub. Merge and Discard need a real repo: each test builds one with a night branch in a worktree
| beside it, as the hub's runner leaves them, and registers it in the temp hub.
*/

/** Runs git in a test repo, with its own identity and no global hooks. */
function git(string $repo, string ...$args): string
{
    $result = Process::run(['git', '-C', $repo, ...$args]);
    expect($result->successful())->toBeTrue($result->errorOutput());

    return trim($result->output());
}

/**
 * A project repo on main with a night branch (night/T20 by default) in a worktree beside it,
 * registered in the temp hub as `nightly`, with a `ready` status row for the task. Its TASKS.md has
 * T20 in Up next. Returns the repo path and the branch head.
 *
 * @return array{string, string}
 */
function nightRepo(string $hub, string $branchFile = 'feature.txt', string $branchText = "night work\n", string $task = 'T20'): array
{
    $repo = dirname($hub).'/'.basename($hub).'-nightly';
    File::ensureDirectoryExists($repo);
    git($repo, 'init', '-q', '-b', 'main');
    foreach (['user.name' => 'Sam Rivera', 'user.email' => 'sam@example.com', 'core.hooksPath' => '/dev/null', 'commit.gpgsign' => 'false'] as $key => $value) {
        git($repo, 'config', $key, $value);
    }
    File::put($repo.'/TASKS.md', "# Nightly tasks\n\n## Up next\n- [ ] T20 The night's work\n- [ ] T21 Something else\n\n## In progress\n\n## Waiting on\n\n## Done\n");
    File::put($repo.'/shared.txt', "line one\n");
    git($repo, 'add', '-A');
    git($repo, 'commit', '-q', '-m', 'first');
    git($repo, 'worktree', 'add', '-q', '-b', "night/{$task}", "{$repo}-night-{$task}", 'main');
    File::put("{$repo}-night-{$task}/{$branchFile}", $branchText);
    git("{$repo}-night-{$task}", 'add', '-A');
    git("{$repo}-night-{$task}", 'commit', '-q', '-m', "{$task}: the night's work");

    File::append($hub.'/projects.md', "| nightly | Nightly | {$repo} | product | active | Ship | |\n");
    File::append($hub.'/orders/status.tsv', "nightly\t{$task}\tready\t2026-10-06 23:50\t".substr(git($repo, 'rev-parse', "night/{$task}"), 0, 7)."\tverify passed\n");

    return [$repo, git($repo, 'rev-parse', "night/{$task}")];
}

afterEach(function () {
    foreach (glob(dirname($this->hub).'/'.basename($this->hub).'-nightly*') ?: [] as $folder) {
        File::deleteDirectory($folder);
    }
});

describe('answers', function () {
    $url = '/api/v1/night/orders/bramble-bakery/T6/answers';

    it('writes the answers into the order and keeps every other byte', function () use ($url) {
        $path = $this->hub.'/orders/bramble-bakery/T6.md';
        $before = File::get($path);

        $this->putJson($url, ['answers' => 'Every 30 minutes from 8:00. No same-day orders.'], ifMatch($path))
            ->assertOk()
            ->assertJsonPath('data.task', 'T6')
            ->assertHeader('ETag', '"'.hash_file('sha256', $path).'"');

        expect(File::get($path))->toBe($before."\n## KAI'S ANSWERS\n\nEvery 30 minutes from 8:00. No same-day orders.\n");
    });

    it('needs If-Match (428), refuses a stale one with the current etag (412)', function () use ($url) {
        $path = $this->hub.'/orders/bramble-bakery/T6.md';
        $this->putJson($url, ['answers' => 'Yes.'])->assertStatus(428);

        $stale = ifMatch($path);
        File::append($path, "\nThe chief of staff added a line.\n");
        $this->putJson($url, ['answers' => 'Yes.'], $stale)
            ->assertStatus(412)
            ->assertJsonPath('etag', hash_file('sha256', $path));
        expect(File::get($path))->not->toContain("KAI'S ANSWERS");
    });

    it('refuses an order that no longer waits on Kai (409), an unknown one (404) and empty answers (422)', function () use ($url) {
        $ready = $this->hub.'/orders/lantern/T12.md';
        $this->putJson('/api/v1/night/orders/lantern/T12/answers', ['answers' => 'Yes.'], ifMatch($ready))->assertStatus(409);
        $this->putJson('/api/v1/night/orders/lantern/T99/answers', ['answers' => 'Yes.'], ['If-Match' => '"x"'])->assertNotFound();
        $this->putJson($url, ['answers' => '   '], ifMatch($this->hub.'/orders/bramble-bakery/T6.md'))->assertStatus(422);
    });

    it('never reaches outside orders/: a task ID with a slash or a leading dot is not a route', function () {
        $this->putJson('/api/v1/night/orders/lantern/..%2F..%2Fprojects/answers', ['answers' => 'x'], ['If-Match' => '"x"'])->assertNotFound();
        $this->putJson('/api/v1/night/orders/lantern/.hidden/answers', ['answers' => 'x'], ['If-Match' => '"x"'])->assertNotFound();
    });
});

describe('keep', function () {
    $url = '/api/v1/night/triage/2026-10-06/keep';

    it('writes a kept line under the quick close and nothing else', function () use ($url) {
        $path = $this->hub.'/orders/triage-2026-10-06.md';
        $before = File::get($path);
        $this->travelTo('2026-10-07 09:00:00');

        $this->postJson($url, ['project' => 'tidepool', 'task' => 'T4'], ifMatch($path))
            ->assertOk()->assertJsonPath('data.kept', '2026-10-07')->assertHeader('ETag', '"'.hash_file('sha256', $path).'"');
        expect(File::get($path))->toBe(str_replace("crab.ogg`.\n", "crab.ogg`.\n  kept: 2026-10-07\n", $before));
    });

    it('refuses a stale etag with 412 and the note\'s current etag', function () use ($url) {
        $path = $this->hub.'/orders/triage-2026-10-06.md';

        $this->postJson($url, ['project' => 'tidepool', 'task' => 'T4'], ['If-Match' => '"stale"'])
            ->assertStatus(412)->assertJsonPath('etag', hash_file('sha256', $path));
    });

    it('answers 409 for a task with no open quick close, and 404 for a night with no note', function () use ($url) {
        $path = $this->hub.'/orders/triage-2026-10-06.md';
        $this->postJson($url, ['project' => 'tidepool', 'task' => 'T4'], ifMatch($path))->assertOk();

        $this->postJson($url, ['project' => 'tidepool', 'task' => 'T4'], ifMatch($path))->assertStatus(409);
        $this->postJson($url, ['project' => 'lantern', 'task' => 'T12'], ifMatch($path))->assertStatus(409);
        $this->postJson('/api/v1/night/triage/2026-10-05/keep', ['project' => 'tidepool', 'task' => 'T4'], ['If-Match' => '"x"'])->assertNotFound();
    });

    it('validates the project and task', function () use ($url) {
        $this->postJson($url, ['project' => '../x', 'task' => ''], ifMatch($this->hub.'/orders/triage-2026-10-06.md'))
            ->assertStatus(422)->assertJsonValidationErrors(['project', 'task']);
    });
});

describe('stop', function () {
    it('creates and deletes orders/STOP', function () {
        $this->putJson('/api/v1/night/stop', ['stop' => true])->assertOk()->assertJsonPath('data.stopped', true);
        expect(File::exists($this->hub.'/orders/STOP'))->toBeTrue();

        $this->putJson('/api/v1/night/stop', ['stop' => false])->assertOk()->assertJsonPath('data.stopped', false);
        expect(File::exists($this->hub.'/orders/STOP'))->toBeFalse();
    });

    it('validates stop (422)', function () {
        $this->putJson('/api/v1/night/stop', [])->assertStatus(422);
    });
});

describe('merge', function () {
    it('merges the branch into main, removes the worktree and branch, and appends a merged row', function () {
        [$repo, $head] = nightRepo($this->hub);

        $response = $this->postJson('/api/v1/night/branches/nightly/T20/merge', ['head' => $head])
            ->assertOk()
            ->assertJsonPath('data.outcome', 'merged');

        expect(File::get($repo.'/feature.txt'))->toBe("night work\n")
            ->and(git($repo, 'log', '-1', '--format=%s'))->toBe('done T20: merge night/T20')
            ->and(git($repo, 'branch', '--list', 'night/T20'))->toBe('')
            ->and(File::isDirectory($repo.'-night-T20'))->toBeFalse()
            ->and(File::get($this->hub.'/orders/status.tsv'))->toContain("nightly\tT20\tmerged\t")
            ->and(File::get($this->hub.'/orders/status.tsv'))->toContain($response->json('data.commit'));
    });

    it('ticks the task in TASKS.md inside the merge commit, with the branch head as evidence [D40]', function () {
        [$repo, $head] = nightRepo($this->hub);
        $today = now(config()->string('markboard.timezone'))->format('Y-m-d');
        $short = substr($head, 0, 7);

        $this->postJson('/api/v1/night/branches/nightly/T20/merge', ['head' => $head])
            ->assertOk()
            ->assertJsonPath('data.ticked', true)
            ->assertJsonPath('data.tick', 'ticked T20 in TASKS.md');

        expect(File::get($repo.'/TASKS.md'))->toBe("# Nightly tasks\n\n## Up next\n- [ ] T21 Something else\n\n## In progress\n\n## Waiting on\n\n## Done\n- [x] T20 The night's work (done {$today}, evidence night/T20 {$short}, from Up next)\n")
            ->and(git($repo, 'status', '--porcelain'))->toBe('')
            ->and(git($repo, 'show', '--format=', '--name-only', 'HEAD'))->toBe('TASKS.md')
            ->and(Task::where('project_id', 'nightly')->where('number', 20)->sole()->section)->toBe(Section::Done)
            ->and(File::get($this->hub.'/orders/status.tsv'))->toContain('merged into main from Markboard; ticked T20 in TASKS.md');
    });

    it('still merges a task that isn\'t a TASKS.md ID (a slice), and says it wasn\'t ticked', function () {
        [$repo, $head] = nightRepo($this->hub, task: '0048-action-errors');
        $before = File::get($repo.'/TASKS.md');

        $this->postJson('/api/v1/night/branches/nightly/0048-action-errors/merge', ['head' => $head])
            ->assertOk()
            ->assertJsonPath('data.ticked', false)
            ->assertJsonPath('data.tick', fn (string $note) => str_starts_with($note, "not ticked: 0048-action-errors isn't a task ID in TASKS.md"));
        expect(File::get($repo.'/TASKS.md'))->toBe($before)
            ->and(git($repo, 'log', '-1', '--format=%s'))->toBe("Merge branch 'night/0048-action-errors'");
    });

    it('still merges a task that isn\'t in TASKS.md, and says why it wasn\'t ticked', function () {
        [$repo, $head] = nightRepo($this->hub, task: 'T30');
        $this->postJson('/api/v1/night/branches/nightly/T30/merge', ['head' => $head])
            ->assertOk()
            ->assertJsonPath('data.ticked', false)
            ->assertJsonPath('data.tick', fn (string $note) => str_starts_with($note, 'not ticked: ') && str_contains($note, 'T30'));
        expect(git($repo, 'status', '--porcelain'))->toBe('')
            ->and(File::get($this->hub.'/orders/status.tsv'))->toContain("nightly\tT30\tmerged\t");
    });

    it('aborts a merge whose commit fails (a hook), puts the tick back, and changes nothing', function () {
        [$repo, $head] = nightRepo($this->hub);
        $hooks = $repo.'-hooks';
        File::ensureDirectoryExists($hooks);
        File::put($hooks.'/commit-msg', "#!/bin/sh\necho 'no thanks' >&2\nexit 1\n");
        chmod($hooks.'/commit-msg', 0755);
        git($repo, 'config', 'core.hooksPath', $hooks);
        $mainHead = git($repo, 'rev-parse', 'main');

        $this->postJson('/api/v1/night/branches/nightly/T20/merge', ['head' => $head])
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'no thanks') && str_contains($message, 'nothing changed'));
        expect(git($repo, 'rev-parse', 'main'))->toBe($mainHead)
            ->and(git($repo, 'status', '--porcelain'))->toBe('')
            ->and(File::get($repo.'/TASKS.md'))->toContain('- [ ] T20 ')
            ->and(Task::where('project_id', 'nightly')->where('number', 20)->sole()->section)->toBe(Section::UpNext)
            ->and(git($repo, 'branch', '--list', 'night/T20'))->not->toBe('')
            ->and(File::get($this->hub.'/orders/status.tsv'))->not->toContain("nightly\tT20\tmerged");
    });

    it('says how far main is ahead of its remote, and the page lists it to push', function () {
        [$repo, $head] = nightRepo($this->hub);
        $remote = $repo.'-remote.git';
        git(dirname($repo), 'init', '-q', '--bare', $remote);
        git($repo, 'remote', 'add', 'origin', $remote);
        git($repo, 'push', '-q', '-u', 'origin', 'main');
        expect($this->get('/night')->inertiaProps('unpushed'))->toBe([]);

        $this->postJson('/api/v1/night/branches/nightly/T20/merge', ['head' => $head])
            ->assertOk()
            ->assertJsonPath('data.unpushed', ['remote' => 'origin/main', 'ahead' => 2]);

        expect($this->get('/night')->inertiaProps('unpushed'))->toBe([[
            'project' => 'nightly', 'project_name' => 'Nightly', 'base' => 'main', 'remote' => 'origin/main', 'ahead' => 2, 'command' => "cd {$repo} && git push",
        ]]);
        git($repo, 'push', '-q');
        expect($this->get('/night')->inertiaProps('unpushed'))->toBe([]);
    });

    it('has nothing to push for a repo with no remote', function () {
        [, $head] = nightRepo($this->hub);
        $this->postJson('/api/v1/night/branches/nightly/T20/merge', ['head' => $head])->assertJsonPath('data.unpushed', null);
        expect($this->get('/night')->inertiaProps('unpushed'))->toBe([]);
    });

    it('takes the short head the page showed', function () {
        [, $head] = nightRepo($this->hub);
        $this->postJson('/api/v1/night/branches/nightly/T20/merge', ['head' => substr($head, 0, 7)])->assertOk();
    });

    it('refuses a branch that moved since the page showed it (412), and changes nothing', function () {
        [$repo, $head] = nightRepo($this->hub);
        File::put($repo.'-night-T20/more.txt', "more\n");
        git($repo.'-night-T20', 'add', '-A');
        git($repo.'-night-T20', 'commit', '-q', '-m', 'T20: more');

        $this->postJson('/api/v1/night/branches/nightly/T20/merge', ['head' => $head])
            ->assertStatus(412)
            ->assertJsonPath('head', git($repo, 'rev-parse', 'night/T20'));
        expect(File::exists($repo.'/feature.txt'))->toBeFalse();
    });

    it('refuses a main checkout with uncommitted changes (409), naming them, and changes nothing', function () {
        [$repo, $head] = nightRepo($this->hub);
        File::append($repo.'/shared.txt', "Kai's work in progress\n");

        $this->postJson('/api/v1/night/branches/nightly/T20/merge', ['head' => $head])
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'shared.txt') && str_contains($message, 'git merge --no-ff night/T20'));
        expect(git($repo, 'branch', '--list', 'night/T20'))->not->toBe('')
            ->and(File::get($this->hub.'/orders/status.tsv'))->not->toContain("nightly\tT20\tmerged");
    });

    it('aborts a conflicting merge (409), naming the files, and leaves main as it was', function () {
        [$repo, $head] = nightRepo($this->hub, 'shared.txt', "the branch's line\n");
        File::put($repo.'/shared.txt', "main's line\n");
        git($repo, 'commit', '-q', '-am', 'main moved on');
        $mainHead = git($repo, 'rev-parse', 'main');

        $this->postJson('/api/v1/night/branches/nightly/T20/merge', ['head' => $head])
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'conflicts in shared.txt'));
        expect(git($repo, 'rev-parse', 'main'))->toBe($mainHead)
            ->and(git($repo, 'status', '--porcelain'))->toBe('')
            ->and(File::get($repo.'/shared.txt'))->toBe("main's line\n");
    });

    it('refuses a task whose last outcome isn\'t ready (409), and an unknown branch or project (404)', function () {
        [, $head] = nightRepo($this->hub);
        File::append($this->hub.'/orders/status.tsv', "nightly\tT20\tstuck\t2026-10-07 00:10\t\tthree rounds\n");
        $this->postJson('/api/v1/night/branches/nightly/T20/merge', ['head' => $head])->assertStatus(409);

        $this->postJson('/api/v1/night/branches/nightly/T21/merge', ['head' => $head])->assertNotFound();
        $this->postJson('/api/v1/night/branches/nowhere/T20/merge', ['head' => $head])->assertNotFound();
        $this->postJson('/api/v1/night/branches/lantern/T12/merge', ['head' => $head])->assertNotFound();
    });

    it('validates head (422)', function () {
        $this->postJson('/api/v1/night/branches/lantern/T12/merge', ['head' => 'not a sha'])->assertStatus(422);
        $this->postJson('/api/v1/night/branches/lantern/T12/merge', [])->assertStatus(422);
    });
});

describe('discard', function () {
    it('removes the worktree and the unmerged branch, and appends a discarded row', function () {
        [$repo, $head] = nightRepo($this->hub);

        $this->postJson('/api/v1/night/branches/nightly/T20/discard', ['head' => $head])
            ->assertOk()
            ->assertJsonPath('data.outcome', 'discarded');

        expect(git($repo, 'branch', '--list', 'night/T20'))->toBe('')
            ->and(File::isDirectory($repo.'-night-T20'))->toBeFalse()
            ->and(File::exists($repo.'/feature.txt'))->toBeFalse()
            ->and(File::get($this->hub.'/orders/status.tsv'))->toContain("nightly\tT20\tdiscarded\t");
    });

    it('refuses a branch that moved (412)', function () {
        [$repo] = nightRepo($this->hub);
        $this->postJson('/api/v1/night/branches/nightly/T20/discard', ['head' => 'abcdef1'])->assertStatus(412);
        expect(git($repo, 'branch', '--list', 'night/T20'))->not->toBe('');
    });
});

describe('the page with a real branch', function () {
    it('shows the ready task\'s commits and diff, and the head the actions send back', function () {
        [, $head] = nightRepo($this->hub);

        $branch = collect($this->get('/night')->inertiaProps('tasks.ready'))->firstWhere('task', 'T20')['branch'];
        expect($branch['state'])->toBe('present')
            ->and($branch['head'])->toBe($head)
            ->and($branch['base'])->toBe('main')
            ->and(array_column($branch['commits'], 'subject'))->toBe(["T20: the night's work"])
            ->and($branch['files'][0]['path'])->toBe('feature.txt')
            ->and($branch['files'][0]['added'])->toBe(1)
            ->and($branch['files'][0]['patch'])->toContain('+night work')
            ->and($branch['truncated'])->toBeFalse();
    });
});
