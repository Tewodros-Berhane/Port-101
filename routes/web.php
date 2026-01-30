<?php

use App\Http\Controllers\Core\CompanySwitchController;
use App\Http\Controllers\Core\PartnersController;
use App\Http\Controllers\Core\ProductsController;
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
        Route::resource('partners', PartnersController::class)
            ->except(['show']);
        Route::resource('products', ProductsController::class)
            ->except(['show']);
    });
});

require __DIR__.'/settings.php';
