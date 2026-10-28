<?php

use App\Http\Controllers\Api\V1\ConflictController;
use App\Http\Controllers\Api\V1\NightController;
use App\Http\Controllers\Api\V1\PipelineRowController;
use App\Http\Controllers\Api\V1\ReviewController;
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

Route::post('review/{project}/{task}/answer', [ReviewController::class, 'answer'])->name('api.review.answer');

Route::post('conflicts/{conflict}/apply', [ConflictController::class, 'apply'])->name('api.conflicts.apply');
Route::post('conflicts/{conflict}/discard', [ConflictController::class, 'discard'])->name('api.conflicts.discard');

/*
| The night run (docs/spec.md, Night). {order} is a night task ID as the runner wrote it (T0085,
| 0048-action-errors): a file name in orders/<project>/, so it can never hold a slash or start with a dot.
*/
Route::prefix('night')->name('api.night.')->group(function () {
    Route::pattern('order', '[A-Za-z0-9][A-Za-z0-9._-]{0,99}');
    Route::put('orders/{project}/{order}/answers', [NightController::class, 'answers'])->name('answers');
    Route::post('branches/{project}/{order}/merge', [NightController::class, 'merge'])->name('merge');
    Route::post('branches/{project}/{order}/discard', [NightController::class, 'discard'])->name('discard');
    Route::post('triage/{date}/keep', [NightController::class, 'keep'])->where('date', '\d{4}-\d{2}-\d{2}')->name('keep');
    Route::put('stop', [NightController::class, 'stop'])->name('stop');
});
