<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Core\Notifications\NotificationGovernanceService;
use App\Core\Platform\OperationsReportingSettingsService;
use App\Core\Platform\PlatformOperationalAlertingService;
use App\Core\Platform\PlatformReportExportService;
use App\Core\Platform\PlatformReportsService;
use App\Core\Settings\Models\Setting;
use App\Core\Settings\SettingsService;
use App\Mail\PlatformOperationsReportDeliveryMail;
use App\Models\User;
use App\Modules\Accounting\AccountingLedgerBackfillService;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Inventory\InventoryReorderService;
use App\Modules\Projects\Models\ProjectRecurringBilling;
use App\Modules\Projects\Models\ProjectRecurringBillingRun;
use App\Modules\Projects\ProjectRecurringBillingService;
use App\Modules\Reports\CompanyReportingSettingsService;
use App\Modules\Reports\CompanyReportsService;
use App\Notifications\CompanyReportDeliveryNotification;
use App\Notifications\PlatformNotificationDigestNotification;
use App\Notifications\PlatformOperationsReportDeliveryNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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
    $reportsService = app(PlatformReportsService::class);
    $exportService = app(PlatformReportExportService::class);
    $schedule = $settings->getDeliverySchedule();
    $force = (bool) $this->option('force');

    if (! $force && ! $settings->shouldSendScheduledDeliveryNow($schedule)) {
        $this->line('Operations report schedule not due yet.');

        return self::SUCCESS;
    }

    $filters = $settings->filtersForPreset($schedule['preset_id'] ?? null);
    $trendWindow = (int) ($filters['trend_window'] ?? 30);
    $format = in_array((string) ($schedule['format'] ?? 'xlsx'), ['pdf', 'xlsx'], true)
        ? (string) $schedule['format']
        : 'xlsx';

    $adminActionsReport = $reportsService->buildReport(
        PlatformReportsService::REPORT_ADMIN_ACTIONS,
        $filters
    );
    $deliveryTrendReport = $reportsService->buildReport(
        PlatformReportsService::REPORT_DELIVERY_TRENDS,
        $filters
    );

    if (! $adminActionsReport || ! $deliveryTrendReport) {
        $this->error('Could not build scheduled operations reports.');

        return self::FAILURE;
    }

    $adminActionsCount = count($adminActionsReport['rows']);
    $sent = 0;
    $failed = 0;
    $pending = 0;
    foreach ($deliveryTrendReport['rows'] as $row) {
        $sent += (int) ($row[1] ?? 0);
        $failed += (int) ($row[2] ?? 0);
        $pending += (int) ($row[3] ?? 0);
    }
    $deliveryTotal = $sent + $failed + $pending;
    $attempted = $sent + $failed;
    $failureRate = $attempted > 0
        ? round(($failed / $attempted) * 100, 2)
        : 0.0;

    if (! $force && $adminActionsCount === 0 && $deliveryTotal === 0) {
        $this->line('No operations activity in the scheduled window.');

        return self::SUCCESS;
    }

    $allSuperAdmins = User::query()
        ->where('is_super_admin', true)
        ->get(['id', 'name', 'email']);

    $recipientMode = (string) ($schedule['recipient_mode'] ?? 'all_superadmins');
    $targetedUsers = $recipientMode === 'selected_superadmins'
        ? $allSuperAdmins->whereIn('id', (array) ($schedule['recipient_user_ids'] ?? []))->values()
        : $allSuperAdmins;

    $additionalEmails = collect((array) ($schedule['additional_emails'] ?? []))
        ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
        ->map(fn ($email) => strtolower(trim((string) $email)))
        ->unique()
        ->values()
        ->all();

    $channels = collect((array) ($schedule['channels'] ?? ['in_app']))
        ->filter(fn ($channel) => is_string($channel))
        ->map(fn ($channel) => strtolower(trim((string) $channel)))
        ->filter(fn ($channel) => in_array($channel, OperationsReportingSettingsService::DELIVERY_CHANNELS, true))
        ->unique()
        ->values()
        ->all();

    if ($channels === []) {
        $channels = ['in_app'];
    }

    if ($targetedUsers->isEmpty() && $additionalEmails === [] && ! in_array('webhook', $channels, true)
        && ! in_array('slack', $channels, true)
    ) {
        $this->line('No scheduled report recipients available.');

        return self::SUCCESS;
    }

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

    $summary = [
        'admin_actions' => $adminActionsCount,
        'delivery_total' => $deliveryTotal,
        'sent' => $sent,
        'failed' => $failed,
        'pending' => $pending,
        'failure_rate' => $failureRate,
    ];

    $deliverySucceeded = false;
    $sentEmailCount = 0;
    $webhookDispatches = 0;
    $slackDispatches = 0;

    if (in_array('in_app', $channels, true) && $targetedUsers->isNotEmpty()) {
        Notification::send(
            $targetedUsers,
            new PlatformOperationsReportDeliveryNotification(
                presetName: $presetName,
                periodLabel: "{$trendWindow}-day window",
                format: $format,
                links: $links,
                summary: $summary
            )
        );

        $deliverySucceeded = true;
    }

    if (in_array('email', $channels, true)) {
        $emailTargets = $targetedUsers
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && $email !== '')
            ->merge($additionalEmails)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($emailTargets->isNotEmpty()) {
            $attachments = [
                $exportService->buildAttachmentPayload(
                    $adminActionsReport,
                    $format,
                    "admin-actions-{$trendWindow}d.{$format}"
                ),
                $exportService->buildAttachmentPayload(
                    $deliveryTrendReport,
                    $format,
                    "invite-delivery-trends-{$trendWindow}d.{$format}"
                ),
            ];

            foreach ($emailTargets as $email) {
                Mail::to($email)->send(new PlatformOperationsReportDeliveryMail(
                    presetName: $presetName,
                    periodLabel: "{$trendWindow}-day window",
                    format: $format,
                    summary: $summary,
                    links: $links,
                    attachmentsData: $attachments
                ));
                $sentEmailCount++;
            }

            $deliverySucceeded = $deliverySucceeded || $sentEmailCount > 0;
        }
    }

    $webhookPayload = [
        'event' => 'platform.operations_report.delivered',
        'preset_name' => $presetName,
        'period' => "{$trendWindow}-day window",
        'format' => $format,
        'links' => [
            'admin_actions' => url($links['admin_actions']),
            'delivery_trends' => url($links['delivery_trends']),
        ],
        'summary' => $summary,
        'filters' => $filters,
        'generated_at' => now()->toIso8601String(),
    ];

    if (in_array('webhook', $channels, true) && is_string($schedule['webhook_url'] ?? null)) {
        $webhookUrl = trim((string) $schedule['webhook_url']);
        if ($webhookUrl !== '') {
            try {
                Http::timeout(15)->post($webhookUrl, $webhookPayload)->throw();
                $webhookDispatches++;
                $deliverySucceeded = true;
            } catch (\Throwable $exception) {
                report($exception);
                $this->warn('Webhook delivery failed for scheduled operations report.');
            }
        }
    }

    if (in_array('slack', $channels, true) && is_string($schedule['slack_webhook_url'] ?? null)) {
        $slackUrl = trim((string) $schedule['slack_webhook_url']);
        if ($slackUrl !== '') {
            try {
                Http::timeout(15)->post($slackUrl, [
                    'text' => "Port-101 scheduled operations reports ready ({$trendWindow}-day window, {$format}).",
                    'blocks' => [
                        [
                            'type' => 'section',
                            'text' => [
                                'type' => 'mrkdwn',
                                'text' => "*Port-101 Scheduled Reports*\nPreset: {$presetName}\nWindow: {$trendWindow}-day window\nFormat: ".strtoupper($format),
                            ],
                        ],
                        [
                            'type' => 'section',
                            'fields' => [
                                ['type' => 'mrkdwn', 'text' => "*Admin actions*\n{$adminActionsCount}"],
                                ['type' => 'mrkdwn', 'text' => "*Invite deliveries*\n{$deliveryTotal}"],
                                ['type' => 'mrkdwn', 'text' => "*Failures*\n{$failed}"],
                                ['type' => 'mrkdwn', 'text' => "*Failure rate*\n{$failureRate}%"],
                            ],
                        ],
                        [
                            'type' => 'actions',
                            'elements' => [
                                [
                                    'type' => 'button',
                                    'text' => ['type' => 'plain_text', 'text' => 'Admin Actions'],
                                    'url' => url($links['admin_actions']),
                                ],
                                [
                                    'type' => 'button',
                                    'text' => ['type' => 'plain_text', 'text' => 'Delivery Trends'],
                                    'url' => url($links['delivery_trends']),
                                ],
                            ],
                        ],
                    ],
                ])->throw();
                $slackDispatches++;
                $deliverySucceeded = true;
            } catch (\Throwable $exception) {
                report($exception);
                $this->warn('Slack delivery failed for scheduled operations report.');
            }
        }
    }

    if (! $deliverySucceeded) {
        $this->line('No scheduled report deliveries were dispatched.');

        return self::SUCCESS;
    }

    $settings->markDeliverySent();

    $this->info(
        'Scheduled operations report delivered via '
        .'in-app recipients: '.$targetedUsers->count()
        .", email recipients: {$sentEmailCount}, webhook dispatches: {$webhookDispatches}, slack dispatches: {$slackDispatches}."
    );

    return self::SUCCESS;
})->purpose('Deliver scheduled operations report exports to platform admins');

