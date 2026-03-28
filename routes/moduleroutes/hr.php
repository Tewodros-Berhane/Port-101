<?php

use App\Http\Controllers\Hr\HrDashboardController;
use App\Http\Controllers\Hr\HrEmployeeContractsController;
use App\Http\Controllers\Hr\HrEmployeeDocumentsController;
use App\Http\Controllers\Hr\HrEmployeesController;
use Illuminate\Support\Facades\Route;

Route::get('hr', [HrDashboardController::class, 'index'])
    ->name('modules.hr');

Route::prefix('hr')->name('hr.')->group(function () {
    Route::get('employees', [HrEmployeesController::class, 'index'])
        ->name('employees.index');
    Route::get('employees/create', [HrEmployeesController::class, 'create'])
        ->name('employees.create');
    Route::post('employees', [HrEmployeesController::class, 'store'])
        ->name('employees.store');
    Route::get('employees/{employee}', [HrEmployeesController::class, 'show'])
        ->name('employees.show');
    Route::get('employees/{employee}/edit', [HrEmployeesController::class, 'edit'])
        ->name('employees.edit');
    Route::put('employees/{employee}', [HrEmployeesController::class, 'update'])
        ->name('employees.update');
    Route::delete('employees/{employee}', [HrEmployeesController::class, 'destroy'])
        ->name('employees.destroy');

    Route::post('employees/{employee}/contracts', [HrEmployeeContractsController::class, 'store'])
        ->name('contracts.store');
    Route::put('contracts/{contract}', [HrEmployeeContractsController::class, 'update'])
        ->name('contracts.update');
    Route::delete('contracts/{contract}', [HrEmployeeContractsController::class, 'destroy'])
        ->name('contracts.destroy');

    Route::post('employees/{employee}/documents', [HrEmployeeDocumentsController::class, 'store'])
        ->name('documents.store');
    Route::get('documents/{document}/download', [HrEmployeeDocumentsController::class, 'download'])
        ->name('documents.download');
    Route::delete('documents/{document}', [HrEmployeeDocumentsController::class, 'destroy'])
        ->name('documents.destroy');
});
