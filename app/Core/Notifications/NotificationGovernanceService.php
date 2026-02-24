<?php

namespace App\Core\Notifications;

use App\Core\Settings\SettingsService;
use App\Models\User;
use App\Notifications\NotificationGovernanceEscalationNotification;
use App\Notifications\PlatformNotificationDigestNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationGovernanceService
{
    /**
     * @var array<string, int>
     */
    private const SEVERITY_ORDER = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'critical' => 4,
    ];

    /**
     * @var array<int, string>
     */
    private const GOVERNANCE_KEYS = [
        'platform.notifications.min_severity',
        'platform.notifications.escalation_enabled',
        'platform.notifications.escalation_severity',
        'platform.notifications.escalation_delay_minutes',
        'platform.notifications.digest_enabled',
        'platform.notifications.digest_frequency',
        'platform.notifications.digest_day_of_week',
        'platform.notifications.digest_time',
        'platform.notifications.digest_timezone',
        'platform.notifications.noisy_event_threshold',
    ];

    public function __construct(
        private SettingsService $settingsService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $defaults = (array) config('core.notifications.governance', []);
        $stored = $this->settingsService->getMany(self::GOVERNANCE_KEYS);

        $minSeverity = $this->normalizeSeverity(
            $stored['platform.notifications.min_severity'] ?? $defaults['min_severity'] ?? 'low'
        );
        $escalationSeverity = $this->normalizeSeverity(
            $stored['platform.notifications.escalation_severity'] ?? $defaults['escalation_severity'] ?? 'high'
        );
        $escalationDelay = (int) ($stored['platform.notifications.escalation_delay_minutes']
            ?? $defaults['escalation_delay_minutes']
            ?? 30);
        $digestFrequency = (string) ($stored['platform.notifications.digest_frequency']
            ?? $defaults['digest_frequency']
            ?? 'daily');
        $digestDay = (int) ($stored['platform.notifications.digest_day_of_week']
            ?? $defaults['digest_day_of_week']
            ?? 1);
        $digestTime = (string) ($stored['platform.notifications.digest_time']
            ?? $defaults['digest_time']
            ?? '08:00');
        $digestTimezone = (string) ($stored['platform.notifications.digest_timezone']
            ?? $defaults['digest_timezone']
            ?? 'UTC');

        if (! in_array($digestFrequency, ['daily', 'weekly'], true)) {
            $digestFrequency = 'daily';
        }

        if ($digestDay < 1 || $digestDay > 7) {
            $digestDay = 1;
        }

        if (! preg_match('/^\d{2}:\d{2}$/', $digestTime)) {
            $digestTime = '08:00';
        }

        return [
            'min_severity' => $minSeverity,
            'escalation_enabled' => filter_var(
                $stored['platform.notifications.escalation_enabled']
                    ?? $defaults['escalation_enabled']
                    ?? false,
                FILTER_VALIDATE_BOOLEAN
            ),
            'escalation_severity' => $escalationSeverity,
            'escalation_delay_minutes' => max(1, min(1440, $escalationDelay)),
            'digest_enabled' => filter_var(
                $stored['platform.notifications.digest_enabled']
                    ?? $defaults['digest_enabled']
                    ?? true,
                FILTER_VALIDATE_BOOLEAN
            ),
            'digest_frequency' => $digestFrequency,
            'digest_day_of_week' => $digestDay,
            'digest_time' => $digestTime,
            'digest_timezone' => $digestTimezone,
            'noisy_event_threshold' => max(
                1,
                min(
                    100,
                    (int) ($stored['platform.notifications.noisy_event_threshold']
                        ?? $defaults['noisy_event_threshold']
                        ?? 3)
                )
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setSettings(array $data, ?string $actorId = null): void
    {
        $sanitized = [
            'platform.notifications.min_severity' => $this->normalizeSeverity(
                $data['min_severity'] ?? 'low'
            ),
            'platform.notifications.escalation_enabled' => (bool) ($data['escalation_enabled'] ?? false),
            'platform.notifications.escalation_severity' => $this->normalizeSeverity(
                $data['escalation_severity'] ?? 'high'
            ),
            'platform.notifications.escalation_delay_minutes' => max(
                1,
                min(1440, (int) ($data['escalation_delay_minutes'] ?? 30))
            ),
            'platform.notifications.digest_enabled' => (bool) ($data['digest_enabled'] ?? true),
            'platform.notifications.digest_frequency' => in_array(
                (string) ($data['digest_frequency'] ?? 'daily'),
                ['daily', 'weekly'],
                true
            ) ? (string) $data['digest_frequency'] : 'daily',
            'platform.notifications.digest_day_of_week' => max(
                1,
                min(7, (int) ($data['digest_day_of_week'] ?? 1))
            ),
            'platform.notifications.digest_time' => preg_match(
                '/^\d{2}:\d{2}$/',
                (string) ($data['digest_time'] ?? '')
            ) ? (string) $data['digest_time'] : '08:00',
            'platform.notifications.digest_timezone' => (string) ($data['digest_timezone'] ?? 'UTC'),
            'platform.notifications.noisy_event_threshold' => max(
                1,
                min(100, (int) ($data['noisy_event_threshold'] ?? 3))
            ),
        ];

        foreach ($sanitized as $key => $value) {
            $this->settingsService->set($key, $value, null, null, $actorId);
        }
    }

    public function allowsSeverity(string $severity): bool
    {
        $settings = $this->getSettings();

        return $this->severityRank($severity) >= $this->severityRank($settings['min_severity']);
    }

    /**
     * @param  iterable<int, User>|Collection<int, User>  $recipients
     * @param  array<string, mixed>  $context
     */
    public function notify(
        iterable $recipients,
        Notification $notification,
        string $severity = 'low',
        array $context = []
    ): void {
        if (! $this->allowsSeverity($severity)) {
            return;
        }

        $users = $this->normalizeRecipients($recipients);

        if ($users->isEmpty()) {
            return;
        }

        NotificationFacade::send($users, $notification);
        $this->handleEscalation($users, $severity, $context);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function shouldSendDigestNow(
        array $settings,
        ?CarbonImmutable $now = null
    ): bool {
        if (! ($settings['digest_enabled'] ?? false)) {
            return false;
        }

        $timezone = (string) ($settings['digest_timezone'] ?? 'UTC');
        $time = (string) ($settings['digest_time'] ?? '08:00');
        $current = $now
            ? $now->setTimezone($timezone)
            : CarbonImmutable::now($timezone);

        if ($current->format('H:i') !== $time) {
            return false;
        }

        if (($settings['digest_frequency'] ?? 'daily') === 'weekly') {
            $day = (int) ($settings['digest_day_of_week'] ?? 1);

            return (int) $current->format('N') === $day;
        }

        return true;
    }

    /**
     * @return array{
     *  window_days: int,
     *  noisy_event_threshold: int,
     *  escalations: array{triggered: int, acknowledged: int, pending: int, acknowledgement_rate: float},
     *  digest_coverage: array{sent: int, opened: int, open_rate: float, total_notifications_summarized: int},
     *  noisy_events: array<int, array{event: string, count: int, unread: int, high_or_critical: int, source: string}>,
     *  source_segmentation: array<int, array{source: string, count: int, unread: int, high_or_critical: int, escalations: int}>,
     *  time_series: array<int, array{
     *      date: string,
     *      notifications_total: int,
     *      escalations_triggered: int,
     *      escalations_acknowledged: int,
     *      digests_sent: int,
     *      digests_opened: int
     *  }>
     * }
     */
    public function getAnalytics(int $windowDays = 30): array
    {
        $settings = $this->getSettings();
        $noisyThreshold = max(1, min(100, (int) ($settings['noisy_event_threshold'] ?? 3)));
        $window = in_array($windowDays, [7, 30, 90], true)
            ? $windowDays
            : 30;
        $start = CarbonImmutable::now()->startOfDay()->subDays($window - 1);
        $today = CarbonImmutable::now()->startOfDay();

        $timeSeries = [];
        for ($day = $start; $day->lte($today); $day = $day->addDay()) {
            $timeSeries[$day->toDateString()] = [
                'date' => $day->toDateString(),
                'notifications_total' => 0,
                'escalations_triggered' => 0,
                'escalations_acknowledged' => 0,
                'digests_sent' => 0,
                'digests_opened' => 0,
            ];
        }

        $superAdminIds = User::query()
            ->where('is_super_admin', true)
            ->pluck('id');

        if ($superAdminIds->isEmpty()) {
            return [
                'window_days' => $window,
                'noisy_event_threshold' => $noisyThreshold,
                'escalations' => [
                    'triggered' => 0,
                    'acknowledged' => 0,
                    'pending' => 0,
                    'acknowledgement_rate' => 0.0,
                ],
                'digest_coverage' => [
                    'sent' => 0,
                    'opened' => 0,
                    'open_rate' => 0.0,
                    'total_notifications_summarized' => 0,
                ],
                'noisy_events' => [],
                'source_segmentation' => [],
                'time_series' => array_values($timeSeries),
            ];
        }

        $notifications = DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $superAdminIds->all())
            ->where('created_at', '>=', $start)
            ->get(['type', 'data', 'read_at']);

        $escalations = $notifications
            ->where('type', NotificationGovernanceEscalationNotification::class);
        $escalationTotal = $escalations->count();
        $escalationAcknowledged = $escalations
            ->filter(fn (DatabaseNotification $notification) => $notification->read_at !== null)
            ->count();
        $escalationPending = $escalationTotal - $escalationAcknowledged;
        $escalationRate = $escalationTotal > 0
            ? round(($escalationAcknowledged / $escalationTotal) * 100, 2)
            : 0.0;

        $digests = $notifications
            ->where('type', PlatformNotificationDigestNotification::class);
        $digestSent = $digests->count();
        $digestOpened = $digests
            ->filter(fn (DatabaseNotification $notification) => $notification->read_at !== null)
            ->count();
        $digestOpenRate = $digestSent > 0
            ? round(($digestOpened / $digestSent) * 100, 2)
            : 0.0;
        $digestTotalSummarized = $digests
            ->sum(function (DatabaseNotification $notification) {
                $payload = is_array($notification->data)
                    ? $notification->data
                    : [];

                return (int) (($payload['meta']['total'] ?? 0));
            });

        $sourceSegmentation = [];
        $eventRows = collect();

        foreach ($notifications as $notification) {
            $payload = is_array($notification->data)
                ? $notification->data
                : [];
            $meta = is_array($payload['meta'] ?? null)
                ? $payload['meta']
                : [];
            $severity = strtolower((string) ($payload['severity'] ?? 'low'));
            $source = trim((string) ($meta['source'] ?? 'unknown'));
            if ($source === '') {
                $source = 'unknown';
            }
            $event = trim((string) ($meta['event']
                ?? $payload['title']
                ?? class_basename($notification->type)));
            if ($event === '') {
                $event = 'Notification event';
            }

            $date = $notification->created_at?->toDateString();
            if ($date && isset($timeSeries[$date])) {
                $timeSeries[$date]['notifications_total']++;
                if ($notification->type === NotificationGovernanceEscalationNotification::class) {
                    $timeSeries[$date]['escalations_triggered']++;
                    if ($notification->read_at !== null) {
                        $timeSeries[$date]['escalations_acknowledged']++;
                    }
                }
                if ($notification->type === PlatformNotificationDigestNotification::class) {
                    $timeSeries[$date]['digests_sent']++;
                    if ($notification->read_at !== null) {
                        $timeSeries[$date]['digests_opened']++;
                    }
                }
            }

            if ($notification->type !== PlatformNotificationDigestNotification::class) {
                $sourceSegmentation[$source] = $sourceSegmentation[$source] ?? [
                    'source' => $source,
                    'count' => 0,
                    'unread' => 0,
                    'high_or_critical' => 0,
                    'escalations' => 0,
                ];
                $sourceSegmentation[$source]['count'] += 1;
                if ($notification->read_at === null) {
                    $sourceSegmentation[$source]['unread'] += 1;
                }
                if (in_array($severity, ['high', 'critical'], true)) {
                    $sourceSegmentation[$source]['high_or_critical'] += 1;
                }
                if ($notification->type === NotificationGovernanceEscalationNotification::class) {
                    $sourceSegmentation[$source]['escalations'] += 1;
                }

                $eventRows->push([
                    'event' => $event,
                    'source' => $source,
                    'unread' => $notification->read_at === null ? 1 : 0,
                    'high_or_critical' => in_array($severity, ['high', 'critical'], true) ? 1 : 0,
                ]);
            }
        }

        $sourceRows = collect($sourceSegmentation)
            ->sortByDesc('count')
            ->values()
            ->take(12)
            ->all();

        $noisyEvents = $eventRows
            ->groupBy(function (array $row) {
                return $row['event'].'::'.$row['source'];
            })
            ->map(function (Collection $group) {
                $first = (array) $group->first();

                return [
                    'event' => (string) ($first['event'] ?? 'Notification event'),
                    'source' => (string) ($first['source'] ?? 'unknown'),
                    'count' => $group->count(),
                    'unread' => (int) $group->sum('unread'),
                    'high_or_critical' => (int) $group->sum('high_or_critical'),
                ];
            })
            ->sortByDesc('count')
            ->filter(fn (array $row) => $row['count'] >= $noisyThreshold)
            ->take(10)
            ->values()
            ->all();

        return [
            'window_days' => $window,
            'noisy_event_threshold' => $noisyThreshold,
            'escalations' => [
                'triggered' => $escalationTotal,
                'acknowledged' => $escalationAcknowledged,
                'pending' => $escalationPending,
                'acknowledgement_rate' => $escalationRate,
            ],
            'digest_coverage' => [
                'sent' => $digestSent,
                'opened' => $digestOpened,
                'open_rate' => $digestOpenRate,
                'total_notifications_summarized' => (int) $digestTotalSummarized,
            ],
            'noisy_events' => $noisyEvents,
            'source_segmentation' => $sourceRows,
            'time_series' => array_values($timeSeries),
        ];
    }

    /**
     * @param  iterable<int, User>|Collection<int, User>  $recipients
     * @return Collection<int, User>
     */
    private function normalizeRecipients(iterable $recipients): Collection
    {
        return collect($recipients)
            ->filter(fn ($recipient) => $recipient instanceof User && $recipient->id)
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $context
     */
    private function handleEscalation(
        Collection $recipients,
        string $severity,
        array $context
    ): void {
        $settings = $this->getSettings();

        if (! ($settings['escalation_enabled'] ?? false)) {
            return;
        }

        if (
            $this->severityRank($severity)
            < $this->severityRank((string) ($settings['escalation_severity'] ?? 'high'))
        ) {
            return;
        }

        $excludeIds = $recipients->pluck('id')->all();
        $superAdmins = User::query()
            ->where('is_super_admin', true)
            ->when($excludeIds !== [], function ($query) use ($excludeIds) {
                $query->whereNotIn('id', $excludeIds);
            })
            ->get(['id', 'name', 'email']);

        if ($superAdmins->isEmpty()) {
            return;
        }

        NotificationFacade::send(
            $superAdmins,
            new NotificationGovernanceEscalationNotification(
                eventName: (string) ($context['event'] ?? class_basename($context['type'] ?? 'Notification event')),
                severity: $this->normalizeSeverity($severity),
                source: (string) ($context['source'] ?? 'core'),
                escalationDelayMinutes: (int) ($settings['escalation_delay_minutes'] ?? 30),
                details: (string) ($context['details'] ?? '')
            )
        );
    }

    private function severityRank(string $severity): int
    {
        return self::SEVERITY_ORDER[$this->normalizeSeverity($severity)] ?? 1;
    }

    private function normalizeSeverity(mixed $severity): string
    {
        $value = strtolower(trim((string) $severity));

        if (! array_key_exists($value, self::SEVERITY_ORDER)) {
            return 'low';
        }

        return $value;
    }
}
