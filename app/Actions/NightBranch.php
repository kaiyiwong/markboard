<?php

namespace App\Actions;

use App\Models\Project;
use App\Night\NightGit;
use App\Night\StatusLog;
use App\Night\StatusRow;
use App\Sync\Hub;
use App\Sync\HubSync;
use App\Sync\SourceKind;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Merge and Discard on a night branch (docs/spec.md, Night, Actions). Both are guarded by the branch
 * head the page showed [D35], and both end with a status row, so the hub's runner never builds
 * the task again. Merge also ticks the task, in the merge commit [D40].
 */
final readonly class NightBranch
{
    public function __construct(
        private Hub $hub,
        private HubSync $sync,
        private WriteFile $write,
    ) {}

    /**
     * Merges the branch into main with a merge commit, then removes its worktree and branch [D36].
     * The task is ticked in the project's TASKS.md inside the same merge commit [D40], and the
     * answer says how far main is ahead of its remote, since nothing is pushed from here.
     */
    public function merge(Project $project, string $task, string $head): JsonResponse
    {
        $git = $this->checked($project, $task, $head);
        $latest = $this->log()->latest($project->id, $task);
        abort_unless($latest?->outcome === 'ready', 409, "Only a task the night run left ready can be merged; {$task}'s last outcome is ".($latest->outcome ?? 'unknown').'.');

        $base = (string) $git->baseBranch();
        $command = "cd {$project->path} && git merge --no-ff ".NightGit::branch($task);
        $on = $git->currentBranch();
        abort_unless($on === $base, 409, "The main checkout is on {$on}, not {$base}. Switch it to {$base}, or merge by hand: {$command}");
        $uncommitted = $git->uncommitted();
        abort_unless($uncommitted === [], 409, 'The main checkout has uncommitted changes ('.implode(', ', array_slice($uncommitted, 0, 8)).(count($uncommitted) > 8 ? ', …' : '').'). Commit them first, or merge by hand: '.$command);

        $tick = null;
        $merged = $git->merge(NightGit::branch($task), function () use ($project, $task, $head, &$tick): array {
            $tick = $this->tick($project, $task, $head);

            return $tick['ticked']
                ? ['message' => "done {$task}: merge ".NightGit::branch($task), 'add' => ['TASKS.md']]
                : ['message' => null, 'add' => []];
        });
        if (! $merged['ok']) {
            if ($tick['ticked'] ?? false) {
                $this->resync($project); // the abort put TASKS.md back
            }
            abort(409, ($merged['conflicts'] === [] ? "The merge couldn't be committed ({$merged['message']})" : 'The merge conflicts in '.implode(', ', $merged['conflicts'])).", so it was aborted and nothing changed. Merge by hand: {$command}");
        }
        $git->removeBranch($task, force: false);
        $this->append($project->id, $task, 'merged', $merged['commit'], "merged into {$base} from Markboard; {$tick['note']}");

        return response()->json(['data' => [
            'project' => $project->id,
            'task' => $task,
            'outcome' => 'merged',
            'commit' => $merged['commit'],
            'ticked' => $tick['ticked'],
            'tick' => $tick['note'],
            'unpushed' => $git->unpushed($base),
        ]]);
    }

    /**
     * Ticks the task in the project's TASKS.md through the write path, with the branch head as its
     * evidence. Only a night task that is a whole TASKS.md task (`T<n>`) can be ticked; anything
     * else, or a refusal, leaves the file alone and says why, and the merge goes ahead.
     *
     * @return array{ticked: bool, note: string}
     */
    private function tick(Project $project, string $task, string $head): array
    {
        if (preg_match('/^T(\d+)$/', $task, $number) !== 1) {
            return ['ticked' => false, 'note' => "not ticked: {$task} isn't a task ID in TASKS.md, so tick it yourself when the whole task is done"];
        }
        $bytes = @file_get_contents($project->path.'/TASKS.md');
        if ($bytes === false) {
            return ['ticked' => false, 'note' => 'not ticked: the project has no TASKS.md'];
        }
        try {
            $this->write->write($project, new TickTask((int) $number[1], NightGit::branch($task).' '.substr($head, 0, 7)), hash('sha256', $bytes));
        } catch (Throwable $e) {
            $why = match (true) {
                $e instanceof HttpResponseException => (string) (json_decode((string) $e->getResponse()->getContent(), true)['message'] ?? 'refused'),
                $e instanceof ValidationException => (string) collect($e->errors())->flatten()->first(),
                default => $e->getMessage(),
            };

            return ['ticked' => false, 'note' => 'not ticked: '.rtrim(preg_replace('/\s+/', ' ', $why) ?? '', '.')];
        }

        return ['ticked' => true, 'note' => "ticked {$task} in TASKS.md"];
    }

    /** Re-indexes TASKS.md after an aborted merge put back the version before the tick. */
    private function resync(Project $project): void
    {
        $path = $project->path.'/TASKS.md';
        $lock = $this->sync->lock($path);
        $lock->block(3);
        try {
            $this->sync->syncLocked(SourceKind::Tasks, $path, $project->id);
        } finally {
            $lock->release();
        }
    }

    /** Removes the worktree and the branch, unmerged work and all. */
    public function discard(Project $project, string $task, string $head): JsonResponse
    {
        $git = $this->checked($project, $task, $head);
        $git->removeBranch($task, force: true);
        $short = substr($head, 0, 7);
        $this->append($project->id, $task, 'discarded', $short, 'discarded from Markboard');

        return response()->json(['data' => ['project' => $project->id, 'task' => $task, 'outcome' => 'discarded', 'commit' => $short]]);
    }

    /** The branch exists (404), and is still where the page showed it (412). */
    private function checked(Project $project, string $task, string $head): NightGit
    {
        $git = new NightGit($project->path);
        $branch = NightGit::branch($task);
        $now = $project->folder_found && $git->isRepo() ? $git->head($branch) : null;
        abort_if($now === null, 404, "There's no {$branch} branch in {$project->name}.");
        if (! str_starts_with($now, strtolower($head))) {
            abort(response()->json(['message' => "{$branch} has moved since the page showed it. Look at it again before you act.", 'head' => $now], 412));
        }

        return $git;
    }

    private function log(): StatusLog
    {
        $path = $this->hub->ordersPath('status.tsv');

        return StatusLog::parse(is_file($path) ? (string) file_get_contents($path) : '');
    }

    /** One row at the end of status.tsv, in the hub's local time, under the file's lock. */
    private function append(string $project, string $task, string $outcome, string $commit, string $reason): void
    {
        $path = $this->hub->ordersPath('status.tsv');
        $lock = $this->sync->lock($path);
        try {
            $lock->block(3);
        } catch (LockTimeoutException) {
            abort(503, 'status.tsv is being written. The branch action is done; reload to see it.', ['Retry-After' => '1']);
        }
        try {
            $time = now(config()->string('markboard.timezone'))->format('Y-m-d H:i');
            file_put_contents($path, StatusLog::line(new StatusRow($project, $task, $outcome, $time, $commit, $reason)), FILE_APPEND | LOCK_EX);
        } finally {
            $lock->release();
        }
    }
}
