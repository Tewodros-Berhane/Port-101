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
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function assignPurchasingApiRole(User $user, string $companyId, array $permissionSlugs): void
{
    $role = Role::create([
        'name' => 'Purchasing API Role '.Str::upper(Str::random(4)),
        'slug' => 'purchasing-api-role-'.Str::lower(Str::random(8)),
        'description' => 'Purchasing API test role',
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

function createPurchasingApiVendor(string $companyId, string $userId, string $name): Partner
{
    return Partner::create([
        'company_id' => $companyId,
        'name' => $name,
        'type' => 'vendor',
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function createPurchasingApiProduct(string $companyId, string $userId, string $name): Product
{
    return Product::create([
        'company_id' => $companyId,
        'name' => $name,
        'sku' => 'SKU-'.Str::upper(Str::random(6)),
        'type' => 'stock',
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

test('api v1 purchasing endpoints are company scoped and support rfq to po to receipt workflow', function () {
    [$buyer, $company] = makeActiveCompanyMember();
    [$otherBuyer, $otherCompany] = makeActiveCompanyMember();

    assignPurchasingApiRole($buyer, $company->id, [
        'purchasing.rfq.view',
        'purchasing.rfq.manage',
        'purchasing.po.view',
        'purchasing.po.manage',
        'accounting.invoices.view',
    ]);
    assignPurchasingApiRole($otherBuyer, $otherCompany->id, [
        'purchasing.rfq.view',
        'purchasing.rfq.manage',
        'purchasing.po.view',
        'purchasing.po.manage',
    ]);

    $vendor = createPurchasingApiVendor($company->id, $buyer->id, 'Purchasing API Vendor');
    $product = createPurchasingApiProduct($company->id, $buyer->id, 'Purchasing API Product');

    $otherVendor = createPurchasingApiVendor($otherCompany->id, $otherBuyer->id, 'Other Purchasing Vendor');
    $otherProduct = createPurchasingApiProduct($otherCompany->id, $otherBuyer->id, 'Other Purchasing Product');

    $outOfScopeRfq = PurchaseRfq::create([
        'company_id' => $otherCompany->id,
        'partner_id' => $otherVendor->id,
        'rfq_number' => 'RFQ-OTHER-001',
        'status' => PurchaseRfq::STATUS_DRAFT,
        'rfq_date' => now()->toDateString(),
        'valid_until' => now()->addDays(10)->toDateString(),
        'subtotal' => 50,
        'tax_total' => 0,
        'grand_total' => 50,
        'created_by' => $otherBuyer->id,
        'updated_by' => $otherBuyer->id,
    ]);

    $outOfScopeRfq->lines()->create([
        'company_id' => $otherCompany->id,
        'product_id' => $otherProduct->id,
        'description' => 'Out of scope line',
        'quantity' => 1,
        'unit_cost' => 50,
        'tax_rate' => 0,
        'line_subtotal' => 50,
        'line_total' => 50,
        'created_by' => $otherBuyer->id,
        'updated_by' => $otherBuyer->id,
    ]);

    Sanctum::actingAs($buyer);

    $rfqResponse = postJson('/api/v1/purchasing/rfqs', [
        'external_reference' => 'ERP-RFQ-001',
        'partner_id' => $vendor->id,
        'rfq_date' => now()->toDateString(),
        'valid_until' => now()->addDays(10)->toDateString(),
        'notes' => 'API RFQ',
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'API RFQ line',
                'quantity' => 5,
                'unit_cost' => 20,
                'tax_rate' => 10,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.external_reference', 'ERP-RFQ-001')
        ->assertJsonPath('data.status', PurchaseRfq::STATUS_DRAFT)
        ->assertJsonPath('data.partner_id', $vendor->id);

    $rfqId = (string) $rfqResponse->json('data.id');

    getJson('/api/v1/purchasing/rfqs?external_reference=ERP-RFQ-001&status=draft&sort=rfq_number&direction=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $rfqId)
        ->assertJsonPath('meta.filters.external_reference', 'ERP-RFQ-001')
        ->assertJsonPath('meta.filters.status', PurchaseRfq::STATUS_DRAFT)
        ->assertJsonPath('meta.sort', 'rfq_number');

    getJson('/api/v1/purchasing/rfqs/'.$outOfScopeRfq->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');

    patchJson('/api/v1/purchasing/rfqs/'.$rfqId, [
        'partner_id' => $vendor->id,
        'rfq_date' => now()->toDateString(),
        'valid_until' => now()->addDays(12)->toDateString(),
        'notes' => 'API RFQ updated',
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'API RFQ line updated',
                'quantity' => 6,
                'unit_cost' => 22,
                'tax_rate' => 10,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.notes', 'API RFQ updated')
        ->assertJsonPath('data.grand_total', 145.2);

    postJson('/api/v1/purchasing/rfqs/'.$rfqId.'/send')
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseRfq::STATUS_SENT);

    postJson('/api/v1/purchasing/rfqs/'.$rfqId.'/respond')
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseRfq::STATUS_VENDOR_RESPONDED);

    $selectResponse = postJson('/api/v1/purchasing/rfqs/'.$rfqId.'/select')
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseRfq::STATUS_SELECTED)
        ->assertJsonPath('data.order_status', PurchaseOrder::STATUS_DRAFT);

    $orderId = (string) $selectResponse->json('data.order_id');

    getJson('/api/v1/purchasing/orders?status=draft&requires_approval=false&sort=order_number&direction=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $orderId)
        ->assertJsonPath('meta.filters.status', PurchaseOrder::STATUS_DRAFT)
        ->assertJsonPath('meta.filters.requires_approval', false);

    patchJson('/api/v1/purchasing/orders/'.$orderId, [
        'external_reference' => 'ERP-PO-001',
        'partner_id' => $vendor->id,
        'order_date' => now()->toDateString(),
        'notes' => 'API PO updated',
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'API PO line updated',
                'quantity' => 6,
                'unit_cost' => 22,
                'tax_rate' => 10,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.external_reference', 'ERP-PO-001')
        ->assertJsonPath('data.notes', 'API PO updated');

    getJson('/api/v1/purchasing/orders?external_reference=ERP-PO-001&status=draft&requires_approval=false&sort=order_number&direction=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $orderId)
        ->assertJsonPath('meta.filters.external_reference', 'ERP-PO-001');

    $confirmResponse = postJson('/api/v1/purchasing/orders/'.$orderId.'/confirm')
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_ORDERED);

    $lineId = (string) $confirmResponse->json('data.lines.0.id');

    postJson('/api/v1/purchasing/orders/'.$orderId.'/receive', [
        'lines' => [
            [
                'line_id' => $lineId,
                'quantity' => 2,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
        ->assertJsonPath('data.lines.0.received_quantity', 2);

    postJson('/api/v1/purchasing/orders/'.$orderId.'/receive', [])
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_BILLED)
        ->assertJsonPath('data.vendor_bills_count', 1);

    getJson('/api/v1/purchasing/orders/'.$orderId)
        ->assertOk()
        ->assertJsonPath('data.vendor_bills.0.status', AccountingInvoice::STATUS_DRAFT)
        ->assertJsonPath('data.vendor_bills.0.grand_total', 145.2)
        ->assertJsonPath('data.vendor_bills.0.balance_due', 145.2);

    $draftDeleteResponse = postJson('/api/v1/purchasing/rfqs', [
        'partner_id' => $vendor->id,
        'rfq_date' => now()->toDateString(),
        'valid_until' => now()->addDays(5)->toDateString(),
        'notes' => 'Delete me',
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'Delete line',
                'quantity' => 1,
                'unit_cost' => 10,
                'tax_rate' => 0,
            ],
        ],
    ])->assertCreated();

    deleteJson('/api/v1/purchasing/rfqs/'.(string) $draftDeleteResponse->json('data.id'))
        ->assertNoContent();
});

test('api v1 purchasing approval gate and receipt-line validation are enforced', function () {
    [$buyer, $company] = makeActiveCompanyMember();
    $manager = User::factory()->create();

    assignPurchasingApiRole($buyer, $company->id, [
        'purchasing.rfq.view',
        'purchasing.rfq.manage',
        'purchasing.po.view',
        'purchasing.po.manage',
    ]);
    assignPurchasingApiRole($manager, $company->id, [
        'purchasing.po.view',
        'purchasing.po.approve',
    ]);

    $vendor = createPurchasingApiVendor($company->id, $buyer->id, 'Purchasing Approval Vendor');
    $product = createPurchasingApiProduct($company->id, $buyer->id, 'Purchasing Approval Product');

    /** @var SettingsService $settings */
    $settings = app(SettingsService::class);
    $settings->set('company.approvals.enabled', true, $company->id, null, $buyer->id);
    $settings->set('company.approvals.policy', 'amount_based', $company->id, null, $buyer->id);
    $settings->set('company.approvals.threshold_amount', 100, $company->id, null, $buyer->id);

    Sanctum::actingAs($buyer);

    $rfqResponse = postJson('/api/v1/purchasing/rfqs', [
        'partner_id' => $vendor->id,
        'rfq_date' => now()->toDateString(),
        'valid_until' => now()->addDays(10)->toDateString(),
        'notes' => 'Approval API RFQ',
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'High value API RFQ line',
                'quantity' => 1,
                'unit_cost' => 500,
                'tax_rate' => 0,
            ],
        ],
    ])->assertCreated();

    $rfqId = (string) $rfqResponse->json('data.id');

    postJson('/api/v1/purchasing/rfqs/'.$rfqId.'/send')->assertOk();

    $selectResponse = postJson('/api/v1/purchasing/rfqs/'.$rfqId.'/select')
        ->assertOk()
        ->assertJsonPath('data.order_status', PurchaseOrder::STATUS_DRAFT);

    $orderId = (string) $selectResponse->json('data.order_id');

    postJson('/api/v1/purchasing/orders/'.$orderId.'/confirm')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This purchase order requires approval before placement.');

    Sanctum::actingAs($manager);

    postJson('/api/v1/purchasing/orders/'.$orderId.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_APPROVED);

    Sanctum::actingAs($buyer);

    $confirmedOrder = postJson('/api/v1/purchasing/orders/'.$orderId.'/confirm')
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_ORDERED);

    $lineId = (string) $confirmedOrder->json('data.lines.0.id');

    $otherDraftOrder = postJson('/api/v1/purchasing/orders', [
        'rfq_id' => null,
        'partner_id' => $vendor->id,
        'order_date' => now()->toDateString(),
        'notes' => 'Other order for validation',
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'Other line',
                'quantity' => 1,
                'unit_cost' => 20,
                'tax_rate' => 0,
            ],
        ],
    ])->assertCreated();

    $otherLineId = (string) $otherDraftOrder->json('data.lines.0.id');

    postJson('/api/v1/purchasing/orders/'.$orderId.'/receive', [
        'lines' => [
            [
                'line_id' => $otherLineId,
                'quantity' => 1,
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonStructure([
            'message',
            'errors' => ['lines.0.line_id'],
        ]);

    postJson('/api/v1/purchasing/orders/'.$orderId.'/receive', [
        'lines' => [
            [
                'line_id' => $lineId,
                'quantity' => 1,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_BILLED);
});
