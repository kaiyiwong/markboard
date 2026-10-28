<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskResource;
use App\Markdown\Section;
use App\Models\Project;
use App\Models\Task;
use App\Review\ReviewCard;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReviewController extends Controller
{
    /** `waiting` values that mean Kai himself: the hub's KAI_NAMES (its scripts/check-review.py). */
    public const array KAI = ['kai', 'kai wong', 'kaiyi', 'kaiyi wong'];

    /**
     * Every task in an active project waiting on Kai, in priority order, each with its review card
     * when it has one. Cards aren't indexed: they are read from the project on each request.
     */
    public function show(): Response
    {
        $tasks = DB::transaction(fn () => Task::query()
            ->with('project')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->where('projects.status', 'active')
            ->where('tasks.section', Section::WaitingOn)
            ->whereIn(DB::raw('lower(tasks.waiting)'), self::KAI)
            ->orderBy('projects.category_rank')
            ->orderBy('projects.registry_order')
            ->orderBy('tasks.position')
            ->select('tasks.*')
            ->get());

        return Inertia::render('Review/Show', [
            'items' => $tasks->map(fn (Task $task): array => self::item($task))->all(),
        ]);
    }

    /** One image of a task's card, re-checked against the card's rules before it is sent. */
    public function image(Project $project, string $task, int $index): BinaryFileResponse
    {
        $path = ReviewCard::read($project->path, $task)?->imagePath($project->path, $index);
        abort_if($path === null, 404);

        return response()->file($path, ['Cache-Control' => 'no-store']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(Task $task): array
    {
        /** @var Project $project */
        $project = $task->project;
        $card = ReviewCard::read($project->path, $task->task_id);

        return [
            'project' => ['id' => $project->id, 'name' => $project->name],
            'task' => (new TaskResource($task))->resolve(),
            'since' => $task->since?->toDateString(),
            'card' => $card === null || $card->errors !== [] ? null : [
                'question' => $card->question,
                'answer' => $card->answer,
                'images' => array_map(fn (array $image, int $i): array => [
                    'label' => $image['label'],
                    'url' => "/review/{$project->id}/{$task->task_id}/images/{$i}",
                ], $card->images, array_keys($card->images)),
                'choices' => $card->choices,
            ],
            'card_errors' => $card->errors ?? [],
        ];
    }
}
