<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskResource;
use App\Markdown\Section;
use App\Models\Project;
use App\Models\Task;
use App\Sync\SourceKind;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TaskListController extends Controller
{
    /** The page's groups, in the order they show (docs/spec.md, Tasks). */
    public const array GROUPS = ['waiting-you', 'in-progress', 'up-next', 'waiting-others'];

    /**
     * Every open task in an active project as one list, grouped and ordered as docs/spec.md's Tasks
     * section says, with each project's TASKS.md etag read in the same transaction.
     */
    public function __invoke(): Response
    {
        [$projects, $tasks] = DB::transaction(fn (): array => [
            Project::query()
                ->where('status', 'active')
                ->with('sourceFiles')
                ->orderBy('category_rank')
                ->orderBy('registry_order')
                ->get(),
            Task::query()
                ->join('projects', 'projects.id', '=', 'tasks.project_id')
                ->where('projects.status', 'active')
                ->whereNot('tasks.section', Section::Done)
                ->orderBy('projects.category_rank')
                ->orderBy('projects.registry_order')
                ->orderBy('tasks.position')
                ->select('tasks.*')
                ->get(),
        ]);

        return Inertia::render('Tasks/Index', [
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'etag' => self::etag($project),
            ])->all(),
            'tasks' => self::ordered($tasks),
        ]);
    }

    /** The project's TASKS.md hash when it can be edited, else null. */
    private static function etag(Project $project): ?string
    {
        $file = $project->sourceFiles->firstWhere('kind', SourceKind::Tasks);

        return $file !== null && $file->editable ? $file->hash : null;
    }

    /**
     * The tasks in group order; Waiting on and In progress oldest first by `since` or `started`,
     * undated ones after, and ties in the priority and file order the query gave.
     *
     * @param  Collection<int, Task>  $tasks
     * @return list<array{project_id: string, group: string, task: array<string, mixed>, age_from: string|null}>
     */
    private static function ordered(Collection $tasks): array
    {
        $items = $tasks->values()->map(fn (Task $task, int $order): array => [
            'order' => $order,
            'group' => self::group($task),
            'age_from' => ($task->section === Section::InProgress ? $task->started : ($task->section === Section::WaitingOn ? $task->since : null))?->toDateString(),
            'task' => $task,
        ])->all();

        usort($items, fn (array $a, array $b): int => [array_search($a['group'], self::GROUPS, true), $a['age_from'] === null, $a['age_from'], $a['order']]
            <=> [array_search($b['group'], self::GROUPS, true), $b['age_from'] === null, $b['age_from'], $b['order']]);

        return array_map(fn (array $item): array => [
            'project_id' => $item['task']->project_id,
            'group' => $item['group'],
            'task' => (new TaskResource($item['task']))->resolve(),
            'age_from' => $item['age_from'],
        ], $items);
    }

    private static function group(Task $task): string
    {
        return match ($task->section) {
            Section::InProgress => 'in-progress',
            Section::WaitingOn => in_array(mb_strtolower((string) $task->waiting), Task::KAI, true) ? 'waiting-you' : 'waiting-others',
            default => 'up-next',
        };
    }
}
