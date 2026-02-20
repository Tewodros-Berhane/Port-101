<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Notifications\NotificationGovernanceService;
use App\Core\Platform\OperationsReportingSettingsService;
use App\Core\Settings\Models\Setting;
use App\Core\Settings\SettingsService;
use App\Models\User;
use App\Notifications\PlatformNotificationDigestNotification;
use App\Notifications\PlatformOperationsReportDeliveryNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('core:audit-logs:prune {--days=} {--dry-run}', function () {
    $settingsService = app(SettingsService::class);
    $configuredDays = $settingsService->get(
        'core.audit_logs.retention_days',
        (int) config('core.audit_logs.retention_days', 365),
    );

    $daysOption = $this->option('days');
    $isForcedDays = $daysOption !== null && $daysOption !== '';

    if ($isForcedDays) {
        $days = (int) $daysOption;

        if ($days < 1) {
            $this->error('Retention days must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $query = AuditLog::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->line(
                "Dry run: {$count} audit log entries older than {$days} days (before {$cutoff->toDateTimeString()}) would be removed."
            );

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info(
            "Pruned {$deleted} audit log entries older than {$days} days (before {$cutoff->toDateTimeString()})."
        );

        return self::SUCCESS;
    }

    $defaultDays = (int) $configuredDays;
    if ($defaultDays < 1) {
        $defaultDays = (int) config('core.audit_logs.retention_days', 365);
    }
    if ($defaultDays < 1) {
        $defaultDays = 365;
    }

    $companyRetentionMap = Setting::query()
        ->where('key', 'core.audit_logs.retention_days')
        ->whereNotNull('company_id')
        ->get(['company_id', 'value'])
        ->mapWithKeys(function (Setting $setting) use ($defaultDays) {
            $days = (int) $setting->value;

            if ($days < 1) {
                $days = $defaultDays;
            }

            return [$setting->company_id => $days];
        });

    $companyIds = AuditLog::withoutGlobalScopes()
        ->whereNotNull('company_id')
        ->distinct()
        ->pluck('company_id');

    $totalCandidates = 0;
    $totalDeleted = 0;
    $dryRun = (bool) $this->option('dry-run');

    foreach ($companyIds as $companyId) {
        $companyDays = (int) ($companyRetentionMap[$companyId] ?? $defaultDays);
        $cutoff = now()->subDays($companyDays);

        $query = AuditLog::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('created_at', '<', $cutoff);

        $count = $query->count();
        $totalCandidates += $count;

        if ($dryRun) {
            continue;
        }

        $totalDeleted += $query->delete();
    }

    $globalCutoff = now()->subDays($defaultDays);
    $globalQuery = AuditLog::withoutGlobalScopes()
        ->whereNull('company_id')
        ->where('created_at', '<', $globalCutoff);
    $globalCount = $globalQuery->count();
    $totalCandidates += $globalCount;

    if ($dryRun) {
        $this->line(
            "Dry run: {$totalCandidates} audit log entries would be removed using company or default retention rules."
        );

        return self::SUCCESS;
    }

    $totalDeleted += $globalQuery->delete();

    $this->info(
        "Pruned {$totalDeleted} audit log entries using company or default retention rules."
    );

    return self::SUCCESS;
})->purpose('Prune audit logs using configured retention rules');

Artisan::command('platform:notifications:send-digest {--force}', function () {
    $governance = app(NotificationGovernanceService::class);
    $settings = $governance->getSettings();
    $force = (bool) $this->option('force');

    if (! $force && ! $governance->shouldSendDigestNow($settings)) {
        $this->line('Digest schedule not due yet.');

        return self::SUCCESS;
    }

    $frequency = (string) ($settings['digest_frequency'] ?? 'daily');
    $timezone = (string) ($settings['digest_timezone'] ?? 'UTC');
    $now = CarbonImmutable::now($timezone);
    $appTimezone = (string) config('app.timezone', 'UTC');
    $cutoff = $frequency === 'weekly'
        ? $now->subWeek()->setTimezone($appTimezone)
        : $now->subDay()->setTimezone($appTimezone);
    $periodLabel = $frequency === 'weekly' ? 'weekly digest' : 'daily digest';

    $recipients = User::query()
        ->where('is_super_admin', true)
        ->get(['id', 'name', 'email']);

    if ($recipients->isEmpty()) {
        $this->line('No platform admins available for digest delivery.');

        return self::SUCCESS;
    }

    $severityCounts = [
        'low' => 0,
        'medium' => 0,
        'high' => 0,
        'critical' => 0,
    ];

    $notifications = DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->whereIn('notifiable_id', $recipients->pluck('id'))
        ->where('created_at', '>=', $cutoff)
        ->where('type', '!=', PlatformNotificationDigestNotification::class)
        ->get(['data']);

    foreach ($notifications as $notification) {
        $payload = is_array($notification->data)
            ? $notification->data
            : [];
        $severity = strtolower((string) ($payload['severity'] ?? 'low'));

        if (! array_key_exists($severity, $severityCounts)) {
            $severity = 'low';
        }

        $severityCounts[$severity] += 1;
    }

    $total = array_sum($severityCounts);

    if (! $force && $total === 0) {
        $this->line('No notifications in the configured digest window.');

        return self::SUCCESS;
    }

    Notification::send(
        $recipients,
        new PlatformNotificationDigestNotification(
            periodLabel: $periodLabel,
            totalNotifications: $total,
            severityCounts: $severityCounts
        )
    );

    $this->info("Notification digest delivered to {$recipients->count()} platform admin(s).");

    return self::SUCCESS;
})->purpose('Send platform notification digests based on governance policy');

Artisan::command('platform:operations-reports:deliver-scheduled {--force}', function () {
    $settings = app(OperationsReportingSettingsService::class);
    $schedule = $settings->getDeliverySchedule();
    $force = (bool) $this->option('force');

    if (! $force && ! $settings->shouldSendScheduledDeliveryNow($schedule)) {
        $this->line('Operations report schedule not due yet.');

        return self::SUCCESS;
    }

    $filters = $settings->filtersForPreset($schedule['preset_id'] ?? null);
    $trendWindow = (int) ($filters['trend_window'] ?? 30);

    $adminActionsQuery = AuditLog::query()
        ->with(['actor:id,name,is_super_admin', 'company:id,name'])
        ->whereHas('actor', function ($query) {
            $query->where('is_super_admin', true);
        });

    $action = $filters['admin_action'] ?? null;
    $actorId = $filters['admin_actor_id'] ?? null;
    $startDate = $filters['admin_start_date'] ?? null;
    $endDate = $filters['admin_end_date'] ?? null;

    if ($action) {
        $adminActionsQuery->where('action', $action);
    }

    if ($actorId) {
        $adminActionsQuery->where('user_id', $actorId);
    }

    $start = null;
    $end = null;
    if (is_string($startDate) && $startDate !== '') {
        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $startDate)->startOfDay();
        } catch (\Throwable) {
            $start = null;
        }
    }
    if (is_string($endDate) && $endDate !== '') {
        try {
            $end = CarbonImmutable::createFromFormat('Y-m-d', $endDate)->endOfDay();
        } catch (\Throwable) {
            $end = null;
        }
    }

    if ($start && $end && $start->gt($end)) {
        [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
    }

    if ($start && $end) {
        $adminActionsQuery->whereBetween('created_at', [$start, $end]);
    } elseif ($start) {
        $adminActionsQuery->where('created_at', '>=', $start);
    } elseif ($end) {
        $adminActionsQuery->where('created_at', '<=', $end);
    }

    $adminActionsCount = $adminActionsQuery->count();

    $today = CarbonImmutable::now()->startOfDay();
    $trendStart = $today->subDays($trendWindow - 1);
    $trendRows = [];

    for ($day = $trendStart; $day->lte($today); $day = $day->addDay()) {
        $trendRows[$day->toDateString()] = [
            'sent' => 0,
            'failed' => 0,
            'pending' => 0,
        ];
    }

    $invites = \App\Core\Access\Models\Invite::query()
        ->whereBetween('created_at', [$trendStart, $today->endOfDay()])
        ->get(['delivery_status', 'created_at']);

    foreach ($invites as $invite) {
        $date = $invite->created_at?->toDateString();

        if (! $date || ! isset($trendRows[$date])) {
            continue;
        }

        if ($invite->delivery_status === \App\Core\Access\Models\Invite::DELIVERY_SENT) {
            $trendRows[$date]['sent'] += 1;

            continue;
        }

        if ($invite->delivery_status === \App\Core\Access\Models\Invite::DELIVERY_FAILED) {
            $trendRows[$date]['failed'] += 1;

            continue;
        }

        $trendRows[$date]['pending'] += 1;
    }

    $sent = (int) collect($trendRows)->sum('sent');
    $failed = (int) collect($trendRows)->sum('failed');
    $pending = (int) collect($trendRows)->sum('pending');
    $deliveryTotal = $sent + $failed + $pending;
    $attempted = $sent + $failed;
    $failureRate = $attempted > 0
        ? round(($failed / $attempted) * 100, 2)
        : 0.0;

    if (! $force && $adminActionsCount === 0 && $deliveryTotal === 0) {
        $this->line('No operations activity in the scheduled window.');

        return self::SUCCESS;
    }

    $recipients = User::query()
        ->where('is_super_admin', true)
        ->get(['id', 'name', 'email']);

    if ($recipients->isEmpty()) {
        $this->line('No platform admins available for report delivery.');

        return self::SUCCESS;
    }

    $format = (string) ($schedule['format'] ?? 'xlsx');
    $query = $settings->filtersToQuery($filters);
    $links = [
        'admin_actions' => "/platform/reports/export/admin-actions?{$query}&format={$format}",
        'delivery_trends' => "/platform/reports/export/invite-delivery-trends?{$query}&format={$format}",
    ];

    $presetId = $schedule['preset_id'] ?? null;
    $presetName = 'Default filters';
    if ($presetId) {
        $preset = collect($settings->getPresets())
            ->first(fn (array $row) => $row['id'] === $presetId);
        $presetName = (string) ($preset['name'] ?? $presetName);
    }

    Notification::send(
        $recipients,
        new PlatformOperationsReportDeliveryNotification(
            presetName: $presetName,
            periodLabel: "{$trendWindow}-day window",
            format: $format,
            links: $links,
            summary: [
                'admin_actions' => $adminActionsCount,
                'delivery_total' => $deliveryTotal,
                'sent' => $sent,
                'failed' => $failed,
                'pending' => $pending,
                'failure_rate' => $failureRate,
            ]
        )
    );

    $settings->markDeliverySent();

    $this->info("Scheduled operations report delivered to {$recipients->count()} platform admin(s).");

    return self::SUCCESS;
})->purpose('Deliver scheduled operations report exports to platform admins');

Schedule::command('core:audit-logs:prune')->dailyAt('03:00');
Schedule::command('platform:notifications:send-digest')->everyMinute();
Schedule::command('platform:operations-reports:deliver-scheduled')->everyMinute();
