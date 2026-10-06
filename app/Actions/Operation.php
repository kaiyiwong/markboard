<?php

namespace App\Actions;

/**
 * The edits a conflict can record, by the name stored in `conflicts.operation`.
 */
enum Operation: string
{
    case AddTask = 'add';
    case EditTask = 'edit';
    case MoveTask = 'move';
    case ReorderTask = 'reorder';
    case TickTask = 'tick';
    case CancelTask = 'cancel';
    case UndoTask = 'undo';
    case AddPipelineRow = 'add-row';
    case EditPipelineRow = 'edit-row';

    /**
     * Rebuilds the edit from the parameters its conflict stored.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function edit(array $parameters): FileEdit
    {
        $class = match ($this) {
            self::AddTask => AddTask::class,
            self::EditTask => EditTask::class,
            self::MoveTask => MoveTask::class,
            self::ReorderTask => ReorderTask::class,
            self::TickTask => TickTask::class,
            self::CancelTask => CancelTask::class,
            self::UndoTask => UndoTask::class,
            self::AddPipelineRow => AddPipelineRow::class,
            self::EditPipelineRow => EditPipelineRow::class,
        };

        return new $class(...$parameters);
    }
}
