<?php

use App\Http\Controllers\Api\V1\AccountingInvoicesController as ApiAccountingInvoicesController;
use App\Http\Controllers\Api\V1\AccountingPaymentsController as ApiAccountingPaymentsController;
use App\Http\Controllers\Api\V1\InventoryStockBalancesController as ApiInventoryStockBalancesController;
use App\Http\Controllers\Api\V1\InventoryStockMovesController as ApiInventoryStockMovesController;
use App\Http\Controllers\Api\V1\PartnersController as ApiPartnersController;
use App\Http\Controllers\Api\V1\ProductsController as ApiProductsController;
use App\Http\Controllers\Api\V1\ProjectsController as ApiProjectsController;
use App\Http\Controllers\Api\V1\ProjectTasksController as ApiProjectTasksController;
use App\Http\Controllers\Api\V1\ProjectTimesheetsController as ApiProjectTimesheetsController;
use App\Http\Controllers\Api\V1\PurchaseOrdersController as ApiPurchaseOrdersController;
use App\Http\Controllers\Api\V1\PurchaseRfqsController as ApiPurchaseRfqsController;
use App\Http\Controllers\Api\V1\SalesLeadsController as ApiSalesLeadsController;
use App\Http\Controllers\Api\V1\SalesOrdersController as ApiSalesOrdersController;
use App\Http\Controllers\Api\V1\SalesQuotesController as ApiSalesQuotesController;
use App\Http\Controllers\Api\V1\SettingsController as ApiSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'version' => 'v1',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::middleware(['auth:sanctum', 'company.context', 'company'])->group(function () {
        Route::prefix('accounting')->group(function () {
            Route::apiResource('invoices', ApiAccountingInvoicesController::class)
                ->parameters(['invoices' => 'invoice']);
            Route::post('invoices/{invoice}/post', [ApiAccountingInvoicesController::class, 'post']);
            Route::post('invoices/{invoice}/cancel', [ApiAccountingInvoicesController::class, 'cancel']);

            Route::apiResource('payments', ApiAccountingPaymentsController::class)
                ->parameters(['payments' => 'payment']);
            Route::post('payments/{payment}/post', [ApiAccountingPaymentsController::class, 'post']);
            Route::post('payments/{payment}/reconcile', [ApiAccountingPaymentsController::class, 'reconcile']);
            Route::post('payments/{payment}/reverse', [ApiAccountingPaymentsController::class, 'reverse']);
        });
        Route::apiResource('partners', ApiPartnersController::class);
        Route::apiResource('products', ApiProductsController::class);
        Route::prefix('inventory')->group(function () {
            Route::get('stock-balances', [ApiInventoryStockBalancesController::class, 'index']);
            Route::apiResource('stock-moves', ApiInventoryStockMovesController::class)
                ->parameters(['stock-moves' => 'move']);
            Route::post('stock-moves/{move}/reserve', [ApiInventoryStockMovesController::class, 'reserve']);
            Route::post('stock-moves/{move}/dispatch', [ApiInventoryStockMovesController::class, 'dispatch']);
            Route::post('stock-moves/{move}/receive', [ApiInventoryStockMovesController::class, 'receive']);
            Route::post('stock-moves/{move}/complete', [ApiInventoryStockMovesController::class, 'complete']);
            Route::post('stock-moves/{move}/cancel', [ApiInventoryStockMovesController::class, 'cancel']);
        });
        Route::prefix('purchasing')->group(function () {
            Route::apiResource('rfqs', ApiPurchaseRfqsController::class)
                ->parameters(['rfqs' => 'rfq']);
            Route::post('rfqs/{rfq}/send', [ApiPurchaseRfqsController::class, 'send']);
            Route::post('rfqs/{rfq}/respond', [ApiPurchaseRfqsController::class, 'respondToVendor']);
            Route::post('rfqs/{rfq}/select', [ApiPurchaseRfqsController::class, 'select']);
            Route::apiResource('orders', ApiPurchaseOrdersController::class)
                ->parameters(['orders' => 'order']);
            Route::post('orders/{order}/approve', [ApiPurchaseOrdersController::class, 'approve']);
            Route::post('orders/{order}/confirm', [ApiPurchaseOrdersController::class, 'confirm']);
            Route::post('orders/{order}/receive', [ApiPurchaseOrdersController::class, 'receive']);
        });
        Route::prefix('sales')->group(function () {
            Route::apiResource('leads', ApiSalesLeadsController::class);
            Route::apiResource('quotes', ApiSalesQuotesController::class);
            Route::post('quotes/{quote}/send', [ApiSalesQuotesController::class, 'send']);
            Route::post('quotes/{quote}/approve', [ApiSalesQuotesController::class, 'approve']);
            Route::post('quotes/{quote}/reject', [ApiSalesQuotesController::class, 'reject']);
            Route::post('quotes/{quote}/confirm', [ApiSalesQuotesController::class, 'confirm']);
            Route::apiResource('orders', ApiSalesOrdersController::class);
            Route::post('orders/{order}/approve', [ApiSalesOrdersController::class, 'approve']);
            Route::post('orders/{order}/confirm', [ApiSalesOrdersController::class, 'confirm']);
        });
        Route::apiResource('projects', ApiProjectsController::class);

        Route::prefix('projects')->group(function () {
            Route::get('{project}/tasks', [ApiProjectTasksController::class, 'index']);
            Route::post('{project}/tasks', [ApiProjectTasksController::class, 'store']);
            Route::get('tasks/{task}', [ApiProjectTasksController::class, 'show']);
            Route::match(['put', 'patch'], 'tasks/{task}', [ApiProjectTasksController::class, 'update']);
            Route::delete('tasks/{task}', [ApiProjectTasksController::class, 'destroy']);

            Route::get('{project}/timesheets', [ApiProjectTimesheetsController::class, 'index']);
            Route::post('{project}/timesheets', [ApiProjectTimesheetsController::class, 'store']);
            Route::get('timesheets/{timesheet}', [ApiProjectTimesheetsController::class, 'show']);
            Route::match(['put', 'patch'], 'timesheets/{timesheet}', [ApiProjectTimesheetsController::class, 'update']);
            Route::post('timesheets/{timesheet}/submit', [ApiProjectTimesheetsController::class, 'submit']);
            Route::post('timesheets/{timesheet}/approve', [ApiProjectTimesheetsController::class, 'approve']);
            Route::post('timesheets/{timesheet}/reject', [ApiProjectTimesheetsController::class, 'reject']);
            Route::delete('timesheets/{timesheet}', [ApiProjectTimesheetsController::class, 'destroy']);
        });

        Route::get('settings', [ApiSettingsController::class, 'index']);
        Route::put('settings', [ApiSettingsController::class, 'update']);
    });
});
