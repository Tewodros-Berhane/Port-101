<?php

namespace App\Core\Platform;

use App\Models\User;
use App\Core\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class OperationsReportingSettingsService
{
    public const PRESETS_KEY = 'platform.operations_reporting.presets';

    public const DELIVERY_SCHEDULE_KEY = 'platform.operations_reporting.delivery_schedule';

    /**
     * @var array<int, string>
     */
    public const DELIVERY_CHANNELS = [
        'in_app',
        'email',
        'webhook',
        'slack',
    ];

    /**
     * @var array<int, string>
     */
    public const RECIPIENT_MODES = [
        'all_superadmins',
        'selected_superadmins',
    ];

    public function __construct(
        private SettingsService $settingsService
    ) {}

    /**
     * @return array<int, array{id: string, name: string, filters: array<string, mixed>, created_at: string|null}>
     */
    public function getPresets(): array
    {
        $stored = $this->settingsService->get(self::PRESETS_KEY, null, null, null);
        $presets = is_array($stored) ? $stored : [];

        $normalized = collect($presets)
            ->filter(fn ($preset) => is_array($preset))
            ->map(function (array $preset) {
                $id = (string) ($preset['id'] ?? '');

                if ($id === '') {
                    $id = (string) Str::uuid();
                }

                return [
                    'id' => $id,
                    'name' => trim((string) ($preset['name'] ?? 'Preset')),
                    'filters' => $this->normalizeFilters((array) ($preset['filters'] ?? [])),
                    'created_at' => $this->normalizedDateTime(
                        $preset['created_at'] ?? null
                    ),
                ];
            })
            ->values()
            ->all();

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{id: string, name: string, filters: array<string, mixed>, created_at: string|null}
     */
    public function savePreset(
        string $name,
        array $filters,
        ?string $actorId = null
    ): array {
        $presets = $this->getPresets();

        $preset = [
            'id' => (string) Str::uuid(),
            'name' => trim($name),
            'filters' => $this->normalizeFilters($filters),
            'created_at' => now()->toIso8601String(),
        ];

        $presets[] = $preset;
        $limited = collect($presets)
            ->sortByDesc(function (array $row) {
                return $row['created_at'] ?? '';
            })
            ->take(25)
            ->values()
            ->all();

        $this->settingsService->set(
            self::PRESETS_KEY,
            $limited,
            null,
            null,
            $actorId
        );

        return $preset;
    }

    public function deletePreset(string $presetId, ?string $actorId = null): bool
    {
        $presets = $this->getPresets();
        $remaining = collect($presets)
            ->reject(fn (array $preset) => $preset['id'] === $presetId)
            ->values()
            ->all();

        if (count($remaining) === count($presets)) {
            return false;
        }

        $this->settingsService->set(
            self::PRESETS_KEY,
            $remaining,
            null,
            null,
            $actorId
        );

        $rawSchedule = $this->settingsService->get(
            self::DELIVERY_SCHEDULE_KEY,
            null,
            null,
            null
        );

        if (
            is_array($rawSchedule)
            && (($rawSchedule['preset_id'] ?? null) === $presetId)
        ) {
            $this->setDeliverySchedule([
                ...$rawSchedule,
                'preset_id' => null,
            ], $actorId);
        }

        return true;
    }

    /**
     * @return array{
     *  enabled: bool,
     *  preset_id: string|null,
     *  format: string,
     *  frequency: string,
     *  day_of_week: int,
     *  time: string,
     *  timezone: string,
     *  last_sent_at: string|null,
     *  channels: array<int, string>,
     *  recipient_mode: string,
     *  recipient_user_ids: array<int, string>,
     *  additional_emails: array<int, string>,
     *  webhook_url: string|null,
     *  slack_webhook_url: string|null
     * }
     */
    public function getDeliverySchedule(): array
    {
        $stored = $this->settingsService->get(self::DELIVERY_SCHEDULE_KEY, null, null, null);
        $schedule = is_array($stored) ? $stored : [];
        $superAdminIds = User::query()
            ->where('is_super_admin', true)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
        $presets = $this->getPresets();
        $presetIds = collect($presets)->pluck('id')->all();

        $format = strtolower(trim((string) ($schedule['format'] ?? 'xlsx')));
        if (! in_array($format, ['pdf', 'xlsx'], true)) {
            $format = 'xlsx';
        }

        $frequency = strtolower(trim((string) ($schedule['frequency'] ?? 'weekly')));
        if (! in_array($frequency, ['daily', 'weekly'], true)) {
            $frequency = 'weekly';
        }

        $dayOfWeek = (int) ($schedule['day_of_week'] ?? 1);
        if ($dayOfWeek < 1 || $dayOfWeek > 7) {
            $dayOfWeek = 1;
        }

        $time = (string) ($schedule['time'] ?? '08:00');
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '08:00';
        }

        $presetId = $schedule['preset_id'] ?? null;
        $presetId = is_string($presetId) && in_array($presetId, $presetIds, true)
            ? $presetId
            : null;

        $channels = $this->normalizeDeliveryChannels(
            is_array($schedule['channels'] ?? null) ? $schedule['channels'] : ['in_app']
        );
        $recipientMode = $this->normalizeRecipientMode(
            $schedule['recipient_mode'] ?? null
        );
        $recipientUserIds = collect(is_array($schedule['recipient_user_ids'] ?? null)
            ? $schedule['recipient_user_ids']
            : [])
            ->filter(fn ($id) => is_string($id) && in_array($id, $superAdminIds, true))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
        $additionalEmails = $this->normalizeEmailList(
            is_array($schedule['additional_emails'] ?? null)
                ? $schedule['additional_emails']
                : []
        );
        $webhookUrl = $this->normalizeOptionalUrl($schedule['webhook_url'] ?? null);
        $slackWebhookUrl = $this->normalizeOptionalUrl($schedule['slack_webhook_url'] ?? null);

        return [
            'enabled' => (bool) ($schedule['enabled'] ?? false),
            'preset_id' => $presetId,
            'format' => $format,
            'frequency' => $frequency,
            'day_of_week' => $dayOfWeek,
            'time' => $time,
            'timezone' => (string) ($schedule['timezone'] ?? config('app.timezone', 'UTC')),
            'last_sent_at' => $this->normalizedDateTime($schedule['last_sent_at'] ?? null),
            'channels' => $channels,
            'recipient_mode' => $recipientMode,
            'recipient_user_ids' => $recipientUserIds,
            'additional_emails' => $additionalEmails,
            'webhook_url' => $webhookUrl,
            'slack_webhook_url' => $slackWebhookUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setDeliverySchedule(array $data, ?string $actorId = null): array
    {
        $current = $this->getDeliverySchedule();

        $enabled = (bool) ($data['enabled'] ?? false);
        $format = strtolower(trim((string) ($data['format'] ?? $current['format'])));
        if (! in_array($format, ['pdf', 'xlsx'], true)) {
            $format = 'xlsx';
        }

        $frequency = strtolower(trim((string) ($data['frequency'] ?? $current['frequency'])));
        if (! in_array($frequency, ['daily', 'weekly'], true)) {
            $frequency = 'weekly';
        }

        $dayOfWeek = (int) ($data['day_of_week'] ?? $current['day_of_week']);
        $dayOfWeek = max(1, min(7, $dayOfWeek));

        $time = (string) ($data['time'] ?? $current['time']);
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '08:00';
        }

        $presetId = $data['preset_id'] ?? $current['preset_id'];
        $presetIds = collect($this->getPresets())->pluck('id')->all();
        $presetId = is_string($presetId) && in_array($presetId, $presetIds, true)
            ? $presetId
            : null;

        $lastSentAt = $this->normalizedDateTime(
            $data['last_sent_at'] ?? $current['last_sent_at']
        );
        $channels = $this->normalizeDeliveryChannels(
            is_array($data['channels'] ?? null)
                ? $data['channels']
                : ($current['channels'] ?? ['in_app'])
        );
        $recipientMode = $this->normalizeRecipientMode(
            $data['recipient_mode'] ?? $current['recipient_mode'] ?? null
        );
        $superAdminIds = User::query()
            ->where('is_super_admin', true)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
        $recipientUserIds = collect(is_array($data['recipient_user_ids'] ?? null)
            ? $data['recipient_user_ids']
            : ($current['recipient_user_ids'] ?? []))
            ->filter(fn ($id) => is_string($id) && in_array($id, $superAdminIds, true))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
        $additionalEmails = $this->normalizeEmailList(
            is_array($data['additional_emails'] ?? null)
                ? $data['additional_emails']
                : ($current['additional_emails'] ?? [])
        );
        $webhookUrl = $this->normalizeOptionalUrl(
            $data['webhook_url'] ?? $current['webhook_url'] ?? null
        );
        $slackWebhookUrl = $this->normalizeOptionalUrl(
            $data['slack_webhook_url'] ?? $current['slack_webhook_url'] ?? null
        );

        $schedule = [
            'enabled' => $enabled,
            'preset_id' => $presetId,
            'format' => $format,
            'frequency' => $frequency,
            'day_of_week' => $dayOfWeek,
            'time' => $time,
            'timezone' => (string) ($data['timezone'] ?? $current['timezone']),
            'last_sent_at' => $lastSentAt,
            'channels' => $channels,
            'recipient_mode' => $recipientMode,
            'recipient_user_ids' => $recipientUserIds,
            'additional_emails' => $additionalEmails,
            'webhook_url' => $webhookUrl,
            'slack_webhook_url' => $slackWebhookUrl,
        ];

        $this->settingsService->set(
            self::DELIVERY_SCHEDULE_KEY,
            $schedule,
            null,
            null,
            $actorId
        );

        return $schedule;
    }

    public function markDeliverySent(?CarbonImmutable $now = null): array
    {
        $current = $this->getDeliverySchedule();
        $timestamp = ($now ?? CarbonImmutable::now())->toIso8601String();

        return $this->setDeliverySchedule([
            ...$current,
            'last_sent_at' => $timestamp,
        ]);
    }

    public function shouldSendScheduledDeliveryNow(
        array $schedule,
        ?CarbonImmutable $now = null
    ): bool {
        if (! ($schedule['enabled'] ?? false)) {
            return false;
        }

        $timezone = (string) ($schedule['timezone'] ?? config('app.timezone', 'UTC'));
        $current = $now
            ? $now->setTimezone($timezone)
            : CarbonImmutable::now($timezone);

        if ($current->format('H:i') !== (string) ($schedule['time'] ?? '08:00')) {
            return false;
        }

        $frequency = (string) ($schedule['frequency'] ?? 'weekly');
        if ($frequency === 'weekly') {
            $day = (int) ($schedule['day_of_week'] ?? 1);

            if ((int) $current->format('N') !== $day) {
                return false;
            }
        }

        $lastSentAt = $schedule['last_sent_at'] ?? null;
        if (! is_string($lastSentAt) || trim($lastSentAt) === '') {
            return true;
        }

        try {
            $lastSent = CarbonImmutable::parse($lastSentAt)->setTimezone($timezone);
        } catch (\Throwable) {
            return true;
        }

        if ($frequency === 'daily') {
            return ! $current->isSameDay($lastSent);
        }

        return $current->format('o-W') !== $lastSent->format('o-W');
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $input): array
    {
        $trendWindow = (int) ($input['trend_window'] ?? 30);
        if (! in_array($trendWindow, [7, 30, 90], true)) {
            $trendWindow = 30;
        }

        $actorId = $input['admin_actor_id'] ?? null;
        if (! is_string($actorId) || trim($actorId) === '') {
            $actorId = null;
        }

        $action = $input['admin_action'] ?? null;
        if (! is_string($action) || trim($action) === '') {
            $action = null;
        } else {
            $action = trim($action);
        }

        return [
            'trend_window' => $trendWindow,
            'admin_action' => $action,
            'admin_actor_id' => $actorId,
            'admin_start_date' => $this->normalizeDate($input['admin_start_date'] ?? null),
            'admin_end_date' => $this->normalizeDate($input['admin_end_date'] ?? null),
            'invite_delivery_status' => $this->normalizeInviteDeliveryStatus(
                $input['invite_delivery_status'] ?? null
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultFilters(): array
    {
        return $this->normalizeFilters([]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filtersForPreset(?string $presetId): array
    {
        if (! $presetId) {
            return $this->defaultFilters();
        }

        $preset = collect($this->getPresets())
            ->first(fn (array $row) => $row['id'] === $presetId);

        if (! $preset) {
            return $this->defaultFilters();
        }

        return $this->normalizeFilters((array) ($preset['filters'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filtersToQuery(array $filters): string
    {
        $normalized = $this->normalizeFilters($filters);

        $query = http_build_query([
            'trend_window' => (string) $normalized['trend_window'],
            'admin_action' => (string) ($normalized['admin_action'] ?? ''),
            'admin_actor_id' => (string) ($normalized['admin_actor_id'] ?? ''),
            'admin_start_date' => (string) ($normalized['admin_start_date'] ?? ''),
            'admin_end_date' => (string) ($normalized['admin_end_date'] ?? ''),
            'invite_delivery_status' => (string) ($normalized['invite_delivery_status'] ?? ''),
        ]);

        return $query;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $trimmed)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizedDateTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeInviteDeliveryStatus(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        if (! in_array($normalized, ['pending', 'sent', 'failed'], true)) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $channels
     * @return array<int, string>
     */
    private function normalizeDeliveryChannels(array $channels): array
    {
        $normalized = collect($channels)
            ->filter(fn ($channel) => is_string($channel))
            ->map(fn (string $channel) => strtolower(trim($channel)))
            ->filter(fn (string $channel) => in_array($channel, self::DELIVERY_CHANNELS, true))
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            return ['in_app'];
        }

        return $normalized;
    }

    private function normalizeRecipientMode(mixed $mode): string
    {
        $normalized = strtolower(trim((string) $mode));

        if (! in_array($normalized, self::RECIPIENT_MODES, true)) {
            return 'all_superadmins';
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return array<int, string>
     */
    private function normalizeEmailList(array $emails): array
    {
        return collect($emails)
            ->filter(fn ($email) => is_string($email))
            ->map(fn (string $email) => strtolower(trim($email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->take(50)
            ->values()
            ->all();
    }

    private function normalizeOptionalUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (! filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $trimmed;
    }
}
