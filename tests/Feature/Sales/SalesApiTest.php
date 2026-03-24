<?php

use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Core\RBAC\Models\Role;
use App\Core\Settings\SettingsService;
use App\Models\User;
use App\Modules\Integrations\Models\ApiIdempotencyKey;
use App\Modules\Projects\Models\Project;
use App\Modules\Sales\Models\SalesLead;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

function assignSalesApiRole(User $user, string $companyId, string $roleSlug): void
{
    $role = Role::query()
        ->where('slug', $roleSlug)
        ->whereNull('company_id')
        ->firstOrFail();

    $user->memberships()
        ->where('company_id', $companyId)
        ->update([
            'role_id' => $role->id,
            'is_owner' => false,
        ]);

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

function createSalesApiCurrency(string $companyId, string $userId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'S'.Str::upper(Str::random(2)),
        'name' => 'Sales API Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

/**
 * @return array{0: Partner, 1: Product}
 */
function createSalesApiPartnerProduct(string $companyId, string $userId, string $productType = 'stock'): array
{
    $partner = Partner::create([
        'company_id' => $companyId,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Sales API Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);

    $product = Product::create([
        'company_id' => $companyId,
        'name' => 'Service Line '.Str::upper(Str::random(4)),
        'sku' => 'SKU-'.Str::upper(Str::random(4)),
        'type' => $productType,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);

    return [$partner, $product];
}

test('api v1 sales endpoints are company scoped and support lead quote and order workflows', function () {
    $this->seed(CoreRolesSeeder::class);

    [$salesUser, $company] = makeActiveCompanyMember();
    [$otherUser, $otherCompany] = makeActiveCompanyMember();

    assignSalesApiRole($salesUser, $company->id, 'sales_user');
    assignSalesApiRole($otherUser, $otherCompany->id, 'sales_user');

    createSalesApiCurrency($company->id, $salesUser->id);
    [$partner, $product] = createSalesApiPartnerProduct($company->id, $salesUser->id, 'service');

    $otherLead = SalesLead::create([
        'company_id' => $otherCompany->id,
        'title' => 'Other Company Lead',
        'stage' => 'new',
        'estimated_value' => 500,
        'created_by' => $otherUser->id,
        'updated_by' => $otherUser->id,
    ]);

    Sanctum::actingAs($salesUser);

    $leadResponse = postJson('/api/v1/sales/leads', [
        'partner_id' => $partner->id,
        'title' => 'API Expansion Opportunity',
        'stage' => 'new',
        'estimated_value' => 900,
        'expected_close_date' => now()->addDays(10)->toDateString(),
        'notes' => 'Inbound request from the API.',
    ], apiIdempotencyHeaders())
        ->assertCreated()
        ->assertJsonPath('data.title', 'API Expansion Opportunity')
        ->assertJsonPath('data.stage', 'new');

    $leadId = (string) $leadResponse->json('data.id');

    putJson("/api/v1/sales/leads/{$leadId}", [
        'partner_id' => $partner->id,
        'title' => 'API Expansion Opportunity Qualified',
        'stage' => 'qualified',
        'estimated_value' => 1200,
        'expected_close_date' => now()->addDays(14)->toDateString(),
        'notes' => 'Qualified after discovery.',
    ])
        ->assertOk()
        ->assertJsonPath('data.stage', 'qualified')
        ->assertJsonPath('data.estimated_value', 1200);

    getJson('/api/v1/sales/leads?stage=qualified&sort=title&direction=asc&per_page=500')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $leadId)
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonPath('meta.sort', 'title')
        ->assertJsonPath('meta.direction', 'asc')
        ->assertJsonPath('meta.filters.stage', 'qualified');

    getJson('/api/v1/sales/leads/'.$otherLead->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');

    $quoteResponse = postJson('/api/v1/sales/quotes', [
        'lead_id' => $leadId,
        'partner_id' => $partner->id,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(14)->toDateString(),
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'Implementation workshop',
                'quantity' => 2,
                'unit_price' => 150,
                'discount_percent' => 0,
                'tax_rate' => 0,
            ],
        ],
    ], apiIdempotencyHeaders())
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.lines.0.product_id', $product->id);

    $quoteId = (string) $quoteResponse->json('data.id');

    putJson("/api/v1/sales/quotes/{$quoteId}", [
        'lead_id' => $leadId,
        'partner_id' => $partner->id,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(21)->toDateString(),
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'Implementation workshop updated',
                'quantity' => 3,
                'unit_price' => 150,
                'discount_percent' => 0,
                'tax_rate' => 0,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.lines.0.quantity', 3)
        ->assertJsonPath('data.grand_total', 450);

    postJson("/api/v1/sales/quotes/{$quoteId}/send")
        ->assertOk()
        ->assertJsonPath('data.status', 'sent');

    $confirmQuoteResponse = postJson("/api/v1/sales/quotes/{$quoteId}/confirm", [], apiIdempotencyHeaders())
        ->assertOk()
        ->assertJsonPath('data.quote.status', 'confirmed')
        ->assertJsonPath('data.order.status', 'draft');

    $orderId = (string) $confirmQuoteResponse->json('data.order.id');

    getJson('/api/v1/sales/orders?status=draft&sort=order_number&direction=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $orderId)
        ->assertJsonPath('meta.sort', 'order_number')
        ->assertJsonPath('meta.direction', 'asc')
        ->assertJsonPath('meta.filters.status', 'draft');

    putJson("/api/v1/sales/orders/{$orderId}", [
        'partner_id' => $partner->id,
        'order_date' => now()->toDateString(),
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'Implementation workshop final',
                'quantity' => 4,
                'unit_price' => 150,
                'discount_percent' => 0,
                'tax_rate' => 0,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.lines.0.quantity', 4)
        ->assertJsonPath('data.grand_total', 600);

    postJson("/api/v1/sales/orders/{$orderId}/confirm", [], apiIdempotencyHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $project = Project::query()
        ->where('sales_order_id', $orderId)
        ->first();

    expect($project)->not->toBeNull();
    expect($project?->status)->toBe(Project::STATUS_ACTIVE);
    expect($project?->customer_id)->toBe($partner->id);

    $deletableLeadId = (string) postJson('/api/v1/sales/leads', [
        'partner_id' => $partner->id,
        'title' => 'Delete Me Lead',
        'stage' => 'new',
        'estimated_value' => 50,
        'expected_close_date' => now()->addDays(2)->toDateString(),
        'notes' => 'Disposable lead.',
    ], apiIdempotencyHeaders())->json('data.id');

    deleteJson('/api/v1/sales/leads/'.$deletableLeadId)
        ->assertNoContent();

    $deletableQuoteId = (string) postJson('/api/v1/sales/quotes', [
        'lead_id' => null,
        'partner_id' => $partner->id,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(7)->toDateString(),
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'Disposable quote',
                'quantity' => 1,
                'unit_price' => 25,
                'discount_percent' => 0,
                'tax_rate' => 0,
            ],
        ],
    ], apiIdempotencyHeaders())->json('data.id');

    deleteJson('/api/v1/sales/quotes/'.$deletableQuoteId)
        ->assertNoContent();

    $deletableOrderId = (string) postJson('/api/v1/sales/orders', [
        'quote_id' => null,
        'partner_id' => $partner->id,
        'order_date' => now()->toDateString(),
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'Disposable order',
                'quantity' => 1,
                'unit_price' => 35,
                'discount_percent' => 0,
                'tax_rate' => 0,
            ],
        ],
    ], apiIdempotencyHeaders())->json('data.id');

    deleteJson('/api/v1/sales/orders/'.$deletableOrderId)
        ->assertNoContent();
});

test('api v1 sales approval endpoints support quote and order approval gates', function () {
    $this->seed(CoreRolesSeeder::class);

    [$salesUser, $company] = makeActiveCompanyMember();
    $approver = User::factory()->create();

    $company->users()->attach($approver->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignSalesApiRole($salesUser, $company->id, 'sales_user');
    assignSalesApiRole($approver, $company->id, 'approver');

    [$partner, $product] = createSalesApiPartnerProduct($company->id, $salesUser->id, 'stock');

    /** @var SettingsService $settings */
    $settings = app(SettingsService::class);
    $settings->set('company.approvals.enabled', true, $company->id, null, $salesUser->id);
    $settings->set('company.approvals.policy', 'amount_based', $company->id, null, $salesUser->id);
    $settings->set('company.approvals.threshold_amount', 100, $company->id, null, $salesUser->id);

    Sanctum::actingAs($salesUser);

    $quoteId = (string) postJson('/api/v1/sales/quotes', [
        'lead_id' => null,
        'partner_id' => $partner->id,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(14)->toDateString(),
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'Approval-required quote',
                'quantity' => 1,
                'unit_price' => 500,
                'discount_percent' => 0,
                'tax_rate' => 0,
            ],
        ],
    ], apiIdempotencyHeaders())
        ->assertCreated()
        ->assertJsonPath('data.requires_approval', true)
        ->json('data.id');

    postJson("/api/v1/sales/quotes/{$quoteId}/send")
        ->assertOk()
        ->assertJsonPath('data.status', 'sent');

    postJson("/api/v1/sales/quotes/{$quoteId}/confirm", [], apiIdempotencyHeaders())
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['quote']]);

    Sanctum::actingAs($approver);

    postJson("/api/v1/sales/quotes/{$quoteId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    Sanctum::actingAs($salesUser);

    $orderId = (string) postJson("/api/v1/sales/quotes/{$quoteId}/confirm", [], apiIdempotencyHeaders())
        ->assertOk()
        ->assertJsonPath('data.order.requires_approval', true)
        ->json('data.order.id');

    postJson("/api/v1/sales/orders/{$orderId}/confirm", [], apiIdempotencyHeaders())
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['order']]);

    Sanctum::actingAs($approver);

    postJson("/api/v1/sales/orders/{$orderId}/approve")
        ->assertOk()
        ->assertJsonPath('data.approved_by', $approver->id);

    Sanctum::actingAs($salesUser);

    postJson("/api/v1/sales/orders/{$orderId}/confirm", [], apiIdempotencyHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');
});

test('api v1 sales idempotency replays duplicate create requests and rejects payload conflicts', function () {
    $this->seed(CoreRolesSeeder::class);

    [$salesUser, $company] = makeActiveCompanyMember();

    assignSalesApiRole($salesUser, $company->id, 'sales_user');
    createSalesApiCurrency($company->id, $salesUser->id);
    [$partner] = createSalesApiPartnerProduct($company->id, $salesUser->id, 'service');

    Sanctum::actingAs($salesUser);

    $payload = [
        'partner_id' => $partner->id,
        'title' => 'Idempotent API Lead',
        'stage' => 'new',
        'estimated_value' => 750,
        'expected_close_date' => now()->addDays(5)->toDateString(),
        'notes' => 'Replay-safe create request.',
    ];

    postJson('/api/v1/sales/leads', $payload)
        ->assertBadRequest()
        ->assertJsonPath('message', 'Idempotency-Key header is required for this endpoint.');

    $key = 'sales-lead-create';

    $firstResponse = postJson('/api/v1/sales/leads', $payload, apiIdempotencyHeaders($key))
        ->assertCreated()
        ->assertHeader('Idempotency-Key', $key)
        ->assertHeader('X-Port101-Idempotency-Replayed', 'false');

    $leadId = (string) $firstResponse->json('data.id');

    postJson('/api/v1/sales/leads', $payload, apiIdempotencyHeaders($key))
        ->assertCreated()
        ->assertHeader('Idempotency-Key', $key)
        ->assertHeader('X-Port101-Idempotency-Replayed', 'true')
        ->assertJsonPath('data.id', $leadId);

    expect(SalesLead::query()->where('company_id', $company->id)->count())->toBe(1);

    postJson('/api/v1/sales/leads', [
        ...$payload,
        'title' => 'Conflicting payload',
    ], apiIdempotencyHeaders($key))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Idempotency-Key cannot be reused with a different request payload.');
});

test('api v1 sales idempotency blocks requests while the same key is in flight', function () {
    $this->seed(CoreRolesSeeder::class);

    [$salesUser, $company] = makeActiveCompanyMember();

    assignSalesApiRole($salesUser, $company->id, 'sales_user');
    createSalesApiCurrency($company->id, $salesUser->id);
    [$partner] = createSalesApiPartnerProduct($company->id, $salesUser->id, 'service');

    Sanctum::actingAs($salesUser);

    ApiIdempotencyKey::create([
        'company_id' => $company->id,
        'user_id' => $salesUser->id,
        'key' => 'sales-in-flight',
        'request_fingerprint' => hash('sha256', json_encode([
            'method' => 'POST',
            'path' => 'api/v1/sales/leads',
            'route_parameters' => [],
            'payload' => [
                'estimated_value' => 400,
                'expected_close_date' => now()->addDays(3)->toDateString(),
                'notes' => 'In-flight replay.',
                'partner_id' => $partner->id,
                'stage' => 'new',
                'title' => 'In-flight lead',
            ],
        ], JSON_THROW_ON_ERROR)),
        'expires_at' => now()->addHour(),
    ]);

    postJson('/api/v1/sales/leads', [
        'partner_id' => $partner->id,
        'title' => 'In-flight lead',
        'stage' => 'new',
        'estimated_value' => 400,
        'expected_close_date' => now()->addDays(3)->toDateString(),
        'notes' => 'In-flight replay.',
    ], apiIdempotencyHeaders('sales-in-flight'))
        ->assertConflict()
        ->assertJsonPath('message', 'A request with this Idempotency-Key is already being processed.');
});
