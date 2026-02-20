<?php

use App\Http\Controllers\Company\ModulesController as CompanyModulesController;
use App\Http\Controllers\Company\RolesController as CompanyRolesController;
use App\Http\Controllers\Company\SettingsController as CompanySettingsController;
use App\Http\Controllers\Company\UsersController as CompanyUsersController;
use App\Http\Controllers\Core\AddressesController;
use App\Http\Controllers\Core\AttachmentsController;
use App\Http\Controllers\Core\AuditLogsController;
use App\Http\Controllers\Core\CompanyInvitesController;
use App\Http\Controllers\Core\CompanySwitchController;
use App\Http\Controllers\Core\ContactsController;
use App\Http\Controllers\Core\CurrenciesController;
use App\Http\Controllers\Core\NotificationsController;
use App\Http\Controllers\Core\PartnersController;
use App\Http\Controllers\Core\PriceListsController;
use App\Http\Controllers\Core\ProductsController;
use App\Http\Controllers\Core\TaxesController;
use App\Http\Controllers\Core\UomsController;
use App\Http\Controllers\InviteAcceptanceController;
use App\Http\Controllers\Platform\AdminUsersController as PlatformAdminUsersController;
use App\Http\Controllers\Platform\CompaniesController as PlatformCompaniesController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\InvitesController as PlatformInvitesController;
use App\Http\Controllers\Platform\ReportsController as PlatformReportsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
    ]);
})->name('home');

Route::get('invites/{token}', [InviteAcceptanceController::class, 'show'])
    ->name('invites.accept.show');
Route::post('invites/{token}/accept', [InviteAcceptanceController::class, 'store'])
    ->middleware('guest')
    ->name('invites.accept.store');

Route::middleware(['auth'])->group(function () {
    Route::get('company/inactive', function (Request $request) {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->is_super_admin) {
            return redirect()->route('platform.dashboard');
        }

        $activeCompany = $user->companies()
            ->where('companies.is_active', true)
            ->orderBy('companies.name')
            ->first();

        if ($activeCompany) {
            $user->forceFill([
                'current_company_id' => $activeCompany->id,
            ])->save();

            return redirect()->route('company.dashboard');
        }

        return Inertia::render('company/inactive');
    })->name('company.inactive');

    Route::post('company/switch', [CompanySwitchController::class, 'update'])
        ->name('company.switch');
});

Route::middleware(['auth', 'verified', 'company'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $user = $request->user();

        if ($user && $user->is_super_admin) {
            return redirect()->route('platform.dashboard');
        }

        return redirect()->route('company.dashboard');
    })->name('dashboard');

    Route::get('company/dashboard', function () {
        return Inertia::render('company/dashboard');
    })->name('company.dashboard');

    Route::prefix('company')->name('company.')->group(function () {
        Route::get('settings', [CompanySettingsController::class, 'show'])
            ->name('settings.show');
        Route::put('settings', [CompanySettingsController::class, 'update'])
            ->name('settings.update');

        Route::get('users', [CompanyUsersController::class, 'index'])
            ->name('users.index');
        Route::put('users/{membership}/role', [CompanyUsersController::class, 'updateRole'])
            ->name('users.update-role');
        Route::get('roles', [CompanyRolesController::class, 'index'])
            ->name('roles.index');

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

    Route::prefix('core')->name('core.')->group(function () {
        Route::get('audit-logs', [AuditLogsController::class, 'index'])
            ->name('audit-logs.index');
        Route::get('audit-logs/export', [AuditLogsController::class, 'export'])
            ->name('audit-logs.export');
        Route::delete('audit-logs/{auditLog}', [AuditLogsController::class, 'destroy'])
            ->name('audit-logs.destroy');
        Route::resource('partners', PartnersController::class)
            ->except(['show']);
        Route::resource('addresses', AddressesController::class)
            ->except(['show']);
        Route::resource('contacts', ContactsController::class)
            ->except(['show']);
        Route::resource('products', ProductsController::class)
            ->except(['show']);
        Route::resource('taxes', TaxesController::class)
            ->except(['show']);
        Route::resource('currencies', CurrenciesController::class)
            ->except(['show']);
        Route::resource('uoms', UomsController::class)
            ->except(['show']);
        Route::resource('price-lists', PriceListsController::class)
            ->except(['show']);
        Route::post('attachments', [AttachmentsController::class, 'store'])
            ->name('attachments.store');
        Route::get('attachments/{attachment}/download', [AttachmentsController::class, 'download'])
            ->name('attachments.download');
        Route::delete('attachments/{attachment}', [AttachmentsController::class, 'destroy'])
            ->name('attachments.destroy');
        Route::get('notifications', [NotificationsController::class, 'index'])
            ->name('notifications.index');
        Route::post('notifications/mark-all-read', [NotificationsController::class, 'markAllRead'])
            ->name('notifications.mark-all-read');
        Route::post('notifications/{notificationId}/read', [NotificationsController::class, 'markRead'])
            ->name('notifications.mark-read');
        Route::delete('notifications/{notificationId}', [NotificationsController::class, 'destroy'])
            ->name('notifications.destroy');
        Route::resource('invites', CompanyInvitesController::class)
            ->only(['index', 'create', 'store', 'destroy']);
        Route::post('invites/{invite}/resend', [CompanyInvitesController::class, 'resend'])
            ->name('invites.resend');
        Route::post('invites/{invite}/retry-delivery', [CompanyInvitesController::class, 'retryDelivery'])
            ->name('invites.retry-delivery');
    });
});

Route::middleware(['auth', 'verified', 'superadmin'])->prefix('platform')->name('platform.')->group(function () {
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

require __DIR__.'/settings.php';
