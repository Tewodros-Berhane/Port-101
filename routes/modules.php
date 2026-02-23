<?php

use Illuminate\Support\Facades\Route;

$moduleRouteFiles = [
    base_path('routes/moduleroutes/sales.php'),
    base_path('routes/moduleroutes/inventory.php'),
    base_path('routes/moduleroutes/general.php'),
];

foreach ($moduleRouteFiles as $moduleRouteFile) {
    Route::middleware(['auth', 'verified', 'company', 'company.workspace'])
        ->prefix('company')
        ->name('company.')
        ->group($moduleRouteFile);
}
