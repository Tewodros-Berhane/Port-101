<?php

use App\Http\Controllers\Company\ModulesController as CompanyModulesController;
use Illuminate\Support\Facades\Route;

Route::get('purchasing', [CompanyModulesController::class, 'purchasing'])
    ->name('modules.purchasing');
Route::get('accounting', [CompanyModulesController::class, 'accounting'])
    ->name('modules.accounting');
Route::get('reports', [CompanyModulesController::class, 'reports'])
    ->name('modules.reports');
Route::get('approvals', [CompanyModulesController::class, 'approvals'])
    ->name('modules.approvals');
