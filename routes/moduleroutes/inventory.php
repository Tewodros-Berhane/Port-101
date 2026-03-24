<?php

use App\Http\Controllers\Inventory\InventoryDashboardController;
use App\Http\Controllers\Inventory\InventoryCycleCountsController;
use App\Http\Controllers\Inventory\InventoryLocationsController;
use App\Http\Controllers\Inventory\InventoryLotsController;
use App\Http\Controllers\Inventory\InventoryReorderingController;
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
    Route::get('lots', [InventoryLotsController::class, 'index'])
        ->name('lots.index');
    Route::get('lots/{lot}', [InventoryLotsController::class, 'show'])
        ->name('lots.show');
    Route::get('cycle-counts', [InventoryCycleCountsController::class, 'index'])
        ->name('cycle-counts.index');
    Route::get('cycle-counts/create', [InventoryCycleCountsController::class, 'create'])
        ->name('cycle-counts.create');
    Route::post('cycle-counts', [InventoryCycleCountsController::class, 'store'])
        ->name('cycle-counts.store');
    Route::get('cycle-counts/{cycleCount}', [InventoryCycleCountsController::class, 'show'])
        ->name('cycle-counts.show');
    Route::put('cycle-counts/{cycleCount}', [InventoryCycleCountsController::class, 'update'])
        ->name('cycle-counts.update');
    Route::post('cycle-counts/{cycleCount}/start', [InventoryCycleCountsController::class, 'start'])
        ->name('cycle-counts.start');
    Route::post('cycle-counts/{cycleCount}/review', [InventoryCycleCountsController::class, 'review'])
        ->name('cycle-counts.review');
    Route::post('cycle-counts/{cycleCount}/post', [InventoryCycleCountsController::class, 'post'])
        ->name('cycle-counts.post');
    Route::post('cycle-counts/{cycleCount}/cancel', [InventoryCycleCountsController::class, 'cancel'])
        ->name('cycle-counts.cancel');
    Route::get('reordering', [InventoryReorderingController::class, 'index'])
        ->name('reordering.index');
    Route::get('reordering/create', [InventoryReorderingController::class, 'create'])
        ->name('reordering.create');
    Route::post('reordering', [InventoryReorderingController::class, 'store'])
        ->name('reordering.store');
    Route::post('reordering/scan', [InventoryReorderingController::class, 'scan'])
        ->name('reordering.scan');
    Route::get('reordering/{rule}/edit', [InventoryReorderingController::class, 'edit'])
        ->name('reordering.edit');
    Route::put('reordering/{rule}', [InventoryReorderingController::class, 'update'])
        ->name('reordering.update');
    Route::delete('reordering/{rule}', [InventoryReorderingController::class, 'destroy'])
        ->name('reordering.destroy');
    Route::post('reordering/suggestions/{suggestion}/dismiss', [InventoryReorderingController::class, 'dismiss'])
        ->name('reordering.suggestions.dismiss');
    Route::post('reordering/suggestions/{suggestion}/convert', [InventoryReorderingController::class, 'convert'])
        ->name('reordering.suggestions.convert');
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
