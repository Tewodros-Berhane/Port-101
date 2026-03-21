<?php

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Core\Settings\SettingsService;
use App\Models\User;
use App\Modules\Accounting\AccountingSetupService;
use App\Modules\Accounting\Models\AccountingAccount;
use App\Modules\Accounting\Models\AccountingBankReconciliationBatch;
use App\Modules\Accounting\Models\AccountingBankStatementImport;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingLedgerEntry;
use App\Modules\Accounting\Models\AccountingManualJournal;
use App\Modules\Accounting\Models\AccountingPayment;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Inventory\Events\StockDelivered;
use App\Modules\Sales\Events\SalesOrderReadyForInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

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
        'accounting.bank_reconciliation.view',
        'accounting.manual_journals.view',
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
        ->get(route('company.accounting.bank-reconciliation.index'))
        ->assertOk();

    actingAs($user)
        ->get(route('company.accounting.manual-journals.index'))
        ->assertOk();

    actingAs($user)
        ->get(route('company.accounting.ledger.index'))
        ->assertOk();

    actingAs($user)
        ->get(route('company.accounting.statements.index'))
        ->assertOk();
});

test('manual journal workflow supports draft posting and reversal', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignAccountingWorkflowRole($user, $company->id, [
        'accounting.manual_journals.view',
        'accounting.manual_journals.manage',
        'accounting.manual_journals.post',
        'accounting.accounts.view',
        'accounting.journals.view',
        'accounting.ledger.view',
    ]);

    $setup = app(AccountingSetupService::class)->ensureCompanySetup(
        companyId: $company->id,
        currencyCode: $company->currency_code,
        actorId: $user->id,
    );

    $cashAccount = $setup['accounts'][AccountingAccount::SYSTEM_CASH_BANK];
    $expenseAccount = $setup['accounts'][AccountingAccount::SYSTEM_PURCHASE_EXPENSE];
    $generalJournal = $setup['journals']['general'];

    actingAs($user)
        ->post(route('company.accounting.manual-journals.store'), [
            'journal_id' => $generalJournal->id,
            'entry_date' => now()->toDateString(),
            'reference' => 'ACCRUAL-001',
            'description' => 'Month-end accrual',
            'lines' => [
                [
                    'account_id' => $expenseAccount->id,
                    'description' => 'Expense accrual',
                    'debit' => 250,
                    'credit' => 0,
                ],
                [
                    'account_id' => $cashAccount->id,
                    'description' => 'Cash offset',
                    'debit' => 0,
                    'credit' => 250,
                ],
            ],
        ])
        ->assertRedirect();

    $manualJournal = AccountingManualJournal::query()->first();

    expect($manualJournal)->not->toBeNull();
    expect($manualJournal?->status)->toBe(AccountingManualJournal::STATUS_DRAFT);
    expect($manualJournal?->lines()->count())->toBe(2);

    actingAs($user)
        ->post(route('company.accounting.manual-journals.post', $manualJournal))
        ->assertRedirect();

    expect($manualJournal?->fresh()->status)->toBe(AccountingManualJournal::STATUS_POSTED);
    expect(AccountingLedgerEntry::query()
        ->where('source_type', AccountingManualJournal::class)
        ->where('source_id', $manualJournal?->id)
        ->where('source_action', AccountingLedgerEntry::ACTION_MANUAL_JOURNAL_POST)
        ->count())->toBe(2);

    actingAs($user)
        ->post(route('company.accounting.manual-journals.reverse', $manualJournal), [
            'reason' => 'Accrual released',
        ])
        ->assertRedirect();

    expect($manualJournal?->fresh()->status)->toBe(AccountingManualJournal::STATUS_REVERSED);
    expect(AccountingLedgerEntry::query()
        ->where('source_type', AccountingManualJournal::class)
        ->where('source_id', $manualJournal?->id)
        ->where('source_action', AccountingLedgerEntry::ACTION_MANUAL_JOURNAL_REVERSE)
        ->count())->toBe(2);
});

