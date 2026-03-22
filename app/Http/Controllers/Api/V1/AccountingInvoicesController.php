<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Accounting\AccountingInvoiceStoreRequest;
use App\Http\Requests\Accounting\AccountingInvoiceUpdateRequest;
use App\Models\User;
use App\Modules\Accounting\AccountingInvoiceWorkflowService;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingInvoicesController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountingInvoice::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $documentType = trim((string) $request->input('document_type', ''));
        $partnerId = trim((string) $request->input('partner_id', ''));
        $salesOrderId = trim((string) $request->input('sales_order_id', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['created_at', 'updated_at', 'invoice_date', 'due_date', 'invoice_number', 'status', 'grand_total', 'balance_due'],
            defaultSort: 'invoice_date',
            defaultDirection: 'desc',
        );

        $invoices = AccountingInvoice::query()
            ->with([
                'partner:id,name,type',
                'salesOrder:id,order_number',
                'purchaseOrder:id,order_number',
            ])
            ->withCount(['lines', 'payments'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('partner', fn ($partnerQuery) => $partnerQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('salesOrder', fn ($salesOrderQuery) => $salesOrderQuery->where('order_number', 'like', "%{$search}%"))
                        ->orWhereHas('purchaseOrder', fn ($purchaseOrderQuery) => $purchaseOrderQuery->where('order_number', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($documentType !== '', fn ($query) => $query->where('document_type', $documentType))
            ->when($partnerId !== '', fn ($query) => $query->where('partner_id', $partnerId))
            ->when($salesOrderId !== '', fn ($query) => $query->where('sales_order_id', $salesOrderId))
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $invoices,
            data: collect($invoices->items())
                ->map(fn (AccountingInvoice $invoice) => $this->mapInvoice($invoice, $user, false))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'status' => $status,
                'document_type' => $documentType,
                'partner_id' => $partnerId,
                'sales_order_id' => $salesOrderId,
            ],
        );
    }

    public function store(
        AccountingInvoiceStoreRequest $request,
        AccountingInvoiceWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('create', AccountingInvoice::class);

        $user = $request->user();
        $companyId = (string) $user?->current_company_id;

        if ($companyId === '') {
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

        return $this->respond(
            $this->mapInvoice(
                $invoice->fresh($this->invoiceRelationships())->loadCount(['lines', 'payments']),
                $request->user(),
            ),
            201,
        );
    }

    public function show(AccountingInvoice $invoice, Request $request): JsonResponse
    {
        $this->authorize('view', $invoice);

        $invoice->load($this->invoiceRelationships())
            ->loadCount(['lines', 'payments']);

        return $this->respond($this->mapInvoice($invoice, $request->user()));
    }

    public function update(
        AccountingInvoiceUpdateRequest $request,
        AccountingInvoice $invoice,
        AccountingInvoiceWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('update', $invoice);

        $invoice = $workflowService->updateDraft(
            invoice: $invoice,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return $this->respond(
            $this->mapInvoice(
                $invoice->fresh($this->invoiceRelationships())->loadCount(['lines', 'payments']),
                $request->user(),
            ),
        );
    }

    public function post(
        Request $request,
        AccountingInvoice $invoice,
        AccountingInvoiceWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('post', $invoice);

        $invoice = $workflowService->post($invoice, $request->user()?->id);

        return $this->respond(
            $this->mapInvoice(
                $invoice->fresh($this->invoiceRelationships())->loadCount(['lines', 'payments']),
                $request->user(),
            ),
        );
    }

    public function cancel(
        Request $request,
        AccountingInvoice $invoice,
        AccountingInvoiceWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('cancel', $invoice);

        $invoice = $workflowService->cancel($invoice, $request->user()?->id);

        return $this->respond(
            $this->mapInvoice(
                $invoice->fresh($this->invoiceRelationships())->loadCount(['lines', 'payments']),
                $request->user(),
            ),
        );
    }

    public function destroy(AccountingInvoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        if ($invoice->payments()->exists()) {
            abort(422, 'Invoice has payments and cannot be deleted.');
        }

        $invoice->lines()->delete();
        $invoice->delete();

        return $this->respondNoContent();
    }

    /**
     * @return array<int, string>
     */
    private function invoiceRelationships(): array
    {
        return [
            'partner:id,name,type',
            'salesOrder:id,order_number',
            'purchaseOrder:id,order_number',
            'lines.product:id,name,sku',
            'payments:id,invoice_id,payment_number,status,amount,payment_date',
        ];
    }

    private function mapInvoice(
        AccountingInvoice $invoice,
        ?User $user = null,
        bool $includeRelations = true,
    ): array {
        $payload = [
            'id' => $invoice->id,
            'partner_id' => $invoice->partner_id,
            'partner_name' => $invoice->partner?->name,
            'partner_type' => $invoice->partner?->type,
            'document_type' => $invoice->document_type,
            'sales_order_id' => $invoice->sales_order_id,
            'sales_order_number' => $invoice->salesOrder?->order_number,
            'purchase_order_id' => $invoice->purchase_order_id,
            'purchase_order_number' => $invoice->purchaseOrder?->order_number,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'delivery_status' => $invoice->delivery_status,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'currency_code' => $invoice->currency_code,
            'subtotal' => (float) $invoice->subtotal,
            'tax_total' => (float) $invoice->tax_total,
            'grand_total' => (float) $invoice->grand_total,
            'paid_total' => (float) $invoice->paid_total,
            'balance_due' => (float) $invoice->balance_due,
            'posted_at' => $invoice->posted_at?->toIso8601String(),
            'cancelled_at' => $invoice->cancelled_at?->toIso8601String(),
            'notes' => $invoice->notes,
            'lines_count' => (int) ($invoice->lines_count ?? $invoice->lines()->count()),
            'payments_count' => (int) ($invoice->payments_count ?? $invoice->payments()->count()),
            'updated_at' => $invoice->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $invoice) ?? false,
            'can_edit' => $user?->can('update', $invoice) ?? false,
            'can_delete' => $user?->can('delete', $invoice) ?? false,
            'can_post' => $user?->can('post', $invoice) ?? false,
            'can_cancel' => $user?->can('cancel', $invoice) ?? false,
        ];

        if (! $includeRelations) {
            return $payload;
        }

        $payload['lines'] = $invoice->relationLoaded('lines')
            ? $invoice->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => $line->product?->name,
                'product_sku' => $line->product?->sku,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'tax_rate' => (float) $line->tax_rate,
                'line_subtotal' => (float) $line->line_subtotal,
                'line_total' => (float) $line->line_total,
            ])->values()->all()
            : [];

        $payload['payments'] = $invoice->relationLoaded('payments')
            ? $invoice->payments
                ->sortByDesc('payment_date')
                ->values()
                ->map(fn ($payment) => [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'status' => $payment->status,
                    'amount' => (float) $payment->amount,
                    'payment_date' => $payment->payment_date?->toDateString(),
                ])
                ->all()
            : [];

        return $payload;
    }
}
