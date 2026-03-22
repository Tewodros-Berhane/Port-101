<?php

use App\Http\Controllers\Projects\ProjectBillablesController;
use App\Http\Controllers\Projects\ProjectMilestonesController;
use App\Http\Controllers\Projects\ProjectRecurringBillingController;
use App\Http\Controllers\Projects\ProjectsController;
use App\Http\Controllers\Projects\ProjectsDashboardController;
use App\Http\Controllers\Projects\ProjectTasksController;
use App\Http\Controllers\Projects\ProjectTimesheetsController;
use Illuminate\Support\Facades\Route;

Route::get('projects', [ProjectsDashboardController::class, 'index'])
    ->name('modules.projects');

Route::prefix('projects')->name('projects.')->group(function () {
    Route::get('workspace', [ProjectsController::class, 'index'])
        ->name('index');
    Route::get('billables', [ProjectBillablesController::class, 'index'])
        ->name('billables.index');
    Route::get('recurring-billing', [ProjectRecurringBillingController::class, 'index'])
        ->name('recurring-billing.index');
    Route::get('recurring-billing/create', [ProjectRecurringBillingController::class, 'create'])
        ->name('recurring-billing.create');
    Route::post('recurring-billing', [ProjectRecurringBillingController::class, 'store'])
        ->name('recurring-billing.store');
    Route::get('recurring-billing/{recurringBilling}/edit', [ProjectRecurringBillingController::class, 'edit'])
        ->name('recurring-billing.edit');
    Route::put('recurring-billing/{recurringBilling}', [ProjectRecurringBillingController::class, 'update'])
        ->name('recurring-billing.update');
    Route::post('recurring-billing/{recurringBilling}/activate', [ProjectRecurringBillingController::class, 'activate'])
        ->name('recurring-billing.activate');
    Route::post('recurring-billing/{recurringBilling}/pause', [ProjectRecurringBillingController::class, 'pause'])
        ->name('recurring-billing.pause');
    Route::post('recurring-billing/{recurringBilling}/cancel', [ProjectRecurringBillingController::class, 'cancel'])
        ->name('recurring-billing.cancel');
    Route::post('recurring-billing/{recurringBilling}/run-now', [ProjectRecurringBillingController::class, 'runNow'])
        ->name('recurring-billing.run-now');
    Route::post('billables/invoice-drafts', [ProjectBillablesController::class, 'createInvoiceDrafts'])
        ->name('billables.invoice-drafts.store');
    Route::post('billables/{billable}/approve', [ProjectBillablesController::class, 'approve'])
        ->name('billables.approve');
    Route::post('billables/{billable}/reject', [ProjectBillablesController::class, 'reject'])
        ->name('billables.reject');
    Route::post('billables/{billable}/cancel', [ProjectBillablesController::class, 'cancel'])
        ->name('billables.cancel');
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

    Route::get('{project}/timesheets/create', [ProjectTimesheetsController::class, 'create'])
        ->name('timesheets.create');
    Route::post('{project}/timesheets', [ProjectTimesheetsController::class, 'store'])
        ->name('timesheets.store');
    Route::get('timesheets/{timesheet}/edit', [ProjectTimesheetsController::class, 'edit'])
        ->name('timesheets.edit');
    Route::put('timesheets/{timesheet}', [ProjectTimesheetsController::class, 'update'])
        ->name('timesheets.update');
    Route::post('timesheets/{timesheet}/submit', [ProjectTimesheetsController::class, 'submit'])
        ->name('timesheets.submit');
    Route::post('timesheets/{timesheet}/approve', [ProjectTimesheetsController::class, 'approve'])
        ->name('timesheets.approve');
    Route::post('timesheets/{timesheet}/reject', [ProjectTimesheetsController::class, 'reject'])
        ->name('timesheets.reject');
    Route::delete('timesheets/{timesheet}', [ProjectTimesheetsController::class, 'destroy'])
        ->name('timesheets.destroy');

    Route::get('{project}/milestones/create', [ProjectMilestonesController::class, 'create'])
        ->name('milestones.create');
    Route::post('{project}/milestones', [ProjectMilestonesController::class, 'store'])
        ->name('milestones.store');
    Route::get('milestones/{milestone}/edit', [ProjectMilestonesController::class, 'edit'])
        ->name('milestones.edit');
    Route::put('milestones/{milestone}', [ProjectMilestonesController::class, 'update'])
        ->name('milestones.update');
    Route::delete('milestones/{milestone}', [ProjectMilestonesController::class, 'destroy'])
        ->name('milestones.destroy');

    Route::get('{project}', [ProjectsController::class, 'show'])
        ->name('show');
    Route::get('{project}/edit', [ProjectsController::class, 'edit'])
        ->name('edit');
    Route::put('{project}', [ProjectsController::class, 'update'])
        ->name('update');
    Route::delete('{project}', [ProjectsController::class, 'destroy'])
        ->name('destroy');
});
