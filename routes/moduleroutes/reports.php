<?php

use App\Http\Controllers\Reports\CompanyReportsController;
use Illuminate\Support\Facades\Route;

Route::get('reports', [CompanyReportsController::class, 'index'])
    ->name('modules.reports');

Route::get('reports/export/{reportKey}', [CompanyReportsController::class, 'export'])
    ->name('reports.export');

Route::post('reports/presets', [CompanyReportsController::class, 'storePreset'])
    ->name('reports.presets.store');

Route::delete('reports/presets/{presetId}', [CompanyReportsController::class, 'destroyPreset'])
    ->name('reports.presets.destroy');

Route::put('reports/delivery-schedule', [CompanyReportsController::class, 'updateDeliverySchedule'])
    ->name('reports.delivery-schedule.update');
