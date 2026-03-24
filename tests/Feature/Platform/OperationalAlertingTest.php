<?php

use App\Core\Company\Models\Company;
use App\Core\Platform\Models\PlatformAlertIncident;
use App\Core\Platform\PlatformOperationalAlertingService;
use App\Models\User;
use App\Modules\Integrations\Models\IntegrationEvent;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\WebhookEventCatalog;
use App\Modules\Reports\CompanyReportsService;
use App\Modules\Reports\Models\ReportExport;
use App\Notifications\PlatformOperationsAlertNotification;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

function createOperationalAlertSuperAdmin(): User
{
    return User::factory()->create([
        'is_super_admin' => true,
    ]);
}

function createOperationalAlertCompany(User $owner): Company
{
    $name = 'Ops Alert Co '.Str::upper(Str::random(4));

    return Company::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $owner->id,
    ]);
}

function createOperationalAlertFailedJob(?string $companyId = null, ?string $userId = null): string
{
    $payload = json_encode([
        'uuid' => (string) Str::uuid(),
        'displayName' => 'Tests\\Fixtures\\Jobs\\CaptureRetriedQueueJob',
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'data' => [
            'commandName' => 'Tests\\Fixtures\\Jobs\\CaptureRetriedQueueJob',
            'command' => serialize(new Tests\Fixtures\Jobs\CaptureRetriedQueueJob('ops-alert')),
        ],
        'port101_context' => array_filter([
            'request_id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'user_id' => $userId,
            'correlation_origin' => 'http',
        ]),
        'createdAt' => now()->timestamp,
    ], JSON_UNESCAPED_UNICODE);

    /** @var FailedJobProviderInterface $failer */
    $failer = app(FailedJobProviderInterface::class);

    return (string) $failer->log(
        'sync',
        'default',
        $payload ?: '{}',
        new RuntimeException('Operational alert test failure'),
    );
}

function createOperationalAlertBacklogJob(): void
{
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'Queued backlog job']),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);
}

