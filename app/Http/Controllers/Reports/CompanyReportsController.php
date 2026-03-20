<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Modules\Reports\CompanyReportExportService;
use App\Modules\Reports\CompanyReportingSettingsService;
use App\Modules\Reports\CompanyReportsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CompanyReportsController extends Controller
{
    public function index(
        Request $request,
        CompanyReportsService $reportsService,
        CompanyReportingSettingsService $reportingSettingsService,
    ): Response {
        abort_unless($request->user()?->hasPermission('reports.view'), 403);

        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $validatedFilters = $this->validatedFilters($request);
        $presetId = $request->string('preset_id')->toString();

        if (! $this->hasAnyFilterInput($request) && $presetId !== '') {
            $validatedFilters = [
                ...$validatedFilters,
                ...$reportingSettingsService->filtersForPreset($company->id, $presetId),
            ];
        }

        $filters = $reportingSettingsService->normalizeFilters($validatedFilters);

        return Inertia::render('reports/index', [
            'filters' => [
                'trend_window' => (int) ($filters['trend_window'] ?? 30),
                'start_date' => $filters['start_date'] ?? '',
                'end_date' => $filters['end_date'] ?? '',
                'approval_status' => $filters['approval_status'] ?? '',
            ],
            'reportCatalog' => $reportsService->reportCatalog($company, $filters),
            'reportPresets' => $reportingSettingsService->getPresets($company->id),
            'deliverySchedule' => $reportingSettingsService->getDeliverySchedule($company->id),
            'reportKeyOptions' => collect(CompanyReportsService::REPORT_KEYS)
                ->map(fn (string $key) => [
                    'value' => $key,
                    'label' => $this->reportKeyLabel($key),
                ])
                ->values()
                ->all(),
            'canExport' => $request->user()?->hasPermission('reports.export') ?? false,
            'canManage' => $request->user()?->hasPermission('reports.export') ?? false,
        ]);
    }

    public function export(
        Request $request,
        string $reportKey,
        CompanyReportsService $reportsService,
        CompanyReportExportService $exportService,
        CompanyReportingSettingsService $reportingSettingsService,
    ): HttpResponse {
        abort_unless($request->user()?->hasPermission('reports.export'), 403);

        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $validatedFilters = $this->validatedFilters($request);
        $presetId = $request->string('preset_id')->toString();

        if (! $this->hasAnyFilterInput($request) && $presetId !== '') {
            $validatedFilters = [
                ...$validatedFilters,
                ...$reportingSettingsService->filtersForPreset($company->id, $presetId),
            ];
        }

        $filters = $reportingSettingsService->normalizeFilters($validatedFilters);
        $report = $reportsService->buildReport($company, $reportKey, $filters);

        abort_if(! $report, 404);

        $format = $exportService->normalizeFormat((string) $request->input('format', 'pdf'));

        return $exportService->export($company, $report, $format);
    }

    public function storePreset(
        Request $request,
        CompanyReportingSettingsService $reportingSettingsService,
    ): RedirectResponse {
        abort_unless($request->user()?->hasPermission('reports.export'), 403);

        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'trend_window' => ['nullable', 'integer', 'in:7,30,90'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'approval_status' => ['nullable', 'string', 'in:pending,approved,rejected,cancelled'],
        ]);

        $reportingSettingsService->savePreset(
            companyId: $company->id,
            name: (string) $data['name'],
            filters: $data,
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.modules.reports')
            ->with('success', 'Report preset saved.');
    }

    public function destroyPreset(
        Request $request,
        string $presetId,
        CompanyReportingSettingsService $reportingSettingsService,
    ): RedirectResponse {
        abort_unless($request->user()?->hasPermission('reports.export'), 403);

        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $deleted = $reportingSettingsService->deletePreset(
            companyId: $company->id,
            presetId: $presetId,
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.modules.reports')
            ->with($deleted ? 'success' : 'warning', $deleted
                ? 'Report preset deleted.'
                : 'Preset was not found.');
    }

    public function updateDeliverySchedule(
        Request $request,
        CompanyReportingSettingsService $reportingSettingsService,
    ): RedirectResponse {
        abort_unless($request->user()?->hasPermission('reports.export'), 403);

        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'preset_id' => ['nullable', 'string', 'max:80'],
            'report_key' => ['required', 'string', 'in:'.implode(',', CompanyReportsService::REPORT_KEYS)],
            'format' => ['required', 'string', 'in:pdf,xlsx'],
            'frequency' => ['required', 'string', 'in:daily,weekly'],
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $presetId = $data['preset_id'] ?? null;

        if (
            $presetId
            && ! collect($reportingSettingsService->getPresets($company->id))
                ->contains(fn (array $preset) => $preset['id'] === $presetId)
        ) {
            return redirect()
                ->route('company.modules.reports')
                ->withErrors([
                    'delivery_schedule.preset_id' => 'Selected preset was not found.',
                ]);
        }

        $reportingSettingsService->setDeliverySchedule(
            companyId: $company->id,
            data: [
                'enabled' => (bool) $data['enabled'],
                'preset_id' => $presetId ?: null,
                'report_key' => $data['report_key'],
                'format' => $data['format'],
                'frequency' => $data['frequency'],
                'day_of_week' => (int) $data['day_of_week'],
                'time' => $data['time'],
                'timezone' => $data['timezone'],
            ],
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.modules.reports')
            ->with('success', 'Report delivery schedule updated.');
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
            'approval_status' => ['nullable', 'string', 'in:pending,approved,rejected,cancelled'],
        ]);
    }

    private function hasAnyFilterInput(Request $request): bool
    {
        foreach (['trend_window', 'start_date', 'end_date', 'approval_status'] as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }

    private function reportKeyLabel(string $reportKey): string
    {
        return match ($reportKey) {
            CompanyReportsService::REPORT_SALES_PERFORMANCE => 'Sales performance',
            CompanyReportsService::REPORT_INVENTORY_OPERATIONS => 'Inventory operations',
            CompanyReportsService::REPORT_PURCHASING_PERFORMANCE => 'Purchasing performance',
            CompanyReportsService::REPORT_FINANCE_SNAPSHOT => 'Finance snapshot',
            CompanyReportsService::REPORT_FINANCIAL_PROFIT_LOSS => 'Profit and loss',
            CompanyReportsService::REPORT_FINANCIAL_BALANCE_SHEET => 'Balance sheet',
            CompanyReportsService::REPORT_FINANCIAL_TRIAL_BALANCE => 'Trial balance',
            CompanyReportsService::REPORT_FINANCIAL_CASH_FLOW => 'Cash flow summary',
            CompanyReportsService::REPORT_APPROVAL_GOVERNANCE => 'Approval governance',
            default => $reportKey,
        };
    }
}
