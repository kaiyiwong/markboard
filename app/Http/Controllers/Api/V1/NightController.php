<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AnswerOrder;
use App\Actions\KeepQuickClose;
use App\Actions\NightBranch;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerOrderRequest;
use App\Http\Requests\KeepQuickCloseRequest;
use App\Http\Requests\NightBranchRequest;
use App\Http\Requests\NightStopRequest;
use App\Models\Project;
use App\Sync\Hub;
use Illuminate\Http\JsonResponse;

/**
 * The Night page's actions (docs/spec.md, Night, Actions): answer a work order's questions, merge or
 * discard a night branch, keep a task the triage suggested closing, and skip a night. A night task is addressed by its ID as the runner wrote
 * it (`T0085`, `0048-action-errors`), so `{order}` is not `{task}`.
 */
class NightController extends Controller
{
    public function answers(AnswerOrderRequest $request, Project $project, string $order, AnswerOrder $answer): JsonResponse
    {
        return $answer->handle($project->id, $order, (string) $request->validated('answers'), $request->etag());
    }

    public function merge(NightBranchRequest $request, Project $project, string $order, NightBranch $branch): JsonResponse
    {
        return $branch->merge($project, $order, (string) $request->validated('head'));
    }

    public function discard(NightBranchRequest $request, Project $project, string $order, NightBranch $branch): JsonResponse
    {
        return $branch->discard($project, $order, (string) $request->validated('head'));
    }

    public function keep(KeepQuickCloseRequest $request, string $date, KeepQuickClose $keep): JsonResponse
    {
        return $keep->handle($date, (string) $request->validated('project'), (string) $request->validated('task'), $request->etag());
    }

    /** STOP is the runner's own switch: while it's there, a run starts nothing. */
    public function stop(NightStopRequest $request, Hub $hub): JsonResponse
    {
        $path = $hub->ordersPath('STOP');
        abort_unless(is_dir(dirname($path)), 409, 'The hub has no orders folder, so there is no night run to stop.');
        if ($request->boolean('stop')) {
            touch($path);
        } elseif (is_file($path)) {
            unlink($path);
        }

        return response()->json(['data' => ['stopped' => is_file($path)]]);
    }
}
