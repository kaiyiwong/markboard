<?php

namespace App\Actions;

use App\Night\WorkOrder;
use App\Sync\Hub;
use Illuminate\Http\JsonResponse;

/**
 * Writes Kai's answers into a work order that waits on him (docs/spec.md, Night, Actions): its
 * `## KAI'S ANSWERS` section, every other byte kept, through ReplaceOrderFile's lock and hash checks.
 */
final readonly class AnswerOrder
{
    public function __construct(
        private Hub $hub,
        private ReplaceOrderFile $replace,
    ) {}

    public function handle(string $project, string $task, string $answers, string $etag): JsonResponse
    {
        $path = $this->hub->ordersPath("{$project}/{$task}.md");
        abort_unless(is_file($path), 404, "There's no work order {$project} {$task}.");

        $result = $this->replace->handle($path, $etag, 'work order', function (string $bytes) use ($answers): string {
            abort_unless(WorkOrder::parse($bytes)->needsKai(), 409, 'This work order no longer waits on you.');

            return WorkOrder::withAnswers($bytes, $answers);
        });

        return $result instanceof JsonResponse ? $result : response()->json(['data' => ['project' => $project, 'task' => $task]])->setEtag($result);
    }
}
