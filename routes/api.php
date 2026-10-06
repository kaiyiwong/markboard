<?php

use App\Http\Controllers\Api\V1\ConflictController;
use App\Http\Controllers\Api\V1\PipelineRowController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

/*
| The JSON API under /api/v1 (docs/spec.md, API): one endpoint per operation. Every write but
| Discard needs If-Match with the etag of the file version the user saw.
*/

Route::pattern('task', 'T[0-9]+');

Route::prefix('projects/{project}')->name('api.')->group(function () {
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::post('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
    Route::put('tasks/{task}/position', [TaskController::class, 'reorder'])->name('tasks.reorder');
    Route::post('tasks/{task}/tick', [TaskController::class, 'tick'])->name('tasks.tick');
    Route::post('tasks/{task}/cancel', [TaskController::class, 'cancel'])->name('tasks.cancel');
    Route::post('tasks/{task}/undo', [TaskController::class, 'undo'])->name('tasks.undo');

    Route::post('pipeline/rows', [PipelineRowController::class, 'store'])->name('pipeline.store');
    Route::patch('pipeline/rows/{row}', [PipelineRowController::class, 'update'])->whereNumber('row')->name('pipeline.update');
});

Route::post('conflicts/{conflict}/apply', [ConflictController::class, 'apply'])->name('api.conflicts.apply');
Route::post('conflicts/{conflict}/discard', [ConflictController::class, 'discard'])->name('api.conflicts.discard');
