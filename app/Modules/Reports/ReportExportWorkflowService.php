<?php

namespace App\Modules\Reports;

use App\Core\Company\Models\Company;
use App\Jobs\GenerateCompanyReportExport;
use App\Models\User;
use App\Modules\Reports\Models\ReportExport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class ReportExportWorkflowService
{
    public function __construct(
        private readonly CompanyReportsService $reportsService,
        private readonly CompanyReportExportService $companyReportExportService,
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

    public function markFailed(string $reportExportId, Throwable $exception): void
    {
        /** @var ReportExport|null $export */
        $export = ReportExport::withoutGlobalScopes()->find($reportExportId);

        if (! $export) {
            return;
        }

        $export->forceFill([
            'status' => ReportExport::STATUS_FAILED,
            'failed_at' => now(),
            'failure_message' => $this->truncateFailureMessage($exception),
            'updated_by' => $export->requested_by_user_id,
        ])->save();
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

    private function truncateFailureMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message === '') {
            $message = 'Report export failed.';
        }

        if (strlen($message) <= 1000) {
            return $message;
        }

        return substr($message, 0, 997).'...';
    }
}
