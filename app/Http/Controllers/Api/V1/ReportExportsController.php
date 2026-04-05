<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Reports\ReportExportStoreRequest;
use App\Models\User;
use App\Modules\Reports\CompanyReportingSettingsService;
use App\Modules\Reports\Models\ReportExport;
use App\Modules\Reports\ReportExportWorkflowService;
use App\Support\Operations\OperationalFailureSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportExportsController extends ApiController
{
    public function __construct(
        private readonly OperationalFailureSanitizer $failureSanitizer,
    ) {}

    public function store(
        ReportExportStoreRequest $request,
        ReportExportWorkflowService $workflowService,
        CompanyReportingSettingsService $reportingSettingsService,
    ): JsonResponse {
        $this->authorize('create', ReportExport::class);

        $user = $request->user();
        $company = $user?->currentCompany;

        if (! $user instanceof User || ! $company) {
            abort(403, 'Company context not available.');
        }

        $validated = $request->validated();
        $presetId = trim((string) ($validated['preset_id'] ?? ''));

        if ($presetId !== '' && ! $this->presetExists($reportingSettingsService, $company->id, $presetId)) {
            throw ValidationException::withMessages([
                'preset_id' => 'Selected preset was not found.',
            ]);
        }

        if (! $this->hasAnyFilterInput($validated) && $presetId !== '') {
            $validated = [
                ...$validated,
                ...$reportingSettingsService->filtersForPreset($company->id, $presetId),
            ];
        }

        $filters = $reportingSettingsService->normalizeFilters($validated);
        $export = $workflowService->create(
            company: $company,
            actor: $user,
            reportKey: (string) $validated['report_key'],
            format: (string) $validated['format'],
            filters: $filters,
        );

        return $this->respond($this->mapExport($export), 202);
    }

    public function show(ReportExport $reportExport): JsonResponse
    {
        $this->authorize('view', $reportExport);

        $reportExport->load('requestedBy:id,name,email');

        return $this->respond($this->mapExport($reportExport));
    }

    public function download(
        ReportExport $reportExport,
        ReportExportWorkflowService $workflowService,
    ): HttpResponse {
        $this->authorize('download', $reportExport);

        return $workflowService->downloadResponse($reportExport);
    }

    private function mapExport(ReportExport $reportExport): array
    {
        $downloadUrl = null;

        if (
            $reportExport->status === ReportExport::STATUS_COMPLETED
            && $reportExport->file_path
        ) {
            $downloadUrl = "/api/v1/reports/exports/{$reportExport->id}/download";
        }

        return [
            'id' => $reportExport->id,
            'report_key' => $reportExport->report_key,
            'report_title' => $reportExport->report_title,
            'format' => $reportExport->format,
            'status' => $reportExport->status,
            'filters' => is_array($reportExport->filters) ? $reportExport->filters : [],
            'requested_by_user_id' => $reportExport->requested_by_user_id,
            'requested_by_name' => $reportExport->requestedBy?->name,
            'requested_by_email' => $reportExport->requestedBy?->email,
            'file_name' => $reportExport->file_name,
            'mime_type' => $reportExport->mime_type,
            'file_size' => $reportExport->file_size,
            'row_count' => $reportExport->row_count,
            'started_at' => $reportExport->started_at?->toIso8601String(),
            'completed_at' => $reportExport->completed_at?->toIso8601String(),
            'failed_at' => $reportExport->failed_at?->toIso8601String(),
            'expires_at' => $reportExport->expires_at?->toIso8601String(),
            'failure_message' => $this->failureSanitizer->sanitizeStoredReportFailureMessage(
                $reportExport->failure_message
            ),
            'download_url' => $downloadUrl,
            'can_download' => $downloadUrl !== null,
            'created_at' => $reportExport->created_at?->toIso8601String(),
            'updated_at' => $reportExport->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasAnyFilterInput(array $data): bool
    {
        foreach (['trend_window', 'start_date', 'end_date', 'approval_status'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function presetExists(
        CompanyReportingSettingsService $reportingSettingsService,
        string $companyId,
        string $presetId,
    ): bool {
        return collect($reportingSettingsService->getPresets($companyId))
            ->contains(fn (array $preset) => $preset['id'] === $presetId);
    }
}
