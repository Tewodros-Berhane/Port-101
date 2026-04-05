<?php

use App\Core\Company\Models\Company;
use App\Core\Platform\Models\QueueFailureReview;
use App\Models\User;
use App\Modules\Integrations\Models\IntegrationEvent;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\WebhookEventCatalog;
use App\Modules\Reports\CompanyReportsService;
use App\Modules\Reports\Models\ReportExport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\Jobs\CaptureRetriedQueueJob;

use function Pest\Laravel\actingAs;

function createQueueHealthSuperAdmin(): User
{
    return User::factory()->create([
        'is_super_admin' => true,
    ]);
}

function createQueueHealthCompany(User $owner): Company
{
    $name = 'Queue Health Co '.Str::upper(Str::random(4));

    return Company::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $owner->id,
    ]);
}

function createFailedQueueHealthJob(
    string $label,
    ?string $companyId = null,
    ?string $userId = null,
    ?\Throwable $exception = null,
): string {
    $payload = json_encode([
        'uuid' => (string) Str::uuid(),
        'displayName' => CaptureRetriedQueueJob::class,
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'maxTries' => null,
        'maxExceptions' => null,
        'failOnTimeout' => false,
        'backoff' => null,
        'timeout' => null,
        'retryUntil' => null,
        'data' => [
            'commandName' => CaptureRetriedQueueJob::class,
            'command' => serialize(new CaptureRetriedQueueJob($label)),
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
        $exception ?? new RuntimeException('Queue failure for '.$label),
    );
}

function createQueueHealthWebhookEndpoint(string $companyId, string $userId): WebhookEndpoint
{
    return WebhookEndpoint::create([
        'company_id' => $companyId,
        'name' => 'Queue Health Endpoint',
        'target_url' => 'https://hooks.example.com/queue-health',
        'signing_secret' => Str::random(64),
        'api_version' => 'v1',
        'is_active' => true,
        'subscribed_events' => [WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED],
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function createQueueHealthDeadWebhookDelivery(string $companyId, string $userId): WebhookDelivery
{
    $endpoint = createQueueHealthWebhookEndpoint($companyId, $userId);

    $event = IntegrationEvent::create([
        'company_id' => $companyId,
        'event_type' => WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED,
        'aggregate_type' => WebhookEndpoint::class,
        'aggregate_id' => $endpoint->id,
        'occurred_at' => now(),
        'payload' => [
            'event_type' => WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED,
            'data' => ['reference' => 'INV-QUEUE-1'],
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
        'failure_message' => 'Upstream endpoint unavailable',
        'dead_lettered_at' => now(),
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function createFailedQueueHealthReportExport(string $companyId, string $userId): ReportExport
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

test('superadmin can view the platform queue health dashboard', function () {
    $superAdmin = createQueueHealthSuperAdmin();
    $company = createQueueHealthCompany($superAdmin);

    createFailedQueueHealthJob('dashboard-render', $company->id, $superAdmin->id);
    createFailedQueueHealthJob(
        'authorization-poison',
        $company->id,
        $superAdmin->id,
        new AuthorizationException('Forbidden queue action'),
    );
    createQueueHealthDeadWebhookDelivery($company->id, $superAdmin->id);
    createFailedQueueHealthReportExport($company->id, $superAdmin->id);

    actingAs($superAdmin)
        ->get(route('platform.queue-health'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/operations/queue-health')
            ->has('summary')
            ->has('backlogByQueue')
            ->where('summary.poison_suspected_failed_jobs', 1)
            ->has('failedJobs.data', 2)
            ->has('recentPoisonReviews')
            ->has('deadWebhookDeliveries', 1)
            ->has('failedReportExports', 1));
});

test('superadmin can retry and forget failed queue jobs from queue health', function () {
    $superAdmin = createQueueHealthSuperAdmin();

    CaptureRetriedQueueJob::$handledLabels = [];

    $retryId = createFailedQueueHealthJob('retry-me');
    $forgetId = createFailedQueueHealthJob('forget-me');

    actingAs($superAdmin)
        ->from(route('platform.queue-health'))
        ->post(route('platform.queue-health.failed-jobs.retry', ['failedJobId' => $retryId]))
        ->assertRedirect(route('platform.queue-health'));

    expect(CaptureRetriedQueueJob::$handledLabels)->toContain('retry-me');
    expect(app(FailedJobProviderInterface::class)->find($retryId))->toBeNull();

    actingAs($superAdmin)
        ->from(route('platform.queue-health'))
        ->delete(route('platform.queue-health.failed-jobs.destroy', ['failedJobId' => $forgetId]))
        ->assertRedirect(route('platform.queue-health'));

    expect(app(FailedJobProviderInterface::class)->find($forgetId))->toBeNull();
});

test('superadmin can discard poison jobs from queue health and retain review history', function () {
    $superAdmin = createQueueHealthSuperAdmin();
    $company = createQueueHealthCompany($superAdmin);

    $poisonId = createFailedQueueHealthJob(
        'discard-poison',
        $company->id,
        $superAdmin->id,
        new AuthorizationException('Forbidden queue action'),
    );

    actingAs($superAdmin)
        ->from(route('platform.queue-health'))
        ->post(route('platform.queue-health.failed-jobs.discard-poison', ['failedJobId' => $poisonId]))
        ->assertRedirect(route('platform.queue-health'));

    expect(app(FailedJobProviderInterface::class)->find($poisonId))->toBeNull();

    $review = QueueFailureReview::query()
        ->where('failed_job_id', $poisonId)
        ->first();

    expect($review)->not->toBeNull()
        ->and($review?->decision)->toBe(QueueFailureReview::DECISION_DISCARDED)
        ->and($review?->classification)->toBe(QueueFailureReview::CLASSIFICATION_POISON_SUSPECTED);
});

test('superadmin can retry dead webhook deliveries from queue health', function () {
    Http::fake([
        'https://hooks.example.com/queue-health' => Http::response(['ok' => true], 200),
    ]);

    $superAdmin = createQueueHealthSuperAdmin();
    $company = createQueueHealthCompany($superAdmin);
    $delivery = createQueueHealthDeadWebhookDelivery($company->id, $superAdmin->id);

    actingAs($superAdmin)
        ->from(route('platform.queue-health'))
        ->post(route('platform.queue-health.webhook-deliveries.retry', $delivery))
        ->assertRedirect(route('platform.queue-health'));

    expect($delivery->fresh()->status)->toBe(WebhookDelivery::STATUS_DELIVERED);
});

test('superadmin can retry failed report exports from queue health', function () {
    Storage::fake('local');

    $superAdmin = createQueueHealthSuperAdmin();
    $company = createQueueHealthCompany($superAdmin);
    $export = createFailedQueueHealthReportExport($company->id, $superAdmin->id);

    actingAs($superAdmin)
        ->from(route('platform.queue-health'))
        ->post(route('platform.queue-health.report-exports.retry', $export))
        ->assertRedirect(route('platform.queue-health'));

    $export->refresh();

    expect($export->status)->toBe(ReportExport::STATUS_COMPLETED)
        ->and($export->file_path)->not->toBeNull();
});

test('queue health sanitizes stored webhook and report failure messages for display', function () {
    $superAdmin = createQueueHealthSuperAdmin();
    $company = createQueueHealthCompany($superAdmin);

    $delivery = createQueueHealthDeadWebhookDelivery($company->id, $superAdmin->id);
    $delivery->forceFill([
        'failure_message' => 'upstream token=secret-value',
        'response_status' => 500,
        'response_body_excerpt' => 'api_key=secret-value stack trace',
    ])->save();

    $export = createFailedQueueHealthReportExport($company->id, $superAdmin->id);
    $export->forceFill([
        'failure_message' => 'SQLSTATE[08006] password=secret',
    ])->save();

    actingAs($superAdmin)
        ->get(route('platform.queue-health'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('deadWebhookDeliveries.0.failure_message', 'Endpoint returned a server error (HTTP 500).')
            ->where('failedReportExports.0.failure_message', 'The export failed while generating the output file.'));
});
