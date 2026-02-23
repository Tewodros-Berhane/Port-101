<?php

use App\Http\Controllers\Accounting\AccountingDashboardController;
use App\Http\Controllers\Accounting\AccountingInvoicesController;
use App\Http\Controllers\Accounting\AccountingPaymentsController;
use Illuminate\Support\Facades\Route;

Route::get('accounting', [AccountingDashboardController::class, 'index'])
    ->name('modules.accounting');

Route::prefix('accounting')->name('accounting.')->group(function () {
    Route::resource('invoices', AccountingInvoicesController::class)
        ->except(['show']);
    Route::post('invoices/{invoice}/post', [AccountingInvoicesController::class, 'post'])
        ->name('invoices.post');
    Route::post('invoices/{invoice}/cancel', [AccountingInvoicesController::class, 'cancel'])
        ->name('invoices.cancel');

    Route::resource('payments', AccountingPaymentsController::class)
        ->except(['show']);
    Route::post('payments/{payment}/post', [AccountingPaymentsController::class, 'post'])
        ->name('payments.post');
    Route::post('payments/{payment}/reconcile', [AccountingPaymentsController::class, 'reconcile'])
        ->name('payments.reconcile');
    Route::post('payments/{payment}/reverse', [AccountingPaymentsController::class, 'reverse'])
        ->name('payments.reverse');
});
