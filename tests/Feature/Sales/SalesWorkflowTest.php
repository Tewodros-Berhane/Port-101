<?php

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Core\Settings\SettingsService;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Sales\Events\SalesOrderConfirmed;
use App\Modules\Sales\Events\SalesOrderReadyForInvoice;
use App\Modules\Sales\Models\SalesLead;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function assignSalesRole(User $user, Company $company, array $permissionSlugs, string $scope = User::DATA_SCOPE_COMPANY): void
{
    $role = Role::create([
        'name' => 'Sales Role '.Str::upper(Str::random(4)),
        'slug' => 'sales-role-'.Str::lower(Str::random(8)),
        'description' => 'Sales workflow test role',
        'data_scope' => $scope,
        'company_id' => null,
    ]);

    $permissionIds = collect($permissionSlugs)
        ->map(function (string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'sales']
            )->id;
        })
        ->all();

    $role->permissions()->sync($permissionIds);

    $company->users()->syncWithoutDetaching([
        $user->id => [
            'role_id' => $role->id,
            'is_owner' => false,
        ],
    ]);

    $user->forceFill([
        'current_company_id' => $company->id,
    ])->save();
}

/**
 * @return array{0: Partner, 1: Product}
 */
function createSalesPartnerProduct(
    User $actor,
    Company $company,
    string $productType = 'stock'
): array {
    $partner = Partner::create([
        'company_id' => $company->id,
        'name' => 'Acme Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Widget '.Str::upper(Str::random(4)),
        'type' => $productType,
        'is_active' => true,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);

    return [$partner, $product];
}

test('sales workflow supports lead to quote to order confirmation flow', function () {
    [$salesUser, $company] = makeActiveCompanyMember();

    assignSalesRole($salesUser, $company, [
        'sales.leads.view',
        'sales.leads.manage',
        'sales.quotes.view',
        'sales.quotes.manage',
        'sales.orders.view',
        'sales.orders.manage',
    ], User::DATA_SCOPE_COMPANY);

    [$partner, $product] = createSalesPartnerProduct($salesUser, $company);

    actingAs($salesUser)
        ->post(route('company.sales.leads.store'), [
            'partner_id' => $partner->id,
            'title' => 'Q1 Expansion Opportunity',
            'stage' => 'new',
            'estimated_value' => 1200,
            'expected_close_date' => now()->addDays(14)->toDateString(),
            'notes' => 'Warm inbound lead',
        ])
        ->assertRedirect();

    $lead = SalesLead::query()->first();

    expect($lead)->not->toBeNull();

    actingAs($salesUser)
        ->post(route('company.sales.quotes.store'), [
            'lead_id' => $lead?->id,
            'partner_id' => $partner->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'lines' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Widget line',
                    'quantity' => 2,
                    'unit_price' => 250,
                    'discount_percent' => 0,
                    'tax_rate' => 10,
                ],
            ],
        ])
        ->assertRedirect();

    $quote = SalesQuote::query()->first();

    expect($quote)->not->toBeNull();
    expect($quote?->status)->toBe(SalesQuote::STATUS_DRAFT);

    actingAs($salesUser)
        ->post(route('company.sales.quotes.send', $quote))
        ->assertRedirect();

    actingAs($salesUser)
        ->post(route('company.sales.quotes.confirm', $quote))
        ->assertRedirect();

    $order = SalesOrder::query()->first();

    expect($order)->not->toBeNull();
    expect($order?->status)->toBe(SalesOrder::STATUS_DRAFT);

    Event::fake([
        SalesOrderConfirmed::class,
        SalesOrderReadyForInvoice::class,
    ]);

    actingAs($salesUser)
        ->post(route('company.sales.orders.confirm', $order))
        ->assertRedirect();

    Event::assertDispatched(SalesOrderConfirmed::class);
    Event::assertDispatched(SalesOrderReadyForInvoice::class);

    expect($order?->fresh()->status)->toBe(SalesOrder::STATUS_CONFIRMED);
    expect($quote?->fresh()->status)->toBe(SalesQuote::STATUS_CONFIRMED);
});

