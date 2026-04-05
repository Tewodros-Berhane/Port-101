<?php

namespace App\Modules\Reports;

use App\Core\Company\Models\Company;
use App\Jobs\GenerateCompanyReportExport;
use App\Models\User;
use App\Modules\Reports\Models\ReportExport;
use App\Support\Operations\OperationalFailureSanitizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class ReportExportWorkflowService
{
    public function __construct(
        private readonly CompanyReportsService $reportsService,
        private readonly CompanyReportExportService $companyReportExportService,
        private readonly OperationalFailureSanitizer $failureSanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function create(
        Company $company,
        User $actor,
        string $reportKey,
        string $format,
        array $filters,
    ): ReportExport {
        $export = ReportExport::create([
            'company_id' => $company->id,
            'report_key' => $reportKey,
            'format' => $this->companyReportExportService->normalizeFormat($format),
            'status' => ReportExport::STATUS_PENDING,
            'filters' => $filters,
            'requested_by_user_id' => $actor->id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        GenerateCompanyReportExport::dispatch($export->id);

        return $export->fresh(['requestedBy:id,name,email']) ?? $export;
    }

    public function process(string $reportExportId): ?ReportExport
    {
        /** @var ReportExport|null $export */
        $export = ReportExport::withoutGlobalScopes()
            ->with(['company', 'requestedBy:id,name,email'])
            ->find($reportExportId);

        if (! $export) {
            return null;
        }

        if (
            $export->status === ReportExport::STATUS_COMPLETED
            && $export->file_path
            && Storage::disk($export->disk)->exists($export->file_path)
        ) {
            return $export;
        }

        $export->forceFill([
            'status' => ReportExport::STATUS_PROCESSING,
            'started_at' => $export->started_at ?? now(),
            'failed_at' => null,
            'failure_message' => null,
            'updated_by' => $export->requested_by_user_id,
        ])->save();

        $company = $export->company;

        if (! $company) {
            throw new \RuntimeException('Company not found for report export.');
        }

        $report = $this->reportsService->buildReport(
            $company,
            $export->report_key,
            is_array($export->filters) ? $export->filters : [],
        );

        if (! $report) {
            throw new \RuntimeException('Requested report could not be generated.');
        }

        $stored = $this->companyReportExportService->storeExport(
            $company,
            $report,
            $export->format,
            'report-exports/'.$company->id,
        );

        $export->forceFill([
            'report_title' => $report['title'],
            'status' => ReportExport::STATUS_COMPLETED,
            'disk' => $stored['disk'],
            'file_path' => $stored['path'],
            'file_name' => $stored['file_name'],
            'mime_type' => $stored['mime_type'],
            'file_size' => $stored['file_size'],
            'row_count' => count($report['rows']),
            'completed_at' => now(),
            'failed_at' => null,
            'expires_at' => now()->addDays(7),
            'failure_message' => null,
            'updated_by' => $export->requested_by_user_id,
        ])->save();

        return $export->fresh(['requestedBy:id,name,email']) ?? $export;
    }

    public function retry(ReportExport $reportExport, ?string $actorId = null): ReportExport
    {
        abort_if(
            $reportExport->status !== ReportExport::STATUS_FAILED,
            422,
            'Only failed report exports can be retried.',
        );

        $reportExport->forceFill([
            'status' => ReportExport::STATUS_PENDING,
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'failure_message' => null,
            'file_path' => null,
            'file_name' => null,
            'mime_type' => null,
            'file_size' => null,
            'row_count' => null,
            'expires_at' => null,
            'updated_by' => $actorId ?? $reportExport->requested_by_user_id,
        ])->save();

        GenerateCompanyReportExport::dispatch($reportExport->id);

        return $reportExport->fresh(['requestedBy:id,name,email']) ?? $reportExport;
    }

    public function markFailed(string $reportExportId, Throwable $exception): void
    {
        /** @var ReportExport|null $export */
        $export = ReportExport::withoutGlobalScopes()->find($reportExportId);

        if (! $export) {
            return;
        }

        $normalized = $this->failureSanitizer->normalizeReportExportFailure($exception);

        $export->forceFill([
            'status' => ReportExport::STATUS_FAILED,
            'failed_at' => now(),
            'failure_message' => $normalized['message'],
            'updated_by' => $export->requested_by_user_id,
        ])->save();

        Log::warning('Report export failed.', [
            'module' => 'reports',
            'entity' => 'report_export',
            'action' => 'generation_failed',
            'company_id' => $export->company_id,
            'report_export_id' => $export->id,
            'report_key' => $export->report_key,
            'format' => $export->format,
            'requested_by_user_id' => $export->requested_by_user_id,
            ...$normalized['log_context'],
        ]);
    }

    public function downloadResponse(ReportExport $reportExport): HttpResponse
    {
        if (
            $reportExport->status !== ReportExport::STATUS_COMPLETED
            || ! $reportExport->file_path
        ) {
            abort(422, 'Report export is not ready for download.');
        }

        if (! Storage::disk($reportExport->disk)->exists($reportExport->file_path)) {
            abort(404, 'Export file not found.');
        }

        $content = Storage::disk($reportExport->disk)->get($reportExport->file_path);
        $filename = $reportExport->file_name ?: basename($reportExport->file_path);

        return response($content, 200, [
            'Content-Type' => $reportExport->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function throwSanitizedFailure(string $reportExportId, Throwable $exception): never
    {
        $normalized = $this->failureSanitizer->normalizeReportExportFailure($exception);

        $export = ReportExport::withoutGlobalScopes()
            ->select(['id', 'company_id', 'report_key', 'format', 'requested_by_user_id'])
            ->find($reportExportId);

        Log::warning('Report export processing threw an exception.', [
            'module' => 'reports',
            'entity' => 'report_export',
            'action' => 'processing_exception',
            'company_id' => $export?->company_id,
            'report_export_id' => $reportExportId,
            'report_key' => $export?->report_key,
            'format' => $export?->format,
            'requested_by_user_id' => $export?->requested_by_user_id,
            ...$normalized['log_context'],
        ]);

        throw new \RuntimeException($normalized['message']);
    }
}