function createOperationalAlertDeadWebhookDelivery(string $companyId, string $userId): WebhookDelivery
{
    $endpoint = WebhookEndpoint::create([
        'company_id' => $companyId,
        'name' => 'Ops Alert Endpoint',
        'target_url' => 'https://hooks.example.com/ops-alert',
        'signing_secret' => Str::random(64),
        'api_version' => 'v1',
        'is_active' => true,
        'subscribed_events' => [WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED],
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);

    $event = IntegrationEvent::create([
        'company_id' => $companyId,
        'event_type' => WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED,
        'aggregate_type' => WebhookEndpoint::class,
        'aggregate_id' => $endpoint->id,
        'occurred_at' => now(),
        'payload' => [
            'event_type' => WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED,
            'data' => ['reference' => 'INV-OPS-1'],
        ],
        'published_at' => now(),
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);

    return WebhookDelivery::create([
        'company_id' => $companyId,
        'webhook_endpoint_id' => $endpoint->id,
        'integration_event_id' => $event->id,
        'event_type' => WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED,
        'status' => WebhookDelivery::STATUS_DEAD,
        'attempt_count' => 5,
        'last_attempt_at' => now(),
        'response_status' => 500,
        'failure_message' => 'Endpoint unavailable',
        'dead_lettered_at' => now(),
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function createOperationalAlertFailedReportExport(string $companyId, string $userId): ReportExport
{
    return ReportExport::create([
        'company_id' => $companyId,
        'report_key' => CompanyReportsService::REPORT_FINANCE_SNAPSHOT,
        'report_title' => 'Finance Snapshot Report',
        'format' => ReportExport::FORMAT_PDF,
        'status' => ReportExport::STATUS_FAILED,
        'filters' => ['trend_window' => 30],
        'requested_by_user_id' => $userId,
        'failed_at' => now(),
        'failure_message' => 'Queue worker timed out.',
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

test('superadmin can update operational alerting settings and view governance status', function () {
    $superAdmin = createOperationalAlertSuperAdmin();

    actingAs($superAdmin)
        ->put(route('platform.dashboard.operational-alerting.update'), [
            'enabled' => true,
            'cooldown_minutes' => 45,
            'failed_jobs_threshold' => 3,
            'queue_backlog_threshold' => 20,
            'dead_webhook_threshold' => 2,
            'failed_report_export_threshold' => 1,
            'scheduler_drift_minutes' => 12,
        ])
        ->assertRedirect(route('platform.governance'));

    expect(app(PlatformOperationalAlertingService::class)->getSettings())
        ->toMatchArray([
            'enabled' => true,
            'cooldown_minutes' => 45,
            'failed_jobs_threshold' => 3,
            'queue_backlog_threshold' => 20,
            'dead_webhook_threshold' => 2,
            'failed_report_export_threshold' => 1,
            'scheduler_drift_minutes' => 12,
        ]);

    actingAs($superAdmin)
        ->get(route('platform.governance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/governance')
            ->has('operationalAlerting')
            ->has('operationalAlertingStatus.active_incidents')
            ->has('operationalAlertingStatus.heartbeat'));
});

test('platform operational alert scan opens incidents, notifies admins, and resolves recovered conditions', function () {
    $superAdmin = createOperationalAlertSuperAdmin();
    $company = createOperationalAlertCompany($superAdmin);

    $failedJobId = createOperationalAlertFailedJob($company->id, $superAdmin->id);
    createOperationalAlertBacklogJob();
    $delivery = createOperationalAlertDeadWebhookDelivery($company->id, $superAdmin->id);
    $export = createOperationalAlertFailedReportExport($company->id, $superAdmin->id);

    $alerting = app(PlatformOperationalAlertingService::class);
    $alerting->setSettings([
        'enabled' => true,
        'cooldown_minutes' => 60,
        'failed_jobs_threshold' => 1,
        'queue_backlog_threshold' => 1,
        'dead_webhook_threshold' => 1,
        'failed_report_export_threshold' => 1,
        'scheduler_drift_minutes' => 5,
    ], $superAdmin->id);

    app(\App\Core\Settings\SettingsService::class)->set(
        PlatformOperationalAlertingService::HEARTBEAT_KEY,
        now()->subMinutes(20)->toIso8601String(),
    );

    artisan('platform:operations:scan-alerts', ['--force' => true])
        ->assertSuccessful();

    expect(
        PlatformAlertIncident::query()
            ->where('status', PlatformAlertIncident::STATUS_OPEN)
            ->count()
    )->toBe(5);

    expect(
        $superAdmin->fresh()
            ->notifications()
            ->where('type', PlatformOperationsAlertNotification::class)
            ->count()
    )->toBe(5);

    artisan('platform:operations:scan-alerts')
        ->assertSuccessful();

    expect(
        $superAdmin->fresh()
            ->notifications()
            ->where('type', PlatformOperationsAlertNotification::class)
            ->count()
    )->toBe(5);

    app(FailedJobProviderInterface::class)->forget($failedJobId);
    DB::table('jobs')->delete();

    $delivery->update([
        'status' => WebhookDelivery::STATUS_DELIVERED,
        'dead_lettered_at' => null,
        'failure_message' => null,
        'response_status' => 200,
    ]);

    $export->update([
        'status' => ReportExport::STATUS_COMPLETED,
        'failed_at' => null,
        'failure_message' => null,
        'completed_at' => now(),
        'file_path' => 'exports/test.pdf',
        'file_name' => 'test.pdf',
        'mime_type' => 'application/pdf',
    ]);

    artisan('platform:operations:heartbeat')
        ->assertSuccessful();

    artisan('platform:operations:scan-alerts')
        ->assertSuccessful();

    expect(
        PlatformAlertIncident::query()
            ->where('status', PlatformAlertIncident::STATUS_OPEN)
            ->count()
    )->toBe(0);

    expect(
        PlatformAlertIncident::query()
            ->where('status', PlatformAlertIncident::STATUS_RESOLVED)
            ->count()
    )->toBe(5);
});
