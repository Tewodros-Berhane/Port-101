<?php

use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Integrations\Models\IntegrationEvent;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookSecretRotation;
use App\Modules\Integrations\WebhookEventCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function assignWebhookWorkspaceRole(User $user, string $companyId, array $permissionSlugs): void
{
    $role = Role::create([
        'name' => 'Webhook Workspace Role '.Str::upper(Str::random(4)),
        'slug' => 'webhook-workspace-role-'.Str::lower(Str::random(8)),
        'description' => 'Webhook workspace test role',
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

function createWebhookWorkspaceEndpoint(string $companyId, string $userId): WebhookEndpoint
{
    return WebhookEndpoint::create([
        'company_id' => $companyId,
        'name' => 'Finance Hub',
        'target_url' => 'https://hooks.example.com/finance',
        'signing_secret' => Str::random(64),
        'api_version' => 'v1',
        'is_active' => true,
        'subscribed_events' => [WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED],
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function createWebhookWorkspaceDelivery(
    string $companyId,
    string $userId,
    WebhookEndpoint $endpoint,
    string $status = WebhookDelivery::STATUS_FAILED,
): WebhookDelivery {
    $event = IntegrationEvent::create([
        'company_id' => $companyId,
        'event_type' => WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED,
        'aggregate_type' => WebhookEndpoint::class,
        'aggregate_id' => $endpoint->id,
        'occurred_at' => now(),
        'payload' => [
            'event_type' => WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED,
            'data' => ['reference' => 'INV-1001'],
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
        'status' => $status,
        'attempt_count' => $status === WebhookDelivery::STATUS_DEAD ? 5 : 1,
        'last_attempt_at' => now(),
        'next_retry_at' => $status === WebhookDelivery::STATUS_FAILED ? now()->addMinute() : null,
        'response_status' => 500,
        'failure_message' => 'Temporary upstream failure',
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

test('company integrations workspace pages render for webhook-enabled users', function () {
    [$manager, $company] = makeActiveCompanyMember();

    assignWebhookWorkspaceRole($manager, $company->id, [
        'integrations.webhooks.view',
        'integrations.webhooks.manage',
    ]);

    $endpoint = createWebhookWorkspaceEndpoint($company->id, $manager->id);
    $delivery = createWebhookWorkspaceDelivery($company->id, $manager->id, $endpoint);

    actingAs($manager)
        ->get(route('company.modules.integrations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('integrations/index')
            ->has('summary')
            ->has('recentEndpoints', 1)
            ->has('deadLetters', 1));

    actingAs($manager)
        ->get(route('company.integrations.webhooks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('integrations/webhooks/index')
            ->has('endpoints.data', 1));

    actingAs($manager)
        ->get(route('company.integrations.webhooks.show', $endpoint))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('integrations/webhooks/show')
            ->where('endpoint.id', $endpoint->id)
            ->has('endpoint.analytics')
            ->has('endpoint.delivery_security_policy')
            ->has('endpoint.recent_secret_rotations')
            ->has('deliveries.data', 1));

    actingAs($manager)
        ->get(route('company.integrations.deliveries.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('integrations/deliveries/index')
            ->has('deliveries.data', 1));

    actingAs($manager)
        ->get(route('company.integrations.deliveries.show', $delivery))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('integrations/deliveries/show')
            ->where('delivery.id', $delivery->id)
            ->has('securityPolicy'));
});

test('company webhook workspace supports create test rotate and retry flows', function () {
    [$manager, $company] = makeActiveCompanyMember();

    assignWebhookWorkspaceRole($manager, $company->id, [
        'integrations.webhooks.view',
        'integrations.webhooks.manage',
    ]);

    actingAs($manager)
        ->post(route('company.integrations.webhooks.store'), [
            'name' => 'Finance Hub',
            'target_url' => 'https://hooks.example.com/finance',
            'is_active' => true,
            'subscribed_events' => [WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED],
        ])
        ->assertRedirect();

    $endpoint = WebhookEndpoint::query()->firstOrFail();
    $originalSecret = $endpoint->signing_secret;

    expect(WebhookSecretRotation::query()->where('webhook_endpoint_id', $endpoint->id)->count())
        ->toBe(1);

    Http::fake([
        'https://hooks.example.com/finance' => Http::sequence()
            ->push('temporary failure', 500)
            ->push(['ok' => true], 200),
    ]);

    actingAs($manager)
        ->post(route('company.integrations.webhooks.test', $endpoint))
        ->assertRedirect(route('company.integrations.webhooks.show', $endpoint));

    $delivery = WebhookDelivery::query()->latest('created_at')->firstOrFail();

    expect($delivery->status)->toBe(WebhookDelivery::STATUS_FAILED);

    actingAs($manager)
        ->post(route('company.integrations.deliveries.retry', $delivery))
        ->assertRedirect(route('company.integrations.deliveries.show', $delivery));

    expect($delivery->fresh()->status)->toBe(WebhookDelivery::STATUS_DELIVERED);

    actingAs($manager)
        ->post(route('company.integrations.webhooks.rotate-secret', $endpoint))
        ->assertRedirect(route('company.integrations.webhooks.show', $endpoint))
        ->assertSessionHas('webhook_signing_secret');

    expect($endpoint->fresh()->signing_secret)->not->toBe($originalSecret);
    expect(WebhookSecretRotation::query()->where('webhook_endpoint_id', $endpoint->id)->count())
        ->toBe(2);
});

test('company webhook viewers can inspect workspace but cannot manage endpoints or retries', function () {
    [$viewer, $company] = makeActiveCompanyMember();

    assignWebhookWorkspaceRole($viewer, $company->id, [
        'integrations.webhooks.view',
    ]);

    $endpoint = createWebhookWorkspaceEndpoint($company->id, $viewer->id);
    $delivery = createWebhookWorkspaceDelivery($company->id, $viewer->id, $endpoint);

    actingAs($viewer)
        ->get(route('company.modules.integrations'))
        ->assertOk();

    actingAs($viewer)
        ->get(route('company.integrations.webhooks.show', $endpoint))
        ->assertOk();

    actingAs($viewer)
        ->get(route('company.integrations.deliveries.show', $delivery))
        ->assertOk();

    actingAs($viewer)
        ->get(route('company.integrations.webhooks.create'))
        ->assertForbidden();

    actingAs($viewer)
        ->post(route('company.integrations.webhooks.test', $endpoint))
        ->assertForbidden();

    actingAs($viewer)
        ->post(route('company.integrations.deliveries.retry', $delivery))
        ->assertForbidden();
});
