<?php

namespace App\Actions;

use App\Night\WorkOrder;
use App\Sync\Hub;
use App\Sync\HubSync;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Writes Kai's answers into a work order that waits on him (docs/spec.md, Night, Actions): its
 * `## KAI'S ANSWERS` section, every other byte kept. Like the write path, under the file's lock,
 * refused if the file changed since the version the page showed, and replaced atomically.
 */
final readonly class AnswerOrder
{
    private const int LOCK_WAIT_SECONDS = 3;

    public function __construct(
        private Hub $hub,
        private HubSync $sync,
    ) {}

    public function handle(string $project, string $task, string $answers, string $etag): JsonResponse
    {
        $path = $this->hub->ordersPath("{$project}/{$task}.md");
        abort_unless(is_file($path), 404, "There's no work order {$project} {$task}.");
        $lock = $this->sync->lock($path);
        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            abort(503, 'The work order is being written by another request. Try again.', ['Retry-After' => '1']);
        }

        $temp = null;
        try {
            $bytes = (string) file_get_contents($path);
            $hash = hash('sha256', $bytes);
            if ($hash !== $etag) {
                return response()->json(['message' => 'The work order changed since the page showed it. Here is the current one.', 'etag' => $hash], 412);
            }
            abort_unless(WorkOrder::parse($bytes)->needsKai(), 409, 'This work order no longer waits on you.');

            $temp = dirname($path).'/.'.basename($path).'.markboard-'.bin2hex(random_bytes(6)).'.tmp';
            if (@file_put_contents($temp, WorkOrder::withAnswers($bytes, $answers)) === false || ! @chmod($temp, (int) fileperms($path) & 0777)) {
                throw new RuntimeException("Couldn't write a temp file next to {$path}.");
            }
            clearstatcache(true, $path);
            if (@hash_file('sha256', $path) !== $hash) {
                return response()->json(['message' => 'The work order changed while it was being saved. Nothing was written.', 'etag' => (string) @hash_file('sha256', $path)], 412);
            }
            if (! @rename($temp, $path)) {
                throw new RuntimeException("Couldn't replace {$path}.");
            }
            $temp = null;
            $etag = (string) hash_file('sha256', $path);

            return response()->json(['data' => ['project' => $project, 'task' => $task]])->setEtag($etag);
        } finally {
            if ($temp !== null && is_file($temp)) {
                @unlink($temp);
            }
            $lock->release();
        }
    }
}
