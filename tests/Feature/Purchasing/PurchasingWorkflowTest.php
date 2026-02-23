<?php

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Core\Settings\SettingsService;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRfq;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function assignPurchasingWorkflowRole(
    User $user,
    string $companyId,
    array $permissionSlugs
): void {
    $role = Role::create([
        'name' => 'Purchasing Role '.Str::upper(Str::random(4)),
        'slug' => 'purchasing-role-'.Str::lower(Str::random(8)),
        'description' => 'Purchasing workflow test role',
        'data_scope' => User::DATA_SCOPE_COMPANY,
        'company_id' => null,
    ]);

    $permissionIds = collect($permissionSlugs)
        ->map(function (string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'purchasing']
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

test('purchasing workflow supports rfq to po to receipt with vendor bill handoff', function () {
    [$buyer, $company] = makeActiveCompanyMember();

    assignPurchasingWorkflowRole($buyer, $company->id, [
        'purchasing.rfq.view',
        'purchasing.rfq.manage',
        'purchasing.po.view',
        'purchasing.po.manage',
        'accounting.invoices.view',
    ]);

    $vendor = Partner::create([
        'company_id' => $company->id,
        'name' => 'Vendor '.Str::upper(Str::random(4)),
        'type' => 'vendor',
        'is_active' => true,
        'created_by' => $buyer->id,
        'updated_by' => $buyer->id,
    ]);

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Purchase Item '.Str::upper(Str::random(4)),
        'type' => 'stock',
        'is_active' => true,
        'created_by' => $buyer->id,
        'updated_by' => $buyer->id,
    ]);

    actingAs($buyer)
        ->post(route('company.purchasing.rfqs.store'), [
            'partner_id' => $vendor->id,
            'rfq_date' => now()->toDateString(),
            'valid_until' => now()->addDays(10)->toDateString(),
            'notes' => 'Initial vendor quote request',
            'lines' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Raw material lot',
                    'quantity' => 5,
                    'unit_cost' => 20,
                    'tax_rate' => 10,
                ],
            ],
        ])
        ->assertRedirect();

    $rfq = PurchaseRfq::query()->first();

    expect($rfq)->not->toBeNull();
    expect($rfq?->status)->toBe(PurchaseRfq::STATUS_DRAFT);

    actingAs($buyer)
        ->post(route('company.purchasing.rfqs.send', $rfq))
        ->assertRedirect();

    actingAs($buyer)
        ->post(route('company.purchasing.rfqs.respond', $rfq))
        ->assertRedirect();

    actingAs($buyer)
        ->post(route('company.purchasing.rfqs.select', $rfq))
        ->assertRedirect();

    $order = PurchaseOrder::query()->first();

    expect($order)->not->toBeNull();
    expect($order?->status)->toBe(PurchaseOrder::STATUS_DRAFT);
    expect($order?->requires_approval)->toBeFalse();

    actingAs($buyer)
        ->post(route('company.purchasing.orders.place', $order))
        ->assertRedirect();

    expect($order?->fresh()->status)->toBe(PurchaseOrder::STATUS_ORDERED);

    actingAs($buyer)
        ->post(route('company.purchasing.orders.receive', $order), [])
        ->assertRedirect();

    $order = $order?->fresh();

    expect($order?->status)->toBe(PurchaseOrder::STATUS_BILLED);

    $vendorBill = AccountingInvoice::query()
        ->where('company_id', $company->id)
        ->where('purchase_order_id', $order?->id)
        ->where('document_type', AccountingInvoice::TYPE_VENDOR_BILL)
        ->first();

    expect($vendorBill)->not->toBeNull();
    expect($vendorBill?->status)->toBe(AccountingInvoice::STATUS_DRAFT);
    expect((float) $vendorBill?->grand_total)->toBe(110.0);
    expect((float) $vendorBill?->balance_due)->toBe(110.0);
    expect((float) $vendorBill?->lines()->first()?->quantity)->toBe(5.0);
});

test('purchase order approvals are enforced by company approval settings', function () {
    [$buyer, $company] = makeActiveCompanyMember();
    $manager = User::factory()->create();

    assignPurchasingWorkflowRole($buyer, $company->id, [
        'purchasing.rfq.view',
        'purchasing.rfq.manage',
        'purchasing.po.view',
        'purchasing.po.manage',
    ]);

    assignPurchasingWorkflowRole($manager, $company->id, [
        'purchasing.po.view',
        'purchasing.po.approve',
    ]);

    $vendor = Partner::create([
        'company_id' => $company->id,
        'name' => 'Approval Vendor '.Str::upper(Str::random(4)),
        'type' => 'vendor',
        'is_active' => true,
        'created_by' => $buyer->id,
        'updated_by' => $buyer->id,
    ]);

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Approval Item '.Str::upper(Str::random(4)),
        'type' => 'stock',
        'is_active' => true,
        'created_by' => $buyer->id,
        'updated_by' => $buyer->id,
    ]);

    /** @var SettingsService $settings */
    $settings = app(SettingsService::class);
    $settings->set('company.approvals.enabled', true, $company->id, null, $buyer->id);
    $settings->set('company.approvals.policy', 'amount_based', $company->id, null, $buyer->id);
    $settings->set('company.approvals.threshold_amount', 100, $company->id, null, $buyer->id);

    actingAs($buyer)
        ->post(route('company.purchasing.rfqs.store'), [
            'partner_id' => $vendor->id,
            'rfq_date' => now()->toDateString(),
            'valid_until' => now()->addDays(10)->toDateString(),
            'notes' => 'Approval required RFQ',
            'lines' => [
                [
                    'product_id' => $product->id,
                    'description' => 'High value line',
                    'quantity' => 1,
                    'unit_cost' => 500,
                    'tax_rate' => 0,
                ],
            ],
        ])
        ->assertRedirect();

    $rfq = PurchaseRfq::query()->first();

    actingAs($buyer)
        ->post(route('company.purchasing.rfqs.send', $rfq))
        ->assertRedirect();

    actingAs($buyer)
        ->post(route('company.purchasing.rfqs.select', $rfq))
        ->assertRedirect();

    $order = PurchaseOrder::query()->first();

    expect($order)->not->toBeNull();
    expect($order?->requires_approval)->toBeTrue();

    actingAs($buyer)
        ->post(route('company.purchasing.orders.place', $order))
        ->assertSessionHas('error');

    actingAs($manager)
        ->post(route('company.purchasing.orders.approve', $order))
        ->assertRedirect();

    expect($order?->fresh()->status)->toBe(PurchaseOrder::STATUS_APPROVED);

    actingAs($buyer)
        ->post(route('company.purchasing.orders.place', $order))
        ->assertRedirect();

    expect($order?->fresh()->status)->toBe(PurchaseOrder::STATUS_ORDERED);
});
