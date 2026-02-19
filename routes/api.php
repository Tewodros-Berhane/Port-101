<?php

use App\Http\Controllers\Api\V1\PartnersController as ApiPartnersController;
use App\Http\Controllers\Api\V1\ProductsController as ApiProductsController;
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
        Route::apiResource('partners', ApiPartnersController::class);
        Route::apiResource('products', ApiProductsController::class);

        Route::get('settings', [ApiSettingsController::class, 'index']);
        Route::put('settings', [ApiSettingsController::class, 'update']);
    });
});
