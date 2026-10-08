<?php

namespace App\Actions;

use App\Http\Resources\ConflictResource;
use App\Markdown\EditRefused;
use App\Markdown\FileNotEditable;
use App\Markdown\PipelineFile;
use App\Markdown\RowNotFound;
use App\Markdown\TaskNotFound;
use App\Markdown\TasksFile;
use App\Models\Conflict;
use App\Models\Project;
use App\Models\SourceFile;
use App\Sync\Hub;
use App\Sync\HubSync;
use App\Sync\SourceKind;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * The write path every edit goes through (docs/spec.md, "The write path"; the step numbers below
 * are the spec's). It never writes over a version the user hasn't seen: a file that changed since
 * the etag they sent is refused with a 412 and a recorded conflict, checked when the file is read
 * and again just before the new version replaces it.
 */
final class WriteFile
{
    /** How long a request waits for another write of the same file before answering 503. */
    private const int LOCK_WAIT_SECONDS = 3;

    private const int CHECK_TIMEOUT_SECONDS = 10;

    /**
     * A test hook, called with the file's real path after the new version is checked and written
     * to its temp file and before the original is hashed again (step 7).
     */
    public ?Closure $beforeReplace = null;

    public function __construct(
        private readonly Hub $hub,
        private readonly HubSync $sync,
    ) {}

    /**
     * Runs the edit on the project's TASKS.md or pipeline.md and answers with the task or row it
     * wrote and the file's new ETag. With a conflict, this is Apply: the edit runs on the version
     * on disk only if its task or row is unchanged since the version the conflict was made from.
     *
     * @param  string  $etag  the hash of the version the user saw (If-Match)
     */
    public function write(Project $project, FileEdit $edit, string $etag, ?Conflict $applying = null): JsonResponse
    {
        $kind = $edit->kind();
        $path = $project->path.'/'.($kind === SourceKind::Tasks ? 'TASKS.md' : 'pipeline.md');
        $name = basename($path);

        // 1. The real file (a symlink stays a symlink), and its lock.
        $real = realpath($path);
        abort_if($real === false, 409, "{$name} doesn't exist.");
        abort_unless(is_file($real), 409, "{$name} isn't a regular file.");
        $lock = $this->sync->lock($path);
        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            abort(503, "{$name} is being written by another request. Try again.", ['Retry-After' => '1']);
        }

        $temp = null;
        try {
            // 2. Still the version the user saw?
            $bytes = @file_get_contents($real);
            abort_if($bytes === false, 409, "{$name} doesn't exist.");
            $hash = hash('sha256', $bytes);
            if ($hash !== $etag) {
                $this->refuse($project, $path, $edit, $etag, $hash, $applying);
            }
            if ($applying !== null && ! $applying->canApplyTo($bytes)) {
                abort(409, 'What this edit changes is different on disk now, so it can only be discarded.');
            }

            // 3 and 4. Editable, the task or row there, the state checks, then the new lines.
            $candidate = $this->apply($edit, $bytes);

            // 5. The new version must parse cleanly, and a TASKS.md must pass the hub's checker.
            $this->check($kind, $candidate);

            // 6. The new version goes to a temp file next to the original, with its permissions.
            $temp = dirname($real).'/.'.basename($real).'.markboard-'.bin2hex(random_bytes(6)).'.tmp';
            if (@file_put_contents($temp, $candidate) === false || ! @chmod($temp, (int) fileperms($real) & 0777)) {
                throw new RuntimeException("Couldn't write a temp file next to {$path}.");
            }
            if ($this->beforeReplace !== null) {
                ($this->beforeReplace)($real);
            }

            // 7. Changed on disk while this request worked on it?
            clearstatcache(true, $real);
            $now = @hash_file('sha256', $real);
            abort_if($now === false, 409, "{$name} doesn't exist.");
            if ($now !== $hash) {
                $this->refuse($project, $path, $edit, $etag, $now, $applying);
            }

            // 8. Atomic on the same filesystem.
            if (! @rename($temp, $real)) {
                throw new RuntimeException("Couldn't replace {$path}.");
            }
            $temp = null;

            // 9. Re-synced under the same lock, so the response and the next page show the new rows.
            $this->sync->syncLocked($kind, $path, $project->id);
            $applying?->update(['status' => Conflict::RESOLVED]);
            $file = SourceFile::where('path_hash', hash('sha256', $path))->sole();

            return $edit->result($file)->response()->setEtag(hash('sha256', $candidate));
        } finally {
            if ($temp !== null) {
                @unlink($temp);
            }
            $lock->release();
        }
    }

    /**
     * The 412 path: the rows are re-synced to what is on disk, and the refused edit is stored as a
     * conflict. A conflict that was being applied is superseded by the new one, which keeps its
     * base, so applicability is always judged from the version the user first edited.
     */
    private function refuse(Project $project, string $path, FileEdit $edit, string $etag, string $diskHash, ?Conflict $applying): never
    {
        $this->sync->syncLocked($edit->kind(), $path, $project->id);
        $applying?->update(['status' => Conflict::SUPERSEDED]);
        $conflict = Conflict::create([
            'path' => $path,
            'path_hash' => hash('sha256', $path),
            'operation' => $edit->operation(),
            'parameters' => get_object_vars($edit),
            'base_hash' => $applying->base_hash ?? $etag,
            'disk_hash' => $diskHash,
            'status' => Conflict::OPEN,
        ]);

        throw new HttpResponseException(response()->json([
            'message' => basename($path).' changed on disk since you opened the page, so nothing was written.',
            'conflict' => ConflictResource::make($conflict)->resolve(),
        ], 412));
    }

    /** Steps 3 and 4, with the parser's refusals turned into their status codes. */
    private function apply(FileEdit $edit, string $bytes): string
    {
        try {
            return $edit->apply($bytes, today(config()->string('markboard.timezone')));
        } catch (FileNotEditable $e) {
            abort(409, $e->getMessage());
        } catch (TaskNotFound|RowNotFound $e) {
            abort(404, $e->getMessage());
        } catch (EditRefused $e) {
            throw ValidationException::withMessages(['edit' => $e->getMessage()]);
        }
    }

    /** Step 5. A candidate that fails is a Markboard bug (500); a checker that can't answer is a 503. */
    private function check(SourceKind $kind, string $candidate): void
    {
        $parsed = $kind === SourceKind::Tasks ? TasksFile::parse($candidate) : PipelineFile::parse($candidate);
        abort_unless($parsed->isEditable(), 500, 'The edit would leave the file with format errors, so nothing was written. This is a Markboard bug.');
        if ($kind !== SourceKind::Tasks) {
            return;
        }

        $checker = $this->checker();
        $scratch = storage_path('app/check/'.bin2hex(random_bytes(8)).'.md');
        File::ensureDirectoryExists(dirname($scratch));
        file_put_contents($scratch, $candidate);
        $process = new Process(['python3', $checker, $scratch], timeout: self::CHECK_TIMEOUT_SECONDS);
        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            abort(503, 'The format checker timed out, so nothing was written.');
        } finally {
            @unlink($scratch);
        }

        if ($process->getExitCode() === 0 && $process->getErrorOutput() === '') {
            abort_unless($process->getOutput() === '', 500, 'The format checker rejected the edit, so nothing was written. This is a Markboard bug: '.trim($process->getOutput()));

            return;
        }
        abort(503, 'The format checker could not run, so nothing was written.');
    }

    /** The hub's own checker; a demo-kind hub without one uses the bundled copy, a real hub can't write. */
    private function checker(): string
    {
        $checker = $this->hub->path.'/scripts/check-tasks.py';
        if (is_file($checker)) {
            return $checker;
        }
        abort_unless($this->hub->isDemoKind(), 503, "The hub has no scripts/check-tasks.py, so TASKS.md files can't be written.");

        return resource_path('checker/check-tasks.py');
    }
}