test('confirmed service sales orders auto provision a project workspace', function () {
    [$salesUser, $company] = makeActiveCompanyMember();

    assignSalesRole($salesUser, $company, [
        'sales.leads.view',
        'sales.leads.manage',
        'sales.quotes.view',
        'sales.quotes.manage',
        'sales.orders.view',
        'sales.orders.manage',
    ], User::DATA_SCOPE_COMPANY);

    Currency::create([
        'company_id' => $company->id,
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $salesUser->id,
        'updated_by' => $salesUser->id,
    ]);

    [$partner, $product] = createSalesPartnerProduct($salesUser, $company, 'service');

    actingAs($salesUser)
        ->post(route('company.sales.quotes.store'), [
            'lead_id' => null,
            'partner_id' => $partner->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'lines' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Implementation workshop',
                    'quantity' => 12,
                    'unit_price' => 150,
                    'discount_percent' => 0,
                    'tax_rate' => 0,
                ],
            ],
        ])
        ->assertRedirect();

    $quote = SalesQuote::query()->firstOrFail();

    actingAs($salesUser)
        ->post(route('company.sales.quotes.confirm', $quote))
        ->assertRedirect();

    $order = SalesOrder::query()->firstOrFail();

    actingAs($salesUser)
        ->post(route('company.sales.orders.confirm', $order))
        ->assertRedirect();

    $project = Project::query()
        ->where('sales_order_id', $order->id)
        ->first();

    expect($project)->not->toBeNull();
    expect($project?->status)->toBe(Project::STATUS_ACTIVE);
    expect($project?->customer_id)->toBe($partner->id);
    expect($project?->billing_type)->toBe(Project::BILLING_TYPE_TIME_AND_MATERIAL);
    expect((float) $project?->budget_amount)->toBe(1800.0);

    $task = ProjectTask::query()
        ->where('project_id', $project?->id)
        ->first();

    expect($task)->not->toBeNull();
    expect($task?->title)->toBe($product->name);
    expect((float) $task?->estimated_hours)->toBe(12.0);
});

test('sales quote and order approvals are enforced by company approval settings', function () {
    [$salesUser, $company] = makeActiveCompanyMember();
    $manager = User::factory()->create();

    assignSalesRole($salesUser, $company, [
        'sales.leads.view',
        'sales.leads.manage',
        'sales.quotes.view',
        'sales.quotes.manage',
        'sales.orders.view',
        'sales.orders.manage',
    ], User::DATA_SCOPE_COMPANY);

    assignSalesRole($manager, $company, [
        'sales.quotes.view',
        'sales.quotes.approve',
        'sales.orders.view',
        'sales.orders.approve',
    ], User::DATA_SCOPE_COMPANY);

    [$partner, $product] = createSalesPartnerProduct($salesUser, $company);

    /** @var SettingsService $settings */
    $settings = app(SettingsService::class);
    $settings->set('company.approvals.enabled', true, $company->id, null, $salesUser->id);
    $settings->set('company.approvals.policy', 'amount_based', $company->id, null, $salesUser->id);
    $settings->set('company.approvals.threshold_amount', 100, $company->id, null, $salesUser->id);

    actingAs($salesUser)
        ->post(route('company.sales.quotes.store'), [
            'lead_id' => null,
            'partner_id' => $partner->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'lines' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Large quote line',
                    'quantity' => 1,
                    'unit_price' => 500,
                    'discount_percent' => 0,
                    'tax_rate' => 0,
                ],
            ],
        ])
        ->assertRedirect();

    $quote = SalesQuote::query()->first();

    expect($quote?->requires_approval)->toBeTrue();

    actingAs($salesUser)
        ->post(route('company.sales.quotes.confirm', $quote))
        ->assertSessionHas('error');

    actingAs($manager)
        ->post(route('company.sales.quotes.approve', $quote))
        ->assertRedirect();

    expect($quote?->fresh()->status)->toBe(SalesQuote::STATUS_APPROVED);

    actingAs($salesUser)
        ->post(route('company.sales.quotes.confirm', $quote))
        ->assertRedirect();

    $order = SalesOrder::query()->first();

    expect($order?->requires_approval)->toBeTrue();

    actingAs($salesUser)
        ->post(route('company.sales.orders.confirm', $order))
        ->assertSessionHas('error');

    actingAs($manager)
        ->post(route('company.sales.orders.approve', $order))
        ->assertRedirect();

    Event::fake([
        SalesOrderConfirmed::class,
        SalesOrderReadyForInvoice::class,
    ]);

    actingAs($salesUser)
        ->post(route('company.sales.orders.confirm', $order))
        ->assertRedirect();

    Event::assertDispatched(SalesOrderConfirmed::class);
    Event::assertDispatched(SalesOrderReadyForInvoice::class);

    expect($order?->fresh()->status)->toBe(SalesOrder::STATUS_CONFIRMED);
});
