<?php

use App\Http\Controllers\Company\ModulesController as CompanyModulesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'company', 'company.workspace'])
    ->prefix('company')
    ->name('company.')
    ->group(function () {
        Route::get('sales', [CompanyModulesController::class, 'sales'])
            ->name('modules.sales');
        Route::get('inventory', [CompanyModulesController::class, 'inventory'])
            ->name('modules.inventory');
        Route::get('purchasing', [CompanyModulesController::class, 'purchasing'])
            ->name('modules.purchasing');
        Route::get('accounting', [CompanyModulesController::class, 'accounting'])
            ->name('modules.accounting');
        Route::get('reports', [CompanyModulesController::class, 'reports'])
            ->name('modules.reports');
        Route::get('approvals', [CompanyModulesController::class, 'approvals'])
            ->name('modules.approvals');
    });
