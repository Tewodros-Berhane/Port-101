<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Notifications\NotificationGovernanceService;
use App\Core\Settings\Models\Setting;
use App\Core\Settings\SettingsService;
use App\Models\User;
use App\Notifications\PlatformNotificationDigestNotification;
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

Schedule::command('core:audit-logs:prune')->dailyAt('03:00');
Schedule::command('platform:notifications:send-digest')->everyMinute();
