<?php

use App\Http\Controllers\PublicSite\ContactRequestsController as PublicContactRequestsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
    ]);
})->name('home');

Route::get('/book-demo', [PublicContactRequestsController::class, 'bookDemo'])
    ->name('public.book-demo');
Route::get('/contact-sales', [PublicContactRequestsController::class, 'contactSales'])
    ->name('public.contact-sales');
Route::post('/contact-requests', [PublicContactRequestsController::class, 'store'])
    ->middleware('throttle:contact-requests')
    ->name('public.contact-requests.store');

require __DIR__.'/auth.php';
require __DIR__.'/company.php';
require __DIR__.'/modules.php';
require __DIR__.'/masterdata.php';
require __DIR__.'/platform.php';
require __DIR__.'/settings.php';
