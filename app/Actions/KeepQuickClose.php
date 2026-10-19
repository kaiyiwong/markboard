<?php

namespace App\Actions;

use App\Night\Triage;
use App\Sync\Hub;
use Illuminate\Http\JsonResponse;

/**
 * Keeps a task the chief of staff suggested closing (docs/spec.md, Night, Actions): a `kept: <date>`
 * line under it in the triage note's `## Quick closes`, so the next triage stops suggesting it. Every
 * other byte is kept, through ReplaceOrderFile's lock and hash checks.
 */
final readonly class KeepQuickClose
{
    public function __construct(
        private Hub $hub,
        private ReplaceOrderFile $replace,
    ) {}

    public function handle(string $date, string $project, string $task, string $etag): JsonResponse
    {
        $path = $this->hub->ordersPath("triage-{$date}.md");
        abort_unless(is_file($path), 404, "There's no triage note for {$date}.");
        $today = now()->setTimezone((string) config('markboard.timezone'))->toDateString();

        $result = $this->replace->handle($path, $etag, 'triage note', function (string $bytes) use ($project, $task, $today): string {
            $kept = Triage::withKept($bytes, $project, $task, $today);
            abort_if($kept === null, 409, "The triage note has no open quick close for {$project} {$task}.");

            return $kept;
        });

        return $result instanceof JsonResponse ? $result : response()->json(['data' => ['project' => $project, 'task' => $task, 'kept' => $today]])->setEtag($result);
    }
}
