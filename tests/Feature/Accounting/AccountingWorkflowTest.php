<?php

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingAccount;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingLedgerEntry;
use App\Modules\Accounting\Models\AccountingPayment;
use App\Modules\Inventory\Events\StockDelivered;
use App\Modules\Sales\Events\SalesOrderReadyForInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function assignAccountingWorkflowRole(
    User $user,
    string $companyId,
    array $permissionSlugs
): void {
    $role = Role::create([
        'name' => 'Accounting Role '.Str::upper(Str::random(4)),
        'slug' => 'accounting-role-'.Str::lower(Str::random(8)),
        'description' => 'Accounting workflow test role',
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

    $user->memberships()
        ->where('company_id', $companyId)
        ->update([
            'role_id' => $role->id,
            'is_owner' => false,
        ]);
}

test('sales invoice handoff creates draft invoice and delivery event marks it ready', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignAccountingWorkflowRole($user, $company->id, [
        'accounting.invoices.view',
        'accounting.invoices.manage',
        'accounting.invoices.post',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'name' => 'Accounting Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Accounting Product '.Str::upper(Str::random(4)),
        'type' => 'stock',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $order = SalesOrder::create([
        'company_id' => $company->id,
        'quote_id' => null,
        'partner_id' => $partner->id,
        'order_number' => 'SO-ACC-'.Str::upper(Str::random(4)),
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 200,
        'discount_total' => 0,
        'tax_total' => 20,
        'grand_total' => 220,
        'requires_approval' => false,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    SalesOrderLine::create([
        'company_id' => $company->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'description' => 'Auto invoice line',
        'quantity' => 2,
        'unit_price' => 100,
        'discount_percent' => 0,
        'tax_rate' => 10,
        'line_subtotal' => 200,
        'line_total' => 220,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    event(new SalesOrderReadyForInvoice(
        orderId: $order->id,
        companyId: $company->id,
        quoteId: null,
    ));

    $invoice = AccountingInvoice::query()
        ->where('company_id', $company->id)
        ->where('sales_order_id', $order->id)
        ->first();

    expect($invoice)->not->toBeNull();
    expect($invoice?->status)->toBe(AccountingInvoice::STATUS_DRAFT);
    expect($invoice?->delivery_status)->toBe(AccountingInvoice::DELIVERY_STATUS_PENDING);
    expect((float) $invoice?->grand_total)->toBe(220.0);
    expect((float) $invoice?->balance_due)->toBe(220.0);

    event(new StockDelivered(
        moveId: (string) Str::uuid(),
        companyId: $company->id,
        orderId: $order->id,
        productId: $product->id,
        quantity: 2,
    ));

    expect($invoice?->fresh()->delivery_status)->toBe(AccountingInvoice::DELIVERY_STATUS_READY);
    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_FULFILLED);
});

test('accounting invoice and payment workflow supports post reconcile and reversal', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignAccountingWorkflowRole($user, $company->id, [
        'accounting.invoices.view',
        'accounting.invoices.manage',
        'accounting.invoices.post',
        'accounting.payments.view',
        'accounting.payments.manage',
        'accounting.payments.approve_reversal',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'name' => 'Payment Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('company.accounting.invoices.store'), [
            'partner_id' => $partner->id,
            'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
            'sales_order_id' => null,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'notes' => 'Lifecycle test invoice',
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
        ->assertRedirect();

    $invoice = AccountingInvoice::query()->first();

    expect($invoice)->not->toBeNull();
    expect($invoice?->status)->toBe(AccountingInvoice::STATUS_DRAFT);
    expect((float) $invoice?->grand_total)->toBe(110.0);

    actingAs($user)
        ->post(route('company.accounting.invoices.post', $invoice))
        ->assertRedirect();

    expect($invoice?->fresh()->status)->toBe(AccountingInvoice::STATUS_POSTED);
    expect(AccountingLedgerEntry::query()
        ->where('source_type', AccountingInvoice::class)
        ->where('source_id', $invoice?->id)
        ->where('source_action', AccountingLedgerEntry::ACTION_INVOICE_POST)
        ->count())->toBe(3);

    $receivable = AccountingAccount::query()
        ->where('system_key', AccountingAccount::SYSTEM_ACCOUNTS_RECEIVABLE)
        ->first();

    expect($receivable)->not->toBeNull();
    expect((float) AccountingLedgerEntry::query()
        ->where('account_id', $receivable?->id)
        ->sum('debit'))->toBe(110.0);

    actingAs($user)
        ->post(route('company.accounting.payments.store'), [
            'invoice_id' => $invoice?->id,
            'payment_date' => now()->toDateString(),
            'amount' => 50,
            'method' => 'bank_transfer',
            'reference' => 'PAY-50',
            'notes' => 'Partial payment',
        ])
        ->assertRedirect();

    $firstPayment = AccountingPayment::query()->where('reference', 'PAY-50')->first();

    expect($firstPayment)->not->toBeNull();
    expect($firstPayment?->status)->toBe(AccountingPayment::STATUS_DRAFT);

    actingAs($user)
        ->post(route('company.accounting.payments.post', $firstPayment))
        ->assertRedirect();

    expect(AccountingLedgerEntry::query()
        ->where('source_type', AccountingPayment::class)
        ->where('source_id', $firstPayment?->id)
        ->where('source_action', AccountingLedgerEntry::ACTION_PAYMENT_POST)
        ->count())->toBe(2);
    actingAs($user)
        ->post(route('company.accounting.payments.reconcile', $firstPayment))
        ->assertRedirect();

    $invoice = $invoice?->fresh();
    expect($invoice?->status)->toBe(AccountingInvoice::STATUS_PARTIALLY_PAID);
    expect((float) $invoice?->paid_total)->toBe(50.0);
    expect((float) $invoice?->balance_due)->toBe(60.0);

    actingAs($user)
        ->post(route('company.accounting.payments.reverse', $firstPayment), [
            'reason' => 'Bank transfer bounced',
        ])
        ->assertRedirect();

    expect($firstPayment?->fresh()->status)->toBe(AccountingPayment::STATUS_REVERSED);
    expect(AccountingLedgerEntry::query()
        ->where('source_type', AccountingPayment::class)
        ->where('source_id', $firstPayment?->id)
        ->where('source_action', AccountingLedgerEntry::ACTION_PAYMENT_REVERSE)
        ->count())->toBe(2);
    $invoice = $invoice?->fresh();
    expect($invoice?->status)->toBe(AccountingInvoice::STATUS_POSTED);
    expect((float) $invoice?->paid_total)->toBe(0.0);
    expect((float) $invoice?->balance_due)->toBe(110.0);

    actingAs($user)
        ->post(route('company.accounting.payments.store'), [
            'invoice_id' => $invoice?->id,
            'payment_date' => now()->toDateString(),
            'amount' => 110,
            'method' => 'card',
            'reference' => 'PAY-110',
            'notes' => 'Final settlement',
        ])
        ->assertRedirect();

    $secondPayment = AccountingPayment::query()->where('reference', 'PAY-110')->first();

    actingAs($user)
        ->post(route('company.accounting.payments.post', $secondPayment))
        ->assertRedirect();
    actingAs($user)
        ->post(route('company.accounting.payments.reconcile', $secondPayment))
        ->assertRedirect();

    $invoice = $invoice?->fresh();
    expect($invoice?->status)->toBe(AccountingInvoice::STATUS_PAID);
    expect((float) $invoice?->paid_total)->toBe(110.0);
    expect((float) $invoice?->balance_due)->toBe(0.0);
});

test('accounting foundation pages render for finance-enabled users', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignAccountingWorkflowRole($user, $company->id, [
        'accounting.accounts.view',
        'accounting.journals.view',
        'accounting.ledger.view',
        'accounting.statements.view',
        'reports.export',
    ]);

    actingAs($user)
        ->get(route('company.accounting.accounts.index'))
        ->assertOk();

    actingAs($user)
        ->get(route('company.accounting.journals.index'))
        ->assertOk();

    actingAs($user)
        ->get(route('company.accounting.ledger.index'))
        ->assertOk();

    actingAs($user)
        ->get(route('company.accounting.statements.index'))
        ->assertOk();
});
