<?php

use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\WebhookEventCatalog;
use App\Modules\Integrations\WebhookTargetSecurityService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function assignWebhookApiRole(User $user, string $companyId, array $permissionSlugs): void
{
    $role = Role::create([
        'name' => 'Webhook API Role '.Str::upper(Str::random(4)),
        'slug' => 'webhook-api-role-'.Str::lower(Str::random(8)),
        'description' => 'Webhook API test role',
        'data_scope' => User::DATA_SCOPE_COMPANY,
        'company_id' => null,
    ]);

    $permissionIds = collect($permissionSlugs)
        ->map(function (string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'integrations']
            )->id;
        })
        ->all();

    $role->permissions()->sync($permissionIds);

    $user->memberships()->updateOrCreate(
        ['company_id' => $companyId],
        [
            'role_id' => $role->id,
            'is_owner' => false,
        ],
    );

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

test('api v1 webhook endpoints support management test delivery and retry flows', function () {
    [$manager, $company] = makeActiveCompanyMember();
    [$otherManager, $otherCompany] = makeActiveCompanyMember();

    assignWebhookApiRole($manager, $company->id, [
        'integrations.webhooks.view',
        'integrations.webhooks.manage',
    ]);
    assignWebhookApiRole($otherManager, $otherCompany->id, [
        'integrations.webhooks.view',
        'integrations.webhooks.manage',
    ]);

    $otherEndpoint = WebhookEndpoint::create([
        'company_id' => $otherCompany->id,
        'name' => 'Other Company Endpoint',
        'target_url' => 'https://hooks.example.com/other',
        'signing_secret' => Str::random(64),
        'api_version' => 'v1',
        'is_active' => true,
        'subscribed_events' => [WebhookEventCatalog::SALES_ORDER_CONFIRMED],
        'created_by' => $otherManager->id,
        'updated_by' => $otherManager->id,
    ]);

    Sanctum::actingAs($manager);

    $createResponse = postJson('/api/v1/webhooks/endpoints', [
        'name' => 'Finance Hub',
        'target_url' => 'https://hooks.example.com/finance',
        'is_active' => true,
        'subscribed_events' => [
            WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED,
            WebhookEventCatalog::ACCOUNTING_PAYMENT_RECEIVED,
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Finance Hub')
        ->assertJsonPath('data.target_url', 'https://hooks.example.com/finance')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.subscribed_events.0', WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED)
        ->assertJsonPath('data.signing_secret_version', 1)
        ->assertJsonPath('data.recent_secret_rotations.0.reason', 'created')
        ->assertJsonPath('data.delivery_security_policy.replay_window_seconds', 300);

    $endpointId = (string) $createResponse->json('data.id');
    $originalSecret = (string) $createResponse->json('data.revealed_signing_secret');

    expect($originalSecret)->not->toBe('');

    getJson('/api/v1/webhooks/endpoints?search=Finance&sort=name&direction=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $endpointId)
        ->assertJsonPath('data.0.health_status', 'healthy')
        ->assertJsonPath('meta.filters.search', 'Finance')
        ->assertJsonPath('meta.sort', 'name')
        ->assertJsonPath('meta.delivery_security_policy.signature_version', 'v1');

    getJson('/api/v1/webhooks/endpoints/'.$otherEndpoint->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');

    patchJson('/api/v1/webhooks/endpoints/'.$endpointId, [
        'name' => 'Finance Hub Updated',
        'target_url' => 'https://hooks.example.com/finance',
        'is_active' => true,
        'subscribed_events' => ['*'],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Finance Hub Updated')
        ->assertJsonPath('data.subscribed_events.0', '*');

    $rotateResponse = postJson('/api/v1/webhooks/endpoints/'.$endpointId.'/rotate-secret')
        ->assertOk()
        ->assertJsonPath('data.id', $endpointId)
        ->assertJsonPath('data.signing_secret_version', 2)
        ->assertJsonPath('data.recent_secret_rotations.0.reason', 'manual');

    $rotatedSecret = (string) $rotateResponse->json('data.revealed_signing_secret');

    expect($rotatedSecret)->not->toBe('');
    expect($rotatedSecret)->not->toBe($originalSecret);

    Http::fake([
        'https://hooks.example.com/finance' => Http::sequence()
            ->push('temporary failure', 500)
            ->push(['ok' => true], 200),
    ]);

    $testDeliveryResponse = postJson('/api/v1/webhooks/endpoints/'.$endpointId.'/test', [], apiIdempotencyHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', WebhookDelivery::STATUS_FAILED)
        ->assertJsonPath('data.response_status', 500);

    $deliveryId = (string) $testDeliveryResponse->json('data.id');

    expect($testDeliveryResponse->json('data.first_attempt_at'))->not->toBeNull();

    getJson('/api/v1/webhooks/endpoints/'.$endpointId.'/deliveries?status=failed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $deliveryId)
        ->assertJsonPath('meta.filters.status', WebhookDelivery::STATUS_FAILED)
        ->assertJsonPath('meta.delivery_security_policy.replay_window_seconds', 300);

    getJson('/api/v1/webhooks/deliveries/'.$deliveryId)
        ->assertOk()
        ->assertJsonPath('data.id', $deliveryId)
        ->assertJsonPath('data.status', WebhookDelivery::STATUS_FAILED)
        ->assertJsonPath('data.event_type', WebhookEventCatalog::SYSTEM_WEBHOOK_TEST)
        ->assertJsonPath('meta.delivery_security_policy.signature_algorithm', 'hmac-sha256');

    postJson('/api/v1/webhooks/deliveries/'.$deliveryId.'/retry', [], apiIdempotencyHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', WebhookDelivery::STATUS_DELIVERED)
        ->assertJsonPath('data.response_status', 200);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        $signatureVersion = $request->header('X-Port101-Signature-Version');
        $replayWindow = $request->header('X-Port101-Replay-Window-Seconds');
        $deliveryAttempt = $request->header('X-Port101-Delivery-Attempt');

        return $request->hasHeader('X-Port101-Event')
            && $request->hasHeader('X-Port101-Event-Id')
            && $request->hasHeader('X-Port101-Timestamp')
            && $request->hasHeader('X-Port101-Signature')
            && ($signatureVersion[0] ?? null) === 'v1'
            && ($replayWindow[0] ?? null) === '300'
            && in_array($deliveryAttempt[0] ?? null, ['1', '2'], true)
            && $request->url() === 'https://hooks.example.com/finance';
    });

    deleteJson('/api/v1/webhooks/endpoints/'.$endpointId)
        ->assertNoContent();
});

test('api v1 webhook management enforces permissions and validation contracts', function () {
    [$viewer, $company] = makeActiveCompanyMember();

    assignWebhookApiRole($viewer, $company->id, [
        'integrations.webhooks.view',
    ]);

    $endpoint = WebhookEndpoint::create([
        'company_id' => $company->id,
        'name' => 'Viewer Endpoint',
        'target_url' => 'https://hooks.example.com/viewer',
        'signing_secret' => Str::random(64),
        'api_version' => 'v1',
        'is_active' => true,
        'subscribed_events' => [WebhookEventCatalog::SALES_ORDER_CONFIRMED],
        'created_by' => $viewer->id,
        'updated_by' => $viewer->id,
    ]);

    Sanctum::actingAs($viewer);

    getJson('/api/v1/webhooks/endpoints')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    postJson('/api/v1/webhooks/endpoints', [
        'name' => 'Forbidden Endpoint',
        'target_url' => 'https://hooks.example.com/forbidden',
        'subscribed_events' => [WebhookEventCatalog::SALES_ORDER_CONFIRMED],
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');

    postJson('/api/v1/webhooks/endpoints/'.$endpoint->id.'/rotate-secret')
        ->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');

    assignWebhookApiRole($viewer, $company->id, [
        'integrations.webhooks.view',
        'integrations.webhooks.manage',
    ]);

    Sanctum::actingAs($viewer);

    postJson('/api/v1/webhooks/endpoints', [
        'name' => '',
        'target_url' => 'not-a-url',
        'subscribed_events' => ['not-real'],
    ])
        ->assertUnprocessable()
        ->assertJsonStructure([
            'message',
            'errors' => ['name', 'target_url', 'subscribed_events.0'],
        ]);

    Config::set('core.webhooks.require_https', true);
    Config::set('core.webhooks.allow_private_targets', false);

    postJson('/api/v1/webhooks/endpoints', [
        'name' => 'Unsafe Endpoint',
        'target_url' => 'https://127.0.0.1/internal',
        'subscribed_events' => [WebhookEventCatalog::SALES_ORDER_CONFIRMED],
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.target_url.0',
            'Webhook target URL cannot target localhost or local-only hostnames.',
        );

    app()->instance(WebhookTargetSecurityService::class, new class extends WebhookTargetSecurityService
    {
        protected function resolveHostIps(string $host): array
        {
            if ($host === 'private-resolved.example.com') {
                return ['10.0.0.44'];
            }

            return parent::resolveHostIps($host);
        }
    });

    postJson('/api/v1/webhooks/endpoints', [
        'name' => 'Rebound Endpoint',
        'target_url' => 'https://private-resolved.example.com/hooks',
        'subscribed_events' => [WebhookEventCatalog::SALES_ORDER_CONFIRMED],
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.target_url.0',
            'Webhook target URL cannot resolve to private or reserved IP ranges.',
        );

    $delivered = WebhookDelivery::create([
        'company_id' => $company->id,
        'webhook_endpoint_id' => $endpoint->id,
        'integration_event_id' => \App\Modules\Integrations\Models\IntegrationEvent::create([
            'company_id' => $company->id,
            'event_type' => WebhookEventCatalog::SYSTEM_WEBHOOK_TEST,
            'aggregate_type' => WebhookEndpoint::class,
            'aggregate_id' => $endpoint->id,
            'occurred_at' => now(),
            'payload' => ['event_type' => WebhookEventCatalog::SYSTEM_WEBHOOK_TEST],
            'created_by' => $viewer->id,
            'updated_by' => $viewer->id,
        ])->id,
        'event_type' => WebhookEventCatalog::SYSTEM_WEBHOOK_TEST,
        'status' => WebhookDelivery::STATUS_DELIVERED,
        'attempt_count' => 1,
        'delivered_at' => now(),
        'created_by' => $viewer->id,
        'updated_by' => $viewer->id,
    ]);

    postJson('/api/v1/webhooks/deliveries/'.$delivered->id.'/retry', [], apiIdempotencyHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only failed or dead deliveries can be retried.');
});

test('api v1 webhook test deliveries revalidate targets before sending', function () {
    [$manager, $company] = makeActiveCompanyMember();

    assignWebhookApiRole($manager, $company->id, [
        'integrations.webhooks.view',
        'integrations.webhooks.manage',
    ]);

    $endpoint = WebhookEndpoint::create([
        'company_id' => $company->id,
        'name' => 'DNS Rebound Endpoint',
        'target_url' => 'https://runtime-private.example.com/hooks',
        'signing_secret' => Str::random(64),
        'api_version' => 'v1',
        'is_active' => true,
        'subscribed_events' => [WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED],
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    Config::set('core.webhooks.allow_private_targets', false);
    app()->instance(WebhookTargetSecurityService::class, new class extends WebhookTargetSecurityService
    {
        protected function resolveHostIps(string $host): array
        {
            if ($host === 'runtime-private.example.com') {
                return ['10.10.10.10'];
            }

            return parent::resolveHostIps($host);
        }
    });

    Sanctum::actingAs($manager);

    postJson('/api/v1/webhooks/endpoints/'.$endpoint->id.'/test', [], apiIdempotencyHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', WebhookDelivery::STATUS_DEAD)
        ->assertJsonPath(
            'data.failure_message',
            'Webhook target URL cannot resolve to private or reserved IP ranges.',
        );

    Http::assertNothingSent();
});

test('api v1 webhook test deliveries replay duplicate idempotent requests without re-sending', function () {
    [$manager, $company] = makeActiveCompanyMember();

    assignWebhookApiRole($manager, $company->id, [
        'integrations.webhooks.view',
        'integrations.webhooks.manage',
    ]);

    $endpoint = WebhookEndpoint::create([
        'company_id' => $company->id,
        'name' => 'Replay Endpoint',
        'target_url' => 'https://hooks.example.com/replay',
        'signing_secret' => Str::random(64),
        'api_version' => 'v1',
        'is_active' => true,
        'subscribed_events' => [WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED],
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    Sanctum::actingAs($manager);

    Http::fake([
        'https://hooks.example.com/replay' => Http::response(['ok' => true], 200),
    ]);

    $key = 'webhook-test-delivery';

    postJson('/api/v1/webhooks/endpoints/'.$endpoint->id.'/test')
        ->assertBadRequest()
        ->assertJsonPath('message', 'Idempotency-Key header is required for this endpoint.');

    $firstResponse = postJson('/api/v1/webhooks/endpoints/'.$endpoint->id.'/test', [], apiIdempotencyHeaders($key))
        ->assertOk()
        ->assertHeader('Idempotency-Key', $key)
        ->assertHeader('X-Port101-Idempotency-Replayed', 'false');

    $deliveryId = (string) $firstResponse->json('data.id');

    postJson('/api/v1/webhooks/endpoints/'.$endpoint->id.'/test', [], apiIdempotencyHeaders($key))
        ->assertOk()
        ->assertHeader('Idempotency-Key', $key)
        ->assertHeader('X-Port101-Idempotency-Replayed', 'true')
        ->assertJsonPath('data.id', $deliveryId);

    Http::assertSentCount(1);
    expect(WebhookDelivery::query()->where('company_id', $company->id)->count())->toBe(1);
});
