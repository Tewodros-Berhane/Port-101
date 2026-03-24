<?php

namespace App\Core\Platform;

use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Models\User;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\WebhookDeliveryService;
use App\Modules\Integrations\WebhookEventCatalog;
use App\Modules\Reports\Models\ReportExport;
use App\Modules\Reports\ReportExportWorkflowService;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class QueueHealthService
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $recentFailedJobSnapshots = null;

    public function __construct(
        private readonly FailedJobProviderInterface $failedJobProvider,
        private readonly QueueFactory $queueFactory,
        private readonly WebhookDeliveryService $webhookDeliveryService,
        private readonly ReportExportWorkflowService $reportExportWorkflowService,
        private readonly WebhookEventCatalog $webhookEventCatalog,
        private readonly ?Encrypter $encrypter = null,
    ) {}

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $backlogByQueue = collect($this->backlogByQueue());
        $failedJobsCount = (clone $this->failedJobsQuery())->count();
        $recentFailedJobs = collect($this->recentFailedJobSnapshots());

        $companyIds = collect($this->companyImpact())
            ->pluck('company_id')
            ->filter()
            ->unique()
            ->count();

        return [
            'queued_jobs' => (int) $backlogByQueue->sum('total_jobs'),
            'ready_jobs' => (int) $backlogByQueue->sum('ready_jobs'),
            'reserved_jobs' => (int) $backlogByQueue->sum('reserved_jobs'),
            'delayed_jobs' => (int) $backlogByQueue->sum('delayed_jobs'),
            'failed_jobs' => $failedJobsCount,
            'failed_jobs_last_24_hours' => (int) (clone $this->failedJobsQuery())
                ->where('failed_at', '>=', now()->subDay())
                ->count(),
            'dead_webhook_deliveries' => WebhookDelivery::withoutGlobalScopes()
                ->where('status', WebhookDelivery::STATUS_DEAD)
                ->count(),
            'failed_report_exports' => ReportExport::withoutGlobalScopes()
                ->where('status', ReportExport::STATUS_FAILED)
                ->count(),
            'impacted_queues' => $backlogByQueue
                ->pluck('queue')
                ->merge($recentFailedJobs->pluck('queue'))
                ->filter()
                ->unique()
                ->count(),
            'impacted_companies' => $companyIds,
        ];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    public function backlogByQueue(): array
    {
        $currentTimestamp = now()->timestamp;
        $failedByQueue = (clone $this->failedJobsQuery())
            ->selectRaw('queue, COUNT(*) as aggregate_count')
            ->groupBy('queue')
            ->pluck('aggregate_count', 'queue');

        $rows = $this->jobsQuery()
            ->selectRaw(
                'queue,
                COUNT(*) as total_jobs,
                SUM(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN 1 ELSE 0 END) as ready_jobs,
                SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) as reserved_jobs,
                SUM(CASE WHEN reserved_at IS NULL AND available_at > ? THEN 1 ELSE 0 END) as delayed_jobs',
                [$currentTimestamp, $currentTimestamp]
            )
            ->groupBy('queue')
            ->orderByDesc('total_jobs')
            ->get()
            ->map(function ($row) use ($failedByQueue) {
                return [
                    'queue' => (string) $row->queue,
                    'total_jobs' => (int) $row->total_jobs,
                    'ready_jobs' => (int) $row->ready_jobs,
                    'reserved_jobs' => (int) $row->reserved_jobs,
                    'delayed_jobs' => (int) $row->delayed_jobs,
                    'failed_jobs' => (int) ($failedByQueue[(string) $row->queue] ?? 0),
                ];
            });

        $missingFailedQueues = collect($failedByQueue)
            ->keys()
            ->reject(fn (string $queue) => $rows->contains(fn (array $row) => $row['queue'] === $queue))
            ->map(fn (string $queue) => [
                'queue' => $queue,
                'total_jobs' => 0,
                'ready_jobs' => 0,
                'reserved_jobs' => 0,
                'delayed_jobs' => 0,
                'failed_jobs' => (int) ($failedByQueue[$queue] ?? 0),
            ]);

        return $rows
            ->concat($missingFailedQueues)
            ->sortByDesc(fn (array $row) => $row['failed_jobs'] + $row['total_jobs'])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{job_name: string, count: int}>
     */
    public function topFailedJobTypes(int $limit = 8): array
    {
        return collect($this->recentFailedJobSnapshots())
            ->groupBy(fn (array $row) => $row['job_name_label'] ?: 'Unknown job')
            ->map(fn (Collection $group, string $jobName) => [
                'job_name' => $jobName,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{reason: string, count: int}>
     */
    public function topFailureReasons(int $limit = 8): array
    {
        return collect($this->recentFailedJobSnapshots())
            ->groupBy(fn (array $row) => $row['exception_class'] ?: 'Unknown failure')
            ->map(fn (Collection $group, string $reason) => [
                'reason' => $reason,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{company_id: string, company_name: string, count: int}>
     */
    public function companyImpact(int $limit = 8): array
    {
        $companyCounts = [];

        WebhookDelivery::withoutGlobalScopes()
            ->selectRaw('company_id, COUNT(*) as aggregate_count')
            ->where('status', WebhookDelivery::STATUS_DEAD)
            ->groupBy('company_id')
            ->get()
            ->each(function ($row) use (&$companyCounts): void {
                $companyCounts[(string) $row->company_id] = ($companyCounts[(string) $row->company_id] ?? 0)
                    + (int) $row->aggregate_count;
            });

        ReportExport::withoutGlobalScopes()
            ->selectRaw('company_id, COUNT(*) as aggregate_count')
            ->where('status', ReportExport::STATUS_FAILED)
            ->groupBy('company_id')
            ->get()
            ->each(function ($row) use (&$companyCounts): void {
                $companyCounts[(string) $row->company_id] = ($companyCounts[(string) $row->company_id] ?? 0)
                    + (int) $row->aggregate_count;
            });

        collect($this->recentFailedJobSnapshots())
            ->pluck('company_id')
            ->filter()
            ->each(function (string $companyId) use (&$companyCounts): void {
                $companyCounts[$companyId] = ($companyCounts[$companyId] ?? 0) + 1;
            });

        $companies = Company::query()
            ->whereIn('id', array_keys($companyCounts))
            ->get(['id', 'name'])
            ->keyBy('id');

        return collect($companyCounts)
            ->map(function (int $count, string $companyId) use ($companies) {
                return [
                    'company_id' => $companyId,
                    'company_name' => $companies->get($companyId)?->name ?? 'Unknown company',
                    'count' => $count,
                ];
            })
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function failedJobs(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $queue = trim((string) ($filters['queue'] ?? ''));

        $paginator = $this->failedJobsQuery()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('uuid', 'like', "%{$search}%")
                        ->orWhere('queue', 'like', "%{$search}%")
                        ->orWhere('exception', 'like', "%{$search}%")
                        ->orWhere('payload', 'like', "%{$search}%");
                });
            })
            ->when($queue !== '', fn (Builder $query) => $query->where('queue', $queue))
            ->orderByDesc('failed_at')
            ->paginate($perPage)
            ->withQueryString();

        $snapshots = collect($paginator->items())
            ->map(fn ($record) => $this->mapFailedJobRecord($record));

        $companyNames = Company::query()
            ->whereIn('id', $snapshots->pluck('company_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        $userNames = User::query()
            ->whereIn('id', $snapshots->pluck('user_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        $paginator->setCollection(
            $snapshots
                ->map(function (array $snapshot) use ($companyNames, $userNames) {
                    $snapshot['company_name'] = $snapshot['company_id']
                        ? ($companyNames[$snapshot['company_id']] ?? 'Unknown company')
                        : null;
                    $snapshot['user_name'] = $snapshot['user_id']
                        ? ($userNames[$snapshot['user_id']] ?? 'Unknown user')
                        : null;

                    return $snapshot;
                })
        );

        return $paginator;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function queueOptions(): array
    {
        return collect($this->backlogByQueue())
            ->pluck('queue')
            ->merge(collect($this->recentFailedJobSnapshots())->pluck('queue'))
            ->filter()
            ->unique()
            ->sort()
            ->map(fn (string $queue) => [
                'value' => $queue,
                'label' => $queue,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentDeadWebhookDeliveries(int $limit = 8): array
    {
        return WebhookDelivery::withoutGlobalScopes()
            ->with([
                'company:id,name',
                'endpoint:id,name,target_url',
                'integrationEvent:id,event_type,occurred_at',
            ])
            ->where('status', WebhookDelivery::STATUS_DEAD)
            ->latest('dead_lettered_at')
            ->limit($limit)
            ->get()
            ->map(function (WebhookDelivery $delivery) {
                return [
                    'id' => $delivery->id,
                    'company_id' => $delivery->company_id,
                    'company_name' => $delivery->company?->name,
                    'endpoint_name' => $delivery->endpoint?->name,
                    'event_label' => $this->webhookEventCatalog->label((string) $delivery->event_type),
                    'attempt_count' => (int) $delivery->attempt_count,
                    'failure_message' => $delivery->failure_message,
                    'response_status' => $delivery->response_status,
                    'dead_lettered_at' => $delivery->dead_lettered_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function failedReportExports(int $limit = 8): array
    {
        return ReportExport::withoutGlobalScopes()
            ->with([
                'company:id,name',
                'requestedBy:id,name,email',
            ])
            ->where('status', ReportExport::STATUS_FAILED)
            ->latest('failed_at')
            ->limit($limit)
            ->get()
            ->map(function (ReportExport $export) {
                return [
                    'id' => $export->id,
                    'company_id' => $export->company_id,
                    'company_name' => $export->company?->name,
                    'report_key' => $export->report_key,
                    'report_title' => $export->report_title ?: $this->reportKeyLabel((string) $export->report_key),
                    'format' => $export->format,
                    'requested_by_name' => $export->requestedBy?->name,
                    'failed_at' => $export->failed_at?->toIso8601String(),
                    'failure_message' => $export->failure_message,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{status: 'retried'|'failed_again', message: string}
     */
    public function retryFailedJob(string $failedJobId, ?string $actorId = null): array
    {
        $record = $this->failedJobProvider->find($failedJobId);

        abort_if(! $record, 404, 'Failed job was not found.');

        $snapshot = $this->mapFailedJobRecord($record);
        $payload = $this->refreshRetryUntil($this->resetAttempts((string) $record->payload));

        $this->failedJobProvider->forget($failedJobId);

        try {
            $this->dispatchFailedJobPayload(
                connectionName: (string) $record->connection,
                queue: (string) $record->queue,
                payload: $payload,
            );

            $this->recordAudit(
                action: 'platform_queue_job_retried',
                actorId: $actorId,
                auditableId: $failedJobId,
                companyId: $snapshot['company_id'],
                changes: [
                    'before' => ['status' => 'failed'],
                    'after' => ['status' => 'retried'],
                ],
                metadata: [
                    'queue' => $snapshot['queue'],
                    'job_name' => $snapshot['job_name'],
                    'request_id' => $snapshot['request_id'],
                ],
            );

            return [
                'status' => 'retried',
                'message' => 'Failed job was requeued successfully.',
            ];
        } catch (Throwable $exception) {
            $this->failedJobProvider->log(
                (string) $record->connection,
                (string) $record->queue,
                $payload,
                $exception,
            );

            $this->recordAudit(
                action: 'platform_queue_job_retry_failed',
                actorId: $actorId,
                auditableId: $failedJobId,
                companyId: $snapshot['company_id'],
                changes: [
                    'before' => ['status' => 'failed'],
                    'after' => ['status' => 'failed'],
                ],
                metadata: [
                    'queue' => $snapshot['queue'],
                    'job_name' => $snapshot['job_name'],
                    'request_id' => $snapshot['request_id'],
                    'exception' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ],
            );

            return [
                'status' => 'failed_again',
                'message' => 'Retry executed immediately and failed again.',
            ];
        }
    }

    public function forgetFailedJob(string $failedJobId, ?string $actorId = null): bool
    {
        $record = $this->failedJobProvider->find($failedJobId);

        abort_if(! $record, 404, 'Failed job was not found.');

        $snapshot = $this->mapFailedJobRecord($record);
        $deleted = $this->failedJobProvider->forget($failedJobId);

        if ($deleted) {
            $this->recordAudit(
                action: 'platform_queue_job_forgotten',
                actorId: $actorId,
                auditableId: $failedJobId,
                companyId: $snapshot['company_id'],
                changes: [
                    'before' => ['status' => 'failed'],
                    'after' => ['status' => 'forgotten'],
                ],
                metadata: [
                    'queue' => $snapshot['queue'],
                    'job_name' => $snapshot['job_name'],
                    'request_id' => $snapshot['request_id'],
                ],
            );
        }

        return $deleted;
    }

    public function retryWebhookDelivery(WebhookDelivery $delivery, ?string $actorId = null): WebhookDelivery
    {
        $beforeStatus = $delivery->status;
        $retried = $this->webhookDeliveryService->retry($delivery, $actorId);

        $this->recordAudit(
            action: 'platform_webhook_retried',
            actorId: $actorId,
            auditableId: (string) $delivery->id,
            companyId: (string) $delivery->company_id,
            changes: [
                'before' => ['status' => $beforeStatus],
                'after' => ['status' => $retried->status],
            ],
            metadata: [
                'event_type' => $delivery->event_type,
                'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
            ],
        );

        return $retried;
    }

    public function retryReportExport(ReportExport $reportExport, ?string $actorId = null): ReportExport
    {
        $beforeStatus = $reportExport->status;
        $retried = $this->reportExportWorkflowService->retry($reportExport, $actorId);

        $this->recordAudit(
            action: 'platform_report_export_retried',
            actorId: $actorId,
            auditableId: (string) $reportExport->id,
            companyId: (string) $reportExport->company_id,
            changes: [
                'before' => ['status' => $beforeStatus],
                'after' => ['status' => $retried->status],
            ],
            metadata: [
                'report_key' => $reportExport->report_key,
                'format' => $reportExport->format,
            ],
        );

        return $retried;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentFailedJobSnapshots(int $limit = 200): array
    {
        if ($this->recentFailedJobSnapshots !== null) {
            return $this->recentFailedJobSnapshots;
        }

        $companyNames = Company::query()
            ->pluck('name', 'id');
        $userNames = User::query()
            ->pluck('name', 'id');

        $this->recentFailedJobSnapshots = $this->failedJobsQuery()
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(function ($record) use ($companyNames, $userNames) {
                $snapshot = $this->mapFailedJobRecord($record);
                $snapshot['company_name'] = $snapshot['company_id']
                    ? ($companyNames[$snapshot['company_id']] ?? 'Unknown company')
                    : null;
                $snapshot['user_name'] = $snapshot['user_id']
                    ? ($userNames[$snapshot['user_id']] ?? 'Unknown user')
                    : null;

                return $snapshot;
            })
            ->values()
            ->all();

        return $this->recentFailedJobSnapshots;
    }

    private function failedJobsQuery(): Builder
    {
        return DB::connection((string) config('queue.failed.database'))
            ->table((string) config('queue.failed.table', 'failed_jobs'));
    }

    private function jobsQuery(): Builder
    {
        $connection = config('queue.connections.database.connection')
            ?: config('database.default');

        return DB::connection((string) $connection)
            ->table((string) config('queue.connections.database.table', 'jobs'));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapFailedJobRecord(object $record): array
    {
        $payload = json_decode((string) $record->payload, true);
        $payload = is_array($payload) ? $payload : [];
        $context = is_array($payload['port101_context'] ?? null)
            ? $payload['port101_context']
            : [];

        $jobName = (string) ($payload['displayName']
            ?? data_get($payload, 'data.commandName')
            ?? 'Unknown job');

        $exceptionClass = $this->exceptionClass((string) $record->exception);
        $exceptionMessage = $this->exceptionMessage((string) $record->exception);

        return [
            'id' => (string) ($record->uuid ?? $record->id),
            'queue' => (string) $record->queue,
            'connection' => (string) $record->connection,
            'job_name' => $jobName,
            'job_name_label' => class_basename($jobName),
            'request_id' => $context['request_id'] ?? null,
            'company_id' => $context['company_id'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'parent_job_id' => $context['parent_job_id'] ?? null,
            'correlation_origin' => $context['correlation_origin'] ?? null,
            'job_uuid' => $payload['uuid'] ?? null,
            'failed_at' => optional($record->failed_at)->toIso8601String() ?? (string) $record->failed_at,
            'exception_class' => $exceptionClass,
            'exception_message' => $exceptionMessage,
            'can_retry' => true,
            'can_forget' => true,
        ];
    }

    private function dispatchFailedJobPayload(
        string $connectionName,
        string $queue,
        string $payload,
    ): void {
        $connection = $this->queueFactory->connection($connectionName);
        $job = $this->extractQueuedCommand($payload);

        if ($job !== null) {
            $connection->push($job, '', $queue);

            return;
        }

        $connection->pushRaw($payload, $queue);
    }

    private function resetAttempts(string $payload): string
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return $payload;
        }

        if (isset($decoded['attempts'])) {
            $decoded['attempts'] = 0;
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE) ?: $payload;
    }

    private function refreshRetryUntil(string $payload): string
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return $payload;
        }

        $job = $this->extractQueuedCommand($payload);

        if ($job !== null && method_exists($job, 'retryUntil')) {
            $retryUntil = $job->retryUntil();
            $decoded['retryUntil'] = $retryUntil instanceof \DateTimeInterface
                ? $retryUntil->getTimestamp()
                : $retryUntil;
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE) ?: $payload;
    }

    private function extractQueuedCommand(string $payload): ?object
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return null;
        }

        $command = data_get($decoded, 'data.command');

        if (! is_string($command) || trim($command) === '') {
            return null;
        }

        if (str_starts_with($command, 'O:')) {
            $resolved = @unserialize($command);

            return is_object($resolved) && ! $resolved instanceof \__PHP_Incomplete_Class
                ? $resolved
                : null;
        }

        if (! $this->encrypter) {
            return null;
        }

        try {
            $resolved = @unserialize($this->encrypter->decrypt($command));
        } catch (Throwable) {
            return null;
        }

        return is_object($resolved) && ! $resolved instanceof \__PHP_Incomplete_Class
            ? $resolved
            : null;
    }

    private function exceptionClass(string $exception): string
    {
        $line = Str::before($exception, PHP_EOL);

        if (preg_match('/^([A-Za-z0-9_\\\\]+)(?::|$)/', $line, $matches) === 1) {
            return $matches[1];
        }

        return 'Unknown failure';
    }

    private function exceptionMessage(string $exception): string
    {
        $line = trim(Str::before($exception, PHP_EOL));

        return Str::limit($line !== '' ? $line : 'No failure message captured.', 220);
    }

    private function reportKeyLabel(string $reportKey): string
    {
        return Str::of($reportKey)
            ->replace('-', ' ')
            ->title()
            ->toString();
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $metadata
     */
    private function recordAudit(
        string $action,
        ?string $actorId,
        string $auditableId,
        ?string $companyId,
        array $changes = [],
        array $metadata = [],
    ): void {
        if (! $companyId) {
            return;
        }

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $actorId,
            'auditable_type' => 'platform_queue_health',
            'auditable_id' => $auditableId,
            'action' => $action,
            'changes' => $changes,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
