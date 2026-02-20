<?php

namespace App\Http\Controllers\Platform;

use App\Core\Platform\DashboardPreferencesService;
use App\Core\Platform\OperationsReportingSettingsService;
use App\Core\Platform\PlatformReportExportService;
use App\Core\Platform\PlatformReportsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportsController extends Controller
{
    public function index(
        Request $request,
        PlatformReportsService $reportsService,
        OperationsReportingSettingsService $operationsSettings,
        DashboardPreferencesService $dashboardPreferences
    ): Response {
        $userId = $request->user()?->id;
        $preferences = $dashboardPreferences->get($userId);
        $validatedFilters = $this->validatedOperationsFilters($request);

        if (! $this->hasAnyOperationsFilterInput($request)) {
            $validatedFilters = [
                ...$validatedFilters,
                ...$operationsSettings->filtersForPreset(
                    $preferences['default_preset_id'] ?? null
                ),
            ];
        }

        $normalizedFilters = $operationsSettings->normalizeFilters($validatedFilters);

        return Inertia::render('platform/reports', [
            'operationsFilters' => [
                'trend_window' => (int) ($normalizedFilters['trend_window'] ?? 30),
                'admin_action' => $normalizedFilters['admin_action'] ?? null,
                'admin_actor_id' => $normalizedFilters['admin_actor_id'] ?? null,
                'admin_start_date' => $normalizedFilters['admin_start_date'] ?? null,
                'admin_end_date' => $normalizedFilters['admin_end_date'] ?? null,
                'invite_delivery_status' => $normalizedFilters['invite_delivery_status'] ?? null,
            ],
            'adminFilterOptions' => $reportsService->adminFilterOptions(),
            'reportCatalog' => $reportsService->reportCatalog($normalizedFilters),
            'operationsReportPresets' => $operationsSettings->getPresets(),
            'reportingResearch' => [
                'north_star_metrics' => [
                    'title' => 'North Star Metrics',
                    'source' => 'Amplitude',
                    'url' => 'https://amplitude.com/blog/north-star-metric',
                    'summary' => 'SaaS reporting should center on activation, engagement, retention, and monetization outcomes.',
                ],
                'saas_kpi_baselines' => [
                    'title' => 'SaaS KPIs and Benchmarks',
                    'source' => 'Stripe',
                    'url' => 'https://stripe.com/resources/more/saas-metrics-and-kpis',
                    'summary' => 'Track MRR, churn, LTV, CAC payback, and expansion to monitor platform health and growth efficiency.',
                ],
                'cohort_retention' => [
                    'title' => 'Retention and Revenue Churn',
                    'source' => 'Paddle',
                    'url' => 'https://www.paddle.com/resources/saas-churn-rate',
                    'summary' => 'Separate logo churn from revenue churn for clearer retention diagnostics.',
                ],
            ],
        ]);
    }

    public function export(
        Request $request,
        string $reportKey,
        PlatformReportsService $reportsService,
        PlatformReportExportService $exportService,
        OperationsReportingSettingsService $operationsSettings,
        DashboardPreferencesService $dashboardPreferences
    ): HttpResponse {
        $validatedFilters = $this->validatedOperationsFilters($request);

        if (! $this->hasAnyOperationsFilterInput($request)) {
            $userId = $request->user()?->id;
            $preferences = $dashboardPreferences->get($userId);

            $validatedFilters = [
                ...$validatedFilters,
                ...$operationsSettings->filtersForPreset(
                    $preferences['default_preset_id'] ?? null
                ),
            ];
        }

        $filters = $operationsSettings->normalizeFilters($validatedFilters);
        $report = $reportsService->buildReport($reportKey, $filters);

        abort_if(! $report, 404);

        $format = $exportService->normalizeFormat(
            (string) $request->input('format', 'pdf')
        );

        return $exportService->export($report, $format);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedOperationsFilters(Request $request): array
    {
        return $request->validate([
            'trend_window' => ['nullable', 'integer', 'in:7,30,90'],
            'admin_action' => ['nullable', 'string', 'max:32'],
            'admin_actor_id' => ['nullable', 'uuid', 'exists:users,id'],
            'admin_start_date' => ['nullable', 'date_format:Y-m-d'],
            'admin_end_date' => ['nullable', 'date_format:Y-m-d'],
            'invite_delivery_status' => ['nullable', 'string', 'in:pending,sent,failed'],
        ]);
    }

    private function hasAnyOperationsFilterInput(Request $request): bool
    {
        foreach ([
            'trend_window',
            'admin_action',
            'admin_actor_id',
            'admin_start_date',
            'admin_end_date',
            'invite_delivery_status',
        ] as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }
}
