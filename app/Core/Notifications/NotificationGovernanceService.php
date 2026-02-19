<?php

namespace App\Core\Notifications;

use App\Core\Settings\SettingsService;
use App\Models\User;
use App\Notifications\NotificationGovernanceEscalationNotification;
use Carbon\CarbonImmutable;
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

