<?php

use App\Http\Controllers\Company\DashboardController as CompanyDashboardController;
use App\Http\Controllers\Company\RolesController as CompanyRolesController;
use App\Http\Controllers\Company\SettingsController as CompanySettingsController;
use App\Http\Controllers\Company\UsersController as CompanyUsersController;
use App\Http\Controllers\Core\CompanySwitchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::get('company/inactive', function (Request $request) {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->is_super_admin) {
            return redirect()->route('platform.dashboard');
        }

        $activeCompany = $user->companies()
            ->where('companies.is_active', true)
            ->orderBy('companies.name')
            ->first();

        if ($activeCompany) {
            $user->forceFill([
                'current_company_id' => $activeCompany->id,
            ])->save();

            return redirect()->route('company.dashboard');
        }

        return Inertia::render('company/inactive');
    })->name('company.inactive');

    Route::post('company/switch', [CompanySwitchController::class, 'update'])
        ->middleware('company.workspace')
        ->name('company.switch');
});

Route::middleware(['auth', 'verified', 'company'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $user = $request->user();

        if ($user && $user->is_super_admin) {
            return redirect()->route('platform.dashboard');
        }

        return redirect()->route('company.dashboard');
    })->name('dashboard');

    Route::middleware('company.workspace')->group(function () {
        Route::get('company/dashboard', [CompanyDashboardController::class, 'index'])
            ->name('company.dashboard');

        Route::prefix('company')->name('company.')->group(function () {
            Route::get('settings', [CompanySettingsController::class, 'show'])
                ->name('settings.show');
            Route::put('settings', [CompanySettingsController::class, 'update'])
                ->name('settings.update');

            Route::get('users', [CompanyUsersController::class, 'index'])
                ->name('users.index');
            Route::put('users/{membership}/role', [CompanyUsersController::class, 'updateRole'])
                ->name('users.update-role');

            Route::get('roles', [CompanyRolesController::class, 'index'])
                ->name('roles.index');
        });
    });
});
