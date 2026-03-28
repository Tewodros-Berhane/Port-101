<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Hr\HrReportsService;
use App\Modules\Reports\CompanyReportExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class HrReportsController extends Controller
{
    public function index(Request $request, HrReportsService $reportsService): Response
    {
        abort_unless($request->user()?->hasPermission('hr.reports.view'), 403);

        $user = $request->user();
        $company = $user?->currentCompany;

        if (! $user instanceof User || ! $company) {
            abort(403, 'Company context not available.');
        }

        $filters = $this->validatedFilters($request);

        return Inertia::render('hr/reports/index', [
            'filters' => [
                'trend_window' => (int) ($filters['trend_window'] ?? 30),
                'start_date' => $filters['start_date'] ?? '',
                'end_date' => $filters['end_date'] ?? '',
            ],
            'reportCatalog' => $reportsService->reportCatalog($company, $user, $filters),
            'canExport' => true,
        ]);
    }

    public function export(
        Request $request,
        string $reportKey,
        HrReportsService $reportsService,
        CompanyReportExportService $exportService,
    ): HttpResponse {
        abort_unless($request->user()?->hasPermission('hr.reports.view'), 403);

        $user = $request->user();
        $company = $user?->currentCompany;

        if (! $user instanceof User || ! $company) {
            abort(403, 'Company context not available.');
        }

        abort_unless(in_array($reportKey, HrReportsService::REPORT_KEYS, true), 404);

        $filters = $this->validatedFilters($request);
        $report = $reportsService->buildReport($company, $user, $reportKey, $filters);

        abort_if(! $report, 404);

        return $exportService->export(
            $company,
            $report,
            $exportService->normalizeFormat((string) $request->input('format', 'pdf')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'trend_window' => ['nullable', 'integer', 'in:7,30,90'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
    }
}