test('manual journals require approval above configured threshold before posting', function () {
    [$user, $company] = makeActiveCompanyMember();

    $approver = User::factory()->create();
    $company->users()->attach($approver->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);
    $approver->forceFill(['current_company_id' => $company->id])->save();

    assignAccountingWorkflowRole($user, $company->id, [
        'accounting.manual_journals.view',
        'accounting.manual_journals.manage',
        'accounting.manual_journals.post',
        'accounting.accounts.view',
        'accounting.journals.view',
        'accounting.ledger.view',
    ]);

    assignAccountingWorkflowRole($approver, $company->id, [
        'approvals.requests.view',
        'approvals.requests.manage',
    ]);

    $settings = app(SettingsService::class);
    $settings->set('company.approvals.enabled', true, $company->id, null, $user->id);
    $settings->set('company.approvals.policy', 'amount_based', $company->id, null, $user->id);
    $settings->set('company.approvals.threshold_amount', 1000, $company->id, null, $user->id);
    $settings->set('company.accounting.manual_journal_approval_threshold', 100, $company->id, null, $user->id);

    $setup = app(AccountingSetupService::class)->ensureCompanySetup(
        companyId: $company->id,
        currencyCode: $company->currency_code,
        actorId: $user->id,
    );

    $cashAccount = $setup['accounts'][AccountingAccount::SYSTEM_CASH_BANK];
    $expenseAccount = $setup['accounts'][AccountingAccount::SYSTEM_PURCHASE_EXPENSE];
    $generalJournal = $setup['journals']['general'];

    actingAs($user)
        ->post(route('company.accounting.manual-journals.store'), [
            'journal_id' => $generalJournal->id,
            'entry_date' => now()->toDateString(),
            'reference' => 'APPROVAL-001',
            'description' => 'Approval threshold accrual',
            'lines' => [
                [
                    'account_id' => $expenseAccount->id,
                    'description' => 'Expense accrual',
                    'debit' => 250,
                    'credit' => 0,
                ],
                [
                    'account_id' => $cashAccount->id,
                    'description' => 'Cash offset',
                    'debit' => 0,
                    'credit' => 250,
                ],
            ],
        ])
        ->assertRedirect();

    $manualJournal = AccountingManualJournal::query()->first();

    expect($manualJournal)->not->toBeNull();
    expect($manualJournal?->requires_approval)->toBeTrue();
    expect($manualJournal?->approval_status)->toBe(AccountingManualJournal::APPROVAL_STATUS_PENDING);

    actingAs($user)
        ->post(route('company.accounting.manual-journals.post', $manualJournal))
        ->assertForbidden();

    $approvalRequest = ApprovalRequest::query()
        ->where('source_type', AccountingManualJournal::class)
        ->where('source_id', $manualJournal?->id)
        ->first();

    expect($approvalRequest)->not->toBeNull();
    expect($approvalRequest?->status)->toBe(ApprovalRequest::STATUS_PENDING);

    actingAs($approver)
        ->post(route('company.approvals.approve', $approvalRequest))
        ->assertRedirect();

    expect($manualJournal?->fresh()->approval_status)
        ->toBe(AccountingManualJournal::APPROVAL_STATUS_APPROVED);

    actingAs($user)
        ->post(route('company.accounting.manual-journals.post', $manualJournal))
        ->assertRedirect();

    expect($manualJournal?->fresh()->status)->toBe(AccountingManualJournal::STATUS_POSTED);
});

