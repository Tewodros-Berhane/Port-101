<?php

use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\WebhookEventCatalog;
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
        ->assertJsonPath('data.subscribed_events.0', WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED);

    $endpointId = (string) $createResponse->json('data.id');
    $originalSecret = (string) $createResponse->json('data.revealed_signing_secret');

    expect($originalSecret)->not->toBe('');

    getJson('/api/v1/webhooks/endpoints?search=Finance&sort=name&direction=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $endpointId)
        ->assertJsonPath('meta.filters.search', 'Finance')
        ->assertJsonPath('meta.sort', 'name');

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
        ->assertJsonPath('data.id', $endpointId);

    $rotatedSecret = (string) $rotateResponse->json('data.revealed_signing_secret');

    expect($rotatedSecret)->not->toBe('');
    expect($rotatedSecret)->not->toBe($originalSecret);

    Http::fake([
        'https://hooks.example.com/finance' => Http::sequence()
            ->push('temporary failure', 500)
            ->push(['ok' => true], 200),
    ]);

    $testDeliveryResponse = postJson('/api/v1/webhooks/endpoints/'.$endpointId.'/test')
        ->assertOk()
        ->assertJsonPath('data.status', WebhookDelivery::STATUS_FAILED)
        ->assertJsonPath('data.response_status', 500);

    $deliveryId = (string) $testDeliveryResponse->json('data.id');

    getJson('/api/v1/webhooks/endpoints/'.$endpointId.'/deliveries?status=failed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $deliveryId)
        ->assertJsonPath('meta.filters.status', WebhookDelivery::STATUS_FAILED);

    getJson('/api/v1/webhooks/deliveries/'.$deliveryId)
        ->assertOk()
        ->assertJsonPath('data.id', $deliveryId)
        ->assertJsonPath('data.status', WebhookDelivery::STATUS_FAILED)
        ->assertJsonPath('data.event_type', WebhookEventCatalog::SYSTEM_WEBHOOK_TEST);

    postJson('/api/v1/webhooks/deliveries/'.$deliveryId.'/retry')
        ->assertOk()
        ->assertJsonPath('data.status', WebhookDelivery::STATUS_DELIVERED)
        ->assertJsonPath('data.response_status', 200);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        return $request->hasHeader('X-Port101-Event')
            && $request->hasHeader('X-Port101-Event-Id')
            && $request->hasHeader('X-Port101-Timestamp')
            && $request->hasHeader('X-Port101-Signature')
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

    postJson('/api/v1/webhooks/deliveries/'.$delivered->id.'/retry')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only failed or dead deliveries can be retried.');
});
