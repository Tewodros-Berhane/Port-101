<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Purchasing\PurchaseOrderReceiveRequest;
use App\Http\Requests\Purchasing\PurchaseOrderStoreRequest;
use App\Http\Requests\Purchasing\PurchaseOrderUpdateRequest;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\PurchasingOrderWorkflowService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrdersController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $partnerId = trim((string) $request->input('partner_id', ''));
        $rfqId = trim((string) $request->input('rfq_id', ''));
        $externalReference = trim((string) $request->input('external_reference', ''));
        $requiresApproval = $this->booleanFilter($request, 'requires_approval');
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['created_at', 'updated_at', 'order_date', 'order_number', 'external_reference', 'status', 'grand_total', 'ordered_at'],
            defaultSort: 'order_date',
            defaultDirection: 'desc',
        );

        $orders = PurchaseOrder::query()
            ->with(['partner:id,name', 'rfq:id,rfq_number', 'approvedBy:id,name', 'orderedBy:id,name'])
            ->withCount(['lines', 'vendorBills'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('order_number', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('partner', fn ($partnerQuery) => $partnerQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('rfq', fn ($rfqQuery) => $rfqQuery->where('rfq_number', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($partnerId !== '', fn ($query) => $query->where('partner_id', $partnerId))
            ->when($rfqId !== '', fn ($query) => $query->where('rfq_id', $rfqId))
            ->when($externalReference !== '', fn ($query) => $query->where('external_reference', $externalReference))
            ->when($requiresApproval !== null, fn ($query) => $query->where('requires_approval', $requiresApproval))
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $orders,
            data: collect($orders->items())
                ->map(fn (PurchaseOrder $order) => $this->mapOrder($order, $user, false))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'status' => $status,
                'partner_id' => $partnerId,
                'rfq_id' => $rfqId,
                'external_reference' => $externalReference,
                'requires_approval' => $requiresApproval,
            ],
        );
    }

    public function store(
        PurchaseOrderStoreRequest $request,
        PurchasingOrderWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('create', PurchaseOrder::class);

        $user = $request->user();
        $companyId = (string) $user?->current_company_id;

        if ($companyId === '') {
            abort(403, 'Company context not available.');
        }

        $order = $workflowService->createDraft(
            attributes: $request->validated(),
            companyId: $companyId,
            actorId: $user?->id,
        );

        return $this->respond(
            $this->mapOrder(
                $order->fresh($this->orderRelationships())->loadCount(['lines', 'vendorBills']),
                $request->user(),
            ),
            201,
        );
    }

    public function show(PurchaseOrder $order, Request $request): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load($this->orderRelationships())
            ->loadCount(['lines', 'vendorBills']);

        return $this->respond($this->mapOrder($order, $request->user()));
    }

    public function update(
        PurchaseOrderUpdateRequest $request,
        PurchaseOrder $order,
        PurchasingOrderWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('update', $order);

        $order = $workflowService->updateDraft(
            order: $order,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return $this->respond(
            $this->mapOrder(
                $order->fresh($this->orderRelationships())->loadCount(['lines', 'vendorBills']),
                $request->user(),
            ),
        );
    }

    public function approve(
        Request $request,
        PurchaseOrder $order,
        PurchasingOrderWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('approve', $order);

        $order = $workflowService->approve($order, $request->user()?->id);

        return $this->respond(
            $this->mapOrder(
                $order->fresh($this->orderRelationships())->loadCount(['lines', 'vendorBills']),
                $request->user(),
            ),
        );
    }

    public function confirm(
        Request $request,
        PurchaseOrder $order,
        PurchasingOrderWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('place', $order);

        $order = $workflowService->place($order, $request->user()?->id);

        return $this->respond(
            $this->mapOrder(
                $order->fresh($this->orderRelationships())->loadCount(['lines', 'vendorBills']),
                $request->user(),
            ),
        );
    }

    public function receive(
        PurchaseOrderReceiveRequest $request,
        PurchaseOrder $order,
        PurchasingOrderWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('receive', $order);

        $lineQuantities = collect($request->validated('lines', []))
            ->mapWithKeys(fn (array $line) => [
                (string) $line['line_id'] => (float) $line['quantity'],
            ])
            ->all();

        $order = $workflowService->receive($order, $lineQuantities, $request->user()?->id);

        return $this->respond(
            $this->mapOrder(
                $order->fresh($this->orderRelationships())->loadCount(['lines', 'vendorBills']),
                $request->user(),
            ),
        );
    }

    public function destroy(PurchaseOrder $order): JsonResponse
    {
        $this->authorize('delete', $order);

        if ($order->vendorBills()->exists()) {
            abort(422, 'Order has linked vendor bills and cannot be deleted.');
        }

        $order->lines()->delete();
        $order->delete();

        return $this->respondNoContent();
    }

    /**
     * @return array<int, string>
     */
    private function orderRelationships(): array
    {
        return [
            'partner:id,name',
            'rfq:id,rfq_number',
            'approvedBy:id,name',
            'orderedBy:id,name',
            'lines.product:id,name,sku',
            'vendorBills:id,purchase_order_id,invoice_number,document_type,status,invoice_date,grand_total,balance_due',
        ];
    }

    private function mapOrder(
        PurchaseOrder $order,
        ?User $user = null,
        bool $includeLines = true,
    ): array {
        $payload = [
            'id' => $order->id,
            'external_reference' => $order->external_reference,
            'rfq_id' => $order->rfq_id,
            'rfq_number' => $order->rfq?->rfq_number,
            'partner_id' => $order->partner_id,
            'partner_name' => $order->partner?->name,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'order_date' => $order->order_date?->toDateString(),
            'subtotal' => (float) $order->subtotal,
            'tax_total' => (float) $order->tax_total,
            'grand_total' => (float) $order->grand_total,
            'requires_approval' => (bool) $order->requires_approval,
            'approved_by' => $order->approved_by,
            'approved_by_name' => $order->approvedBy?->name,
            'approved_at' => $order->approved_at?->toIso8601String(),
            'ordered_by' => $order->ordered_by,
            'ordered_by_name' => $order->orderedBy?->name,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'received_at' => $order->received_at?->toIso8601String(),
            'billed_at' => $order->billed_at?->toIso8601String(),
            'notes' => $order->notes,
            'lines_count' => (int) ($order->lines_count ?? $order->lines()->count()),
            'vendor_bills_count' => (int) ($order->vendor_bills_count ?? $order->vendorBills()->count()),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $order) ?? false,
            'can_edit' => $user?->can('update', $order) ?? false,
            'can_delete' => $user?->can('delete', $order) ?? false,
            'can_approve' => $user?->can('approve', $order) ?? false,
            'can_confirm' => $user?->can('place', $order) ?? false,
            'can_receive' => $user?->can('receive', $order) ?? false,
        ];

        if (! $includeLines) {
            return $payload;
        }

        $payload['lines'] = $order->relationLoaded('lines')
            ? $order->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => $line->product?->name,
                'product_sku' => $line->product?->sku,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'received_quantity' => (float) $line->received_quantity,
                'unit_cost' => (float) $line->unit_cost,
                'tax_rate' => (float) $line->tax_rate,
                'line_subtotal' => (float) $line->line_subtotal,
                'line_total' => (float) $line->line_total,
            ])->values()->all()
            : [];

        $payload['vendor_bills'] = $order->relationLoaded('vendorBills')
            ? $order->vendorBills
                ->where('document_type', AccountingInvoice::TYPE_VENDOR_BILL)
                ->values()
                ->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'invoice_date' => $invoice->invoice_date?->toDateString(),
                    'grand_total' => (float) $invoice->grand_total,
                    'balance_due' => (float) $invoice->balance_due,
                ])
                ->all()
            : [];

        return $payload;
    }

    private function booleanFilter(Request $request, string $key): ?bool
    {
        if (! $request->query->has($key)) {
            return null;
        }

        return filter_var($request->query($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
