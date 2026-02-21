<?php

use App\Http\Controllers\InviteAcceptanceController;
use Illuminate\Support\Facades\Route;

Route::get('invites/{token}', [InviteAcceptanceController::class, 'show'])
    ->name('invites.accept.show');

Route::post('invites/{token}/accept', [InviteAcceptanceController::class, 'store'])
    ->middleware('guest')
    ->name('invites.accept.store');
