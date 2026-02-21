<?php

use App\Http\Controllers\Platform\AdminUsersController as PlatformAdminUsersController;
use App\Http\Controllers\Platform\CompaniesController as PlatformCompaniesController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\InvitesController as PlatformInvitesController;
use App\Http\Controllers\Platform\ReportsController as PlatformReportsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'superadmin'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        Route::get('dashboard', [PlatformDashboardController::class, 'index'])
            ->name('dashboard');
        Route::get('reports', [PlatformReportsController::class, 'index'])
            ->name('reports');
        Route::get('reports/export/{reportKey}', [PlatformReportsController::class, 'export'])
            ->name('reports.export');
        Route::post('reports/report-presets', [PlatformDashboardController::class, 'storeReportPreset'])
            ->name('reports.report-presets.store');
        Route::delete('reports/report-presets/{presetId}', [PlatformDashboardController::class, 'destroyReportPreset'])
            ->name('reports.report-presets.destroy');

        Route::get('governance', [PlatformDashboardController::class, 'governance'])
            ->name('governance');
        Route::get('dashboard/export/admin-actions', [PlatformDashboardController::class, 'exportAdminActions'])
            ->name('dashboard.export.admin-actions');
        Route::get('dashboard/export/delivery-trends', [PlatformDashboardController::class, 'exportDeliveryTrends'])
            ->name('dashboard.export.delivery-trends');
        Route::post('dashboard/report-presets', [PlatformDashboardController::class, 'storeReportPreset'])
            ->name('dashboard.report-presets.store');
        Route::delete('dashboard/report-presets/{presetId}', [PlatformDashboardController::class, 'destroyReportPreset'])
            ->name('dashboard.report-presets.destroy');
        Route::put('dashboard/preferences', [PlatformDashboardController::class, 'updatePreferences'])
            ->name('dashboard.preferences.update');
        Route::put('dashboard/report-delivery-schedule', [PlatformDashboardController::class, 'updateReportDeliverySchedule'])
            ->name('dashboard.report-delivery-schedule.update');
        Route::put('dashboard/notification-governance', [PlatformDashboardController::class, 'updateNotificationGovernance'])
            ->name('dashboard.notification-governance.update');

        Route::resource('companies', PlatformCompaniesController::class)
            ->only(['index', 'create', 'store', 'show', 'update']);
        Route::resource('admin-users', PlatformAdminUsersController::class)
            ->only(['index', 'create', 'store']);
        Route::resource('invites', PlatformInvitesController::class)
            ->only(['index', 'create', 'store', 'destroy']);
        Route::post('invites/{invite}/resend', [PlatformInvitesController::class, 'resend'])
            ->name('invites.resend');
        Route::post('invites/{invite}/retry-delivery', [PlatformInvitesController::class, 'retryDelivery'])
            ->name('invites.retry-delivery');
    });
