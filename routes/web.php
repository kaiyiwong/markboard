<?php

use App\Http\Controllers\BriefController;
use App\Http\Controllers\NightController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
Route::get('/projects/{project}/tasks/{task}', [ProjectController::class, 'show'])
    ->where('task', 'T[0-9]+')
    ->name('projects.task');
Route::get('/pipeline', PipelineController::class)->name('pipeline.index');
Route::get('/brief', [BriefController::class, 'show'])->name('brief.today');
Route::get('/brief/{date}', [BriefController::class, 'show'])
    ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
    ->name('brief.show');
Route::get('/review', [ReviewController::class, 'show'])->name('review.show');
Route::get('/review/{project}/{task}/images/{index}', [ReviewController::class, 'image'])
    ->where(['task' => 'T[0-9]+', 'index' => '[0-9]+'])
    ->name('review.image');
Route::get('/night', [NightController::class, 'show'])->name('night.latest');
Route::get('/night/{date}', [NightController::class, 'show'])
    ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
    ->name('night.show');
