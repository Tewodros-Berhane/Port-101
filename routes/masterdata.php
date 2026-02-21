<?php

use App\Http\Controllers\Core\AddressesController;
use App\Http\Controllers\Core\AttachmentsController;
use App\Http\Controllers\Core\AuditLogsController;
use App\Http\Controllers\Core\CompanyInvitesController;
use App\Http\Controllers\Core\ContactsController;
use App\Http\Controllers\Core\CurrenciesController;
use App\Http\Controllers\Core\NotificationsController;
use App\Http\Controllers\Core\PartnersController;
use App\Http\Controllers\Core\PriceListsController;
use App\Http\Controllers\Core\ProductsController;
use App\Http\Controllers\Core\TaxesController;
use App\Http\Controllers\Core\UomsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'company'])
    ->prefix('core')
    ->name('core.')
    ->group(function () {
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
