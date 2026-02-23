<?php

use App\Http\Controllers\Sales\SalesDashboardController;
use App\Http\Controllers\Sales\SalesLeadsController;
use App\Http\Controllers\Sales\SalesOrdersController;
use App\Http\Controllers\Sales\SalesQuotesController;
use Illuminate\Support\Facades\Route;

Route::get('sales', [SalesDashboardController::class, 'index'])
    ->name('modules.sales');

Route::prefix('sales')->name('sales.')->group(function () {
    Route::resource('leads', SalesLeadsController::class)
        ->except(['show']);

    Route::resource('quotes', SalesQuotesController::class)
        ->except(['show']);
    Route::post('quotes/{quote}/send', [SalesQuotesController::class, 'send'])
        ->name('quotes.send');
    Route::post('quotes/{quote}/approve', [SalesQuotesController::class, 'approve'])
        ->name('quotes.approve');
    Route::post('quotes/{quote}/reject', [SalesQuotesController::class, 'reject'])
        ->name('quotes.reject');
    Route::post('quotes/{quote}/confirm', [SalesQuotesController::class, 'confirm'])
        ->name('quotes.confirm');

    Route::resource('orders', SalesOrdersController::class)
        ->except(['show']);
    Route::post('orders/{order}/approve', [SalesOrdersController::class, 'approve'])
        ->name('orders.approve');
    Route::post('orders/{order}/confirm', [SalesOrdersController::class, 'confirm'])
        ->name('orders.confirm');
});
