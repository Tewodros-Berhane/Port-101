<?php

use App\Http\Controllers\InviteAcceptanceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
    ]);
})->name('home');

Route::get('invites/{token}', [InviteAcceptanceController::class, 'show'])
    ->name('invites.accept.show');
Route::post('invites/{token}/accept', [InviteAcceptanceController::class, 'store'])
    ->middleware('guest')
    ->name('invites.accept.store');

require __DIR__.'/company.php';
require __DIR__.'/modules.php';
require __DIR__.'/masterdata.php';
require __DIR__.'/platform.php';

require __DIR__.'/settings.php';
