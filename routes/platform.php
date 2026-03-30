<?php

use App\Http\Controllers\Platform\AdminUsersController as PlatformAdminUsersController;
use App\Http\Controllers\Platform\CompaniesController as PlatformCompaniesController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\InvitesController as PlatformInvitesController;
use App\Http\Controllers\Platform\QueueHealthController as PlatformQueueHealthController;
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
        Route::get('operations/queue-health', [PlatformQueueHealthController::class, 'index'])
            ->name('queue-health');
        Route::post('operations/queue-health/failed-jobs/{failedJobId}/retry', [PlatformQueueHealthController::class, 'retryFailedJob'])
            ->name('queue-health.failed-jobs.retry');
        Route::post('operations/queue-health/failed-jobs/{failedJobId}/discard-poison', [PlatformQueueHealthController::class, 'discardFailedJobAsPoison'])
            ->name('queue-health.failed-jobs.discard-poison');
        Route::delete('operations/queue-health/failed-jobs/{failedJobId}', [PlatformQueueHealthController::class, 'forgetFailedJob'])
            ->name('queue-health.failed-jobs.destroy');
        Route::post('operations/queue-health/webhook-deliveries/{delivery}/retry', [PlatformQueueHealthController::class, 'retryWebhookDelivery'])
            ->name('queue-health.webhook-deliveries.retry');
        Route::post('operations/queue-health/report-exports/{reportExport}/retry', [PlatformQueueHealthController::class, 'retryReportExport'])
            ->name('queue-health.report-exports.retry');
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
        Route::put('dashboard/operational-alerting', [PlatformDashboardController::class, 'updateOperationalAlerting'])
            ->name('dashboard.operational-alerting.update');

        Route::resource('companies', PlatformCompaniesController::class)
            ->only(['index', 'create', 'store', 'show', 'update']);
        Route::resource('admin-users', PlatformAdminUsersController::class)
            ->only(['index', 'create', 'store']);
        Route::delete('invites/{invite}', [PlatformInvitesController::class, 'destroy'])
            ->whereUuid('invite')
            ->name('invites.destroy');
        Route::post('invites/{invite}/resend', [PlatformInvitesController::class, 'resend'])
            ->whereUuid('invite')
            ->name('invites.resend');
        Route::post('invites/{invite}/retry-delivery', [PlatformInvitesController::class, 'retryDelivery'])
            ->whereUuid('invite')
            ->name('invites.retry-delivery');
    });