Artisan::command('company:reports:deliver-scheduled {--force}', function () {
    $settings = app(CompanyReportingSettingsService::class);
    $reportsService = app(CompanyReportsService::class);
    $force = (bool) $this->option('force');
    $companies = Company::query()
        ->where('is_active', true)
        ->with('users:id,name,email')
        ->get(['id', 'name', 'timezone', 'currency_code']);

    $deliveredToUsers = 0;
    $triggeredCompanies = 0;

    foreach ($companies as $company) {
        $schedule = $settings->getDeliverySchedule($company->id);

        if (! $force && ! $settings->shouldSendScheduledDeliveryNow($schedule)) {
            continue;
        }

        $filters = $settings->filtersForPreset(
            $company->id,
            $schedule['preset_id'] ?? null
        );

        $reportKey = (string) ($schedule['report_key'] ?? CompanyReportsService::REPORT_APPROVAL_GOVERNANCE);
        $report = $reportsService->buildReport($company, $reportKey, $filters);

        if (! $report) {
            continue;
        }

        if (! $force && count($report['rows']) === 0) {
            continue;
        }

        $format = in_array((string) ($schedule['format'] ?? 'xlsx'), ['pdf', 'xlsx'], true)
            ? (string) $schedule['format']
            : 'xlsx';
        $query = $settings->filtersToQuery($filters);
        $link = "/company/reports/export/{$reportKey}?{$query}&format={$format}";

        $presetName = 'Default filters';

        if ($schedule['preset_id'] ?? null) {
            $preset = collect($settings->getPresets($company->id))
                ->first(fn (array $row) => $row['id'] === $schedule['preset_id']);
            $presetName = (string) ($preset['name'] ?? $presetName);
        }

        $periodLabel = ((int) ($filters['trend_window'] ?? 30)).'-day window';

        if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
            $periodLabel = sprintf(
                '%s to %s',
                (string) ($filters['start_date'] ?? 'start'),
                (string) ($filters['end_date'] ?? 'end')
            );
        }

        $recipients = $company->users
            ->filter(fn (User $user) => $user->hasPermission('reports.view', $company))
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            continue;
        }

        Notification::send(
            $recipients,
            new CompanyReportDeliveryNotification(
                companyName: (string) $company->name,
                reportTitle: (string) $report['title'],
                presetName: $presetName,
                periodLabel: $periodLabel,
                format: $format,
                link: $link,
                summary: [
                    'rows' => count($report['rows']),
                    'columns' => count($report['columns']),
                    'report_key' => $reportKey,
                ],
            )
        );

        $settings->markDeliverySent($company->id);
        $deliveredToUsers += $recipients->count();
        $triggeredCompanies += 1;
    }

    if ($triggeredCompanies === 0) {
        $this->line('No company report schedules due or no data to deliver.');

        return self::SUCCESS;
    }

    $this->info("Scheduled company reports delivered for {$triggeredCompanies} compan(ies) to {$deliveredToUsers} recipient(s).");

    return self::SUCCESS;
})->purpose('Deliver scheduled company report exports to company recipients');

