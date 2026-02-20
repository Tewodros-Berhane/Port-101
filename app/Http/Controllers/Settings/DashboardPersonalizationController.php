<?php

namespace App\Http\Controllers\Settings;

use App\Core\Platform\DashboardPreferencesService;
use App\Core\Platform\OperationsReportingSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardPersonalizationController extends Controller
{
    public function edit(
        Request $request,
        DashboardPreferencesService $dashboardPreferences,
        OperationsReportingSettingsService $operationsSettings
    ): Response {
        return Inertia::render('settings/dashboard-personalization', [
            'dashboardPreferences' => $dashboardPreferences->get($request->user()?->id),
            'operationsReportPresets' => $operationsSettings->getPresets(),
        ]);
    }
}
