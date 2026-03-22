<?php

use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingPayment;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function assignAccountingApiRole(User $user, string $companyId, array $permissionSlugs): void
{
    $role = Role::create([
        'name' => 'Accounting API Role '.Str::upper(Str::random(4)),
        'slug' => 'accounting-api-role-'.Str::lower(Str::random(8)),
        'description' => 'Accounting API test role',
        'data_scope' => User::DATA_SCOPE_COMPANY,
        'company_id' => null,
    ]);

    $permissionIds = collect($permissionSlugs)
        ->map(function (string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'accounting']
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

function createAccountingApiPartner(string $companyId, string $userId, string $name, string $type): Partner
{
    return Partner::create([
        'company_id' => $companyId,
        'name' => $name,
        'type' => $type,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

test('api v1 accounting endpoints are company scoped and support invoice and payment workflows', function () {
    [$financeUser, $company] = makeActiveCompanyMember();
    [$otherFinanceUser, $otherCompany] = makeActiveCompanyMember();

    assignAccountingApiRole($financeUser, $company->id, [
        'accounting.invoices.view',
        'accounting.invoices.manage',
        'accounting.invoices.post',
        'accounting.payments.view',
        'accounting.payments.manage',
        'accounting.payments.approve_reversal',
    ]);
    assignAccountingApiRole($otherFinanceUser, $otherCompany->id, [
        'accounting.invoices.view',
        'accounting.invoices.manage',
        'accounting.invoices.post',
        'accounting.payments.view',
        'accounting.payments.manage',
        'accounting.payments.approve_reversal',
    ]);

    $customer = createAccountingApiPartner($company->id, $financeUser->id, 'Accounting API Customer', 'customer');
    $vendor = createAccountingApiPartner($company->id, $financeUser->id, 'Accounting API Vendor', 'vendor');
    $otherCustomer = createAccountingApiPartner($otherCompany->id, $otherFinanceUser->id, 'Other Accounting Customer', 'customer');

    $outOfScopeInvoice = AccountingInvoice::create([
        'company_id' => $otherCompany->id,
        'partner_id' => $otherCustomer->id,
        'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
        'invoice_number' => 'INV-OTHER-'.Str::upper(Str::random(4)),
        'status' => AccountingInvoice::STATUS_DRAFT,
        'delivery_status' => AccountingInvoice::DELIVERY_STATUS_NOT_REQUIRED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 100,
        'tax_total' => 0,
        'grand_total' => 100,
        'paid_total' => 0,
        'balance_due' => 100,
        'created_by' => $otherFinanceUser->id,
        'updated_by' => $otherFinanceUser->id,
    ]);

    $outOfScopePayment = AccountingPayment::create([
        'company_id' => $otherCompany->id,
        'invoice_id' => $outOfScopeInvoice->id,
        'payment_number' => 'PAY-OTHER-'.Str::upper(Str::random(4)),
        'status' => AccountingPayment::STATUS_DRAFT,
        'payment_date' => now()->toDateString(),
        'amount' => 50,
        'method' => 'bank_transfer',
        'created_by' => $otherFinanceUser->id,
        'updated_by' => $otherFinanceUser->id,
    ]);

    Sanctum::actingAs($financeUser);

    $invoiceResponse = postJson('/api/v1/accounting/invoices', [
        'partner_id' => $customer->id,
        'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
        'sales_order_id' => null,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'notes' => 'API lifecycle invoice',
        'lines' => [
            [
                'product_id' => null,
                'description' => 'Consulting service',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 10,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', AccountingInvoice::STATUS_DRAFT)
        ->assertJsonPath('data.document_type', AccountingInvoice::TYPE_CUSTOMER_INVOICE);

    expect((float) $invoiceResponse->json('data.grand_total'))->toBe(110.0);

    $invoiceId = (string) $invoiceResponse->json('data.id');

    getJson('/api/v1/accounting/invoices?status=draft&document_type=customer_invoice&sort=invoice_number&direction=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $invoiceId)
        ->assertJsonPath('meta.filters.status', AccountingInvoice::STATUS_DRAFT)
        ->assertJsonPath('meta.filters.document_type', AccountingInvoice::TYPE_CUSTOMER_INVOICE)
        ->assertJsonPath('meta.sort', 'invoice_number');

    getJson('/api/v1/accounting/invoices/'.$outOfScopeInvoice->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');

    patchJson('/api/v1/accounting/invoices/'.$invoiceId, [
        'partner_id' => $customer->id,
        'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(45)->toDateString(),
        'notes' => 'API lifecycle invoice updated',
        'lines' => [
            [
                'product_id' => null,
                'description' => 'Expanded consulting service',
                'quantity' => 2,
                'unit_price' => 100,
                'tax_rate' => 10,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.notes', 'API lifecycle invoice updated');

    postJson('/api/v1/accounting/invoices/'.$invoiceId.'/post')
        ->assertOk()
        ->assertJsonPath('data.status', AccountingInvoice::STATUS_POSTED);

    $paymentResponse = postJson('/api/v1/accounting/payments', [
        'invoice_id' => $invoiceId,
        'payment_date' => now()->toDateString(),
        'amount' => 80,
        'method' => 'bank_transfer',
        'reference' => 'API-PAY-001',
        'notes' => 'Initial API payment',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', AccountingPayment::STATUS_DRAFT);

    $paymentId = (string) $paymentResponse->json('data.id');

    getJson('/api/v1/accounting/payments?status=draft&invoice_id='.$invoiceId.'&sort=payment_number&direction=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $paymentId)
        ->assertJsonPath('meta.filters.status', AccountingPayment::STATUS_DRAFT)
        ->assertJsonPath('meta.filters.invoice_id', $invoiceId);

    getJson('/api/v1/accounting/payments/'.$outOfScopePayment->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');

    patchJson('/api/v1/accounting/payments/'.$paymentId, [
        'invoice_id' => $invoiceId,
        'payment_date' => now()->toDateString(),
        'amount' => 90,
        'method' => 'bank_transfer',
        'reference' => 'API-PAY-002',
        'notes' => 'Updated API payment',
    ])
        ->assertOk()
        ->assertJsonPath('data.reference', 'API-PAY-002');

    postJson('/api/v1/accounting/payments/'.$paymentId.'/post')
        ->assertOk()
        ->assertJsonPath('data.status', AccountingPayment::STATUS_POSTED);

    $reconcileResponse = postJson('/api/v1/accounting/payments/'.$paymentId.'/reconcile')
        ->assertOk()
        ->assertJsonPath('data.status', AccountingPayment::STATUS_RECONCILED)
        ->assertJsonPath('data.invoice_status', AccountingInvoice::STATUS_PARTIALLY_PAID);

    expect((float) $reconcileResponse->json('data.invoice_balance_due'))->toBe(130.0);

    $reverseResponse = postJson('/api/v1/accounting/payments/'.$paymentId.'/reverse', [
        'reason' => 'Customer transfer failed',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', AccountingPayment::STATUS_REVERSED)
        ->assertJsonPath('data.invoice_status', AccountingInvoice::STATUS_POSTED);

    expect((float) $reverseResponse->json('data.invoice_balance_due'))->toBe(220.0);

    $vendorBillResponse = postJson('/api/v1/accounting/invoices', [
        'partner_id' => $vendor->id,
        'document_type' => AccountingInvoice::TYPE_VENDOR_BILL,
        'sales_order_id' => null,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(20)->toDateString(),
        'notes' => 'API vendor bill',
        'lines' => [
            [
                'product_id' => null,
                'description' => 'Vendor service',
                'quantity' => 1,
                'unit_price' => 40,
                'tax_rate' => 0,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.document_type', AccountingInvoice::TYPE_VENDOR_BILL);

    $vendorBillId = (string) $vendorBillResponse->json('data.id');

    postJson('/api/v1/accounting/invoices/'.$vendorBillId.'/post')
        ->assertOk()
        ->assertJsonPath('data.status', AccountingInvoice::STATUS_POSTED);

    postJson('/api/v1/accounting/invoices/'.$vendorBillId.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', AccountingInvoice::STATUS_CANCELLED);

    $draftDeleteResponse = postJson('/api/v1/accounting/invoices', [
        'partner_id' => $customer->id,
        'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
        'sales_order_id' => null,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'notes' => 'Delete draft invoice',
        'lines' => [
            [
                'product_id' => null,
                'description' => 'Draft cleanup line',
                'quantity' => 1,
                'unit_price' => 10,
                'tax_rate' => 0,
            ],
        ],
    ])->assertCreated();

    deleteJson('/api/v1/accounting/invoices/'.(string) $draftDeleteResponse->json('data.id'))
        ->assertNoContent();
});

test('api v1 accounting permissions validation and lifecycle errors use the shared contract', function () {
    [$viewer, $company] = makeActiveCompanyMember();

    assignAccountingApiRole($viewer, $company->id, [
        'accounting.invoices.view',
        'accounting.payments.view',
    ]);

    $customer = createAccountingApiPartner($company->id, $viewer->id, 'Restricted Accounting Customer', 'customer');

    Sanctum::actingAs($viewer);

    postJson('/api/v1/accounting/invoices', [
        'partner_id' => $customer->id,
        'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
        'sales_order_id' => null,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'notes' => 'Forbidden invoice',
        'lines' => [
            [
                'product_id' => null,
                'description' => 'Forbidden line',
                'quantity' => 1,
                'unit_price' => 10,
                'tax_rate' => 0,
            ],
        ],
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');

    assignAccountingApiRole($viewer, $company->id, [
        'accounting.invoices.view',
        'accounting.invoices.manage',
        'accounting.invoices.post',
        'accounting.payments.view',
        'accounting.payments.manage',
        'accounting.payments.approve_reversal',
    ]);

    Sanctum::actingAs($viewer);

    $draftInvoiceResponse = postJson('/api/v1/accounting/invoices', [
        'partner_id' => $customer->id,
        'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
        'sales_order_id' => null,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'notes' => 'Draft invoice for errors',
        'lines' => [
            [
                'product_id' => null,
                'description' => 'Draft line',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 10,
            ],
        ],
    ])->assertCreated();

    $draftInvoiceId = (string) $draftInvoiceResponse->json('data.id');

    postJson('/api/v1/accounting/payments', [
        'invoice_id' => $draftInvoiceId,
        'payment_date' => now()->toDateString(),
        'amount' => 50,
        'method' => 'cash',
        'reference' => 'DRAFT-PAY',
        'notes' => 'Should fail',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Invoice must be posted before payments can be captured.');

    postJson('/api/v1/accounting/invoices/'.$draftInvoiceId.'/post')
        ->assertOk()
        ->assertJsonPath('data.status', AccountingInvoice::STATUS_POSTED);

    postJson('/api/v1/accounting/payments', [
        'invoice_id' => $draftInvoiceId,
        'payment_date' => now()->toDateString(),
        'amount' => 1000,
        'method' => 'cash',
        'reference' => 'OVERPAY',
        'notes' => 'Should fail',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Payment amount cannot exceed outstanding balance.');

    $paymentResponse = postJson('/api/v1/accounting/payments', [
        'invoice_id' => $draftInvoiceId,
        'payment_date' => now()->toDateString(),
        'amount' => 40,
        'method' => 'cash',
        'reference' => 'VALID-PAY',
        'notes' => 'Valid payment',
    ])->assertCreated();

    $paymentId = (string) $paymentResponse->json('data.id');

    postJson('/api/v1/accounting/payments/'.$paymentId.'/reverse', [
        'reason' => 'bad',
    ])
        ->assertUnprocessable()
        ->assertJsonStructure([
            'message',
            'errors' => ['reason'],
        ]);
});