Artisan::command('accounting:backfill-ledger {companyId?}', function (?string $companyId = null) {
    $service = app(AccountingLedgerBackfillService::class);

    if ($companyId) {
        $service->backfillCompany($companyId);
        $this->info("Ledger foundations backfilled for company {$companyId}.");

        return self::SUCCESS;
    }

    $count = 0;

    Company::query()->select('id')->chunkById(100, function ($companies) use ($service, &$count): void {
        foreach ($companies as $company) {
            $service->backfillCompany($company->id);
            $count++;
        }
    }, 'id');

    $this->info("Ledger foundations backfilled for {$count} compan(ies).");

    return self::SUCCESS;
})->purpose('Backfill accounting ledger entries and default chart of accounts for existing companies');

Artisan::command('projects:recurring-billing:run {companyId?} {--schedule=}', function (?string $companyId = null) {
    $service = app(ProjectRecurringBillingService::class);
    $approvalQueueService = app(ApprovalQueueService::class);
    $scheduleId = $this->option('schedule');

    if (is_string($scheduleId) && trim($scheduleId) !== '') {
        $schedule = ProjectRecurringBilling::query()
            ->when(
                filled($companyId),
                fn ($query) => $query->where('company_id', $companyId),
            )
            ->findOrFail($scheduleId);

        $run = $service->runNow($schedule);
        $processed = collect($run ? [$run] : []);
    } else {
        $processed = $service->processDueSchedules($companyId);
    }

    if ($processed->isEmpty()) {
        $this->line('No recurring billing schedules were processed.');

        return self::SUCCESS;
    }

    $companyIds = $processed
        ->pluck('company_id')
        ->filter()
        ->unique()
        ->values();

    if ($companyIds->isNotEmpty()) {
        Company::query()
            ->whereIn('id', $companyIds)
            ->get()
            ->each(fn (Company $company) => $approvalQueueService->syncPendingRequests($company));
    }

    $failed = $processed
        ->where('status', ProjectRecurringBillingRun::STATUS_FAILED)
        ->count();
    $invoiced = $processed
        ->where('status', ProjectRecurringBillingRun::STATUS_INVOICED)
        ->count();
    $pendingApproval = $processed
        ->where('status', ProjectRecurringBillingRun::STATUS_PENDING_APPROVAL)
        ->count();
    $ready = $processed
        ->where('status', ProjectRecurringBillingRun::STATUS_READY)
        ->count();

    $this->info(
        "Processed {$processed->count()} recurring billing run(s). "
        ."Ready: {$ready}, pending approval: {$pendingApproval}, invoiced: {$invoiced}, failed: {$failed}."
    );

    return self::SUCCESS;
})->purpose('Process due project recurring billing schedules and create billables or invoices');

