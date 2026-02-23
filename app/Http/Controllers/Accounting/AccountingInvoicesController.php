<?php

namespace App\Http\Controllers\Accounting;

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountingInvoiceStoreRequest;
use App\Http\Requests\Accounting\AccountingInvoiceUpdateRequest;
use App\Modules\Accounting\AccountingInvoiceWorkflowService;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingInvoicesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AccountingInvoice::class);

        $user = $request->user();

        $query = AccountingInvoice::query()
            ->with(['partner:id,name,type', 'salesOrder:id,order_number'])
            ->latest('invoice_date')
            ->latest('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        $invoices = $query->paginate(20)->withQueryString();

        return Inertia::render('accounting/invoices/index', [
            'invoices' => $invoices->through(fn (AccountingInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'document_type' => $invoice->document_type,
                'status' => $invoice->status,
                'delivery_status' => $invoice->delivery_status,
                'partner_name' => $invoice->partner?->name,
                'partner_type' => $invoice->partner?->type,
                'sales_order_number' => $invoice->salesOrder?->order_number,
                'invoice_date' => $invoice->invoice_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'grand_total' => (float) $invoice->grand_total,
                'paid_total' => (float) $invoice->paid_total,
                'balance_due' => (float) $invoice->balance_due,
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', AccountingInvoice::class);

        $today = now()->toDateString();

        return Inertia::render('accounting/invoices/create', [
            'invoice' => [
                'partner_id' => '',
                'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
                'sales_order_id' => '',
                'invoice_date' => $today,
                'due_date' => now()->addDays(30)->toDateString(),
                'notes' => '',
                'lines' => [[
                    'product_id' => '',
                    'description' => '',
                    'quantity' => 1,
                    'unit_price' => 0,
                    'tax_rate' => 0,
                ]],
            ],
            'documentTypes' => AccountingInvoice::TYPES,
            'partners' => $this->partnerOptions(),
            'products' => $this->productOptions(),
            'salesOrders' => $this->salesOrderOptions($request),
        ]);
    }

    public function store(
        AccountingInvoiceStoreRequest $request,
        AccountingInvoiceWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('create', AccountingInvoice::class);

        $user = $request->user();
        $companyId = (string) $user?->current_company_id;

        if (! $companyId) {
            abort(403, 'Company context not available.');
        }

        $invoice = $workflowService->createDraft(
            attributes: [
                ...$request->validated(),
                'currency_code' => $user?->currentCompany?->currency_code,
            ],
            companyId: $companyId,
            actorId: $user?->id,
        );

        return redirect()
            ->route('company.accounting.invoices.edit', $invoice)
            ->with('success', 'Invoice created.');
    }

    public function edit(Request $request, AccountingInvoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'lines',
            'partner:id,name,type',
            'salesOrder:id,order_number',
            'payments:id,invoice_id,payment_number,status,amount,payment_date',
        ]);

        return Inertia::render('accounting/invoices/edit', [
            'invoice' => [
                'id' => $invoice->id,
                'partner_id' => $invoice->partner_id,
                'partner_name' => $invoice->partner?->name,
                'document_type' => $invoice->document_type,
                'sales_order_id' => $invoice->sales_order_id,
                'sales_order_number' => $invoice->salesOrder?->order_number,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'delivery_status' => $invoice->delivery_status,
                'invoice_date' => $invoice->invoice_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'subtotal' => (float) $invoice->subtotal,
                'tax_total' => (float) $invoice->tax_total,
                'grand_total' => (float) $invoice->grand_total,
                'paid_total' => (float) $invoice->paid_total,
                'balance_due' => (float) $invoice->balance_due,
                'posted_at' => $invoice->posted_at?->toIso8601String(),
                'cancelled_at' => $invoice->cancelled_at?->toIso8601String(),
                'notes' => $invoice->notes,
                'lines' => $invoice->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'tax_rate' => (float) $line->tax_rate,
                ])->values()->all(),
                'recent_payments' => $invoice->payments
                    ->sortByDesc('payment_date')
                    ->take(5)
                    ->values()
                    ->map(fn ($payment) => [
                        'id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'status' => $payment->status,
                        'amount' => (float) $payment->amount,
                        'payment_date' => $payment->payment_date?->toDateString(),
                    ])
                    ->all(),
            ],
            'documentTypes' => AccountingInvoice::TYPES,
            'partners' => $this->partnerOptions(),
            'products' => $this->productOptions(),
            'salesOrders' => $this->salesOrderOptions($request),
        ]);
    }

    public function update(
        AccountingInvoiceUpdateRequest $request,
        AccountingInvoice $invoice,
        AccountingInvoiceWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('update', $invoice);

        $workflowService->updateDraft(
            invoice: $invoice,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.accounting.invoices.edit', $invoice)
            ->with('success', 'Invoice updated.');
    }

    public function post(
        Request $request,
        AccountingInvoice $invoice,
        AccountingInvoiceWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('post', $invoice);

        $workflowService->post($invoice, $request->user()?->id);

        return redirect()
            ->route('company.accounting.invoices.edit', $invoice)
            ->with('success', 'Invoice posted.');
    }

    public function cancel(
        Request $request,
        AccountingInvoice $invoice,
        AccountingInvoiceWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('cancel', $invoice);

        $workflowService->cancel($invoice, $request->user()?->id);

        return redirect()
            ->route('company.accounting.invoices.edit', $invoice)
            ->with('success', 'Invoice cancelled.');
    }

    public function destroy(AccountingInvoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        if ($invoice->payments()->exists()) {
            return back()->with('error', 'Invoice has payments and cannot be deleted.');
        }

        $invoice->lines()->delete();
        $invoice->delete();

        return redirect()
            ->route('company.accounting.invoices.index')
            ->with('success', 'Invoice deleted.');
    }

    /**
     * @return array<int, array{id: string, name: string, type: string}>
     */
    private function partnerOptions(): array
    {
        return Partner::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'type' => $partner->type,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, sku: string|null}>
     */
    private function productOptions(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, order_number: string, partner_id: string}>
     */
    private function salesOrderOptions(Request $request): array
    {
        $user = $request->user();

        $query = SalesOrder::query()
            ->whereIn('status', [
                SalesOrder::STATUS_CONFIRMED,
                SalesOrder::STATUS_FULFILLED,
                SalesOrder::STATUS_INVOICED,
            ])
            ->latest('created_at');

        if ($user) {
            $query = $user->applyDataScopeToQuery($query);
        }

        return $query
            ->limit(200)
            ->get(['id', 'order_number', 'partner_id'])
            ->map(fn (SalesOrder $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'partner_id' => $order->partner_id,
            ])
            ->values()
            ->all();
    }
}
