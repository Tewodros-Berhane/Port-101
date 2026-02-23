<?php

namespace App\Modules\Reports;

use App\Core\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class CompanyReportingSettingsService
{
    public const PRESETS_KEY = 'company.reports.presets';

    public const DELIVERY_SCHEDULE_KEY = 'company.reports.delivery_schedule';

    public function __construct(
        private readonly SettingsService $settingsService,
    ) {
    }

    /**
     * @return array<int, array{id: string, name: string, filters: array<string, mixed>, created_at: string|null}>
     */
    public function getPresets(string $companyId): array
    {
        $stored = $this->settingsService->get(self::PRESETS_KEY, [], $companyId);
        $presets = is_array($stored) ? $stored : [];

        return collect($presets)
            ->filter(fn ($preset) => is_array($preset))
            ->map(function (array $preset) {
                $id = trim((string) ($preset['id'] ?? ''));

                if ($id === '') {
                    $id = (string) Str::uuid();
                }

                return [
                    'id' => $id,
                    'name' => trim((string) ($preset['name'] ?? 'Preset')),
                    'filters' => $this->normalizeFilters((array) ($preset['filters'] ?? [])),
                    'created_at' => $this->normalizeDateTime($preset['created_at'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{id: string, name: string, filters: array<string, mixed>, created_at: string|null}
     */
    public function savePreset(
        string $companyId,
        string $name,
        array $filters,
        ?string $actorId = null,
    ): array {
        $presets = $this->getPresets($companyId);

        $preset = [
            'id' => (string) Str::uuid(),
            'name' => trim($name),
            'filters' => $this->normalizeFilters($filters),
            'created_at' => now()->toIso8601String(),
        ];

        $presets[] = $preset;
        $limited = collect($presets)
            ->sortByDesc(fn (array $row) => $row['created_at'] ?? '')
            ->take(25)
            ->values()
            ->all();

        $this->settingsService->set(
            self::PRESETS_KEY,
            $limited,
            $companyId,
            null,
            $actorId,
        );

        return $preset;
    }

    public function deletePreset(
        string $companyId,
        string $presetId,
        ?string $actorId = null,
    ): bool {
        $presets = $this->getPresets($companyId);

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
            $companyId,
            null,
            $actorId,
        );

        $schedule = $this->getDeliverySchedule($companyId);

        if (($schedule['preset_id'] ?? null) === $presetId) {
            $this->setDeliverySchedule(
                companyId: $companyId,
                data: [
                    ...$schedule,
                    'preset_id' => null,
                ],
                actorId: $actorId,
            );
        }

        return true;
    }

    /**
     * @return array{
     *  enabled: bool,
     *  preset_id: string|null,
     *  report_key: string,
     *  format: string,
     *  frequency: string,
     *  day_of_week: int,
     *  time: string,
     *  timezone: string,
     *  last_sent_at: string|null
     * }
     */
    public function getDeliverySchedule(string $companyId): array
    {
        $stored = $this->settingsService->get(
            self::DELIVERY_SCHEDULE_KEY,
            [],
            $companyId,
        );
        $schedule = is_array($stored) ? $stored : [];

        $presetIds = collect($this->getPresets($companyId))
            ->pluck('id')
            ->all();

        $presetId = $schedule['preset_id'] ?? null;
        $presetId = is_string($presetId) && in_array($presetId, $presetIds, true)
            ? $presetId
            : null;

        $reportKey = strtolower(trim((string) ($schedule['report_key'] ?? CompanyReportsService::REPORT_APPROVAL_GOVERNANCE)));

        if (! in_array($reportKey, CompanyReportsService::REPORT_KEYS, true)) {
            $reportKey = CompanyReportsService::REPORT_APPROVAL_GOVERNANCE;
        }

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

        return [
            'enabled' => (bool) ($schedule['enabled'] ?? false),
            'preset_id' => $presetId,
            'report_key' => $reportKey,
            'format' => $format,
            'frequency' => $frequency,
            'day_of_week' => $dayOfWeek,
            'time' => $time,
            'timezone' => (string) ($schedule['timezone'] ?? config('app.timezone', 'UTC')),
            'last_sent_at' => $this->normalizeDateTime($schedule['last_sent_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *  enabled: bool,
     *  preset_id: string|null,
     *  report_key: string,
     *  format: string,
     *  frequency: string,
     *  day_of_week: int,
     *  time: string,
     *  timezone: string,
     *  last_sent_at: string|null
     * }
     */
    public function setDeliverySchedule(
        string $companyId,
        array $data,
        ?string $actorId = null,
    ): array {
        $current = $this->getDeliverySchedule($companyId);

        $presetId = $data['preset_id'] ?? $current['preset_id'];
        $presetIds = collect($this->getPresets($companyId))->pluck('id')->all();
        $presetId = is_string($presetId) && in_array($presetId, $presetIds, true)
            ? $presetId
            : null;

        $reportKey = strtolower(trim((string) ($data['report_key'] ?? $current['report_key'])));

        if (! in_array($reportKey, CompanyReportsService::REPORT_KEYS, true)) {
            $reportKey = CompanyReportsService::REPORT_APPROVAL_GOVERNANCE;
        }

        $format = strtolower(trim((string) ($data['format'] ?? $current['format'])));

        if (! in_array($format, ['pdf', 'xlsx'], true)) {
            $format = 'xlsx';
        }

        $frequency = strtolower(trim((string) ($data['frequency'] ?? $current['frequency'])));

        if (! in_array($frequency, ['daily', 'weekly'], true)) {
            $frequency = 'weekly';
        }

        $dayOfWeek = max(1, min(7, (int) ($data['day_of_week'] ?? $current['day_of_week'])));

        $time = (string) ($data['time'] ?? $current['time']);

        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '08:00';
        }

        $schedule = [
            'enabled' => (bool) ($data['enabled'] ?? $current['enabled']),
            'preset_id' => $presetId,
            'report_key' => $reportKey,
            'format' => $format,
            'frequency' => $frequency,
            'day_of_week' => $dayOfWeek,
            'time' => $time,
            'timezone' => (string) ($data['timezone'] ?? $current['timezone']),
            'last_sent_at' => $this->normalizeDateTime($data['last_sent_at'] ?? $current['last_sent_at']),
        ];

        $this->settingsService->set(
            self::DELIVERY_SCHEDULE_KEY,
            $schedule,
            $companyId,
            null,
            $actorId,
        );

        return $schedule;
    }

    public function shouldSendScheduledDeliveryNow(
        array $schedule,
        ?CarbonImmutable $now = null,
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
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $input): array
    {
        $trendWindow = (int) ($input['trend_window'] ?? 30);

        if (! in_array($trendWindow, [7, 30, 90], true)) {
            $trendWindow = 30;
        }

        $approvalStatus = $input['approval_status'] ?? null;

        if (! is_string($approvalStatus) || trim($approvalStatus) === '') {
            $approvalStatus = null;
        } else {
            $approvalStatus = strtolower(trim($approvalStatus));

            if (! in_array($approvalStatus, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
                $approvalStatus = null;
            }
        }

        return [
            'trend_window' => $trendWindow,
            'start_date' => $this->normalizeDate($input['start_date'] ?? null),
            'end_date' => $this->normalizeDate($input['end_date'] ?? null),
            'approval_status' => $approvalStatus,
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
    public function filtersForPreset(string $companyId, ?string $presetId): array
    {
        if (! $presetId) {
            return $this->defaultFilters();
        }

        $preset = collect($this->getPresets($companyId))
            ->first(fn (array $row) => $row['id'] === $presetId);

        if (! $preset) {
            return $this->defaultFilters();
        }

        return $this->normalizeFilters((array) ($preset['filters'] ?? []));
    }

    public function markDeliverySent(string $companyId, ?CarbonImmutable $now = null): array
    {
        $current = $this->getDeliverySchedule($companyId);

        return $this->setDeliverySchedule(
            companyId: $companyId,
            data: [
                ...$current,
                'last_sent_at' => ($now ?? CarbonImmutable::now())->toIso8601String(),
            ],
            actorId: null,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filtersToQuery(array $filters): string
    {
        $normalized = $this->normalizeFilters($filters);

        return http_build_query([
            'trend_window' => (string) $normalized['trend_window'],
            'start_date' => (string) ($normalized['start_date'] ?? ''),
            'end_date' => (string) ($normalized['end_date'] ?? ''),
            'approval_status' => (string) ($normalized['approval_status'] ?? ''),
        ]);
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
            return CarbonImmutable::createFromFormat('Y-m-d', $trimmed)
                ->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeDateTime(mixed $value): ?string
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
}