Artisan::command('inventory:reorder-scan {companyId?}', function (?string $companyId = null) {
    $service = app(InventoryReorderService::class);

    if ($companyId) {
        $processed = $service->scanCompany($companyId);
        $this->info("Processed {$processed->count()} replenishment suggestion(s) for company {$companyId}.");

        return self::SUCCESS;
    }

    $companies = Company::query()
        ->where('is_active', true)
        ->pluck('id');

    $suggestions = 0;

    foreach ($companies as $activeCompanyId) {
        $suggestions += $service->scanCompany((string) $activeCompanyId)->count();
    }

    $this->info("Processed {$suggestions} replenishment suggestion(s) across {$companies->count()} compan(ies).");

    return self::SUCCESS;
})->purpose('Scan inventory reorder rules and refresh replenishment suggestions');

Artisan::command('platform:operations:heartbeat', function () {
    app(PlatformOperationalAlertingService::class)->recordSchedulerHeartbeat();

    $this->info('Platform scheduler heartbeat recorded.');

    return self::SUCCESS;
})->purpose('Record a scheduler heartbeat for platform operations monitoring');

Artisan::command('platform:operations:scan-alerts {--force}', function () {
    $result = app(PlatformOperationalAlertingService::class)->scan(
        force: (bool) $this->option('force'),
    );

    $this->info(
        "Platform alert scan complete. Opened: {$result['opened']}, notified: {$result['notified']}, resolved: {$result['resolved']}, active: {$result['active']}."
    );

    return self::SUCCESS;
})->purpose('Evaluate platform operational thresholds and dispatch operator alerts');

Schedule::command('core:audit-logs:prune')->dailyAt('03:00');
Schedule::command('platform:operations:heartbeat')->everyMinute();
Schedule::command('platform:operations:scan-alerts')->everyFiveMinutes();
Schedule::command('platform:notifications:send-digest')->everyMinute();
Schedule::command('platform:operations-reports:deliver-scheduled')->everyMinute();
Schedule::command('company:reports:deliver-scheduled')->everyMinute();
Schedule::command('projects:recurring-billing:run')->everyMinute();
Schedule::command('inventory:reorder-scan')->hourly();
