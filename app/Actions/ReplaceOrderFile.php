<?php

namespace App\Actions;

use App\Sync\HubSync;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Replaces a file in the hub's orders/ folder the way the write path replaces a TASKS.md: under the
 * file's lock, refused if it changed since the version the page showed (checked when read and again
 * just before replacing), and swapped in atomically from a temp file beside it.
 */
final readonly class ReplaceOrderFile
{
    private const int LOCK_WAIT_SECONDS = 3;

    public function __construct(private HubSync $sync) {}

    /**
     * @param  string  $what  how messages name the file ("work order", "triage note")
     * @param  Closure(string): string  $change  the file's new bytes from its current ones; it may abort
     * @return string|JsonResponse the new etag, or the 412 response when the file changed
     */
    public function handle(string $path, string $etag, string $what, Closure $change): string|JsonResponse
    {
        $lock = $this->sync->lock($path);
        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            abort(503, "The {$what} is being written by another request. Try again.", ['Retry-After' => '1']);
        }

        $temp = null;
        try {
            $bytes = (string) file_get_contents($path);
            $hash = hash('sha256', $bytes);
            if ($hash !== $etag) {
                return response()->json(['message' => "The {$what} changed since the page showed it. Here is the current one.", 'etag' => $hash], 412);
            }
            $changed = $change($bytes);

            $temp = dirname($path).'/.'.basename($path).'.markboard-'.bin2hex(random_bytes(6)).'.tmp';
            if (@file_put_contents($temp, $changed) === false || ! @chmod($temp, (int) fileperms($path) & 0777)) {
                throw new RuntimeException("Couldn't write a temp file next to {$path}.");
            }
            clearstatcache(true, $path);
            if (@hash_file('sha256', $path) !== $hash) {
                return response()->json(['message' => "The {$what} changed while it was being saved. Nothing was written.", 'etag' => (string) @hash_file('sha256', $path)], 412);
            }
            if (! @rename($temp, $path)) {
                throw new RuntimeException("Couldn't replace {$path}.");
            }
            $temp = null;

            return (string) hash_file('sha256', $path);
        } finally {
            if ($temp !== null && is_file($temp)) {
                @unlink($temp);
            }
            $lock->release();
        }
    }
}
