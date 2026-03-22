<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Inventory\InventoryStockMoveStoreRequest;
use App\Http\Requests\Inventory\InventoryStockMoveUpdateRequest;
use App\Models\User;
use App\Modules\Inventory\InventoryStockWorkflowService;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryStockMovesController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InventoryStockMove::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $moveType = trim((string) $request->input('move_type', ''));
        $status = trim((string) $request->input('status', ''));
        $productId = trim((string) $request->input('product_id', ''));
        $sourceLocationId = trim((string) $request->input('source_location_id', ''));
        $destinationLocationId = trim((string) $request->input('destination_location_id', ''));
        $salesOrderId = trim((string) $request->input('related_sales_order_id', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['created_at', 'updated_at', 'reference', 'move_type', 'status', 'quantity', 'completed_at'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $moves = InventoryStockMove::query()
            ->with($this->moveRelationships())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('reference', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%"))
                        ->orWhereHas('sourceLocation', fn ($locationQuery) => $locationQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('destinationLocation', fn ($locationQuery) => $locationQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('salesOrder', fn ($salesOrderQuery) => $salesOrderQuery
                            ->where('order_number', 'like', "%{$search}%"));
                });
            })
            ->when($moveType !== '', fn ($query) => $query->where('move_type', $moveType))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($productId !== '', fn ($query) => $query->where('product_id', $productId))
            ->when($sourceLocationId !== '', fn ($query) => $query->where('source_location_id', $sourceLocationId))
            ->when($destinationLocationId !== '', fn ($query) => $query->where('destination_location_id', $destinationLocationId))
            ->when($salesOrderId !== '', fn ($query) => $query->where('related_sales_order_id', $salesOrderId))
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $moves,
            data: collect($moves->items())
                ->map(fn (InventoryStockMove $move) => $this->mapMove($move, $user, false))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'move_type' => $moveType,
                'status' => $status,
                'product_id' => $productId,
                'source_location_id' => $sourceLocationId,
                'destination_location_id' => $destinationLocationId,
                'related_sales_order_id' => $salesOrderId,
            ],
        );
    }

    public function store(InventoryStockMoveStoreRequest $request): JsonResponse
    {
        $this->authorize('create', InventoryStockMove::class);

        $move = InventoryStockMove::create([
            ...$request->validated(),
            'company_id' => $request->user()?->current_company_id,
            'status' => InventoryStockMove::STATUS_DRAFT,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return $this->respond(
            $this->mapMove(
                $move->fresh($this->moveRelationships()),
                $request->user(),
            ),
            201,
        );
    }

    public function show(InventoryStockMove $move, Request $request): JsonResponse
    {
        $this->authorize('view', $move);

        $move->load($this->moveRelationships());

        return $this->respond($this->mapMove($move, $request->user()));
    }

    public function update(
        InventoryStockMoveUpdateRequest $request,
        InventoryStockMove $move,
    ): JsonResponse {
        $this->authorize('update', $move);

        $move->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return $this->respond(
            $this->mapMove(
                $move->fresh($this->moveRelationships()),
                $request->user(),
            ),
        );
    }

    public function reserve(
        Request $request,
        InventoryStockMove $move,
        InventoryStockWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('reserve', $move);

        $move = $workflowService->reserve($move, $request->user()?->id);

        return $this->respond($this->mapMove($move->fresh($this->moveRelationships()), $request->user()));
    }

    public function dispatch(
        Request $request,
        InventoryStockMove $move,
        InventoryStockWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('complete', $move);

        $move = $workflowService->dispatch($move, $request->user()?->id);

        return $this->respond($this->mapMove($move->fresh($this->moveRelationships()), $request->user()));
    }

    public function receive(
        Request $request,
        InventoryStockMove $move,
        InventoryStockWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('complete', $move);

        $move = $workflowService->receive($move, $request->user()?->id);

        return $this->respond($this->mapMove($move->fresh($this->moveRelationships()), $request->user()));
    }

    public function complete(
        Request $request,
        InventoryStockMove $move,
        InventoryStockWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('complete', $move);

        $move = $workflowService->complete($move, $request->user()?->id);

        return $this->respond($this->mapMove($move->fresh($this->moveRelationships()), $request->user()));
    }

    public function cancel(
        Request $request,
        InventoryStockMove $move,
        InventoryStockWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('cancel', $move);

        $move = $workflowService->cancel($move, $request->user()?->id);

        return $this->respond($this->mapMove($move->fresh($this->moveRelationships()), $request->user()));
    }

    public function destroy(InventoryStockMove $move): JsonResponse
    {
        $this->authorize('delete', $move);

        $move->delete();

        return $this->respondNoContent();
    }

    /**
     * @return array<int, string>
     */
    private function moveRelationships(): array
    {
        return [
            'product:id,name,sku',
            'sourceLocation:id,code,name,type',
            'destinationLocation:id,code,name,type',
            'salesOrder:id,order_number',
            'reservedBy:id,name',
            'completedBy:id,name',
            'cancelledBy:id,name',
        ];
    }

    private function mapMove(
        InventoryStockMove $move,
        ?User $user = null,
        bool $includeNotes = true,
    ): array {
        $payload = [
            'id' => $move->id,
            'reference' => $move->reference,
            'move_type' => $move->move_type,
            'status' => $move->status,
            'product_id' => $move->product_id,
            'product_name' => $move->product?->name,
            'product_sku' => $move->product?->sku,
            'source_location_id' => $move->source_location_id,
            'source_location_code' => $move->sourceLocation?->code,
            'source_location_name' => $move->sourceLocation?->name,
            'destination_location_id' => $move->destination_location_id,
            'destination_location_code' => $move->destinationLocation?->code,
            'destination_location_name' => $move->destinationLocation?->name,
            'related_sales_order_id' => $move->related_sales_order_id,
            'related_sales_order_number' => $move->salesOrder?->order_number,
            'quantity' => (float) $move->quantity,
            'reserved_at' => $move->reserved_at?->toIso8601String(),
            'reserved_by' => $move->reserved_by,
            'reserved_by_name' => $move->reservedBy?->name,
            'completed_at' => $move->completed_at?->toIso8601String(),
            'completed_by' => $move->completed_by,
            'completed_by_name' => $move->completedBy?->name,
            'cancelled_at' => $move->cancelled_at?->toIso8601String(),
            'cancelled_by' => $move->cancelled_by,
            'cancelled_by_name' => $move->cancelledBy?->name,
            'updated_at' => $move->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $move) ?? false,
            'can_edit' => $user?->can('update', $move) ?? false,
            'can_delete' => $user?->can('delete', $move) ?? false,
            'can_reserve' => $user?->can('reserve', $move) ?? false,
            'can_complete' => $user?->can('complete', $move) ?? false,
            'can_cancel' => $user?->can('cancel', $move) ?? false,
            'can_dispatch' => ($user?->can('complete', $move) ?? false)
                && $move->move_type === InventoryStockMove::TYPE_DELIVERY,
            'can_receive' => ($user?->can('complete', $move) ?? false)
                && $move->move_type === InventoryStockMove::TYPE_RECEIPT,
        ];

        if (! $includeNotes) {
            return $payload;
        }

        $payload['notes'] = $move->notes;

        return $payload;
    }
}
