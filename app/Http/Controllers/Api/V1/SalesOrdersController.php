<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Sales\SalesOrderStoreRequest;
use App\Http\Requests\Sales\SalesOrderUpdateRequest;
use App\Models\User;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\SalesOrderWorkflowService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOrdersController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesOrder::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $requiresApproval = $this->booleanFilter($request, 'requires_approval');
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['created_at', 'order_number', 'status', 'order_date', 'grand_total', 'confirmed_at', 'updated_at'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $orders = SalesOrder::query()
            ->with(['partner:id,name', 'quote:id,quote_number', 'approvedBy:id,name', 'confirmedBy:id,name'])
            ->withCount('lines')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('partner', fn ($partnerQuery) => $partnerQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('quote', fn ($quoteQuery) => $quoteQuery->where('quote_number', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($requiresApproval !== null, fn ($query) => $query->where('requires_approval', $requiresApproval))
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $orders,
            data: collect($orders->items())
                ->map(fn (SalesOrder $order) => $this->mapOrder($order, $user, false))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'status' => $status,
                'requires_approval' => $requiresApproval,
            ],
        );
    }

    public function store(
        SalesOrderStoreRequest $request,
        SalesOrderWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('create', SalesOrder::class);

        $order = $workflowService->create($request->validated(), $request->user());

        return $this->respond($this->mapOrder($order, $request->user()), 201);
    }

    public function show(SalesOrder $order, Request $request): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load([
            'partner:id,name',
            'quote:id,quote_number',
            'approvedBy:id,name',
            'confirmedBy:id,name',
            'lines.product:id,name,sku',
        ])->loadCount('lines');

        return $this->respond($this->mapOrder($order, $request->user()));
    }

    public function update(
        SalesOrderUpdateRequest $request,
        SalesOrder $order,
        SalesOrderWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('update', $order);

        $order = $workflowService->update($order, $request->validated(), $request->user());

        return $this->respond($this->mapOrder($order, $request->user()));
    }

    public function approve(
        Request $request,
        SalesOrder $order,
        SalesOrderWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('approve', $order);

        $order = $workflowService->approve($order, $request->user());

        return $this->respond($this->mapOrder($order, $request->user()));
    }

    public function confirm(
        Request $request,
        SalesOrder $order,
        SalesOrderWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('confirm', $order);

        $order = $workflowService->confirm($order, $request->user());

        return $this->respond($this->mapOrder($order, $request->user()));
    }

    public function destroy(
        SalesOrder $order,
        SalesOrderWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('delete', $order);

        $workflowService->delete($order);

        return $this->respondNoContent();
    }

    private function mapOrder(SalesOrder $order, ?User $user = null, bool $includeLines = true): array
    {
        $payload = [
            'id' => $order->id,
            'quote_id' => $order->quote_id,
            'quote_number' => $order->quote?->quote_number,
            'partner_id' => $order->partner_id,
            'partner_name' => $order->partner?->name,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'order_date' => $order->order_date?->toDateString(),
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'tax_total' => (float) $order->tax_total,
            'grand_total' => (float) $order->grand_total,
            'requires_approval' => (bool) $order->requires_approval,
            'approved_by' => $order->approved_by,
            'approved_by_name' => $order->approvedBy?->name,
            'approved_at' => $order->approved_at?->toIso8601String(),
            'confirmed_by' => $order->confirmed_by,
            'confirmed_by_name' => $order->confirmedBy?->name,
            'confirmed_at' => $order->confirmed_at?->toIso8601String(),
            'lines_count' => (int) ($order->lines_count ?? $order->lines()->count()),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $order) ?? false,
            'can_edit' => $user?->can('update', $order) ?? false,
            'can_delete' => $user?->can('delete', $order) ?? false,
            'can_approve' => $user?->can('approve', $order) ?? false,
            'can_confirm' => $user?->can('confirm', $order) ?? false,
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
                'unit_price' => (float) $line->unit_price,
                'discount_percent' => (float) $line->discount_percent,
                'tax_rate' => (float) $line->tax_rate,
                'line_subtotal' => (float) $line->line_subtotal,
                'line_total' => (float) $line->line_total,
            ])->values()->all()
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