test('manual journal edit supports supporting document attachments', function () {
    Storage::fake('attachments');
    config()->set('core.attachments.disk', 'attachments');

    [$user, $company] = makeActiveCompanyMember();

    assignAccountingWorkflowRole($user, $company->id, [
        'accounting.manual_journals.view',
        'accounting.manual_journals.manage',
        'accounting.accounts.view',
        'accounting.journals.view',
        'core.attachments.view',
        'core.attachments.manage',
    ]);

    $setup = app(AccountingSetupService::class)->ensureCompanySetup(
        companyId: $company->id,
        currencyCode: $company->currency_code,
        actorId: $user->id,
    );

    $cashAccount = $setup['accounts'][AccountingAccount::SYSTEM_CASH_BANK];
    $expenseAccount = $setup['accounts'][AccountingAccount::SYSTEM_PURCHASE_EXPENSE];
    $generalJournal = $setup['journals']['general'];

    actingAs($user)
        ->post(route('company.accounting.manual-journals.store'), [
            'journal_id' => $generalJournal->id,
            'entry_date' => now()->toDateString(),
            'reference' => 'ATTACH-001',
            'description' => 'Supporting docs journal',
            'lines' => [
                [
                    'account_id' => $expenseAccount->id,
                    'description' => 'Expense accrual',
                    'debit' => 120,
                    'credit' => 0,
                ],
                [
                    'account_id' => $cashAccount->id,
                    'description' => 'Cash offset',
                    'debit' => 0,
                    'credit' => 120,
                ],
            ],
        ])
        ->assertRedirect();

    $manualJournal = AccountingManualJournal::query()->first();

    $file = UploadedFile::fake()->create(
        'manual-journal-support.pdf',
        50,
        'application/pdf',
    );

    actingAs($user)
        ->post(route('core.attachments.store'), [
            'attachable_type' => 'manual_journal',
            'attachable_id' => $manualJournal?->id,
            'file' => $file,
        ])
        ->assertRedirect();

    actingAs($user)
        ->get(route('company.accounting.manual-journals.edit', $manualJournal))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('attachments', 1)
            ->where('attachments.0.original_name', 'manual-journal-support.pdf')
        );
});

test('bank reconciliation batches stamp payments and ledger entries', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignAccountingWorkflowRole($user, $company->id, [
        'accounting.bank_reconciliation.view',
        'accounting.bank_reconciliation.manage',
        'accounting.invoices.view',
        'accounting.invoices.manage',
        'accounting.invoices.post',
        'accounting.payments.view',
        'accounting.payments.manage',
        'accounting.payments.approve_reversal',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'name' => 'Bank Rec Customer '.Str::upper(Str::random(4)),
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
            'notes' => 'Bank reconciliation test invoice',
            'lines' => [[
                'product_id' => null,
                'description' => 'Support retainer',
                'quantity' => 1,
                'unit_price' => 180,
                'tax_rate' => 0,
            ]],
        ])
        ->assertRedirect();

    $invoice = AccountingInvoice::query()->first();

    actingAs($user)
        ->post(route('company.accounting.invoices.post', $invoice))
        ->assertRedirect();

    actingAs($user)
        ->post(route('company.accounting.payments.store'), [
            'invoice_id' => $invoice?->id,
            'payment_date' => now()->toDateString(),
            'amount' => 180,
            'method' => 'bank_transfer',
            'reference' => 'BANK-REC-180',
            'notes' => 'Statement line payment',
        ])
        ->assertRedirect();

    $payment = AccountingPayment::query()->where('reference', 'BANK-REC-180')->first();

    actingAs($user)
        ->post(route('company.accounting.payments.post', $payment))
        ->assertRedirect();

    $setup = app(AccountingSetupService::class)->ensureCompanySetup(
        companyId: $company->id,
        currencyCode: $company->currency_code,
        actorId: $user->id,
    );

    $bankJournal = $setup['journals']['bank'];

    actingAs($user)
        ->post(route('company.accounting.bank-reconciliation.store'), [
            'journal_id' => $bankJournal->id,
            'statement_reference' => 'STATEMENT-2026-03',
            'statement_date' => now()->toDateString(),
            'notes' => 'Primary bank statement import',
            'payment_ids' => [$payment?->id],
        ])
        ->assertRedirect();

    $batch = AccountingBankReconciliationBatch::query()->first();

    expect($batch)->not->toBeNull();
    expect($payment?->fresh()->bank_reconciled_at)->not->toBeNull();
    expect(AccountingLedgerEntry::query()
        ->where('source_type', AccountingPayment::class)
        ->where('source_id', $payment?->id)
        ->where('source_action', AccountingLedgerEntry::ACTION_PAYMENT_POST)
        ->whereNotNull('reconciled_at')
        ->count())->toBe(2);

    actingAs($user)
        ->post(route('company.accounting.payments.reverse', $payment), [
            'reason' => 'Should be blocked after bank reconciliation',
        ])
        ->assertForbidden();
});

