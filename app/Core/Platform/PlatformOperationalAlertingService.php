<?php

namespace App\Core\Platform;

use App\Core\Notifications\NotificationGovernanceService;
use App\Core\Platform\Models\PlatformAlertIncident;
use App\Core\Settings\SettingsService;
use App\Models\User;
use App\Notifications\PlatformOperationsAlertNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PlatformOperationalAlertingService
{
    public const HEARTBEAT_KEY = 'platform.alerting.scheduler.last_heartbeat_at';

    public const LAST_SCAN_KEY = 'platform.alerting.last_scan_at';

    /**
     * @var array<int, string>
     */
    private const SETTINGS_KEYS = [
        'platform.alerting.enabled',
        'platform.alerting.cooldown_minutes',
        'platform.alerting.failed_jobs_threshold',
        'platform.alerting.queue_backlog_threshold',
        'platform.alerting.dead_webhook_threshold',
        'platform.alerting.failed_report_export_threshold',
        'platform.alerting.scheduler_drift_minutes',
    ];

    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly QueueHealthService $queueHealthService,
        private readonly NotificationGovernanceService $notificationGovernance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $defaults = (array) config('core.platform_alerting', []);
        $stored = $this->settingsService->getMany(self::SETTINGS_KEYS);

        return [
            'enabled' => filter_var(
                $stored['platform.alerting.enabled'] ?? $defaults['enabled'] ?? true,
                FILTER_VALIDATE_BOOLEAN
            ),
            'cooldown_minutes' => max(
                1,
                min(1440, (int) ($stored['platform.alerting.cooldown_minutes'] ?? $defaults['cooldown_minutes'] ?? 30))
            ),
            'failed_jobs_threshold' => max(
                0,
                min(100000, (int) ($stored['platform.alerting.failed_jobs_threshold'] ?? $defaults['failed_jobs_threshold'] ?? 5))
            ),
            'queue_backlog_threshold' => max(
                0,
                min(100000, (int) ($stored['platform.alerting.queue_backlog_threshold'] ?? $defaults['queue_backlog_threshold'] ?? 50))
            ),
            'dead_webhook_threshold' => max(
                0,
                min(100000, (int) ($stored['platform.alerting.dead_webhook_threshold'] ?? $defaults['dead_webhook_threshold'] ?? 5))
            ),
            'failed_report_export_threshold' => max(
                0,
                min(100000, (int) ($stored['platform.alerting.failed_report_export_threshold'] ?? $defaults['failed_report_export_threshold'] ?? 3))
            ),
            'scheduler_drift_minutes' => max(
                1,
                min(1440, (int) ($stored['platform.alerting.scheduler_drift_minutes'] ?? $defaults['scheduler_drift_minutes'] ?? 10))
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setSettings(array $data, ?string $actorId = null): void
    {
        $sanitized = [
            'platform.alerting.enabled' => (bool) ($data['enabled'] ?? true),
            'platform.alerting.cooldown_minutes' => max(1, min(1440, (int) ($data['cooldown_minutes'] ?? 30))),
            'platform.alerting.failed_jobs_threshold' => max(0, min(100000, (int) ($data['failed_jobs_threshold'] ?? 5))),
            'platform.alerting.queue_backlog_threshold' => max(0, min(100000, (int) ($data['queue_backlog_threshold'] ?? 50))),
            'platform.alerting.dead_webhook_threshold' => max(0, min(100000, (int) ($data['dead_webhook_threshold'] ?? 5))),
            'platform.alerting.failed_report_export_threshold' => max(0, min(100000, (int) ($data['failed_report_export_threshold'] ?? 3))),
            'platform.alerting.scheduler_drift_minutes' => max(1, min(1440, (int) ($data['scheduler_drift_minutes'] ?? 10))),
        ];

        foreach ($sanitized as $key => $value) {
            $this->settingsService->set($key, $value, null, null, $actorId);
        }
    }

    public function recordSchedulerHeartbeat(?CarbonImmutable $capturedAt = null): void
    {
        $capturedAt ??= CarbonImmutable::now();

        $this->settingsService->set(
            self::HEARTBEAT_KEY,
            $capturedAt->toIso8601String(),
        );
    }

    /**
     * @return array{
     *     last_scan_at: string|null,
     *     heartbeat: array{last_seen_at: string|null, minutes_since: int|null, is_stale: bool},
     *     active_incidents: array<int, array<string, mixed>>,
     *     recent_resolved_incidents: array<int, array<string, mixed>>
     * }
     */
    public function getStatus(): array
    {
        $settings = $this->getSettings();
        $heartbeatAt = $this->lastHeartbeatAt();
        $now = CarbonImmutable::now();
        $minutesSinceHeartbeat = $heartbeatAt
            ? $heartbeatAt->diffInMinutes($now)
            : null;

        return [
            'last_scan_at' => $this->lastScanAt()?->toIso8601String(),
            'heartbeat' => [
                'last_seen_at' => $heartbeatAt?->toIso8601String(),
                'minutes_since' => $minutesSinceHeartbeat,
                'is_stale' => $minutesSinceHeartbeat !== null
                    ? $minutesSinceHeartbeat >= (int) $settings['scheduler_drift_minutes']
                    : true,
            ],
            'active_incidents' => $this->mapIncidents(
                PlatformAlertIncident::query()
                    ->where('status', PlatformAlertIncident::STATUS_OPEN)
                    ->orderByDesc('last_triggered_at')
                    ->get()
            ),
            'recent_resolved_incidents' => $this->mapIncidents(
                PlatformAlertIncident::query()
                    ->where('status', PlatformAlertIncident::STATUS_RESOLVED)
                    ->orderByDesc('resolved_at')
                    ->limit(5)
                    ->get()
            ),
        ];
    }

    /**
     * @return array{opened: int, notified: int, resolved: int, active: int}
     */
    public function scan(bool $force = false, ?CarbonImmutable $scannedAt = null): array
    {
        $scannedAt ??= CarbonImmutable::now();

        $this->settingsService->set(
            self::LAST_SCAN_KEY,
            $scannedAt->toIso8601String(),
        );

        $settings = $this->getSettings();

        if (! $settings['enabled']) {
            return [
                'opened' => 0,
                'notified' => 0,
                'resolved' => 0,
                'active' => PlatformAlertIncident::query()
                    ->where('status', PlatformAlertIncident::STATUS_OPEN)
                    ->count(),
            ];
        }

        $summary = $this->queueHealthService->summary();
        $heartbeatAt = $this->lastHeartbeatAt();
        $heartbeatLag = $heartbeatAt
            ? $heartbeatAt->diffInMinutes($scannedAt)
            : (int) $settings['scheduler_drift_minutes'] + 1;

        $result = [
            'opened' => 0,
            'notified' => 0,
            'resolved' => 0,
            'active' => 0,
        ];

        $rules = [
            [
                'key' => 'failed_jobs',
                'severity' => 'high',
                'title' => 'Queue failures exceeded threshold',
                'message' => "{$summary['failed_jobs']} failed jobs are currently recorded across the platform.",
                'metric_value' => (int) $summary['failed_jobs'],
                'threshold_value' => (int) $settings['failed_jobs_threshold'],
                'metadata' => [
                    'summary_key' => 'failed_jobs',
                    'window_failed_jobs_last_24_hours' => $summary['failed_jobs_last_24_hours'],
                ],
            ],
            [
                'key' => 'queue_backlog',
                'severity' => 'high',
                'title' => 'Queue backlog exceeded threshold',
                'message' => "{$summary['ready_jobs']} ready jobs are waiting in the platform queues.",
                'metric_value' => (int) $summary['ready_jobs'],
                'threshold_value' => (int) $settings['queue_backlog_threshold'],
                'metadata' => [
                    'summary_key' => 'ready_jobs',
                    'queued_jobs' => $summary['queued_jobs'],
                    'reserved_jobs' => $summary['reserved_jobs'],
                    'delayed_jobs' => $summary['delayed_jobs'],
                ],
            ],
            [
                'key' => 'dead_webhooks',
                'severity' => 'high',
                'title' => 'Dead webhook deliveries exceeded threshold',
                'message' => "{$summary['dead_webhook_deliveries']} webhook deliveries are currently dead-lettered.",
                'metric_value' => (int) $summary['dead_webhook_deliveries'],
                'threshold_value' => (int) $settings['dead_webhook_threshold'],
                'metadata' => [
                    'summary_key' => 'dead_webhook_deliveries',
                ],
            ],
            [
                'key' => 'failed_report_exports',
                'severity' => 'medium',
                'title' => 'Failed report exports exceeded threshold',
                'message' => "{$summary['failed_report_exports']} report exports are currently failed.",
                'metric_value' => (int) $summary['failed_report_exports'],
                'threshold_value' => (int) $settings['failed_report_export_threshold'],
                'metadata' => [
                    'summary_key' => 'failed_report_exports',
                ],
            ],
            [
                'key' => 'scheduler_drift',
                'severity' => 'critical',
                'title' => 'Scheduler heartbeat is stale',
                'message' => $heartbeatAt
                    ? "Scheduler heartbeat is {$heartbeatLag} minute(s) old."
                    : 'Scheduler heartbeat has not been recorded yet.',
                'metric_value' => $heartbeatLag,
                'threshold_value' => (int) $settings['scheduler_drift_minutes'],
                'metadata' => [
                    'summary_key' => 'scheduler_drift_minutes',
                    'last_heartbeat_at' => $heartbeatAt?->toIso8601String(),
                ],
            ],
        ];

        foreach ($rules as $rule) {
            $evaluation = $this->evaluateRule(
                key: (string) $rule['key'],
                severity: (string) $rule['severity'],
                title: (string) $rule['title'],
                message: (string) $rule['message'],
                metricValue: (int) $rule['metric_value'],
                thresholdValue: (int) $rule['threshold_value'],
                cooldownMinutes: (int) $settings['cooldown_minutes'],
                metadata: (array) $rule['metadata'],
                scannedAt: $scannedAt,
                force: $force,
            );

            $result['opened'] += $evaluation['opened'];
            $result['notified'] += $evaluation['notified'];
            $result['resolved'] += $evaluation['resolved'];
        }

        $result['active'] = PlatformAlertIncident::query()
            ->where('status', PlatformAlertIncident::STATUS_OPEN)
            ->count();

        return $result;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{opened: int, notified: int, resolved: int}
     */
    private function evaluateRule(
        string $key,
        string $severity,
        string $title,
        string $message,
        int $metricValue,
        int $thresholdValue,
        int $cooldownMinutes,
        array $metadata,
        CarbonImmutable $scannedAt,
        bool $force = false,
    ): array {
        $incident = PlatformAlertIncident::query()
            ->where('alert_key', $key)
            ->where('status', PlatformAlertIncident::STATUS_OPEN)
            ->latest('first_triggered_at')
            ->first();

        $triggered = $thresholdValue > 0 && $metricValue >= $thresholdValue;
        $result = [
            'opened' => 0,
            'notified' => 0,
            'resolved' => 0,
        ];

        if ($triggered) {
            if (! $incident) {
                $incident = PlatformAlertIncident::query()->create([
                    'alert_key' => $key,
                    'status' => PlatformAlertIncident::STATUS_OPEN,
                    'severity' => $severity,
                    'title' => $title,
                    'message' => $message,
                    'metric_value' => $metricValue,
                    'threshold_value' => $thresholdValue,
                    'metadata' => $metadata,
                    'first_triggered_at' => $scannedAt,
                    'last_triggered_at' => $scannedAt,
                    'last_notified_at' => null,
                ]);

                $result['opened']++;
            } else {
                $incident->forceFill([
                    'severity' => $severity,
                    'title' => $title,
                    'message' => $message,
                    'metric_value' => $metricValue,
                    'threshold_value' => $thresholdValue,
                    'metadata' => $metadata,
                    'last_triggered_at' => $scannedAt,
                    'resolved_at' => null,
                ])->save();
            }

            $shouldNotify = $force
                || ! $incident->last_notified_at
                || $incident->last_notified_at->addMinutes($cooldownMinutes)->lte($scannedAt);

            if ($shouldNotify) {
                $this->notifyOpenIncident($incident);

                $incident->forceFill([
                    'last_notified_at' => $scannedAt,
                ])->save();

                $result['notified']++;
            }

            return $result;
        }

        if ($incident) {
            $incident->forceFill([
                'status' => PlatformAlertIncident::STATUS_RESOLVED,
                'resolved_at' => $scannedAt,
                'last_triggered_at' => $scannedAt,
                'metric_value' => $metricValue,
                'threshold_value' => $thresholdValue,
                'message' => $message,
                'metadata' => $metadata,
            ])->save();

            $result['resolved']++;
        }

        return $result;
    }

    private function notifyOpenIncident(PlatformAlertIncident $incident): void
    {
        $recipients = User::query()
            ->where('is_super_admin', true)
            ->get(['id', 'name', 'email']);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notificationGovernance->notify(
            recipients: $recipients,
            notification: new PlatformOperationsAlertNotification(
                title: $incident->title,
                message: $incident->message,
                severity: $incident->severity,
                status: $incident->status,
                url: '/platform/operations/queue-health',
                meta: [
                    'alert_key' => $incident->alert_key,
                    'metric_value' => $incident->metric_value,
                    'threshold_value' => $incident->threshold_value,
                    'triggered_at' => $incident->first_triggered_at?->toIso8601String(),
                    'details' => $incident->metadata,
                ],
            ),
            severity: $incident->severity,
            context: [
                'event' => $incident->title,
                'source' => 'platform.operations.alerting',
            ],
        );
    }

    private function lastHeartbeatAt(): ?CarbonImmutable
    {
        return $this->parseTimestamp($this->settingsService->get(self::HEARTBEAT_KEY));
    }

    private function lastScanAt(): ?CarbonImmutable
    {
        return $this->parseTimestamp($this->settingsService->get(self::LAST_SCAN_KEY));
    }

    private function parseTimestamp(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  Collection<int, PlatformAlertIncident>  $incidents
     * @return array<int, array<string, mixed>>
     */
    private function mapIncidents(Collection $incidents): array
    {
        return $incidents
            ->map(fn (PlatformAlertIncident $incident) => [
                'id' => $incident->id,
                'alert_key' => $incident->alert_key,
                'status' => $incident->status,
                'severity' => $incident->severity,
                'title' => $incident->title,
                'message' => $incident->message,
                'metric_value' => $incident->metric_value,
                'threshold_value' => $incident->threshold_value,
                'first_triggered_at' => $incident->first_triggered_at?->toIso8601String(),
                'last_triggered_at' => $incident->last_triggered_at?->toIso8601String(),
                'last_notified_at' => $incident->last_notified_at?->toIso8601String(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'metadata' => $incident->metadata ?? [],
            ])
            ->values()
            ->all();
    }
}
