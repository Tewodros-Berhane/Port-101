<?php

namespace App\Http\Controllers\Platform;

use App\Core\Platform\QueueHealthService;
use App\Http\Controllers\Controller;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Reports\Models\ReportExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueHealthController extends Controller
{
    public function index(Request $request, QueueHealthService $queueHealthService): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'queue' => ['nullable', 'string', 'max:160'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $queue = trim((string) ($filters['queue'] ?? ''));

        return Inertia::render('platform/operations/queue-health', [
            'filters' => [
                'search' => $search,
                'queue' => $queue,
            ],
            'summary' => $queueHealthService->summary(),
            'queueOptions' => $queueHealthService->queueOptions(),
            'backlogByQueue' => $queueHealthService->backlogByQueue(),
            'topFailedJobTypes' => $queueHealthService->topFailedJobTypes(),
            'topFailureReasons' => $queueHealthService->topFailureReasons(),
            'companyImpact' => $queueHealthService->companyImpact(),
            'failedJobs' => $queueHealthService->failedJobs([
                'search' => $search,
                'queue' => $queue,
            ]),
            'deadWebhookDeliveries' => $queueHealthService->recentDeadWebhookDeliveries(),
            'failedReportExports' => $queueHealthService->failedReportExports(),
        ]);
    }

    public function retryFailedJob(
        string $failedJobId,
        Request $request,
        QueueHealthService $queueHealthService
    ): RedirectResponse {
        $result = $queueHealthService->retryFailedJob($failedJobId, $request->user()?->id);

        return back(303)->with(
            $result['status'] === 'retried' ? 'success' : 'warning',
            $result['message'],
        );
    }

    public function forgetFailedJob(
        string $failedJobId,
        Request $request,
        QueueHealthService $queueHealthService
    ): RedirectResponse {
        $deleted = $queueHealthService->forgetFailedJob($failedJobId, $request->user()?->id);

        return back(303)->with(
            $deleted ? 'success' : 'warning',
            $deleted
                ? 'Failed job was removed from the failure queue.'
                : 'Failed job was not found.',
        );
    }

    public function retryWebhookDelivery(
        WebhookDelivery $delivery,
        Request $request,
        QueueHealthService $queueHealthService
    ): RedirectResponse {
        $retried = $queueHealthService->retryWebhookDelivery($delivery, $request->user()?->id);

        $flash = match ($retried->status) {
            WebhookDelivery::STATUS_DELIVERED => ['success', 'Webhook dead letter retried successfully.'],
            WebhookDelivery::STATUS_FAILED => ['warning', 'Webhook delivery failed again and is scheduled for another retry.'],
            WebhookDelivery::STATUS_DEAD => ['warning', 'Webhook delivery failed again and remains dead-lettered.'],
            default => ['success', 'Webhook delivery retry queued.'],
        };

        return back(303)->with($flash[0], $flash[1]);
    }

    public function retryReportExport(
        ReportExport $reportExport,
        Request $request,
        QueueHealthService $queueHealthService
    ): RedirectResponse {
        $retried = $queueHealthService->retryReportExport($reportExport, $request->user()?->id);

        $flash = match ($retried->status) {
            ReportExport::STATUS_COMPLETED => ['success', 'Report export was regenerated successfully.'],
            ReportExport::STATUS_PROCESSING,
            ReportExport::STATUS_PENDING => ['success', 'Report export was requeued successfully.'],
            ReportExport::STATUS_FAILED => ['warning', 'Report export failed again during retry.'],
            default => ['success', 'Report export retry queued.'],
        };

        return back(303)->with($flash[0], $flash[1]);
    }
}