test('bank reconciliation batches can be unreconciled and release payment reversal', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignAccountingWorkflowRole($user, $company->id, [
        'accounting.bank_reconciliation.view',
        'accounting.bank_reconciliation.manage',
        'accounting.invoices.view',
        'accounting.invoices.manage',
        'accounting.invoices.post',
        'accounting.payments.view',
        'accounting.payments.manage',
        'accounting.payments.approve_reversal',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'name' => 'Bank Unrec Customer '.Str::upper(Str::random(4)),
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
            'notes' => 'Bank unreconcile test invoice',
            'lines' => [[
                'product_id' => null,
                'description' => 'Support retainer',
                'quantity' => 1,
                'unit_price' => 220,
                'tax_rate' => 0,
            ]],
        ])
        ->assertRedirect();

    $invoice = AccountingInvoice::query()->first();

    actingAs($user)
        ->post(route('company.accounting.invoices.post', $invoice))
        ->assertRedirect();

    actingAs($user)
        ->post(route('company.accounting.payments.store'), [
            'invoice_id' => $invoice?->id,
            'payment_date' => now()->toDateString(),
            'amount' => 220,
            'method' => 'bank_transfer',
            'reference' => 'BANK-UNREC-220',
            'notes' => 'Statement line payment',
        ])
        ->assertRedirect();

    $payment = AccountingPayment::query()->where('reference', 'BANK-UNREC-220')->first();

    actingAs($user)
        ->post(route('company.accounting.payments.post', $payment))
        ->assertRedirect();

    $setup = app(AccountingSetupService::class)->ensureCompanySetup(
        companyId: $company->id,
        currencyCode: $company->currency_code,
        actorId: $user->id,
    );

    actingAs($user)
        ->post(route('company.accounting.bank-reconciliation.store'), [
            'journal_id' => $setup['journals']['bank']->id,
            'statement_reference' => 'STATEMENT-2026-04',
            'statement_date' => now()->toDateString(),
            'notes' => 'Batch for unreconcile',
            'payment_ids' => [$payment?->id],
        ])
        ->assertRedirect();

    $batch = AccountingBankReconciliationBatch::query()->first();

    actingAs($user)
        ->post(route('company.accounting.bank-reconciliation.unreconcile', $batch), [
            'reason' => 'Statement imported twice',
        ])
        ->assertRedirect();

    expect($batch?->fresh()->unreconciled_at)->not->toBeNull();
    expect($batch?->fresh()->unreconcile_reason)->toBe('Statement imported twice');
    expect($payment?->fresh()->bank_reconciled_at)->toBeNull();
    expect(AccountingLedgerEntry::query()
        ->where('source_type', AccountingPayment::class)
        ->where('source_id', $payment?->id)
        ->where('source_action', AccountingLedgerEntry::ACTION_PAYMENT_POST)
        ->whereNotNull('reconciled_at')
        ->count())->toBe(0);

    actingAs($user)
        ->post(route('company.accounting.payments.reverse', $payment), [
            'reason' => 'Allowed after unreconcile',
        ])
        ->assertRedirect();

    expect($payment?->fresh()->status)->toBe(AccountingPayment::STATUS_REVERSED);
});

