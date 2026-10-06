<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Http\Resources\SourceFileResource;
use App\Http\Resources\TaskResource;
use App\Markdown\Registry;
use App\Markdown\Section;
use App\Models\Project;
use App\Models\SourceFile;
use App\Models\Task;
use App\Sync\SourceKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Every project: active ones in priority order, then paused, then done. Filters and search come
     * from the query string, so a filtered view has its own URL.
     */
    public function index(Request $request): Response
    {
        $category = in_array($request->query('category'), Registry::CATEGORIES, true) ? $request->query('category') : null;
        $status = in_array($request->query('status'), Registry::STATUSES, true) ? $request->query('status') : null;
        $query = trim($request->string('q')->toString());

        return Inertia::render('Projects/Index', DB::transaction(fn (): array => [
            'projects' => ProjectResource::collection(
                Project::query()
                    ->when($category, fn (Builder $projects, string $category) => $projects->where('category', $category))
                    ->when($status, fn (Builder $projects, string $status) => $projects->where('status', $status))
                    ->withCount([
                        'tasks as up_next' => fn (Builder $tasks) => $tasks->where('section', Section::UpNext),
                        'tasks as in_progress' => fn (Builder $tasks) => $tasks->where('section', Section::InProgress),
                        'tasks as waiting_on' => fn (Builder $tasks) => $tasks->where('section', Section::WaitingOn),
                    ])
                    ->withMin(['tasks as next_due' => fn (Builder $tasks) => $tasks->whereNot('section', Section::Done)], 'due')
                    ->with('sourceFiles')
                    ->orderByRaw("case status when 'active' then 0 when 'paused' then 1 else 2 end")
                    ->orderBy('category_rank')
                    ->orderBy('registry_order')
                    ->get(),
            )->resolve(),
            'registryErrors' => SourceFile::where('kind', SourceKind::Registry)->value('errors') ?? [],
            'hubSyncErrors' => SourceFile::whereNull('project_id')->whereNotNull('sync_error')->pluck('sync_error'),
            'filters' => ['category' => $category, 'status' => $status, 'q' => $query],
            'categories' => Registry::CATEGORIES,
            'statuses' => Registry::STATUSES,
            'results' => $query === '' ? null : Task::search($query)
                ->with('project:id,name')
                ->orderBy('project_id')->orderBy('number')
                ->limit(50)
                ->get()
                ->map(fn (Task $task): array => [
                    'project_id' => $task->project_id,
                    'project_name' => $task->project->name,
                    'task_id' => $task->task_id,
                    'title' => $task->title,
                    'section' => $task->section,
                ]),
        ]));
    }

    /**
     * One project's tasks by section, in file order. The task route highlights one task; a task
     * number that isn't in the file is a 404, like the API's.
     */
    public function show(Project $project, ?string $task = null): Response
    {
        return Inertia::render('Projects/Show', DB::transaction(function () use ($project, $task): array {
            $tasks = $project->tasks()->orderBy('position')->get();
            $highlight = $task === null ? null : $tasks->firstWhere('number', (int) substr($task, 1));
            abort_if($task !== null && $highlight === null, 404);
            $file = $project->sourceFiles()->where('kind', SourceKind::Tasks)->first();

            return [
                'project' => ProjectResource::make($project)->resolve(),
                'file' => $file === null ? null : SourceFileResource::make($file)->resolve(),
                'sections' => array_map(fn (Section $section): array => [
                    'name' => $section,
                    'tasks' => TaskResource::collection($tasks->where('section', $section)->values())->resolve(),
                ], Section::cases()),
                'highlight' => $highlight?->task_id,
            ];
        }));
    }
}
