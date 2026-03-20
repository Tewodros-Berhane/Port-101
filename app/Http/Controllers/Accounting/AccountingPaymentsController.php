<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountingPaymentReverseRequest;
use App\Http\Requests\Accounting\AccountingPaymentStoreRequest;
use App\Http\Requests\Accounting\AccountingPaymentUpdateRequest;
use App\Modules\Accounting\AccountingPaymentWorkflowService;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingPaymentsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AccountingPayment::class);

        $user = $request->user();

        $query = AccountingPayment::query()
            ->with(['invoice:id,invoice_number,partner_id', 'invoice.partner:id,name'])
            ->latest('payment_date')
            ->latest('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        $payments = $query->paginate(20)->withQueryString();

        return Inertia::render('accounting/payments/index', [
            'payments' => $payments->through(fn (AccountingPayment $payment) => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'status' => $payment->status,
                'invoice_id' => $payment->invoice_id,
                'invoice_number' => $payment->invoice?->invoice_number,
                'partner_name' => $payment->invoice?->partner?->name,
                'payment_date' => $payment->payment_date?->toDateString(),
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'bank_reconciled_at' => $payment->bank_reconciled_at?->toIso8601String(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', AccountingPayment::class);

        return Inertia::render('accounting/payments/create', [
            'payment' => [
                'invoice_id' => $request->string('invoice_id')->toString(),
                'payment_date' => now()->toDateString(),
                'amount' => 0,
                'method' => '',
                'reference' => '',
                'notes' => '',
            ],
            'invoices' => $this->invoiceOptions($request),
        ]);
    }

    public function store(
        AccountingPaymentStoreRequest $request,
        AccountingPaymentWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('create', AccountingPayment::class);

        $companyId = (string) $request->user()?->current_company_id;

        if (! $companyId) {
            abort(403, 'Company context not available.');
        }

        $payment = $workflowService->createDraft(
            attributes: $request->validated(),
            companyId: $companyId,
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.accounting.payments.edit', $payment)
            ->with('success', 'Payment created.');
    }

    public function edit(Request $request, AccountingPayment $payment): Response
    {
        $this->authorize('view', $payment);

        $payment->load([
            'invoice:id,invoice_number,balance_due,status,partner_id',
            'invoice.partner:id,name',
            'reconciliationEntries:id,payment_id,entry_type,amount,reconciled_at',
        ]);

        return Inertia::render('accounting/payments/edit', [
            'payment' => [
                'id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'invoice_number' => $payment->invoice?->invoice_number,
                'invoice_status' => $payment->invoice?->status,
                'partner_name' => $payment->invoice?->partner?->name,
                'payment_number' => $payment->payment_number,
                'status' => $payment->status,
                'payment_date' => $payment->payment_date?->toDateString(),
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'posted_at' => $payment->posted_at?->toIso8601String(),
                'reconciled_at' => $payment->reconciled_at?->toIso8601String(),
                'bank_reconciled_at' => $payment->bank_reconciled_at?->toIso8601String(),
                'reversed_at' => $payment->reversed_at?->toIso8601String(),
                'reversal_reason' => $payment->reversal_reason,
                'reconciliations' => $payment->reconciliationEntries
                    ->map(fn ($entry) => [
                        'id' => $entry->id,
                        'entry_type' => $entry->entry_type,
                        'amount' => (float) $entry->amount,
                        'reconciled_at' => $entry->reconciled_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ],
            'invoices' => $this->invoiceOptions($request, $payment->invoice_id),
        ]);
    }

    public function update(
        AccountingPaymentUpdateRequest $request,
        AccountingPayment $payment,
        AccountingPaymentWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('update', $payment);

        $workflowService->updateDraft(
            payment: $payment,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.accounting.payments.edit', $payment)
            ->with('success', 'Payment updated.');
    }

    public function post(
        Request $request,
        AccountingPayment $payment,
        AccountingPaymentWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('post', $payment);

        $workflowService->post($payment, $request->user()?->id);

        return redirect()
            ->route('company.accounting.payments.edit', $payment)
            ->with('success', 'Payment posted.');
    }

    public function reconcile(
        Request $request,
        AccountingPayment $payment,
        AccountingPaymentWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('reconcile', $payment);

        $workflowService->reconcile($payment, $request->user()?->id);

        return redirect()
            ->route('company.accounting.payments.edit', $payment)
            ->with('success', 'Payment reconciled.');
    }

    public function reverse(
        AccountingPaymentReverseRequest $request,
        AccountingPayment $payment,
        AccountingPaymentWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('reverse', $payment);

        $workflowService->reverse(
            payment: $payment,
            reason: (string) $request->validated('reason'),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.accounting.payments.edit', $payment)
            ->with('success', 'Payment reversed.');
    }

    public function destroy(AccountingPayment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        $payment->delete();

        return redirect()
            ->route('company.accounting.payments.index')
            ->with('success', 'Payment deleted.');
    }

    /**
     * @return array<int, array{id: string, invoice_number: string, partner_name: string|null, balance_due: number}>
     */
    private function invoiceOptions(Request $request, ?string $selectedInvoiceId = null): array
    {
        $user = $request->user();

        $query = AccountingInvoice::query()
            ->with('partner:id,name')
            ->whereIn('status', [
                AccountingInvoice::STATUS_POSTED,
                AccountingInvoice::STATUS_PARTIALLY_PAID,
                AccountingInvoice::STATUS_PAID,
            ])
            ->where(function ($builder) use ($selectedInvoiceId) {
                $builder->where('balance_due', '>', 0);

                if ($selectedInvoiceId) {
                    $builder->orWhere('id', $selectedInvoiceId);
                }
            })
            ->latest('invoice_date');

        if ($user) {
            $query = $user->applyDataScopeToQuery($query);
        }

        return $query
            ->limit(300)
            ->get(['id', 'invoice_number', 'partner_id', 'balance_due'])
            ->map(fn (AccountingInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'partner_name' => $invoice->partner?->name,
                'balance_due' => (float) $invoice->balance_due,
            ])
            ->values()
            ->all();
    }
}
