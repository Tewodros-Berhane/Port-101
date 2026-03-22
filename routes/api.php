<?php

use App\Http\Controllers\Api\V1\PartnersController as ApiPartnersController;
use App\Http\Controllers\Api\V1\ProductsController as ApiProductsController;
use App\Http\Controllers\Api\V1\ProjectsController as ApiProjectsController;
use App\Http\Controllers\Api\V1\ProjectTasksController as ApiProjectTasksController;
use App\Http\Controllers\Api\V1\ProjectTimesheetsController as ApiProjectTimesheetsController;
use App\Http\Controllers\Api\V1\SettingsController as ApiSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'version' => 'v1',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::middleware(['auth:sanctum', 'company.context', 'company'])->group(function () {
        Route::apiResource('partners', ApiPartnersController::class);
        Route::apiResource('products', ApiProductsController::class);
        Route::apiResource('projects', ApiProjectsController::class);

        Route::prefix('projects')->group(function () {
            Route::get('{project}/tasks', [ApiProjectTasksController::class, 'index']);
            Route::post('{project}/tasks', [ApiProjectTasksController::class, 'store']);
            Route::get('tasks/{task}', [ApiProjectTasksController::class, 'show']);
            Route::match(['put', 'patch'], 'tasks/{task}', [ApiProjectTasksController::class, 'update']);
            Route::delete('tasks/{task}', [ApiProjectTasksController::class, 'destroy']);

            Route::get('{project}/timesheets', [ApiProjectTimesheetsController::class, 'index']);
            Route::post('{project}/timesheets', [ApiProjectTimesheetsController::class, 'store']);
            Route::get('timesheets/{timesheet}', [ApiProjectTimesheetsController::class, 'show']);
            Route::match(['put', 'patch'], 'timesheets/{timesheet}', [ApiProjectTimesheetsController::class, 'update']);
            Route::post('timesheets/{timesheet}/submit', [ApiProjectTimesheetsController::class, 'submit']);
            Route::post('timesheets/{timesheet}/approve', [ApiProjectTimesheetsController::class, 'approve']);
            Route::post('timesheets/{timesheet}/reject', [ApiProjectTimesheetsController::class, 'reject']);
            Route::delete('timesheets/{timesheet}', [ApiProjectTimesheetsController::class, 'destroy']);
        });

        Route::get('settings', [ApiSettingsController::class, 'index']);
        Route::put('settings', [ApiSettingsController::class, 'update']);
    });
});
