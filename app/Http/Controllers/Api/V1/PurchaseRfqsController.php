<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Purchasing\PurchaseRfqStoreRequest;
use App\Http\Requests\Purchasing\PurchaseRfqUpdateRequest;
use App\Models\User;
use App\Modules\Purchasing\Models\PurchaseRfq;
use App\Modules\Purchasing\PurchasingOrderWorkflowService;
use App\Modules\Purchasing\PurchasingRfqWorkflowService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRfqsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseRfq::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $partnerId = trim((string) $request->input('partner_id', ''));
        $externalReference = trim((string) $request->input('external_reference', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['created_at', 'updated_at', 'rfq_date', 'valid_until', 'rfq_number', 'external_reference', 'status', 'grand_total'],
            defaultSort: 'rfq_date',
            defaultDirection: 'desc',
        );

        $rfqs = PurchaseRfq::query()
            ->with(['partner:id,name', 'order:id,rfq_id,order_number,status'])
            ->withCount('lines')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('rfq_number', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('partner', fn ($partnerQuery) => $partnerQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($partnerId !== '', fn ($query) => $query->where('partner_id', $partnerId))
            ->when($externalReference !== '', fn ($query) => $query->where('external_reference', $externalReference))
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $rfqs,
            data: collect($rfqs->items())
                ->map(fn (PurchaseRfq $rfq) => $this->mapRfq($rfq, $user, false))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'status' => $status,
                'partner_id' => $partnerId,
                'external_reference' => $externalReference,
            ],
        );
    }

    public function store(
        PurchaseRfqStoreRequest $request,
        PurchasingRfqWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('create', PurchaseRfq::class);

        $user = $request->user();
        $companyId = (string) $user?->current_company_id;

        if ($companyId === '') {
            abort(403, 'Company context not available.');
        }

        $rfq = $workflowService->createDraft(
            attributes: $request->validated(),
            companyId: $companyId,
            actorId: $user?->id,
        );

        return $this->respond(
            $this->mapRfq(
                $rfq->fresh(['partner:id,name', 'order:id,rfq_id,order_number,status', 'lines.product:id,name,sku']),
                $request->user(),
            ),
            201,
        );
    }

    public function show(PurchaseRfq $rfq, Request $request): JsonResponse
    {
        $this->authorize('view', $rfq);

        $rfq->load(['partner:id,name', 'order:id,rfq_id,order_number,status', 'lines.product:id,name,sku'])
            ->loadCount('lines');

        return $this->respond($this->mapRfq($rfq, $request->user()));
    }

    public function update(
        PurchaseRfqUpdateRequest $request,
        PurchaseRfq $rfq,
        PurchasingRfqWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('update', $rfq);

        $rfq = $workflowService->updateDraft(
            rfq: $rfq,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return $this->respond(
            $this->mapRfq(
                $rfq->fresh(['partner:id,name', 'order:id,rfq_id,order_number,status', 'lines.product:id,name,sku'])
                    ->loadCount('lines'),
                $request->user(),
            ),
        );
    }

    public function send(
        Request $request,
        PurchaseRfq $rfq,
        PurchasingRfqWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('send', $rfq);

        $rfq = $workflowService->send($rfq, $request->user()?->id);

        return $this->respond(
            $this->mapRfq(
                $rfq->fresh(['partner:id,name', 'order:id,rfq_id,order_number,status', 'lines.product:id,name,sku'])
                    ->loadCount('lines'),
                $request->user(),
            ),
        );
    }

    public function respondToVendor(
        Request $request,
        PurchaseRfq $rfq,
        PurchasingRfqWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('markVendorResponded', $rfq);

        $rfq = $workflowService->markVendorResponded($rfq, $request->user()?->id);

        return $this->respond(
            $this->mapRfq(
                $rfq->fresh(['partner:id,name', 'order:id,rfq_id,order_number,status', 'lines.product:id,name,sku'])
                    ->loadCount('lines'),
                $request->user(),
            ),
        );
    }

    public function select(
        Request $request,
        PurchaseRfq $rfq,
        PurchasingRfqWorkflowService $rfqWorkflowService,
        PurchasingOrderWorkflowService $orderWorkflowService,
    ): JsonResponse {
        $this->authorize('select', $rfq);

        $selectedRfq = $rfqWorkflowService->select($rfq, $request->user()?->id);
        $orderWorkflowService->createOrRefreshDraftFromRfq(
            $selectedRfq,
            $request->user()?->id,
        );

        return $this->respond(
            $this->mapRfq(
                $selectedRfq->fresh(['partner:id,name', 'order:id,rfq_id,order_number,status', 'lines.product:id,name,sku'])
                    ->loadCount('lines'),
                $request->user(),
            ),
        );
    }

    public function destroy(PurchaseRfq $rfq): JsonResponse
    {
        $this->authorize('delete', $rfq);

        if ($rfq->order()->exists()) {
            abort(422, 'Linked RFQs cannot be deleted once an order exists.');
        }

        $rfq->lines()->delete();
        $rfq->delete();

        return $this->respondNoContent();
    }

    private function mapRfq(
        PurchaseRfq $rfq,
        ?User $user = null,
        bool $includeLines = true,
    ): array {
        $payload = [
            'id' => $rfq->id,
            'external_reference' => $rfq->external_reference,
            'partner_id' => $rfq->partner_id,
            'partner_name' => $rfq->partner?->name,
            'rfq_number' => $rfq->rfq_number,
            'status' => $rfq->status,
            'rfq_date' => $rfq->rfq_date?->toDateString(),
            'valid_until' => $rfq->valid_until?->toDateString(),
            'subtotal' => (float) $rfq->subtotal,
            'tax_total' => (float) $rfq->tax_total,
            'grand_total' => (float) $rfq->grand_total,
            'sent_at' => $rfq->sent_at?->toIso8601String(),
            'vendor_responded_at' => $rfq->vendor_responded_at?->toIso8601String(),
            'selected_at' => $rfq->selected_at?->toIso8601String(),
            'notes' => $rfq->notes,
            'order_id' => $rfq->order?->id,
            'order_number' => $rfq->order?->order_number,
            'order_status' => $rfq->order?->status,
            'lines_count' => (int) ($rfq->lines_count ?? $rfq->lines()->count()),
            'updated_at' => $rfq->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $rfq) ?? false,
            'can_edit' => $user?->can('update', $rfq) ?? false,
            'can_delete' => $user?->can('delete', $rfq) ?? false,
            'can_send' => $user?->can('send', $rfq) ?? false,
            'can_respond' => $user?->can('markVendorResponded', $rfq) ?? false,
            'can_select' => $user?->can('select', $rfq) ?? false,
        ];

        if (! $includeLines) {
            return $payload;
        }

        $payload['lines'] = $rfq->relationLoaded('lines')
            ? $rfq->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => $line->product?->name,
                'product_sku' => $line->product?->sku,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_cost,
                'tax_rate' => (float) $line->tax_rate,
                'line_subtotal' => (float) $line->line_subtotal,
                'line_total' => (float) $line->line_total,
            ])->values()->all()
            : [];

        return $payload;
    }
}
