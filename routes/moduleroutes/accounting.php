<?php

use App\Http\Controllers\Accounting\AccountingAccountsController;
use App\Http\Controllers\Accounting\AccountingBankReconciliationController;
use App\Http\Controllers\Accounting\AccountingDashboardController;
use App\Http\Controllers\Accounting\AccountingInvoicesController;
use App\Http\Controllers\Accounting\AccountingJournalsController;
use App\Http\Controllers\Accounting\AccountingLedgerController;
use App\Http\Controllers\Accounting\AccountingManualJournalsController;
use App\Http\Controllers\Accounting\AccountingPaymentsController;
use App\Http\Controllers\Accounting\AccountingStatementsController;
use Illuminate\Support\Facades\Route;

Route::get('accounting', [AccountingDashboardController::class, 'index'])
    ->name('modules.accounting');

Route::prefix('accounting')->name('accounting.')->group(function () {
    Route::get('accounts', [AccountingAccountsController::class, 'index'])
        ->name('accounts.index');
    Route::get('journals', [AccountingJournalsController::class, 'index'])
        ->name('journals.index');
    Route::get('ledger', [AccountingLedgerController::class, 'index'])
        ->name('ledger.index');
    Route::get('statements', [AccountingStatementsController::class, 'index'])
        ->name('statements.index');
    Route::get('bank-reconciliation', [AccountingBankReconciliationController::class, 'index'])
        ->name('bank-reconciliation.index');
    Route::post('bank-reconciliation/import', [AccountingBankReconciliationController::class, 'import'])
        ->name('bank-reconciliation.import');
    Route::post('bank-reconciliation', [AccountingBankReconciliationController::class, 'store'])
        ->name('bank-reconciliation.store');
    Route::post('bank-reconciliation/{batch}/unreconcile', [AccountingBankReconciliationController::class, 'unreconcile'])
        ->name('bank-reconciliation.unreconcile');
    Route::resource('manual-journals', AccountingManualJournalsController::class)
        ->except(['show']);
    Route::post('manual-journals/{manualJournal}/post', [AccountingManualJournalsController::class, 'post'])
        ->name('manual-journals.post');
    Route::post('manual-journals/{manualJournal}/reverse', [AccountingManualJournalsController::class, 'reverse'])
        ->name('manual-journals.reverse');

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
