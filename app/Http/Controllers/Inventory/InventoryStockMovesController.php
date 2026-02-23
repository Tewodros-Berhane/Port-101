<?php

namespace App\Http\Controllers\Inventory;

use App\Modules\Inventory\InventoryStockWorkflowService;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Core\MasterData\Models\Product;
use App\Modules\Sales\Models\SalesOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryStockMoveStoreRequest;
use App\Http\Requests\Inventory\InventoryStockMoveUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryStockMovesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryStockMove::class);

        $user = $request->user();

        $query = InventoryStockMove::query()
            ->with([
                'product:id,name,sku',
                'sourceLocation:id,name',
                'destinationLocation:id,name',
                'salesOrder:id,order_number',
            ])
            ->latest('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        $moves = $query->paginate(30)->withQueryString();

        return Inertia::render('inventory/moves/index', [
            'moves' => $moves->through(function (InventoryStockMove $move) {
                return [
                    'id' => $move->id,
                    'reference' => $move->reference,
                    'move_type' => $move->move_type,
                    'status' => $move->status,
                    'product_name' => $move->product?->name,
                    'product_sku' => $move->product?->sku,
                    'source_location_name' => $move->sourceLocation?->name,
                    'destination_location_name' => $move->destinationLocation?->name,
                    'sales_order_number' => $move->salesOrder?->order_number,
                    'quantity' => (float) $move->quantity,
                    'reserved_at' => $move->reserved_at?->toIso8601String(),
                    'completed_at' => $move->completed_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', InventoryStockMove::class);

        return Inertia::render('inventory/moves/create', [
            'move' => [
                'reference' => '',
                'move_type' => InventoryStockMove::TYPE_RECEIPT,
                'source_location_id' => '',
                'destination_location_id' => '',
                'product_id' => '',
                'quantity' => 1,
                'related_sales_order_id' => '',
                'notes' => '',
            ],
            'moveTypes' => InventoryStockMove::TYPES,
            'products' => $this->productOptions(),
            'locations' => $this->locationOptions(),
            'salesOrders' => $this->salesOrderOptions(),
        ]);
    }

    public function store(InventoryStockMoveStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', InventoryStockMove::class);

        $user = $request->user();

        $move = InventoryStockMove::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'status' => InventoryStockMove::STATUS_DRAFT,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('company.inventory.moves.edit', $move)
            ->with('success', 'Stock move created.');
    }

    public function edit(InventoryStockMove $move): Response
    {
        $this->authorize('view', $move);

        return Inertia::render('inventory/moves/edit', [
            'move' => [
                'id' => $move->id,
                'reference' => $move->reference,
                'move_type' => $move->move_type,
                'status' => $move->status,
                'source_location_id' => $move->source_location_id,
                'destination_location_id' => $move->destination_location_id,
                'product_id' => $move->product_id,
                'quantity' => (float) $move->quantity,
                'related_sales_order_id' => $move->related_sales_order_id,
                'notes' => $move->notes,
                'reserved_at' => $move->reserved_at?->toIso8601String(),
                'completed_at' => $move->completed_at?->toIso8601String(),
                'cancelled_at' => $move->cancelled_at?->toIso8601String(),
            ],
            'moveTypes' => InventoryStockMove::TYPES,
            'products' => $this->productOptions(),
            'locations' => $this->locationOptions(),
            'salesOrders' => $this->salesOrderOptions(),
        ]);
    }

    public function update(
        InventoryStockMoveUpdateRequest $request,
        InventoryStockMove $move
    ): RedirectResponse {
        $this->authorize('update', $move);

        $move->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('company.inventory.moves.edit', $move)
            ->with('success', 'Stock move updated.');
    }

    public function reserve(
        Request $request,
        InventoryStockMove $move,
        InventoryStockWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('reserve', $move);

        $workflowService->reserve($move, $request->user()?->id);

        return redirect()
            ->route('company.inventory.moves.edit', $move)
            ->with('success', 'Stock move reserved.');
    }

    public function complete(
        Request $request,
        InventoryStockMove $move,
        InventoryStockWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('complete', $move);

        $workflowService->complete($move, $request->user()?->id);

        return redirect()
            ->route('company.inventory.moves.edit', $move)
            ->with('success', 'Stock move completed.');
    }

    public function cancel(
        Request $request,
        InventoryStockMove $move,
        InventoryStockWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('cancel', $move);

        $workflowService->cancel($move, $request->user()?->id);

        return redirect()
            ->route('company.inventory.moves.edit', $move)
            ->with('success', 'Stock move cancelled.');
    }

    public function destroy(InventoryStockMove $move): RedirectResponse
    {
        $this->authorize('delete', $move);

        $move->delete();

        return redirect()
            ->route('company.inventory.moves.index')
            ->with('success', 'Stock move removed.');
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
     * @return array<int, array{id: string, name: string, type: string}>
     */
    private function locationOptions(): array
    {
        return InventoryLocation::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn (InventoryLocation $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, order_number: string}>
     */
    private function salesOrderOptions(): array
    {
        return SalesOrder::query()
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'order_number'])
            ->map(fn (SalesOrder $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
            ])
            ->values()
            ->all();
    }
}


