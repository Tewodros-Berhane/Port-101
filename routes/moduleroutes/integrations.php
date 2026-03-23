<?php

use App\Http\Controllers\Integrations\IntegrationsDashboardController;
use App\Http\Controllers\Integrations\WebhookDeliveriesController;
use App\Http\Controllers\Integrations\WebhookEndpointsController;
use Illuminate\Support\Facades\Route;

Route::get('integrations', [IntegrationsDashboardController::class, 'index'])
    ->name('modules.integrations');

Route::prefix('integrations')->name('integrations.')->group(function () {
    Route::get('webhooks', [WebhookEndpointsController::class, 'index'])
        ->name('webhooks.index');
    Route::get('webhooks/create', [WebhookEndpointsController::class, 'create'])
        ->name('webhooks.create');
    Route::post('webhooks', [WebhookEndpointsController::class, 'store'])
        ->name('webhooks.store');
    Route::get('webhooks/{endpoint}', [WebhookEndpointsController::class, 'show'])
        ->name('webhooks.show');
    Route::get('webhooks/{endpoint}/edit', [WebhookEndpointsController::class, 'edit'])
        ->name('webhooks.edit');
    Route::put('webhooks/{endpoint}', [WebhookEndpointsController::class, 'update'])
        ->name('webhooks.update');
    Route::delete('webhooks/{endpoint}', [WebhookEndpointsController::class, 'destroy'])
        ->name('webhooks.destroy');
    Route::post('webhooks/{endpoint}/rotate-secret', [WebhookEndpointsController::class, 'rotateSecret'])
        ->name('webhooks.rotate-secret');
    Route::post('webhooks/{endpoint}/test', [WebhookEndpointsController::class, 'test'])
        ->name('webhooks.test');

    Route::get('deliveries', [WebhookDeliveriesController::class, 'index'])
        ->name('deliveries.index');
    Route::get('deliveries/{delivery}', [WebhookDeliveriesController::class, 'show'])
        ->name('deliveries.show');
    Route::post('deliveries/{delivery}/retry', [WebhookDeliveriesController::class, 'retry'])
        ->name('deliveries.retry');
});
