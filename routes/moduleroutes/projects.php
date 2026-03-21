<?php

use App\Http\Controllers\Projects\ProjectsController;
use App\Http\Controllers\Projects\ProjectsDashboardController;
use App\Http\Controllers\Projects\ProjectTasksController;
use Illuminate\Support\Facades\Route;

Route::get('projects', [ProjectsDashboardController::class, 'index'])
    ->name('modules.projects');

Route::prefix('projects')->name('projects.')->group(function () {
    Route::get('workspace', [ProjectsController::class, 'index'])
        ->name('index');
    Route::get('create', [ProjectsController::class, 'create'])
        ->name('create');
    Route::post('/', [ProjectsController::class, 'store'])
        ->name('store');

    Route::get('{project}/tasks/create', [ProjectTasksController::class, 'create'])
        ->name('tasks.create');
    Route::post('{project}/tasks', [ProjectTasksController::class, 'store'])
        ->name('tasks.store');
    Route::get('tasks/{task}/edit', [ProjectTasksController::class, 'edit'])
        ->name('tasks.edit');
    Route::put('tasks/{task}', [ProjectTasksController::class, 'update'])
        ->name('tasks.update');
    Route::delete('tasks/{task}', [ProjectTasksController::class, 'destroy'])
        ->name('tasks.destroy');

    Route::get('{project}', [ProjectsController::class, 'show'])
        ->name('show');
    Route::get('{project}/edit', [ProjectsController::class, 'edit'])
        ->name('edit');
    Route::put('{project}', [ProjectsController::class, 'update'])
        ->name('update');
    Route::delete('{project}', [ProjectsController::class, 'destroy'])
        ->name('destroy');
});
