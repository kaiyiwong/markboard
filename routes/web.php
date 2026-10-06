<?php

use App\Http\Controllers\BriefController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProjectController;
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
