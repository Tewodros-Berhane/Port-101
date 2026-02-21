<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
    ]);
})->name('home');

require __DIR__.'/auth.php';

require __DIR__.'/company.php';
require __DIR__.'/modules.php';
require __DIR__.'/masterdata.php';
require __DIR__.'/platform.php';

require __DIR__.'/settings.php';
