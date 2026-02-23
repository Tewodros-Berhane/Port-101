<?php

use App\Http\Controllers\Purchasing\PurchaseOrdersController;
use App\Http\Controllers\Purchasing\PurchaseRfqsController;
use App\Http\Controllers\Purchasing\PurchasingDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('purchasing', [PurchasingDashboardController::class, 'index'])
    ->name('modules.purchasing');

Route::prefix('purchasing')->name('purchasing.')->group(function () {
    Route::resource('rfqs', PurchaseRfqsController::class)
        ->except(['show']);
    Route::post('rfqs/{rfq}/send', [PurchaseRfqsController::class, 'send'])
        ->name('rfqs.send');
    Route::post('rfqs/{rfq}/respond', [PurchaseRfqsController::class, 'respond'])
        ->name('rfqs.respond');
    Route::post('rfqs/{rfq}/select', [PurchaseRfqsController::class, 'select'])
        ->name('rfqs.select');

    Route::resource('orders', PurchaseOrdersController::class)
        ->except(['show']);
    Route::post('orders/{order}/approve', [PurchaseOrdersController::class, 'approve'])
        ->name('orders.approve');
    Route::post('orders/{order}/place', [PurchaseOrdersController::class, 'place'])
        ->name('orders.place');
    Route::post('orders/{order}/receive', [PurchaseOrdersController::class, 'receive'])
        ->name('orders.receive');
});
