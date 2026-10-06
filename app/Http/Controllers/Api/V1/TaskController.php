<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AddTask;
use App\Actions\CancelTask;
use App\Actions\EditTask;
use App\Actions\MoveTask;
use App\Actions\ReorderTask;
use App\Actions\TickTask;
use App\Actions\UndoTask;
use App\Actions\WriteFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddTaskRequest;
use App\Http\Requests\EditTaskRequest;
use App\Http\Requests\FileEditRequest;
use App\Http\Requests\MoveTaskRequest;
use App\Http\Requests\ReorderTaskRequest;
use App\Http\Requests\TickTaskRequest;
use App\Http\Requests\UndoTaskRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * The task operations on a project's TASKS.md, one endpoint each. A task is addressed by its ID
 * (`T12`), looked up by number in the file as read, so `T9` finds `T09`.
 */
class TaskController extends Controller
{
    public function __construct(
        private readonly WriteFile $write,
    ) {}

    public function store(AddTaskRequest $request, Project $project): JsonResponse
    {
        return $this->write->write($project, new AddTask(...$request->validated()), $request->etag());
    }

    public function update(EditTaskRequest $request, Project $project, string $task): JsonResponse
    {
        return $this->write->write($project, new EditTask(self::number($task), $request->validated()), $request->etag());
    }

    public function move(MoveTaskRequest $request, Project $project, string $task): JsonResponse
    {
        return $this->write->write($project, new MoveTask(self::number($task), ...$request->validated()), $request->etag());
    }

    public function reorder(ReorderTaskRequest $request, Project $project, string $task): JsonResponse
    {
        return $this->write->write($project, new ReorderTask(self::number($task), $request->integer('position')), $request->etag());
    }

    public function tick(TickTaskRequest $request, Project $project, string $task): JsonResponse
    {
        return $this->write->write($project, new TickTask(self::number($task), ...$request->validated()), $request->etag());
    }

    public function cancel(FileEditRequest $request, Project $project, string $task): JsonResponse
    {
        return $this->write->write($project, new CancelTask(self::number($task)), $request->etag());
    }

    public function undo(UndoTaskRequest $request, Project $project, string $task): JsonResponse
    {
        return $this->write->write($project, new UndoTask(self::number($task), ...$request->validated()), $request->etag());
    }

    private static function number(string $task): int
    {
        return (int) substr($task, 1);
    }
}