test('bank statement csv import matches payments and creates reconciliation batch', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignAccountingWorkflowRole($user, $company->id, [
        'accounting.bank_reconciliation.view',
        'accounting.bank_reconciliation.manage',
        'accounting.invoices.view',
        'accounting.invoices.manage',
        'accounting.invoices.post',
        'accounting.payments.view',
        'accounting.payments.manage',
        'accounting.payments.approve_reversal',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'name' => 'Statement Import Customer '.Str::upper(Str::random(4)),
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
            'notes' => 'Statement import invoice',
            'lines' => [[
                'product_id' => null,
                'description' => 'Retainer',
                'quantity' => 1,
                'unit_price' => 310,
                'tax_rate' => 0,
            ]],
        ])
        ->assertRedirect();

    $invoice = AccountingInvoice::query()->first();

    actingAs($user)
        ->post(route('company.accounting.invoices.post', $invoice))
        ->assertRedirect();

    actingAs($user)
        ->post(route('company.accounting.payments.store'), [
            'invoice_id' => $invoice?->id,
            'payment_date' => now()->toDateString(),
            'amount' => 310,
            'method' => 'bank_transfer',
            'reference' => 'CSV-IMPORT-310',
            'notes' => 'Statement import payment',
        ])
        ->assertRedirect();

    $payment = AccountingPayment::query()->where('reference', 'CSV-IMPORT-310')->first();

    actingAs($user)
        ->post(route('company.accounting.payments.post', $payment))
        ->assertRedirect();

    $setup = app(AccountingSetupService::class)->ensureCompanySetup(
        companyId: $company->id,
        currencyCode: $company->currency_code,
        actorId: $user->id,
    );

    $csv = implode("\n", [
        'date,reference,description,amount',
        now()->toDateString().',CSV-IMPORT-310,Primary statement line,310.00',
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'statement.csv',
        $csv,
    );

    actingAs($user)
        ->post(route('company.accounting.bank-reconciliation.import'), [
            'journal_id' => $setup['journals']['bank']->id,
            'statement_reference' => 'CSV-STATEMENT-2026-03',
            'statement_date' => now()->toDateString(),
            'notes' => 'CSV import run',
            'file' => $file,
        ])
        ->assertRedirect();

    $statementImport = AccountingBankStatementImport::query()->first();

    expect($statementImport)->not->toBeNull();
    expect($statementImport?->lines()->count())->toBe(1);

    $line = $statementImport?->lines()->first();

    expect($line?->match_status)->toBe('matched');
    expect($line?->payment_id)->toBe($payment?->id);

    actingAs($user)
        ->post(route('company.accounting.bank-reconciliation.store'), [
            'bank_statement_import_id' => $statementImport?->id,
            'line_ids' => [$line?->id],
        ])
        ->assertRedirect();

    $batch = AccountingBankReconciliationBatch::query()->first();

    expect($batch)->not->toBeNull();
    expect($statementImport?->fresh()->reconciled_batch_id)->toBe($batch?->id);
    expect($payment?->fresh()->bank_reconciled_at)->not->toBeNull();
    expect($batch?->items()->first()?->statement_line_reference)->toBe('CSV-IMPORT-310');
});

test('bank statement ofx import matches payments', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignAccountingWorkflowRole($user, $company->id, [
        'accounting.bank_reconciliation.view',
        'accounting.bank_reconciliation.manage',
        'accounting.invoices.view',
        'accounting.invoices.manage',
        'accounting.invoices.post',
        'accounting.payments.view',
        'accounting.payments.manage',
        'accounting.payments.approve_reversal',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'name' => 'Statement OFX Customer '.Str::upper(Str::random(4)),
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
            'notes' => 'Statement OFX invoice',
            'lines' => [[
                'product_id' => null,
                'description' => 'Retainer',
                'quantity' => 1,
                'unit_price' => 455,
                'tax_rate' => 0,
            ]],
        ])
        ->assertRedirect();

    $invoice = AccountingInvoice::query()->first();

    actingAs($user)
        ->post(route('company.accounting.invoices.post', $invoice))
        ->assertRedirect();

    actingAs($user)
        ->post(route('company.accounting.payments.store'), [
            'invoice_id' => $invoice?->id,
            'payment_date' => now()->toDateString(),
            'amount' => 455,
            'method' => 'bank_transfer',
            'reference' => 'OFX-IMPORT-455',
            'notes' => 'OFX statement import payment',
        ])
        ->assertRedirect();

    $payment = AccountingPayment::query()->where('reference', 'OFX-IMPORT-455')->first();

    actingAs($user)
        ->post(route('company.accounting.payments.post', $payment))
        ->assertRedirect();

    $setup = app(AccountingSetupService::class)->ensureCompanySetup(
        companyId: $company->id,
        currencyCode: $company->currency_code,
        actorId: $user->id,
    );

    $ofx = implode("\n", [
        'OFXHEADER:100',
        'DATA:OFXSGML',
        'VERSION:102',
        '',
        '<OFX>',
        '<BANKMSGSRSV1>',
        '<STMTTRNRS>',
        '<STMTRS>',
        '<BANKTRANLIST>',
        '<STMTTRN>',
        '<TRNTYPE>CREDIT',
        '<DTPOSTED>'.now()->format('YmdHis'),
        '<TRNAMT>455.00',
        '<FITID>OFX-IMPORT-455',
        '<NAME>OFX customer receipt',
        '<MEMO>Primary OFX statement line',
        '</STMTTRN>',
        '</BANKTRANLIST>',
        '</STMTRS>',
        '</STMTTRNRS>',
        '</BANKMSGSRSV1>',
        '</OFX>',
    ]);

    $file = UploadedFile::fake()->createWithContent('statement.ofx', $ofx);

    actingAs($user)
        ->post(route('company.accounting.bank-reconciliation.import'), [
            'journal_id' => $setup['journals']['bank']->id,
            'statement_reference' => 'OFX-STATEMENT-2026-03',
            'statement_date' => now()->toDateString(),
            'notes' => 'OFX import run',
            'file' => $file,
        ])
        ->assertRedirect();

    $statementImport = AccountingBankStatementImport::query()->first();
    $line = $statementImport?->lines()->first();

    expect($statementImport)->not->toBeNull();
    expect($line?->match_status)->toBe('matched');
    expect($line?->payment_id)->toBe($payment?->id);
    expect($line?->reference)->toBe('OFX-IMPORT-455');
});

