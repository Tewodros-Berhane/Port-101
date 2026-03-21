<?php

use Illuminate\Support\Facades\Route;

$moduleRouteFiles = [
    base_path('routes/moduleroutes/sales.php'),
    base_path('routes/moduleroutes/inventory.php'),
    base_path('routes/moduleroutes/projects.php'),
    base_path('routes/moduleroutes/accounting.php'),
    base_path('routes/moduleroutes/purchasing.php'),
    base_path('routes/moduleroutes/approvals.php'),
    base_path('routes/moduleroutes/reports.php'),
];

foreach ($moduleRouteFiles as $moduleRouteFile) {
    Route::middleware(['auth', 'verified', 'company', 'company.workspace'])
        ->prefix('company')
        ->name('company.')
        ->group($moduleRouteFile);
}
