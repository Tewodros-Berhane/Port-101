<?php

use App\Http\Controllers\Core\AuditLogsController;
use App\Http\Controllers\Core\CompanySwitchController;
use App\Http\Controllers\Core\AddressesController;
use App\Http\Controllers\Core\ContactsController;
use App\Http\Controllers\Core\CurrenciesController;
use App\Http\Controllers\Core\PartnersController;
use App\Http\Controllers\Core\PriceListsController;
use App\Http\Controllers\Core\ProductsController;
use App\Http\Controllers\Core\TaxesController;
use App\Http\Controllers\Core\UomsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::post('company/switch', [CompanySwitchController::class, 'update'])
        ->name('company.switch');
});

Route::middleware(['auth', 'verified', 'company'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::prefix('core')->name('core.')->group(function () {
        Route::get('audit-logs', [AuditLogsController::class, 'index'])
            ->name('audit-logs.index');
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
    });
});

require __DIR__.'/settings.php';
