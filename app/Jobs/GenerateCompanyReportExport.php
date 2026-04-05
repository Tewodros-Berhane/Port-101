<?php

namespace App\Jobs;

use App\Modules\Reports\ReportExportWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateCompanyReportExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public string $reportExportId) {}

    public function handle(ReportExportWorkflowService $workflowService): void
    {
        try {
            $workflowService->process($this->reportExportId);
        } catch (Throwable $exception) {
            $workflowService->throwSanitizedFailure($this->reportExportId, $exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(ReportExportWorkflowService::class)->markFailed(
            $this->reportExportId,
            $exception,
        );
    }
}