test('bank statement camt import matches payments', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignAccountingWorkflowRole($user, $company->id, [
        'accounting.bank_reconciliation.view',
        'accounting.bank_reconciliation.manage',
        'accounting.invoices.view',
        'accounting.invoices.manage',
        'accounting.invoices.post',
        'accounting.payments.view',
        'accounting.payments.manage',
        'accounting.payments.approve_reversal',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'name' => 'Statement CAMT Customer '.Str::upper(Str::random(4)),
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
            'notes' => 'Statement CAMT invoice',
            'lines' => [[
                'product_id' => null,
                'description' => 'Retainer',
                'quantity' => 1,
                'unit_price' => 522,
                'tax_rate' => 0,
            ]],
        ])
        ->assertRedirect();

    $invoice = AccountingInvoice::query()->first();

    actingAs($user)
        ->post(route('company.accounting.invoices.post', $invoice))
        ->assertRedirect();

    actingAs($user)
        ->post(route('company.accounting.payments.store'), [
            'invoice_id' => $invoice?->id,
            'payment_date' => now()->toDateString(),
            'amount' => 522,
            'method' => 'bank_transfer',
            'reference' => 'CAMT-IMPORT-522',
            'notes' => 'CAMT statement import payment',
        ])
        ->assertRedirect();

    $payment = AccountingPayment::query()->where('reference', 'CAMT-IMPORT-522')->first();

    actingAs($user)
        ->post(route('company.accounting.payments.post', $payment))
        ->assertRedirect();

    $setup = app(AccountingSetupService::class)->ensureCompanySetup(
        companyId: $company->id,
        currencyCode: $company->currency_code,
        actorId: $user->id,
    );

    $camt = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02">
  <BkToCstmrStmt>
    <Stmt>
      <Ntry>
        <Amt Ccy="USD">522.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <BookgDt>
          <Dt>%s</Dt>
        </BookgDt>
        <NtryDtls>
          <TxDtls>
            <Refs>
              <EndToEndId>CAMT-IMPORT-522</EndToEndId>
            </Refs>
            <RmtInf>
              <Ustrd>Primary CAMT statement line</Ustrd>
            </RmtInf>
          </TxDtls>
        </NtryDtls>
      </Ntry>
    </Stmt>
  </BkToCstmrStmt>
</Document>
XML;

    $file = UploadedFile::fake()->createWithContent(
        'statement.xml',
        sprintf($camt, now()->toDateString()),
    );

    actingAs($user)
        ->post(route('company.accounting.bank-reconciliation.import'), [
            'journal_id' => $setup['journals']['bank']->id,
            'statement_reference' => 'CAMT-STATEMENT-2026-03',
            'statement_date' => now()->toDateString(),
            'notes' => 'CAMT import run',
            'file' => $file,
        ])
        ->assertRedirect();

    $statementImport = AccountingBankStatementImport::query()->first();
    $line = $statementImport?->lines()->first();

    expect($statementImport)->not->toBeNull();
    expect($line?->match_status)->toBe('matched');
    expect($line?->payment_id)->toBe($payment?->id);
    expect($line?->reference)->toBe('CAMT-IMPORT-522');
});
