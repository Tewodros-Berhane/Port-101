<?php

use App\Http\Controllers\Inventory\InventoryDashboardController;
use App\Http\Controllers\Inventory\InventoryLocationsController;
use App\Http\Controllers\Inventory\InventoryStockLevelsController;
use App\Http\Controllers\Inventory\InventoryStockMovesController;
use App\Http\Controllers\Inventory\InventoryWarehousesController;
use Illuminate\Support\Facades\Route;

Route::get('inventory', [InventoryDashboardController::class, 'index'])
    ->name('modules.inventory');

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::resource('warehouses', InventoryWarehousesController::class)
        ->except(['show']);
    Route::resource('locations', InventoryLocationsController::class)
        ->except(['show']);
    Route::get('stock-levels', [InventoryStockLevelsController::class, 'index'])
        ->name('stock-levels.index');
    Route::resource('moves', InventoryStockMovesController::class)
        ->except(['show']);
    Route::post('moves/{move}/reserve', [InventoryStockMovesController::class, 'reserve'])
        ->name('moves.reserve');
    Route::post('moves/{move}/complete', [InventoryStockMovesController::class, 'complete'])
        ->name('moves.complete');
    Route::post('moves/{move}/cancel', [InventoryStockMovesController::class, 'cancel'])
        ->name('moves.cancel');
});
